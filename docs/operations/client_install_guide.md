# FamilyLock v1.4.8 — Manual Técnico de Instalación, Operación y Mantenimiento

> **Destinatarios:** Administradores de Sistemas, Ingenieros DevOps y Personal Técnico Escolar.  
> **Producto:** FamilyLock — Sistema de Control de Asistencia Escolar y Comunicación Institucional.  
> **Versión:** 1.4.8 (Edición Consolidada y Lista para Distribución Institucional/Comercial).  
> **Modelo de Despliegue:** *On-Premises* / Nube Privada Dedicada por Escuela (Arquitectura Monolítica SPA Segura).

---

## 1. Requisitos del Sistema

### 1.1 Requisitos Obligatorios (Imprescindibles para Operar)

| Componente | Requisito Mínimo Obligatorio | Notas de Validación |
| :--- | :--- | :--- |
| **Sistema Operativo** | Linux LTS (Ubuntu 22.04 LTS / Ubuntu 24.04 LTS / Debian 12 / RHEL 9 / Rocky Linux 9). | Servidor físico en el plantel o VPS/Instancia en la nube. |
| **PHP** | PHP 8.3 o superior (Probado y optimizado en PHP 8.3, 8.4 y 8.5). | SAPI CLI y PHP-FPM obligatorios. |
| **Extensiones PHP** | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`. | `pdo_mysql` es crítico para transacciones MariaDB. |
| **Motor de Base de Datos** | MariaDB 10.11+ LTS o MySQL 8.0+. | Debe soportar codificación `utf8mb4` e InnoDB. |
| **Servidor Web** | Nginx 1.20+ o Apache 2.4+ con `mod_rewrite`. | Se recomienda Nginx para máximo rendimiento en terminales QR concurrentes. |
| **Gestor de Dependencias** | Composer 2.7+. | Necesario para instalar y actualizar librerías PHP. |
| **Entorno Node.js** | Node.js 20.x o 22.x LTS y NPM 10+. | Exclusivamente requerido durante la fase de despliegue para compilar assets Vite. |
| **Certificado SSL/TLS** | Certificado X.509 válido (Let's Encrypt o institucional). | Los navegadores modernos **bloquean la cámara web de los quioscos QR** si el origen no es `https://` o `localhost`. |
| **Gestor de Procesos** | Supervisor (o Systemd) para trabajadores de cola. | Para el despacho asíncrono y reintentos de notificaciones. |

### 1.2 Recomendaciones de Infraestructura (Recomendado para Planteles Medianos y Grandes)

- **CPU / RAM**: 2 vCPU y 4 GB de RAM (soporta hasta 1,500 alumnos y 3 quioscos de acceso continuo).
- **Almacenamiento**: Disco SSD o NVMe con al menos 20 GB de espacio libre (para expedientes en PDF, fotografías y respaldos rotativos).
- **Caché / Sesiones**: Para planteles con más de 2,000 alumnos concurrentes, se recomienda Redis 7+ en lugar del driver `database`.

---

## 2. Instalación Inicial Paso a Paso

### Paso 1: Aprovisionar Dependencias en el Servidor (Ubuntu / Debian)

```bash
# Actualizar repositorios
sudo apt update && sudo apt upgrade -y

# Instalar PHP, extensiones, MariaDB, Nginx, Supervisor y herramientas
sudo apt install -y nginx mariadb-server supervisor git curl unzip \
    php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-gd php8.3-mysql php8.3-zip php8.3-intl php8.3-bcmath

# Instalar Composer globalmente
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Node.js LTS (v22.x)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

### Paso 2: Creación de la Base de Datos

Acceda a la consola de MariaDB y ejecute:

```sql
CREATE DATABASE familylock_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'familylock_user'@'localhost' IDENTIFIED BY 'CLAVE_ROBUSTA_AQUI';
GRANT ALL PRIVILEGES ON familylock_db.* TO 'familylock_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Paso 3: Despliegue del Código Fuente y Permisos

