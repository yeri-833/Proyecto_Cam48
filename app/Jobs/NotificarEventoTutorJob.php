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

    /**
     * Create a new job instance.
     */
    public function __construct(Tutor $tutor, $mensajeWhatsapp, $nombreEvento, $fecha, $ubicacion)
    {
        $this->tutor = $tutor;
        $this->mensajeWhatsapp = $mensajeWhatsapp;
        $this->nombreEvento = $nombreEvento;
        $this->fecha = $fecha;
        $this->ubicacion = $ubicacion;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService, TelegramService $telegramService): void
    {
        $idempotencyKey = null;

        // 0. Idempotencia y exclusión atómica de workers si existe la tabla
        if (Schema::hasTable('notificaciones_procesadas')) {
            $idempotencyKey = hash('sha256', "evento:{$this->nombreEvento}:tutor:{$this->tutor->id}:fecha:{$this->fecha}");

            // Recuperar posibles bloqueos zombi abandonados por caída previa de worker
            DB::table('notificaciones_procesadas')
                ->where('idempotency_key', $idempotencyKey)
                ->where('estado', 'EN_PROCESO')
                ->where('updated_at', '<', now()->subMinutes(5))
                ->update(['estado' => 'PENDIENTE']);

            $inserted = DB::table('notificaciones_procesadas')->insertOrIgnore([
                'idempotency_key' => $idempotencyKey,
                'destinatario_tipo' => 'Tutor',
                'destinatario_id' => (string) $this->tutor->id,
                'canal' => 'MULTICANAL',
                'estado' => 'EN_PROCESO',
                'intentos' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                $existente = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->first();
                if ($existente && ($existente->estado === 'ENTREGADO' || $existente->estado === 'EN_PROCESO')) {
                    Log::info("[NotificarEventoTutorJob] Notificación ya entregada o en curso para tutor {$this->tutor->id}, omitiendo duplicado.");
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
            // 1. Enviar WhatsApp en segundo plano
            if (! empty($this->tutor->t1_telefono)) {
                $telefonoLimpio = preg_replace('/[^0-9]/', '', $this->tutor->t1_telefono);
                if (! empty($telefonoLimpio)) {
                    $whatsappService->enviarMensajeDirecto($telefonoLimpio, $this->mensajeWhatsapp);
                }
            }

            // 2. Enviar Telegram en segundo plano (si el tutor tiene Chat ID registrado)
            if (! empty($this->tutor->t1_telegram_chat_id)) {
                $telegramService->enviarAvisoEventoDirecto(
                    $this->tutor->t1_telegram_chat_id,
                    $this->nombreEvento,
                    $this->fecha,
                    $this->ubicacion
                );
            }

            // 3. Enviar Correo en segundo plano
            if (! empty($this->tutor->t1_email)) {
                try {
                    Mail::to($this->tutor->t1_email)
                        ->send(new AvisoEventoMail($this->nombreEvento, $this->fecha, $this->ubicacion));
                } catch (\Exception $e) {
                    Log::error('[Mail] [FALLIDO] Error al enviar correo de evento institucional: '.$e->getMessage());
                }
            }

            // 4. Marcar entrega exitosa y confirmar notificación en convocatoria
            if (Schema::hasTable('notificaciones_procesadas')) {
                DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                    'estado' => 'ENTREGADO',
                    'fecha_entrega' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('evento_convocatorias')) {
                DB::table('evento_convocatorias')
                    ->where('tutor_id', $this->tutor->id)
                    ->update(['notificado' => true, 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
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
     * Manejo de fallo terminal tras agotar reintentos.
     */
    public function failed(?\Throwable $exception): void
    {
        $idempotencyKey = hash('sha256', "evento:{$this->nombreEvento}:tutor:{$this->tutor->id}:fecha:{$this->fecha}");

        Log::error("[NotificarEventoTutorJob] Fallo terminal al notificar evento a tutor {$this->tutor->id}: " . ($exception ? $exception->getMessage() : 'Error desconocido'));

        if (Schema::hasTable('notificaciones_procesadas')) {
            DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->update([
                'estado' => 'FALLIDO',
                'ultimo_error' => $exception ? $exception->getMessage() : 'Agotados los reintentos permitidos',
                'updated_at' => now(),
            ]);
        }
    }
}
