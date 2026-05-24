# Historial de Cambios (Changelog) - Proyecto CAM 48

Todos los cambios notables en este proyecto serán documentados en este archivo. El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a [SemVer (Versionamiento Semántico)](https://semver.org/lang/es/).

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
