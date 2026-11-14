<?php

namespace App\Jobs;

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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $eventoRef = $this->eventoId !== null ? "id:{$this->eventoId}" : $this->nombreEvento;

        return hash('sha256', "evento:{$eventoRef}:tutor:{$this->tutor->id}:fecha:{$this->fecha}");
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService, TelegramService $telegramService): void
    {
        $idempotencyKey = null;
        $ownerToken = bin2hex(random_bytes(16));
        $leaseExpires = now()->addMinutes(5);
        $hasOwnerCol = false;

        // 0. Idempotencia y exclusión atómica de workers si existe la tabla
        if (Schema::hasTable('notificaciones_procesadas')) {
            $idempotencyKey = $this->resolverIdempotencyKey();
            $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');

            $insertData = [
                'idempotency_key' => $idempotencyKey,
                'destinatario_tipo' => 'Tutor',
                'destinatario_id' => (string) $this->tutor->id,
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
                    Log::info("[NotificarEventoTutorJob] Notificación ya entregada para tutor {$this->tutor->id}, omitiendo duplicado.");
                    return;
                }

                // Evaluar si otro worker tiene el lease activo
                if ($existente && $existente->estado === 'EN_PROCESO') {
                    $leaseValido = false;
                    if ($hasOwnerCol && ! empty($existente->lease_expires_at)) {
                        $leaseValido = now()->lt(\Carbon\Carbon::parse($existente->lease_expires_at));
                    } else {
                        $leaseValido = $existente->updated_at && now()->diffInMinutes(\Carbon\Carbon::parse($existente->updated_at)) < 5;
                    }

                    if ($leaseValido) {
                        Log::info("[NotificarEventoTutorJob] Trabajo actualmente en proceso por otro worker activo, omitiendo colisión.");
                        return;
                    }
                }

                // Reclamar lease vencido o reintentar trabajo pendiente/fallido de manera atómica
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
                    Log::info("[NotificarEventoTutorJob] No se pudo adquirir el lease concurrentemente, desistiendo.");
                    return;
                }
            }
        }

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
                    } else {
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
                } else {
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
                } catch (\Throwable $eMail) {
                    $erroresCanales[] = 'Email: '.$eMail->getMessage();
                    Log::error('[Mail] [FALLIDO] Error al enviar correo de evento institucional: '.$eMail->getMessage());
                }
            }

            // Si se intentaron canales y ninguno tuvo éxito, lanzar excepción para reintentar
            if ($canalesIntentados > 0 && $canalesExitosos === 0) {
                $detalles = implode(' | ', $erroresCanales);
                throw new \RuntimeException("Fallo de entrega en todos los canales disponibles: {$detalles}");
            }

            // 4. Marcar entrega exitosa SOLO si el worker actual aún es propietario del lease
            if (Schema::hasTable('notificaciones_procesadas') && $idempotencyKey) {
                $finishQuery = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey);
                if ($hasOwnerCol) {
                    $finishQuery->where('owner_token', $ownerToken);
                }

                $filasActualizadas = $finishQuery->update([
                    'estado' => 'ENTREGADO',
                    'fecha_entrega' => now(),
                    'updated_at' => now(),
                ]);

                if ($filasActualizadas === 0 && $hasOwnerCol) {
                    Log::warning("[NotificarEventoTutorJob] El worker {$ownerToken} perdió el lease de ejecución; omitiendo marcado como ENTREGADO.");
                }
            }

            // 5. Confirmar notificación en convocatoria restringiendo estrictamente a este evento y tutor
            if (Schema::hasTable('evento_convocatorias')) {
                $convocatoriaQuery = DB::table('evento_convocatorias')->where('tutor_id', $this->tutor->id);

                if ($this->eventoId !== null) {
                    $convocatoriaQuery->where('evento_id', $this->eventoId);
                } elseif (! empty($this->nombreEvento)) {
                    $eventoEncontrado = DB::table('eventos')->where('nombre_evento', $this->nombreEvento)->value('id');
                    if ($eventoEncontrado) {
                        $convocatoriaQuery->where('evento_id', $eventoEncontrado);
                    }
                }

                $convocatoriaQuery->update(['notificado' => true, 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
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
     * Manejo de fallo terminal tras agotar reintentos.
     */
    public function failed(?\Throwable $exception): void
    {
        $idempotencyKey = $this->resolverIdempotencyKey();

        Log::error("[NotificarEventoTutorJob] Fallo terminal al notificar evento a tutor {$this->tutor->id}: " . ($exception ? $exception->getMessage() : 'Error desconocido'));

        if (Schema::hasTable('notificaciones_procesadas')) {
            DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                'estado' => 'FALLIDO',
                'ultimo_error' => $exception ? substr($exception->getMessage(), 0, 500) : 'Agotados los reintentos permitidos',
                'updated_at' => now(),
            ]);
        }
    }
}
