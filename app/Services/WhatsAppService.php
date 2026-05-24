<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $apiToken;
    protected $modoSimulacion;

    public function __construct()
    {
        // Se toman las credenciales del .env
        $this->apiUrl = env('WHATSAPP_API_URL', '');
        $this->apiToken = env('WHATSAPP_API_TOKEN', '');
        
        // Si no hay token configurado, entramos en modo simulación
        $this->modoSimulacion = empty($this->apiToken) || empty($this->apiUrl);
    }

    /**
     * Envía una notificación de asistencia al tutor del alumno
     */
    public function enviarAvisoAsistencia($telefono, $nombreAlumno, $hora, $tipo = 'Pase de Lista')
    {
        if (empty($telefono)) {
            return false;
        }

        // Limpiar el teléfono de espacios o guiones (ej. 555-1234 -> 5551234)
        $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);

        $mensaje = "🏫 *CAM #48 - Sistema de Accesos*\n\n"
                 . "Le informamos que el alumno(a) *{$nombreAlumno}* ha registrado su asistencia.\n\n"
                 . "⌚ Hora de registro: *{$hora}*\n"
                 . "📌 Tipo: {$tipo}\n\n"
                 . "_Este es un mensaje automático, por favor no responda._";

        return $this->enviarMensaje($telefonoLimpio, $mensaje);
    }

    /**
     * Envía una notificación general sobre un nuevo evento agendado masivamente
     */
    public function enviarAvisoEvento($nombreEvento, $fecha, $ubicacion)
    {
        $mensaje = "📅 *CAM #48 - Nuevo Evento Escolar*\n\n"
                 . "Se ha agendado una nueva actividad en la escuela:\n\n"
                 . "📌 *Actividad:* {$nombreEvento}\n"
                 . "🕒 *Fecha y Hora:* {$fecha}\n";
                 
        if (!empty($ubicacion)) {
            $mensaje .= "📍 *Ubicación:* {$ubicacion}\n";
        }
        
        $mensaje .= "\n_Por favor, agéndelo. Le esperamos._";

        $tutores = \App\Models\Tutor::all();
        $enviados = 0;

        foreach ($tutores as $tutor) {
            // 1. Enviar WhatsApp al Tutor
            if (!empty($tutor->t1_telefono)) {
                $telefonoLimpio = preg_replace('/[^0-9]/', '', $tutor->t1_telefono);
                $this->enviarMensaje($telefonoLimpio, $mensaje);
            }

            // 2. Enviar Correo Electrónico al Tutor
            if (!empty($tutor->t1_email)) {
                try {
                    \Illuminate\Support\Facades\Mail::to($tutor->t1_email)
                        ->send(new \App\Mail\AvisoEventoMail($nombreEvento, $fecha, $ubicacion ?? 'Por confirmar'));
                    Log::info("📧 Correo de Evento enviado a {$tutor->t1_email}");
                } catch (\Exception $e) {
                    Log::error("Fallo al enviar correo a {$tutor->t1_email}: " . $e->getMessage());
                }
            }
            $enviados++;
        }

        Log::info("✅ Aviso de Evento procesado para {$enviados} tutores.");
        return true;
    }

    /**
     * Método base para disparar la petición HTTP a la API de WhatsApp
     */
    private function enviarMensaje($telefonoDestino, $mensaje)
    {
        if ($this->modoSimulacion) {
            // MODO PREPARADO / SIMULADO
            Log::info("🟩 SIMULACIÓN WHATSAPP a [{$telefonoDestino}]:\n{$mensaje}");
            return true;
        }

        // MODO REAL (Cuando se configure el API)
        try {
            // NOTA: Esta estructura varía ligeramente dependiendo del API que contrate la escuela.
            // Aquí hay un ejemplo genérico usado por UltraMsg o GreenAPI.
            
            $response = Http::post($this->apiUrl . '/messages/chat', [
                'token' => $this->apiToken,
                'to' => $telefonoDestino,
                'body' => $mensaje
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp enviado exitosamente a {$telefonoDestino}");
                return true;
            } else {
                Log::error("Fallo al enviar WhatsApp a {$telefonoDestino}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Excepción en WhatsAppService: " . $e->getMessage());
            return false;
        }
    }
}
