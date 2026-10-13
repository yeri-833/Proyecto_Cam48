# FamilyLock CAM #48 — Plan de Implementación: Hardening de Seguridad y Autorizaciones

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar un hardening de seguridad integral y defensivo en el backend de FamilyLock CAM #48 según OWASP ASVS, aislando completamente las pruebas para proteger la base de datos institucional `proyecto_colegio`, restringiendo el registro público no autorizado, implementando autorizaciones por rol (Director/Admin, Personal/Docente, Operador QR), blindando la privacidad de los expedientes digitales y diseñando el protocolo criptográfico de vinculación de Telegram, manteniendo Larastan en 0 errores.

**Architecture:** Se implementa un circuito de protección (circuit-breaker) en `tests/TestCase.php` y configuración estricta en `phpunit.xml`. El registro público de cuentas en `routes/auth.php` y `RegisteredUserController.php` se restringe exclusivamente a usuarios administradores autenticados o con token de invitación. Se define una matriz de autorización en `AppServiceProvider` con Gates institucionales (`admin-access`, `personal-access`, `kiosco-access`, `eliminar-registros`, `gestionar-usuarios`). En `ExpedienteController`, se refuerza la autorización por Gate para ver y descargar archivos, manteniendo los documentos nuevos en el disco `local` privado.

**Tech Stack:** Laravel 12/13, PHP 8.5, Larastan Nivel 5, Laravel Pint, SQLite in-memory para testing aislado, OWASP ASVS v4.0.

**Spec:** Especificación dada por el usuario en USER_REQUEST para la Fase 2: Hardening de Seguridad y Autorizaciones.

## Global Constraints

- Prohibido terminantemente conectarse a la base institucional `proyecto_colegio`, incluso para lecturas o diagnósticos.
- Prohibido ejecutar migraciones, rollback, fresh, reset, wipe o seeders en la base de datos institucional.
- Prohibido modificar el archivo `.env` o exponer credenciales en logs o comandos.
- Prohibido modificar las cuentas o contraseñas existentes de MariaDB.
- Prohibido alterar componentes visuales Vue, estilos CSS, logos institucionales, sidebar o diseño de credenciales.
- No realizar commits ni pushes sin autorización expresa.
- Mantener Larastan en 0 errores (Nivel 5) sin recurrir a `ignoreErrors` ni baseline.
- Todo código PHP modificado debe pasar el linter de sintaxis (`php -l`) y formateo con Laravel Pint (`pint --format agent`).

## Review Focus

1. **Intento de conexión a `proyecto_colegio` en pruebas:** El circuit breaker en `TestCase::setUp()` debe abortar inmediatamente con `RuntimeException` si el nombre de la base de datos contiene `proyecto_colegio` sin sufijo de pruebas.
2. **Acceso no autenticado o no administrativo a `/register`:** Debe abortar con HTTP 403 Forbidden o redirigir con error si el usuario no es un Administrador autenticado.
3. **Escalamiento de privilegios en creación de usuarios:** `RegisteredUserController` debe impedir que usuarios no autorizados asignen roles arbitrarios.
4. **Acceso no autorizado a expedientes confidenciales:** Los endpoints `/expedientes/{id}/ver` y `/expedientes/{id}/descargar` deben validar el Gate institucional correspondiente antes de entregar bytes del archivo.
5. **Regresiones en Larastan Nivel 5:** Ningún cambio de código debe reintroducir observaciones en el análisis estático.

---

### Task 1: Circuito de Protección Institucional y Configuración Segura de Pruebas

**Files:**
- Modify: `tests/TestCase.php`
- Modify: `phpunit.xml`

**Interfaces:**
- Consumes: `Illuminate\Foundation\Testing\TestCase`, `config('database.*')`
- Produces: Protección activa en `TestCase::setUp()` contra ejecuciones accidentales en `proyecto_colegio`.

- [ ] **Step 1: Inspeccionar y modificar `tests/TestCase.php` con el circuito de protección institucional.**
- [ ] **Step 2: Modificar `phpunit.xml` para asegurar que `DB_CONNECTION` use `sqlite` y `DB_DATABASE` use `:memory:` por defecto.**
- [ ] **Step 3: Verificar que la sintaxis de `tests/TestCase.php` sea válida con `php -l`.**
- [ ] **Step 4: Formatear con Laravel Pint.**

---

### Task 2: Restricción del Registro Público de Cuentas (Protección contra Auto-Creación)

