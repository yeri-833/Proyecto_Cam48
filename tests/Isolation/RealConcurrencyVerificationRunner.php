<?php

/**
 * Runner de pruebas de CONCURRENCIA REAL multi-proceso contra MariaDB 12.3 aislada (puerto 3307).
 *
 * A diferencia de las pruebas unitarias en memoria, este verificador lanza procesos PHP
 * independientes del sistema operativo (proc_open) conectados simultáneamente a MariaDB
 * para demostrar exclusión mutua real, protección de lease con owner_token y recuperación ante fallos.
 */

require_once __DIR__.'/../../vendor/autoload.php';

use App\Http\Controllers\AsistenciaController;
use App\Jobs\NotificarAsistenciaJob;
use App\Jobs\NotificarAvisoPersonalJob;
use App\Models\Alumno;
use App\Models\Evento;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Isolation\DestructiveTestGuard;

$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = '';
$dbName = 'familylock_concurrency_test';

if (getenv('FAMILYLOCK_TEST_BYPASS_SANDBOX_CHECK') === '1') {
    throw new RuntimeException('La prueba de concurrencia requiere la comprobación de Windows Sandbox.');
}
DestructiveTestGuard::guardDatabaseOperation($host, $port, $dbName, $argv ?? []);

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Config::set('database.connections.mariadb_conc', [
    'driver' => 'mariadb',
    'host' => $host,
    'port' => $port,
    'database' => $dbName,
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
]);

