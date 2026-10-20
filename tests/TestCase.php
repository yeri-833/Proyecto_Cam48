<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Isolation\IsolatedApplication;
use Tests\Isolation\IsolationGuard;
use Tests\Isolation\SyntheticSchema;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        IsolationGuard::assertRuntime();
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        foreach (['RefreshDatabase', 'DatabaseMigrations', 'DatabaseTruncation', 'DatabaseTransactions', 'WithCachedConfig', 'WithCachedRoutes'] as $trait) {
            if (isset($this->traitsUsedByTest['Illuminate\\Foundation\\Testing\\'.$trait])) {
                throw new RuntimeException('ISOLATION_UNREVIEWED_TEST_TRAIT_REJECTED');
            }
        }

        $app = IsolatedApplication::make();
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        IsolationGuard::assertRuntime();
        IsolationGuard::assertDatabaseConfiguration($this->app['config']->get('database'));
        $this->withoutVite();
        SyntheticSchema::create($this->app['db']->connection('sqlite'));
    }
}
