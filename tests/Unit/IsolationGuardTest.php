<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Isolation\IsolationGuard;

class IsolationGuardTest extends TestCase
{
    public function test_acepta_solo_los_selectores_explicitos_de_prueba(): void
    {
        IsolationGuard::assertEnvironment($this->entornoSeguro());

        $this->addToAssertionCount(1);
    }

    #[DataProvider('variablesInseguras')]
    public function test_rechaza_desvios_y_credenciales_antes_del_bootstrap(string $nombre, string $valor): void
    {
        $entorno = array_merge($this->entornoSeguro(), [$nombre => $valor]);
        $this->expectException(RuntimeException::class);

        IsolationGuard::assertEnvironment($entorno);
    }

    /** @return array<string, array{string, string}> */
    public static function variablesInseguras(): array
    {
        return [
            'sin autorizacion' => ['FAMILYLOCK_ISOLATED', ''],
            'produccion' => ['APP_ENV', 'production'],
            'otro motor' => ['DB_CONNECTION', 'mariadb'],
            'sqlite persistente' => ['DB_DATABASE', 'synthetic.sqlite'],
            'url' => ['DB_URL', 'mysql://fictional:secret@invalid/synthetic'],
            'url alternativa' => ['DATABASE_URL', 'sqlite:///synthetic.sqlite'],
            'credencial' => ['DB_PASSWORD', 'secreto-ficticio'],
            'conexion secundaria' => ['DB_SECONDARY_HOST', 'fictional.invalid'],
            'cache heredada' => ['APP_CONFIG_CACHE', 'synthetic.php'],
            'token externo' => ['TELEGRAM_BOT_TOKEN', 'token-ficticio'],
            'clave heredada' => ['APP_KEY', 'clave-ficticia'],
        ];
    }

    public function test_rechaza_selectores_ausentes(): void
    {
        $entorno = $this->entornoSeguro();
        unset($entorno['DB_DATABASE']);
        $this->expectException(RuntimeException::class);

        IsolationGuard::assertEnvironment($entorno);
    }

    public function test_configuracion_secundaria_se_deniega_aunque_su_nombre_contenga_test(): void
    {
        $this->expectException(RuntimeException::class);

        IsolationGuard::assertDatabaseConfiguration([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'mariadb_test' => ['driver' => 'mariadb', 'database' => 'synthetic_test'],
            ],
        ]);
    }

    public function test_se_deniegan_parametros_de_lectura_y_escritura_secundarios(): void
    {
        $this->expectException(RuntimeException::class);

        IsolationGuard::assertDatabaseConfiguration([
            'default' => 'sqlite',
            'connections' => ['sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'read' => ['database' => 'synthetic.sqlite'],
            ]],
        ]);
    }

    #[DataProvider('rutasNoLocales')]
    public function test_rechaza_rutas_temporales_de_red_o_relativas(string $ruta): void
    {
        $this->expectException(RuntimeException::class);

        IsolationGuard::assertLocalRuntimePath($ruta);
    }

    /** @return array<string, array{string}> */
    public static function rutasNoLocales(): array
    {
        return [
            'unc' => ['\\\\fictional.invalid\\share\\runtime'],
            'unc con barras' => ['//fictional.invalid/share/runtime'],
            'relativa' => ['runtime'],
            'relativa a unidad' => ['C:runtime'],
            'dispositivo' => ['\\\\?\\C:\\runtime'],
        ];
    }

    /** @return array<string, string> */
    private function entornoSeguro(): array
    {
        return [
            'FAMILYLOCK_ISOLATED' => '1',
            'FAMILYLOCK_ISOLATION_MODE' => 'phpunit',
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ];
    }
}
