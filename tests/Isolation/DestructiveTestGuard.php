<?php

namespace Tests\Isolation;

use InvalidArgumentException;
use RuntimeException;

/**
 * Guardián de seguridad para verificadores de aislamiento de FamilyLock.
 *
 * Previene la ejecución accidental de operaciones destructivas (DROP DATABASE,
 * CREATE DATABASE, TRUNCATE, etc.) sobre instancias o bases de datos de producción
 * o entornos institucionales reales.
 */
class DestructiveTestGuard
{
    /**
     * Variable de entorno requerida para autorizar operaciones destructivas en aislamiento.
     */
    public const ENV_AUTHORIZATION = 'FAMILYLOCK_ALLOW_DESTRUCTIVE_ISOLATION_TESTS';

    /**
     * Argumento CLI requerido para autorizar operaciones destructivas en aislamiento.
     */
    public const CLI_AUTHORIZATION_FLAG = '--authorize-destructive-tests';

    /**
     * Hosts loopback locales autorizados exclusivamente.
     */
    public const ALLOWED_HOSTS = [
        '127.0.0.1',
        'localhost',
        '::1',
    ];

    /**
     * Puertos autorizados para pruebas destructivas aisladas (3307 por defecto en Sandbox).
     */
    public const ALLOWED_PORTS = [
        3307,
    ];

    /**
     * Puertos estrictamente prohibidos (e.g. puerto estándar de MySQL/MariaDB en producción).
     */
    public const BLOCKED_PORTS = [
        3306,
    ];

    /**
     * Lista blanca estricta de bases de datos sobre las cuales se permite DROP/CREATE.
     */
    public const ALLOWED_DATABASES = [
        'familylock_real_fresh',
        'familylock_real_upgrade',
        'familylock_concurrency_test',
        'familylock_mariadb_fresh',
        'familylock_mariadb_upgrade',
    ];

    /**
     * Patrones o nombres prohibidos explícitamente (producción, institucionales, del sistema).
     */
    public const BLOCKED_DATABASE_NAMES = [
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
        'familylock_db',
        'proyecto_colegio',
        'production',
        'produccion',
        'familylock',
        'cam48',
    ];

    /**
     * Valida de manera integral que una operación destructiva esté autorizada,
     * sobre un host/puerto seguro y una base de datos en lista blanca.
     *
     * @param string $host
     * @param int $port
     * @param string $dbName
     * @param array<int, string> $argv
     * @throws RuntimeException|InvalidArgumentException
     */
    public static function guardDatabaseOperation(string $host, int $port, string $dbName, array $argv = []): void
    {
        self::assertExecutionAuthorized($argv);
        self::assertSafeConnection($host, $port);
        self::assertSafeDatabaseName($dbName);
    }

    /**
     * Verifica que exista autorización explícita por variable de entorno o argumento CLI.
     *
     * @param array<int, string> $argv
     * @throws RuntimeException
     */
    public static function assertExecutionAuthorized(array $argv = []): void
    {
        $envValue = getenv(self::ENV_AUTHORIZATION);
        $hasEnvAuth = ($envValue === '1' || $envValue === 'true');
        $hasCliAuth = in_array(self::CLI_AUTHORIZATION_FLAG, $argv, true);

        if (! $hasEnvAuth && ! $hasCliAuth) {
            throw new RuntimeException(
                "OPERACIÓN DESTRUCTIVA BLOQUEADA: Se requiere autorización explícita para ejecutar pruebas destructivas.\n" .
                "Establezca la variable de entorno " . self::ENV_AUTHORIZATION . "=1 o incluya el flag CLI '" . self::CLI_AUTHORIZATION_FLAG . "'."
            );
        }
    }

    /**
     * Verifica que el host y puerto pertenezcan estrictamente al sandbox de pruebas.
     *
     * @param string $host
     * @param int $port
     * @throws InvalidArgumentException
     */
    public static function assertSafeConnection(string $host, int $port): void
    {
        $normalizedHost = strtolower(trim($host));

        if (! in_array($normalizedHost, self::ALLOWED_HOSTS, true)) {
            throw new InvalidArgumentException(
                "HOST NO AUTORIZADO ('{$host}'): Las operaciones destructivas solo están permitidas en host loopback (" .
                implode(', ', self::ALLOWED_HOSTS) . ")."
            );
        }

        if (in_array($port, self::BLOCKED_PORTS, true)) {
            throw new InvalidArgumentException(
                "PUERTO BLOQUEADO ('{$port}'): El puerto 3306 está estrictamente prohibido para evitar colisiones con instancias estándar o de producción."
            );
        }

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new InvalidArgumentException(
                "PUERTO NO AUTORIZADO ('{$port}'): Las operaciones destructivas solo están permitidas en puertos de aislamiento autorizados (" .
                implode(', ', self::ALLOWED_PORTS) . ")."
            );
        }
    }

    /**
     * Verifica que la base de datos esté en la lista blanca permitida y no en la lista negra.
     *
     * @param string $dbName
     * @throws InvalidArgumentException
     */
    public static function assertSafeDatabaseName(string $dbName): void
    {
        $normalizedDb = strtolower(trim($dbName));

        if (empty($normalizedDb)) {
            throw new InvalidArgumentException("Nombre de base de datos vacío no permitido.");
        }

        if (in_array($normalizedDb, self::BLOCKED_DATABASE_NAMES, true)) {
            throw new InvalidArgumentException(
                "BASE DE DATOS BLOQUEADA ('{$dbName}'): No se permite ejecutar operaciones destructivas sobre bases de datos de sistema o producción."
            );
        }

        $isAllowedExact = in_array($normalizedDb, self::ALLOWED_DATABASES, true);
        $isAllowedPrefix = str_starts_with($normalizedDb, 'familylock_test_');

        if (! $isAllowedExact && ! $isAllowedPrefix) {
            throw new InvalidArgumentException(
                "BASE DE DATOS NO PERMITIDA ('{$dbName}'): Solo se permiten bases de datos registradas en lista blanca: " .
                implode(', ', self::ALLOWED_DATABASES) . " o con prefijo 'familylock_test_'."
            );
        }
    }
}
