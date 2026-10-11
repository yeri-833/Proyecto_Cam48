<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Alumno;
use App\Services\WhatsAppService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class NotificarAsistenciaJob implements ShouldQueue
{
    use Queueable;

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
        } catch (\Exception $e) {
            Log::error('Error en NotificarAsistenciaJob: ' . $e->getMessage(), [
                'matricula' => $this->alumno->matricula ?? 'N/A'
            ]);
        }
    }
}
