<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Tutor;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
    public function handle(WhatsAppService $whatsappService): void
    {
        // 1. Enviar WhatsApp
        if (!empty($this->tutor->t1_telefono)) {
            $telefonoLimpio = preg_replace('/[^0-9]/', '', $this->tutor->t1_telefono);
            $whatsappService->enviarMensajeDirecto($telefonoLimpio, $this->mensajeWhatsapp);
        }

        // 2. Enviar Correo
        if (!empty($this->tutor->t1_email)) {
            try {
                Mail::to($this->tutor->t1_email)
                    ->send(new \App\Mail\AvisoEventoMail($this->nombreEvento, $this->fecha, $this->ubicacion));
            } catch (\Exception $e) {
                Log::error("Fallo al enviar correo de evento a {$this->tutor->t1_email}: " . $e->getMessage());
            }
        }
    }
}
