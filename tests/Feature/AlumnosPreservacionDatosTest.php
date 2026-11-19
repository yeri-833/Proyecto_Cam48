<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use App\Services\FamiliaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlumnosPreservacionDatosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
    }

    public function test_dashboard_incluye_campos_completos_del_alumno(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_DASH_01',
            'nombre' => 'Alumno',
            'apellido' => 'Dashboard',
            'sexo' => 'M',
            'curp' => 'FICT123456HDASH01',
            'fecha_nacimiento' => '2016-05-15',
            'edad' => 10,
            'nivel_educativo' => 'Primaria',
            'grado' => '4',
            'grupo' => 'A',
            'discapacidad' => 'Visual',
            'comentarios' => 'Observación del expediente',
            'foto_perfil' => 'fotos/alumnos/ficticio.jpg',
            'activo' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('alumnos.data', 1)
            ->where('alumnos.data.0.matricula', 'FICT_DASH_01')
            ->where('alumnos.data.0.curp', 'FICT123456HDASH01')
            ->where('alumnos.data.0.fecha_nacimiento', '2016-05-15')
            ->where('alumnos.data.0.edad', 10)
            ->where('alumnos.data.0.comentarios', 'Observación del expediente')
            ->where('alumnos.data.0.foto_perfil', 'fotos/alumnos/ficticio.jpg')
        );
    }

    public function test_actualizacion_parcial_del_nombre_preserva_curp_nacimiento_edad_y_comentarios(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_PRESERV_01',
            'nombre' => 'Nombre Original',
            'apellido' => 'Apellido Original',
            'sexo' => 'M',
            'curp' => 'FICT999999HDFR01',
            'fecha_nacimiento' => '2015-08-20',
            'edad' => 11,
            'nivel_educativo' => 'Primaria',
            'grado' => '5',
            'grupo' => 'B',
            'discapacidad' => 'Motriz',
            'comentarios' => 'Comentarios existentes intocables',
            'activo' => 1,
        ]);

        $this->actingAs($admin)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Nombre Actualizado',
            'apellido' => 'Apellido Actualizado',
            'nivel_educativo' => 'Primaria',
            'grado' => '5',
            'grupo' => 'B',
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('NOMBRE ACTUALIZADO', $alumno->nombre);
        $this->assertSame('APELLIDO ACTUALIZADO', $alumno->apellido);
        $this->assertSame('FICT999999HDFR01', $alumno->curp);
        $this->assertSame('2015-08-20', substr((string) $alumno->fecha_nacimiento, 0, 10));
        $this->assertSame(11, $alumno->edad);
        $this->assertSame('Comentarios existentes intocables', $alumno->comentarios);
        $this->assertSame('Motriz', $alumno->discapacidad);
    }

    public function test_valores_vacios_artificiales_no_borran_datos_existentes(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_PRESERV_02',
            'nombre' => 'Alumno',
            'apellido' => 'Sensible',
            'curp' => 'FICT888888HDFR02',
            'fecha_nacimiento' => '2014-03-10',
            'edad' => 12,
            'comentarios' => 'Historial médico relevante',
            'discapacidad' => 'Auditiva',
            'grado' => '6',
            'grupo' => 'A',
            'nivel_educativo' => 'Primaria',
            'activo' => 1,
        ]);

        $this->actingAs($admin)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Alumno Editado',
            'apellido' => 'Sensible',
            'curp' => '',
            'fecha_nacimiento' => '',
            'edad' => '',
            'comentarios' => '',
            'discapacidad' => '',
            'grado' => '6',
            'grupo' => 'A',
            'nivel_educativo' => 'Primaria',
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('ALUMNO EDITADO', $alumno->nombre);
        $this->assertSame('FICT888888HDFR02', $alumno->curp);
        $this->assertSame('2014-03-10', substr((string) $alumno->fecha_nacimiento, 0, 10));
        $this->assertSame(12, $alumno->edad);
        $this->assertSame('Historial médico relevante', $alumno->comentarios);
        $this->assertSame('Auditiva', $alumno->discapacidad);
    }

    public function test_actualizacion_explicita_modifica_los_campos(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_PRESERV_03',
            'nombre' => 'Alumno',
            'apellido' => 'Para Actualizar',
            'curp' => 'VIEJA123456HDFR00',
            'edad' => 9,
            'activo' => 1,
        ]);

        $this->actingAs($admin)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Alumno',
            'apellido' => 'Actualizado',
            'curp' => 'NUEVA123456HDFR00',
            'fecha_nacimiento' => '2017-01-01',
            'edad' => 10,
            'comentarios' => 'Nuevas observaciones',
            'discapacidad' => 'Ninguna',
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('NUEVA123456HDFR00', $alumno->curp);
        $this->assertSame('2017-01-01', substr((string) $alumno->fecha_nacimiento, 0, 10));
        $this->assertSame(10, $alumno->edad);
        $this->assertSame('Nuevas observaciones', $alumno->comentarios);
        $this->assertSame('Ninguna', $alumno->discapacidad);
    }

    public function test_borrado_explicito_con_bandera_elimina_campos_solicitados(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_PRESERV_04',
            'nombre' => 'Alumno',
            'apellido' => 'Borrado Explicito',
            'curp' => 'BORRAR123456HDFR0',
            'comentarios' => 'Comentario a eliminar',
            'edad' => 10,
            'activo' => 1,
        ]);

        $this->actingAs($admin)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Alumno',
            'apellido' => 'Borrado Explicito',
            'borrar_comentarios' => true,
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertNull($alumno->comentarios);
        $this->assertSame('BORRAR123456HDFR0', $alumno->curp);
        $this->assertSame(10, $alumno->edad);
    }

    public function test_actualizacion_sin_foto_preserva_foto_perfil_existente_y_asistencias(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_PRESERV_FOTO',
            'nombre' => 'Alumno',
            'apellido' => 'Con Foto',
            'foto_perfil' => 'fotos/alumnos/perfil_original.jpg',
            'activo' => 1,
        ]);

        RegistroAsistencia::create([
            'asistible_type' => Alumno::class,
            'asistible_id' => $alumno->matricula,
            'fecha' => now()->toDateString(),
            'hora' => '08:00:00',
        ]);

        $this->actingAs($admin)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Alumno Renombrado',
            'apellido' => 'Con Foto',
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('ALUMNO RENOMBRADO', $alumno->nombre);
        $this->assertSame('fotos/alumnos/perfil_original.jpg', $alumno->foto_perfil, 'La foto no debe ser alterada');
        $this->assertSame(1, RegistroAsistencia::where('asistible_id', $alumno->matricula)->count(), 'El historial de asistencia debe permanecer intacto');
    }

    public function test_desvinculacion_de_tutor_preserva_historial_y_actualiza_clave_familiar(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'ALU_DESVINC_01',
            'nombre' => 'David',
            'apellido' => 'Cruz',
            'activo' => 1,
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Monica Cruz',
            't1_telefono' => '9215551122',
        ]);

        DB::table('alumno_tutor')->insert([
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno->matricula,
            'es_tutor_principal' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar asistencia tanto para el alumno como para el tutor
        RegistroAsistencia::create([
            'asistible_type' => Alumno::class,
            'asistible_id' => $alumno->matricula,
            'fecha' => now()->toDateString(),
            'hora' => '08:10:00',
        ]);
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => now()->toDateString(),
            'hora' => '08:15:00',
        ]);

        $familiaService = new FamiliaService;
        $this->assertSame('FAM:ALU_DESVINC_01', $familiaService->obtenerClaveFamilia($tutor));

        // Desvincular alumno del tutor
        $this->actingAs($admin)->delete(route('tutores.desvincularAlumno', [
            'id' => $tutor->id,
            'matricula' => $alumno->matricula,
        ]))->assertRedirect();

        // 1. Historial del alumno se conserva
        $this->assertSame(1, RegistroAsistencia::where('asistible_type', Alumno::class)->where('asistible_id', $alumno->matricula)->count());

        // 2. Historial del tutor se conserva
        $this->assertSame(1, RegistroAsistencia::where('asistible_type', Tutor::class)->where('asistible_id', (string) $tutor->id)->count());

        // 3. Clave familiar se actualiza (el tutor ahora es independiente o sin alumnos)
        $tutor->refresh();
        $nuevaClave = $familiaService->obtenerClaveFamilia($tutor);
        $this->assertNotSame('FAM:ALU_DESVINC_01', $nuevaClave);
        $this->assertStringStartsWith('FAM-ISOLATED:', $nuevaClave);
    }
}
