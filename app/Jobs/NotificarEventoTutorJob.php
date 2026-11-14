<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithNotificationLease;
use App\Mail\AvisoEventoMail;
use App\Models\Tutor;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class NotificarEventoTutorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithNotificationLease, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 60, 180];

    protected $tutor;

    protected $mensajeWhatsapp;

    protected $nombreEvento;

    protected $fecha;

    protected $ubicacion;

    public ?int $eventoId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(Tutor $tutor, $mensajeWhatsapp, $nombreEvento, $fecha, $ubicacion, ?int $eventoId = null)
    {
        $this->tutor = $tutor;
        $this->mensajeWhatsapp = $mensajeWhatsapp;
        $this->nombreEvento = $nombreEvento;
        $this->fecha = $fecha;
        $this->ubicacion = $ubicacion;
        $this->eventoId = $eventoId;
    }

    /**
     * Resuelve la clave de idempotencia determinista para la notificación del evento.
     */
    public function resolverIdempotencyKey(): string
    {
        $eventoRef = $this->eventoId !== null ? "id:{$this->eventoId}" : 'sin_evento';

        return hash('sha256', "evento:{$eventoRef}:tutor:{$this->tutor->id}:fecha:{$this->fecha}");
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService, TelegramService $telegramService): void
    {
        // Requisito estricto: si falta el identificador explícito del evento, detener de forma segura.
        if ($this->eventoId === null || $this->eventoId <= 0) {
            Log::warning('[NotificarEventoTutorJob] Deteniendo ejecución de forma segura: falta eventoId explícito.', [
                'tutor_id' => $this->tutor->id ?? null,
                'nombre_evento' => $this->nombreEvento,
            ]);

            return;
        }

        $idempotencyKey = $this->resolverIdempotencyKey();
        $estadoLease = $this->acquireNotificationLease(
            $idempotencyKey,
            'Tutor',
            (string) $this->tutor->id,
            'MULTICANAL'
        );

        // POLÍTICA FAIL-CLOSED ESTRICTA: Sin tabla de persistencia, abortar inmediatamente sin contactar proveedores
        if ($estadoLease === 'NO_TABLE') {
            throw new RuntimeException("PERSISTENCIA OBLIGATORIA: La tabla 'notificaciones_procesadas' no existe. Notificación de evento abortada para prevenir envíos duplicados o no auditados.");
        }

        if ($estadoLease === 'ALREADY_COMPLETED') {
            Log::info("[NotificarEventoTutorJob] Notificación ya procesada para tutor {$this->tutor->id} en evento {$this->eventoId}, omitiendo duplicado.");

            return;
        }

        if ($estadoLease === 'LOCKED_BY_OTHER') {
            Log::info("[NotificarEventoTutorJob] Trabajo actualmente en proceso por otro worker activo, omitiendo colisión.");
            if ($this->job !== null) {
                $this->release(10);
            }

            return;
        }

        try {
            $canalesConfigurados = 0;
            $erroresCanales = [];

            // 1. WhatsApp
            $telefonoLimpio = ! empty($this->tutor->t1_telefono)
                ? preg_replace('/[^0-9]/', '', (string) $this->tutor->t1_telefono)
                : '';
            $tieneTelefono = ! empty($telefonoLimpio);

            if ($tieneTelefono) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('whatsapp')) {
                    try {
                        $resWs = $whatsappService->enviarMensajeDirecto($telefonoLimpio, $this->mensajeWhatsapp);
                        if ($resWs) {
                            $this->registrarResultadoCanal('whatsapp', self::CANAL_ACEPTADO);
                        } else {
                            $this->registrarResultadoCanal('whatsapp', self::CANAL_FALLIDO, 'WhatsApp: proveedor rechazó el envío');
                            $erroresCanales[] = 'WhatsApp: proveedor rechazó el envío';
                        }
                    } catch (\Throwable $eWs) {
                        $estadoWs = $this->esErrorIncierto($eWs->getMessage()) ? self::CANAL_INCIERTO : self::CANAL_FALLIDO;
                        $this->registrarResultadoCanal('whatsapp', $estadoWs, $eWs->getMessage());
                        $erroresCanales[] = 'WhatsApp: '.$eWs->getMessage();
                    }
                }
            } else {
                if (! $this->isCanalAceptado('whatsapp')) {
                    $this->registrarResultadoCanal('whatsapp', self::CANAL_NO_CONFIGURADO);
                }
            }

            // 2. Telegram (si el tutor tiene Chat ID registrado)
            $tieneTelegram = ! empty($this->tutor->t1_telegram_chat_id);
            if ($tieneTelegram) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('telegram')) {
                    try {
                        $resTg = $telegramService->enviarAvisoEventoDirecto(
                            $this->tutor->t1_telegram_chat_id,
                            $this->nombreEvento,
                            $this->fecha,
                            $this->ubicacion
                        );
                        if ($resTg) {
                            $this->registrarResultadoCanal('telegram', self::CANAL_ACEPTADO);
                        } else {
                            $this->registrarResultadoCanal('telegram', self::CANAL_FALLIDO, 'Telegram: proveedor rechazó el envío');
                            $erroresCanales[] = 'Telegram: proveedor rechazó el envío';
                        }
                    } catch (\Throwable $eTg) {
                        $estadoTg = $this->esErrorIncierto($eTg->getMessage()) ? self::CANAL_INCIERTO : self::CANAL_FALLIDO;
                        $this->registrarResultadoCanal('telegram', $estadoTg, $eTg->getMessage());
                        $erroresCanales[] = 'Telegram: '.$eTg->getMessage();
                    }
                }
            } else {
                if (! $this->isCanalAceptado('telegram')) {
                    $this->registrarResultadoCanal('telegram', self::CANAL_NO_CONFIGURADO);
                }
            }

            // 3. Email
            $tieneEmail = ! empty($this->tutor->t1_email);
            if ($tieneEmail) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('email')) {
                    try {
                        Mail::to($this->tutor->t1_email)
                            ->send(new AvisoEventoMail($this->nombreEvento, $this->fecha, $this->ubicacion));
                        $this->registrarResultadoCanal('email', self::CANAL_ACEPTADO);
                    } catch (\Throwable $eMail) {
                        $estadoMail = $this->esErrorIncierto($eMail->getMessage()) ? self::CANAL_INCIERTO : self::CANAL_FALLIDO;
                        $this->registrarResultadoCanal('email', $estadoMail, $eMail->getMessage());
                        $erroresCanales[] = 'Email: '.$eMail->getMessage();
                        Log::error('[Mail] [FALLIDO] Error al enviar correo de evento: '.$eMail->getMessage());
                    }
                }
            } else {
                if (! $this->isCanalAceptado('email')) {
                    $this->registrarResultadoCanal('email', self::CANAL_NO_CONFIGURADO);
                }
            }

            // Si no había ningún canal configurado
            if ($canalesConfigurados === 0) {
                $this->completeNotificationLease(
                    self::ESTADO_SIN_CANAL,
                    [],
                    'Tutor sin canales de mensajería configurados.',
                    false
                );

                return;
            }

            $hayAceptado = $this->isCanalAceptado('whatsapp')
                || $this->isCanalAceptado('telegram')
                || $this->isCanalAceptado('email');

            $hayFallido = ($tieneTelefono && $this->getEstadoCanal('whatsapp') === self::CANAL_FALLIDO)
                || ($tieneTelegram && $this->getEstadoCanal('telegram') === self::CANAL_FALLIDO)
                || ($tieneEmail && $this->getEstadoCanal('email') === self::CANAL_FALLIDO);

            $ultimoError = ! empty($erroresCanales) ? implode(' | ', $erroresCanales) : null;

            // Confirmar en convocatoria si al menos un canal fue aceptado
            if ($hayAceptado && Schema::hasTable('evento_convocatorias')) {
                DB::table('evento_convocatorias')
                    ->where('evento_id', $this->eventoId)
                    ->where('tutor_id', $this->tutor->id)
                    ->update(['notificado' => true, 'updated_at' => now()]);
            }

            // Caso 1: Todos los canales configurados fueron aceptados (o sin fallos)
            if (! $hayFallido && $hayAceptado) {
                $this->completeNotificationLease(self::ESTADO_ACEPTADO_PROVEEDOR, [], null, true);

                return;
            }

            // Caso 2: Entrega parcial (al menos un canal fue aceptado pero otro falló)
            if ($hayAceptado) {
                $this->saveInterimLeaseState(self::ESTADO_PARCIAL, $ultimoError);
                throw new RuntimeException("Entrega parcial en evento {$this->eventoId} para tutor {$this->tutor->id}: {$ultimoError}");
            }

            // Caso 3: Fallo en todos los canales intentados
            $this->saveInterimLeaseState(self::ESTADO_FALLIDO, $ultimoError);
            throw new RuntimeException("Fallo de entrega en todos los canales disponibles: {$ultimoError}");
        } catch (\Throwable $e) {
            Log::error('Error en NotificarEventoTutorJob: '.$e->getMessage(), [
                'tutor_id' => $this->tutor->id ?? null,
                'evento_id' => $this->eventoId,
            ]);

            throw $e;
        }
    }

    /**
     * Manejo de fallo terminal tras agotar reintentos.
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->eventoId === null || $this->eventoId <= 0) {
            return;
        }

        $this->handleTerminalFailure($this->resolverIdempotencyKey(), $exception, $this->tries);

        Log::error("[NotificarEventoTutorJob] Fallo terminal al notificar evento {$this->eventoId} a tutor {$this->tutor->id}: ".($exception ? $exception->getMessage() : 'Error desconocido'));
    }
}
