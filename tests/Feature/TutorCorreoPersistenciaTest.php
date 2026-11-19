<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Tutor;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TutorCorreoPersistenciaTest extends TestCase
{
    #[DataProvider('estadosDelTutor')]
    public function test_formulario_persiste_correo_al_crear_o_actualizar_tutor(bool $existente): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_CORREO',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
        ]);
        $tutorId = null;

        if ($existente) {
            $tutor = Tutor::create([
                'matricula_alumno' => $alumno->matricula,
                't1_nombre' => 'Tutor ficticio',
            ]);
            $tutor->t1_email = 'anterior@example.test';
            $tutor->save();
            $tutorId = $tutor->id;
        }

        $this->actingAs($admin)->post(route('tutores.store'), [
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Tutor ficticio',
            't1_telefono' => '0000000000',
            't1_email' => 'nuevo@example.test',
        ])->assertSessionHasNoErrors()->assertRedirect(route('tutores.index'));

        $this->assertDatabaseCount('tutores', 1);
        $this->assertDatabaseHas('tutores', [
            'matricula_alumno' => 'FICT_CORREO',
            't1_email' => 'nuevo@example.test',
        ]);

        if ($existente) {
            $this->assertSame($tutorId, Tutor::sole()->id);
        }
    }

    /** @param array<string, string|null> $campos */
    #[DataProvider('correosNoInformados')]
    public function test_actualizacion_sin_correo_informado_conserva_todos_los_datos(array $campos): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $tutor = $this->crearTutorConCorreo();
        $antes = $tutor->getAttributes();

        $this->actingAs($admin)->post(route('tutores.store'), array_merge($this->datosDelFormulario(), $campos))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tutores.index'));

        $this->assertSame($antes, $tutor->refresh()->getAttributes());
        $this->assertDatabaseCount('tutores', 1);
    }

    public function test_correo_valido_actualiza_solo_el_correo_del_tutor(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $tutor = $this->crearTutorConCorreo();
        $esperado = $tutor->getAttributes();
        $esperado['t1_email'] = 'nuevo@example.test';

        $this->actingAs($admin)->post(route('tutores.store'), array_merge($this->datosDelFormulario(), [
            't1_email' => 'nuevo@example.test',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('tutores.index'));

        $this->assertSame($esperado, $tutor->refresh()->getAttributes());
        $this->assertDatabaseCount('tutores', 1);
    }

    public function test_correo_invalido_rechaza_la_solicitud_sin_modificar_datos(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        $tutor = $this->crearTutorConCorreo();
        $antes = $tutor->getAttributes();

        $this->actingAs($admin)->postJson(route('tutores.store'), array_merge($this->datosDelFormulario(), [
            't1_email' => 'correo-invalido',
        ]))->assertUnprocessable()->assertJsonValidationErrors('t1_email');

        $this->assertSame($antes, $tutor->refresh()->getAttributes());
        $this->assertDatabaseCount('tutores', 1);
    }

    public function test_usuario_sin_permiso_no_puede_cambiar_el_correo(): void
    {
        $usuario = User::factory()->create(['role' => 'alumno']);
        $tutor = $this->crearTutorConCorreo();
        $antes = $tutor->getAttributes();

        $this->actingAs($usuario)->post(route('tutores.store'), array_merge($this->datosDelFormulario(), [
            't1_email' => 'nuevo@example.test',
        ]))->assertForbidden();

        $this->assertSame($antes, $tutor->refresh()->getAttributes());
        $this->assertDatabaseCount('tutores', 1);
    }

    public function test_alta_con_correo_vacio_conserva_su_caracter_opcional(): void
    {
        $admin = User::factory()->create(['role' => 'Secretaria']);
        Alumno::create([
            'matricula' => 'FICT_CORREO',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
        ]);

        $this->actingAs($admin)->post(route('tutores.store'), array_merge($this->datosDelFormulario(), [
            't1_email' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('tutores.index'));

        $this->assertDatabaseHas('tutores', [
            'matricula_alumno' => 'FICT_CORREO',
            't1_email' => null,
        ]);
        $this->assertDatabaseCount('tutores', 1);
    }

    /** @return array<string, array{array<string, string|null>}> */
    public static function correosNoInformados(): array
    {
        return [
            'omitido' => [[]],
            'vacio' => [['t1_email' => '']],
            'null' => [['t1_email' => null]],
            'espacios' => [['t1_email' => '   ']],
        ];
    }

    private function crearTutorConCorreo(): Tutor
    {
        Alumno::create([
            'matricula' => 'FICT_CORREO',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
        ]);

        return Tutor::create([
            'matricula_alumno' => 'FICT_CORREO',
            't1_nombre' => 'TUTOR FICTICIO',
            't1_parentesco' => 'Familiar ficticio',
            't1_telefono' => '0000000000',
            't1_email' => 'anterior@example.test',
            't1_direccion' => 'Domicilio ficticio',
            't1_profesion' => 'Profesion ficticia',
            't1_foto_perfil' => 'ficticio/no-leer.png',
            't1_telegram_chat_id' => 'CHAT_FICTICIO',
            't2_nombre' => 'Tutor alterno ficticio',
            't2_parentesco' => 'Familiar ficticio',
            't2_telefono' => '0000000001',
        ])->refresh();
    }

    /** @return array{matricula_alumno: string, t1_nombre: string, t1_telefono: string, t1_telegram_chat_id: string} */
    private function datosDelFormulario(): array
    {
        return [
            'matricula_alumno' => 'FICT_CORREO',
            't1_nombre' => 'TUTOR FICTICIO',
            't1_telefono' => '0000000000',
            't1_telegram_chat_id' => 'CHAT_FICTICIO',
        ];
    }

    /** @return array<string, array{bool}> */
    public static function estadosDelTutor(): array
    {
        return ['alta' => [false], 'actualizacion' => [true]];
    }
}
