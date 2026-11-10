<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Evento;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TutoresAsistenciaParticipacionTest extends TestCase
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

    public function test_historial_asistencia_muestra_alumno_relacionado_para_tutores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_ALUM_TUT_01',
            'nombre' => 'Pedrito',
            'apellido' => 'Gómez',
            'activo' => 1,
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'MARIA GOMEZ',
            't1_telefono' => '9210001122',
        ]);

        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '08:30:00',
        ]);

        $response = $this->actingAs($admin)->get(route('historial.index', [
            'fecha' => Carbon::today()->toDateString(),
            'rol' => 'Tutor',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Asistencia/Historial')
            ->has('registros.data', 1)
            ->where('registros.data.0.nombre_completo', 'MARIA GOMEZ')
            ->where('registros.data.0.rol', 'Tutor')
            ->where('registros.data.0.alumno_relacionado', 'Pedrito Gómez')
        );
    }

    public function test_exportacion_csv_incluye_columna_de_alumno_relacionado(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_ALUM_CSV',
            'nombre' => 'Ana',
            'apellido' => 'López',
            'activo' => 1,
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'CARLOS LOPEZ',
            't1_telefono' => '9219998877',
        ]);

        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '09:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('historial.exportar', [
            'fecha' => Carbon::today()->toDateString(),
            'rol' => 'Tutor',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Alumno Relacionado', $content);
        $this->assertStringContainsString('CARLOS LOPEZ', $content);
        $this->assertStringContainsString('Ana López', $content);
    }

    public function test_estadisticas_calcula_participacion_de_tutores_y_eventos(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $alumno = Alumno::create([
            'matricula' => 'FICT_ALUM_ESTAD',
            'nombre' => 'Sofía',
            'apellido' => 'Ramírez',
            'activo' => 1,
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'ELENA RAMIREZ',
            't1_telefono' => '9211112233',
        ]);

        $evento = Evento::create([
            'nombre_evento' => 'Junta Informativa de Evaluación',
            'fecha_hora' => Carbon::today()->setTime(10, 0),
            'ubicacion' => 'Auditorio Escolar',
        ]);

        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '10:05:00',
            'evento_id' => $evento->id,
        ]);

        $response = $this->actingAs($admin)->get(route('estadisticas.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Estadisticas/Index')
            ->has('rankingTutores')
            ->where('rankingTutores.0.nombre_tutor', 'ELENA RAMIREZ')
            ->has('participacionEventos')
            ->where('participacionEventos.0.nombre', 'Junta Informativa de Evaluación')
            ->where('participacionEventos.0.convocados', 1)
            ->where('participacionEventos.0.asistentes', 1)
            ->where('participacionEventos.0.porcentaje', 100)
        );
    }

    public function test_asistencia_procesar_qr_admite_evento_id(): void
    {
        $operador = User::factory()->create(['role' => 'kiosco']);
        $tutor = Tutor::create([
            't1_nombre' => 'TUTOR QR EVENTO',
            't1_telefono' => '0000000000',
        ]);

        $evento = Evento::create([
            'nombre_evento' => 'Junta de Inicio de Ciclo',
            'fecha_hora' => Carbon::now(),
        ]);

        $signature = hash_hmac('sha256', (string) $tutor->id.'Tutor', config('app.key'));

        $response = $this->actingAs($operador)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => json_encode([
                'id' => (string) $tutor->id,
                'rol' => 'Tutor',
                'signature' => $signature,
            ]),
            'evento_id' => $evento->id,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('registros_asistencia', [
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'evento_id' => $evento->id,
        ]);
    }

    public function test_familias_con_multiples_hijos_agrupan_alumnos_y_calculan_denominador_real_de_convocatorias(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Familia 1 con 2 hermanos inscritos
        $h1 = Alumno::create([
            'matricula' => 'HERM_01',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'activo' => 1,
        ]);
        $h2 = Alumno::create([
            'matricula' => 'HERM_02',
            'nombre' => 'Carlos',
            'apellido' => 'Pérez',
            'activo' => 1,
        ]);

        // Familia 2 con 1 alumno
        $h3 = Alumno::create([
            'matricula' => 'UNICO_03',
            'nombre' => 'Ana',
            'apellido' => 'López',
            'activo' => 1,
        ]);

        $tutor1 = Tutor::create([
            'matricula_alumno' => $h1->matricula,
            't1_nombre' => 'MARIA PEREZ',
            't1_telefono' => '9211112222',
            't1_email' => 'maria@familia.com',
        ]);

        $tutor2 = Tutor::create([
            'matricula_alumno' => $h2->matricula,
            't1_nombre' => 'MARIA PEREZ',
            't1_telefono' => '9211112222',
            't1_email' => 'maria@familia.com',
        ]);

        $tutor3 = Tutor::create([
            'matricula_alumno' => $h3->matricula,
            't1_nombre' => 'ROSA LOPEZ',
            't1_telefono' => '9213334444',
            't1_email' => 'rosa@otra.com',
        ]);

        // 1. Verificación del modelo para familias con múltiples hijos
        $alumnosVinculados = $tutor1->alumnos_vinculados;
        $this->assertCount(2, $alumnosVinculados);
        $this->assertStringContainsString('JUAN PÉREZ', $tutor1->nombres_alumnos);
        $this->assertStringContainsString('CARLOS PÉREZ', $tutor1->nombres_alumnos);

        // 2. Creación de evento y asistencia de la Familia Pérez (1 tutor asistente)
        $evento = Evento::create([
            'nombre_evento' => 'Asamblea General Ordinaria',
            'fecha_hora' => Carbon::today()->setTime(11, 0),
        ]);

        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor1->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '11:10:00',
            'evento_id' => $evento->id,
        ]);

        // 3. Verificación de estadísticas con denominador real de convocatorias
        $response = $this->actingAs($admin)->get(route('estadisticas.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Estadisticas/Index')
            ->has('participacionEventos')
            ->where('participacionEventos.0.nombre', 'Asamblea General Ordinaria')
            // Convocados reales: 2 familias (Pérez y López), NO 3 filas de tutores
            ->where('participacionEventos.0.convocados', 2)
            ->where('participacionEventos.0.asistentes', 1)
            ->where('participacionEventos.0.porcentaje', 50)
            ->has('rankingTutores')
            ->where('rankingTutores.0.nombre_tutor', 'MARIA PEREZ')
            ->where('rankingTutores.0.asistencias', 1)
        );
    }

    public function test_distincion_estricta_familias_tutores_homonimos_telefonos_compartidos_y_deduplicacion_eventos(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Homónimos: Dos tutoras distintas llamadas "MARIA LOPEZ" con teléfonos y alumnos distintos
        $a1 = Alumno::create(['matricula' => 'ALUM_HOMO_1', 'nombre' => 'Luis', 'apellido' => 'García', 'activo' => 1]);
        $a2 = Alumno::create(['matricula' => 'ALUM_HOMO_2', 'nombre' => 'Diana', 'apellido' => 'Sánchez', 'activo' => 1]);

        $tutorHomo1 = Tutor::create([
            'matricula_alumno' => $a1->matricula,
            't1_nombre' => 'MARIA LOPEZ',
            't1_telefono' => '9211110001',
            't1_email' => 'maria1@test.com',
        ]);
        $tutorHomo2 = Tutor::create([
            'matricula_alumno' => $a2->matricula,
            't1_nombre' => 'MARIA LOPEZ',
            't1_telefono' => '9211110002',
            't1_email' => 'maria2@test.com',
        ]);

        // Verificar que no se agrupen erróneamente por homonimia
        $this->assertCount(1, $tutorHomo1->alumnos_vinculados);
        $this->assertCount(1, $tutorHomo2->alumnos_vinculados);

        // 2. Teléfono compartido (comunitario/vecindario): Dos tutores distintos con nombres distintos
        $a3 = Alumno::create(['matricula' => 'ALUM_COMUN_1', 'nombre' => 'Pedro', 'apellido' => 'Pérez', 'activo' => 1]);
        $a4 = Alumno::create(['matricula' => 'ALUM_COMUN_2', 'nombre' => 'Carmen', 'apellido' => 'Torres', 'activo' => 1]);

        $tutorComun1 = Tutor::create([
            'matricula_alumno' => $a3->matricula,
            't1_nombre' => 'JUAN PEREZ',
            't1_telefono' => '9219990000', // Teléfono compartido
            't1_email' => 'juan@test.com',
        ]);
        $tutorComun2 = Tutor::create([
            'matricula_alumno' => $a4->matricula,
            't1_nombre' => 'SILVIA TORRES',
            't1_telefono' => '9219990000', // Teléfono compartido
            't1_email' => 'silvia@test.com',
        ]);

        // Verificar que no se agrupen erróneamente por compartir teléfono
        $this->assertCount(1, $tutorComun1->alumnos_vinculados);
        $this->assertCount(1, $tutorComun2->alumnos_vinculados);

        // 3. Crear dos eventos institucionales distintos
        $evento1 = Evento::create([
            'nombre_evento' => 'Junta Trimestral 1',
            'fecha_hora' => Carbon::today()->setTime(9, 0),
        ]);
        $evento2 = Evento::create([
            'nombre_evento' => 'Junta Trimestral 2',
            'fecha_hora' => Carbon::today()->setTime(12, 0),
        ]);

        // TutorHomo1 asiste dos veces al MISMO evento 1 (escaneo duplicado)
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutorHomo1->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '09:05:00',
            'evento_id' => $evento1->id,
        ]);
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutorHomo1->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '09:07:00',
            'evento_id' => $evento1->id,
        ]);

        // TutorHomo1 asiste también al evento 2 (asistencia legítima a dos juntas diferentes)
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutorHomo1->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '12:10:00',
            'evento_id' => $evento2->id,
        ]);

        $response = $this->actingAs($admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Estadisticas/Index')
            // El escaneo doble del evento 1 debe deduplicarse (1 asistente, no 2)
            ->where('participacionEventos.1.asistentes', 1)
            ->where('participacionEventos.1.tutores_asistentes', 1)
            // En el ranking individual, TutorHomo1 tiene exactamente 2 asistencias (1 por cada junta distinta),
            // no 3 por el escaneo repetido
            ->where('rankingTutores.0.nombre_tutor', 'MARIA LOPEZ')
            ->where('rankingTutores.0.asistencias', 2)
        );
    }

    public function test_relaciones_explicitas_alumno_tutor_y_soporte_multiples_hijos(): void
    {
        $hijo1 = Alumno::create(['matricula' => 'HIJO_EXP_1', 'nombre' => 'Mateo', 'apellido' => 'Sánchez', 'activo' => 1]);
        $hijo2 = Alumno::create(['matricula' => 'HIJO_EXP_2', 'nombre' => 'Sofía', 'apellido' => 'Sánchez', 'activo' => 1]);

        $tutor = Tutor::create([
            'matricula_alumno' => $hijo1->matricula,
            't1_nombre' => 'RICARDO SANCHEZ',
            't1_telefono' => '9215551234',
        ]);

        // Vincular explícitamente en tabla alumno_tutor
        \Illuminate\Support\Facades\DB::table('alumno_tutor')->insert([
            ['tutor_id' => $tutor->id, 'alumno_matricula' => $hijo1->matricula, 'parentesco' => 'Padre', 'es_tutor_principal' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor->id, 'alumno_matricula' => $hijo2->matricula, 'parentesco' => 'Padre', 'es_tutor_principal' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertCount(2, $tutor->alumnos);
        $this->assertCount(2, $tutor->alumnos_vinculados);
        $this->assertStringContainsString('MATEO SÁNCHEZ', $tutor->nombres_alumnos);
        $this->assertStringContainsString('SOFÍA SÁNCHEZ', $tutor->nombres_alumnos);

        // Relación inversa desde el alumno
        $this->assertCount(1, $hijo1->tutores);
        $this->assertSame($tutor->id, $hijo1->tutores->first()->id);
    }

    public function test_evento_convocatorias_explicitas_calcula_denominador_real_sin_contar_toda_la_escuela(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Crear 10 alumnos activos en la escuela
        for ($i = 1; $i <= 10; $i++) {
            Alumno::create(['matricula' => "MAT_ESC_{$i}", 'nombre' => "Alumno {$i}", 'apellido' => 'General', 'activo' => 1]);
        }

        // Crear 3 tutores específicos para un subgrupo
        $tutores = [];
        for ($t = 1; $t <= 3; $t++) {
            $tutores[$t] = Tutor::create([
                'matricula_alumno' => "MAT_ESC_{$t}",
                't1_nombre' => "TUTOR CONVOCADO {$t}",
                't1_telefono' => "921888000{$t}",
            ]);
        }

        $evento = Evento::create([
            'nombre_evento' => 'Junta Especial de 1er Grado',
            'fecha_hora' => Carbon::today()->setTime(10, 0),
        ]);

        // Convocar explícitamente solo a los 3 tutores (no a los 10 de la escuela)
        foreach ($tutores as $t) {
            \Illuminate\Support\Facades\DB::table('evento_convocatorias')->insert([
                'evento_id' => $evento->id,
                'tutor_id' => $t->id,
                'alumno_matricula' => $t->matricula_alumno,
                'notificado' => true,
                'asistio' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Asisten solo 2 tutores de los 3 convocados
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutores[1]->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '10:05:00',
            'evento_id' => $evento->id,
        ]);
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutores[2]->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '10:10:00',
            'evento_id' => $evento->id,
        ]);

        $response = $this->actingAs($admin)->get(route('estadisticas.index'));
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Estadisticas/Index')
            // El denominador de convocados debe ser 3 (familias convocadas), NO 10 (alumnos de la escuela)
            ->where('participacionEventos.0.convocados', 3)
            ->where('participacionEventos.0.asistentes', 2)
            ->where('participacionEventos.0.porcentaje', 67)
        );
    }

    public function test_escaner_rechaza_registro_duplicado_mismo_tutor_y_evento(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $alumno = Alumno::create(['matricula' => 'MAT_SCAN_01', 'nombre' => 'Hugo', 'apellido' => 'Díaz', 'activo' => 1]);
        $tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'HUGO DIAZ SR',
            't1_telefono' => '9217771122',
        ]);

        $evento = Evento::create([
            'nombre_evento' => 'Asamblea General Matutina',
            'fecha_hora' => Carbon::today()->setTime(8, 0),
        ]);

        \Illuminate\Support\Facades\DB::table('evento_convocatorias')->insert([
            'evento_id' => $evento->id,
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno->matricula,
            'notificado' => true,
            'asistio' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $signature = hash_hmac('sha256', (string) $tutor->id.'Tutor', config('app.key'));
        $qrPayload = json_encode([
            'id' => (string) $tutor->id,
            'rol' => 'Tutor',
            'signature' => $signature,
        ]);

        // Primer escaneo: debe registrar asistencia y actualizar convocatoria
        $resp1 = $this->actingAs($admin)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $qrPayload,
            'evento_id' => $evento->id,
        ]);

        $resp1->assertOk();
        $resp1->assertJson(['status' => 'success']);

        $convocatoria = \Illuminate\Support\Facades\DB::table('evento_convocatorias')
            ->where('evento_id', $evento->id)
            ->where('tutor_id', $tutor->id)
            ->first();
        $this->assertTrue((bool) $convocatoria->asistio);

        // Segundo escaneo al MISMO evento: debe ser rechazado por duplicado
        $resp2 = $this->actingAs($admin)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $qrPayload,
            'evento_id' => $evento->id,
        ]);

        $resp2->assertOk();
        $resp2->assertJson([
            'status' => 'warning',
            'msg' => 'Asistencia ya registrada previamente para este evento escolar.',
        ]);

        // La tabla de asistencias solo debe contener 1 registro para este evento
        $totalAsistencias = RegistroAsistencia::where('asistible_type', Tutor::class)
            ->where('asistible_id', (string) $tutor->id)
            ->where('evento_id', $evento->id)
            ->count();

        $this->assertSame(1, $totalAsistencias);
    }

    public function test_familia_service_distingue_identidad_familiar_de_identidad_del_tutor(): void
    {
        $familiaService = app(\App\Services\FamiliaService::class);

        // Alumnos hermanos
        $alumno1 = Alumno::create([
            'matricula' => 'ALUM_FAM_01',
            'nombre' => 'Carlos',
            'apellido' => 'Pérez López',
            'activo' => 1,
        ]);
        $alumno2 = Alumno::create([
            'matricula' => 'ALUM_FAM_02',
            'nombre' => 'Sofía',
            'apellido' => 'Pérez López',
            'activo' => 1,
        ]);

        // Mamá y Papá de la misma familia
        $mama = Tutor::create([
            'matricula_alumno' => $alumno1->matricula,
            't1_nombre' => 'MARIA LOPEZ',
            't1_telefono' => '9211112233',
        ]);
        $papa = Tutor::create([
            'matricula_alumno' => $alumno2->matricula,
            't1_nombre' => 'JUAN PEREZ',
            't1_telefono' => '9214445566',
        ]);

        // Vinculación en alumno_tutor
        \Illuminate\Support\Facades\DB::table('alumno_tutor')->insert([
            ['tutor_id' => $mama->id, 'alumno_matricula' => $alumno1->matricula, 'parentesco' => 'MADRE', 'es_tutor_principal' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $mama->id, 'alumno_matricula' => $alumno2->matricula, 'parentesco' => 'MADRE', 'es_tutor_principal' => false, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $papa->id, 'alumno_matricula' => $alumno1->matricula, 'parentesco' => 'PADRE', 'es_tutor_principal' => false, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $papa->id, 'alumno_matricula' => $alumno2->matricula, 'parentesco' => 'PADRE', 'es_tutor_principal' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 1. Mamá y Papá tienen IDs de tutor DIFERENTES pero la MISMA clave familiar canónica
        $this->assertNotSame($mama->id, $papa->id);
        $this->assertSame($mama->familia_key, $papa->familia_key);
        $this->assertTrue($familiaService->sonMismaFamilia($mama, $papa));
        $this->assertStringContainsString('ALUM_FAM_01', $mama->familia_key);
        $this->assertStringContainsString('ALUM_FAM_02', $mama->familia_key);

        // 2. Tutora homónima en OTRA familia distinta
        $alumnoOtraFamilia = Alumno::create([
            'matricula' => 'ALUM_FAM_03',
            'nombre' => 'Luis',
            'apellido' => 'Hernández',
            'activo' => 1,
        ]);
        $tutorHomonimo = Tutor::create([
            'matricula_alumno' => $alumnoOtraFamilia->matricula,
            't1_nombre' => 'MARIA LOPEZ', // Mismo nombre
            't1_telefono' => '9219998877',
        ]);
        \Illuminate\Support\Facades\DB::table('alumno_tutor')->insert([
            'tutor_id' => $tutorHomonimo->id,
            'alumno_matricula' => $alumnoOtraFamilia->matricula,
            'parentesco' => 'MADRE',
            'es_tutor_principal' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotSame($mama->familia_key, $tutorHomonimo->familia_key);
        $this->assertFalse($familiaService->sonMismaFamilia($mama, $tutorHomonimo));

        // 3. Familia no relacionada que comparte teléfono comunitario
        $alumnoCompartido = Alumno::create([
            'matricula' => 'ALUM_FAM_04',
            'nombre' => 'Pedro',
            'apellido' => 'Ramírez',
            'activo' => 1,
        ]);
        $tutorCaseta = Tutor::create([
            'matricula_alumno' => $alumnoCompartido->matricula,
            't1_nombre' => 'ROSA CASANOVA',
            't1_telefono' => '9211112233', // Mismo teléfono de la caseta que $mama
        ]);
        \Illuminate\Support\Facades\DB::table('alumno_tutor')->insert([
            'tutor_id' => $tutorCaseta->id,
            'alumno_matricula' => $alumnoCompartido->matricula,
            'parentesco' => 'MADRE',
            'es_tutor_principal' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotSame($mama->familia_key, $tutorCaseta->familia_key);
        $this->assertFalse($familiaService->sonMismaFamilia($mama, $tutorCaseta));

        // Contar familias únicas en la muestra: deben ser 3 familias distintas
        $familiasUnicas = $familiaService->contarFamiliasUnicas([$mama, $papa, $tutorHomonimo, $tutorCaseta]);
        $this->assertSame(3, $familiasUnicas);
    }

    public function test_administracion_convocatorias_permite_convocar_por_tutor_directo_y_grado_grupo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $evento = Evento::create([
            'nombre_evento' => 'Junta Informativa 1A',
            'fecha_hora' => Carbon::tomorrow()->setTime(9, 0),
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'CONV_ALUM_1A',
            'nombre' => 'Mateo',
            'apellido' => 'Vázquez',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $tutor1A = Tutor::create([
            'matricula_alumno' => $alumno1A->matricula,
            't1_nombre' => 'LETICIA VAZQUEZ',
            't1_telefono' => '9211234567',
        ]);

        \Illuminate\Support\Facades\DB::table('alumno_tutor')->insert([
            'tutor_id' => $tutor1A->id,
            'alumno_matricula' => $alumno1A->matricula,
            'es_tutor_principal' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Convocar por Grado y Grupo '1' 'A'
        $responseConvocar = $this->actingAs($admin)->post(route('eventos.convocar', $evento->id), [
            'grado' => '1',
            'grupo' => 'A',
        ]);

        $responseConvocar->assertRedirect();
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $evento->id,
            'tutor_id' => $tutor1A->id,
            'notificado' => false,
            'asistio' => false,
        ]);

        // Consultar convocatorias del evento vía endpoint
        $responseList = $this->actingAs($admin)->getJson(route('eventos.convocatorias', $evento->id));
        $responseList->assertOk();
        $responseList->assertJsonStructure([
            'evento',
            'convocatorias' => [
                '*' => ['id', 'evento_id', 'tutor_id', 'tutor_nombre', 'asistio'],
            ],
        ]);
        $this->assertCount(1, $responseList->json('convocatorias'));

        // Desconvocar
        $responseDesconvocar = $this->actingAs($admin)->delete(route('eventos.desconvocar', [
            'id' => $evento->id,
            'tutorId' => $tutor1A->id,
        ]));
        $responseDesconvocar->assertRedirect();
        $this->assertDatabaseMissing('evento_convocatorias', [
            'evento_id' => $evento->id,
            'tutor_id' => $tutor1A->id,
        ]);
    }

    public function test_asistencia_manual_en_evento_confirma_asistencia_y_actualiza_convocatoria(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $evento = Evento::create([
            'nombre_evento' => 'Junta General Anual',
            'fecha_hora' => Carbon::today()->setTime(10, 0),
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => 'FICT_MANUAL_01',
            't1_nombre' => 'GABRIEL RIVERA',
            't1_telefono' => '9219876543',
        ]);

        \Illuminate\Support\Facades\DB::table('evento_convocatorias')->insert([
            'evento_id' => $evento->id,
            'tutor_id' => $tutor->id,
            'alumno_matricula' => 'FICT_MANUAL_01',
            'notificado' => true,
            'asistio' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar asistencia manual
        $responseManual = $this->actingAs($admin)->post(route('eventos.asistenciaManual', [
            'id' => $evento->id,
            'tutorId' => $tutor->id,
        ]));

        $responseManual->assertRedirect();

        // Verificar que RegistroAsistencia fue creado con modo MANUAL
        $this->assertDatabaseHas('registros_asistencia', [
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $tutor->id,
            'evento_id' => $evento->id,
            'modo_registro' => 'MANUAL',
        ]);

        // Verificar que evento_convocatorias se actualizó a asistio = true
        $convocatoria = \Illuminate\Support\Facades\DB::table('evento_convocatorias')
            ->where('evento_id', $evento->id)
            ->where('tutor_id', $tutor->id)
            ->first();

        $this->assertNotNull($convocatoria);
        $this->assertTrue((bool) $convocatoria->asistio);

        // Reintentar registro manual al mismo evento: debe advertir que ya asistió
        $responseManual2 = $this->actingAs($admin)->post(route('eventos.asistenciaManual', [
            'id' => $evento->id,
            'tutorId' => $tutor->id,
        ]));
        $responseManual2->assertRedirect();
        $responseManual2->assertSessionHas('warning', 'El tutor ya tiene asistencia registrada para este evento.');
    }

    public function test_asistencia_walk_in_de_tutor_no_convocado_se_registra_en_evento_convocatorias(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $evento = Evento::create([
            'nombre_evento' => 'Junta Especial de Padres',
            'fecha_hora' => Carbon::today()->setTime(11, 0),
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => 'FICT_WALK_01',
            't1_nombre' => 'CAROLINA MENDEZ',
            't1_telefono' => '9213334455',
        ]);

        // El tutor NO está en evento_convocatorias previamente
        $this->assertDatabaseMissing('evento_convocatorias', [
            'evento_id' => $evento->id,
            'tutor_id' => $tutor->id,
        ]);

        $signature = hash_hmac('sha256', (string) $tutor->id.'Tutor', config('app.key'));
        $qrPayload = json_encode([
            'id' => (string) $tutor->id,
            'rol' => 'Tutor',
            'signature' => $signature,
        ]);

        $response = $this->actingAs($admin)->postJson(route('asistencia.procesarQr'), [
            'qr_data' => $qrPayload,
            'evento_id' => $evento->id,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        // Ahora el tutor debe existir en evento_convocatorias con asistio = true
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $evento->id,
            'tutor_id' => $tutor->id,
            'asistio' => true,
        ]);
    }

    public function test_tutor_controller_sincroniza_alumno_tutor_y_permite_vincular_desvincular(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $alumno1 = Alumno::create([
            'matricula' => 'ALUM_SYNC_01',
            'nombre' => 'Esteban',
            'apellido' => 'Díaz',
            'activo' => 1,
        ]);
        $alumno2 = Alumno::create([
            'matricula' => 'ALUM_SYNC_02',
            'nombre' => 'Fernanda',
            'apellido' => 'Díaz',
            'activo' => 1,
        ]);

        // Crear tutor mediante TutorController@store
        $responseStore = $this->actingAs($admin)->post(route('tutores.store'), [
            'matricula_alumno' => $alumno1->matricula,
            't1_nombre' => 'RAUL DIAZ',
            't1_telefono' => '9215556677',
            'alumnos_adicionales' => [$alumno2->matricula],
        ]);

        $responseStore->assertRedirect(route('tutores.index'));

        $tutor = Tutor::where('t1_nombre', 'RAUL DIAZ')->first();
        $this->assertNotNull($tutor);

        // Verificar que ambos alumnos se sincronizaron en alumno_tutor
        $this->assertDatabaseHas('alumno_tutor', [
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno1->matricula,
            'es_tutor_principal' => true,
        ]);
        $this->assertDatabaseHas('alumno_tutor', [
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno2->matricula,
            'es_tutor_principal' => false,
        ]);

        // Vincular un tercer alumno
        $alumno3 = Alumno::create([
            'matricula' => 'ALUM_SYNC_03',
            'nombre' => 'Gloria',
            'apellido' => 'Díaz',
            'activo' => 1,
        ]);

        $responseVincular = $this->actingAs($admin)->post(route('tutores.vincularAlumno', $tutor->id), [
            'alumno_matricula' => $alumno3->matricula,
            'parentesco' => 'PADRE',
            'es_tutor_principal' => false,
        ]);

        $responseVincular->assertRedirect();
        $this->assertDatabaseHas('alumno_tutor', [
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno3->matricula,
        ]);

        // Desvincular el tercer alumno
        $responseDesvincular = $this->actingAs($admin)->delete(route('tutores.desvincularAlumno', [
            'id' => $tutor->id,
            'matricula' => $alumno3->matricula,
        ]));

        $responseDesvincular->assertRedirect();
        $this->assertDatabaseMissing('alumno_tutor', [
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno3->matricula,
        ]);
    }

    public function test_notificar_evento_tutor_job_es_idempotente_y_previene_duplicados(): void
    {
        $tutor = Tutor::create([
            'matricula_alumno' => 'NOTIF_TUT_01',
            't1_nombre' => 'LUCIA SANTOS',
            't1_telefono' => '9217778899',
            't1_email' => 'lucia.santos@example.test',
        ]);

        $wsMock = \Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock->shouldReceive('enviarMensajeDirecto')->once()->andReturn(true);

        $tgMock = \Mockery::mock(\App\Services\TelegramService::class);

        $job = new \App\Jobs\NotificarEventoTutorJob(
            $tutor,
            'Recordatorio de Junta',
            'Junta General 2026',
            '10/11/2026 10:00 AM',
            'Auditorio CAM 48'
        );

        // Primera ejecución: debe enviar WhatsApp y registrar en notificaciones_procesadas
        $job->handle($wsMock, $tgMock);

        $this->assertDatabaseHas('notificaciones_procesadas', [
            'destinatario_tipo' => 'Tutor',
            'destinatario_id' => (string) $tutor->id,
            'estado' => 'ENTREGADO',
        ]);

        // Segunda ejecución con los mismos datos: debe omitir y no volver a enviar al mock
        $job->handle($wsMock, $tgMock);

        // Solo debe haber un registro en la tabla para esta clave
        $idempotencyKey = hash('sha256', "evento:Junta General 2026:tutor:{$tutor->id}:fecha:10/11/2026 10:00 AM");
        $count = \Illuminate\Support\Facades\DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_notificar_asistencia_job_es_idempotente_y_previene_duplicados(): void
    {
        $alumno = Alumno::create([
            'matricula' => 'NOTIF_ALUM_01',
            'nombre' => 'Santiago',
            'apellido' => 'Morales',
            'activo' => 1,
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'ROBERTO MORALES',
            't1_telefono' => '9212223344',
        ]);

        $alumno->setRelation('tutor', $tutor);

        $wsMock = \Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(true);

        $tgMock = \Mockery::mock(\App\Services\TelegramService::class);

        $job = new \App\Jobs\NotificarAsistenciaJob(
            $alumno,
            'ENTRADA',
            '08:15 AM',
            'Santiago Morales'
        );

        // Primera ejecución: debe enviar aviso y registrar en notificaciones_procesadas
        $job->handle($wsMock, $tgMock);

        $this->assertDatabaseHas('notificaciones_procesadas', [
            'destinatario_tipo' => 'Alumno',
            'destinatario_id' => (string) $alumno->matricula,
            'estado' => 'ENTREGADO',
        ]);

        // Segunda ejecución para el mismo alumno/pase/hora: se detecta como duplicado
        $job->handle($wsMock, $tgMock);

        $fechaHoy = now()->toDateString();
        $idempotencyKey = hash('sha256', "asistencia:alumno:{$alumno->matricula}:pase:ENTRADA:fecha:{$fechaHoy}:hora:08:15 AM");
        $count = \Illuminate\Support\Facades\DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $this->assertSame(1, $count);
    }
}
