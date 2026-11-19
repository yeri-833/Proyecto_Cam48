<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\RegistroAsistencia;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class AsistenciaFlujoCompletoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Prueba el flujo completo de validación de QR, registro de asistencia y cooldown.
     */
    public function test_registro_asistencia_alumno_exitoso_y_cooldown(): void
    {
        $user = User::factory()->create(['role' => 'Director']);

        // Crear o buscar alumno de prueba
        $alumno = Alumno::firstOrCreate(
            ['matricula' => 'TEST_ALUM_01'],
            [
                'nombre' => 'JUAN',
                'apellido' => 'PEREZ',
                'sexo' => 'M',
                'activo' => 1,
            ]
        );

        // Limpiar asistencias previas de prueba de hoy
        RegistroAsistencia::where('asistible_type', Alumno::class)
            ->where('asistible_id', $alumno->matricula)
            ->whereDate('fecha', Carbon::today())
            ->delete();

        // 1. Primer escaneo auténtico
        $sig = hash_hmac('sha256', $alumno->matricula.'Alumno', config('app.key'));
        $qrData = json_encode([
            'id' => $alumno->matricula,
            'rol' => 'Alumno',
            'signature' => $sig,
        ]);

        $res1 = $this->actingAs($user)->postJson('/escaner/procesar', ['qr_data' => $qrData]);
        $res1->assertStatus(200);
        $this->assertEquals('success', $res1->json('status'));

        // 2. Segundo escaneo inmediato (debe activar Cooldown)
        $res2 = $this->actingAs($user)->postJson('/escaner/procesar', ['qr_data' => $qrData]);
        $res2->assertStatus(200);
        $this->assertEquals('warning', $res2->json('status'));
        $this->assertStringContainsString('recientemente', $res2->json('msg'));
    }

    /**
     * Prueba que un alumno inactivo no pueda registrar asistencia.
     */
    public function test_rechaza_alumno_inactivo(): void
    {
        $user = User::factory()->create(['role' => 'Director']);

        $alumno = Alumno::firstOrCreate(
            ['matricula' => 'TEST_INACTIVO_01'],
            [
                'nombre' => 'PEDRO',
                'apellido' => 'BAJA',
                'sexo' => 'M',
                'activo' => 0,
            ]
        );
        $alumno->activo = 0;
        $alumno->save();

        $sig = hash_hmac('sha256', $alumno->matricula.'Alumno', config('app.key'));
        $qrData = json_encode([
            'id' => $alumno->matricula,
            'rol' => 'Alumno',
            'signature' => $sig,
        ]);

        $res = $this->actingAs($user)->postJson('/escaner/procesar', ['qr_data' => $qrData]);
        $res->assertStatus(200);
        $this->assertEquals('error', $res->json('status'));
        $this->assertStringContainsString('inactivo', $res->json('msg'));
    }

    /**
     * Prueba que las rutas de Historial y Estadísticas respondan HTTP 200 sin errores de esquema.
     */
    public function test_vistas_historial_y_estadisticas_cargan_correctamente(): void
    {
        $user = User::factory()->create(['role' => 'Secretaria']);

        $resHistorial = $this->actingAs($user)->get('/historial');
        $resHistorial->assertStatus(200);

        $resEstadisticas = $this->actingAs($user)->get('/estadisticas');
        $resEstadisticas->assertStatus(200);

        $resCredenciales = $this->actingAs($user)->get('/credenciales');
        $resCredenciales->assertStatus(200);
    }
}
