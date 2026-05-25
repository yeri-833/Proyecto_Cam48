# TAREAS FASE 3: OPTIMIZACIÓN DE MEMORIA Y ACCESIBILIDAD - FAMILYLOCK

**ATENCIÓN AGENTE DE PROGRAMACIÓN / DESARROLLADOR:**
Esta es la Fase 3 de auditoría del proyecto FamilyLock. El sistema núcleo (Kiosco y Firmas HMAC) ya es estable y seguro. Las siguientes tareas están enfocadas estrictamente en prevenir colapsos de memoria RAM en el servidor y cumplir con estándares de accesibilidad visual (WCAG).

---

## PRIORIDAD 1: Optimización de RAM en Exportación Masiva (Chunking)

**Problema Actual:** En `app/Http/Controllers/HistorialController.php`, el método `exportar()` extrae todos los registros del día a la vez usando `->get()`. Si el CSV requiere exportar miles de registros, PHP lanzará un error de memoria agotada (Allowed memory size exhausted).
**Solución:** Modificar la consulta para procesar lotes de 500 registros a la vez usando el método `chunk()`.

### Paso 1: Refactorizar `HistorialController@exportar`
Sustituye la estructura actual del método por la siguiente lógica optimizada:

```php
public function exportar(Request $request)
{
    $fecha = $request->query('fecha', Carbon::today()->toDateString());
    $busqueda = $request->query('busqueda', '');
    $rol = $request->query('rol', 'Todos');

    $query = $this->getBaseQuery($fecha, $busqueda, $rol);
    $nombreArchivo = "Reporte_Asistencias_" . $fecha . ".csv";

    $cabeceras = [
        "Content-type"        => "text/csv; charset=UTF-8",
        "Content-Disposition" => "attachment; filename=$nombreArchivo",
        "Pragma"              => "no-cache",
        "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
        "Expires"             => "0"
    ];

    $callback = function() use($query) {
        $file = fopen('php://output', 'w');
        
        // BOM for UTF-8 compatibility in Excel
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($file, ['Hora', 'Identificacion', 'Nombre Completo', 'Rol']);

        // Uso de Chunk para procesar en lotes de 500 y no saturar RAM
        $query->select(
            'r.hora',
            'r.id_persona',
            DB::raw("COALESCE(a.nombre, p.nombre_completo, t.t1_nombre, r.id_persona) as nombre_persona"),
            DB::raw("COALESCE(a.apellido, '') as apellido_persona"),
            'r.rol'
        )
        ->orderBy('r.hora', 'desc')
        ->chunk(500, function($registros) use ($file) {
            foreach ($registros as $row) {
                $nombreCompleto = trim($row->nombre_persona . ' ' . $row->apellido_persona);
                fputcsv($file, [
                    Carbon::parse($row->hora)->format('h:i A'),
                    $row->id_persona,
                    $nombreCompleto,
                    $row->rol
                ]);
            }
        });

        fclose($file);
    };

    return new StreamedResponse($callback, 200, $cabeceras);
}
```

---

## PRIORIDAD 2: Accesibilidad (a11y) en Kiosco SPA

**Problema Actual:** En `resources/js/Pages/Asistencia/Escaner.vue`, los botones principales de la cabecera del Kiosco (Pantalla completa, USB/Cámara, y Cerrar) no tienen el atributo `aria-label`. Esto es una mala práctica de UX e impide que lectores de pantalla interactúen correctamente.

### Paso 1: Actualizar Botones en `Escaner.vue`
En el bloque `<nav>` (aproximadamente en la línea 314), localiza los botones y añade los `aria-label`:

1.  **Botón Pantalla Completa:**
    ```html
    <button aria-label="Alternar pantalla completa" @click="togglePantallaCompleta" ...>
    ```

2.  **Botón Toggle USB/Cámara:**
    ```html
    <button :aria-label="usandoCamara ? 'Cambiar a lector USB' : 'Cambiar a cámara web'" @click="toggleModoCamara" ...>
    ```

3.  **Botón de Bloqueo/Salir:**
    ```html
    <button aria-label="Salir del modo kiosco y regresar al panel de control" @click="intentarSalir" ...>
    ```

---
**FIN DEL REPORTE FASE 3.**
