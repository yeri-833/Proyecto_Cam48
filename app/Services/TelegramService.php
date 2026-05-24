<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $channelId;
    protected $modoSimulacion;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '');
        $this->channelId = env('TELEGRAM_CHANNEL_ID', '');
        
        // Si no hay token de bot, entramos en modo simulación (Mock)
        $this->modoSimulacion = empty($this->botToken);
    }

    /**
     * Envía un aviso individual a un tutor por Telegram
     */
    public function enviarAvisoAsistencia($chatId, $nombreAlumno, $hora, $tipo = 'Pase de Lista')
    {
        if (empty($chatId)) {
            return false;
        }

        $mensaje = "🏫 *CAM #48 - Control de Accesos*\n\n"
                 . "Estimado tutor, le informamos que el alumno(a) *{$nombreAlumno}* ha registrado su asistencia.\n\n"
                 . "⌚ *Hora:* {$hora}\n"
                 . "📌 *Movimiento:* {$tipo}\n\n"
                 . "_Mensaje automático institucional._";

        return $this->enviarMensaje($chatId, $mensaje);
    }

    /**
     * Envía una notificación masiva de eventos al canal escolar general
     */
    public function enviarAvisoEvento($nombreEvento, $fecha, $ubicacion)
    {
        $mensaje = "📅 *CAM #48 - Nuevo Evento Escolar*\n\n"
                 . "Se ha agendado una nueva actividad en el plantel:\n\n"
                 . "📌 *Actividad:* {$nombreEvento}\n"
                 . "🕒 *Fecha y Hora:* {$fecha}\n"
                 . "📍 *Ubicación:* {$ubicacion}\n\n"
                 . "¡Le esperamos! Agradecemos su puntual asistencia.";

        // 1. Enviar al canal general institucional si está configurado
        if (!empty($this->channelId)) {
            $this->enviarMensaje($this->channelId, $mensaje);
        }

        // 2. Adicionalmente, enviar de forma personalizada a todos los tutores con Chat ID activo
        $tutoresConTelegram = \App\Models\Tutor::whereNotNull('t1_telegram_chat_id')->get();
        foreach ($tutoresConTelegram as $tutor) {
            $this->enviarMensaje($tutor->t1_telegram_chat_id, $mensaje);
        }

        return true;
    }

    /**
     * Método base para mandar la petición HTTP a la API de Telegram
     */
    private function enviarMensaje($chatId, $mensaje)
    {
        if ($this->modoSimulacion) {
            Log::info("🔵 SIMULACIÓN TELEGRAM a [{$chatId}]:\n{$mensaje}");
            return true;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $response = Http::withoutVerifying()->post($url, [
                'chat_id' => $chatId,
                'text' => $mensaje,
                'parse_mode' => 'Markdown'
            ]);

            if ($response->successful()) {
                Log::info("Telegram enviado exitosamente a {$chatId}");
                return true;
            } else {
                Log::error("Fallo al enviar Telegram a {$chatId}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Excepción en TelegramService: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía un mensaje de texto directo y personalizado a un tutor específico
     */
    public function enviarMensajeDirecto($chatId, $nombreTutor, $mensajeTexto)
    {
        if (empty($chatId)) {
            return false;
        }

        $mensaje = "🏫 *CAM #48 - Mensaje Directo Escolar*\n\n"
                 . "Estimado(a) *{$nombreTutor}*:\n\n"
                 . "{$mensajeTexto}\n\n"
                 . "_Atentamente, la Dirección Escolar._";

        return $this->enviarMensaje($chatId, $mensaje);
    }
}
