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
        $this->admin = User::factory()->create(['role' => 'admin']);
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
}
