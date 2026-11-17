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
use RuntimeException;

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
        if (! $this->alumno) {
            Log::warning('[NotificarAsistenciaJob] Ejecución omitida: alumno no definido.');

            return;
        }

        $idempotencyKey = $this->resolverIdempotencyKey();
        $estadoLease = $this->acquireNotificationLease(
            $idempotencyKey,
            'Alumno',
            (string) $this->alumno->matricula,
            'MULTICANAL'
        );

        // POLÍTICA FAIL-CLOSED ESTRICTA: Sin tabla de persistencia, abortar inmediatamente sin contactar proveedores
        if ($estadoLease === 'NO_TABLE') {
            throw new RuntimeException("PERSISTENCIA OBLIGATORIA: La tabla 'notificaciones_procesadas' no existe. Notificación de asistencia abortada para prevenir envíos duplicados o no auditados.");
        }

        if ($estadoLease === 'ALREADY_COMPLETED') {
            Log::info("[NotificarAsistenciaJob] Notificación de asistencia ya procesada para {$this->alumno->matricula}, omitiendo duplicado.");

            return;
        }

        if ($estadoLease === 'LOCKED_BY_OTHER') {
            Log::info('[NotificarAsistenciaJob] Trabajo en proceso por otro worker activo, liberando a la cola.');
            if ($this->job !== null) {
                $this->release(10);
            }

            return;
        }

        try {
            $tutor = $this->alumno->tutor;
            $canalesConfigurados = 0;
            $errores = [];

            // 1. WhatsApp
            $tieneTelefono = $tutor !== null && ! empty($tutor->t1_telefono);
            if ($tieneTelefono) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('whatsapp')) {
                    try {
                        $resWs = $whatsAppService->enviarAvisoAsistencia(
                            (string) $tutor->t1_telefono,
                            $this->nombreCompleto,
                            $this->horaFormateada,
                            $this->tipoPase
                        );
                        if ($resWs) {
                            $this->registrarYPersistirCanal('whatsapp', self::CANAL_ACEPTADO);
                        } else {
                            $this->registrarYPersistirCanal('whatsapp', self::CANAL_FALLIDO, 'WhatsApp: no confirmado por el proveedor');
                            $errores[] = 'WhatsApp: no confirmado por el proveedor';
                        }
                    } catch (\Throwable $eWs) {
                        $estadoWs = $this->esErrorIncierto($eWs->getMessage()) ? self::CANAL_INCIERTO : self::CANAL_FALLIDO;
                        $this->registrarYPersistirCanal('whatsapp', $estadoWs, $eWs->getMessage());
                        $errores[] = 'WhatsApp: '.$eWs->getMessage();
                    }
                }
            } else {
                if (! $this->isCanalAceptado('whatsapp')) {
                    $this->registrarYPersistirCanal('whatsapp', self::CANAL_NO_CONFIGURADO);
                }
            }

            // 2. Telegram (Si tiene Chat ID registrado)
            $tieneTelegram = $tutor !== null && ! empty($tutor->t1_telegram_chat_id);
            if ($tieneTelegram) {
                $canalesConfigurados++;
                if ($this->debeProcesarCanal('telegram')) {
                    try {
                        $resTg = $telegramService->enviarAvisoAsistencia(
                            $tutor->t1_telegram_chat_id,
                            $this->nombreCompleto,
                            $this->horaFormateada,
                            $this->tipoPase
                        );
                        if ($resTg) {
                            $this->registrarYPersistirCanal('telegram', self::CANAL_ACEPTADO);
                        } else {
                            $this->registrarYPersistirCanal('telegram', self::CANAL_FALLIDO, 'Telegram: no confirmado por el proveedor');
                            $errores[] = 'Telegram: no confirmado por el proveedor';
                        }
                    } catch (\Throwable $eTg) {
                        $estadoTg = $this->esErrorIncierto($eTg->getMessage()) ? self::CANAL_INCIERTO : self::CANAL_FALLIDO;
                        $this->registrarYPersistirCanal('telegram', $estadoTg, $eTg->getMessage());
                        $errores[] = 'Telegram: '.$eTg->getMessage();
                    }
                }
            } else {
                if (! $this->isCanalAceptado('telegram')) {
                    $this->registrarYPersistirCanal('telegram', self::CANAL_NO_CONFIGURADO);
                }
            }

            if ($canalesConfigurados === 0) {
                $this->completeNotificationLease(
                    self::ESTADO_SIN_CANAL,
                    [],
                    'Tutor sin canales de mensajería configurados.',
                    false
                );

                return;
            }

            $wsAceptado = $tieneTelefono && $this->isCanalAceptado('whatsapp');
            $tgAceptado = $tieneTelegram && $this->isCanalAceptado('telegram');
            $wsIncierto = $tieneTelefono && $this->isCanalIncierto('whatsapp');
            $tgIncierto = $tieneTelegram && $this->isCanalIncierto('telegram');
            $wsFallido = $tieneTelefono && $this->getEstadoCanal('whatsapp') === self::CANAL_FALLIDO;
            $tgFallido = $tieneTelegram && $this->getEstadoCanal('telegram') === self::CANAL_FALLIDO;

            $aceptados = ($wsAceptado ? 1 : 0) + ($tgAceptado ? 1 : 0);
            $inciertos = ($wsIncierto ? 1 : 0) + ($tgIncierto ? 1 : 0);
            $fallidos = ($wsFallido ? 1 : 0) + ($tgFallido ? 1 : 0);

            // 1. Éxito total: todos los canales configurados fueron aceptados
            if ($aceptados === $canalesConfigurados) {
                $this->completeNotificationLease(self::ESTADO_ACEPTADO_PROVEEDOR, [], null, true);

                return;
            }

            $ultimoError = ! empty($errores) ? implode(' | ', $errores) : 'Fallo o incertidumbre en canales de notificación';

            // 2. Al menos un canal fue aceptado
            if ($aceptados > 0) {
                if ($fallidos > 0) {
                    $this->saveInterimLeaseState(self::ESTADO_PARCIAL, $ultimoError);
                    throw new RuntimeException("Entrega parcial en asistencia para {$this->alumno->matricula}: {$ultimoError}");
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
                throw new RuntimeException("Fallo en todos los canales para {$this->alumno->matricula}: {$ultimoError}");
            }
        } catch (\Throwable $e) {
            Log::error('Error en NotificarAsistenciaJob: '.$e->getMessage(), [
                'matricula' => $this->alumno->matricula ?? 'N/A',
            ]);

            throw $e;
        }
    }

    /**
     * Manejo de fallo terminal tras agotar los intentos en la cola.
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->alumno) {
            $this->handleTerminalFailure($this->resolverIdempotencyKey(), $exception, $this->tries);
        }
    }
}
