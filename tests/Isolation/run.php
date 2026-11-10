<?php

use Tests\Isolation\IsolationGuard;

require_once __DIR__.'/IsolationGuard.php';

try {
    if (PHP_SAPI !== 'cli' || php_ini_loaded_file() !== false || php_ini_scanned_files() !== false) {
        throw new RuntimeException('ISOLATION_LAUNCHER_REQUIRES_PHP_N');
    }

    $mode = $argv[1] ?? '';
    if (! in_array($mode, ['phpunit', 'larastan'], true) || ($argv[2] ?? '') !== '--authorize-isolated-run') {
        throw new RuntimeException('ISOLATION_EXPLICIT_AUTHORIZATION_REQUIRED');
    }

    $filter = null;
    if (count($argv) > 3) {
        if ($mode !== 'phpunit' || count($argv) !== 5 || $argv[3] !== '--filter') {
            throw new RuntimeException('ISOLATION_ARGUMENTS_REJECTED');
        }
        $filter = $argv[4];
    }

    $selectors = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'];
    $inherited = getenv();
    foreach ($selectors as $name => $expected) {
        if (isset($inherited[$name]) && $inherited[$name] !== $expected) {
            throw new RuntimeException('ISOLATION_INHERITED_SELECTOR_REJECTED');
        }
    }
    IsolationGuard::assertEnvironment(array_merge($inherited, $selectors, [
        'FAMILYLOCK_ISOLATED' => '1',
        'FAMILYLOCK_ISOLATION_MODE' => $mode,
    ]));

    $root = IsolationGuard::projectPath();
    if (is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('ISOLATION_PROJECT_CONFIG_CACHE_REJECTED');
    }

    if (PHP_OS_FAMILY !== 'Windows') {
        throw new RuntimeException('ISOLATION_PLATFORM_NOT_REVIEWED');
    }

    $extensionDirectory = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'ext';
    $extensions = ['mbstring', 'gd', 'openssl', 'fileinfo', 'pdo_sqlite'];
    foreach ($extensions as $extension) {
        if (! is_file($extensionDirectory.'/php_'.$extension.'.dll')) {
            throw new RuntimeException('ISOLATION_REQUIRED_EXTENSION_MISSING');
        }
    }

    $temporaryDirectory = sys_get_temp_dir();
    if (! preg_match('/^[A-Za-z]:[\\\\\/]/', $temporaryDirectory) || strcasecmp(substr($temporaryDirectory, 0, 2), substr(PHP_BINARY, 0, 2)) !== 0) {
        throw new RuntimeException('ISOLATION_NONLOCAL_TEMPORARY_DIRECTORY_REJECTED');
    }
    $temporaryDirectory = realpath($temporaryDirectory);
    if ($temporaryDirectory === false) {
        throw new RuntimeException('ISOLATION_TEMPORARY_DIRECTORY_UNRESOLVED');
    }
    IsolationGuard::assertLocalRuntimePath($temporaryDirectory);
    $runtime = rtrim($temporaryDirectory, '\\/').DIRECTORY_SEPARATOR.'familylock-isolation-'.bin2hex(random_bytes(16));
    if (! mkdir($runtime, 0700) || realpath($runtime) === false) {
        throw new RuntimeException('ISOLATION_RUNTIME_CREATION_FAILED');
    }
    $runtime = realpath($runtime);
    IsolationGuard::assertLocalRuntimePath($runtime);

    $environment = array_merge($selectors, [
        'FAMILYLOCK_ISOLATED' => '1',
        'FAMILYLOCK_ISOLATION_MODE' => $mode,
        'FAMILYLOCK_RUNTIME' => $runtime,
        'DB_URL' => '',
        'DATABASE_URL' => '',
        'TEMP' => $runtime,
        'TMP' => $runtime,
        'SystemRoot' => (string) getenv('SystemRoot'),
        'WINDIR' => (string) getenv('WINDIR'),
    ]);

    $command = [PHP_BINARY, '-n'];
    foreach ($extensions as $extension) {
        $command[] = '-d';
        $command[] = 'extension='.$extensionDirectory.'/php_'.$extension.'.dll';
    }
    foreach ([
        'disable_functions' => IsolationGuard::DISABLED_FUNCTIONS,
        'open_basedir' => IsolationGuard::allowedPaths($runtime),
        'allow_url_fopen' => '0',
        'allow_url_include' => '0',
        'ffi.enable' => '0',
        'sys_temp_dir' => $runtime,
        'upload_tmp_dir' => $runtime,
        'session.save_path' => $runtime,
        'memory_limit' => '512M',
        'date.timezone' => 'UTC',
    ] as $name => $value) {
        $command[] = '-d';
        $command[] = $name.'='.($name === 'open_basedir' ? '"'.$value.'"' : $value);
    }
    $command[] = __DIR__.'/child.php';
    if ($filter !== null) {
        $command[] = $filter;
    }

    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, __DIR__, $environment, ['bypass_shell' => true]);
    if (! is_resource($process)) {
        throw new RuntimeException('ISOLATION_CHILD_START_FAILED');
    }

    exit(proc_close($process));
} catch (Throwable) {
    fwrite(STDERR, "ISOLATION_LAUNCH_ABORTED: no se autorizó un arranque seguro.\n");
    exit(1);
}
