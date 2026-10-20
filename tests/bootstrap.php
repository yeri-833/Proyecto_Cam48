<?php

use Tests\Isolation\IsolationGuard;

require_once __DIR__.'/Isolation/IsolationGuard.php';

try {
    IsolationGuard::assertRuntime();
} catch (Throwable) {
    fwrite(STDERR, "ISOLATION_BOOTSTRAP_REJECTED\n");
    exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';
