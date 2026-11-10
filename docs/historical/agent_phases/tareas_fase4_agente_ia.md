# TAREAS FASE 4: OPTIMIZACIÓN DE COMUNICACIONES Y BACKGROUND JOBS

**ATENCIÓN AGENTE DE PROGRAMACIÓN / DESARROLLADOR:**
Esta es la Fase 4 de auditoría del proyecto FamilyLock. El sistema requiere optimización urgente en el envío de notificaciones masivas para evitar "Timeouts" y caídas del servidor web cuando se programan eventos escolares. Además, se deben corregir malas prácticas con la lectura de variables de entorno.

---

## PRIORIDAD 1: Evitar Caída del Servidor por Envíos Masivos Síncronos

**Problema Actual:** En `app/Services/WhatsAppService.php`, el método `enviarAvisoEvento()` carga a todos los tutores con `Tutor::all()` y hace un bucle síncrono enviando un WhatsApp y un Correo por cada uno. Si hay 500 tutores, el hilo de PHP se bloqueará por ~10 minutos, causando un `504 Gateway Timeout` y deteniendo el envío a la mitad.
**Solución:** Despachar cada aviso de evento a un _Job_ en segundo plano y cargar los tutores por lotes (Chunking).

### Paso 1: Crear el Job para Notificaciones Masivas
Ejecuta `php artisan make:job NotificarEventoTutorJob` y configúralo de la siguiente manera:

```php
<?php

namespace App\Jobs;

use App\Models\Tutor;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function __construct(Tutor $tutor, $mensajeWhatsapp, $nombreEvento, $fecha, $ubicacion)
    {
        $this->tutor = $tutor;
        $this->mensajeWhatsapp = $mensajeWhatsapp;
        $this->nombreEvento = $nombreEvento;
        $this->fecha = $fecha;
        $this->ubicacion = $ubicacion;
    }

    public function handle(WhatsAppService $whatsappService)
    {
        // 1. Enviar WhatsApp
        if (!empty($this->tutor->t1_telefono)) {
            $telefonoLimpio = preg_replace('/[^0-9]/', '', $this->tutor->t1_telefono);
            // IMPORTANTE: En el WhatsAppService debes crear un método público 'enviarMensajeDirecto' 
            // similar al privado 'enviarMensaje', o cambiar 'enviarMensaje' a public.
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
```

### Paso 2: Refactorizar `WhatsAppService@enviarAvisoEvento`
Sustituye la lógica interna del método para usar `chunk` y `dispatch`:

```php
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

    // Procesar en lotes de 100 para no saturar memoria RAM
    \App\Models\Tutor::chunk(100, function ($tutores) use ($mensaje, $nombreEvento, $fecha, $ubicacion) {
        foreach ($tutores as $tutor) {
            // Despachar a la cola (respuesta en milisegundos)
            \App\Jobs\NotificarEventoTutorJob::dispatch($tutor, $mensaje, $nombreEvento, $fecha, $ubicacion ?? 'Por confirmar');
        }
    });

    Log::info("✅ Creación de avisos de Evento masivos enviada a la Cola de Trabajos.");
    return true;
}
```

---

## PRIORIDAD 2: Seguridad y Configuración (Buenas Prácticas)

**Problema Actual:** `WhatsAppService` lee las variables con `env('...')`. Leer el `.env` directamente fuera de los archivos en la carpeta `config/` puede romper el sistema en producción al ejecutar el comando de optimización `php artisan config:cache`, devolviendo `null`.

### Paso 1: Crear Archivo de Configuración
Crea un archivo llamado `config/whatsapp.php`:

```php
<?php

return [
    'api_url' => env('WHATSAPP_API_URL', ''),
    'api_token' => env('WHATSAPP_API_TOKEN', ''),
];
```

### Paso 2: Actualizar el Constructor de `WhatsAppService`
Cambia el uso de `env()` por `config()`:

```php
public function __construct()
{
    // Se toman las credenciales vía config() en lugar de env()
    $this->apiUrl = config('whatsapp.api_url', '');
    $this->apiToken = config('whatsapp.api_token', '');
    
    // Si no hay token configurado, entramos en modo simulación
    $this->modoSimulacion = empty($this->apiToken) || empty($this->apiUrl);
}
```

---
**FIN DEL REPORTE FASE 4.**
