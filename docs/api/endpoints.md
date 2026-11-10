# FamilyLock — Catálogo de Rutas y Endpoints (v1.4.8)

## 1. Módulo de Autenticación y Perfil

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/` | — | `web` | Redirección a `/login`. |
| `GET` | `/login` | `login` | `guest` | Formulario de autenticación institucional. |
| `POST` | `/login` | — | `guest`, `throttle` | Validación de credenciales de usuario. |
| `POST` | `/logout` | `logout` | `auth` | Cierre de sesión y revocación de tokens. |
| `GET` | `/profile` | `profile.edit` | `auth` | Formulario de edición de perfil. |
| `PATCH` | `/profile` | `profile.update` | `auth` | Actualización de perfil institucional. |
| `DELETE` | `/profile` | `profile.destroy` | `auth` | Eliminación de cuenta de usuario. |

---

## 2. Módulo de Alumnos y Dashboard

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/dashboard` | `dashboard` | `auth`, `can:personal-access` | Tablero principal con métricas del día y tabla paginada. |
| `POST` | `/alumnos` | `alumnos.store` | `auth`, `can:alumnos.crear` | Registro de nuevo alumno con foto y tutor asignado. |
| `PUT` | `/alumnos/{matricula}` | `alumnos.update` | `auth`, `can:alumnos.actualizar` | Edición de alumno con preservación de datos no alterados. |

---

## 3. Módulo de Escáner y Asistencia QR

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/escaner` | `asistencia.escaner` | `auth`, `can:asistencia.escanear` | Interfaz de escáner de cámara o lector óptico. |
| `POST` | `/escaner/procesar` | `asistencia.procesarQr` | `auth`, `can:asistencia.escanear`, `throttle:60,1` | Procesa código QR con validación de cooldown y evento. |
| `POST` | `/escaner/desbloquear` | `asistencia.desbloquear` | `auth`, `can:asistencia.escanear` | Liberación manual de pantalla en kiosco. |

---

## 4. Módulo de Tutores y Gestión de Hermanos

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/tutores` | `tutores.index` | `auth`, `can:tutores.consultar` | Directorio de tutores con alumnos vinculados y familias. |
| `POST` | `/tutores` | `tutores.store` | `auth`, `can:tutores.crear` | Registro de tutor y sincronización en `alumno_tutor`. |
| `DELETE` | `/tutores/{id}` | `tutores.destroy` | `auth`, `can:tutores.eliminar` | Baja de tutor en el sistema escolar. |
| `POST` | `/tutores/{id}/mensaje` | `tutores.mensaje` | `auth`, `can:notificaciones.tutores` | Envío de mensaje directo al tutor vía WhatsApp/Telegram. |
| `POST` | `/tutores/{id}/invitacion-telegram` | `tutores.invitacion` | `auth`, `can:notificaciones.tutores` | Envío de invitación con protocolo tokenizado seguro. |
| `POST` | `/tutores/{id}/vincular-alumno` | `tutores.vincularAlumno` | `auth`, `can:tutores.actualizar` | Vinculación explícita de un alumno adicional al tutor. |
| `DELETE` | `/tutores/{id}/desvincular-alumno/{matricula}` | `tutores.desvincularAlumno` | `auth`, `can:tutores.actualizar` | Desvinculación de un alumno sin eliminar registros asistenciales. |

---

## 5. Módulo de Juntas, Eventos y Convocatorias

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/eventos` | `eventos.index` | `auth`, `can:eventos.consultar` | Catálogo de juntas y eventos escolares agendados. |
| `POST` | `/eventos` | `eventos.store` | `auth`, `can:eventos.crear` | Alta de nueva junta escolar con fecha y sede. |
| `DELETE` | `/eventos/{id}` | `eventos.destroy` | `auth`, `can:eventos.eliminar` | Cancelación/eliminación de evento agendado. |
| `GET` | `/eventos/{id}/convocatorias` | `eventos.convocatorias` | `auth`, `can:eventos.consultar` | Listado de tutores convocados, notificación y asistencia. |
| `POST` | `/eventos/{id}/convocar` | `eventos.convocar` | `auth`, `can:eventos.crear` | Convocatoria masiva o individual de tutores al evento. |
| `DELETE` | `/eventos/{id}/convocatorias/{tutorId}` | `eventos.desconvocar` | `auth`, `can:eventos.eliminar` | Desconvocatoria de un tutor para el evento. |
| `POST` | `/eventos/{id}/asistencia-manual/{tutorId}` | `eventos.asistenciaManual` | `auth`, `can:eventos.crear` | Registro de asistencia presencial manual con trazabilidad. |

---

## 6. Módulo de Historial, Estadísticas y Credenciales

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/historial` | `historial.index` | `auth`, `can:historial.consultar` | Historial paginado con filtros de fecha, tipo y persona. |
| `GET` | `/historial/exportar` | `historial.exportar` | `auth`, `can:historial.exportar` | Exportación CSV en streaming con BOM UTF-8 y sanitización. |
| `DELETE` | `/historial/{id}` | `historial.destroy` | `auth`, `can:historial.eliminar` | Eliminación de registro asistencial por directivo. |
| `GET` | `/estadisticas` | `estadisticas.index` | `auth`, `can:estadisticas.consultar` | Métricas de asistencia, participación en juntas y rankings. |
| `GET` | `/credenciales` | `credenciales.index` | `auth`, `can:credenciales.generar` | Generación e impresión de credenciales QR. |

---

## 7. Módulo de Personal y Sincronización

| Método | Ruta | Nombre | Middleware | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/personal` | `personal.index` | `auth`, `can:personal.consultar` | Directorio de personal docente y administrativo. |
| `POST` | `/personal` | `personal.store` | `auth`, `can:personal.crear` | Registro de personal en la nómina escolar. |
| `PUT` | `/personal/{id}` | `personal.update` | `auth`, `can:personal.actualizar` | Edición de personal escolar. |
| `DELETE` | `/personal/{id}` | `personal.destroy` | `auth`, `can:personal.eliminar` | Baja de personal en el sistema. |
| `POST` | `/personal/mensaje-masivo` | `personal.mensajeMasivo` | `auth`, `can:notificaciones.personal` | Notificación asíncrona a todos los docentes vinculados. |
| `GET` | `/telegram/sync` | `telegram.sync` | `auth`, `can:telegram.sincronizar`, `throttle:20,1` | Sincronización atómica con Telegram Bot API. |