**Files:**
- Modify: `routes/auth.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Gate`, `App\Models\User`
- Produces: Registro restringido a administradores autenticados o con token de invitación seguro.

- [ ] **Step 1: Restringir rutas `/register` en `routes/auth.php`:** Mover la creación de usuarios al grupo administrativo o condicionar la acción si no es administrador ni posee token de invitación.
- [ ] **Step 2: Actualizar `RegisteredUserController.php`:** Validar autorización institucional para registrar usuarios, asignar por defecto un rol no privilegiado ('Docente') si no se especifica, y evitar inicio de sesión automático si un administrador crea una cuenta ajena.
- [ ] **Step 3: Verificar sintaxis y análisis estático con Larastan.**
- [ ] **Step 4: Formatear con Laravel Pint.**

---

### Task 3: Matriz de Autorización por Roles y Definición de Gates

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Consumes: `$user->tipo_usuario`, `$user->role`
- Produces: Gates `admin-access`, `personal-access`, `kiosco-access`, `eliminar-registros`, `gestionar-usuarios`. Métodos auxiliares en `User`: `isAdmin()`, `isPersonal()`, `isKiosco()`.

- [ ] **Step 1: Añadir métodos semánticos en `User.php`:** `isAdmin(): bool`, `isPersonal(): bool`, `isKiosco(): bool`.
- [ ] **Step 2: Definir Gates institucionales en `AppServiceProvider::boot()`:**
  - `admin-access`: Administrador o Director.
  - `personal-access`: Administrador, Director, Docente, Personal.
  - `kiosco-access`: Administrador, Director o rol de Kiosco/Operador/Asistencia.
  - `eliminar-registros`: Administrador o Director.
  - `gestionar-usuarios`: Administrador o Director.
- [ ] **Step 3: Verificar con `php -l` y Larastan.**
- [ ] **Step 4: Formatear con Laravel Pint.**

---

### Task 4: Blindaje de Expedientes Digitales Confidenciales

**Files:**
- Modify: `app/Http/Controllers/ExpedienteController.php`

**Interfaces:**
- Consumes: `Gate::authorize('personal-access')`, `Storage::disk('local')`
- Produces: Verificación de acceso y descarga protegida de documentos confidenciales.

- [ ] **Step 1: Aplicar autorización por Gate en `ver($id)` y `descargar($id)`:** Exigir que el usuario posea al menos `personal-access`.
- [ ] **Step 2: Validar tipos MIME y protección contra path traversal en nombres de archivo.**
- [ ] **Step 3: Confirmar que documentos nuevos continúen almacenándose exclusivamente en disco `local` privado.**
- [ ] **Step 4: Verificar sintaxis y Larastan.**
- [ ] **Step 5: Formatear con Laravel Pint.**

---

### Task 5: Diseño del Protocolo de Vinculación Criptográfica de Telegram

**Files:**
- Create: `docs/seguridad/protocolo_vinculacion_telegram.md`

**Interfaces:**
- Propuesta de arquitectura técnica y esquema de migración reversible (sin ejecutar cambios de base de datos en esta fase).

- [ ] **Step 1: Redactar especificación técnica de tokens efímeros HMAC / UUIDv4 con vencimiento de 30 minutos y de un solo uso.**
- [ ] **Step 2: Documentar flujo de inicio seguro: `/start VINC_<token>`.**
- [ ] **Step 3: Definir esquema propuesto para la tabla `telegram_vinculaciones`.**

---

### Task 6: Inventario Seguro de Limpieza y Retiro de Scripts con Secretos

**Files:**
- Documentar en el reporte final la matriz de scripts temporales en `scratch/` que contienen lógica de conexión o diagnósticos anteriores para su retiro seguro.

- [ ] **Step 1: Identificar scripts temporales a retirar (`scratch/check_db.php`, `scratch/diagnostico_bd.php`, etc.).**
- [ ] **Step 2: Validar que el código productivo no tenga dependencias hacia ningún archivo en `scratch/`.**

---

### Task 7: Verificación Final con Larastan y Laravel Pint

**Files:**
- All modified files

- [ ] **Step 1: Ejecutar Larastan Nivel 5 y verificar 0 errores.**
- [ ] **Step 2: Ejecutar Laravel Pint sobre todos los archivos editados.**
- [ ] **Step 3: Ejecutar `git status` para confirmar árbol de trabajo limpio sin commits ni pushes.**
