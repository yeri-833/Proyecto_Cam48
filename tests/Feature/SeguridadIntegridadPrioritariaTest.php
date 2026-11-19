<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAuthorizedProfile;
use App\Models\Alumno;
use App\Models\Evento;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class SeguridadIntegridadPrioritariaTest extends TestCase
{
    /** @return array<string, array{string, mixed}> */
    public static function eventosInvalidos(): array
    {
        $cases = [];
        foreach (['Director', 'Operador'] as $perfil) {
            foreach ([
                'inexistente' => 999999,
                'texto' => 'evento-invalido',
                'cero' => 0,
                'negativo' => -1,
                'decimal' => 1.5,
                'booleano' => true,
                'arreglo' => [1],
                'fuera de rango' => '999999999999999999999999999',
            ] as $name => $value) {
                $cases[$perfil.' '.$name] = [$perfil, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('eventosInvalidos')]
    public function test_evento_invalido_no_crea_asistencias_ni_convocatorias(string $perfil, mixed $eventoId): void
    {
        $usuario = $this->crearUsuario($perfil);
        $tutor = Tutor::create([
            't1_nombre' => 'Tutor Sintetico', 't1_telefono' => '0001234567',
            't1_email' => 'private-marker@example.test',
        ]);
        $evento = Evento::create(['nombre_evento' => 'Evento Valido', 'fecha_hora' => '2030-01-01 09:00:00']);
        DB::table('evento_convocatorias')->insert([
            'evento_id' => $evento->id, 'tutor_id' => $tutor->id,
            'asistio' => false, 'notificado' => true,
        ]);
        $antes = $this->estadoHistorico();

        $respuesta = $this->actingAs($usuario)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $this->qr((string) $tutor->id, 'Tutor'),
            'evento_id' => $eventoId,
        ])->assertUnprocessable()->assertJsonValidationErrors('evento_id');

        $respuesta->assertDontSee('Tutor Sintetico')->assertDontSee('0001234567')
            ->assertDontSee('private-marker@example.test');
        if ($perfil === 'Operador') {
            $respuesta->assertExactJson([
                'message' => 'Los datos del registro no son válidos.',
                'errors' => ['evento_id' => ['El evento seleccionado no es válido o no está disponible.']],
            ]);
        }

        $this->assertSame($antes, $this->estadoHistorico());
        $this->assertDatabaseCount('registros_asistencia', 0);
        $this->assertDatabaseCount('evento_convocatorias', 1);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @return array<string, array{bool}> */
    public static function erroresKiosco(): array
    {
        return ['evento y campo privado' => [true], 'solo campo no permitido' => [false]];
    }

    #[DataProvider('erroresKiosco')]
    public function test_middleware_sanea_validacion_sin_copiar_datos_privados(bool $conErrorEvento): void
    {
        $usuario = $this->crearUsuario('Operador');
        $request = Request::create('/escaner/procesar', 'POST');
        $request->setUserResolver(static fn (): User => $usuario);
        $route = new Route('POST', 'escaner/procesar', static fn (): null => null);
        $route->name('asistencia.procesarQr');
        $request->setRouteResolver(static fn (): Route => $route);
        $privado = 'PERSONA_FICTICIA 0001234567 privado@example.test QR_PRIVADO_FICTICIO';
        $errors = ['qr_data' => [$privado]];
        if ($conErrorEvento) {
            $errors['evento_id'] = [$privado];
        }
        $original = new JsonResponse([
            'message' => $privado,
            'errors' => $errors,
            'persona' => $privado, 'alumnos_vinculados' => $privado,
        ], 422, ['X-Synthetic-Contract' => 'preserved']);

        $respuesta = (new EnsureAuthorizedProfile)->handle($request, static fn (): JsonResponse => $original);

        $this->assertSame($original, $respuesta);
        $this->assertSame(422, $respuesta->getStatusCode());
        $this->assertSame('preserved', $respuesta->headers->get('X-Synthetic-Contract'));
        $this->assertSame([
            'message' => 'Los datos del registro no son válidos.',
            'errors' => $conErrorEvento ? ['evento_id' => ['El evento seleccionado no es válido o no está disponible.']] : [],
        ], $original->getData(true));
        if ($conErrorEvento === false) {
            $this->assertInstanceOf(stdClass::class, $original->getData()->errors);
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function perfilesEscaner(): array
    {
        return [
            'director entero' => ['Director', false],
            'director cadena' => ['Director', true],
            'operador entero' => ['Operador', false],
            'operador cadena' => ['Operador', true],
        ];
    }

    #[DataProvider('perfilesEscaner')]
    public function test_evento_existente_registra_tutor_y_convocatoria(string $perfil, bool $cadena): void
    {
        $usuario = $this->crearUsuario($perfil);
        $tutor = Tutor::create(['t1_nombre' => 'Tutor Sintetico']);
        $evento = Evento::create(['nombre_evento' => 'Evento Sintetico', 'fecha_hora' => '2030-01-01 09:00:00']);

        $respuesta = $this->actingAs($usuario)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $this->qr((string) $tutor->id, 'Tutor'),
            'evento_id' => $cadena ? (string) $evento->id : $evento->id,
        ]);

        $respuesta->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('registros_asistencia', [
            'evento_id' => $evento->id, 'asistible_id' => (string) $tutor->id,
            'asistible_type' => Tutor::class, 'modo_registro' => 'QR',
        ]);
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $evento->id, 'tutor_id' => $tutor->id, 'asistio' => true,
        ]);
        $this->assertDatabaseCount('registros_asistencia', 1);
        $this->assertDatabaseCount('evento_convocatorias', 1);
        if ($perfil === 'Operador') {
            $this->assertEqualsCanonicalizing(['status', 'msg', 'hora'], array_keys($respuesta->json()));
            $respuesta->assertDontSee('Tutor Sintetico');
        }
    }

    public function test_fallo_al_insertar_convocatoria_revierte_la_asistencia(): void
    {
        $usuario = $this->crearUsuario('Director');
        $tutor = Tutor::create(['t1_nombre' => 'Tutor Sintetico']);
        $evento = Evento::create(['nombre_evento' => 'Evento Sintetico', 'fecha_hora' => '2030-01-01 09:00:00']);
        $antes = $this->estadoHistorico();
        $falloProvocado = false;
        DB::connection()->beforeExecuting(static function (string $sql) use (&$falloProvocado): void {
            if (str_starts_with(strtolower(ltrim($sql)), 'insert') && str_contains($sql, 'evento_convocatorias')) {
                $falloProvocado = true;
                throw new RuntimeException('SYNTHETIC_CONVOCATORIA_WRITE_FAILURE');
            }
        });

        $this->actingAs($usuario)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $this->qr((string) $tutor->id, 'Tutor'), 'evento_id' => $evento->id,
        ])->assertOk()->assertJsonPath('status', 'error');

        $this->assertTrue($falloProvocado);
        $this->assertSame($antes, $this->estadoHistorico());
        $this->assertDatabaseCount('registros_asistencia', 0);
        $this->assertDatabaseCount('evento_convocatorias', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function eventoOpcional(): array
    {
        return ['omitido' => [[]], 'nulo' => [['evento_id' => null]], 'vacio' => [['evento_id' => '']]];
    }

    #[DataProvider('eventoOpcional')]
    public function test_registro_qr_sin_evento_sigue_permitido(array $entrada): void
    {
        $usuario = $this->crearUsuario('Operador');
        $alumno = Alumno::create(['matricula' => 'SYN-QR-001', 'nombre' => 'Alumno', 'apellido' => 'Sintetico', 'activo' => true]);

        $this->actingAs($usuario)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $this->qr($alumno->matricula, 'Alumno'),
            ...$entrada,
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('registros_asistencia', 1);
        $this->assertDatabaseHas('registros_asistencia', [
            'asistible_type' => Alumno::class, 'asistible_id' => $alumno->matricula, 'evento_id' => null,
        ]);
        $this->assertDatabaseCount('evento_convocatorias', 0);
    }

    /** @return array<string, array{string, int}> */
    public static function permisosDestructivos(): array
    {
        return [
            'director' => ['Director', 409], 'directivo' => ['Directivo', 409],
            'secretaria' => ['Secretaria', 403], 'secretario' => ['Secretario', 403],
            'operador' => ['Operador', 403], 'admin retirado' => ['Admin', 403],
        ];
    }

    #[DataProvider('permisosDestructivos')]
    public function test_operaciones_destructivas_preservan_todo_el_historial(string $perfil, int $status): void
    {
        $usuario = $this->crearUsuario($perfil);
        $evento = Evento::create(['nombre_evento' => 'Junta Sintetica', 'fecha_hora' => '2030-01-01 09:00:00']);
        $tutor = Tutor::create(['t1_nombre' => 'Tutor Sintetico']);
        DB::table('evento_convocatorias')->insert([
            'evento_id' => $evento->id, 'tutor_id' => $tutor->id,
            'asistio' => true, 'notificado' => true, 'fecha_asistencia' => '2030-01-01 09:10:00',
        ]);
        $asistencia = RegistroAsistencia::create([
            'asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor->id,
            'evento_id' => $evento->id, 'fecha' => '2030-01-01', 'hora' => '09:10:00', 'modo_registro' => 'MANUAL',
        ]);
        $antes = $this->estadoHistorico();
        $this->actingAs($usuario);

        foreach ([
            route('historial.destroy', $asistencia->id),
            route('eventos.destroy', $evento->id),
            route('eventos.desconvocar', [$evento->id, $tutor->id]),
        ] as $ruta) {
            $this->delete($ruta)->assertStatus($status);
            $this->assertSame($antes, $this->estadoHistorico());
        }
    }

    /** @return array<string, array{string}> */
    public static function perfilesSinAutoeliminacion(): array
    {
        return ['secretaria' => ['Secretaria'], 'secretario' => ['Secretario'], 'operador' => ['Operador']];
    }

    #[DataProvider('perfilesSinAutoeliminacion')]
    public function test_cuenta_no_puede_autoeliminarse_sin_permiso(string $perfil): void
    {
        $usuario = $this->crearUsuario($perfil);
        $antes = $usuario->refresh()->getAttributes();
        $this->actingAs($usuario);

        foreach ([['password' => 'synthetic-password-123'], ['password' => 'incorrecta'], []] as $entrada) {
            $this->delete(route('profile.destroy'), $entrada)->assertForbidden();
            $this->assertSame($antes, $usuario->refresh()->getAttributes());
            $this->assertDatabaseCount('usuarios', 1);
            $this->assertAuthenticatedAs($usuario);
        }
    }

    public function test_secretaria_conserva_actualizacion_legitima_del_perfil(): void
    {
        $usuario = $this->crearUsuario('Secretario');
        $this->actingAs($usuario)->patch(route('profile.update'), [
            'name' => 'Nombre Sintetico Corregido', 'email' => $usuario->email,
            'role' => 'Director', 'tipo_usuario' => 'Director',
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $this->assertSame('Nombre Sintetico Corregido', $usuario->refresh()->name);
        $this->assertSame('Secretario', $usuario->tipo_usuario);
        $this->assertAuthenticatedAs($usuario);
    }

    private function crearUsuario(string $perfil): User
    {
        return User::factory()->create(['role' => $perfil, 'email' => 'synthetic@example.test', 'password' => 'synthetic-password-123']);
    }

    private function qr(string $id, string $rol): string
    {
        return json_encode(['id' => $id, 'rol' => $rol, 'signature' => hash_hmac('sha256', $id.$rol, config('app.key'))], JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function estadoHistorico(): array
    {
        $estado = [];
        foreach (['eventos', 'registros_asistencia', 'evento_convocatorias'] as $tabla) {
            $estado[$tabla] = DB::table($tabla)->orderBy('id')->get()
                ->map(static fn (object $fila): array => (array) $fila)->all();
        }

        return $estado;
    }
}
