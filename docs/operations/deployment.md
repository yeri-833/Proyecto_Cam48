# FamilyLock — Guía de Operaciones y Despliegue (v1.4.8)

## 1. Requisitos del Servidor y Entorno

- **PHP**: 8.3 o superior (recomendado PHP 8.4/8.5) con extensiones:
  - `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`.
- **Base de Datos**: MariaDB 10.6+ o MySQL 8.0+.
- **Servidor Web**: Nginx o Apache con soporte para `mod_rewrite` y certificados SSL/TLS.
- **Node.js**: v20.x o v22.x LTS para compilación de assets frontend.
- **Gestor de Procesos**: Supervisor o Systemd para trabajadores de cola (`queue:work`).

---

## 2. Variables de Entorno Críticas (`.env`)

```env
APP_NAME="FamilyLock"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://asistencia.cam48.edu.mx

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=familylock_db
DB_USERNAME=familylock_user
DB_PASSWORD="<CLAVE_SEGURA_GENERADA>"

# Colas y Sesiones
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# Integraciones de Mensajería
WHATSAPP_API_URL=https://api.gateway-escolar.com/v1
WHATSAPP_API_TOKEN="<TOKEN_PASARELA_WHATSAPP>"
TELEGRAM_BOT_TOKEN="<TOKEN_BOT_TELEGRAM>"
TELEGRAM_BOT_USERNAME="FamilyLockCam48Bot"

# Credenciales de Administrador Inicial
ADMIN_EMAIL=admin@cam48.edu.mx
ADMIN_PASSWORD="<PASSWORD_INICIAL_ADMIN>"
```

---

## 3. Procedimiento de Instalación Inicial

```bash
# 1. Clonar repositorio
git clone -b feature/familylock-v1.4.8-consolidacion https://github.com/yeri-833/Proyecto_Cam48.git /var/www/familylock
cd /var/www/familylock

# 2. Configurar permisos
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 3. Ejecutar instalación protegida
composer setup
```

> [!IMPORTANT]
> El comando `composer setup` está programado para verificar la existencia previa de `APP_KEY`. Si `APP_KEY` ya está definida, no la regenera, protegiendo las firmas HMAC de credenciales QR ya distribuidas a los alumnos.

---

## 4. Procedimiento de Actualización Grácil

Para actualizar una versión existente en producción:

```bash
# 1. Modo mantenimiento
php artisan down --render="errors::503" --secret="mantenimiento-cam48"

# 2. Descargar cambios
git pull origin feature/familylock-v1.4.8-consolidacion

# 3. Instalar dependencias sin scripts destructivos
composer install --no-dev --optimize-autoloader

# 4. Migración segura y grácil
php artisan migrate --graceful --ansi

# 5. Compilar assets de frontend
npm ci --ignore-scripts
npm run build

# 6. Optimizar caché de Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Reiniciar colas y salir de mantenimiento
php artisan queue:restart
php artisan up
```

---

## 5. Configuración del Worker de Colas (Supervisor)

Archivo: `/etc/supervisor/conf.d/familylock-worker.conf`

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
