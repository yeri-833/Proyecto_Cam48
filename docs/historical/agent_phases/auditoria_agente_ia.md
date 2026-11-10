# AUDITORÍA DE ARQUITECTURA Y SEGURIDAD - FAMILYLOCK (PROYECTO CAM 48)

**ATENCIÓN AGENTE DE PROGRAMACIÓN / DESARROLLADOR:** 
Este documento contiene el análisis arquitectónico, ingeniería inversa y los hallazgos críticos de seguridad y rendimiento del sistema "FamilyLock". Tu objetivo es ejecutar las refactorizaciones y correcciones listadas en este documento siguiendo estrictamente los principios SOLID, Clean Code y patrones de diseño Enterprise de Laravel.

---

## 1. ANÁLISIS ARQUITECTÓNICO
*   **Stack Tecnológico:** Laravel 10/11, Vue.js (Inertia.js), TailwindCSS, MySQL.
*   **Patrón de Arquitectura:** Monolítico MVC Híbrido.
*   **Problema Principal:** Alto acoplamiento en la capa de controladores. `AsistenciaController` orquesta peticiones HTTP, reglas de negocio complejas y llamadas a APIs externas sincrónicas. `DashboardController` viola el Principio de Responsabilidad Única (SRP) al manejar el CRUD de Alumnos.

## 2. INGENIERÍA INVERSA (FLUJO CRÍTICO)
**Flujo Actual del Escáner QR (`AsistenciaController@procesarQr`):**
1. Recibe payload (JSON o String) con ID de persona.
2. Consulta base de datos (`alumnos`, `tutores` o `personal`) para validar estatus `activo`.
3. Valida cooldown de 5 minutos para evitar lecturas duplicadas.
4. Inserta registro en `registros_asistencia`.
5. **(CUELLO DE BOTELLA CRÍTICO):** Instancia `WhatsAppService` y `TelegramService` enviando peticiones HTTP bloqueantes.
6. Retorna JSON al frontend.

## 3. ANÁLISIS BACKEND
*   **Refactorización Urgente requerida en Rutas (`routes/web.php`):** 
    *   La ruta `/api/telegram/sync` está en `web.php`. Debe moverse a `api.php` o ser tratada como webhook sin middlewares de sesión web.
    *   Faltan Resource Controllers. Hay que agrupar rutas de personal, tutores, etc.
*   **Falta de Inyección de Dependencias:** Los servicios externos deben inyectarse a través del constructor, no instanciarse directamente.

## 4. ANÁLISIS FRONTEND
*   **Componente Analizado (`Pagination.vue`):** Depende de clases de Tailwind muy extensas directamente en el template. Carece de etiquetas ARIA (`aria-current="page"`, `aria-label`) para accesibilidad de lectores de pantalla. Usa `v-html` lo que supone un riesgo XSS si la data no viene sanitizada del paginador de Laravel.

## 5. ANÁLISIS DE BASE DE DATOS
*   **Vulnerabilidad de Integridad Referencial:** La tabla `registros_asistencia` utiliza un "Polimorfismo Blando" (`id_persona` de tipo String + columna `rol`). MySQL no puede aplicar restricciones de llaves foráneas lógicas. 
*   **Falta de Auditoría:** Modelos críticos como `Alumno` y `RegistroAsistencia` tienen `public $timestamps = false;`. Es imposible auditar alteraciones en los datos.

## 6. AUDITORÍA DE SEGURIDAD
*   **A04:2021 Diseño Inseguro (Riesgo QR):** El payload del QR actual (`{"id":"matricula"}`) es estático. Es extremadamente vulnerable a ataques de clonación y replay. Cualquier persona con la foto del QR de un alumno puede registrar su asistencia.
*   **Rate Limiting Ausente:** El endpoint de escaneo (`/escaner/procesar`) no tiene límite de peticiones (throttle). Es susceptible a ataques DoS o fuerza bruta para disparar la API de WhatsApp, agotando los créditos o baneando el número de la escuela.

## 7. ANÁLISIS DE RENDIMIENTO
*   **Latencia Crítica (Bloqueo de Hilo PHP):** Las llamadas a las APIs de WhatsApp y Telegram en `AsistenciaController` son síncronas. Esto bloquea el hilo de ejecución por 1 a 3 segundos por cada alumno escaneado. En hora pico, esto congelará la interfaz del escáner en la escuela, causando un embotellamiento físico.

---

## 8. TAREAS A EJECUTAR POR EL AGENTE DE PROGRAMACIÓN

Por favor, ejecuta las siguientes tareas en orden de prioridad:

### PRIORIDAD 1: Evitar el colapso del escáner (Implementar Queues)
1. Configura el sistema de colas de Laravel (ej. driver `database` o `redis`).
2. Crea un Job `php artisan make:job NotificarAsistenciaJob`.
3. Mueve la lógica de instanciación y envío de `WhatsAppService` y `TelegramService` dentro del método `handle()` del Job.
4. En `AsistenciaController@procesarQr`, reemplaza el código síncrono por: `NotificarAsistenciaJob::dispatch($alumno, $tipoPase);`.

### PRIORIDAD 2: Seguridad y Arquitectura de Datos
5. Activa los `$timestamps = true` en `Alumno.php` y `RegistroAsistencia.php` y crea una migración para añadir `created_at` y `updated_at`.
6. **(Opcional pero recomendado):** Migra la tabla `registros_asistencia` a Relaciones Polimórficas nativas de Laravel (`asistible_id`, `asistible_type`) usando `$table->morphs('asistible')`.
7. Añade el middleware `throttle:60,1` (60 peticiones por minuto) a la ruta de `/escaner/procesar` en `web.php` para prevenir ataques DoS.

### PRIORIDAD 3: Clean Code en Controladores
8. Remueve los métodos de gestión de alumnos (`storeAlumno`, `updateAlumno`) del `DashboardController` y muévelos a un nuevo `AlumnoController` dedicado, respetando el Principio de Responsabilidad Única.
9. Refactoriza el `web.php` agrupando las rutas bajo prefijos y nombres lógicos.

### PRIORIDAD 4: Frontend y UX
10. En `Pagination.vue`, extrae las clases CSS dinámicas a un `computed` e integra atributos `aria-*` para accesibilidad.

---
**FIN DEL REPORTE.**
