<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Personal;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlumnosAutorizacionTest extends TestCase
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

    #[DataProvider('rolesAdministrativos')]
    public function test_administrador_puede_actualizar_datos_y_asignacion_del_alumno(string $rol): void
    {
        $admin = User::factory()->create(['role' => $rol]);
        $alumno = $this->crearAlumno();

        $this->actingAs($admin)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Nombre corregido',
            'apellido' => 'Apellido ficticio',
            'grado' => '2',
            'grupo' => 'B',
            'nivel_educativo' => 'Primaria',
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('NOMBRE CORREGIDO', $alumno->nombre);
        $this->assertSame('2', $alumno->grado);
        $this->assertSame('B', $alumno->grupo);
        $this->assertSame(10, $alumno->edad);
    }

    #[DataProvider('rolesDocentes')]
    public function test_docente_edita_datos_de_su_alumno_sin_perder_campos_omitidos(string $rol): void
    {
        $docente = $this->crearPersonal($rol, '1° A');
        $alumno = $this->crearAlumno();

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Nombre corregido',
            'apellido' => 'Apellido ficticio',
            'comentarios' => 'Observación ficticia',
            'grado' => '1',
            'grupo' => 'A',
            'nivel_educativo' => 'Primaria',
            'activo' => 1,
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('NOMBRE CORREGIDO', $alumno->nombre);
        $this->assertSame('Observación ficticia', $alumno->comentarios);
        $this->assertSame('1', $alumno->grado);
        $this->assertSame('A', $alumno->grupo);
        $this->assertSame(10, $alumno->edad);
        $this->assertTrue($alumno->activo);
    }

    public function test_docente_no_actualiza_alumno_ajeno_ni_adquiere_acceso_a_su_expediente(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno(['grado' => '2']);
        $antes = $alumno->refresh()->getAttributes();
        $archivo = ArchivoSistema::create([
            'matricula' => $alumno->matricula,
            'nombre_original' => 'documento_ficticio.pdf',
            'nombre_servidor' => 'documento_ficticio.pdf',
            'tamano_bytes' => 1,
            'fecha_subida' => now(),
        ]);

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
            'grado' => '1',
            'grupo' => 'A',
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
        $this->get(route('expedientes.ver', $archivo->id))->assertForbidden();
        $this->get(route('expedientes.descargar', $archivo->id))->assertForbidden();
    }

    #[DataProvider('cambiosDeAsignacion')]
    public function test_docente_no_puede_cambiar_campos_de_asignacion(string $campo, string|int $valor): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno();
        $antes = $alumno->refresh()->getAttributes();

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
            $campo => $valor,
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
    }

    /** @return array<string, array{string, string|int}> */
    public static function cambiosDeAsignacion(): array
    {
        return [
            'grado' => ['grado', '2'],
            'grupo' => ['grupo', 'B'],
            'nivel' => ['nivel_educativo', 'Secundaria'],
            'estado' => ['activo', 0],
            'matricula' => ['matricula', 'FICT_OTRA'],
            'rol' => ['rol', 'Administrador'],
        ];
    }

    #[DataProvider('personasSinPermisoDeEdicion')]
    public function test_edicion_se_deniega_sin_rol_y_asignacion_verificables(string $rol, ?string $asignacion, bool $activo): void
    {
        $usuario = $this->crearPersonal($rol, $asignacion, $activo);
        $alumno = $this->crearAlumno();
        $antes = $alumno->refresh()->getAttributes();

        $this->actingAs($usuario)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
    }

    /** @return array<string, array{string, ?string, bool}> */
    public static function personasSinPermisoDeEdicion(): array
    {
        return [
            'docente sin grupo' => ['Docente', null, true],
            'docente ambiguo' => ['Docente', 'A', true],
            'docente inactivo' => ['Docente', '1 A', false],
            'personal sin grupo' => ['Personal', null, true],
            'personal con alcance no definido' => ['Personal', '1 A', true],
            'administrativo sin facultades definidas' => ['Administrativo', '1 A', true],
            'kiosco' => ['Operador', '1 A', true],
        ];
    }

    public function test_actualizacion_denegada_no_procesa_ni_elimina_fotografias(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno(['foto_perfil' => 'fotos/alumnos/ficticia.jpg']);
        $antes = $alumno->refresh()->getAttributes();
        Storage::disk('public')->put($alumno->foto_perfil, 'imagen ficticia preservada');
        $this->mock(ImageUploadService::class)->shouldNotReceive('procesarYGuardar');

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
            'grupo' => 'B',
            'foto_perfil' => UploadedFile::fake()->image('reemplazo.jpg'),
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
        $this->assertSame('imagen ficticia preservada', Storage::disk('public')->get('fotos/alumnos/ficticia.jpg'));
        $this->assertSame(['fotos/alumnos/ficticia.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_no_se_puede_crear_un_alumno_para_asignarlo_sin_facultades_administrativas(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');

        $this->actingAs($docente)->post(route('alumnos.store'), [
            'matricula' => 'FICT_ALTA',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
            'grado' => '2',
            'grupo' => 'B',
        ])->assertForbidden();

        $this->assertDatabaseMissing('alumnos', ['matricula' => 'FICT_ALTA']);
    }

    public function test_docente_sin_ficha_de_personal_no_modifica_alumnos(): void
    {
        $docente = User::factory()->create(['role' => 'Docente']);
        $alumno = $this->crearAlumno();
        $antes = $alumno->refresh()->getAttributes();

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
    }

    public function test_payload_del_formulario_preserva_la_asignacion_y_el_sexo_originales(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno(['nivel_educativo' => 'PRIMARIA', 'sexo' => 'F']);

        $this->actingAs($docente)->post(route('alumnos.update', $alumno->matricula), [
            '_method' => 'put',
            'matricula' => $alumno->matricula,
            'nombre' => 'Nombre corregido',
            'apellido' => $alumno->apellido,
            'sexo' => 'F',
            'nivel_educativo' => 'PRIMARIA',
            'grado' => '1',
            'grupo' => 'A',
            'foto_perfil' => null,
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('NOMBRE CORREGIDO', $alumno->nombre);
        $this->assertSame('PRIMARIA', $alumno->nivel_educativo);
        $this->assertSame('F', $alumno->sexo);
    }

    public function test_recorte_del_transporte_no_reasigna_campos_legacy_espaciados(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno([
            'nivel_educativo' => ' PRIMARIA ',
            'grado' => ' 1° ',
            'grupo' => ' a ',
        ]);

        $this->actingAs($docente)->post(route('alumnos.update', $alumno->matricula), [
            '_method' => 'put',
            'matricula' => $alumno->matricula,
            'nombre' => 'Nombre corregido',
            'apellido' => $alumno->apellido,
            'nivel_educativo' => ' PRIMARIA ',
            'grado' => ' 1° ',
            'grupo' => ' a ',
        ])->assertRedirect();

        $alumno->refresh();
        $this->assertSame('NOMBRE CORREGIDO', $alumno->nombre);
        $this->assertSame(' PRIMARIA ', $alumno->nivel_educativo);
        $this->assertSame(' 1° ', $alumno->grado);
        $this->assertSame(' a ', $alumno->grupo);
    }

    public function test_asignaciones_duplicadas_impiden_actualizar_alumnos(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        Personal::create([
            'id' => 9901,
            'nombre_completo' => 'Segunda ficha ficticia',
            'email' => $docente->email,
            'grado_grupo' => '2 B',
            'activo' => 1,
        ]);
        $alumno = $this->crearAlumno();
        $antes = $alumno->refresh()->getAttributes();

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
    }

    public function test_docente_no_modifica_alumno_inactivo_de_su_grupo(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno(['activo' => 0]);
        $antes = $alumno->refresh()->getAttributes();

        $this->actingAs($docente)->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
    }

    public function test_reasignacion_se_deniega_antes_de_validar_campos_obligatorios(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $alumno = $this->crearAlumno();
        $antes = $alumno->refresh()->getAttributes();

        $this->actingAs($docente)->putJson(route('alumnos.update', $alumno->matricula), [
            'grupo' => 'B',
        ])->assertForbidden();

        $this->assertSame($antes, $alumno->refresh()->getAttributes());
    }

    public function test_docente_no_puede_adoptar_la_asignacion_de_otra_ficha_cambiando_su_correo(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $correoOriginal = $docente->email;
        Personal::create([
            'id' => 9902,
            'nombre_completo' => 'Personal ajeno ficticio',
            'email' => 'otra.ficha@cam48.test',
            'grado_grupo' => '2 B',
            'activo' => 1,
        ]);
        $alumno = $this->crearAlumno(['grado' => '2', 'grupo' => 'B']);
        $archivo = ArchivoSistema::create([
            'matricula' => $alumno->matricula,
            'nombre_original' => 'ficticio.pdf',
            'nombre_servidor' => 'ficticio.pdf',
            'fecha_subida' => now(),
        ]);

        $this->actingAs($docente)->patch(route('profile.update'), [
            'name' => 'Intento ficticio',
            'email' => 'otra.ficha@cam48.test',
        ])->assertForbidden();

        $this->assertSame($correoOriginal, $docente->refresh()->email);
        $this->get(route('expedientes.ver', $archivo->id))->assertForbidden();
        $this->put(route('alumnos.update', $alumno->matricula), [
            'nombre' => 'Intento',
            'apellido' => 'Ficticio',
        ])->assertForbidden();
    }

    public function test_docente_conserva_edicion_de_nombre_de_su_perfil_sin_cambiar_correo(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $correoOriginal = $docente->email;

        $this->actingAs($docente)->patch(route('profile.update'), [
            'name' => 'Nombre docente ficticio',
            'email' => $correoOriginal,
        ])->assertRedirect(route('profile.edit'));

        $docente->refresh();
        $this->assertSame('Nombre docente ficticio', $docente->name);
        $this->assertSame($correoOriginal, $docente->email);
    }

    public function test_perfil_no_admite_correo_ni_rol_inyectados_fuera_de_los_campos_validados(): void
    {
        $docente = $this->crearPersonal('Docente', '1 A');
        $correoOriginal = $docente->email;

        $this->actingAs($docente)->patch(route('profile.update'), [
            'name' => 'Docente ficticio',
            'email' => $correoOriginal,
            'correo' => 'otra.ficha@cam48.test',
            'role' => 'admin',
            'tipo_usuario' => 'Director',
        ])->assertRedirect(route('profile.edit'));

        $docente->refresh();
        $this->assertSame($correoOriginal, $docente->email);
        $this->assertSame('Docente', $docente->role);
        $this->assertFalse($docente->isAdmin());
    }

    public function test_cambio_de_correo_administrativo_invalida_verificacion_previa(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('profile.update'), [
            'name' => 'Administrador ficticio',
            'email' => 'nuevo.admin@cam48.test',
        ])->assertRedirect(route('profile.edit'));

        $admin->refresh();
        $this->assertSame('nuevo.admin@cam48.test', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }

    /** @return array<string, array{string}> */
    public static function rolesAdministrativos(): array
    {
        return ['admin' => ['admin'], 'administrador' => ['Administrador'], 'director' => ['Director']];
    }

    /** @return array<string, array{string}> */
    public static function rolesDocentes(): array
    {
        return ['docente' => ['Docente'], 'profesor' => ['Profesor'], 'maestro' => ['Maestro']];
    }

    private function crearPersonal(string $rol, ?string $asignacion, bool $activo = true): User
    {
        $usuario = User::factory()->create(['role' => $rol]);
        Personal::create([
            'id' => 9800 + $usuario->id,
            'nombre_completo' => 'Personal ficticio',
            'email' => $usuario->email,
            'grado_grupo' => $asignacion,
            'activo' => $activo,
        ]);

        return $usuario;
    }

    /** @param array<string, mixed> $atributos */
    private function crearAlumno(array $atributos = []): Alumno
    {
        return Alumno::create(array_merge([
            'matricula' => 'FICT_1A',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
            'nivel_educativo' => 'Primaria',
            'grado' => '1',
            'grupo' => 'A',
            'edad' => 10,
            'activo' => 1,
        ], $atributos));
    }
}
