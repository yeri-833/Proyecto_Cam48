<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistorialExportacionTest extends TestCase
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

    /**
     * Prueba 1: Descarga básica con cabeceras y tipo MIME válido.
     */
    public function test_exportacion_historial_descarga_csv_cabeceras_y_mime(): void
    {
        $admin = User::factory()->create(['tipo_usuario' => 'Director']);
        $fecha = Carbon::today()->toDateString();

        $response = $this->actingAs($admin)->get("/historial/exportar?fecha={$fecha}");

        $response->assertOk();
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', "attachment; filename=Reporte_Asistencias_{$fecha}.csv");
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-type'));
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $response->streamedContent());
    }

    /**
     * Pruebas 2-7: Matriz de filtrado por rol (6 casos).
     */
    #[DataProvider('proveedorFiltroRoles')]
    public function test_exportacion_filtrado_por_rol(string $rolFiltro, ?string $rolEsperadoEnCsv, bool $debeContener): void
    {
        $admin = User::factory()->create(['tipo_usuario' => 'Director']);
        $fecha = Carbon::today()->toDateString();

        // Crear alumno, personal y tutor con asistencia hoy
        $alumno = Alumno::create(['matricula' => 'EXP_ALUM_01', 'nombre' => 'Luis', 'apellido' => 'García', 'activo' => 1]);
        $personal = Personal::create(['id' => 'EXP_DOC_01', 'nombre_completo' => 'Profesor Carlos', 'activo' => 1]);
        $tutor = Tutor::create(['matricula_alumno' => $alumno->matricula, 't1_nombre' => 'María García']);

        RegistroAsistencia::create(['asistible_type' => Alumno::class, 'asistible_id' => $alumno->matricula, 'fecha' => $fecha, 'hora' => '08:00:00']);
        RegistroAsistencia::create(['asistible_type' => Personal::class, 'asistible_id' => $personal->id, 'fecha' => $fecha, 'hora' => '08:15:00']);
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor->id, 'fecha' => $fecha, 'hora' => '08:30:00']);

        $response = $this->actingAs($admin)->get("/historial/exportar?fecha={$fecha}&rol={$rolFiltro}");
        $response->assertOk();
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', "attachment; filename=Reporte_Asistencias_{$fecha}.csv");
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $csv);

        if ($rolEsperadoEnCsv !== null) {
            if ($debeContener) {
                $this->assertStringContainsString($rolEsperadoEnCsv, $csv);
            } else {
                $this->assertStringNotContainsString($rolEsperadoEnCsv, $csv);
            }
        } else {
            // Caso Todos: debe contener los 3 roles
            $this->assertStringContainsString('Alumno', $csv);
            $this->assertStringContainsString('Personal', $csv);
            $this->assertStringContainsString('Tutor', $csv);
        }
    }

    public static function proveedorFiltroRoles(): array
    {
        return [
            'todos los roles' => ['Todos', null, true],
            'filtro exclusivo alumno' => ['Alumno', 'Profesor Carlos', false],
            'filtro exclusivo personal' => ['Personal', 'Profesor Carlos', true],
            'filtro exclusivo tutor' => ['Tutor', 'María García', true],
            'filtro rol invalido omite' => ['Invalido', 'Desconocido', false],
            'filtro cadena vacia equivale a todos' => ['', null, true],
        ];
    }

    /**
     * Pruebas 8-15: Matriz de búsqueda por término (8 casos).
     */
    #[DataProvider('proveedorFiltroBusqueda')]
    public function test_exportacion_filtrado_por_busqueda(string $termino, string $coincidenciaEsperada, bool $debeAparecer): void
    {
        $admin = User::factory()->create(['tipo_usuario' => 'Secretaria']);
        $fecha = Carbon::today()->toDateString();

        $alumno = Alumno::create(['matricula' => 'MAT_SRCH_99', 'nombre' => 'Sebastián', 'apellido' => 'Montiel', 'activo' => 1]);
        $personal = Personal::create(['id' => 'DOC_SRCH_88', 'nombre_completo' => 'Patricia Velázquez', 'activo' => 1]);
        $tutor = Tutor::create(['matricula_alumno' => $alumno->matricula, 't1_nombre' => 'Fernanda Montiel']);

        RegistroAsistencia::create(['asistible_type' => Alumno::class, 'asistible_id' => $alumno->matricula, 'fecha' => $fecha, 'hora' => '07:50:00']);
        RegistroAsistencia::create(['asistible_type' => Personal::class, 'asistible_id' => $personal->id, 'fecha' => $fecha, 'hora' => '08:00:00']);
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutor->id, 'fecha' => $fecha, 'hora' => '08:10:00']);

        $response = $this->actingAs($admin)->get('/historial/exportar?fecha='.$fecha.'&busqueda='.urlencode($termino));
        $response->assertOk();
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', "attachment; filename=Reporte_Asistencias_{$fecha}.csv");
        $csv = $response->streamedContent();
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $csv);
        $this->assertStringContainsString('Alumno Relacionado', $csv);

        if ($debeAparecer) {
            $this->assertStringContainsString($coincidenciaEsperada, $csv);
        } else {
            $this->assertStringNotContainsString($coincidenciaEsperada, $csv);
        }
    }

    public static function proveedorFiltroBusqueda(): array
    {
        return [
            'busqueda por matricula alumno' => ['MAT_SRCH_99', 'MAT_SRCH_99', true],
            'busqueda por nombre alumno' => ['Sebastián', 'Sebastián Montiel', true],
            'busqueda por apellido alumno' => ['Montiel', 'Sebastián Montiel', true],
            'busqueda por id personal' => ['DOC_SRCH_88', 'DOC_SRCH_88', true],
            'busqueda por nombre personal' => ['Patricia', 'Patricia Velázquez', true],
            'busqueda por nombre tutor' => ['Fernanda', 'Fernanda Montiel', true],
            'busqueda por subcadena parcial' => ['Veláz', 'Patricia Velázquez', true],
            'busqueda sin coincidencias' => ['INEXISTENTE_XYZ', 'Sebastián Montiel', false],
        ];
    }

    /**
     * Perfiles vigentes, aliases compatibles y perfiles retirados en la exportación.
     */
    #[DataProvider('proveedorRolesAutorizacion')]
    public function test_exportacion_matriz_autorizacion_rbac(?string $role, int $codigoHttpEsperado): void
    {
        $fecha = Carbon::today()->toDateString();
        $url = "/historial/exportar?fecha={$fecha}";

        if ($role !== null) {
            $user = User::factory()->create(['tipo_usuario' => $role]);
            $response = $this->actingAs($user)->get($url);
        } else {
            // Usuario no autenticado (invitado)
            $response = $this->get($url);
        }

        $response->assertStatus($codigoHttpEsperado);

        if ($codigoHttpEsperado === 200) {
            $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
            $response->assertHeader('Content-Disposition', "attachment; filename=Reporte_Asistencias_{$fecha}.csv");
            $this->assertSame('no-cache', $response->headers->get('Pragma'));
            $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $response->streamedContent());
        } elseif ($codigoHttpEsperado === 403) {
            $this->assertTrue($response->isForbidden());
            $this->assertNull($response->headers->get('Content-Disposition'));
            $this->assertNotSame('text/csv; charset=UTF-8', (string) $response->headers->get('Content-type'));
            $this->assertFalse($response->isOk());
        } else {
            $this->assertTrue($response->isRedirect());
            $this->assertNull($response->headers->get('Content-Disposition'));
            $this->assertNotSame('text/csv; charset=UTF-8', (string) $response->headers->get('Content-type'));
            $this->assertFalse($response->isOk());
        }
    }

    public static function proveedorRolesAutorizacion(): array
    {
        return [
            'director autorizado' => ['Director', 200],
            'directivo compatible autorizado' => ['Directivo', 200],
            'secretaria autorizada' => ['Secretaria', 200],
            'secretario compatible autorizado' => ['Secretario', 200],
            'admin retirado denegado' => ['admin', 403],
            'administrador retirado denegado' => ['Administrador', 403],
            'personal retirado denegado' => ['personal', 403],
            'docente retirado denegado' => ['Docente', 403],
            'alumno denegado' => ['alumno', 403],
            'kiosco denegado' => ['kiosco', 403],
            'operador denegado' => ['Operador', 403],
            'invitado no autenticado redirige' => [null, 302],
        ];
    }

    /**
     * Pruebas 21-25: Sanitización, escape y formato CSV (5 casos).
     */
    #[DataProvider('proveedorSanitizacionCsv')]
    public function test_exportacion_sanitizacion_y_formato_csv(string $escenario, string $patronBuscado): void
    {
        $admin = User::factory()->create(['tipo_usuario' => 'Director']);
        $fecha = Carbon::today()->toDateString();

        $alumno = Alumno::create(['matricula' => 'SAN_01', 'nombre' => 'José, María', 'apellido' => 'Pérez "El Rápido"', 'activo' => 1]);
        $tutorSinAlumno = Tutor::create(['matricula_alumno' => null, 't1_nombre' => 'Tutor Solitario']);

        RegistroAsistencia::create(['asistible_type' => Alumno::class, 'asistible_id' => $alumno->matricula, 'fecha' => $fecha, 'hora' => '08:45:00']);
        RegistroAsistencia::create(['asistible_type' => Tutor::class, 'asistible_id' => (string) $tutorSinAlumno->id, 'fecha' => $fecha, 'hora' => '09:00:00']);

        $response = $this->actingAs($admin)->get("/historial/exportar?fecha={$fecha}");
        $response->assertOk();
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', "attachment; filename=Reporte_Asistencias_{$fecha}.csv");
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $csv);

        $this->assertStringContainsString($patronBuscado, $csv, "Fallo en escenario: {$escenario}");
    }

    public static function proveedorSanitizacionCsv(): array
    {
        return [
            'nombre con coma escapado entre comillas' => ['coma', '"José, María'],
            'comillas dobles escapadas correctamente' => ['comillas', '""El Rápido"""'],
            'bom utf8 presente al inicio' => ['bom', chr(0xEF).chr(0xBB).chr(0xBF)],
            'tutor sin alumno muestra etiqueta sin asignar' => ['sin_asignar', 'Sin asignar'],
            'formato de hora hh:mm am/pm' => ['hora', 'AM'],
        ];
    }

    /**
     * Pruebas 26-30: Casos límite, chunking y rendimiento (5 casos).
     */
    #[DataProvider('proveedorCasosLimite')]
    public function test_exportacion_casos_limite_y_rendimiento(string $caso, string $asercionClave): void
    {
        $admin = User::factory()->create(['tipo_usuario' => 'Director']);
        $fecha = Carbon::today()->toDateString();

        // Crear lote de asistencias
        for ($i = 1; $i <= 3; $i++) {
            $alum = Alumno::create(['matricula' => "LOTE_{$i}", 'nombre' => "Alumno {$i}", 'apellido' => 'Test', 'activo' => 1]);
            RegistroAsistencia::create(['asistible_type' => Alumno::class, 'asistible_id' => $alum->matricula, 'fecha' => $fecha, 'hora' => sprintf('%02d:00:00', 7 + $i)]);
        }

        $response = $this->actingAs($admin)->get("/historial/exportar?fecha={$fecha}");
        $response->assertOk();
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', "attachment; filename=Reporte_Asistencias_{$fecha}.csv");
        $csv = $response->streamedContent();
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $csv);

        switch ($caso) {
            case 'cabeceras_anti_cache':
                $this->assertSame('no-cache', $response->headers->get('Pragma'));
                $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
                break;
            case 'encabezados_csv_exactos':
                $this->assertStringContainsString('Hora,Identificacion,"Nombre Completo",Rol,"Alumno Relacionado"', $csv);
                $this->assertStringContainsString('Rol', $csv);
                break;
            case 'descarga_fecha_sin_datos':
                $respVacia = $this->actingAs($admin)->get('/historial/exportar?fecha=1999-01-01');
                $respVacia->assertOk();
                $csvVacio = $respVacia->streamedContent();
                $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $csvVacio);
                $this->assertStringContainsString('Alumno Relacionado', $csvVacio);
                break;
            case 'orden_cronologico_descendente':
                $pos10 = strpos($csv, '10:00 AM');
                $pos08 = strpos($csv, '08:00 AM');
                $this->assertTrue($pos10 !== false && $pos08 !== false && $pos10 < $pos08, 'El registro de las 10:00 AM debe aparecer antes que el de las 08:00 AM.');
                break;
            case 'lote_completo_renderizado':
                $this->assertStringContainsString('LOTE_1', $csv);
                $this->assertStringContainsString('LOTE_2', $csv);
                $this->assertStringContainsString('LOTE_3', $csv);
                break;
        }
    }

    public static function proveedorCasosLimite(): array
    {
        return [
            'cabeceras anti cache' => ['cabeceras_anti_cache', 'Pragma'],
            'encabezados csv exactos' => ['encabezados_csv_exactos', 'Identificacion'],
            'descarga fecha sin datos genera cabeceras limpias' => ['descarga_fecha_sin_datos', 'Alumno Relacionado'],
            'orden cronologico descendente por hora' => ['orden_cronologico_descendente', 'orden'],
            'lote completo renderizado en stream' => ['lote_completo_renderizado', 'LOTE_3'],
        ];
    }
}
