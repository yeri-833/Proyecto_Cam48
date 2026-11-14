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
        $ownerToken = bin2hex(random_bytes(16));
        $leaseExpires = now()->addMinutes(5);
        $hasOwnerCol = false;

        // Idempotencia durable: evitar notificaciones duplicadas para el mismo pase escolar
        if (Schema::hasTable('notificaciones_procesadas') && $this->alumno) {
            $fechaHoy = now()->toDateString();
            $idempotencyKey = hash('sha256', "asistencia:alumno:{$this->alumno->matricula}:pase:{$this->tipoPase}:fecha:{$fechaHoy}:hora:{$this->horaFormateada}");
            $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');

            $insertData = [
                'idempotency_key' => $idempotencyKey,
                'destinatario_tipo' => 'Alumno',
                'destinatario_id' => (string) $this->alumno->matricula,
                'canal' => 'MULTICANAL',
                'estado' => 'EN_PROCESO',
                'intentos' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasOwnerCol) {
                $insertData['owner_token'] = $ownerToken;
                $insertData['lease_expires_at'] = $leaseExpires;
            }

            $inserted = DB::table('notificaciones_procesadas')->insertOrIgnore($insertData);

            if ($inserted === 0) {
                $existente = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->first();

                if ($existente && $existente->estado === 'ENTREGADO') {
                    Log::info("[NotificarAsistenciaJob] Notificación de asistencia ya procesada para {$this->alumno->matricula}, omitiendo duplicado.");
                    return;
                }

                if ($existente && $existente->estado === 'EN_PROCESO') {
                    $leaseValido = false;
                    if ($hasOwnerCol && ! empty($existente->lease_expires_at)) {
                        $leaseValido = now()->lt(\Carbon\Carbon::parse($existente->lease_expires_at));
                    } else {
                        $leaseValido = $existente->updated_at && now()->diffInMinutes(\Carbon\Carbon::parse($existente->updated_at)) < 5;
                    }

                    if ($leaseValido) {
                        Log::info("[NotificarAsistenciaJob] Trabajo en proceso por otro worker activo, omitiendo.");
                        return;
                    }
                }

                $claimQuery = DB::table('notificaciones_procesadas')
                    ->where('idempotency_key', $idempotencyKey)
                    ->where(function ($q) use ($hasOwnerCol) {
                        $q->whereIn('estado', ['PENDIENTE', 'FALLIDO'])
                          ->orWhere(function ($sub) use ($hasOwnerCol) {
                              $sub->where('estado', 'EN_PROCESO');
                              if ($hasOwnerCol) {
                                  $sub->where(function ($sq) {
                                      $sq->whereNull('lease_expires_at')
                                         ->orWhere('lease_expires_at', '<', now());
                                  });
                              } else {
                                  $sub->where('updated_at', '<', now()->subMinutes(5));
                              }
                          });
                    });

                $claimData = [
                    'estado' => 'EN_PROCESO',
                    'intentos' => DB::raw('intentos + 1'),
                    'updated_at' => now(),
                ];
                if ($hasOwnerCol) {
                    $claimData['owner_token'] = $ownerToken;
                    $claimData['lease_expires_at'] = $leaseExpires;
                }

                $acquired = $claimQuery->update($claimData);
                if ($acquired === 0) {
                    return;
                }
            }
        }

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
                    } else {
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
                    } else {
                        $errores[] = 'Telegram: no confirmado por el proveedor';
                    }
                }
            }

            if ($canalesIntentados > 0 && $canalesExitosos === 0) {
                $detalles = implode(' | ', $errores);
                throw new \RuntimeException("Fallo de entrega en todos los canales: {$detalles}");
            }

            if (Schema::hasTable('notificaciones_procesadas') && $idempotencyKey) {
                $finishQuery = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey);
                if ($hasOwnerCol) {
                    $finishQuery->where('owner_token', $ownerToken);
                }

                $finishQuery->update([
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
                $failQuery = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey);
                if ($hasOwnerCol) {
                    $failQuery->where('owner_token', $ownerToken);
                }

                $failQuery->update([
                    'estado' => 'FALLIDO',
                    'ultimo_error' => substr($e->getMessage(), 0, 500),
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
