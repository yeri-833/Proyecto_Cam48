# FamilyLock — Esquema de Base de Datos y Migraciones (v1.4.8)

## 1. Modelo Entidad-Relación (Relational Schema)

La base de datos utiliza el motor InnoDB con codificación `utf8mb4` y colación `utf8mb4_unicode_ci`.

```
┌──────────────┐          ┌───────────────────┐          ┌──────────────┐
│   alumnos    │◄─────────┤   alumno_tutor    ├─────────►│   tutores    │
│ (matricula PK│          │(tutor_id, matric) │          │   (id PK)    │
└──────┬───────┘          └───────────────────┘          └──────┬───────┘
       │                                                        │
       │                                ┌───────────────────────┘
       │                                │
       │ 1:N                            │ 1:N
       ▼                                ▼
┌───────────────────────┐          ┌───────────────────────┐
│ registros_asistencia  │          │  evento_convocatorias │
│  (polimórfica)        │◄─────────┤  (evento_id, tutor_id)│
└───────────────────────┘          └───────────┬───────────┘
       ▲                                       │
       │ 1:N (nullOnDelete)                    │ N:1
       └────────────────────────┐              ▼
                         ┌──────┴───────┐
                         │   eventos    │
                         │   (id PK)    │
                         └──────────────┘
```

---

## 2. Catálogo de Tablas Principales

### `alumnos`
Almacena el registro escolar de los alumnos matriculados.
- `matricula` (`VARCHAR(50)`, Primary Key): Identificador escolar único.
- `nombre` (`VARCHAR(100)`): Nombre de pila (soporte acentos UTF-8).
- `apellido` (`VARCHAR(100)`): Apellidos.
- `curp` (`VARCHAR(18)`, Nullable): Clave Única de Registro de Población.
- `fecha_nacimiento` (`DATE`, Nullable).
- `edad` (`INT`, Nullable).
- `grado` (`VARCHAR(10)`, Nullable): Grado asignado (1–6, preescolar, laboral).
- `grupo` (`VARCHAR(10)`, Nullable): Grupo escolar (A, B, C, Único).
- `discapacidad` (`VARCHAR(150)`, Nullable): Diagnóstico educativo especial.
- `activo` (`TINYINT(1)`, Default `1`): Estado de matriculación vigente.

### `tutores`
Entidad que representa a la persona física del padre, madre o tutor legal.
- `id` (`BIGINT UNSIGNED`, Auto Increment, Primary Key).
- `matricula_alumno` (`VARCHAR(50)`, Nullable): Referencia histórica (v1.0–v1.4.7) preservada para retrocompatibilidad.
- `t1_nombre` (`VARCHAR(150)`): Nombre completo del tutor.
- `t1_telefono` (`VARCHAR(20)`, Nullable): Teléfono de contacto (WhatsApp).
- `t1_email` (`VARCHAR(100)`, Nullable): Correo electrónico institucional/personal.
- `t1_telegram_chat_id` (`VARCHAR(50)`, Nullable): Chat ID verificado de Telegram.
- `t1_direccion` (`VARCHAR(255)`, Nullable): Domicilio particular.

### `alumno_tutor` (Migración `2026_11_08_000000_create_alumno_tutor_table.php`)
Tabla relacional $N:M$ que formaliza la vinculación entre tutores y múltiples alumnos.
- `id` (`BIGINT UNSIGNED`, Auto Increment, Primary Key).
- `tutor_id` (`BIGINT UNSIGNED`): Foreign Key a `tutores.id` (`onDelete('cascade')`).
- `alumno_matricula` (`VARCHAR(50)`): Foreign Key a `alumnos.matricula` (`onDelete('cascade')`).
- `parentesco` (`VARCHAR(50)`, Default `'Tutor Legal'`).
- `es_tutor_principal` (`BOOLEAN`, Default `true`).
- **Restricción de Integridad**: `UNIQUE KEY uq_alumno_tutor (tutor_id, alumno_matricula)`.

