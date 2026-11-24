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
use RuntimeException;

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

        // POLÍTICA FAIL-CLOSED ESTRICTA: Sin tabla de persistencia, abortar inmediatamente sin contactar proveedores
        if ($estadoLease === 'NO_TABLE') {
            throw new RuntimeException("PERSISTENCIA OBLIGATORIA: La tabla 'notificaciones_procesadas' no existe. Notificación a personal abortada para prevenir envíos duplicados o no auditados.");
        }

        if ($estadoLease === 'ALREADY_COMPLETED') {
            Log::info('[NotificarAvisoPersonalJob] Notificación de personal ya procesada.');

            return;
        }

        if ($estadoLease === 'LOCKED_BY_OTHER') {
            Log::info('[NotificarAvisoPersonalJob] Procesamiento concurrente activo, liberando trabajo.');
            if ($this->job !== null) {
                $this->release(10);
            }

            return;
        }

        if (! $this->debeProcesarCanal('telegram')) {
            $estadoCanal = $this->getEstadoCanal('telegram');
            $estadoNotif = $estadoCanal === self::CANAL_INCIERTO ? self::ESTADO_INCIERTO : self::ESTADO_ACEPTADO_PROVEEDOR;
            $this->completeNotificationLease($estadoNotif, [], null, $estadoNotif === self::ESTADO_ACEPTADO_PROVEEDOR);

            return;
        }

        if (empty($this->personal->telegram_chat_id)) {
            $this->registrarYPersistirCanal('telegram', self::CANAL_NO_CONFIGURADO);
            $this->completeNotificationLease(
                self::ESTADO_SIN_CANAL,
                [],
                'Personal sin telegram_chat_id configurado.',
                false
            );

            Log::info('[NotificarAvisoPersonalJob] Sin canal disponible para notificar al personal.');

            return;
        }

        $error = $this->procesarCanalProveedor(
            'Telegram',
            fn (): array => $telegramService->enviarMensajeDirectoDetallado(
                $this->personal->telegram_chat_id,
                $this->personal->nombre_completo,
                "📢 *Aviso Institucional (Personal)*\n\n".$this->mensaje
            ),
        );

        if ($this->getEstadoCanal('telegram') === self::CANAL_FALLIDO) {
            $this->saveInterimLeaseState(self::ESTADO_FALLIDO, $error);

            throw new RuntimeException('Fallo al enviar aviso al personal.');
        }

        $aceptado = $this->isCanalAceptado('telegram');
        $this->completeNotificationLease(
            $aceptado ? self::ESTADO_ACEPTADO_PROVEEDOR : self::ESTADO_INCIERTO,
            [],
            $error,
            $aceptado,
        );
    }

    /**
     * Se ejecuta cuando todos los reintentos del Job han sido agotados en la cola.
     */
    public function failed(?\Throwable $e = null): void
    {
        $this->handleTerminalFailure($this->idempotencyKey, $e, $this->tries);

        Log::error('[NotificarAvisoPersonalJob] Se agotaron los intentos del trabajo.');
    }
}
