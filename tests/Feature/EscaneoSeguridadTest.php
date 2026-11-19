<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EscaneoSeguridadTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function perfilesEscaner(): array
    {
        return ['director' => ['Director'], 'operador' => ['Operador']];
    }

    /**
     * Prueba que el escáner rechaza QRs sin firma.
     */
    #[DataProvider('perfilesEscaner')]
    public function test_rechaza_qr_sin_firma(string $perfil): void
    {
        Alumno::create(['matricula' => '12345', 'nombre' => 'Alumno', 'apellido' => 'Sintetico', 'activo' => 1]);
        $qrDataInvalido = json_encode([
            'id' => '12345',
            'rol' => 'Alumno',
        ]);

        $user = User::factory()->create(['role' => $perfil]);

        $response = $this->actingAs($user)->postJson('/escaner/procesar', [
            'qr_data' => $qrDataInvalido,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'error',
                'msg' => $perfil === 'Operador'
                    ? 'Registro no realizado. Revise el código o solicite apoyo.'
                    : 'Código QR inválido o falsificado (Firma incorrecta)',
            ]);
        $this->assertDatabaseCount('registros_asistencia', 0);
        if ($perfil === 'Operador') {
            $this->assertEqualsCanonicalizing(['status', 'msg', 'hora'], array_keys($response->json()));
            $response->assertDontSee('12345')->assertDontSee('Sintetico');
        }
    }

    /**
     * Prueba que el escáner rechaza QRs con firma falsificada.
     */
    #[DataProvider('perfilesEscaner')]
    public function test_rechaza_qr_con_firma_falsa(string $perfil): void
    {
        Alumno::create(['matricula' => '12345', 'nombre' => 'Alumno', 'apellido' => 'Sintetico', 'activo' => 1]);
        $qrDataInvalido = json_encode([
            'id' => '12345',
            'rol' => 'Alumno',
            'signature' => 'firmafalsainventada123',
        ]);

        $user = User::factory()->create(['role' => $perfil]);
        $response = $this->actingAs($user)->postJson('/escaner/procesar', [
            'qr_data' => $qrDataInvalido,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'error',
                'msg' => $perfil === 'Operador'
                    ? 'Registro no realizado. Revise el código o solicite apoyo.'
                    : 'Código QR inválido o falsificado (Firma incorrecta)',
            ]);
        $this->assertDatabaseCount('registros_asistencia', 0);
        if ($perfil === 'Operador') {
            $this->assertEqualsCanonicalizing(['status', 'msg', 'hora'], array_keys($response->json()));
            $response->assertDontSee('12345')->assertDontSee('Sintetico');
        }
    }

    /**
     * Prueba que el escáner acepta un QR con firma auténtica generada por la app.
     */
    #[DataProvider('perfilesEscaner')]
    public function test_acepta_qr_autentico(string $perfil): void
    {
        $matricula = 'TEST001';
        $rol = 'Alumno';
        Alumno::create(['matricula' => $matricula, 'nombre' => 'Alumno', 'apellido' => 'Sintetico', 'activo' => 1]);

        $firmaAutentica = hash_hmac('sha256', $matricula.$rol, config('app.key'));

        $qrDataAutentico = json_encode([
            'id' => $matricula,
            'rol' => $rol,
            'signature' => $firmaAutentica,
        ]);

        $user = User::factory()->create(['role' => $perfil]);
        $response = $this->actingAs($user)->postJson('/escaner/procesar', [
            'qr_data' => $qrDataAutentico,
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('registros_asistencia', 1);
        $this->assertDatabaseHas('registros_asistencia', ['asistible_type' => Alumno::class, 'asistible_id' => $matricula]);
        if ($perfil === 'Operador') {
            $this->assertEqualsCanonicalizing(['status', 'msg', 'hora'], array_keys($response->json()));
            $response->assertDontSee($matricula)->assertDontSee('Sintetico');
        }
    }
}
