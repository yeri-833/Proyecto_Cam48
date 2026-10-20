<?php

namespace Tests\Isolation;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use UnitEnum;
use WeakMap;

final class MemoryOnlyDatabaseManager extends DatabaseManager
{
    /** @var WeakMap<Connection, bool>|null */
    private ?WeakMap $guardedConnections = null;

    public function connection(mixed $name = null): Connection
    {
        IsolationGuard::assertRuntime();
        IsolationGuard::assertDatabaseConfiguration($this->app['config']->get('database'));
        if ($name !== null && $name !== 'sqlite') {
            throw new RuntimeException('ISOLATION_NAMED_CONNECTION_REJECTED');
        }

        $connection = parent::connection($name);
        $this->guardedConnections ??= new WeakMap;
        if (! isset($this->guardedConnections[$connection])) {
            $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
                IsolationGuard::assertRuntime();
                IsolationGuard::assertDatabaseConfiguration($this->app['config']->get('database'));
                if (getenv('FAMILYLOCK_ISOLATION_MODE') !== 'phpunit' || $connection->getDatabaseName() !== ':memory:') {
                    throw new RuntimeException('ISOLATION_QUERY_REJECTED');
                }
            });
            $this->guardedConnections[$connection] = true;
        }

        return $connection;
    }

    /** @param array<string, mixed> $config */
    public function build(array $config): never
    {
        throw new RuntimeException('ISOLATION_DYNAMIC_CONNECTION_REJECTED');
    }

    /** @param array<string, mixed> $config */
    public function connectUsing(UnitEnum|string $name, array $config, bool $force = false): never
    {
        throw new RuntimeException('ISOLATION_DYNAMIC_CONNECTION_REJECTED');
    }
}