```bash
# Clonar en el directorio web corporativo
sudo git clone -b feature/familylock-v1.4.8-consolidacion https://github.com/yeri-833/Proyecto_Cam48.git /var/www/familylock
cd /var/www/familylock

# Asignar propietario a www-data (o usuario de Nginx/PHP-FPM)
sudo chown -R www-data:www-data /var/www/familylock
sudo chmod -R 775 /var/www/familylock/storage /var/www/familylock/bootstrap/cache
```

### Paso 4: Configuración del Entorno (`.env`)

Copie la plantilla estandarizada y configure las credenciales:

```bash
sudo cp .env.example .env
sudo nano .env
```

Parámetros fundamentales a definir:
```ini
APP_NAME="FamilyLock - Plantel Escolar"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://asistencia.plantel.edu.mx

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=familylock_db
DB_USERNAME=familylock_user
DB_PASSWORD="CLAVE_ROBUSTA_AQUI"

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# Tokens de Notificaciones
TELEGRAM_BOT_TOKEN="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ"
TELEGRAM_BOT_USERNAME="MiEscuelaBot"

# Administrador Institucional Inicial
ADMIN_EMAIL=director@plantel.edu.mx
ADMIN_PASSWORD="ClaveInicialAdministrador123!"
```

### Paso 5: Instalación Automatizada y Compilación

Ejecute como usuario `www-data` (o ajuste permisos posteriormente):

```bash
# Instalación de dependencias backend sin riesgo de sobreescritura de APP_KEY
sudo -u www-data composer install --no-dev --optimize-autoloader

# Generar llave institucional si es una instalación completamente nueva
sudo -u www-data php artisan key:generate --force

# Ejecutar migraciones de base de datos de manera grácil
sudo -u www-data php artisan migrate --graceful --ansi

# Instalar dependencias frontend y compilar paquetes Vite
npm ci --ignore-scripts
npm run build

# Crear enlace simbólico de almacenamiento público
sudo -u www-data php artisan storage:link

# Sembrar o crear usuario administrador inicial
sudo -u www-data php artisan db:seed --force
```

---

## 3. Configuración del Servidor Web (Nginx)

Cree el archivo `/etc/nginx/sites-available/familylock.conf`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name asistencia.plantel.edu.mx;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name asistencia.plantel.edu.mx;

    root /var/www/familylock/public;
    index index.php index.html;

    # Certificados SSL/TLS
    ssl_certificate /etc/letsencrypt/live/asistencia.plantel.edu.mx/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/asistencia.plantel.edu.mx/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Encabezados de Seguridad
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Habilite el sitio y recargue Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/familylock.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 4. Configuración de Colas y Workers (Supervisor)

Cree `/etc/supervisor/conf.d/familylock-worker.conf`:

```ini
[program:familylock-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/familylock/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --backoff=10,60,180
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/familylock/storage/logs/worker.log
```

Active y verifique el servicio:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 5. Tareas Programadas (Scheduler de Laravel)

Edite el crontab del usuario del servidor web (`crontab -u www-data -e`) y agregue:

```bash
* * * * * cd /var/www/familylock && php artisan schedule:run >> /dev/null 2>&1
```

Esto ejecuta automáticamente la sincronización de mensajes de Telegram, la detección de retardos/inasistencias y la limpieza de registros temporales.

---

## 6. Procedimiento de Actualización Grácil (v1.4.7 a v1.4.8 o Superiores)

La versión v1.4.8 está diseñada para actualizarse **sin tiempo de inactividad disruptivo ni pérdida de datos**:

