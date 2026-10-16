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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificarEventoTutorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    }
}