if (($argv[1] ?? '') === '--child-qr') {
    DB::setDefaultConnection('mariadb_conc');
    Config::set('app.key', 'synthetic-qr-concurrency-key');
    Carbon::setTestNow(Carbon::parse('2030-01-01 10:00:00'));
    Queue::fake();
    Auth::setUser(new User(['tipo_usuario' => 'Operador']));

    DB::connection()->beforeExecuting(static function (string $sql): void {
        if (str_contains($sql, '`alumnos`') && str_contains(strtolower($sql), 'for update')) {
            echo "QR_READY\n";
            flush();
        }
    });

    $id = $argv[2];
    $request = Request::create('/escaner/procesar', 'POST', [
        'evento_id' => $argv[3] === '' ? null : $argv[3],
        'qr_data' => json_encode([
            'id' => $id,
            'rol' => 'Alumno',
            'signature' => hash_hmac('sha256', $id.'Alumno', config('app.key')),
        ], JSON_THROW_ON_ERROR),
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $respuesta = $app->make(AsistenciaController::class)->procesarQr($request);
    echo 'QR_RESULT '.json_encode([
        'status' => $respuesta->getData(true)['status'],
        'jobs' => Queue::pushed(NotificarAsistenciaJob::class)->count(),
    ], JSON_THROW_ON_ERROR)."\n";

    exit(0);
}

// Modo hijo: ejecuta un worker independiente sincronizado por barrera temporal
if (($argv[1] ?? '') === '--child-worker') {
    $workerId = $argv[2];
    $idempotencyKey = $argv[3];
    $barrierFile = $argv[4];
    $sendLogFile = $argv[5];
    $sleepMs = (int) ($argv[6] ?? 200);

    DB::purge('mariadb_conc');
    DB::setDefaultConnection('mariadb_conc');

    // Esperar hasta que exista el archivo barrera para arrancar ambos procesos al unísono
    $deadline = microtime(true) + 10.0;
    while (! file_exists($barrierFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "Worker {$workerId}: timeout esperando barrera\n");
            exit(2);
        }
        usleep(5000);
    }

    $personal = Personal::findOrFail('PERS_CONC_01');

    $tgStub = new class($sendLogFile, $workerId, $sleepMs) extends TelegramService
    {
        public function __construct(
            private string $logFile,
            private string $workerId,
            private int $sleepMs
        ) {}

        public function enviarMensajeDirectoDetallado(string|int $chatId, string $nombreTutor, string $mensajeTexto): array
        {
            usleep($this->sleepMs * 1000);
            file_put_contents($this->logFile, $this->workerId."\n", FILE_APPEND | LOCK_EX);

            return ['status' => 'Enviado a proveedor', 'success' => true];
        }
    };

    $job = new NotificarAvisoPersonalJob($personal, 'Mensaje concurrente de prueba', $idempotencyKey);
    $job->handle($tgStub);

    exit(0);
}

// ─────────────────────────────────────────────────────────────
// MODO COORDINADOR
// ─────────────────────────────────────────────────────────────
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("DROP DATABASE IF EXISTS {$dbName}");
$pdo->exec("CREATE DATABASE {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE {$dbName}");

DB::purge('mariadb_conc');
DB::setDefaultConnection('mariadb_conc');

// Aplicar migraciones reales de personal y notificaciones_procesadas
$pdo->exec('
    CREATE TABLE personal (
        id VARCHAR(50) PRIMARY KEY,
        nombre_completo VARCHAR(150) NOT NULL,
        email VARCHAR(150) NULL,
        telefono VARCHAR(30) NULL,
        telegram_chat_id VARCHAR(50) NULL,
        activo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    ) ENGINE=InnoDB;

    CREATE TABLE alumnos (
        matricula VARCHAR(50) PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        activo TINYINT(1) DEFAULT 1,
        fecha_registro TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE tutores (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        matricula_alumno VARCHAR(50) NULL,
        t1_nombre VARCHAR(150) NOT NULL,
        t1_telefono VARCHAR(30) NULL,
        t1_telegram_chat_id VARCHAR(50) NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    ) ENGINE=InnoDB;
');

foreach ([
    '2026_11_07_000001_create_notificaciones_procesadas_table.php',
    '2026_11_13_000000_add_owner_token_to_notificaciones_procesadas_table.php',
    '2026_11_13_000001_add_detalle_canales_to_notificaciones_procesadas_table.php',
] as $mig) {
    $instance = require database_path('migrations/'.$mig);
    $instance->up();
}

Personal::create([
    'id' => 'PERS_CONC_01',
    'nombre_completo' => 'Docente Concurrencia Real',
    'email' => 'docente.conc@escuela.test',
    'telefono' => '9215550101',
    'telegram_chat_id' => '99887766',
    'activo' => true,
]);

$assertions = 0;
function assertConc(bool $cond, string $msg, int &$assertions): void
{
    $assertions++;
    if (! $cond) {
        throw new RuntimeException("FALLO CONCURRENCIA [{$assertions}]: {$msg}");
    }
    echo "  [OK] {$msg}\n";
}

function spawnWorkerProcess(string $workerId, string $key, string $barrierFile, string $sendLogFile, int $sleepMs = 250)
{
    $cmd = [
        PHP_BINARY,
        __FILE__,
        '--child-worker',
        $workerId,
        $key,
        $barrierFile,
        $sendLogFile,
        (string) $sleepMs,
        DestructiveTestGuard::CLI_AUTHORIZATION_FLAG,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 2));
    if (! is_resource($process)) {
        throw new RuntimeException("No se pudo iniciar proceso hijo {$workerId}");
    }
    fclose($pipes[0]);

    return ['process' => $process, 'pipes' => $pipes];
}

function waitWorkerProcess(array $handle): int
{
    $stdout = stream_get_contents($handle['pipes'][1]);
    $stderr = stream_get_contents($handle['pipes'][2]);
    fclose($handle['pipes'][1]);
    fclose($handle['pipes'][2]);
    $code = proc_close($handle['process']);
    if ($code !== 0) {
        echo "  [STDERR hijo]: {$stderr}\n";
    }

    return $code;
}

echo "========================================================\n";
echo "PRUEBAS DE CONCURRENCIA REAL MULTI-PROCESO (MARIADB 12.3)\n";
echo "========================================================\n\n";

$tmpDir = sys_get_temp_dir();

// 1. Dos procesos independientes compitiendo simultáneamente por la misma notificación
echo "--- Escenario 1: Carrera real entre 2 procesos worker independientes ---\n";
$key1 = hash('sha256', 'conc:carrera:'.uniqid('', true));
$barrier1 = $tmpDir.DIRECTORY_SEPARATOR.'conc_barrier_1.flag';
$log1 = $tmpDir.DIRECTORY_SEPARATOR.'conc_log_1.txt';
@unlink($barrier1);
@unlink($log1);

$w1 = spawnWorkerProcess('W1', $key1, $barrier1, $log1, 300);
$w2 = spawnWorkerProcess('W2', $key1, $barrier1, $log1, 300);
usleep(150000); // Dar tiempo a que ambos procesos arranquen PHP y queden en la barrera
file_put_contents($barrier1, 'GO');

$code1 = waitWorkerProcess($w1);
$code2 = waitWorkerProcess($w2);

$envios1 = file_exists($log1) ? array_filter(explode("\n", trim(file_get_contents($log1)))) : [];
$row1 = DB::table('notificaciones_procesadas')->where('idempotency_key', $key1)->first();

assertConc($code1 === 0 && $code2 === 0, 'Ambos procesos worker terminaron sin errores fatales', $assertions);
assertConc(count($envios1) === 1, 'Exactamente 1 proceso ejecutó el envío al proveedor externo (enviados: '.count($envios1).')', $assertions);
assertConc($row1 !== null && $row1->estado === 'ACEPTADO_PROVEEDOR', 'Estado final en MariaDB es ACEPTADO_PROVEEDOR', $assertions);
assertConc((int) $row1->intentos === 1, 'Contador de intentos exactamente en 1 (sin carrera)', $assertions);
@unlink($barrier1);
@unlink($log1);

// 2. Lease activo sostenido por W1 mientras W2 intenta reclamarlo
echo "\n--- Escenario 2: Lease activo bloquea reclamo de segundo proceso ---\n";
$key2 = hash('sha256', 'conc:lease_activo:'.uniqid('', true));
$tokenActivo = bin2hex(random_bytes(16));
DB::table('notificaciones_procesadas')->insert([
    'idempotency_key' => $key2,
    'destinatario_tipo' => 'Personal',
    'destinatario_id' => 'PERS_CONC_01',
    'canal' => 'Telegram',
    'estado' => 'EN_PROCESO',
    'owner_token' => $tokenActivo,
    'lease_expires_at' => Carbon::now()->addMinutes(5),
    'intentos' => 1,
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

$barrier2 = $tmpDir.DIRECTORY_SEPARATOR.'conc_barrier_2.flag';
$log2 = $tmpDir.DIRECTORY_SEPARATOR.'conc_log_2.txt';
@unlink($log2);
file_put_contents($barrier2, 'GO');
$wIntruso = spawnWorkerProcess('W_INTRUSO', $key2, $barrier2, $log2, 50);
$codeIntruso = waitWorkerProcess($wIntruso);
$row2 = DB::table('notificaciones_procesadas')->where('idempotency_key', $key2)->first();

assertConc($codeIntruso === 0, 'Proceso intruso salió limpiamente sin error', $assertions);
assertConc(! file_exists($log2), 'Proceso intruso NO envió ninguna notificación mientras el lease estaba activo', $assertions);
assertConc($row2->owner_token === $tokenActivo && $row2->estado === 'EN_PROCESO', 'El owner_token activo permanece intacto', $assertions);
@unlink($barrier2);
@unlink($log2);

// 3. Worker con token antiguo no puede finalizar ni marcar fallido un registro tomado por otro proceso
echo "\n--- Escenario 3 y 4: Protección contra token obsoleto y failed() ajeno ---\n";
$personal = Personal::findOrFail('PERS_CONC_01');
$jobObsoleto = new NotificarAvisoPersonalJob($personal, 'Mensaje', $key2);
// Inyectar un token viejo distinto al $tokenActivo actual en DB
$ref = new ReflectionClass($jobObsoleto);
$propToken = $ref->getProperty('activeOwnerToken');
$propToken->setAccessible(true);
$propToken->setValue($jobObsoleto, 'TOKEN_VIEJO_ZOMBIE');
$propKey = $ref->getProperty('activeIdempotencyKey');
$propKey->setAccessible(true);
$propKey->setValue($jobObsoleto, $key2);

$methodComplete = $ref->getMethod('completeNotificationLease');
$methodComplete->setAccessible(true);
$resComplete = $methodComplete->invoke($jobObsoleto, 'ACEPTADO_PROVEEDOR', ['telegram' => 'ACEPTADO_PROVEEDOR'], null, true);

assertConc($resComplete === false, 'Worker con token obsoleto no pudo completar el registro de otro worker', $assertions);

// Intentar ejecutar failed() con token obsoleto
$jobObsoleto->failed(new RuntimeException('Fallo tardío de worker obsoleto'));
$row2PostFailed = DB::table('notificaciones_procesadas')->where('idempotency_key', $key2)->first();
assertConc($row2PostFailed->estado === 'EN_PROCESO' && $row2PostFailed->owner_token === $tokenActivo, 'failed() con token obsoleto NO sobreescribió el lease activo de otro proceso', $assertions);

// 4. Recuperación de trabajo cuyo lease expiró por dos procesos compitiendo
echo "\n--- Escenario 5: Recuperación concurrente de lease expirado ---\n";
$key3 = hash('sha256', 'conc:lease_expirado:'.uniqid('', true));
$tokenZombie = bin2hex(random_bytes(16));
DB::table('notificaciones_procesadas')->insert([
    'idempotency_key' => $key3,
    'destinatario_tipo' => 'Personal',
    'destinatario_id' => 'PERS_CONC_01',
    'canal' => 'Telegram',
    'estado' => 'EN_PROCESO',
    'owner_token' => $tokenZombie,
    'lease_expires_at' => Carbon::now()->subMinutes(10),
    'intentos' => 1,
    'created_at' => Carbon::now()->subMinutes(15),
    'updated_at' => Carbon::now()->subMinutes(15),
]);

$barrier3 = $tmpDir.DIRECTORY_SEPARATOR.'conc_barrier_3.flag';
$log3 = $tmpDir.DIRECTORY_SEPARATOR.'conc_log_3.txt';
@unlink($barrier3);
@unlink($log3);
$wRec1 = spawnWorkerProcess('W_REC1', $key3, $barrier3, $log3, 250);
$wRec2 = spawnWorkerProcess('W_REC2', $key3, $barrier3, $log3, 250);
usleep(150000);
file_put_contents($barrier3, 'GO');
waitWorkerProcess($wRec1);
waitWorkerProcess($wRec2);

$envios3 = file_exists($log3) ? array_filter(explode("\n", trim(file_get_contents($log3)))) : [];
$row3 = DB::table('notificaciones_procesadas')->where('idempotency_key', $key3)->first();

assertConc(count($envios3) === 1, 'Solo 1 de los 2 procesos rescató el lease expirado y envió la notificación', $assertions);
assertConc($row3->estado === 'ACEPTADO_PROVEEDOR' && $row3->owner_token !== $tokenZombie, 'Lease expirado rescatado y finalizado como ACEPTADO_PROVEEDOR', $assertions);
assertConc((int) $row3->intentos === 2, 'Contador de intentos incrementado exactamente a 2', $assertions);
@unlink($barrier3);
@unlink($log3);

// 5. Reintento después de interrupción (FALLIDO) y doble despacho posterior (idempotencia)
echo "\n--- Escenario 6 y 7: Reintento tras FALLIDO y doble despacho idempotente ---\n";
$key4 = hash('sha256', 'conc:retry_fallido:'.uniqid('', true));
DB::table('notificaciones_procesadas')->insert([
    'idempotency_key' => $key4,
    'destinatario_tipo' => 'Personal',
    'destinatario_id' => 'PERS_CONC_01',
    'canal' => 'Telegram',
    'estado' => 'FALLIDO',
    'owner_token' => null,
    'lease_expires_at' => null,
    'intentos' => 1,
    'ultimo_error' => 'Timeout previo de red',
    'created_at' => Carbon::now()->subMinutes(2),
    'updated_at' => Carbon::now()->subMinutes(2),
]);

$barrier4 = $tmpDir.DIRECTORY_SEPARATOR.'conc_barrier_4.flag';
$log4 = $tmpDir.DIRECTORY_SEPARATOR.'conc_log_4.txt';
@unlink($log4);
file_put_contents($barrier4, 'GO');
$wRetry = spawnWorkerProcess('W_RETRY', $key4, $barrier4, $log4, 50);
waitWorkerProcess($wRetry);

// Segundo despacho con la misma clave tras haberse completado
$wDup = spawnWorkerProcess('W_DUP', $key4, $barrier4, $log4, 50);
waitWorkerProcess($wDup);

$envios4 = file_exists($log4) ? array_filter(explode("\n", trim(file_get_contents($log4)))) : [];
$row4 = DB::table('notificaciones_procesadas')->where('idempotency_key', $key4)->first();

assertConc(count($envios4) === 1, 'Reintento completó el envío y el segundo despacho idempotente fue omitido', $assertions);
assertConc($row4->estado === 'ACEPTADO_PROVEEDOR' && (int) $row4->intentos === 2, 'Estado ACEPTADO_PROVEEDOR con 2 intentos registrados', $assertions);
@unlink($barrier4);
@unlink($log4);

// 6. Recuperación independiente multicanal: reintento de canal fallido sin duplicar canal ya aceptado
echo "\n--- Escenario 8: Recuperación multicanal independiente tras fallo parcial ---\n";
$alumnoConc = Alumno::create([
    'matricula' => 'ALUM_CONC_RECUP',
    'nombre' => 'Daniel',
    'apellido' => 'Sánchez',
    'activo' => true,
]);

$tutorConc = Tutor::create([
    'matricula_alumno' => $alumnoConc->matricula,
    't1_nombre' => 'Patricia Sánchez',
    't1_telefono' => '9211234567',
    't1_telegram_chat_id' => '77665544',
]);
$alumnoConc->setRelation('tutor', $tutorConc);

$jobRecup = new NotificarAsistenciaJob($alumnoConc, 'ENTRADA', '08:00 AM', 'Daniel Sánchez', '2026-11-14');
$key5 = $jobRecup->resolverIdempotencyKey();

// Simular que el intento anterior guardó WhatsApp como ACEPTADO_PROVEEDOR pero Telegram como FALLIDO
DB::table('notificaciones_procesadas')->insert([
    'idempotency_key' => $key5,
    'destinatario_tipo' => 'Alumno',
    'destinatario_id' => (string) $alumnoConc->matricula,
    'canal' => 'MULTICANAL',
    'estado' => 'PARCIAL',
    'owner_token' => null,
    'lease_expires_at' => null,
    'intentos' => 1,
    'detalle_canales' => json_encode([
        'whatsapp' => [
            'estado' => 'ACEPTADO_PROVEEDOR',
            'intentos' => 1,
            'ultimo_error' => null,
            'fecha_aceptacion' => Carbon::now()->toIso8601String(),
        ],
        'telegram' => [
            'estado' => 'FALLIDO',
            'intentos' => 1,
            'ultimo_error' => 'Timeout proveedor Telegram',
            'fecha_aceptacion' => null,
        ],
    ], JSON_UNESCAPED_UNICODE),
    'ultimo_error' => 'Telegram: Timeout proveedor Telegram',
    'created_at' => Carbon::now()->subMinutes(1),
    'updated_at' => Carbon::now()->subMinutes(1),
]);

$wsInvocado = false;
$tgInvocado = false;

$wsStub = new class($wsInvocado) extends WhatsAppService
{
    public function __construct(private bool &$wsInvocado)
    {
        parent::__construct();
    }

    public function enviarAvisoAsistenciaDetallado(string $telefono, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): array
    {
        $this->wsInvocado = true;
        throw new RuntimeException('ERROR: WhatsApp no debió ser llamado porque ya fue aceptado.');
    }
};

$tgStub = new class($tgInvocado) extends TelegramService
{
    public function __construct(private bool &$tgInvocado)
    {
        parent::__construct();
    }

    public function enviarAvisoAsistenciaDetallado(string|int|null $chatId, string $nombreAlumno, string $hora, string $tipo = 'Pase de Lista'): array
    {
        $this->tgInvocado = true;

        return ['status' => 'Enviado a proveedor', 'success' => true];
    }
};

// Ejecutar reintento
$jobRecup->handle($wsStub, $tgStub);

$row5 = DB::table('notificaciones_procesadas')->where('idempotency_key', $key5)->first();
$det5 = json_decode((string) $row5->detalle_canales, true);

assertConc($wsInvocado === false, 'WhatsApp NO fue reenviado (inmutabilidad de canal ya aceptado)', $assertions);
assertConc($tgInvocado === true, 'Telegram SÍ fue reintentado y completado', $assertions);
assertConc($row5->estado === 'ACEPTADO_PROVEEDOR', 'Estado final de la notificación promovido a ACEPTADO_PROVEEDOR', $assertions);
assertConc(($det5['whatsapp']['estado'] ?? '') === 'ACEPTADO_PROVEEDOR', 'Detalle de canal WhatsApp preserva ACEPTADO_PROVEEDOR', $assertions);
assertConc(($det5['telegram']['estado'] ?? '') === 'ACEPTADO_PROVEEDOR', 'Detalle de canal Telegram actualizado a ACEPTADO_PROVEEDOR', $assertions);

Schema::create('eventos', function (Blueprint $table): void {
    $table->engine('InnoDB');
    $table->id();
    $table->string('nombre_evento');
    $table->dateTime('fecha_hora');
});
Schema::create('registros_asistencia', function (Blueprint $table): void {
    $table->engine('InnoDB');
    $table->id();
    $table->string('asistible_type');
    $table->string('asistible_id');
    $table->date('fecha');
    $table->time('hora');
    $table->foreignId('evento_id')->nullable()->constrained('eventos');
    $table->index(['asistible_type', 'asistible_id']);
});

$eventoQr = Evento::create(['nombre_evento' => 'Evento QR sintético', 'fecha_hora' => Carbon::now()]);
foreach (['con-evento' => $eventoQr->id, 'sin-evento' => null] as $caso => $eventoId) {
    $matricula = 'QR-'.$caso;
    Alumno::create(['matricula' => $matricula, 'nombre' => 'Alumno', 'apellido' => 'Sintético', 'activo' => true]);
    Tutor::create(['matricula_alumno' => $matricula, 't1_nombre' => 'Tutor Sintético']);
    $workers = [];

    try {
        DB::transaction(function () use ($matricula, $eventoId, &$workers, &$assertions): void {
            Alumno::query()->whereKey($matricula)->lockForUpdate()->firstOrFail();

            foreach ([1, 2] as $workerId) {
                $worker = new Process([
                    PHP_BINARY, __FILE__, '--child-qr', $matricula, (string) $eventoId,
                    DestructiveTestGuard::CLI_AUTHORIZATION_FLAG,
                ], dirname(__DIR__, 2), timeout: 25);
                $workers[$workerId] = $worker;
                $worker->start();
            }

            $limite = microtime(true) + 12;
            do {
                $listos = array_filter($workers, static fn (Process $worker): bool => str_contains($worker->getOutput(), 'QR_READY'));
                if (count($listos) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $limite);

            assertConc(count($listos) === 2, 'Ambos escáneres alcanzaron el bloqueo de la misma persona', $assertions);
        });

        $estados = [];
        $trabajos = 0;
        foreach ($workers as $worker) {
            assertConc($worker->wait() === 0, 'El proceso QR terminó correctamente', $assertions);
            assertConc(preg_match('/^QR_RESULT (.+)$/m', $worker->getOutput(), $resultado) === 1, 'El proceso QR devolvió su resultado', $assertions);
            $respuesta = json_decode($resultado[1], true, flags: JSON_THROW_ON_ERROR);
            $estados[] = $respuesta['status'];
            $trabajos += $respuesta['jobs'];
        }
        sort($estados);
        assertConc($estados === ['success', 'warning'], "Un registro y un duplicado rechazado ({$caso})", $assertions);
        assertConc(RegistroAsistencia::where('asistible_id', $matricula)->count() === 1, "Una sola asistencia persistida ({$caso})", $assertions);
        assertConc($trabajos === 1, "Un solo trabajo de notificación ({$caso})", $assertions);
    } finally {
        foreach ($workers as $worker) {
            $worker->stop();
        }
    }
}

echo "\n========================================================\n";
echo "CONCURRENCIA REAL VERIFICADA: {$assertions} ASERCIONES APROBADAS\n";
echo "========================================================\n";
