<?php

namespace App\Services;

use App\Jobs\NotificarEventoTutorJob;
use App\Models\Tutor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    protected string $apiUrl;

    protected string $apiToken;

    protected bool $modoSimulacion;

    public function __construct()
    {
        $this->apiUrl = (string) config('whatsapp.api_url', '');
        $this->apiToken = (string) config('whatsapp.api_token', '');

        // Si no hay token configurado, entramos en modo simulación
        $this->modoSimulacion = empty($this->apiToken) || empty($this->apiUrl);
    }

    /**
     * Envía una notificación de asistencia al tutor del alumno.
     */
    public function enviarAvisoAsistencia(string $telefono, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): bool
    {
        return $this->enviarAvisoAsistenciaDetallado($telefono, $nombreAlumno, $hora, $tipo)['success'];
    }

    /** @return array{status: string, success: bool} */
    public function enviarAvisoAsistenciaDetallado(string $telefono, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): array
    {
        if (empty($telefono)) {
            return ['status' => 'Fallido', 'success' => false];
        }

        $mensaje = "🏫 *CAM #48 - Sistema de Accesos*\n\n"
                 ."Le informamos que el alumno(a) *{$nombreAlumno}* ha registrado su asistencia.\n\n"
                 ."⌚ Hora de registro: *{$hora}*\n"
                 ."📌 Tipo: {$tipo}\n\n"
                 .'_Este es un mensaje automático, por favor no responda._';

        return $this->enviarMensajeDirectoDetallado($telefono, $mensaje);
    }

    /**
     * Envía una notificación general sobre un nuevo evento encolando los envíos individuales (asíncrono).
     */
    public function enviarAvisoEvento(string $nombreEvento, string $fecha, ?string $ubicacion, ?int $eventoId = null): bool
    {
        if ($eventoId === null || $eventoId <= 0) {
            Log::warning('[WhatsApp] Despacho de aviso de evento abortado: falta eventoId explícito.');

            return false;
        }

        $mensaje = "📅 *CAM #48 - Nuevo Evento Escolar*\n\n"
                 ."Se ha agendado una nueva actividad en la escuela:\n\n"
                 ."📌 *Actividad:* {$nombreEvento}\n"
                 ."🕒 *Fecha y Hora:* {$fecha}\n";

        if (! empty($ubicacion)) {
            $mensaje .= "📍 *Ubicación:* {$ubicacion}\n";
        }

        $mensaje .= "\n_Por favor, agéndelo. Le esperamos._";

        // Procesar en lotes de 100 para no saturar memoria RAM
        Tutor::chunk(100, function ($tutores) use ($mensaje, $nombreEvento, $fecha, $ubicacion, $eventoId) {
            foreach ($tutores as $tutor) {
                NotificarEventoTutorJob::dispatch($tutor, $mensaje, $nombreEvento, $fecha, $ubicacion ?? 'Por confirmar', $eventoId);
            }
        });

        Log::info('[WhatsApp] [EN_COLA] Avisos de evento despachados a la cola de trabajos.');

        return true;
    }

    /**
     * @return array{status: string, success: bool}
     */
    public function enviarMensajeDirectoDetallado(string $telefonoDestino, string $mensaje): array
    {
        $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefonoDestino) ?? '';
        if ($this->modoSimulacion) {
            Log::info('[WhatsApp] Envío simulado.');

            return [
                'status' => 'Simulado',
                'success' => false,
            ];
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)->post($this->apiUrl.'/messages/chat', [
                'token' => $this->apiToken,
                'to' => $telefonoLimpio,
                'body' => $mensaje,
            ]);

            $rechazado = in_array($response->json('sent'), [false, 'false'], true)
                || in_array($response->json('success'), [false, 'false'], true);
            if ($response->successful() && ! $rechazado) {
                Log::info('[WhatsApp] Envío aceptado por el proveedor.');

                return ['status' => 'Enviado a proveedor', 'success' => true];
            }

            if (($response->clientError() && $response->status() !== 408) || ($response->successful() && $rechazado)) {
                Log::error('[WhatsApp] Envío rechazado.', ['http_status' => $response->status()]);

                return ['status' => 'Fallido', 'success' => false];
            }

            Log::warning('[WhatsApp] Respuesta sin confirmación de envío.', ['http_status' => $response->status()]);
        } catch (ConnectionException) {
            Log::warning('[WhatsApp] Conexión interrumpida, resultado del envío incierto.');
        } catch (Throwable $exception) {
            Log::error('[WhatsApp] No fue posible completar el envío.', ['exception_type' => $exception::class]);

            return ['status' => 'Fallido', 'success' => false];
        }

        return ['status' => 'Incierto', 'success' => false];
    }

    /**
     * Método base para disparar la petición HTTP a la API de WhatsApp.
     */
    public function enviarMensajeDirecto(string $telefonoDestino, string $mensaje): bool
    {
        $resultado = $this->enviarMensajeDirectoDetallado($telefonoDestino, $mensaje);

        return $resultado['success'];
    }
}