```bash
cd /var/www/familylock

# 1. Poner la aplicación en modo mantenimiento controlado
sudo -u www-data php artisan down --render="errors::503" --secret="actualizacion-plantel"

# 2. Descargar los cambios del repositorio
sudo git pull origin feature/familylock-v1.4.8-consolidacion

# 3. Actualizar librerías sin ejecutar comandos destructivos
sudo -u www-data composer install --no-dev --optimize-autoloader

# 4. Ejecutar migraciones incrementales
sudo -u www-data php artisan migrate --graceful --ansi

# 5. Recompilar frontend Vite
npm ci --ignore-scripts
npm run build

# 6. Limpiar y calentar cachés de producción
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 7. Reiniciar workers de colas
sudo -u www-data php artisan queue:restart

# 8. Reactivar la aplicación
sudo -u www-data php artisan up
```

---

## 7. Estrategia de Respaldos y Restauración

### 7.1 Respaldo Completo de Base de Datos y Archivos

Cree un script de respaldo automático `/usr/local/bin/backup-familylock.sh`:

```bash
#!/bin/bash
FECHA=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="/var/backups/familylock"
mkdir -p "$BACKUP_DIR"

# 1. Respaldo de Base de Datos (consistente y transaccional)
mariadb-dump -u familylock_user -p'CLAVE_ROBUSTA_AQUI' \
    --single-transaction --quick --routines --triggers familylock_db \
    | gzip > "$BACKUP_DIR/db_familylock_${FECHA}.sql.gz"

# 2. Respaldo de Expedientes y Fotografías de Alumnos
tar -czf "$BACKUP_DIR/files_familylock_${FECHA}.tar.gz" -C /var/www/familylock/storage/app .

# 3. Retención de 30 días
find "$BACKUP_DIR" -type f -mtime +30 -delete
```

### 7.2 Procedimiento de Restauración ante Desastres

```bash
# 1. Detener workers y poner en mantenimiento
sudo supervisorctl stop familylock-worker:*
sudo -u www-data php /var/www/familylock/artisan down

# 2. Restaurar Base de Datos
gunzip < /var/backups/familylock/db_familylock_FECHA.sql.gz | mariadb -u familylock_user -p'CLAVE_ROBUSTA_AQUI' familylock_db

# 3. Restaurar Almacenamiento
tar -xzf /var/backups/familylock/files_familylock_FECHA.tar.gz -C /var/www/familylock/storage/app/

# 4. Restaurar Permisos
sudo chown -R www-data:www-data /var/www/familylock/storage
sudo chmod -R 775 /var/www/familylock/storage

# 5. Reactivar Servicios
sudo -u www-data php /var/www/familylock/artisan up
sudo supervisorctl start familylock-worker:*
```

---

## 8. Diagnóstico y Resolución de Problemas Frecuentes

| Síntoma | Causa Frecuente | Solución Técnica |
| :--- | :--- | :--- |
| **"Código QR inválido o falsificado (Firma incorrecta)"** | La variable `APP_KEY` cambió en el servidor respecto a cuando se imprimieron las credenciales. | **Nunca regenere `APP_KEY`** si ya existen credenciales impresas. Restaure el valor de `APP_KEY` respaldado en el archivo `.env`. |
| **La cámara no se activa en el quiosco web** | El sitio se está accediendo vía HTTP en lugar de HTTPS. | Active HTTPS con un certificado SSL válido. Las especificaciones de MediaDevices de los navegadores bloquean la cámara en conexiones no seguras. |
| **Los mensajes de Telegram o avisos no se envían** | El worker de Supervisor está detenido o no hay conexión a internet. | Compruebe `sudo supervisorctl status` y revise el log en `storage/logs/worker.log`. Pruebe el token ejecutando `php artisan tinker --execute 'dump(config("services.telegram"));'`. |
| **"Unable to locate file in Vite manifest"** | No se ejecutó la compilación de frontend tras una actualización. | Ejecute `npm run build` en la raíz del proyecto. |
| **Pase de asistencia dice "Pase registrado recientemente"** | Cooldown de seguridad de 5 minutos activo. | Comportamiento esperado contra lecturas repetitivas accidentales. Si el alumno salió y reingresa después de 5 minutos, el sistema lo admitirá. |
