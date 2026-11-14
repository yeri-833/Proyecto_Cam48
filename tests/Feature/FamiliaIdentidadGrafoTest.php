<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Tutor;
use App\Services\FamiliaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FamiliaIdentidadGrafoTest extends TestCase
{
    private FamiliaService $familiaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->familiaService = new FamiliaService();
    }

    public function test_familia_con_un_solo_alumno(): void
    {
        $alumno = Alumno::create(['matricula' => 'ALU_SOLO_1', 'nombre' => 'Juan', 'apellido' => 'Perez', 'activo' => 1]);
        $tutor = Tutor::create(['t1_nombre' => 'Pedro Perez', 't1_telefono' => '9211111111']);

        DB::table('alumno_tutor')->insert([
            'tutor_id' => $tutor->id,
            'alumno_matricula' => $alumno->matricula,
            'es_tutor_principal' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $claveTutor = $this->familiaService->obtenerClaveFamilia($tutor);
        $claveAlumno = $this->familiaService->obtenerClaveFamiliaAlumno($alumno);

        $this->assertSame('FAM:ALU_SOLO_1', $claveTutor);
        $this->assertSame('FAM:ALU_SOLO_1', $claveAlumno);
    }

    public function test_hermanos_con_mismo_tutor_obtienen_misma_clave_familiar(): void
    {
        $alu1 = Alumno::create(['matricula' => 'ALU_HERM_1', 'nombre' => 'Ana', 'apellido' => 'Lopez', 'activo' => 1]);
        $alu2 = Alumno::create(['matricula' => 'ALU_HERM_2', 'nombre' => 'Beto', 'apellido' => 'Lopez', 'activo' => 1]);
        $tutor = Tutor::create(['t1_nombre' => 'Carmen Lopez', 't1_telefono' => '9212222222']);

        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $tutor->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame('FAM:ALU_HERM_1,ALU_HERM_2', $this->familiaService->obtenerClaveFamilia($tutor));
        $this->assertSame('FAM:ALU_HERM_1,ALU_HERM_2', $this->familiaService->obtenerClaveFamiliaAlumno($alu1));
        $this->assertSame('FAM:ALU_HERM_1,ALU_HERM_2', $this->familiaService->obtenerClaveFamiliaAlumno($alu2));
    }

    public function test_varios_tutores_por_alumno_padre_y_madre_pertenecen_a_la_misma_familia(): void
    {
        $alumno = Alumno::create(['matricula' => 'ALU_MULTI_TUT', 'nombre' => 'Carlos', 'apellido' => 'Gomez', 'activo' => 1]);
        $padre = Tutor::create(['t1_nombre' => 'Roberto Gomez', 't1_telefono' => '9213333331']);
        $madre = Tutor::create(['t1_nombre' => 'Laura Sanchez', 't1_telefono' => '9213333332']);

        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $padre->id, 'alumno_matricula' => $alumno->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $madre->id, 'alumno_matricula' => $alumno->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertTrue($this->familiaService->sonMismaFamilia($padre, $madre));
        $this->assertSame('FAM:ALU_MULTI_TUT', $this->familiaService->obtenerClaveFamilia($padre));
        $this->assertSame('FAM:ALU_MULTI_TUT', $this->familiaService->obtenerClaveFamilia($madre));
    }

    public function test_relaciones_encadenadas_resuelven_el_componente_conexo_completo(): void
    {
        // Tutor A -> Alumno 1
        // Tutor B -> Alumno 1 y Alumno 2
        // Tutor C -> Alumno 2 y Alumno 3
        // Tutor D -> Alumno 3
        $alu1 = Alumno::create(['matricula' => 'ALU_CAD_1', 'nombre' => 'Alu 1', 'apellido' => 'Test', 'activo' => 1]);
        $alu2 = Alumno::create(['matricula' => 'ALU_CAD_2', 'nombre' => 'Alu 2', 'apellido' => 'Test', 'activo' => 1]);
        $alu3 = Alumno::create(['matricula' => 'ALU_CAD_3', 'nombre' => 'Alu 3', 'apellido' => 'Test', 'activo' => 1]);

        $tutorA = Tutor::create(['t1_nombre' => 'Tutor A', 't1_telefono' => '9214444441']);
        $tutorB = Tutor::create(['t1_nombre' => 'Tutor B', 't1_telefono' => '9214444442']);
        $tutorC = Tutor::create(['t1_nombre' => 'Tutor C', 't1_telefono' => '9214444443']);
        $tutorD = Tutor::create(['t1_nombre' => 'Tutor D', 't1_telefono' => '9214444444']);

        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $tutorA->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutorB->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutorB->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutorC->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutorC->id, 'alumno_matricula' => $alu3->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutorD->id, 'alumno_matricula' => $alu3->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $claveEsperada = 'FAM:ALU_CAD_1,ALU_CAD_2,ALU_CAD_3';

        // Todos los tutores en la cadena resuelven exactamente la misma clave canónica
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamilia($tutorA));
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamilia($tutorB));
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamilia($tutorC));
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamilia($tutorD));

        // Todos los alumnos en la cadena resuelven exactamente la misma clave canónica
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamiliaAlumno($alu1));
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamiliaAlumno($alu2));
        $this->assertSame($claveEsperada, $this->familiaService->obtenerClaveFamiliaAlumno($alu3));

        // Todos son la misma familia
        $this->assertTrue($this->familiaService->sonMismaFamilia($tutorA, $tutorD));
        $this->assertTrue($this->familiaService->sonMismaFamilia($tutorB, $tutorC));
    }

    public function test_homonimos_con_alumnos_distintos_permanecen_en_familias_diferentes(): void
    {
        $alu1 = Alumno::create(['matricula' => 'ALU_HOM_1', 'nombre' => 'Alumno Uno', 'apellido' => 'Perez', 'activo' => 1]);
        $alu2 = Alumno::create(['matricula' => 'ALU_HOM_2', 'nombre' => 'Alumno Dos', 'apellido' => 'Ramirez', 'activo' => 1]);

        // Dos tutores con el mismo nombre y teléfono idéntico (homónimos o error de captura)
        $tutor1 = Tutor::create(['t1_nombre' => 'MARIA HERNANDEZ', 't1_telefono' => '9215555555']);
        $tutor2 = Tutor::create(['t1_nombre' => 'MARIA HERNANDEZ', 't1_telefono' => '9215555555']);

        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $tutor1->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor2->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame('FAM:ALU_HOM_1', $this->familiaService->obtenerClaveFamilia($tutor1));
        $this->assertSame('FAM:ALU_HOM_2', $this->familiaService->obtenerClaveFamilia($tutor2));
        $this->assertFalse($this->familiaService->sonMismaFamilia($tutor1, $tutor2));
    }

    public function test_relaciones_circulares_no_provocan_bucle_infinito(): void
    {
        $alu1 = Alumno::create(['matricula' => 'ALU_CIRC_1', 'nombre' => 'Uno', 'apellido' => 'Loop', 'activo' => 1]);
        $alu2 = Alumno::create(['matricula' => 'ALU_CIRC_2', 'nombre' => 'Dos', 'apellido' => 'Loop', 'activo' => 1]);

        $tutor1 = Tutor::create(['t1_nombre' => 'Tutor Uno', 't1_telefono' => '9216666661']);
        $tutor2 = Tutor::create(['t1_nombre' => 'Tutor Dos', 't1_telefono' => '9216666662']);

        // Bucle cruzado: T1 -> A1 y A2; T2 -> A2 y A1
        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $tutor1->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor1->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor2->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor2->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $clave1 = $this->familiaService->obtenerClaveFamilia($tutor1);
        $clave2 = $this->familiaService->obtenerClaveFamilia($tutor2);

        $this->assertSame('FAM:ALU_CIRC_1,ALU_CIRC_2', $clave1);
        $this->assertSame($clave1, $clave2);
    }

    public function test_desvinculacion_de_alumno_actualiza_las_claves_familiares(): void
    {
        $alu1 = Alumno::create(['matricula' => 'ALU_DESV_1', 'nombre' => 'Uno', 'apellido' => 'Split', 'activo' => 1]);
        $alu2 = Alumno::create(['matricula' => 'ALU_DESV_2', 'nombre' => 'Dos', 'apellido' => 'Split', 'activo' => 1]);

        $tutor = Tutor::create(['t1_nombre' => 'Tutor Inicial', 't1_telefono' => '9217777777']);

        DB::table('alumno_tutor')->insert([
            ['tutor_id' => $tutor->id, 'alumno_matricula' => $alu1->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tutor_id' => $tutor->id, 'alumno_matricula' => $alu2->matricula, 'es_tutor_principal' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame('FAM:ALU_DESV_1,ALU_DESV_2', $this->familiaService->obtenerClaveFamilia($tutor));

        // Desvincular al alumno 2
        DB::table('alumno_tutor')
            ->where('tutor_id', $tutor->id)
            ->where('alumno_matricula', $alu2->matricula)
            ->delete();

        $tutor->refresh();

        $this->assertSame('FAM:ALU_DESV_1', $this->familiaService->obtenerClaveFamilia($tutor));
        $this->assertSame('FAM:ALU_DESV_2', $this->familiaService->obtenerClaveFamiliaAlumno($alu2));
    }
}
