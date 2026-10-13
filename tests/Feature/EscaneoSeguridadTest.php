<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\User;
use Tests\TestCase;

class EscaneoSeguridadTest extends TestCase
{
    /**
     * Prueba que el escáner rechaza QRs sin firma.
     */
    public function test_rechaza_qr_sin_firma(): void
    {
        $qrDataInvalido = json_encode([
            'id' => '12345',
            'rol' => 'Alumno',
        ]);

        $user = User::first() ?? User::factory()->create(['role' => 'operador']);

        $response = $this->actingAs($user)->postJson('/escaner/procesar', [
            'qr_data' => $qrDataInvalido,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'error',
                'msg' => 'Código QR inválido o falsificado (Firma incorrecta)',
            ]);
    }

    /**
     * Prueba que el escáner rechaza QRs con firma falsificada.
     */
    public function test_rechaza_qr_con_firma_falsa(): void
    {
        $qrDataInvalido = json_encode([
            'id' => '12345',
            'rol' => 'Alumno',
            'signature' => 'firmafalsainventada123',
        ]);

        $user = User::first() ?? User::factory()->create(['role' => 'operador']);
        $response = $this->actingAs($user)->postJson('/escaner/procesar', [
            'qr_data' => $qrDataInvalido,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'error',
                'msg' => 'Código QR inválido o falsificado (Firma incorrecta)',
            ]);
    }

    /**
     * Prueba que el escáner acepta un QR con firma auténtica generada por la app.
     */
    public function test_acepta_qr_autentico(): void
    {
        // Simulamos un ID de alumno que puede o no existir en BD para esta prueba
        // Solo nos interesa la validación criptográfica inicial.
        $matricula = 'TEST001';
        $rol = 'Alumno';

        $firmaAutentica = hash_hmac('sha256', $matricula.$rol, config('app.key'));

        $qrDataAutentico = json_encode([
            'id' => $matricula,
            'rol' => $rol,
            'signature' => $firmaAutentica,
        ]);

        $user = User::first() ?? User::factory()->create(['role' => 'operador']);
        $response = $this->actingAs($user)->postJson('/escaner/procesar', [
            'qr_data' => $qrDataAutentico,
        ]);

        $response->assertStatus(200);
        // Debe fallar solo porque el alumno no existe en la BD (lo cual prueba que la capa HMAC la pasó)
        $this->assertStringContainsString('no existe', $response->json('msg') ?? '');
    }
}
