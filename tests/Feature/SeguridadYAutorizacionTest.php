<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\RegistroAsistencia;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class SeguridadYAutorizacionTest extends TestCase
{
    /**
     * Un usuario invitado (sin iniciar sesión) no debe acceder a expedientes escolares.
     */
    public function test_invitado_no_puede_acceder_a_expedientes(): void
    {
        $responseIndex = $this->get('/expedientes');
        $responseIndex->assertRedirect('/login');

        $responseVer = $this->get('/expedientes/9999/ver');
        $responseVer->assertRedirect('/login');

        $responseDescargar = $this->get('/expedientes/9999/descargar');
        $responseDescargar->assertRedirect('/login');
    }

    /**
     * Un usuario con rol normal (docente) no debe tener autorización para eliminar registros (Gate eliminar-registros).
     */
    public function test_docente_no_puede_eliminar_registros_protegidos(): void
    {
        $docente = User::factory()->create(['role' => 'docente']);

        $asistencia = RegistroAsistencia::create([
            'asistible_type' => Alumno::class,
            'asistible_id' => 'TEST_DOC_DEL',
            'fecha' => Carbon::today()->toDateString(),
            'hora' => Carbon::now()->toTimeString(),
        ]);

        $responseHistorial = $this->actingAs($docente)->delete("/historial/{$asistencia->id}");
        $responseHistorial->assertStatus(403);

        $archivo = ArchivoSistema::create([
            'matricula' => 'TEST_DOC_DEL',
            'nombre_original' => 'documento.pdf',
            'nombre_servidor' => 'test_file.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => Carbon::now(),
        ]);

        $responseExpediente = $this->actingAs($docente)->delete("/expedientes/{$archivo->id}");
        $responseExpediente->assertStatus(403);
    }

    /**
     * El alias retirado admin no debe permitir eliminar registros.
     */
    public function test_admin_retirado_no_puede_eliminar_registros(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $asistencia = RegistroAsistencia::create([
            'asistible_type' => Alumno::class,
            'asistible_id' => 'TEST_ADM_DEL',
            'fecha' => Carbon::today()->toDateString(),
            'hora' => Carbon::now()->toTimeString(),
        ]);

        $responseHistorial = $this->actingAs($admin)->delete("/historial/{$asistencia->id}");
        $responseHistorial->assertForbidden();
        $this->assertDatabaseHas('registros_asistencia', ['id' => $asistencia->id]);
    }

    /**
     * El registro público está deshabilitado; los intentos sin autenticación deben redirigir a login.
     */
    public function test_registro_publico_esta_deshabilitado_y_redirige_a_login(): void
    {
        $email = 'nuevo_usuario_'.time().'@cam48.test';

        $response = $this->post('/register', [
            'name' => 'Usuario Aspirante',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tipo_usuario' => 'Administrador', // Intento no autenticado
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('usuarios', ['correo' => $email]);
    }

    /**
     * Las peticiones públicas o no autorizadas a la sincronización de Telegram deben ser rechazadas.
     */
    public function test_peticiones_no_autorizadas_a_telegram_son_bloqueadas(): void
    {
        // 1. Endpoint público API bloqueado
        $resApi = $this->getJson('/api/telegram/sync');
        $resApi->assertStatus(401);

        // 2. Endpoint web requiere autenticación
        $resWeb = $this->get('/telegram/sync');
        $resWeb->assertRedirect('/login');
    }

    /**
     * La actualización de un alumno no debe perder campos como edad, comentarios y fecha de nacimiento.
     */
    public function test_actualizacion_alumno_preserva_todos_los_campos(): void
    {
        $secretaria = User::factory()->create(['tipo_usuario' => 'Secretaria']);

        $alumno = Alumno::firstOrCreate(
            ['matricula' => 'ALUM_CAMPOS_01'],
            [
                'nombre' => 'ROBERTO',
                'apellido' => 'GOMEZ',
                'sexo' => 'M',
                'activo' => 1,
            ]
        );

        $response = $this->actingAs($secretaria)->put("/alumnos/{$alumno->matricula}", [
            'nombre' => 'ROBERTO CARLOS',
            'apellido' => 'GOMEZ BOLAÑOS',
            'sexo' => 'M',
            'nivel_educativo' => 'Primaria',
            'grado' => '3',
            'grupo' => 'A',
            'curp' => 'CURP_FICTICIA_0001',
            'fecha_nacimiento' => '2015-05-20',
            'edad' => 11,
            'comentarios' => 'Alumno con excelente progreso.',
            'discapacidad' => 'Visual leve',
            'activo' => 1,
        ]);

        $response->assertStatus(302);

        $alumno->refresh();
        $this->assertEquals('ROBERTO CARLOS', $alumno->nombre);
        $this->assertEquals('GOMEZ BOLAÑOS', $alumno->apellido);
        $this->assertEquals('2015-05-20', $alumno->fecha_nacimiento);
        $this->assertEquals(11, $alumno->edad);
        $this->assertEquals('Alumno con excelente progreso.', $alumno->comentarios);
    }
}
