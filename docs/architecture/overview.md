# FamilyLock — Arquitectura General del Sistema (v1.4.8)

## 1. Propósito y Filosofía del Sistema

**FamilyLock** es un sistema especializado de gestión de asistencia escolar y vinculación con familias, diseñado originalmente para el Centro de Atención Múltiple N.º 48 (CAM 48) de Nanchital, Veracruz. Su objetivo fundamental es resolver con máxima confiabilidad tres necesidades operativas:

1. **Control de Asistencia de Alumnos**: Registro biométrico/QR en punto de acceso escolar, captura inmediata de entradas/salidas, historial auditable y comunicación instantánea hacia los tutores legales.
2. **Control de Asistencia y Participación de Familias**: Registro de asistencia a asambleas, juntas de grupo y actividades escolares, vinculando al tutor presente con sus hijos matriculados y calculando tasas de participación con denominadores reales.
3. **Comunicación Multicanal Asíncrona**: Envío confiable y deduplicado de notificaciones institucionales vía WhatsApp y Telegram, con protección de idempotencia duradera y desacoplamiento mediante colas.

> [!NOTE]
> FamilyLock está diseñado bajo una arquitectura modular y ligera. No pretende ser un ERP escolar monolítico; todos los módulos periféricos (expedientes, credenciales, directorio) complementan el núcleo asistencial y comunicativo.

---

## 2. Pila Tecnológica (Tech Stack)

```
┌─────────────────────────────────────────────────────────────┐
│                 CAPA DE CLIENTE (SPA)                       │
│     Vue 3 (Composition API) + Inertia.js v2 + Vite          │
│          Tailwind CSS v3 (PostCSS) + SweetAlert2            │
└──────────────────────────────┬──────────────────────────────┘
                               │ JSON / Inertia Protocol
┌──────────────────────────────▼──────────────────────────────┐
│                CAPA DE APLICACIÓN (HTTP)                    │
│      Laravel 13.x (PHP 8.3 / 8.5) + Middlewares RBAC        │
│          Controllers Tipados + Form Requests                │
└──────────────────────────────┬──────────────────────────────┘
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                     ▼
┌──────────────────────────────┐   ┌──────────────────────────────┐
│      CAPA DE DOMINIO         │   │      PROCESOS ASÍNCRONOS     │
│   FamiliaService, ImageUpload│   │  Jobs (ShouldQueue, Backoff) │
│   Policies (AlumnoPolicy)    │   │  Idempotencia (notif_proc)   │
└──────────────┬───────────────┘   └──────────────┬───────────────┘
               │                                  │
               └──────────────────┬───────────────┘
                                  ▼
┌─────────────────────────────────────────────────────────────┐
│                   CAPA DE PERSISTENCIA                      │
│       MariaDB 12.x / MySQL 8.x (Producción / Aislado)       │
│        SQLite en Memoria (Testing Aislado Ultrarrápido)     │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Módulos del Sistema y Responsabilidades

### A. Módulo de Asistencia Escolar (`AsistenciaController`)
- **Punto de Kiosco / Escáner QR**: Procesa cargas útiles firmadas con HMAC o identificadores matriculares.
- **Enfriamiento y Protección de Duplicados**: Prevención de lecturas repetidas dentro de una ventana de 5 minutos, diferenciando claramente eventos de entrada, salida o juntas escolares.
- **Enriquecimiento de Respuesta**: Devuelve al operador información del alumno, fotografía, estatus del tutor y alumnos vinculados.

### B. Módulo de Dominio Familiar (`FamiliaService`, `TutorController`)
- **Desacoplamiento Tutor vs. Familia**: Un tutor es una persona física individual; una familia escolar es el grupo conexo de alumnos hermanos.
- **Tabla Pivote `alumno_tutor`**: Soporta relaciones $N:M$ con bandera `es_tutor_principal` y parentesco, permitiendo que varios tutores atiendan a los mismos alumnos o que un tutor atienda a múltiples hermanos.
- **Clave Canónica Familiar**: Resuelta deterministamente mediante `FAM:MAT-001,MAT-002`, garantizando aislamiento absoluto para homónimos y familias que comparten números telefónicos.

### C. Módulo de Convocatorias y Juntas (`EventoController`, `EstadisticasController`)
- **Convocatorias Selectivas (`evento_convocatorias`)**: Formaliza qué tutores han sido citados para cada junta escolar.
- **Métricas Matemáticamente Exactas**: El denominador de participación es estrictamente el número de familias convocadas al evento, no la matrícula escolar completa.
- **Registro de Asistencia Manual y Walk-In**: Admite la llegada de padres no preconvocados, registrando su asistencia y actualizando las estadísticas en tiempo real.

### D. Módulo de Mensajería e Idempotencia (`Notificar*Job`)
- **Procesamiento en Segundo Plano**: Desacoplado mediante contratos `ShouldQueue` para no bloquear los tiempos de respuesta del escáner ni de los formularios.
- **Persistencia Durable (`notificaciones_procesadas`)**: Registro atómico con `insertOrIgnore`, exclusión mutua de workers concurrentes, ciclo de vida (`PENDIENTE` $\to$ `EN_PROCESO` $\to$ `ENTREGADO` / `FALLIDO`) y recuperación de bloqueos zombi tras caídas del servidor.
- **Resiliencia Multicanal**: Fallos en un canal secundario (e.g. Telegram 500) no impiden la entrega en el canal primario (WhatsApp 200) ni provocan reenvíos repetidos ante reintentos.

### E. Módulo de Expedientes Digitales (`ExpedienteController`, `AlumnoPolicy`)
- **Control de Acceso Basado en Roles (RBAC)**: Autorización granular mediante políticas (`AlumnoPolicy`), restringiendo el acceso de docentes únicamente a los expedientes de su grado y grupo asignado.
- **Preservación Integral de Datos**: Validación de contratos frontend-backend para evitar la anulación accidental de campos sensibles (CURP, observaciones médicas, fecha de nacimiento).
