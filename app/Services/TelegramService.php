<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * Envía un aviso individual a un tutor por Telegram.
     */
    public function enviarAvisoAsistencia(string|int|null $chatId, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): bool
    {
        return $this->enviarAvisoAsistenciaDetallado($chatId, $nombreAlumno, $hora, $tipo)['success'];
    }

    /** @return array{status: string, success: bool} */
    public function enviarAvisoAsistenciaDetallado(string|int|null $chatId, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): array
    {
        if (empty($chatId)) {
            return ['status' => 'Fallido', 'success' => false];
        }

        $mensaje = "🏫 *CAM #48 - Control de Accesos*\n\n"
                 ."Estimado tutor, le informamos que el alumno(a) *{$nombreAlumno}* ha registrado su asistencia.\n\n"
                 ."⌚ *Hora:* {$hora}\n"
                 ."📌 *Movimiento:* {$tipo}\n\n"
                 .'_Mensaje automático institucional._';

        return $this->enviarMensajeDetallado($chatId, $mensaje);
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
        return $this->enviarAvisoEventoDirectoDetallado($chatId, $nombreEvento, $fecha, $ubicacion)['success'];
    }

    /** @return array{status: string, success: bool} */
    public function enviarAvisoEventoDirectoDetallado(string|int $chatId, string $nombreEvento, string $fecha, ?string $ubicacion): array
    {
        $mensaje = "📅 *CAM #48 - Notificación de Evento Escolar*\n\n"
                 ."Estimado tutor, le recordamos la próxima actividad escolar:\n\n"
                 ."📌 *Actividad:* {$nombreEvento}\n"
                 ."🕒 *Fecha y Hora:* {$fecha}\n"
                 .'📍 *Ubicación:* '.($ubicacion ?? 'Plantel escolar')."\n\n"
                 .'_Mensaje institucional automático._';

        return $this->enviarMensajeDetallado($chatId, $mensaje);
    }

    /**
     * @return array{status: string, success: bool}
     */
    public function enviarMensajeDetallado(string|int $chatId, string $mensaje): array
    {
        if ($this->modoSimulacion) {
            Log::info('[Telegram] Envío simulado.');

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

            $aceptado = $response->json('ok');
            if ($response->successful() && $aceptado === true) {
                Log::info('[Telegram] Envío aceptado por el proveedor.');

                return ['status' => 'Enviado a proveedor', 'success' => true];
            }

            if (($response->clientError() && $response->status() !== 408)
                || ($response->successful() && $aceptado === false)) {
                Log::error('[TELEGRAM_SEND_FAILED] Envío rechazado.', ['http_status' => $response->status()]);

                return ['status' => 'Fallido', 'success' => false];
            }

            Log::warning('[TELEGRAM_SEND_UNCERTAIN] Respuesta sin confirmación de envío.', ['http_status' => $response->status()]);
        } catch (ConnectionException $exception) {
            Log::warning('[TELEGRAM_SEND_UNCERTAIN] Conexión interrumpida, resultado del envío incierto.', ['exception_type' => $exception::class]);
        } catch (Throwable $exception) {
            Log::error('[TELEGRAM_SEND_FAILED] No fue posible completar el envío de Telegram.', [
                'exception_type' => $exception::class,
            ]);

            return ['status' => 'Fallido', 'success' => false];
        }

        return ['status' => 'Incierto', 'success' => false];
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
        return $this->enviarMensajeDirectoDetallado($chatId, $nombreTutor, $mensajeTexto)['success'];
    }

    /** @return array{status: string, success: bool} */
    public function enviarMensajeDirectoDetallado(string|int $chatId, string $nombreTutor, string $mensajeTexto): array
    {
        if (empty($chatId)) {
            return ['status' => 'Fallido', 'success' => false];
        }

        $mensaje = "🏫 *CAM #48 - Mensaje Directo Escolar*\n\n"
                 ."Estimado(a) *{$nombreTutor}*:\n\n"
                 ."{$mensajeTexto}\n\n"
                 .'_Atentamente, la Dirección Escolar._';

        return $this->enviarMensajeDetallado($chatId, $mensaje);
    }
}
