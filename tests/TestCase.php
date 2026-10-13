<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ─────────────────────────────────────────────────────────────
        // CIRCUITO PREVENTIVO DE AISLAMIENTO INSTITUCIONAL CAM #48
        // Bloqueo preventivo PRE-EJECUCIÓN: valida configuración, URLs,
        // conexiones secundarias y hooks antes de enviar cualquier consulta a PDO.
        // ─────────────────────────────────────────────────────────────
        $this->enforcePreemptiveTestIsolation();

        // Preparación automática del esquema en base de pruebas aislada
        $this->prepareIsolatedTestSchema();
    }

    /**
     * Valida preventivamente que el entorno de pruebas esté 100% aislado
     * y no tenga acceso ni credenciales hacia la base institucional 'proyecto_colegio'.
     *
     * @throws RuntimeException
     */
    protected function enforcePreemptiveTestIsolation(): void
    {
        // 1. Verificación de variables de entorno de conexión directa (DB_URL / DATABASE_URL)
        $dbUrl = (string) (env('DB_URL') ?: env('DATABASE_URL') ?: config('database.url', ''));
        if (str_contains(strtolower($dbUrl), 'proyecto_colegio') && ! str_contains(strtolower($dbUrl), 'test')) {
            throw new RuntimeException(
                "CRITICAL PREVENTIVE CIRCUIT BREAKER: Variable DB_URL apunta a la base institucional [{$dbUrl}]. Ejecución abortada preventivamente antes de abrir conexiones."
            );
        }

        // 2. Verificación de la conexión por defecto
        $defaultConn = (string) config('database.default', 'sqlite');
        $defaultDb = (string) config("database.connections.{$defaultConn}.database", '');

        if ($defaultConn !== 'sqlite' && ! str_contains(strtolower($defaultDb), 'test')) {
            throw new RuntimeException(
                "CRITICAL PREVENTIVE CIRCUIT BREAKER: La conexión activa de pruebas debe ser 'sqlite' (:memory:) o una base MariaDB de prueba aislada con 'test' en su nombre. Detectada: [{$defaultConn}] apuntando a [{$defaultDb}]."
            );
        }

        // 3. Inspección exhaustiva de TODAS las conexiones secundarias configuradas
        /** @var array<string, array<string, mixed>> $connections */
        $connections = config('database.connections', []);
        foreach ($connections as $name => $config) {
            $dbName = strtolower((string) ($config['database'] ?? ''));
            $url = strtolower((string) ($config['url'] ?? ''));

            if (
                (str_contains($dbName, 'proyecto_colegio') && ! str_contains($dbName, 'test')) ||
                (str_contains($url, 'proyecto_colegio') && ! str_contains($url, 'test'))
            ) {
                // Neutralizar preventivamente la conexión para impedir cualquier intento de resolución
                config(["database.connections.{$name}.database" => 'unauthorized_blocked_test_db']);
                config(["database.connections.{$name}.host" => '127.0.0.1.blocked']);

                throw new RuntimeException(
                    "CRITICAL PREVENTIVE CIRCUIT BREAKER: Conexión configurada no autorizada [{$name}] detectada apuntando a base institucional [{$dbName}]. Bloqueada preventivamente antes de ejecutar pruebas."
                );
            }
        }

        // 4. Hook preventivo en tiempo de establecimiento de conexión PDO (ConnectionEstablished)
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            $connName = $event->connectionName;
            $db = (string) $event->connection->getDatabaseName();

            if (str_contains(strtolower($db), 'proyecto_colegio') && ! str_contains(strtolower($db), 'test')) {
                $event->connection->disconnect();
                throw new RuntimeException(
                    "CRITICAL PREVENTIVE CIRCUIT BREAKER: Intento de conexión PDO activa interceptado hacia la base protegida [{$db}] mediante [{$connName}]. Desconexión forzosa inmediata."
                );
            }
        });

        // 5. Interceptor preventivo ANTES de despachar cualquier consulta SQL (beforeExecuting)
        if ($this->app->bound('db')) {
            $connection = $this->app->make('db')->connection();
            if ($connection instanceof Connection) {
                $connection->beforeExecuting(function ($query, $bindings, $conn) {
                    $db = (string) $conn->getDatabaseName();
                    if (str_contains(strtolower($db), 'proyecto_colegio') && ! str_contains(strtolower($db), 'test')) {
                        throw new RuntimeException(
                            "CRITICAL PREVENTIVE CIRCUIT BREAKER: Intento de ejecutar SQL interceptado antes del envío a PDO en [{$db}]. SQL bloqueado: [{$query}]."
                        );
                    }
                });
            }
        }
    }

    /**
     * Prepara el esquema de base de datos en la conexión aislada de pruebas.
     */
    protected function prepareIsolatedTestSchema(): void
    {
        $defaultConn = (string) config('database.default', 'sqlite');
        if ($defaultConn === 'sqlite') {
            Artisan::call('migrate', ['--database' => 'sqlite']);

            if (! Schema::connection('sqlite')->hasTable('usuarios')) {
                Schema::connection('sqlite')->create('usuarios', function ($table) {
                    $table->id();
                    $table->string('nombre_completo', 100)->nullable();
                    $table->string('correo', 100)->nullable()->unique();
                    $table->string('telefono', 20)->nullable();
                    $table->string('password', 255)->nullable();
                    $table->string('tipo_usuario', 50)->nullable();
                    $table->timestamp('fecha_registro')->nullable();
                    $table->rememberToken();
                    $table->timestamp('email_verified_at')->nullable();
                    $table->timestamp('updated_at')->nullable();
                });
            }

            if (! Schema::connection('sqlite')->hasColumn('alumnos', 'fecha_registro')) {
                Schema::connection('sqlite')->table('alumnos', function ($table) {
                    $table->timestamp('fecha_registro')->nullable();
                });
            }
        }
    }
}
