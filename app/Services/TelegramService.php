<?php

namespace App\Services;

use App\Models\Tutor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $botToken;

    protected string $channelId;

    protected bool $modoSimulacion;

    public function __construct()
    {
        $this->botToken = (string) config('telegram.bot_token', '');
        $this->channelId = (string) config('telegram.channel_id', '');

        // Si no hay token de bot, entramos en modo simulación
        $this->modoSimulacion = empty($this->botToken);
    }

    /**
     * Enmascara el identificador de chat para proteger la privacidad en logs operativos (OWASP A03).
     */
    private function maskChatId(string|int|null $chatId): string
    {
        if (empty($chatId)) {
            return '****';
        }
        $str = (string) $chatId;
        $len = strlen($str);
        if ($len <= 4) {
            return '****';
        }

        return substr($str, 0, 3).'****'.substr($str, -2);
    }

    /**
     * Envía un aviso individual a un tutor por Telegram.
     */
    public function enviarAvisoAsistencia(string|int|null $chatId, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): bool
    {
        if (empty($chatId)) {
            return false;
        }

        $mensaje = "🏫 *CAM #48 - Control de Accesos*\n\n"
                 ."Estimado tutor, le informamos que el alumno(a) *{$nombreAlumno}* ha registrado su asistencia.\n\n"
                 ."⌚ *Hora:* {$hora}\n"
                 ."📌 *Movimiento:* {$tipo}\n\n"
                 .'_Mensaje automático institucional._';

        return $this->enviarMensaje($chatId, $mensaje);
    }

    /**
     * Envía notificación masiva de eventos al canal escolar general institucional (si está configurado).
     */
    public function enviarAvisoEvento(string $nombreEvento, string $fecha, ?string $ubicacion): bool
    {
        $mensaje = "📅 *CAM #48 - Nuevo Evento Escolar*\n\n"
                 ."Se ha agendado una nueva actividad en el plantel:\n\n"
                 ."📌 *Actividad:* {$nombreEvento}\n"
                 ."🕒 *Fecha y Hora:* {$fecha}\n"
                 .'📍 *Ubicación:* '.($ubicacion ?? 'Plantel escolar')."\n\n"
                 .'¡Le esperamos! Agradecemos su puntual asistencia.';

        if (! empty($this->channelId)) {
            return $this->enviarMensaje($this->channelId, $mensaje);
        }

        return true;
    }

    /**
     * Envía notificación de evento a un tutor individual de forma asíncrona dentro de un Job de cola.
     */
    public function enviarAvisoEventoDirecto(string|int $chatId, string $nombreEvento, string $fecha, ?string $ubicacion): bool
    {
        $mensaje = "📅 *CAM #48 - Notificación de Evento Escolar*\n\n"
                 ."Estimado tutor, le recordamos la próxima actividad escolar:\n\n"
                 ."📌 *Actividad:* {$nombreEvento}\n"
                 ."🕒 *Fecha y Hora:* {$fecha}\n"
                 .'📍 *Ubicación:* '.($ubicacion ?? 'Plantel escolar')."\n\n"
                 .'_Mensaje institucional automático._';

        return $this->enviarMensaje($chatId, $mensaje);
    }

    /**
     * Envía mensaje con reporte estructurado de estado operativo y enmascaramiento de datos personales.
     *
     * @return array{status: string, success: bool}
     */
    public function enviarMensajeDetallado(string|int $chatId, string $mensaje): array
    {
        $destinatarioEnmascarado = $this->maskChatId($chatId);

        if ($this->modoSimulacion) {
            Log::info("[Telegram] [SIMULADO] Notificación preparada para destinatario: {$destinatarioEnmascarado}. Longitud: ".strlen($mensaje).' caracteres.');

            return [
                'status' => 'Simulado',
                'success' => false,
            ];
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $response = Http::timeout(5)->connectTimeout(3)->post($url, [
                'chat_id' => $chatId,
                'text' => $mensaje,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                Log::info("[Telegram] [ENVIADO_PROVEEDOR] Mensaje entregado exitosamente a {$destinatarioEnmascarado}");

                return [
                    'status' => 'Enviado a proveedor',
                    'success' => true,
                ];
            } else {
                Log::error("[Telegram] [FALLIDO] Proveedor rechazó mensaje a {$destinatarioEnmascarado} (HTTP {$response->status()})");

                return [
                    'status' => 'Fallido',
                    'success' => false,
                ];
            }
        } catch (\Exception $e) {
            Log::error("[Telegram] [FALLIDO] Excepción de conexión: {$e->getMessage()}");

            return [
                'status' => 'Fallido',
                'success' => false,
            ];
        }
    }

    /**
     * Método base para mandar la petición HTTP a la API de Telegram.
     */
    private function enviarMensaje(string|int $chatId, string $mensaje): bool
    {
        $resultado = $this->enviarMensajeDetallado($chatId, $mensaje);

        return $resultado['success'];
    }

    /**
     * Envía un mensaje de texto directo y personalizado a un tutor específico.
     */
    public function enviarMensajeDirecto(string|int $chatId, string $nombreTutor, string $mensajeTexto): bool
    {
        if (empty($chatId)) {
            return false;
        }

        $mensaje = "🏫 *CAM #48 - Mensaje Directo Escolar*\n\n"
                 ."Estimado(a) *{$nombreTutor}*:\n\n"
                 ."{$mensajeTexto}\n\n"
                 .'_Atentamente, la Dirección Escolar._';

        return $this->enviarMensaje($chatId, $mensaje);
    }
}
