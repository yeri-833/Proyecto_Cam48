# TAREAS FASE 5: INFRAESTRUCTURA, DEPLOY Y SEGURIDAD DE SERVIDOR

**ATENCIÓN AGENTE DE PROGRAMACIÓN / DESARROLLADOR:**
Esta es la fase final (Fase 5) de la auditoría del proyecto FamilyLock. El código fuente ya es seguro, asíncrono y está optimizado. Sin embargo, para que todas las refactorizaciones anteriores funcionen en Producción (especialmente las Colas de Mensajería y el Escáner Web), debes preparar la base de datos y la configuración de infraestructura.

---

## PRIORIDAD 1: Preparación de la Base de Datos (Sessions & Queues)

**Contexto:** El sistema está configurado en `.env` para usar la Base de Datos como motor para las sesiones de usuario (`SESSION_DRIVER=database`) y para el procesamiento de tareas en segundo plano (`QUEUE_CONNECTION=database`). 
**Acción Requerida:** Asegúrate de que las tablas existan en MySQL.

1.  Abre la terminal en la raíz del proyecto y ejecuta:
    ```bash
    php artisan queue:table
    php artisan session:table
    php artisan migrate
    ```

---

## PRIORIDAD 2: Activación Permanente del Worker (Colas)

**Contexto:** Las notificaciones de WhatsApp/Telegram y los correos masivos de eventos ahora se despachan a la tabla `jobs`. Si no hay un "Worker" leyendo esa tabla, los mensajes jamás saldrán.
**Acción Requerida (Entorno Local/Desarrollo):**
1.  Debes mantener abierta una consola exclusiva corriendo:
    ```bash
    php artisan queue:work --tries=3
    ```

**Acción Requerida (Entorno Producción - Servidor Linux):**
1.  NO uses XAMPP para el servidor final de la escuela. El proyecto requiere **Linux (Ubuntu) + Nginx + PHP-FPM**.
2.  Instala y configura `Supervisor` (`sudo apt install supervisor`) para crear un Demonio que mantenga vivo el Worker de Laravel indefinidamente.
3.  Crea el archivo `/etc/supervisor/conf.d/familylock-worker.conf`:
    ```ini
    [program:familylock-worker]
    process_name=%(program_name)s_%(process_num)02d
    command=php /ruta/a/tu/proyecto/Proyecto_Cam48/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
    autostart=true
    autorestart=true
    stopasgroup=true
    killasgroup=true
    user=www-data
    numprocs=2
    redirect_stderr=true
    stdout_logfile=/ruta/a/tu/proyecto/Proyecto_Cam48/storage/logs/worker.log
    stopwaitsecs=3600
    ```
4.  Ejecuta: `sudo supervisorctl reread`, `sudo supervisorctl update`, `sudo supervisorctl start familylock-worker:*`.

---

## PRIORIDAD 3: Certificado SSL y Variables de Producción

**Contexto:** La librería `Html5Qrcode` utilizada en el Kiosco (`Escaner.vue`) requiere acceso al Hardware (Cámara Web). Los navegadores modernos (Chrome, Safari, Edge) **bloquean por seguridad el uso de la cámara si la página web no tiene un certificado SSL válido (HTTPS)** (con excepción de `localhost`).

**Acciones Requeridas en el `.env` del Servidor de Producción:**
1.  **Seguridad Crítica:** `APP_DEBUG=false` (Si lo dejas en `true`, cualquier error expondrá las credenciales de la Base de Datos a los usuarios).
2.  **Entorno:** `APP_ENV=production`
3.  **URL Base:** Asegúrate de que tenga HTTPS: `APP_URL=https://sistema.cam48.edu.mx` (o el dominio que vayan a usar).
4.  **SSL:** Instala un certificado SSL (puedes usar _Let's Encrypt / Certbot_ gratuitamente) en Nginx/Apache. Sin SSL, el escáner del Kiosco simplemente no encenderá la cámara.

---
**FIN DEL REPORTE FASE 5 - SISTEMA LISTO PARA PRODUCCIÓN.**