### `eventos`
Registro de juntas generales, asambleas y actividades comunitarias.
- `id` (`BIGINT UNSIGNED`, Auto Increment, Primary Key).
- `nombre_evento` (`VARCHAR(150)`): Título de la reunión.
- `fecha_hora` (`DATETIME`): Fecha y hora programada.
- `ubicacion` (`VARCHAR(150)`, Nullable): Sede o aula de la reunión.

### `evento_convocatorias` (Migración `2026_11_08_000001_create_evento_convocatorias_table.php`)
Registro explícito de tutores citados a cada evento escolar.
- `id` (`BIGINT UNSIGNED`, Auto Increment, Primary Key).
- `evento_id` (`BIGINT UNSIGNED`): Foreign Key a `eventos.id` (`onDelete('cascade')`).
- `tutor_id` (`BIGINT UNSIGNED`): Foreign Key a `tutores.id` (`onDelete('cascade')`).
- `alumno_matricula` (`VARCHAR(50)`, Nullable).
- `canal_notificacion` (`VARCHAR(20)`, Default `'WHATSAPP'`).
- `notificado` (`BOOLEAN`, Default `false`).
- `asistio` (`BOOLEAN`, Default `false`): Marcado en tiempo real al escanear QR.
- `fecha_asistencia` (`DATETIME`, Nullable).
- **Restricción de Integridad**: `UNIQUE KEY uq_evento_tutor (evento_id, tutor_id)`.

### `registros_asistencia` (Actualizada con `add_evento_id`)
Registro histórico de entradas, salidas y participaciones a eventos.
- `id` (`BIGINT UNSIGNED`, Auto Increment, Primary Key).
- `asistible_type` (`VARCHAR(255)`): Clase polimórfica (`App\Models\Alumno`, `App\Models\Tutor`, `App\Models\Personal`).
- `asistible_id` (`VARCHAR(50)`): Identificador de la persona (matrícula o ID numérico).
- `fecha` (`DATE`): Fecha del registro (`Y-m-d`).
- `hora` (`TIME`): Hora de captura (`H:i:s`).
- `tipo` (`VARCHAR(20)`, Nullable): `'ENTRADA'`, `'SALIDA'`, `'EVENTO'`.
- `evento_id` (`BIGINT UNSIGNED`, Nullable): Clave foránea añadida en v1.4.8 con `nullOnDelete()`.

### `notificaciones_procesadas` (Migración `2026_11_07_000001_create_notificaciones_procesadas_table.php`)
Control de idempotencia duradera para mensajería asíncrona.
- `id` (`BIGINT UNSIGNED`, Auto Increment, Primary Key).
- `idempotency_key` (`VARCHAR(64)`, Unique): Hash SHA-256 de la transacción.
- `destinatario_tipo` (`VARCHAR(50)`).
- `destinatario_id` (`VARCHAR(50)`).
- `canal` (`VARCHAR(30)`): `'WHATSAPP'`, `'TELEGRAM'`, `'MAIL'`, `'MULTICANAL'`.
- `estado` (`ENUM('PENDIENTE', 'EN_PROCESO', 'ENTREGADO', 'FALLIDO')`).
- `intentos` (`INT`, Default `1`).
- `ultimo_error` (`TEXT`, Nullable).
- `fecha_entrega` (`DATETIME`, Nullable).
- **Índice**: `INDEX idx_idempotency_estado (idempotency_key, estado)`.

---

## 3. Idempotencia y Reversibilidad de Migraciones

Todas las migraciones de FamilyLock v1.4.8 implementan:
1. Comprobación defensiva con `Schema::hasTable()` y `Schema::hasColumn()`.
2. Compatibilidad cruzada verificada con MariaDB 12.x, MySQL 8.x y SQLite en memoria.
3. Desactivación e inactivación controlada de claves foráneas en métodos `down()`.
4. Claves foráneas con nombres semánticos explícitos (`fk_*`) e índices dedicados (`idx_*`).
