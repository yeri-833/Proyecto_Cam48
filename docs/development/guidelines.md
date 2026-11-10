# FamilyLock — Guía de Desarrollo y Buenas Prácticas (v1.4.8)

## 1. Convenciones de Código y Estándares

### PHP y Laravel
- **Estándar de Codificación**: PSR-12 / Laravel Pint (`vendor/bin/pint --format agent`).
- **Versión Mínima**: PHP 8.3 (soporte probado hasta PHP 8.5.9).
- **Control de Flujo**: Siempre utilizar llaves `{}` en estructuras de control, incluso para cuerpos de una sola línea.
- **Constructor Property Promotion**: Utilizar la sintaxis de promoción de propiedades en constructores:
  ```php
  public function __construct(
      public WhatsAppService $whatsAppService,
      public TelegramService $telegramService
  ) {}
  ```
- **Declaración Estricta de Tipos**: Declarar tipos de retorno y tipos de parámetros en todos los métodos de controladores, servicios y modelos:
  ```php
  public function vincularAlumno(Request $request, int|string $id): RedirectResponse
  ```
- **Modelos y Factorías**: Al crear o modificar modelos, definir accesores tipados, relaciones explícitas con PHPDoc y factorías para cobertura en pruebas.

### Vue 3 e Inertia.js v2
- **Composition API**: Usar siempre `<script setup>` con Composition API.
- **Raíz Única en Componentes**: Cada componente Vue debe tener un único elemento raíz en el `<template>`.
- **Desuscripción de Eventos Globales**: Si se escuchan eventos globales de Inertia (`router.on('start')`, `router.on('finish')`), almacenar sus callbacks y ejecutarlos en el hook `onUnmounted()` para evitar fugas de memoria.
- **Gestión de Props**: Validar props mediante `defineProps()` con tipos y validadores explícitos.
- **Estilos con Tailwind CSS**: Evitar clases inline arbitrarias redundantes; usar tokens estándar y soporte de modo oscuro semántico (`dark:bg-slate-800`).

---

## 2. Principios de Diseño Aplicados (SOLID / DRY / KISS)

### Single Responsibility Principle (SRP)
- **Controladores**: Su única responsabilidad es recibir peticiones HTTP, autorizar permisos (`Gate::authorize`), invocar servicios de dominio o modelos y devolver respuestas Inertia/JSON.
- **Servicios de Dominio (`app/Services/`)**: Lógica de negocio reusable y especializada:
  - `FamiliaService`: Resolución de identidades familiares y grafos de hermanos.
  - `WhatsAppService`: Integración y resiliencia con pasarela WhatsApp.
  - `TelegramService`: Sanitización, tokenización y despacho a Telegram Bot API.
  - `ImageUploadService`: Procesamiento y optimización de fotografías de alumnos y credenciales.
- **Jobs Asíncronos (`app/Jobs/`)**: Tareas diferidas de red o mensajería masiva con gestión propia de reintentos e idempotencia.

### Don't Repeat Yourself (DRY)
- El cálculo de la clave familiar canónica está centralizado en `FamiliaService::obtenerClaveFamilia()`, delegando en él tanto el modelo `Tutor` (`$tutor->familia_key`), como `Alumno` (`$alumno->familia_key`) y los controladores de estadísticas y asistencias.
- Los accesores de nombres completos (`nombre_completo`) centralizan el formateo en mayúsculas multibyte UTF-8 (`mb_strtoupper(..., 'UTF-8')`).

### Keep It Simple, Stupid (KISS)
- No se introducen capas de abstracción innecesarias (Repository Pattern innecesario sobre Eloquent). Eloquent ORM se utiliza directamente con scopes de consulta (`accessibleTo`) y relaciones tipadas.

---

## 3. Protocolo de Pruebas Obligatorio

1. **Aislamiento Total**: Nunca ejecutar pruebas contra bases de datos en producción ni utilizar datos reales de alumnos o tutores.
2. **Entorno Windows Sandbox**: Todas las pruebas automatizadas (PHPUnit, Larastan, Vite Build) deben ejecutarse en la máquina virtual aislada de Windows Sandbox (`wsb.exe`).
3. **Transportes HTTP Simulados**: Todas las pruebas de mensajería deben interceptar el tráfico mediante `Http::fake()` o `IsolatedHttpFactory`, evitando llamadas externas reales a Telegram o WhatsApp.
4. **Verificación Antes de Aserción**: Ninguna prueba debe declararse aprobada sin evidencia auditable de salida y código de salida `0`.
