<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Evento;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadisticasConvocatoriasAmbiguedadTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Secretaria']);
    }

    public function test_dos_eventos_el_mismo_dia_sin_evento_id_no_se_atribuyen_a_ninguno(): void
    {
        $fecha = Carbon::today()->toDateString();

        $evento1 = Evento::create([
            'nombre_evento' => 'Junta Manana',
            'fecha_hora' => Carbon::today()->setTime(9, 0),
        ]);

        $evento2 = Evento::create([
            'nombre_evento' => 'Junta Tarde',
            'fecha_hora' => Carbon::today()->setTime(16, 0),
        ]);

        $alumno = Alumno::create(['matricula' => 'ALU_AMB_01', 'nombre' => 'Luis', 'apellido' => 'Perez', 'activo' => 1]);
        $tutor = Tutor::create(['matricula_alumno' => $alumno->matricula, 't1_nombre' => 'Roberto Perez', 't1_telefono' => '9211002030']);

        // Convocados a ambos eventos
        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $evento1->id, 'tutor_id' => $tutor->id, 'notificado' => true, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $evento2->id, 'tutor_id' => $tutor->id, 'notificado' => true, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Registro de asistencia legado sin evento_id (ambiguo porque hay 2 eventos en la misma fecha)
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => $fecha,
            'hora' => '10:00:00',
            'evento_id' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) use ($evento1, $evento2) {
            $page->component('Estadisticas/Index');
            $participacion = collect($page->toArray()['props']['participacionEventos']);

            $ev1Data = $participacion->firstWhere('id', $evento1->id);
            $ev2Data = $participacion->firstWhere('id', $evento2->id);

            // Ninguno debe haber recibido la asistencia ambigua
            $this->assertEquals(0, $ev1Data['asistentes']);
            $this->assertEquals(0, $ev2Data['asistentes']);
            $this->assertEquals(0, $ev1Data['porcentaje']);
            $this->assertEquals(0, $ev2Data['porcentaje']);
        });
    }

    public function test_dos_eventos_el_mismo_dia_con_evento_id_se_atribuyen_con_total_precision(): void
    {
        $fecha = Carbon::today()->toDateString();

        $evento1 = Evento::create([
            'nombre_evento' => 'Junta Manana',
            'fecha_hora' => Carbon::today()->setTime(9, 0),
        ]);

        $evento2 = Evento::create([
            'nombre_evento' => 'Junta Tarde',
            'fecha_hora' => Carbon::today()->setTime(16, 0),
        ]);

        $alumno = Alumno::create(['matricula' => 'ALU_EXACT_01', 'nombre' => 'Ana', 'apellido' => 'Sosa', 'activo' => 1]);
        $tutor = Tutor::create(['matricula_alumno' => $alumno->matricula, 't1_nombre' => 'Rosa Sosa', 't1_telefono' => '9211004050']);

        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $evento1->id, 'tutor_id' => $tutor->id, 'notificado' => true, 'asistio' => true, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $evento2->id, 'tutor_id' => $tutor->id, 'notificado' => true, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Asistencia con evento_id explícito a la junta de la mañana
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => $fecha,
            'hora' => '09:05:00',
            'evento_id' => $evento1->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) use ($evento1, $evento2) {
            $participacion = collect($page->toArray()['props']['participacionEventos']);

            $ev1Data = $participacion->firstWhere('id', $evento1->id);
            $ev2Data = $participacion->firstWhere('id', $evento2->id);

            // Evento 1 tiene 1 asistente (100%), Evento 2 tiene 0 asistentes (0%)
            $this->assertEquals(1, $ev1Data['asistentes']);
            $this->assertEquals(100, $ev1Data['porcentaje']);
            $this->assertEquals(0, $ev2Data['asistentes']);
            $this->assertEquals(0, $ev2Data['porcentaje']);
        });
    }

    public function test_evento_unico_en_la_fecha_permite_fallback_seguro_de_asistencias_legadas(): void
    {
        $fecha = Carbon::today()->toDateString();

        $eventoUnico = Evento::create([
            'nombre_evento' => 'Junta Unica General',
            'fecha_hora' => Carbon::today()->setTime(10, 0),
        ]);

        $alumno = Alumno::create(['matricula' => 'ALU_LEG_01', 'nombre' => 'Mario', 'apellido' => 'Luna', 'activo' => 1]);
        $tutor = Tutor::create(['matricula_alumno' => $alumno->matricula, 't1_nombre' => 'Sara Luna', 't1_telefono' => '9211006070']);

        DB::table('evento_convocatorias')->insert([
            'evento_id' => $eventoUnico->id,
            'tutor_id' => $tutor->id,
            'notificado' => true,
            'asistio' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Asistencia sin evento_id pero como es el único evento ese día, el fallback es unívoco
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => $fecha,
            'hora' => '10:15:00',
            'evento_id' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) use ($eventoUnico) {
            $participacion = collect($page->toArray()['props']['participacionEventos']);
            $evData = $participacion->firstWhere('id', $eventoUnico->id);

            $this->assertEquals(1, $evData['asistentes']);
            $this->assertEquals(100, $evData['porcentaje']);
        });
    }

    public function test_denominador_de_convocados_reales_vs_asistentes_deduplicados_por_familia(): void
    {
        $evento = Evento::create([
            'nombre_evento' => 'Junta Rendicion de Cuentas',
            'fecha_hora' => Carbon::today()->setTime(11, 0),
        ]);

        $alumno = Alumno::create(['matricula' => 'ALU_FAM_DUAL', 'nombre' => 'Mateo', 'apellido' => 'Ramirez', 'activo' => 1]);
        $padre = Tutor::create(['t1_nombre' => 'Juan Ramirez', 't1_telefono' => '9211008081']);
        $madre = Tutor::create(['t1_nombre' => 'Maria Ramirez', 't1_telefono' => '9211008082']);

        // Vinculamos padre y madre al mismo alumno
        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $padre->id, 'alumno_matricula' => $alumno->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $madre->id, 'alumno_matricula' => $alumno->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Ambos son convocados
        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $evento->id, 'tutor_id' => $padre->id, 'notificado' => true, 'asistio' => true, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $evento->id, 'tutor_id' => $madre->id, 'notificado' => true, 'asistio' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Ambos asisten físicamente a la junta
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $padre->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '11:05:00',
            'evento_id' => $evento->id,
        ]);
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $madre->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '11:06:00',
            'evento_id' => $evento->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) use ($evento) {
            $participacion = collect($page->toArray()['props']['participacionEventos']);
            $evData = $participacion->firstWhere('id', $evento->id);

            // Familias convocadas: 1 (padre y madre son la misma familia)
            $this->assertEquals(1, $evData['convocados']);
            // Tutores personas convocadas: 2
            $this->assertEquals(2, $evData['tutores_convocados']);
            // Familias asistentes deduplicadas: 1
            $this->assertEquals(1, $evData['asistentes']);
            // Tutores personas físicas asistentes: 2
            $this->assertEquals(2, $evData['tutores_asistentes']);
            // Porcentaje de participación familiar: 100%
            $this->assertEquals(100, $evData['porcentaje']);
        });
    }

    public function test_combina_registros_historicos_y_explicitos_en_evento_unico_sin_duplicar(): void
    {
        $fecha = Carbon::today()->toDateString();

        $evento = Evento::create([
            'nombre_evento' => 'Junta Mixta Historica y Nueva',
            'fecha_hora' => Carbon::today()->setTime(10, 0),
        ]);

        $al1 = Alumno::create(['matricula' => 'MIX_01', 'nombre' => 'Hugo', 'apellido' => 'Diaz', 'activo' => 1]);
        $al2 = Alumno::create(['matricula' => 'MIX_02', 'nombre' => 'Paco', 'apellido' => 'Soto', 'activo' => 1]);

        $tutor1 = Tutor::create(['matricula_alumno' => 'MIX_01', 't1_nombre' => 'Tutor Uno', 't1_telefono' => '9210010001']);
        $tutor2 = Tutor::create(['matricula_alumno' => 'MIX_02', 't1_nombre' => 'Tutor Dos', 't1_telefono' => '9210010002']);

        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $evento->id, 'tutor_id' => $tutor1->id, 'notificado' => true, 'asistio' => true, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $evento->id, 'tutor_id' => $tutor2->id, 'notificado' => true, 'asistio' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Tutor 1 tiene registro explícito con evento_id Y un registro duplicado + un histórico sin evento_id
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor1->id, 'fecha' => $fecha, 'hora' => '10:01:00', 'evento_id' => $evento->id]);
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor1->id, 'fecha' => $fecha, 'hora' => '10:02:00', 'evento_id' => $evento->id]);
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor1->id, 'fecha' => $fecha, 'hora' => '10:03:00', 'evento_id' => null]);

        // Tutor 2 solo tiene registro histórico sin evento_id
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor2->id, 'fecha' => $fecha, 'hora' => '10:05:00', 'evento_id' => null]);

        $response = $this->actingAs($this->admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) use ($evento) {
            $props = $page->toArray()['props'];
            $participacion = collect($props['participacionEventos']);
            $evData = $participacion->firstWhere('id', $evento->id);

            // 2 asistentes únicos (Tutor 1 explícito, Tutor 2 histórico), sin duplicar Tutor 1
            $this->assertEquals(2, $evData['asistentes']);
            $this->assertEquals(2, $evData['tutores_asistentes']);
            $this->assertEquals(1, $evData['asistentes_explicitos']);
            $this->assertEquals(1, $evData['asistentes_historicos']);
            $this->assertEquals(0, $evData['registros_ambiguos']);
            $this->assertFalse($evData['es_fecha_ambigua']);

            // En el ranking, cada tutor cuenta exactamente 1 asistencia para esta junta
            $ranking = collect($props['rankingTutores']);
            $r1 = $ranking->firstWhere('nombre_tutor', 'TUTOR UNO');
            $r2 = $ranking->firstWhere('nombre_tutor', 'TUTOR DOS');
            $this->assertEquals(1, $r1['asistencias']);
            $this->assertEquals(1, $r2['asistencias']);
        });
    }

    public function test_evento_cancelado_o_eliminado_no_contamina_estadisticas_de_otro_evento_en_la_misma_fecha(): void
    {
        $fecha = Carbon::today()->toDateString();

        $eventoActivo = Evento::create([
            'nombre_evento' => 'Junta Activa',
            'fecha_hora' => Carbon::today()->setTime(9, 0),
        ]);

        $eventoCancelado = Evento::create([
            'nombre_evento' => 'Junta Cancelada',
            'fecha_hora' => Carbon::today()->setTime(17, 0),
        ]);

        $alActivo = Alumno::create(['matricula' => 'ACT_01', 'nombre' => 'Ana', 'apellido' => 'Sol', 'activo' => 1]);
        $alCancelado = Alumno::create(['matricula' => 'CAN_01', 'nombre' => 'Beto', 'apellido' => 'Mar', 'activo' => 1]);

        $tutorActivo = Tutor::create(['matricula_alumno' => 'ACT_01', 't1_nombre' => 'Tutor Activo', 't1_telefono' => '9212220001']);
        $tutorOtro = Tutor::create(['matricula_alumno' => 'CAN_01', 't1_nombre' => 'Tutor Otro', 't1_telefono' => '9212220002']);

        // Solo tutorActivo está convocado a eventoActivo
        DB::table('evento_convocatorias')->insert([
            'evento_id' => $eventoActivo->id,
            'tutor_id' => $tutorActivo->id,
            'notificado' => true,
            'asistio' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registro de tutorOtro pertenecía al eventoCancelado (al eliminarse, evento_id pasa a NULL por nullOnDelete)
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutorOtro->id,
            'fecha' => $fecha,
            'hora' => '17:05:00',
            'evento_id' => $eventoCancelado->id,
        ]);

        // Cancelar/eliminar eventoCancelado
        $eventoCancelado->delete();

        $response = $this->actingAs($this->admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) use ($eventoActivo, $eventoCancelado) {
            $participacion = collect($page->toArray()['props']['participacionEventos']);

            $this->assertNull($participacion->firstWhere('id', $eventoCancelado->id));

            $evData = $participacion->firstWhere('id', $eventoActivo->id);
            $this->assertNotNull($evData);
            // tutorOtro NO estaba convocado a eventoActivo, por lo que su asistencia huérfana no se le atribuye
            $this->assertEquals(0, $evData['asistentes']);
        });
    }
}
