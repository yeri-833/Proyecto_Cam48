<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Isolation\DestructiveTestGuard;

class DestructiveTestGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(DestructiveTestGuard::ENV_AUTHORIZATION);
        putenv('FAMILYLOCK_TEST_BYPASS_SANDBOX_CHECK');
        parent::tearDown();
    }

    public function test_rechaza_ejecucion_fuera_de_windows_sandbox(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AISLAMIENTO NO DEMOSTRADO');

        DestructiveTestGuard::assertSandboxEnvironment(
            'C:\\Users\\UsuarioHost\\Desktop\\Proyecto_Cam48',
            'C:\\NonExistentInput'
        );
    }

    public function test_acepta_ejecucion_con_marcadores_reales_de_windows_sandbox(): void
    {
        // En Windows Sandbox C:\FamilyLockInput existe por diseño del contenedor
        $inputDir = @is_dir('C:\\FamilyLockInput') ? 'C:\\FamilyLockInput' : __DIR__;

        DestructiveTestGuard::assertSandboxEnvironment('C:\\Project', $inputDir);
        $this->addToAssertionCount(1);
    }

    public function test_rechaza_ejecucion_sin_autorizacion(): void
    {
        putenv(DestructiveTestGuard::ENV_AUTHORIZATION);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPERACIÓN DESTRUCTIVA BLOQUEADA');

        DestructiveTestGuard::assertExecutionAuthorized([]);
    }

    public function test_acepta_ejecucion_con_variable_de_entorno(): void
    {
        putenv(DestructiveTestGuard::ENV_AUTHORIZATION.'=1');
        DestructiveTestGuard::assertExecutionAuthorized([]);

        $this->addToAssertionCount(1);
    }

    public function test_acepta_ejecucion_con_flag_cli(): void
    {
        putenv(DestructiveTestGuard::ENV_AUTHORIZATION);
        DestructiveTestGuard::assertExecutionAuthorized(['--foo', '--authorize-destructive-tests', '--bar']);

        $this->addToAssertionCount(1);
    }

    public function test_rechaza_host_remoto_o_externo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HOST NO AUTORIZADO');

        DestructiveTestGuard::assertSafeConnection('192.168.1.100', 3307);
    }

    public function test_rechaza_puerto_3306_estricto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PUERTO BLOQUEADO');

        DestructiveTestGuard::assertSafeConnection('127.0.0.1', 3306);
    }

    public function test_rechaza_puerto_no_autorizado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PUERTO NO AUTORIZADO');

        DestructiveTestGuard::assertSafeConnection('127.0.0.1', 5432);
    }

    public function test_acepta_host_loopback_y_puerto_aislado(): void
    {
        DestructiveTestGuard::assertSafeConnection('127.0.0.1', 3307);
        DestructiveTestGuard::assertSafeConnection('localhost', 3307);
        DestructiveTestGuard::assertSafeConnection('::1', 3307);

        $this->addToAssertionCount(3);
    }

    public function test_rechaza_nombres_de_bases_de_datos_bloqueadas_o_del_sistema(): void
    {
        $bloqueadas = ['mysql', 'familylock_db', 'proyecto_colegio', 'production', 'sys'];

        foreach ($bloqueadas as $db) {
            try {
                DestructiveTestGuard::assertSafeDatabaseName($db);
                $this->fail("Debió rechazar la base de datos de sistema o producción: {$db}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('BASE DE DATOS BLOQUEADA', $e->getMessage());
            }
        }
    }

    public function test_rechaza_bases_de_datos_fuera_de_whitelist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BASE DE DATOS NO PERMITIDA');

        DestructiveTestGuard::assertSafeDatabaseName('alumnos_db_random');
    }

    public function test_acepta_bases_de_datos_en_whitelist(): void
    {
        foreach (DestructiveTestGuard::ALLOWED_DATABASES as $db) {
            DestructiveTestGuard::assertSafeDatabaseName($db);
        }

        DestructiveTestGuard::assertSafeDatabaseName('familylock_test_custom');

        $this->addToAssertionCount(count(DestructiveTestGuard::ALLOWED_DATABASES) + 1);
    }

    public function test_guard_integral_valida_correctamente_operacion_segura(): void
    {
        putenv(DestructiveTestGuard::ENV_AUTHORIZATION.'=1');
        putenv('FAMILYLOCK_TEST_BYPASS_SANDBOX_CHECK=1');

        DestructiveTestGuard::guardDatabaseOperation('127.0.0.1', 3307, 'familylock_real_fresh', []);

        $this->addToAssertionCount(1);
    }
}
