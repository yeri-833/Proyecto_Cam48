# Historial de Cambios (Changelog) - Proyecto CAM 48

Todos los cambios notables en este proyecto serán documentados en este archivo. El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a [SemVer (Versionamiento Semántico)](https://semver.org/lang/es/).

## [1.4.8] - 2026-11-08

### Limpieza Integral, Optimización y Estandarización Profesional
- **Jerarquía Oficial de Documentación (`docs/`)**: Reestructuración completa de los documentos técnicos en directorios especializados (`architecture/`, `database/`, `development/`, `security/`, `testing/`, `operations/`, `api/`, `historical/`), trasladando archivos históricos sueltos de la raíz del proyecto a `docs/historical/agent_phases/` y `docs/historical/superpowers/`.
- **Auditoría y Retiro de Dependencias Innecesarias (`@tailwindcss/vite`)**: Verificación y desinstalación controlada de `@tailwindcss/vite` mediante `npm uninstall` en entorno Sandbox, actualizando limpiamente `package-lock.json` y confirmando la correcta compilación de producción de Vite mediante PostCSS y Tailwind CSS v3.
- **Optimización de Recursos Gráficos Públicos**:
  - Detección y eliminación del duplicado idéntico `public/isotipo-cam-512.png` (SHA-256 coincidente con `public/icono-512.png`), preservando el estándar PWA en `manifest.json`.
  - Reubicación y archivo del master ráster institucional sin comprimir `public/Logo del CAM.png` (7.0 MB) hacia `docs/historical/branding/Logo_del_CAM_original.png`, eliminando peso innecesario en la carpeta pública servible y estandarizando el uso de los logotipos vectoriales SVG oficiales (`logo-claro.svg`, `logo-oscuro.svg`, `logo-horizontal-*.svg`).
- **Estandarización de Herramientas de IA y Configuraciones Portables**:
  - Estandarización de `.agents/mcp_config.json` sustituyendo rutas absolutas del host (`C:\php\php.exe`) por el ejecutable portable `php`, y creación de la plantilla genérica `.agents/mcp_config.example.json`.
  - Corrección de `AGENTS.md` eliminando invocaciones rígidas a ejecutables de disco (`C:\php\php.exe artisan`).
  - Clasificación formal de las 29 habilidades en `docs/development/ai_skills_and_agents.md`.
- **Plantillas de Configuración de Entorno (`.env.example`)**: Estandarización de `APP_NAME="FamilyLock"`, locales en español (`es`), y adición de variables para credenciales iniciales de administrador institucional sin exponer secretos.
- **Verificación Dinámica y Estática 100% PASS**: Validación ejecutada estrictamente dentro de Windows Sandbox: 223 pruebas de PHPUnit aprobadas (1,240 aserciones, 0 fallos), 0 errores en análisis estático Larastan / PHPStan, y compilación limpia de Vite en 2.23s.

### Seguridad y Consolidación Crítica
- **Servicio de Dominio de Identidad Familiar (`FamiliaService`)**: Desacoplamiento matemático y determinista entre la persona física del tutor y la unidad familiar escolar (`FAM:MAT-001,MAT-002`). La clave canónica familiar se deriva de los grafos conexos de alumnos hermanos en `alumno_tutor`, garantizando que homónimos, tutores con múltiples hijos y familias que comparten líneas telefónicas mantengan un aislamiento absoluto de identidades sin duplicación de métricas.
- **Preservación Integral de Datos en Alumnos**: Corrección del contrato entre backend y frontend en `DashboardController`, `Dashboard.vue` y `AlumnoController`. Se garantiza la transmisión íntegra de CURP, fecha de nacimiento, edad, comentarios y fotografía, evitando la anulación accidental de registros históricos ante ediciones parciales o campos omitidos.
- **Mecanismo de Borrado Explícito Protegido**: Soporte para actualización y borrado explícito de campos sensibles mediante banderas autorizadas (`borrar_curp`, `borrar_comentarios`, etc.), distinguiendo valores vacíos artificiales de intenciones de borrado genuinas.
- **Protección de Instalación y Claves de Cifrado**: Blindaje del script `composer setup` para prevenir la sobreescritura accidental de `APP_KEY` con `key:generate` en despliegues existentes (lo que provocaría la invalidación de firmas HMAC de credenciales QR), y sustitución de `--force` por migraciones gráciles.
- **Idempotencia Durable en Notificaciones Multicanal (`notificaciones_procesadas`)**: Implementación de una tabla dedicada con restricción `UNIQUE(idempotency_key)` y clave compuesta determinista en base de datos (`2026_11_07_000001_create_notificaciones_procesadas_table.php`). Se sustituyó la caché volátil en `NotificarAvisoPersonalJob`, `NotificarAsistenciaJob` y `NotificarEventoTutorJob` por una reserva atómica transaccional (`insertOrIgnore`), seguimiento de estados del ciclo de vida (`PENDIENTE`, `EN_PROCESO`, `ENTREGADO`, `FALLIDO`), reintentos ante fallos transitorios, trazabilidad de fallos terminales vía método `failed()`, y recuperación de bloqueos zombi (`EN_PROCESO` abandonados $\ge 5$ minutos) ante caídas o reinicios del worker.
- **Identificación Estricta y Desacoplamiento de Tutores (`alumno_tutor`)**: Creación de la tabla relacional `alumno_tutor` (`2026_11_08_000000_create_alumno_tutor_table.php`) con restricción `UNIQUE(tutor_id, alumno_matricula)` y claves foráneas en cascada. Permite a un tutor vincularse explícitamente a múltiples alumnos sin depender de heurísticas basadas en nombres, teléfonos o correos electrónicos, garantizando aislamiento de identidades individuales para homónimos y familias que comparten líneas telefónicas.
- **Gestión Completa de Convocatorias a Eventos (`evento_convocatorias`)**: Migración `2026_11_08_000001_create_evento_convocatorias_table.php` con restricción `UNIQUE(evento_id, tutor_id)`. Vincula formalmente a los tutores convocados para cada junta escolar, registrando la confirmación de asistencia en el escaneo de QR y evitando que se considere automáticamente a toda la matrícula activa de la escuela como convocada. Endpoints dedicados en `EventoController`: `convocatorias`, `convocar`, `desconvocar`, y `asistenciaManual`.

### Control de Asistencia y Participación de Padres/Tutores
- **Administración de Convocatorias en `EventoController`**:
  - `GET /eventos/{id}/convocatorias`: Consulta tipada del listado de tutores convocados con estado de notificación y asistencia.
  - `POST /eventos/{id}/convocar`: Convocatoria selectiva individual o masiva por grupo/grado de tutores con canal de notificación.
  - `DELETE /eventos/{id}/convocar/{tutorId}`: Desconvocatoria segura sin alterar registros de asistencia previos.
  - `POST /eventos/{id}/asistencia-manual/{tutorId}`: Registro manual de asistencia presencial respaldada en auditoría.
- **Gestión de Vinculación de Hermanos en `TutorController`**:
  - `POST /tutores/{id}/vincular-alumno`: Vinculación explícita de un alumno adicional a un tutor existente en `alumno_tutor`.
  - `DELETE /tutores/{id}/desvincular-alumno/{matricula}`: Desvinculación de alumnos manteniendo la consistencia de tutor principal.
  - Sincronización automática de alumnos adicionales durante el registro inicial (`store`).
- **Registro Asistencial Enriquecido (`AsistenciaController`)**: Soporte para tutores no convocados previamente (asistencia *walk-in*), vinculación atómica en `evento_convocatorias` con bandera `asistio = true`, y respuesta al operador con desglose de alumnos representados (`alumnos_vinculados`).
- **Distinción Estricta de Familias y Denominador Real (`EstadisticasController`)**: Integración directa con `FamiliaService` para resolver identidades canónicas. Si un evento cuenta con tutores convocados registrados, el denominador de porcentaje de participación es estrictamente el total de tutores convocados para dicho evento, no el censo global de alumnos. Para eventos históricos generales, aplica la agrupación normalizada por clave compuesta sin inflar métricas.
- **Métricas Separadas de Convocatoria y Asistencia**: Distinción matemática explícita entre:
  - **A. Familias convocadas**: Conteo de familias o tutores formalmente convocados para el evento.
  - **B. Familias asistentes**: Familias únicas con asistencia confirmada.
  - **C. Tutores asistentes**: Tutores únicos que asistieron presencialmente.
  - **D. Ranking individual de tutores**: Puntuación acumulativa basada estrictamente en asistencias reales validadas (+1 por cada evento distinto), deduplicando lecturas de QR para la misma sesión.
- **Relaciones Eloquent Bidireccionales**:
  - `Tutor::alumnos()`: Relación `belongsToMany(Alumno::class, 'alumno_tutor')`.
  - `Alumno::tutores()`: Relación `belongsToMany(Tutor::class, 'alumno_tutor')`.
  - `Evento::convocatorias()`: Relación `hasMany(EventoConvocatoria::class)`.
  - `Evento::tutoresConvocados()`: Relación `belongsToMany(Tutor::class, 'evento_convocatorias')`.
- **Normalización Multibyte UTF-8 en Nombres**: Adopción de `mb_strtoupper(..., 'UTF-8')` en los accessors `getNombreCompletoAttribute` de `Alumno` y `Tutor`, preservando acentos y caracteres de la lengua española en visualización y reportes.
- **Trazabilidad de Tutor y Alumno Relacionado en Historial**: Integración del alumno vinculado (`alumno_relacionado`) en las consultas de `HistorialController` y en la exportación de reportes CSV, permitiendo identificar de forma inmediata qué estudiante representa cada tutor en asistencias a eventos y juntas.
- **Migración Robusta de Eventos (`add_evento_id`)**: Migración `2026_11_07_000000_add_evento_id_to_registros_asistencia_table.php` con clave foránea `fk_registros_asistencia_evento_id` (`nullOnDelete`), índice `idx_registros_asistencia_evento_id`, idempotencia ante reejecución y rollback compatible con MariaDB/MySQL y SQLite.
- **Persistencia y Validación de Correo de Tutores**: Incorporación de `'t1_email'` en el modelo `Tutor` y preservación del correo electrónico ante ediciones sin modificar en `TutorController`.
- **Tipado Estricto de Clave Primaria en Personal (`Personal.php`)**: Declaración explícita de `public $incrementing = false;` y `protected $keyType = 'string';` para alinear el modelo con su definición de identificador de nómina en la base de datos institucional.

### Validación Exhaustiva en Entorno Aislado y Windows Sandbox
- **Validación en Motor MariaDB Real Aislado (Puerto 3307)**:
  - Servicio dedicado `MariaDBSynthetic` (MariaDB 12.3.2) ejecutado dentro del Sandbox.
  - Verificación integral de 6 etapas: instalación limpia de todas las tablas v1.4.8, actualización sobre esquema v1.4.7 con preservación de registros históricos, aplicación de restricciones `UNIQUE` y claves foráneas (`CASCADE` y `SET NULL`), idempotencia ante reejecuciones, simulación concurrente atómica de workers (`insertOrIgnore`) y reversibilidad de rollback `down()` (23 aserciones, 100% PASS).
- **Cobertura de Participación de Tutores (`TutoresAsistenciaParticipacionTest`)**:
  - Verificación de 16 escenarios: tutor con múltiples hijos vía `alumno_tutor`, tutores homónimos con diferente contacto, familias que comparten teléfono, correos distintos, asistencias a múltiples juntas escolares, rechazo de asistencias duplicadas por evento, ranking individual por asistencias reales, distinción entre familias convocadas/asistentes y tutores asistentes, cálculo exacto del denominador de convocatorias, sincronización en `TutorController@store`, vinculación/desvinculación de hermanos y registro de asistencia manual (16 pruebas, 173 aserciones, 100% PASS).
- **Cobertura de Mensajería Asíncrona e Idempotencia (`NotificacionesPersonalAsincronasTest` y `NotificacionesResilienciaTest`)**:
  - Pruebas con colas simuladas, validación de autorización RBAC, persistencia durable en tabla `notificaciones_procesadas`, deduplicación de solicitudes concurrentes idénticas, reintentos ante excepciones, recuperación tras reinicio de workers, liberación de registros zombi en proceso por más de 5 minutos, confirmación de no-repetición de mensajes en canales previos ante reintentos de workers y registro terminal `FALLIDO` con trazabilidad (15 pruebas, 46 aserciones, 100% PASS).
- **Auditoría y Suite Completa de Exportación (`HistorialExportacionTest`)**: Reconstrucción y expansión en 30 pruebas paramétricas utilizando 5 DataProviders para verificar cabeceras MIME, orden cronológico descendente, sanitización CSV (comas, comillas dobles, BOM UTF-8), chunking, filtros por rol y matriz RBAC de autorización (30 pruebas, 233 aserciones, 100% PASS).
- **Validación de Migración `add_evento_id` (`EventoMigrationTest`)**: Verificación aislada de instalación limpia, actualización sobre base existente v1.4.7, preservación íntegra de datos históricos, idempotencia y reversibilidad rollback con clave foránea e índices (1 prueba, 8 aserciones, 100% PASS).
- **Cobertura de Preservación de Alumnos (`AlumnosPreservacionDatosTest`)**: Pruebas que validan la entrega completa de atributos en el Dashboard, la inmutabilidad ante payloads vacíos artificiales y el soporte de borrado explícito (5 pruebas, 49 aserciones, 100% PASS).
- **Validación de Seguridad de Scripts Composer (`ComposerScriptSecurityTest`)**: Comprobación automatizada de no-regeneración de `APP_KEY` institucional y ausencia de comandos destructivos en actualización (2 pruebas, 7 aserciones, 100% PASS).
- **Cobertura de Regresiones Anteriores**:
  - `EscaneoSeguridadTest`: 3 pruebas, 6 aserciones (100% PASS).
  - `TelegramSalidasSegurasTest`: 16 pruebas, 199 aserciones (100% PASS).
  - `TutorCorreoPersistenciaTest`: 10 pruebas, 49 aserciones (100% PASS).
  - `IsolatedHttpTransportTest`: 3 pruebas, 6 aserciones (100% PASS).
- **Resumen Global de Ejecución Dinámica del Proyecto**: 223 pruebas ejecutadas, 1240 aserciones, 0 fallos, 0 errores (100% PASS en arnés aislado de Windows Sandbox).

---

## [1.4.7] - 2026-10-19

### Seguridad
- **Políticas de Autorización RBAC en Alumnos (`AlumnoPolicy`)**: Implementación de autorización granular para directores y docentes, garantizando aislamiento de acceso según grado/grupo asignado.
- **Hardening de Privacidad y Salidas Seguras en Telegram**: Sanitización estricta de mensajes salientes, enmascaramiento reforzado de PII y prevención de fugas de metadatos sensibles en `SyncTelegramUpdates` y `TelegramService`.
- **Protección de Rutas de Perfil**: Robustecimiento en `ProfileController` y validación tipada estricta en `ProfileUpdateRequest`.

### Añadido
- **Suite de Pruebas Aisladas en Memoria (`tests/Isolation/`)**: Entorno de ejecución sintético y ultra-rápido para pruebas de seguridad unitarias y validación sin dependencias externas.
- **Pruebas de Autorización Consistente**: Cobertura integral en `AlumnosAutorizacionTest`, `ExpedientesAutorizacionConsistenteTest`, `TelegramPrivacidadHardeningTest` y `TelegramSalidasSegurasTest`.
- **Plantillas de Windows Sandbox para FamilyLock**: Manifiesto y archivo de propuesta `.wsb` en `docs/` para pruebas en entornos aislados.

---

## [1.4.6] - 2026-10-16 (Versión de Desarrollo)

### Seguridad
- **Expedientes Confidenciales Privados**: Confinamiento del almacenamiento de nuevos documentos al disco `local` (`storage/app/private/expedientes/`), eliminando la exposición web directa de nuevos archivos.
- **Autorización Granular en Expedientes**: Implementación de `ArchivoSistemaPolicy` con filtrado a nivel de base de datos previo a la paginación para docentes según asignación escolar de grado y grupo.
- **Protocolo de Vinculación Telegram VINC_ (Consumidor CLI)**: Comando `SyncTelegramUpdates` reforzado con tokens SHA-256 temporales (30 minutos), bloqueo de comandos inseguros `tutor_{id}` y protección *fail-safe* ante ausencia de tabla. *(Nota: La migración de la tabla institucional y la interfaz web de emisión de invitaciones quedan pendientes para el despliegue institucional).*
- **Protección de Datos Personales (PII)**: Enmascaramiento obligatorio de Chat IDs y teléfonos en registros de logs operativos (OWASP A03).
- **Restricción de Autoregistro**: Ocultamiento del rol administrativo en el frontend y bloqueo de registro público para invitados mediante middleware `can:gestionar-usuarios`.

### Añadido
- **Pruebas de Tolerancia a Fallos en Mensajería (`NotificacionesResilienciaTest`)**: Cobertura sintética contra caídas HTTP 500 de WhatsApp, rate limits HTTP 429 de Telegram, bloqueos de bot HTTP 403, desconexión y timeouts. Demostración técnica del riesgo de duplicación en reintentos sin almacenamiento duradero.
- **Pruebas de Autenticación Segura Institucional (`AutenticacionSeguraTest`)**: Verificación aislada del ciclo de vida de sesiones, actualización de contraseñas y políticas de acceso escolar adaptadas al esquema del plantel.
- **Pruebas de Hardening de Fase 3 (`Fase3Etapa3HardeningTest`)**: 15 pruebas automatizadas que validan políticas de expedientes, permisos RBAC de docentes y directivos, y consumo atómico de tokens.

### Corregido
- **Mensaje Neutral de Estado en Eventos**: Corrección del flash message en `EventoController` para ser independiente del driver de colas configurado (`sync` o `database`), evitando declarar falsos procesamientos en segundo plano.
- **Normalización de Contratos de Servicios de Mensajería**: Corrección de contratos en `WhatsAppService` y `TelegramService` para devolver tipos booleanos estrictos sin lanzar excepciones no controladas.

---

## [1.4.1] - 2026-10-11

### Modificado
- **Rediseño Visual Integral de la UI**: Actualización completa de todas las vistas del sistema (Dashboard, Escáner, Historial, Expedientes, Personal, Tutores, Agenda, Estadísticas, Login, Credenciales) con mejoras de consistencia visual, rendimiento de renderizado y experiencia de usuario.
- **AuthenticatedLayout & GuestLayout**: Optimización del layout principal y del layout de invitado para mejor responsividad y jerarquía visual.
- **Componentes Reutilizables**: Actualización de `Modal`, `Pagination`, `DangerButton`, `SecondaryButton`, `TextInput` y `ApplicationLogo` para alinear estilos con el sistema de diseño global del proyecto.
- **Sistema CSS (app.css)**: Refactorización y optimización de clases, reducción de duplicados y limpieza de estilos obsoletos.
- **Configuración de Tailwind**: Ajuste de `tailwind.config.js` para incluir tokens y variantes actualizadas.
- **Controladores Backend (EstadisticasController, HistorialController)**: Ajustes en la lógica de presentación y ordenamiento de datos para los módulos de estadísticas e historial.
- **Migración de Índices**: Ajuste en `optimizar_indices_asistencia` para compatibilidad con el nuevo esquema polimórfico.

---

## [1.4.0] - 2026-10-11

### Añadido
- **Migración a Relaciones Polimórficas**: Refactorización de la tabla `registros_asistencia` para utilizar el patrón polimórfico nativo de Laravel (`asistible_type`, `asistible_id`), unificando los modelos `Alumno`, `Tutor` y `Personal`.
- **Firma Criptográfica HMAC en Códigos QR**: Validación de autenticidad anti-clonación y anti-tampering (`hash_hmac` con `APP_KEY`) en el escaneo de asistencias.
- **Limitadores de Tasa (Rate Limiting)**: Protección contra ataques de denegación de servicio (DoS) en `/escaner/procesar` (`throttle:60,1`) y `/api/telegram/sync` (`throttle:30,1`).
- **Comando Artisan y Endpoint API de Sincronización Telegram**: Creación de `SyncTelegramUpdates` (`php artisan telegram:sync`) y ruta dedicada en `routes/api.php` desacoplada del ciclo web.
- **Documentación Integral**: Rediseño completo de `README.md` con guías de instalación, arquitectura, colas y despliegue en producción.

### Modificado
- **Desacoplamiento de Servicios en Colas (`NotificarAsistenciaJob`)**: Refactorización del Job para recibir `WhatsAppService` y `TelegramService` mediante inyección de dependencias en `handle()`, previniendo errores de serialización en base de datos.
- **Estandarización RESTful de Rutas**: Agrupación y adopción de `Route::resource` para los módulos `Personal`, `Tutores`, `Expedientes`, `Historial` y `Eventos` en `routes/web.php`.
- **Protección de Datos y Reglas en .gitignore**: Exclusión automática de volcados SQL (`*.sql`), ejecutables `.phar`, respaldos (`*.bak`) y archivos de depuración.

### Corregido
- **Corrección Crítica de Recursión Infinita en `Personal.php`**: Corrección del accessor `getNombreCompletoAttribute()` para leer del array interno de atributos `$this->attributes['nombre_completo']`, evitando desbordamientos de pila (*Stack Overflow*).
- **Consistencia de Rutas**: Alineación del parámetro `{matricula}` en la ruta de actualización de expedientes de alumnos.
- **Higiene de Repositorio**: Eliminación del seguimiento en Git de volcados SQL que contenían datos personales (`respaldo.sql`), binario `composer.phar`, logs y scripts temporales.

---

## [1.3.0] - 2026-05-25

### Añadido
- **Arquitectura de Tareas en Segundo Plano**: Se integró el sistema de `Jobs` (colas de trabajo) para manejar procesos pesados de forma asíncrona (como envío de notificaciones), mejorando radicalmente la velocidad de respuesta del sistema.
- **Servicio de Imágenes (`ImageUploadService`)**: Nuevo servicio centralizado para la optimización, redimensionamiento y almacenamiento de fotografías.
- **Gestión de Alumnos**: Nuevo controlador dedicado `AlumnoController`.
- **Archivos de Configuración Independientes**: Creación de `config/telegram.php` y `config/whatsapp.php` para centralizar y asegurar las credenciales de mensajería.
- **Optimización de Base de Datos**: Nuevas migraciones para indexar las tablas de asistencia (`optimizar_indices_asistencia`) y añadir timestamps, acelerando drásticamente las consultas de reportes.
- **Nuevas Pruebas Unitarias (Tests)**: Añadidas pruebas específicas para el núcleo del sistema (`EscaneoSeguridadTest`, `HistorialExportacionTest`, `NotificacionEventosTest`).

### Modificado
- **Refactorización Profunda**: Limpieza masiva en `DashboardController` (reducción de lógica pesada) delegando responsabilidades a servicios y colas.
- **Controladores Optimizados**: Refactorización de `AsistenciaController`, `HistorialController` y `WhatsAppService` para mayor rendimiento y código limpio.
- **UI de Login y Dashboard**: Mejoras visuales en los componentes de autenticación y panel de control.

### Eliminado
- **Limpieza de Tests Obsoletos**: Eliminación de más de 500 líneas de pruebas predeterminadas de Laravel Auth y Profile que no se usaban en la arquitectura de CAM48.

---

## [1.2.1] - 2026-05-24

### Modificado
- **Mejoras de UI y Paginación**: Integración completa del componente `Pagination.vue` en las vistas principales (`Dashboard`, `Expedientes`, `Personal`, `Tutores`) para mejorar la navegación de registros masivos.
- **Optimización de Código (Formateo)**: Estandarización y formateo general del código fuente en las vistas principales Vue para mejorar la legibilidad y mantenimiento.

---

## [1.2.0] - 2026-05-24

### Añadido
- **Soporte PWA (Aplicación Web Progresiva)**: Se agregó el archivo `manifest.json` y configuraciones correspondientes para soporte PWA.
- **Roles de Usuario**: Nueva migración para añadir el campo de rol (`role`) a la tabla de usuarios, permitiendo un mejor control de acceso y autorizaciones en controladores y vistas.
- **Borrado Lógico (Soft Deletes)**: Implementación de Soft Deletes en las tablas de `tutores` y `eventos` para evitar pérdida accidental de registros críticos y mantener la integridad del historial de asistencias.
- **Procesamiento de Imágenes Mejorado**: Se incluyó la librería `intervention/image` en `composer.json` para optimizar y estandarizar la carga de fotografías (como fotos de perfil).
- **Componentes UI (Paginación)**: Nuevo componente Vue para paginación (`Pagination.vue`) para optimizar el listado en vistas masivas.

### Modificado
- Refactorización y optimización en los controladores principales (`DashboardController`, `EventoController`, `ExpedienteController`, `HistorialController`, `PersonalController`, `TutorController`) para soportar los nuevos roles, borrado lógico y paginación.
- Mejoras menores de diseño en componentes base (`DangerButton`, `Modal`, `PrimaryButton`, etc.) y vistas principales (`Agenda`, `Dashboard`, `Expedientes`, `Personal`, `Profile`, `Tutores`).

---

## [1.1.0] - 2026-05-24

### Añadido
- **Módulo de Estadísticas**:
  - Implementación del **Top Padres Comprometidos** (ranking en vivo de tutores con mayor asistencia registrada, mostrando top 20, corona e insignias).
  - Exportación nativa del reporte de ranking de tutores a **Excel** (utilizando `exceljs` y `file-saver`, con formateo automático de anchos de columna y celdas de podio destacadas en oro, plata y bronce).
  - Exportación nativa a **PDF** (utilizando `html2pdf.js`).
- **Mejoras Visuales Premium**:
  - Efecto de **barrido de luz (shimmer effect)** de alta gama al pasar el mouse por las tarjetas del sistema (`premium-card` en modo oscuro).
  - Efecto de resplandor e iluminación interactiva en bordes en modo oscuro.
  - Efecto de sombreado y borde azul interactivo con resplandor interior al pasar el cursor sobre las filas de tablas premium (`table-premium`).
  - Fondo enriquecido con **Orbes de luz animados (Background Orbs)** flotando sutilmente en el fondo de la interfaz en modo oscuro (`AuthenticatedLayout.vue`).
- **Nuevas Dependencias**: `exceljs`, `file-saver`, `html2pdf.js`.

---

## [1.0.0] - 2026-05-24

### Añadido
- **Control de Versiones**: Inicialización del repositorio Git oficial del proyecto.
- **Sincronización en la Nube**: Configuración y vinculación directa con el repositorio privado en GitHub.
- **Integraciones Previas**:
  - Panel de control principal (Dashboard) optimizado.
  - Gestión de Personal con integración y vinculación a Telegram.
  - Gestión de Tutores, Credenciales Digitales y Asistencia escolar por código QR.
  - Envío automático de notificaciones vía correo y Telegram.
  - Soporte para subida de fotos de perfil integradas en la base de datos y vistas.

---

> [!NOTE]
> ### Formato de Versionamiento a Seguir:
> - **MAYOR (X.0.0)**: Cambios significativos o actualizaciones incompatibles hacia atrás.
> - **MENOR (0.X.0)**: Nuevas funcionalidades añadidas sin romper compatibilidad.
> - **PARCHE (0.0.X)**: Corrección de errores y pequeños ajustes visuales o de rendimiento.
