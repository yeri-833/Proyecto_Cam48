<?php

namespace Tests\Isolation;

use PDO;
use RuntimeException;

final class IsolationGuard
{
    public const string DISABLED_FUNCTIONS = 'exec,shell_exec,system,passthru,proc_open,popen,pcntl_exec,dl,mail,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,stream_socket_accept,stream_socket_pair,curl_exec,curl_multi_exec,dns_get_record,gethostbyname,gethostbynamel,gethostbyaddr,checkdnsrr,getmxrr';

    /** @param array<string, string> $environment */
    public static function assertEnvironment(array $environment): void
    {
        foreach (['FAMILYLOCK_ISOLATED' => '1', 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $name => $expected) {
            if (($environment[$name] ?? null) !== $expected) {
                throw new RuntimeException('ISOLATION_ENVIRONMENT_REJECTED');
            }
        }

        if (! in_array($environment['FAMILYLOCK_ISOLATION_MODE'] ?? '', ['phpunit', 'larastan'], true)) {
            throw new RuntimeException('ISOLATION_MODE_REJECTED');
        }

        $allowed = ['DB_CONNECTION', 'DB_DATABASE'];
        foreach ($environment as $name => $value) {
            if (in_array(strtoupper($name), $allowed, true) || $value === '') {
                continue;
            }

            if (preg_match('/^(DB_|DATABASE_|MYSQL_|MARIADB_|PGSQL_|REDIS_|AWS_|MAIL_|QUEUE_|CACHE_|TELEGRAM_|WHATSAPP_|APP_KEY$|APP_PREVIOUS_KEYS$|APP_.*_CACHE$|COMPOSER_|PHPRC$|PHP_INI_SCAN_DIR$)/i', $name)) {
                throw new RuntimeException('ISOLATION_INHERITED_CONFIGURATION_REJECTED');
            }
        }
    }

    /** @param array<string, mixed> $database */
    public static function assertDatabaseConfiguration(array $database): void
    {
        if (($database['default'] ?? null) !== 'sqlite' || array_keys($database['connections'] ?? []) !== ['sqlite']) {
            throw new RuntimeException('ISOLATION_SECONDARY_CONNECTION_REJECTED');
        }

        $connection = $database['connections']['sqlite'];
        if (($connection['driver'] ?? null) !== 'sqlite' || ($connection['database'] ?? null) !== ':memory:') {
            throw new RuntimeException('ISOLATION_DATABASE_REJECTED');
        }

        if (array_diff(array_keys($connection), ['driver', 'database', 'prefix', 'foreign_key_constraints', 'name']) !== [] || (isset($connection['name']) && $connection['name'] !== 'sqlite')) {
            throw new RuntimeException('ISOLATION_CONNECTION_OPTIONS_REJECTED');
        }

        if (! empty($database['url']) || ! empty($database['redis'])) {
            throw new RuntimeException('ISOLATION_DATABASE_URL_REJECTED');
        }
    }

    public static function assertRuntime(): void
    {
        self::assertEnvironment(getenv());

        if (PHP_SAPI !== 'cli' || php_ini_loaded_file() !== false || php_ini_scanned_files() !== false) {
            throw new RuntimeException('ISOLATION_PHP_CONFIGURATION_REJECTED');
        }

        $drivers = PDO::getAvailableDrivers();
        if ($drivers !== ['sqlite']) {
            throw new RuntimeException('ISOLATION_PDO_DRIVERS_REJECTED');
        }

        foreach (explode(',', self::DISABLED_FUNCTIONS) as $function) {
            if (function_exists($function)) {
                throw new RuntimeException('ISOLATION_PROCESS_CAPABILITIES_REJECTED');
            }
        }

        foreach (['curl', 'sockets', 'ffi', 'mysqli', 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlsrv', 'odbc', 'com_dotnet'] as $extension) {
            if (extension_loaded($extension)) {
                throw new RuntimeException('ISOLATION_EXTENSION_REJECTED');
            }
        }

        if (! function_exists('openssl_encrypt') || ! function_exists('mb_internal_encoding') || ! extension_loaded('fileinfo')) {
            throw new RuntimeException('ISOLATION_REQUIRED_EXTENSION_MISSING');
        }

        $runtime = self::runtimePath();
        if ((string) ini_get('open_basedir') !== self::allowedPaths($runtime) || (string) ini_get('allow_url_fopen') !== '0' || (string) ini_get('allow_url_include') !== '0') {
            throw new RuntimeException('ISOLATION_FILESYSTEM_BOUNDARY_REJECTED');
        }

        foreach (['http', 'https', 'ftp', 'ftps'] as $wrapper) {
            if (in_array($wrapper, stream_get_wrappers(), true)) {
                stream_wrapper_unregister($wrapper);
            }
        }
    }

    public static function projectPath(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function runtimePath(): string
    {
        $path = getenv('FAMILYLOCK_RUNTIME');
        if (! is_string($path) || ! preg_match('/^familylock-isolation-[a-f0-9]{32}$/', basename($path)) || realpath($path) !== $path || ! is_dir($path)) {
            throw new RuntimeException('ISOLATION_RUNTIME_PATH_REJECTED');
        }
        self::assertLocalRuntimePath($path);

        return $path;
    }

    public static function assertLocalRuntimePath(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (! preg_match('/^[A-Za-z]:\//', $normalized) || strcasecmp(substr($path, 0, 2), substr(PHP_BINARY, 0, 2)) !== 0) {
            throw new RuntimeException('ISOLATION_NONLOCAL_RUNTIME_REJECTED');
        }
    }

    public static function allowedPaths(string $runtime): string
    {
        $root = self::projectPath();
        $paths = array_map(fn (string $path): string => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path), [
            'app', 'database/factories', 'routes', 'resources', 'tests', 'vendor', 'composer.json', 'composer.lock', 'phpunit.xml', 'phpstan-isolated.neon',
        ]);
        $paths[] = $runtime;

        return implode(PATH_SEPARATOR, $paths);
    }
}
