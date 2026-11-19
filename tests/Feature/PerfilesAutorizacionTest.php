<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Evento;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerfilesAutorizacionTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function perfiles(): array
    {
        return [
            'director' => ['Director', 'Director'],
            'directivo' => ['  dIrEcTiVo  ', 'Director'],
            'secretaria' => ['Secretaria', 'Secretaria'],
            'secretario' => [' SECRETARIO ', 'Secretaria'],
            'operador' => ['Operador', 'Operador'],
            'admin' => ['Admin', null],
            'administrador' => ['Administrador', null],
            'docente' => ['Docente', null],
            'personal' => ['Personal', null],
            'administrativo' => ['Administrativo', null],
            'kiosco' => ['Kiosco', null],
            'asistencia' => ['Asistencia', null],
            'prefectura' => ['Prefectura', null],
            'desconocido' => ['Desconocido', null],
        ];
    }

    #[DataProvider('perfiles')]
    public function test_matriz_de_gates_y_politicas(string $storedRole, ?string $canonical): void
    {
        $user = new User(['tipo_usuario' => $storedRole]);
        $gate = Gate::forUser($user);
        $school = in_array($canonical, ['Director', 'Secretaria'], true);
        $director = $canonical === 'Director';
        $this->assertSame($canonical, $user->canonicalRole());
        $this->assertSame($director, $user->isDirector());
        $this->assertSame($canonical === 'Secretaria', $user->isSecretaria());
        $this->assertSame($canonical === 'Operador', $user->isOperador());

        foreach ([
            'dashboard.consultar', 'alumnos.consultar', 'alumnos.crear', 'alumnos.actualizar',
            'tutores.consultar', 'tutores.crear', 'tutores.actualizar', 'eventos.consultar',
            'eventos.crear', 'eventos.actualizar', 'convocatorias.consultar',
            'convocatorias.gestionar', 'asistencias.manual',
            'asistencias.corregir', 'historial.consultar', 'historial.exportar',
            'estadisticas.consultar', 'notificaciones.tutores', 'credenciales.generar',
            'credenciales.imprimir',
        ] as $ability) {
            $this->assertSame($school, $gate->allows($ability), $ability);
        }

        foreach ([
            'admin-access', 'gestionar-usuarios', 'gestionar-permisos', 'gestionar-configuracion',
            'gestionar-respaldos', 'personal.consultar', 'personal.crear', 'personal.actualizar',
            'personal.eliminar', 'alumnos.eliminar', 'tutores.eliminar', 'historial.eliminar',
            'telegram.sincronizar', 'notificaciones.personal', 'expedientes.consultar',
            'expedientes.gestionar',
            'eventos.eliminar', 'convocatorias.desconvocar',
        ] as $ability) {
            $this->assertSame($director, $gate->allows($ability), $ability);
        }

        $this->assertSame($canonical !== null, $gate->allows('asistencias.registrar'));
        $this->assertSame(in_array($canonical, ['Director', 'Operador'], true), $gate->allows('asistencia.escanear'));
        $this->assertFalse($gate->allows('permiso.no.definido'));
        $this->assertSame($school, $gate->allows('create', Alumno::class));
        $this->assertSame($school, $gate->allows('update', new Alumno));
        $this->assertSame($director, $gate->allows('viewAny', ArchivoSistema::class));
        $this->assertSame($director, $gate->allows('view', new ArchivoSistema));
        $this->assertSame($director, $gate->allows('create', ArchivoSistema::class));
    }

    #[DataProvider('perfiles')]
    public function test_rutas_directas_respetan_perfiles(string $storedRole, ?string $canonical): void
    {
        $user = User::create([
            'nombre_completo' => 'Cuenta Sintetica', 'correo' => 'matrix@example.test',
            'password' => 'synthetic-password-123', 'tipo_usuario' => $storedRole,
        ]);
        $this->actingAs($user);
        $school = in_array($canonical, ['Director', 'Secretaria'], true);
        $this->get('/dashboard')->assertStatus($school ? 200 : 403);
        $this->get('/escaner')->assertStatus(in_array($canonical, ['Director', 'Operador'], true) ? 200 : 403);
        if ($canonical !== 'Director') {
            foreach (['/expedientes', '/personal', '/register'] as $path) {
                $this->get($path)->assertForbidden();
            }
            $this->get(route('expedientes.ver', 999999))->assertForbidden();
            $this->get(route('expedientes.descargar', 999999))->assertForbidden();
            $this->get('/telegram/sync')->assertForbidden();
        }
        if (! $school) {
            foreach (['/tutores', '/estadisticas', '/historial', '/credenciales', '/eventos'] as $path) {
                $this->get($path)->assertForbidden();
            }
            $before = Alumno::count();
            $this->post('/alumnos', ['matricula' => 'SYNTHETIC-DENIED'])->assertForbidden();
            $this->assertSame($before, Alumno::count());
        }
    }

    public function test_secretaria_no_puede_escalar_desde_perfil(): void
    {
        $user = User::create([
            'nombre_completo' => 'Secretaria Sintetica', 'correo' => 'secretaria@example.test',
            'password' => 'synthetic-password-123', 'tipo_usuario' => 'Secretario',
        ]);
        $this->actingAs($user)->patch('/profile', [
            'name' => 'Secretaria Sintetica', 'email' => 'secretaria@example.test',
            'role' => 'Director', 'tipo_usuario' => 'Director',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Secretario', $user->refresh()->tipo_usuario);
        $this->assertFalse($user->isDirector());
    }

    public function test_secretaria_registra_alumno_tutor_y_convocatoria_sinteticos(): void
    {
        $user = User::create([
            'nombre_completo' => 'Secretaria Sintetica', 'correo' => 'school@example.test',
            'password' => 'synthetic-password-123', 'tipo_usuario' => 'Secretario',
        ]);
        $this->actingAs($user)->post('/alumnos', [
            'matricula' => 'SYNTHETIC-001', 'nombre' => 'Alumno', 'apellido' => 'Sintetico',
            'grado' => '1', 'grupo' => 'A',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('alumnos', ['matricula' => 'SYNTHETIC-001', 'nombre' => 'ALUMNO']);
        $this->post(route('tutores.store'), [
            'matricula_alumno' => 'SYNTHETIC-001', 't1_nombre' => 'Tutor Sintetico',
            't1_telefono' => '0000000000', 't1_email' => 'tutor@example.test',
        ])->assertSessionHasNoErrors();
        $tutor = Tutor::where('matricula_alumno', 'SYNTHETIC-001')->firstOrFail();
        $this->assertSame('tutor@example.test', $tutor->t1_email);
        $evento = Evento::create([
            'nombre_evento' => 'Junta Sintetica', 'fecha_hora' => '2030-01-01 12:00:00',
        ]);
        $this->post(route('eventos.convocar', $evento->id), ['tutores_ids' => [$tutor->id]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('evento_convocatorias', ['evento_id' => $evento->id, 'tutor_id' => $tutor->id]);
        $this->post(route('eventos.asistenciaManual', [$evento->id, $tutor->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('registros_asistencia', ['evento_id' => $evento->id, 'asistible_id' => (string) $tutor->id]);
    }

    public function test_cancelaciones_sin_auditoria_no_eliminan_registros(): void
    {
        $user = User::create([
            'nombre_completo' => 'Director Sintetico', 'correo' => 'cancel@example.test',
            'password' => 'synthetic-password-123', 'tipo_usuario' => 'Director',
        ]);
        $evento = Evento::create([
            'nombre_evento' => 'Junta Sintetica', 'fecha_hora' => '2030-01-01 12:00:00',
        ]);
        $tutor = Tutor::create(['t1_nombre' => 'Tutor Sintetico']);
        DB::table('evento_convocatorias')->insert([
            'evento_id' => $evento->id, 'tutor_id' => $tutor->id,
            'notificado' => false, 'asistio' => false,
        ]);
        $before = DB::table('evento_convocatorias')->where('evento_id', $evento->id)->first();
        $this->actingAs($user)->delete(route('eventos.destroy', $evento->id))->assertStatus(409);
        $this->delete(route('eventos.desconvocar', [$evento->id, $tutor->id]))->assertStatus(409);
        $this->assertNotNull($evento->fresh());
        $this->assertEquals($before, DB::table('evento_convocatorias')->where('evento_id', $evento->id)->first());
    }

    #[DataProvider('perfiles')]
    public function test_login_real_redirige_o_rechaza_el_perfil(string $storedRole, ?string $canonical): void
    {
        $user = User::create([
            'nombre_completo' => 'Login Sintetico', 'correo' => 'login-matrix@example.test',
            'password' => 'synthetic-password-123', 'tipo_usuario' => $storedRole,
        ]);
        $response = $this->withSession(['url.intended' => route('register')])->post('/login', [
            'email' => $user->email, 'password' => 'synthetic-password-123',
        ]);
        if ($canonical === null) {
            $response->assertForbidden();
            $this->assertGuest();
        } else {
            $response->assertRedirect(route($canonical === 'Operador' ? 'asistencia.escaner' : 'dashboard'));
            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_director_solo_crea_perfiles_canonicos_y_conserva_sesion(): void
    {
        $director = User::create([
            'nombre_completo' => 'Director Sintetico', 'correo' => 'director@example.test',
            'password' => 'synthetic-password-123', 'tipo_usuario' => 'Directivo',
        ]);
        $this->actingAs($director);
        foreach (['Director', 'Secretaria', 'Operador'] as $index => $role) {
            $email = 'new-profile-'.$index.'-'.strtolower($role).'@example.test';
            $this->post('/register', [
                'name' => 'Cuenta Sintetica', 'email' => $email,
                'password' => 'synthetic-password-123', 'password_confirmation' => 'synthetic-password-123',
                'tipo_usuario' => $role,
            ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
            $this->assertSame($role, User::where('correo', $email)->firstOrFail()->tipo_usuario);
            $this->assertAuthenticatedAs($director);
        }
        $before = User::count();
        $this->post('/register', [
            'name' => 'Alias No Canonico', 'email' => 'alias@example.test',
            'password' => 'synthetic-password-123', 'password_confirmation' => 'synthetic-password-123',
            'tipo_usuario' => 'Directivo',
        ])->assertSessionHasErrors('tipo_usuario');
        $this->assertSame($before, User::count());
    }
}
