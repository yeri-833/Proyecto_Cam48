<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Alumno;
use App\Services\WhatsAppService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificarAsistenciaJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public $alumno;
    public $tipoPase;
    public $horaFormateada;
    public $nombreCompleto;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Alumno $alumno,
        string $tipoPase,
        string $horaFormateada,
        string $nombreCompleto
    ) {
        $this->alumno         = $alumno;
        $this->tipoPase       = $tipoPase;
        $this->horaFormateada = $horaFormateada;
        $this->nombreCompleto = $nombreCompleto;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService, TelegramService $telegramService): void
    {
        $idempotencyKey = null;

        // Idempotencia durable: evitar notificaciones duplicadas para el mismo pase escolar
        if (Schema::hasTable('notificaciones_procesadas') && $this->alumno) {
            $fechaHoy = now()->toDateString();
            $idempotencyKey = hash('sha256', "asistencia:alumno:{$this->alumno->matricula}:pase:{$this->tipoPase}:fecha:{$fechaHoy}:hora:{$this->horaFormateada}");

            // Recuperación de posible bloqueo zombi
            DB::table('notificaciones_procesadas')
                ->where('idempotency_key', $idempotencyKey)
                ->where('estado', 'EN_PROCESO')
                ->where('updated_at', '<', now()->subMinutes(5))
                ->update(['estado' => 'PENDIENTE']);

            $inserted = DB::table('notificaciones_procesadas')->insertOrIgnore([
                'idempotency_key' => $idempotencyKey,
                'destinatario_tipo' => 'Alumno',
                'destinatario_id' => (string) $this->alumno->matricula,
                'canal' => 'MULTICANAL',
                'estado' => 'EN_PROCESO',
                'intentos' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                $existente = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->first();
                if ($existente && ($existente->estado === 'ENTREGADO' || $existente->estado === 'EN_PROCESO')) {
                    Log::info("[NotificarAsistenciaJob] Notificación de asistencia ya procesada para {$this->alumno->matricula}, omitiendo duplicado.");
                    return;
                }

                DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                    'estado' => 'EN_PROCESO',
                    'intentos' => DB::raw('intentos + 1'),
                    'updated_at' => now(),
                ]);
            }
        }

        try {
            if ($this->alumno && $this->alumno->tutor) {
                // 1. WhatsApp
                $whatsAppService->enviarAvisoAsistencia(
                    $this->alumno->tutor->t1_telefono,
                    $this->nombreCompleto,
                    $this->horaFormateada,
                    $this->tipoPase
                );

                // 2. Telegram (Si tiene Chat ID registrado)
                if (!empty($this->alumno->tutor->t1_telegram_chat_id)) {
                    $telegramService->enviarAvisoAsistencia(
                        $this->alumno->tutor->t1_telegram_chat_id,
                        $this->nombreCompleto,
                        $this->horaFormateada,
                        $this->tipoPase
                    );
                }
            }

            if (Schema::hasTable('notificaciones_procesadas') && $idempotencyKey) {
                DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                    'estado' => 'ENTREGADO',
                    'fecha_entrega' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en NotificarAsistenciaJob: ' . $e->getMessage(), [
                'matricula' => $this->alumno->matricula ?? 'N/A'
            ]);

            if (Schema::hasTable('notificaciones_procesadas') && $idempotencyKey) {
                DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                    'estado' => 'PENDIENTE',
                    'ultimo_error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Manejo de fallo terminal.
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->alumno && Schema::hasTable('notificaciones_procesadas')) {
            $fechaHoy = now()->toDateString();
            $idempotencyKey = hash('sha256', "asistencia:alumno:{$this->alumno->matricula}:pase:{$this->tipoPase}:fecha:{$fechaHoy}:hora:{$this->horaFormateada}");

            DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                'estado' => 'FALLIDO',
                'ultimo_error' => $exception ? $exception->getMessage() : 'Agotados los reintentos',
                'updated_at' => now(),
            ]);
        }
    }
}
