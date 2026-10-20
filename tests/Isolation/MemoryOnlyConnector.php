<?php

namespace Tests\Isolation;

use Illuminate\Database\Connectors\SQLiteConnector;
use PDO;
use RuntimeException;

final class MemoryOnlyConnector extends SQLiteConnector
{
    /** @param array<string, mixed> $config */
    public function connect(array $config): PDO
    {
        IsolationGuard::assertRuntime();
        IsolationGuard::assertDatabaseConfiguration(['default' => 'sqlite', 'connections' => ['sqlite' => $config]]);
        if (getenv('FAMILYLOCK_ISOLATION_MODE') !== 'phpunit') {
            throw new RuntimeException('ISOLATION_ANALYSIS_CONNECTION_FORBIDDEN');
        }

        return parent::connect($config);
    }
}
