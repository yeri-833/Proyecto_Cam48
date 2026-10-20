<?php

use Tests\Isolation\IsolationGuard;

require_once __DIR__.'/IsolationGuard.php';

try {
    IsolationGuard::assertRuntime();
} catch (Throwable) {
    fwrite(STDERR, "ISOLATION_PREFLIGHT_REJECTED\n");
    exit(1);
}

$root = IsolationGuard::projectPath();
$runtime = IsolationGuard::runtimePath();
$filter = $argv[1] ?? null;
require $root.'/tests/bootstrap.php';

if (getenv('FAMILYLOCK_ISOLATION_MODE') === 'phpunit') {
    $tool = $root.'/vendor/phpunit/phpunit/phpunit';
    $arguments = [$tool, '--configuration', $root.'/phpunit.xml', '--cache-directory', $runtime.'/phpunit', '--do-not-cache-result'];
    if ($filter !== null) {
        array_push($arguments, '--filter', $filter);
    }
} else {
    $configuration = "includes:\n    - ".json_encode($root.'/phpstan-isolated.neon')."\nparameters:\n    tmpDir: ".json_encode($runtime.'/phpstan')."\n";
    if (file_put_contents($runtime.'/phpstan.neon', $configuration) === false) {
        fwrite(STDERR, "ISOLATION_ANALYSIS_CONFIGURATION_FAILED\n");
        exit(1);
    }
    $tool = $root.'/vendor/phpstan/phpstan/phpstan';
    $arguments = [$tool, 'analyse', '--configuration', $runtime.'/phpstan.neon', '--debug', '--no-progress', '--memory-limit=512M'];
}

$argv = $_SERVER['argv'] = $arguments;
$argc = $_SERVER['argc'] = count($arguments);
require $tool;
