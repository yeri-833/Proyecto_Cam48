# FamilyLock — Modelo de Seguridad y Control de Acceso (v1.4.8)

## 1. Matriz de Roles y Autorizaciones (RBAC)

FamilyLock implementa un modelo de control de acceso basado en roles gestionado mediante Gates de Laravel (`AppServiceProvider`) y Policies dedicadas:

| Rol | Alcance de Acceso | Políticas Principales |
| :--- | :--- | :--- |
| **Administrador / Directivo** (`admin`) | Acceso total al sistema escolar | `personal-access`, `admin-access`, `can:alumnos.*`, `can:tutores.*`, `can:personal.*`, `can:eventos.*` |
| **Docente / Personal Escolar** (`docente` / `user`) | Acceso acotado a su grado y grupo asignado | `personal-access`, `can:alumnos.consultar`, `can:alumnos.actualizar` (restringido por `AlumnoPolicy`) |
| **Operador de Kiosco** | Terminal de escáner asistencial | `can:asistencia.escanear`, `can:asistencia.procesarQr` |

### Alcance por Grado y Grupo (`AlumnoPolicy`)
La política `AlumnoPolicy` verifica que si un usuario autenticado no posee el rol directivo `admin`, solo puede consultar, exportar o actualizar expedientes de alumnos que coincidan con el `grado` y `grupo` asignados en su perfil de personal escolar (`$query->accessibleTo($user)`).

---

## 2. Preservación y Protección de Datos Sensibles (PII)

### Protección contra Anulación Involuntaria
- En `DashboardController`, `AlumnoController` y `Dashboard.vue`, la actualización de un expediente conserva íntegramente los campos no modificados (CURP, fecha de nacimiento, edad, discapacidad, observaciones médicas).
- Los campos vacíos en el payload de actualización no anulan la base de datos a menos que se envíe explícitamente una bandera autorizada de borrado (`borrar_curp = true`, `borrar_comentarios = true`).

### Encriptación y Claves Cifradas
- **Inmutabilidad de `APP_KEY`**: El script de despliegue `composer setup` verifica la existencia de `APP_KEY` en el archivo `.env` antes de ejecutar `key:generate`, protegiendo la validez de los tokens de sesión y las firmas de credenciales QR emitidas a los alumnos.
- **Tokens de Vinculación de Telegram**: La asociación de cuentas de Telegram utiliza un protocolo criptográfico HMAC (`VINC_...`) con tiempo de vida limitado (TTL de 24 horas), impidiendo ataques de enumeración secuencial de identificadores de personal o tutores (OWASP A01).

---

## 3. Resiliencia contra Ataques y Condiciones de Carrera

### Prevención de Reentradas en Escáner QR
- El escáner asistencial (`AsistenciaController@procesarQr`) implementa una doble barrera:
  1. **Cooldown General**: Bloqueo de lecturas consecutivas para la misma persona en un intervalo de 5 minutos.
  2. **Deduplicación por Evento**: Verificación previa en `registros_asistencia` y `evento_convocatorias`, rechazando duplicidades con el mensaje exacto: *"Asistencia ya registrada previamente para este evento escolar"*.
  3. **Rate Limiting HTTP**: Limitado a 60 peticiones por minuto por dirección IP (`throttle:60,1`).

### Sincronización Segura de Telegram (`/telegram/sync`)
- La ejecución del comando de sincronización de actualizaciones de Telegram está protegida mediante un candado atómico en caché (`Cache::lock('telegram_sync_lock', 10)`), previniendo concurrencia destructiva o agotamiento de cuotas ante múltiples peticiones simultáneas.

### Trazabilidad y Ofuscación de Logs
- Los errores registrados en el canal de logs escolar ofuscan datos personales (números de teléfono, correos, nombres completos de menores de edad), registrando únicamente códigos de estado HTTP, tipos de excepción y matrículas escolares anonimizadas.
