# FamilyLock — Estrategia y Arquitectura de Pruebas (v1.4.8)

## 1. Filosofía de Pruebas en Entorno Aislado

El sistema FamilyLock procesa información sensible de menores de edad y credenciales de acceso institucional. Por ello, la suite de pruebas se rige bajo tres mandatos inquebrantables:

1. **Aislamiento Total del Sistema Anfitrión**: Ningún comando de prueba (`php`, `artisan`, `phpunit`, `composer`) se ejecuta directamente en la máquina anfitriona. Todo se canaliza a través de **Windows Sandbox** (`wsb.exe`).
2. **Red Desconectada**: Las pruebas no consumen endpoints reales de Telegram ni pasarelas comerciales de WhatsApp. Todo el transporte externo se intercepta sintéticamente.
3. **Persistencia Efímera**: Los datos de prueba se generan en bases SQLite en memoria o instancias MariaDB efímeras que se destruyen al finalizar la ejecución.

---

## 2. Componentes del Arnés de Aislamiento (`tests/Isolation/`)

El arnés de pruebas aisladas en memoria reside en [`tests/Isolation/`](file:///c:/Users/YERALDI%20EMMMANUEL/Desktop/Proyecto_Cam48/tests/Isolation/):

- **`run.php`**: Lanzador con comprobaciones de seguridad estrictas:
  - Exige bandera de ejecución segura `--authorize-isolated-run`.
  - Verifica que PHP se invoque sin `php.ini` host (`php.exe -n`).
  - Configura variables `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
  - Valida que extensiones esenciales (`mbstring`, `gd`, `openssl`, `fileinfo`, `pdo_sqlite`) estén presentes en la carpeta de entrada.
- **`IsolationGuard.php`**: Guardián que aborta la ejecución si detecta configuración de caché en disco (`bootstrap/cache/config.php`) o conexión a bases de datos de producción.
- **`SyntheticSchema.php`**: Inicializador ultra-rápido de la base de datos sintética en SQLite en memoria, replicando exactamente las tablas y relaciones del motor MariaDB.
- **`IsolatedHttpFactory.php`**: Creador de clientes HTTP simulados para pruebas de transporte sin red.
- **`MariaDbVerificationRunner.php`**: Validador integral ejecutado sobre MariaDB 12.3 real en el Sandbox (puerto 3307), comprobando migraciones, claves foráneas, restricciones UNIQUE y transacciones concurrentes.

---

## 3. Catálogo de Suites de Pruebas

### Pruebas de Dominio y Participación
- **`TutoresAsistenciaParticipacionTest`** (16 pruebas, 173 aserciones):
  - Cobertura de familias con múltiples hijos vía `alumno_tutor`.
  - Tutores homónimos en distintas familias.
  - Familias que comparten teléfonos comunitarios.
  - Asistencia a múltiples juntas escolares.
  - Rechazo de asistencias duplicadas al mismo evento.
  - Ranking individual de tutores por asistencias reales.
  - Denominador de porcentaje basado estrictamente en tutores convocados.
  - Vinculación y desvinculación explícita de hermanos (`TutorController`).
  - Registro de asistencia manual a eventos.

### Pruebas de Resiliencia y Mensajería
- **`NotificacionesPersonalAsincronasTest`** (7 pruebas, 25 aserciones):
  - Despacho asíncrono con colas simuladas.
  - Idempotencia en tabla `notificaciones_procesadas`.
  - Recuperación de bloqueos zombi abandonados por más de 5 minutos.
  - Registro terminal `FALLIDO` ante excepciones permanentes.
- **`NotificacionesResilienciaTest`** (8 pruebas, 21 aserciones):
  - Comportamiento ante errores HTTP 500, 429 (Rate Limit) y 403 (Forbidden).
  - Timeouts de conexión de red y fallos de DNS controlados.
  - Confirmación de que la idempotencia previene la reejecución de mensajes en canales previos ante reintentos de workers.

### Pruebas de Seguridad y Exportación
- **`HistorialExportacionTest`** (30 pruebas, 233 aserciones):
  - Pruebas paramétricas con 5 DataProviders.
  - Validación de cabeceras MIME (`text/csv; charset=UTF-8`), BOM UTF-8 y orden cronológico descendente.
  - Sanitización estricta contra inyección CSV (comas, comillas, saltos de línea).
  - Verificación de chunking de memoria y matriz RBAC de autorización.
- **`TelegramSalidasSegurasTest`** (16 pruebas, 199 aserciones):
  - Sanitización de salidas en Telegram.
  - Prevención de fugas de PII en excepciones.
- **`EscaneoSeguridadTest`** (3 pruebas, 6 aserciones):
  - Validación de firmas HMAC en códigos QR.
  - Rechazo de credenciales alteradas o con fecha expirada.
- **`AlumnosPreservacionDatosTest`** (5 pruebas, 49 aserciones):
  - Integridad de campos de alumnos ante actualizaciones parciales del Dashboard.
  - Mecanismo protegido de borrado explícito.
- **`ComposerScriptSecurityTest`** (2 pruebas, 7 aserciones):
  - Protección de `APP_KEY` ante comandos de instalación/actualización.
  - Migraciones gráciles sin `--force` destructivo.

---

## 4. Ejecución en Windows Sandbox

Para ejecutar la verificación completa:

```powershell
# Iniciar Sandbox con carpetas compartidas
wsb.exe start
wsb.exe share --id <ID> -f "<HOST_STAGING>" -s "C:\FamilyLockInput"
wsb.exe share --id <ID> -f "<HOST_RESULTS>" -s "C:\FamilyLockResults" -w

# Ejecutar suite PHPUnit aislada
wsb.exe exec --id <ID> -r System -d "C:\Project" -c "cmd.exe /c C:\FamilyLockInput\php\php.exe -n tests\Isolation\run.php phpunit --authorize-isolated-run"

# Ejecutar análisis estático Larastan
wsb.exe exec --id <ID> -r System -d "C:\Project" -c "cmd.exe /c C:\FamilyLockInput\php\php.exe -n tests\Isolation\run.php larastan --authorize-isolated-run"
```
