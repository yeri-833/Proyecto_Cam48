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
        // Nunca utilizar el nombre del evento ni actualizar convocatorias de manera ambigua.
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

        $detalleCanales = [
            'whatsapp' => 'NO_CONFIGURADO',
            'telegram' => 'NO_CONFIGURADO',
            'email' => 'NO_CONFIGURADO',
        ];

        try {
            $canalesIntentados = 0;
            $canalesExitosos = 0;
            $erroresCanales = [];

            // 1. Enviar WhatsApp en segundo plano
            if (! empty($this->tutor->t1_telefono)) {
                $telefonoLimpio = preg_replace('/[^0-9]/', '', $this->tutor->t1_telefono);
                if (! empty($telefonoLimpio)) {
                    $canalesIntentados++;
                    $resWs = $whatsappService->enviarMensajeDirecto($telefonoLimpio, $this->mensajeWhatsapp);
                    if ($resWs) {
                        $canalesExitosos++;
                        $detalleCanales['whatsapp'] = self::ESTADO_ACEPTADO_PROVEEDOR;
                    } else {
                        $detalleCanales['whatsapp'] = self::ESTADO_FALLIDO;
                        $erroresCanales[] = 'WhatsApp: proveedor rechazó el envío o devolvió error';
                    }
                }
            }

            // 2. Enviar Telegram en segundo plano (si el tutor tiene Chat ID registrado)
            if (! empty($this->tutor->t1_telegram_chat_id)) {
                $canalesIntentados++;
                $resTg = $telegramService->enviarAvisoEventoDirecto(
                    $this->tutor->t1_telegram_chat_id,
                    $this->nombreEvento,
                    $this->fecha,
                    $this->ubicacion
                );
                if ($resTg) {
                    $canalesExitosos++;
                    $detalleCanales['telegram'] = self::ESTADO_ACEPTADO_PROVEEDOR;
                } else {
                    $detalleCanales['telegram'] = self::ESTADO_FALLIDO;
                    $erroresCanales[] = 'Telegram: proveedor rechazó el envío o devolvió error';
                }
            }

            // 3. Enviar Correo en segundo plano
            if (! empty($this->tutor->t1_email)) {
                $canalesIntentados++;
                try {
                    Mail::to($this->tutor->t1_email)
                        ->send(new AvisoEventoMail($this->nombreEvento, $this->fecha, $this->ubicacion));
                    $canalesExitosos++;
                    $detalleCanales['email'] = self::ESTADO_ACEPTADO_PROVEEDOR;
                } catch (\Throwable $eMail) {
                    $detalleCanales['email'] = self::ESTADO_FALLIDO;
                    $erroresCanales[] = 'Email: '.$eMail->getMessage();
                    Log::error('[Mail] [FALLIDO] Error al enviar correo de evento institucional: '.$eMail->getMessage());
                }
            }

            // Si no había ningún canal configurado, registrar SIN_CANAL_DISPONIBLE y no marcar notificado
            if ($canalesIntentados === 0) {
                $this->completeNotificationLease(
                    self::ESTADO_SIN_CANAL,
                    $detalleCanales,
                    'Tutor sin canales de mensajería configurados.',
                    false
                );

                return;
            }

            // Si se intentaron canales y ninguno tuvo éxito, fallar para reintentar
            if ($canalesExitosos === 0) {
                $detalles = implode(' | ', $erroresCanales);
                $this->failNotificationLease("Fallo de entrega en todos los canales disponibles: {$detalles}", $detalleCanales);
                throw new \RuntimeException("Fallo de entrega en todos los canales disponibles: {$detalles}");
            }

            // 4. Finalizar lease con estado real (ACEPTADO_PROVEEDOR o PARCIAL) solo si seguimos siendo propietarios
            $estadoFinal = ($canalesExitosos < $canalesIntentados)
                ? self::ESTADO_PARCIAL
                : self::ESTADO_ACEPTADO_PROVEEDOR;

            $ultimoError = ! empty($erroresCanales) ? implode(' | ', $erroresCanales) : null;

            $leaseConfirmado = $estadoLease === 'NO_TABLE'
                ? true
                : $this->completeNotificationLease($estadoFinal, $detalleCanales, $ultimoError, true);

            // 5. Confirmar notificación en convocatoria restringiendo estrictamente a este evento_id y tutor_id
            if ($leaseConfirmado && Schema::hasTable('evento_convocatorias')) {
                DB::table('evento_convocatorias')
                    ->where('evento_id', $this->eventoId)
                    ->where('tutor_id', $this->tutor->id)
                    ->update(['notificado' => true, 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            $this->failNotificationLease($e->getMessage(), $detalleCanales);

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
