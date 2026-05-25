# Historial de Cambios (Changelog) - Proyecto CAM 48

Todos los cambios notables en este proyecto serán documentados en este archivo. El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a [SemVer (Versionamiento Semántico)](https://semver.org/lang/es/).

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
