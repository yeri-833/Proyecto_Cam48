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
    public function __construct(Alumno $alumno, $tipoPase, $horaFormateada, $nombreCompleto)
    {
        $this->alumno = $alumno;
        $this->tipoPase = $tipoPase;
        $this->horaFormateada = $horaFormateada;
        $this->nombreCompleto = $nombreCompleto;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->alumno && $this->alumno->tutores) {
                // 1. WhatsApp
                $wsService = new WhatsAppService();
                $wsService->enviarAvisoAsistencia(
                    $this->alumno->tutores->t1_telefono,
                    $this->nombreCompleto,
                    $this->horaFormateada,
                    $this->tipoPase
                );

                // 2. Telegram (Si tiene Chat ID registrado)
                if (!empty($this->alumno->tutores->t1_telegram_chat_id)) {
                    $tgService = new TelegramService();
                    $tgService->enviarAvisoAsistencia(
                        $this->alumno->tutores->t1_telegram_chat_id,
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
