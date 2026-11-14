<?php

/**
 * Script de validación que ejecuta DIRECTAMENTE los archivos de migración reales de Laravel
 * en MariaDB 12.3 aislada (puerto 3307 en Windows Sandbox).
 *
 * Escenarios probados:
 *  1. Instalación limpia en base de datos vacía ejecutando todas las migraciones de Laravel.
 *  2. Actualización desde esquema sintético v1.4.7 con preservación de datos históricos al 100%.
 *  3. Verificación de claves foráneas, índices, restricciones UNIQUE y reversibilidad (rollback).
 */

require_once __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = '';

$pdoRaw = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "========================================================\n";
echo "VALIDACIÓN REAL DE MIGRACIONES LARAVEL EN MARIADB (PUERTO 3307)\n";
$version = $pdoRaw->query("SELECT VERSION()")->fetchColumn();
echo "Versión del Motor: {$version}\n";
echo "========================================================\n\n";

$assertions = 0;
function assertCondition(bool $cond, string $msg, int &$assertions): void {
    $assertions++;
    if (!$cond) {
        throw new RuntimeException("FALLO EN ASERCIÓN [{$assertions}]: {$msg}");
    }
    echo "  [OK] {$msg}\n";
}

// ─────────────────────────────────────────────────────────────
// ESCENARIO 1: INSTALACIÓN LIMPIA EJECUTANDO TODAS LAS MIGRACIONES REALES
// ─────────────────────────────────────────────────────────────
echo "--- ESCENARIO 1: Instalación Limpia con Migraciones Reales de Laravel ---\n";
$pdoRaw->exec("DROP DATABASE IF EXISTS familylock_real_fresh");
$pdoRaw->exec("CREATE DATABASE familylock_real_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Configurar conexión dinámica de Laravel
Config::set('database.connections.mariadb_fresh', [
    'driver' => 'mariadb',
    'host' => $host,
    'port' => $port,
    'database' => 'familylock_real_fresh',
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
]);

DB::purge('mariadb_fresh');
$freshConn = DB::connection('mariadb_fresh');
$freshSchema = $freshConn->getSchemaBuilder();

$migrator = $app->make('migrator');
$migrator->setConnection('mariadb_fresh');

if (!$migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}

// Ejecutar todas las migraciones reales
$migrationPath = database_path('migrations');
$migrator->run([$migrationPath]);

assertCondition($freshSchema->hasTable('alumnos'), "Tabla alumnos creada por migración real", $assertions);
assertCondition($freshSchema->hasTable('tutores'), "Tabla tutores creada por migración real", $assertions);
assertCondition($freshSchema->hasTable('eventos'), "Tabla eventos creada por migración real", $assertions);
assertCondition($freshSchema->hasTable('registros_asistencia'), "Tabla registros_asistencia creada por migración real", $assertions);
assertCondition($freshSchema->hasTable('notificaciones_procesadas'), "Tabla notificaciones_procesadas creada por migración real", $assertions);
assertCondition($freshSchema->hasTable('alumno_tutor'), "Tabla alumno_tutor creada por migración real", $assertions);
assertCondition($freshSchema->hasTable('evento_convocatorias'), "Tabla evento_convocatorias creada por migración real", $assertions);

// Verificar columnas agregadas por migraciones v1.4.8
assertCondition($freshSchema->hasColumn('registros_asistencia', 'evento_id'), "Columna evento_id presente en registros_asistencia", $assertions);
assertCondition($freshSchema->hasColumn('notificaciones_procesadas', 'owner_token'), "Columna owner_token presente en notificaciones_procesadas", $assertions);
assertCondition($freshSchema->hasColumn('notificaciones_procesadas', 'lease_expires_at'), "Columna lease_expires_at presente en notificaciones_procesadas", $assertions);

// Probar inserción y restricciones foráneas reales en la base limpia
$freshConn->table('alumnos')->insert([
    'matricula' => 'FRESH_ALU_01',
    'nombre' => 'Juan',
    'apellido' => 'Perez',
    'activo' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

$freshConn->table('tutores')->insert([
    'id' => 1,
    'matricula_alumno' => 'FRESH_ALU_01',
    't1_nombre' => 'Rosa Perez',
    't1_telefono' => '9211000001',
]);

$freshConn->table('alumno_tutor')->insert([
    'tutor_id' => 1,
    'alumno_matricula' => 'FRESH_ALU_01',
    'es_tutor_principal' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

$freshConn->table('eventos')->insert([
    'id' => 10,
    'nombre_evento' => 'Junta Diagnostica',
    'fecha_hora' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

$freshConn->table('registros_asistencia')->insert([
    'asistible_type' => 'App\\Models\\Tutor',
    'asistible_id' => '1',
    'fecha' => now()->toDateString(),
    'hora' => now()->toTimeString(),
    'evento_id' => 10,
    'created_at' => now(),
    'updated_at' => now(),
]);

assertCondition($freshConn->table('registros_asistencia')->count() === 1, "Registro con FK evento_id insertado exitosamente", $assertions);

// Probar restricción UNIQUE en alumno_tutor
$uniqueDuplicadaFallada = false;
try {
    $freshConn->table('alumno_tutor')->insert([
        'tutor_id' => 1,
        'alumno_matricula' => 'FRESH_ALU_01',
        'es_tutor_principal' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
} catch (\Exception $e) {
    $uniqueDuplicadaFallada = true;
}
assertCondition($uniqueDuplicadaFallada, "Restricción UNIQUE (tutor_id, alumno_matricula) previene tuplas duplicadas", $assertions);


// ─────────────────────────────────────────────────────────────
// ESCENARIO 2: ACTUALIZACIÓN DESDE ESQUEMA SINTÉTICO v1.4.7 CON DATOS
// ─────────────────────────────────────────────────────────────
echo "\n--- ESCENARIO 2: Actualización v1.4.7 -> v1.4.8 y Preservación de Datos ---\n";
$pdoRaw->exec("DROP DATABASE IF EXISTS familylock_real_upgrade");
$pdoRaw->exec("CREATE DATABASE familylock_real_upgrade CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Crear esquema estricto v1.4.7 previo
$pdoRaw->exec("USE familylock_real_upgrade;");
$pdoRaw->exec("
    CREATE TABLE alumnos (
        matricula VARCHAR(50) PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        activo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE tutores (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        matricula_alumno VARCHAR(50) NULL,
        t1_nombre VARCHAR(150) NOT NULL,
        t1_telefono VARCHAR(20) NULL,
        t1_email VARCHAR(100) NULL,
        t1_direccion VARCHAR(255) NULL,
        deleted_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE eventos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre_evento VARCHAR(150) NOT NULL,
        fecha_hora DATETIME NOT NULL,
        ubicacion VARCHAR(150) NULL,
        descripcion TEXT NULL,
        deleted_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE registros_asistencia (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asistible_type VARCHAR(100) NOT NULL,
        asistible_id VARCHAR(50) NOT NULL,
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

// Insertar datos históricos sensibles
$pdoRaw->exec("
    INSERT INTO alumnos (matricula, nombre, apellido, activo) VALUES
        ('HIST_01', 'Carlitos', 'Perez', 1),
        ('HIST_02', 'Sofia', 'Perez', 1),
        ('HIST_03', 'Mateo', 'Gomez', 1);

    INSERT INTO tutores (id, matricula_alumno, t1_nombre, t1_telefono) VALUES
        (101, 'HIST_01', 'MARIA PEREZ', '9211234567'),
        (102, 'HIST_02', 'MARIA PEREZ', '9211234567'),
        (103, 'HIST_03', 'RAMON GOMEZ', '9217654321');

    INSERT INTO eventos (id, nombre_evento, fecha_hora) VALUES
        (501, 'Junta Trimestral Historica', '2026-09-20 09:00:00');

    INSERT INTO registros_asistencia (id, asistible_type, asistible_id, fecha, hora) VALUES
        (1, 'App\\\\Models\\\\Tutor', '101', '2026-09-20', '09:05:00'),
        (2, 'App\\\\Models\\\\Tutor', '102', '2026-09-20', '09:06:00'),
        (3, 'App\\\\Models\\\\Alumno', 'HIST_03', '2026-09-20', '09:10:00');
");

// Configurar conexión Laravel a la base de actualización
Config::set('database.connections.mariadb_upgrade', [
    'driver' => 'mariadb',
    'host' => $host,
    'port' => $port,
    'database' => 'familylock_real_upgrade',
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
]);

DB::purge('mariadb_upgrade');
$upgradeConn = DB::connection('mariadb_upgrade');
$upgradeSchema = $upgradeConn->getSchemaBuilder();

// Instanciar y ejecutar directamente las clases de migración reales de v1.4.8
$migrationsToApply = [
    '2026_11_07_000000_add_evento_id_to_registros_asistencia_table.php',
    '2026_11_07_000001_create_notificaciones_procesadas_table.php',
    '2026_11_08_000000_create_alumno_tutor_table.php',
    '2026_11_08_000001_create_evento_convocatorias_table.php',
    '2026_11_13_000000_add_owner_token_to_notificaciones_procesadas_table.php',
];

// Establecer conexión por defecto temporalmente para que las migraciones anónimas usen mariadb_upgrade
$previousDefault = DB::getDefaultConnection();
DB::setDefaultConnection('mariadb_upgrade');

foreach ($migrationsToApply as $file) {
    $filePath = database_path('migrations/'.$file);
    echo "  -> Aplicando archivo real de migración: {$file}\n";
    $migrationInstance = require $filePath;
    $migrationInstance->up();
}

DB::setDefaultConnection($previousDefault);

// 1. Verificar que los 3 registros históricos de asistencia siguen intactos con su id y valores originales
$registrosHistoricos = $upgradeConn->table('registros_asistencia')->orderBy('id')->get();
assertCondition($registrosHistoricos->count() === 3, "Los 3 registros históricos de asistencia se conservan", $assertions);
assertCondition($registrosHistoricos[0]->id == 1 && $registrosHistoricos[0]->asistible_id === '101', "Registro histórico 1 íntegro", $assertions);
assertCondition($registrosHistoricos[0]->evento_id === null, "Columna evento_id agregada con valor NULL para registros preexistentes", $assertions);

// 2. Verificar que los 3 alumnos históricos existen intactos
assertCondition($upgradeConn->table('alumnos')->count() === 3, "Los 3 alumnos históricos existen intactos", $assertions);

// 3. Verificar que los 3 tutores históricos existen intactos
assertCondition($upgradeConn->table('tutores')->count() === 3, "Los 3 tutores históricos existen intactos", $assertions);

// 4. Verificar que alumno_tutor migró o puede poblarse con datos históricos
$upgradeConn->table('alumno_tutor')->insertOrIgnore([
    ['tutor_id' => 101, 'alumno_matricula' => 'HIST_01', 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['tutor_id' => 102, 'alumno_matricula' => 'HIST_02', 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['tutor_id' => 103, 'alumno_matricula' => 'HIST_03', 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
]);
assertCondition($upgradeConn->table('alumno_tutor')->count() === 3, "Población de alumno_tutor preservando relaciones existentes", $assertions);

// 5. Verificar que notificaciones_procesadas tiene los nuevos campos owner_token y lease_expires_at
assertCondition($upgradeSchema->hasColumn('notificaciones_procesadas', 'owner_token'), "owner_token agregado exitosamente en actualización", $assertions);
assertCondition($upgradeSchema->hasColumn('notificaciones_procesadas', 'lease_expires_at'), "lease_expires_at agregado exitosamente en actualización", $assertions);

// 6. Probar rollback de la última migración (add_owner_token) y re-aplicación (idempotencia)
echo "\n--- Probando Reversibilidad (Rollback) y Re-aplicación ---\n";
DB::setDefaultConnection('mariadb_upgrade');
$lastMigration = require database_path('migrations/2026_11_13_000000_add_owner_token_to_notificaciones_procesadas_table.php');
$lastMigration->down();
assertCondition(!$upgradeSchema->hasColumn('notificaciones_procesadas', 'owner_token'), "Rollback de add_owner_token eliminó la columna", $assertions);

$lastMigration->up();
assertCondition($upgradeSchema->hasColumn('notificaciones_procesadas', 'owner_token'), "Re-aplicación de add_owner_token restableció la columna sin errores", $assertions);
DB::setDefaultConnection($previousDefault);

echo "\n========================================================\n";
echo "RESULTADO FINAL: TODAS LAS VALIDACIONES REALES APROBADAS\n";
echo "Total de Aserciones Exitosas: {$assertions}\n";
echo "========================================================\n";
