# TAREAS FASE 2: SEGURIDAD Y REFACTORIZACIÓN - FAMILYLOCK

**ATENCIÓN AGENTE DE PROGRAMACIÓN / DESARROLLADOR:**
Esta es la Fase 2 de optimización y seguridad del proyecto FamilyLock. Por favor, ejecuta las siguientes tareas asegurando la máxima calidad de código (Clean Code).

---

## PRIORIDAD 1: Seguridad Anti-Clonación de QR (Firma HMAC)

**Problema Actual:** El payload del QR contiene texto plano (ej. `{"id":"123", "rol":"Alumno"}`). Esto permite a cualquiera generar un QR falso o clonarlo.
**Solución:** Firmar criptográficamente el payload utilizando `hash_hmac` y la `APP_KEY` del sistema.

### Paso 1: Modificar la validación en `AsistenciaController.php`
En el método `procesarQr(Request $request)`, debes modificar la forma en que se extrae y valida el JSON.
1. Cuando se decodifique el JSON (`$decoded = json_decode($qrData, true);`), asegúrate de extraer una clave llamada `signature`.
2. Genera la firma esperada usando la llave de la aplicación:
   ```php
   $expectedSignature = hash_hmac('sha256', $idPersona . $rol, config('app.key'));
   ```
3. Compara la firma recibida con la generada usando `hash_equals` para prevenir ataques de timing:
   ```php
   if (!isset($decoded['signature']) || !hash_equals($expectedSignature, $decoded['signature'])) {
       return response()->json([
           'status' => 'error',
           'msg' => 'Código QR inválido o falsificado (Firma incorrecta)'
       ]);
   }
   ```
*(Nota: Debes mantener compatibilidad hacia atrás o actualizar a todos los alumnos si aplicas este bloqueo estricto de inmediato).*

### Paso 2: Actualizar el Generador de QR
En el lugar donde se generen los códigos QR (probablemente `CredencialController.php` o en las vistas de Vue.js), debes generar el JSON incluyendo esta firma. 
*   **Si se genera en el Backend (PHP):** 
    ```php
    $signature = hash_hmac('sha256', $alumno->matricula . 'Alumno', config('app.key'));
    $qrPayload = json_encode([
        'id' => $alumno->matricula,
        'rol' => 'Alumno',
        'signature' => $signature
    ]);
    ```

---

## PRIORIDAD 2: Refactorización DRY (Don't Repeat Yourself)

**Problema Actual:** En `AlumnoController.php`, las líneas que gestionan la subida y compresión de la imagen (`Intervention\ImageManager`) son idénticas en los métodos `store` y `update`.

### Paso 1: Crear Servicio de Imágenes
Crea un nuevo archivo `app/Services/ImageUploadService.php`:
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadService
{
    /**
     * Procesa, comprime y guarda una imagen.
     */
    public function procesarYGuardar($file, $path = 'fotos/alumnos/', $width = 800)
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());
        
        // Escalar manteniendo proporción
        $image->scaleDown(width: $width);
        
        // Convertir a JPG al 80% de calidad
        $encoded = $image->toJpeg(quality: 80);
        
        $filename = $path . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, $encoded->toString());
        
        return $filename;
    }
}
```

### Paso 2: Inyectar el Servicio en `AlumnoController`
Modifica `AlumnoController.php` para inyectar este servicio en los métodos `store` y `update`:

```php
use App\Services\ImageUploadService;

// En store():
public function store(Request $request, ImageUploadService $imageService)
{
    // ... (validaciones y data map) ...
    if ($request->hasFile('foto_perfil')) {
        $data['foto_perfil'] = $imageService->procesarYGuardar($request->file('foto_perfil'));
    }
    // ...
}

// En update():
public function update(Request $request, $matricula, ImageUploadService $imageService)
{
    // ... (validaciones y data map) ...
    if ($request->hasFile('foto_perfil')) {
        if ($alumno->foto_perfil && Storage::disk('public')->exists($alumno->foto_perfil)) {
            Storage::disk('public')->delete($alumno->foto_perfil);
        }
        $data['foto_perfil'] = $imageService->procesarYGuardar($request->file('foto_perfil'));
    }
    // ...
}
```

**Beneficio Técnico:** Reducción de código duplicado, facilitación de Test Unitarios sobre el procesamiento de imágenes, y centralización de la calidad y tamaño de conversión en toda la plataforma.

---
**FIN DEL REPORTE.**
