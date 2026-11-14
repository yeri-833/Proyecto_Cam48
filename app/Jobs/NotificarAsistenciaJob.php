<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithNotificationLease;
use App\Models\Alumno;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotificarAsistenciaJob implements ShouldQueue
{
    use InteractsWithNotificationLease, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public $alumno;

    public $tipoPase;

    public $horaFormateada;

    public $nombreCompleto;

    public ?string $fechaRegistro = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Alumno $alumno,
        string $tipoPase,
        string $horaFormateada,
        string $nombreCompleto,
        ?string $fechaRegistro = null
    ) {
        $this->alumno = $alumno;
        $this->tipoPase = $tipoPase;
        $this->horaFormateada = $horaFormateada;
        $this->nombreCompleto = $nombreCompleto;
        $this->fechaRegistro = $fechaRegistro ?? now()->toDateString();
    }

    public function resolverIdempotencyKey(): string
    {
        $fecha = $this->fechaRegistro ?? now()->toDateString();

        return hash('sha256', "asistencia:alumno:{$this->alumno->matricula}:pase:{$this->tipoPase}:fecha:{$fecha}:hora:{$this->horaFormateada}");
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService, TelegramService $telegramService): void
    {
        $idempotencyKey = null;

        if ($this->alumno) {
            $idempotencyKey = $this->resolverIdempotencyKey();
            $estadoLease = $this->acquireNotificationLease(
                $idempotencyKey,
                'Alumno',
                (string) $this->alumno->matricula,
                'MULTICANAL'
            );

            if ($estadoLease === 'ALREADY_COMPLETED') {
                Log::info("[NotificarAsistenciaJob] Notificación de asistencia ya procesada para {$this->alumno->matricula}, omitiendo duplicado.");

                return;
            }

            if ($estadoLease === 'LOCKED_BY_OTHER') {
                Log::info('[NotificarAsistenciaJob] Trabajo en proceso por otro worker activo, omitiendo.');
                if ($this->job !== null) {
                    $this->release(10);
                }

                return;
            }
        }

        $detalleCanales = [
            'whatsapp' => 'NO_CONFIGURADO',
            'telegram' => 'NO_CONFIGURADO',
        ];

        try {
            $canalesIntentados = 0;
            $canalesExitosos = 0;
            $errores = [];

            if ($this->alumno && $this->alumno->tutor) {
                // 1. WhatsApp
                if (! empty($this->alumno->tutor->t1_telefono)) {
                    $canalesIntentados++;
                    $resWs = $whatsAppService->enviarAvisoAsistencia(
                        $this->alumno->tutor->t1_telefono,
                        $this->nombreCompleto,
                        $this->horaFormateada,
                        $this->tipoPase
                    );
                    if ($resWs) {
                        $canalesExitosos++;
                        $detalleCanales['whatsapp'] = self::ESTADO_ACEPTADO_PROVEEDOR;
                    } else {
                        $detalleCanales['whatsapp'] = self::ESTADO_FALLIDO;
                        $errores[] = 'WhatsApp: no confirmado por el proveedor';
                    }
                }

                // 2. Telegram (Si tiene Chat ID registrado)
                if (! empty($this->alumno->tutor->t1_telegram_chat_id)) {
                    $canalesIntentados++;
                    $resTg = $telegramService->enviarAvisoAsistencia(
                        $this->alumno->tutor->t1_telegram_chat_id,
                        $this->nombreCompleto,
                        $this->horaFormateada,
                        $this->tipoPase
                    );
                    if ($resTg) {
                        $canalesExitosos++;
                        $detalleCanales['telegram'] = self::ESTADO_ACEPTADO_PROVEEDOR;
                    } else {
                        $detalleCanales['telegram'] = self::ESTADO_FALLIDO;
                        $errores[] = 'Telegram: no confirmado por el proveedor';
                    }
                }
            }

            if ($canalesIntentados === 0) {
                $this->completeNotificationLease(
                    self::ESTADO_SIN_CANAL,
                    $detalleCanales,
                    'Tutor sin canales de mensajería configurados.',
                    false
                );

                return;
            }

            if ($canalesExitosos === 0) {
                $detalles = implode(' | ', $errores);
                $this->failNotificationLease("Fallo de entrega en todos los canales: {$detalles}", $detalleCanales);
                throw new \RuntimeException("Fallo de entrega en todos los canales: {$detalles}");
            }

            $estadoFinal = ($canalesExitosos < $canalesIntentados)
                ? self::ESTADO_PARCIAL
                : self::ESTADO_ACEPTADO_PROVEEDOR;

            $ultimoError = ! empty($errores) ? implode(' | ', $errores) : null;

            $this->completeNotificationLease($estadoFinal, $detalleCanales, $ultimoError, true);
        } catch (\Throwable $e) {
            Log::error('Error en NotificarAsistenciaJob: '.$e->getMessage(), [
                'matricula' => $this->alumno->matricula ?? 'N/A',
            ]);

            $this->failNotificationLease($e->getMessage(), $detalleCanales);

            throw $e;
        }
    }

    /**
     * Manejo de fallo terminal.
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->alumno) {
            $this->handleTerminalFailure($this->resolverIdempotencyKey(), $exception, $this->tries);
        }
    }
}
