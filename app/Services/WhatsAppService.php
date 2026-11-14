<?php

namespace App\Services;

use App\Jobs\NotificarEventoTutorJob;
use App\Models\Tutor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Enmascara el número telefónico para salvaguardar la privacidad en logs operativos (OWASP A03).
     */
    private function maskPhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone) ?? '';
        $len = strlen($clean);
        if ($len <= 4) {
            return '****';
        }

        return substr($clean, 0, 3).'****'.substr($clean, -2);
    }

    /**
     * Envía una notificación de asistencia al tutor del alumno.
     */
    public function enviarAvisoAsistencia(string $telefono, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): bool
    {
        if (empty($telefono)) {
            return false;
        }

        $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono) ?? '';

        $mensaje = "🏫 *CAM #48 - Sistema de Accesos*\n\n"
                 ."Le informamos que el alumno(a) *{$nombreAlumno}* ha registrado su asistencia.\n\n"
                 ."⌚ Hora de registro: *{$hora}*\n"
                 ."📌 Tipo: {$tipo}\n\n"
                 .'_Este es un mensaje automático, por favor no responda._';

        return $this->enviarMensajeDirecto($telefonoLimpio, $mensaje);
    }

    /**
     * Envía una notificación general sobre un nuevo evento encolando los envíos individuales (asíncrono).
     */
    public function enviarAvisoEvento(string $nombreEvento, string $fecha, ?string $ubicacion, ?int $eventoId = null): bool
    {
        if ($eventoId === null || $eventoId <= 0) {
            Log::warning('[WhatsApp] Despacho de aviso de evento abortado: falta eventoId explícito.', [
                'nombre_evento' => $nombreEvento,
            ]);

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
     * Envía mensaje con reporte estructurado de estado operativo y enmascaramiento de datos personales.
     *
     * @return array{status: string, success: bool}
     */
    public function enviarMensajeDirectoDetallado(string $telefonoDestino, string $mensaje): array
    {
        $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefonoDestino) ?? '';
        $telefonoEnmascarado = $this->maskPhone($telefonoLimpio);

        if ($this->modoSimulacion) {
            Log::info("[WhatsApp] [SIMULADO] Mensaje preparado para destinatario: {$telefonoEnmascarado}. Longitud: ".strlen($mensaje).' caracteres.');

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

            if ($response->successful()) {
                Log::info("[WhatsApp] [ENVIADO_PROVEEDOR] Mensaje entregado exitosamente a {$telefonoEnmascarado}");

                return [
                    'status' => 'Enviado a proveedor',
                    'success' => true,
                ];
            } else {
                Log::error("[WhatsApp] [FALLIDO] Error devuelto por proveedor para {$telefonoEnmascarado} (HTTP {$response->status()})");

                return [
                    'status' => 'Fallido',
                    'success' => false,
                ];
            }
        } catch (\Exception $e) {
            Log::error("[WhatsApp] [FALLIDO] Excepción de conexión: {$e->getMessage()}");

            return [
                'status' => 'Fallido',
                'success' => false,
            ];
        }
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
