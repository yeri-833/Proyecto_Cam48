<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithNotificationLease;
use App\Models\Personal;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotificarAvisoPersonalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithNotificationLease, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos permitidos.
     */
    public int $tries = 3;

    /**
     * Tiempos de espera entre reintentos en segundos.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 180];

    /**
     * Crea una nueva instancia de trabajo para notificación de personal.
     */
    public function __construct(
        public Personal $personal,
        public string $mensaje,
        public string $idempotencyKey
    ) {}

    /**
     * Ejecuta el trabajo de forma asíncrona, durable e idempotente respaldado en base de datos.
     */
    public function handle(TelegramService $telegramService): void
    {
        $estadoLease = $this->acquireNotificationLease(
            $this->idempotencyKey,
            'Personal',
            (string) $this->personal->id,
            'Telegram'
        );

        if ($estadoLease === 'ALREADY_COMPLETED') {
            Log::info('[NotificarAvisoPersonalJob] Notificación omitida por duplicado ya procesado (persistencia DB).', [
                'personal_id' => $this->personal->id,
                'key' => $this->idempotencyKey,
            ]);

            return;
        }

        if ($estadoLease === 'LOCKED_BY_OTHER') {
            Log::info('[NotificarAvisoPersonalJob] Procesamiento concurrente activo en DB, liberando trabajo.', [
                'personal_id' => $this->personal->id,
            ]);
            if ($this->job !== null) {
                $this->release(10);
            }

            return;
        }

        // Si el personal no tiene Chat ID configurado, no se afirma entrega: se registra SIN_CANAL_DISPONIBLE
        if (empty($this->personal->telegram_chat_id)) {
            $this->completeNotificationLease(
                self::ESTADO_SIN_CANAL,
                ['telegram' => 'NO_CONFIGURADO'],
                'Personal sin telegram_chat_id configurado.',
                false
            );

            Log::info('[NotificarAvisoPersonalJob] Sin canal disponible para notificar al personal.', [
                'personal_id' => $this->personal->id,
            ]);

            return;
        }

        try {
            $exito = $telegramService->enviarMensajeDirecto(
                $this->personal->telegram_chat_id,
                $this->personal->nombre_completo,
                "📢 *Aviso Institucional (Personal)*\n\n".$this->mensaje
            );

            if (! $exito) {
                $this->failNotificationLease(
                    'Fallo temporal en transporte Telegram.',
                    ['telegram' => 'FALLIDO']
                );

                Log::warning('[NotificarAvisoPersonalJob] Reintentando envío de aviso a personal.', [
                    'personal_id' => $this->personal->id,
                    'intento' => $this->attempts(),
                ]);

                throw new \RuntimeException("Fallo temporal al enviar aviso a personal {$this->personal->id}");
            }

            $this->completeNotificationLease(
                self::ESTADO_ACEPTADO_PROVEEDOR,
                ['telegram' => 'ACEPTADO_PROVEEDOR'],
                null,
                true
            );

            Log::info('[NotificarAvisoPersonalJob] Aviso aceptado por el proveedor Telegram.', [
                'personal_id' => $this->personal->id,
            ]);
        } catch (\Throwable $e) {
            $this->failNotificationLease($e->getMessage(), ['telegram' => 'FALLIDO']);

            throw $e;
        }
    }

    /**
     * Se ejecuta cuando todos los reintentos del Job han sido agotados en la cola.
     */
    public function failed(?\Throwable $e = null): void
    {
        $this->handleTerminalFailure($this->idempotencyKey, $e, $this->tries);

        Log::error('[NotificarAvisoPersonalJob] Notificación de personal fallida tras agotar reintentos.', [
            'personal_id' => $this->personal->id,
            'key' => $this->idempotencyKey,
            'error' => $e ? $e->getMessage() : null,
        ]);
    }
}
