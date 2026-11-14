# FamilyLock (CAM 48) - Sistema Integral de Asistencia y Expedientes Escolares

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="280" alt="Laravel Logo">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white" alt="Vue 3">
  <img src="https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia.js">
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/MariaDB-10.11%2B%20%2F%20MySQL-4479A1?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB / MySQL">
</p>

---

## 📌 Descripción del Proyecto

**FamilyLock** es una plataforma web desarrollada a la medida para el **Centro de Atención Múltiple No. 48 (CAM 48)**. Su objetivo primordial es garantizar la seguridad escolar, agilizar el control de asistencias de alumnos, personal docente y tutores, y mantener una comunicación directa e inmediata entre la institución educativa y las familias mediante notificaciones en tiempo real vía **Telegram** y **WhatsApp**.

---

## 🚀 Características Principales

- **Terminal Kiosco de Asistencia QR Seguro**:
  - Lectura en vivo mediante cámara web o lector óptico de códigos de barras.
  - Validación criptográfica **HMAC-SHA256** vinculada a la `APP_KEY` para prevenir clonación y falsificación de códigos QR.
  - Cooldown de 5 minutos anti-lecturas accidentales y protección contra DoS mediante limitador de tasa (*Rate Limiting*).
- **Procesamiento Asíncrono de Mensajería (Colas / Queues)**:
  - Notificaciones inmediatas a tutores sobre entrada o salida escolar despachadas en segundo plano mediante `NotificarAsistenciaJob`.
  - Garantiza respuesta sub-segundo en la terminal de escaneo durante horas pico escolares.
- **Relaciones Polimórficas de Asistencia**:
  - Estructura escalable de datos (`asistible_type`, `asistible_id`) que unifica el control de Alumnos, Tutores y Personal escolar.
- **Integración con Telegram & WhatsApp**:
  - Comando programado de sincronización de cuentas (`php artisan telegram:sync`).
  - Avisos individuales, masivos y alertas de eventos escolares.
- **Directorio y Expediente Digital**:
  - Registro médico, grado, grupo, condición o discapacidad y fotografías optimizadas automáticamente con `Intervention Image`.
  - Borrado lógico (*Soft Deletes*) para tutores y eventos para evitar pérdidas accidentales.
- **Módulo de Estadísticas y Reconocimientos**:
  - Gráficas interactivas de asistencia diaria y semanal.
  - Ranking de *Padres Comprometidos* con exportación nativa a **Excel (.xlsx)** y **PDF**.

---

## 🛠️ Requisitos del Entorno

- **PHP**: `^8.3` (probado en 8.3–8.5) con extensiones activas: `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd` o `imagick`.
- **Laravel**: `^13.8`
- **Composer**: `^2.7`
- **Node.js**: `^20.x` o `^22.x` LTS (requerido para compilación con Vite 8)
- **NPM**: `^10.x`
- **Base de Datos**: MariaDB `10.11+ LTS` o MySQL `8.0+` (con soporte `utf8mb4` e InnoDB)
- **Certificado SSL / HTTPS**: Obligatorio en producción para permitir el acceso del navegador a la cámara web en la terminal del kiosco.

---

## 📦 Instalación y Configuración Local

### 1. Clonar el repositorio
```bash
git clone https://github.com/yeri-833/Proyecto_Cam48.git
cd Proyecto_Cam48
```

### 2. Instalar dependencias
```bash
composer install
npm install
```

### 3. Configurar variables de entorno
Copia el archivo base y ajusta tus credenciales:
```bash
copy .env.example .env
```

Configura en tu `.env`:
```ini
APP_NAME=FamilyLock
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=proyecto_colegio
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database

# TELEGRAM BOT
TELEGRAM_BOT_TOKEN=tu_token_de_bot_aqui
TELEGRAM_BOT_USERNAME=cam48_notificaciones_bot
TELEGRAM_CHANNEL_ID=@cam48comunicados

# WHATSAPP API
WHATSAPP_API_URL=https://tu-gateway-whatsapp.com
WHATSAPP_API_TOKEN=tu_token_aqui
```

### 4. Generar clave de aplicación y enlazar almacenamiento
```bash
php artisan key:generate
php artisan storage:link
```

### 5. Ejecutar migraciones
```bash
php artisan migrate
```

### 6. Iniciar el entorno de desarrollo
En terminales independientes (o mediante `npm run dev` / `composer run dev`):
```bash
# Servidor web local
php artisan serve

# Worker para procesamiento de notificaciones en cola
php artisan queue:work --tries=3

# Compilador de assets Vite
npm run dev
```

---

## ⚙️ Despliegue en Servidor de Producción (Linux / Ubuntu + Nginx)

1. **Variables de entorno estrictas:**
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://sistema.cam48.edu.mx
   ```
2. **Certificado SSL:** Instalar Let's Encrypt / Certbot (`sudo certbot --nginx`). Sin HTTPS, los navegadores modernos **bloquean el acceso a la cámara** para el escáner de asistencias.
3. **Supervisor para Colas:** Mantener activo el demonio de Laravel Queue Worker:
   ```ini
   [program:familylock-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /ruta/al/proyecto/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/ruta/al/proyecto/storage/logs/worker.log
   ```
4. **Programador de Tareas (Cron):**
   ```bash
   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## 🧪 Pruebas Automatizadas

Para ejecutar la suite de pruebas unitarias y de integración:
```bash
php artisan test
```

---

## 📄 Licencia

Este proyecto está bajo la licencia [MIT](LICENSE). Desarrollado para la administración escolar del Centro de Atención Múltiple No. 48.
