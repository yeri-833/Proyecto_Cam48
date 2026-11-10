<?php

namespace App\Jobs;

use App\Models\Personal;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificarAvisoPersonalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $hasPersistence = Schema::hasTable('notificaciones_procesadas');

        // 1. Verificación previa de idempotencia durable en base de datos
        if ($hasPersistence) {
            $registro = DB::table('notificaciones_procesadas')
                ->where('idempotency_key', $this->idempotencyKey)
                ->first();

            if ($registro && $registro->estado === 'ENTREGADO') {
                Log::info('[NotificarAvisoPersonalJob] Notificación omitida por duplicado ya entregado (persistencia DB).', [
                    'personal_id' => $this->personal->id,
                    'key' => $this->idempotencyKey,
                ]);

                return;
            }

            // Reserva atómica con constraint UNIQUE a nivel de motor de base de datos
            if (! $registro) {
                try {
                    DB::table('notificaciones_procesadas')->insert([
                        'idempotency_key' => $this->idempotencyKey,
                        'destinatario_tipo' => 'Personal',
                        'destinatario_id' => (string) $this->personal->id,
                        'canal' => 'Telegram',
                        'estado' => 'EN_PROCESO',
                        'intentos' => 1,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Si otra solicitud concurrente ya insertó la clave, evaluar estado
                    $existente = DB::table('notificaciones_procesadas')
                        ->where('idempotency_key', $this->idempotencyKey)
                        ->first();

                    if ($existente && $existente->estado === 'ENTREGADO') {
                        Log::info('[NotificarAvisoPersonalJob] Notificación omitida tras colisión concurrente (ya entregada).', [
                            'personal_id' => $this->personal->id,
                        ]);

                        return;
                    }

                    if ($existente && $existente->estado === 'EN_PROCESO') {
                        $updatedAt = $existente->updated_at ? Carbon::parse($existente->updated_at) : Carbon::now();
                        // Detección y recuperación de trabajo abandonado/zombie (más de 5 minutos en proceso)
                        if ($updatedAt->diffInMinutes(Carbon::now()) >= 5) {
                            Log::warning('[NotificarAvisoPersonalJob] Registro abandonado en EN_PROCESO detectado; reclamando ejecución.', [
                                'personal_id' => $this->personal->id,
                                'key' => $this->idempotencyKey,
                            ]);

                            DB::table('notificaciones_procesadas')
                                ->where('idempotency_key', $this->idempotencyKey)
                                ->update([
                                    'intentos' => DB::raw('intentos + 1'),
                                    'updated_at' => Carbon::now(),
                                ]);
                        } else {
                            Log::info('[NotificarAvisoPersonalJob] Procesamiento concurrente activo en DB, liberando trabajo.', [
                                'personal_id' => $this->personal->id,
                            ]);
                            $this->release(10);

                            return;
                        }
                    }
                }
            } else {
                // Si ya existía: evaluar si está abandonado o si se está reintentando tras fallo
                if ($registro->estado === 'EN_PROCESO') {
                    $updatedAt = $registro->updated_at ? Carbon::parse($registro->updated_at) : Carbon::now();
                    if ($updatedAt->diffInMinutes(Carbon::now()) >= 5) {
                        Log::warning('[NotificarAvisoPersonalJob] Reclamando registro abandonado en EN_PROCESO.', [
                            'personal_id' => $this->personal->id,
                        ]);
                        DB::table('notificaciones_procesadas')
                            ->where('idempotency_key', $this->idempotencyKey)
                            ->update([
                                'intentos' => DB::raw('intentos + 1'),
                                'updated_at' => Carbon::now(),
                            ]);
                    } else {
                        $this->release(10);

                        return;
                    }
                } else {
                    // Estado FALLIDO o PENDIENTE: incrementar intento y pasar a EN_PROCESO
                    DB::table('notificaciones_procesadas')
                        ->where('idempotency_key', $this->idempotencyKey)
                        ->update([
                            'estado' => 'EN_PROCESO',
                            'intentos' => DB::raw('intentos + 1'),
                            'updated_at' => Carbon::now(),
                        ]);
                }
            }
        }

        // Si el docente no tiene Chat ID, se concluye sin error
        if (empty($this->personal->telegram_chat_id)) {
            if ($hasPersistence) {
                DB::table('notificaciones_procesadas')
                    ->where('idempotency_key', $this->idempotencyKey)
                    ->update([
                        'estado' => 'ENTREGADO',
                        'fecha_entrega' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
            }

            return;
        }

        try {
            $exito = $telegramService->enviarMensajeDirecto(
                $this->personal->telegram_chat_id,
                $this->personal->nombre_completo,
                "📢 *Aviso Institucional (Personal)*\n\n".$this->mensaje
            );

            if (! $exito) {
                if ($hasPersistence) {
                    DB::table('notificaciones_procesadas')
                        ->where('idempotency_key', $this->idempotencyKey)
                        ->update([
                            'estado' => 'FALLIDO',
                            'ultimo_error' => 'Fallo temporal en transporte Telegram.',
                            'updated_at' => Carbon::now(),
                        ]);
                }

                Log::warning('[NotificarAvisoPersonalJob] Reintentando envío de aviso a personal.', [
                    'personal_id' => $this->personal->id,
                    'intento' => $this->attempts(),
                ]);

                throw new \RuntimeException("Fallo temporal al enviar aviso a personal {$this->personal->id}");
            }

            if ($hasPersistence) {
                DB::table('notificaciones_procesadas')
                    ->where('idempotency_key', $this->idempotencyKey)
                    ->update([
                        'estado' => 'ENTREGADO',
                        'fecha_entrega' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
            }

            Log::info('[NotificarAvisoPersonalJob] Aviso entregado satisfactoriamente.', [
                'personal_id' => $this->personal->id,
            ]);
        } catch (\Throwable $e) {
            if ($hasPersistence) {
                DB::table('notificaciones_procesadas')
                    ->where('idempotency_key', $this->idempotencyKey)
                    ->update([
                        'estado' => 'FALLIDO',
                        'ultimo_error' => substr($e->getMessage(), 0, 500),
                        'updated_at' => Carbon::now(),
                    ]);
            }

            throw $e;
        }
    }

    /**
     * Se ejecuta cuando todos los reintentos del Job han sido agotados en la cola.
     */
    public function failed(?\Throwable $e = null): void
    {
        if (Schema::hasTable('notificaciones_procesadas')) {
            DB::table('notificaciones_procesadas')
                ->where('idempotency_key', $this->idempotencyKey)
                ->update([
                    'estado' => 'FALLIDO',
                    'ultimo_error' => 'Agotados los '.$this->tries.' intentos: '.($e ? substr($e->getMessage(), 0, 400) : 'Sin mensaje'),
                    'updated_at' => Carbon::now(),
                ]);
        }

        Log::error('[NotificarAvisoPersonalJob] Notificación de personal fallida tras agotar reintentos.', [
            'personal_id' => $this->personal->id,
            'key' => $this->idempotencyKey,
            'error' => $e ? $e->getMessage() : null,
        ]);
    }
}
