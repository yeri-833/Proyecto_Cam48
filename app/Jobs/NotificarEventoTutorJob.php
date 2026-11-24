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
            Log::warning('[NotificarEventoTutorJob] Falta el identificador del evento.');

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
            Log::info('[NotificarEventoTutorJob] Notificación de evento ya procesada.');

            return;
        }

        if ($estadoLease === 'LOCKED_BY_OTHER') {
            Log::info('[NotificarEventoTutorJob] Trabajo actualmente en proceso por otro worker activo, omitiendo colisión.');
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
                    $error = $this->procesarCanalProveedor(
                        'WhatsApp',
                        fn (): array => $whatsappService->enviarMensajeDirectoDetallado($telefonoLimpio, $this->mensajeWhatsapp),
                    );
                    if ($error !== null) {
                        $erroresCanales[] = $error;
                    }
                }
            } elseif ($this->debeProcesarCanal('whatsapp')) {
                $this->registrarYPersistirCanal('whatsapp', self::CANAL_NO_CONFIGURADO);
            } else {
                $canalesConfigurados++;
            }

            // 2. Telegram (si el tutor tiene Chat ID registrado)
            $tieneTelegram = ! empty($this->tutor->t1_telegram_chat_id);
            if ($tieneTelegram) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('telegram')) {
                    $error = $this->procesarCanalProveedor(
                        'Telegram',
                        fn (): array => $telegramService->enviarAvisoEventoDirectoDetallado(
                            $this->tutor->t1_telegram_chat_id,
                            $this->nombreEvento,
                            $this->fecha,
                            $this->ubicacion
                        ),
                    );
                    if ($error !== null) {
                        $erroresCanales[] = $error;
                    }
                }
            } elseif ($this->debeProcesarCanal('telegram')) {
                $this->registrarYPersistirCanal('telegram', self::CANAL_NO_CONFIGURADO);
            } else {
                $canalesConfigurados++;
            }

            // 3. Email
            $tieneEmail = ! empty($this->tutor->t1_email);
            if ($tieneEmail) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('email')) {
                    try {
                        Mail::to($this->tutor->t1_email)
                            ->send(new AvisoEventoMail($this->nombreEvento, $this->fecha, $this->ubicacion));
                        $this->registrarYPersistirCanal('email', self::CANAL_ACEPTADO);
                    } catch (\Throwable $eMail) {
                        $estadoMail = $this->esErrorIncierto($eMail->getMessage()) ? self::CANAL_INCIERTO : self::CANAL_FALLIDO;
                        $error = $estadoMail === self::CANAL_INCIERTO ? 'Email: resultado incierto del envío.' : 'Email: envío fallido.';
                        $this->registrarYPersistirCanal('email', $estadoMail, $error);
                        $erroresCanales[] = $error;
                        Log::error('[Mail] Error al enviar correo de evento.', ['exception_type' => $eMail::class]);
                    }
                }
            } elseif ($this->debeProcesarCanal('email')) {
                $this->registrarYPersistirCanal('email', self::CANAL_NO_CONFIGURADO);
            } else {
                $canalesConfigurados++;
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

            $wsAceptado = $this->isCanalAceptado('whatsapp');
            $tgAceptado = $this->isCanalAceptado('telegram');
            $emAceptado = $this->isCanalAceptado('email');

            $wsIncierto = $this->isCanalIncierto('whatsapp');
            $tgIncierto = $this->isCanalIncierto('telegram');
            $emIncierto = $this->isCanalIncierto('email');

            $wsFallido = $tieneTelefono && $this->getEstadoCanal('whatsapp') === self::CANAL_FALLIDO;
            $tgFallido = $tieneTelegram && $this->getEstadoCanal('telegram') === self::CANAL_FALLIDO;
            $emFallido = $tieneEmail && $this->getEstadoCanal('email') === self::CANAL_FALLIDO;

            $aceptados = ($wsAceptado ? 1 : 0) + ($tgAceptado ? 1 : 0) + ($emAceptado ? 1 : 0);
            $inciertos = ($wsIncierto ? 1 : 0) + ($tgIncierto ? 1 : 0) + ($emIncierto ? 1 : 0);
            $fallidos = ($wsFallido ? 1 : 0) + ($tgFallido ? 1 : 0) + ($emFallido ? 1 : 0);

            $ultimoError = ! empty($erroresCanales) ? implode(' | ', $erroresCanales) : null;

            // Confirmar en convocatoria si al menos un canal fue aceptado
            if ($aceptados > 0 && Schema::hasTable('evento_convocatorias')) {
                DB::table('evento_convocatorias')
                    ->where('evento_id', $this->eventoId)
                    ->where('tutor_id', $this->tutor->id)
                    ->update(['notificado' => true, 'updated_at' => now()]);
            }

            if ($inciertos > 0 && ! $this->tienePersistenciaPorCanal()) {
                $this->completeNotificationLease(self::ESTADO_INCIERTO, [], $ultimoError, $aceptados > 0);

                return;
            }

            // 1. Éxito total: todos los canales configurados fueron aceptados
            if ($aceptados === $canalesConfigurados) {
                $this->completeNotificationLease(self::ESTADO_ACEPTADO_PROVEEDOR, [], null, true);

                return;
            }

            // 2. Al menos un canal fue aceptado
            if ($aceptados > 0) {
                if ($fallidos > 0) {
                    $this->saveInterimLeaseState(self::ESTADO_PARCIAL, $ultimoError);
                    throw new RuntimeException('Entrega parcial de aviso de evento.');
                }

                // Sin fallidos pero con canales inciertos: estado global PARCIAL (nunca ACEPTADO_PROVEEDOR)
                $this->completeNotificationLease(self::ESTADO_PARCIAL, [], $ultimoError, true);

                return;
            }

            // 3. Ningún canal fue aceptado
            if ($inciertos === $canalesConfigurados) {
                // Todos los canales configurados quedaron en INCIERTO
                $this->completeNotificationLease(self::ESTADO_INCIERTO, [], $ultimoError, false);

                return;
            }

            if ($fallidos > 0) {
                $estadoInterim = ($inciertos > 0) ? self::ESTADO_PARCIAL : self::ESTADO_FALLIDO;
                $this->saveInterimLeaseState($estadoInterim, $ultimoError);
                throw new RuntimeException('Quedan canales fallidos para el aviso de evento.');
            }
        } catch (\Throwable $e) {
            Log::error('[NotificarEventoTutorJob] Fallo de procesamiento.', ['exception_type' => $e::class]);

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

        Log::error('[NotificarEventoTutorJob] Se agotaron los intentos del trabajo.');
    }
}
