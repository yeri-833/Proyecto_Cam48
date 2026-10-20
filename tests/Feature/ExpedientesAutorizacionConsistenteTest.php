<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpedientesAutorizacionConsistenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    #[DataProvider('formatosDeAsignacion')]
    public function test_listado_selector_y_policy_coinciden_para_formatos_escolares(string $asignacion, string $grado, string $grupo, bool $permitido): void
    {
        $docente = User::factory()->create(['role' => 'Docente']);
        Personal::create([
            'id' => 9201,
            'nombre_completo' => 'Docente ficticio',
            'email' => $docente->email,
            'grado_grupo' => $asignacion,
            'activo' => 1,
        ]);
        $alumno = Alumno::create([
            'matricula' => 'FICT_FORMATO',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
            'grado' => $grado,
            'grupo' => $grupo,
            'activo' => 1,
        ]);
        $archivo = ArchivoSistema::create([
            'matricula' => $alumno->matricula,
            'nombre_original' => 'ficticio.pdf',
            'nombre_servidor' => 'ficticio.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1,
            'fecha_subida' => now(),
        ]);
        Storage::disk('local')->put('expedientes/ficticio.pdf', 'documento ficticio');

        $response = $this->actingAs($docente)->get(route('expedientes.index'));
        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertCount($permitido ? 1 : 0, $props['archivos']['data']);
        $this->assertCount($permitido ? 1 : 0, $props['alumnos']);
        $this->get(route('expedientes.ver', $archivo->id))->assertStatus($permitido ? 200 : 403);
        $this->get(route('expedientes.descargar', $archivo->id))->assertStatus($permitido ? 200 : 403);

        $upload = $this->post(route('expedientes.store'), [
            'matricula' => $alumno->matricula,
            'archivo' => UploadedFile::fake()->create('ficticio_nuevo.pdf', 1, 'application/pdf'),
        ]);
        $upload->assertStatus($permitido ? 302 : 403);
        $this->assertSame($permitido ? 2 : 1, ArchivoSistema::count());
    }

    /** @return array<string, array{string, string, string, bool}> */
    public static function formatosDeAsignacion(): array
    {
        return [
            'espacios y simbolo' => ['1° A', ' 1° ', ' a ', true],
            'guion' => ['1 - A', ' 1 ', ' A ', true],
            'compacto' => ['2b', '2°', 'b', true],
            'grupo de varias letras' => ['10 AB', ' 10° ', 'aB', true],
            'solo grupo' => ['A', '1', 'A', false],
            'varias asignaciones' => ['1 A / 2 B', '1', 'A', false],
            'sin asignacion' => ['', '1', 'A', false],
            'otro grado' => ['1 A', '2', 'A', false],
            'otro grupo' => ['1 A', '1', 'B', false],
            'simbolos repetidos' => ['1 A', '1°°', 'A', false],
            'grupo con acento no equivalente' => ['1 A', '1', 'Á', false],
            'grupo con i sin punto no equivalente' => ['1 I', '1', 'ı', false],
            'grupo con s larga no equivalente' => ['1 S', '1', 'ſ', false],
        ];
    }

    public function test_fichas_activas_duplicadas_no_conceden_un_ambito_arbitrario(): void
    {
        $docente = User::factory()->create(['role' => 'Docente']);
        foreach (['1 A', '2 B'] as $indice => $asignacion) {
            Personal::create([
                'id' => 9301 + $indice,
                'nombre_completo' => 'Personal ficticio',
                'email' => $docente->email,
                'grado_grupo' => $asignacion,
                'activo' => 1,
            ]);
        }
        $alumno = Alumno::create([
            'matricula' => 'FICT_DUPLICADO',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);
        $archivo = ArchivoSistema::create([
            'matricula' => $alumno->matricula,
            'nombre_original' => 'ficticio.pdf',
            'nombre_servidor' => 'ficticio.pdf',
            'fecha_subida' => now(),
        ]);

        $response = $this->actingAs($docente)->get(route('expedientes.index'));
        $response->assertOk();
        $this->assertEmpty($response->viewData('page')['props']['archivos']['data']);
        $this->assertEmpty($response->viewData('page')['props']['alumnos']);
        $this->get(route('expedientes.ver', $archivo->id))->assertForbidden();
    }

    /**
     * Docente asignado a 1° A:
     * - Alumno de 1° A: permitido en listado.
     * - Alumno de 2° A: no permitido.
     * - Alumno de 1° B: no permitido.
     */
    public function test_docente_1_a_solo_visualiza_alumnos_y_expedientes_de_su_grado_y_grupo(): void
    {
        $userDocente = User::create([
            'nombre_completo' => 'Docente 1A',
            'correo' => 'docente_1a@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9101,
            'nombre_completo' => 'Docente 1A',
            'email' => 'docente_1a@cam48.test',
            'grado_grupo' => '1° A',
            'activo' => 1,
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'MAT_1A_OK',
            'nombre' => 'Alumno',
            'apellido' => 'Primero A',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $alumno2A = Alumno::create([
            'matricula' => 'MAT_2A_NO',
            'nombre' => 'Alumno',
            'apellido' => 'Segundo A',
            'grado' => '2',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $alumno1B = Alumno::create([
            'matricula' => 'MAT_1B_NO',
            'nombre' => 'Alumno',
            'apellido' => 'Primero B',
            'grado' => '1',
            'grupo' => 'B',
            'activo' => 1,
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno1A->matricula,
            'nombre_original' => 'doc_1a.pdf',
            'nombre_servidor' => 'serv_1a.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno2A->matricula,
            'nombre_original' => 'doc_2a.pdf',
            'nombre_servidor' => 'serv_2a.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno1B->matricula,
            'nombre_original' => 'doc_1b.pdf',
            'nombre_servidor' => 'serv_1b.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        $response = $this->actingAs($userDocente)->get('/expedientes');
        $response->assertOk();

        $archivos = $response->viewData('page')['props']['archivos']['data'];
        $matriculasEnListado = collect($archivos)->pluck('matricula')->all();

        $this->assertContains('MAT_1A_OK', $matriculasEnListado);
        $this->assertNotContains('MAT_2A_NO', $matriculasEnListado);
        $this->assertNotContains('MAT_1B_NO', $matriculasEnListado);

        // Selector de alumnos en modal también restringido
        $alumnosDisponibles = collect($response->viewData('page')['props']['alumnos'])->pluck('matricula')->all();
        $this->assertContains('MAT_1A_OK', $alumnosDisponibles);
        $this->assertNotContains('MAT_2A_NO', $alumnosDisponibles);
        $this->assertNotContains('MAT_1B_NO', $alumnosDisponibles);
    }

    /**
     * Asignación '1A' y '1° A' tienen comportamiento consistente e idéntico.
     */
    public function test_asignacion_1a_sin_simbolo_de_grado_tiene_comportamiento_consistente(): void
    {
        $userDocente = User::create([
            'nombre_completo' => 'Docente 1A Sin Grado',
            'correo' => 'docente_1a_sin@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9102,
            'nombre_completo' => 'Docente 1A Sin Grado',
            'email' => 'docente_1a_sin@cam48.test',
            'grado_grupo' => '1A',
            'activo' => 1,
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'MAT_1A_SIN_DEGREE',
            'nombre' => 'Alumno',
            'apellido' => 'Grado Con Grado Simbolo',
            'grado' => '1°',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno1A->matricula,
            'nombre_original' => 'doc_1a_sin.pdf',
            'nombre_servidor' => 'serv_1a_sin.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        $response = $this->actingAs($userDocente)->get('/expedientes');
        $response->assertOk();

        $archivos = $response->viewData('page')['props']['archivos']['data'];
        $this->assertCount(1, $archivos);
        $this->assertSame('MAT_1A_SIN_DEGREE', $archivos[0]['matricula']);
    }

    /**
     * Asignación ambigua 'A' (solo grupo, sin grado) no debe revelar alumnos de todos los grados (fail-secure).
     */
    public function test_asignacion_ambigua_solo_grupo_no_revela_alumnos_de_todos_los_grados(): void
    {
        $userAmbiguo = User::create([
            'nombre_completo' => 'Docente Ambiguo',
            'correo' => 'ambiguo@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9103,
            'nombre_completo' => 'Docente Ambiguo',
            'email' => 'ambiguo@cam48.test',
            'grado_grupo' => 'A', // Asignación ambigua peligrosa
            'activo' => 1,
        ]);

        Alumno::create([
            'matricula' => 'ALUM_G1A',
            'nombre' => 'Uno',
            'apellido' => 'A',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        Alumno::create([
            'matricula' => 'ALUM_G2A',
            'nombre' => 'Dos',
            'apellido' => 'A',
            'grado' => '2',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $response = $this->actingAs($userAmbiguo)->get('/expedientes');
        $response->assertOk();

        // Debe ser 0 resultados por fail-secure
        $archivos = $response->viewData('page')['props']['archivos']['data'];
        $this->assertEmpty($archivos);

        $alumnos = $response->viewData('page')['props']['alumnos'];
        $this->assertEmpty($alumnos);
    }

    /**
     * Asignación desconocida o personal inactivo: ningún documento visible.
     */
    public function test_asignacion_desconocida_ningun_documento_visible(): void
    {
        $userUnknown = User::create([
            'nombre_completo' => 'Docente Desconocido',
            'correo' => 'unknown@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9104,
            'nombre_completo' => 'Docente Desconocido',
            'email' => 'unknown@cam48.test',
            'grado_grupo' => 'Intendencia / Sin Asignacion',
            'activo' => 1,
        ]);

        $response = $this->actingAs($userUnknown)->get('/expedientes');
        $response->assertOk();

        $this->assertEmpty($response->viewData('page')['props']['archivos']['data']);
    }

    /**
     * Descarga y visualización directa de documento ajeno retorna 403 Forbidden.
     */
    public function test_descarga_y_visualizacion_directa_de_documento_ajeno_retorna_403(): void
    {
        $userDocente = User::create([
            'nombre_completo' => 'Docente 1A Check',
            'correo' => 'docente_check@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9105,
            'nombre_completo' => 'Docente 1A Check',
            'email' => 'docente_check@cam48.test',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $alumnoAjeno = Alumno::create([
            'matricula' => 'ALUM_AJENO_SEC',
            'nombre' => 'Alumno',
            'apellido' => 'Ajeno',
            'grado' => '3',
            'grupo' => 'C',
            'activo' => 1,
        ]);

        $archivoAjeno = ArchivoSistema::create([
            'matricula' => $alumnoAjeno->matricula,
            'nombre_original' => 'secreto_3c.pdf',
            'nombre_servidor' => 'serv_secreto_3c.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        Storage::disk('local')->put('expedientes/serv_secreto_3c.pdf', 'Contenido confidencial');

        // Intento de ver documento ajeno
        $resVer = $this->actingAs($userDocente)->get("/expedientes/{$archivoAjeno->id}/ver");
        $resVer->assertStatus(403);

        // Intento de descargar documento ajeno
        $resDescarga = $this->actingAs($userDocente)->get("/expedientes/{$archivoAjeno->id}/descargar");
        $resDescarga->assertStatus(403);
    }

    /**
     * Carga de documento a alumno ajeno es rechazada con 403 Forbidden.
     */
    public function test_carga_de_documento_a_alumno_ajeno_retorna_403(): void
    {
        $userDocente = User::create([
            'nombre_completo' => 'Docente 1A Upload',
            'correo' => 'docente_upload@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9106,
            'nombre_completo' => 'Docente 1A Upload',
            'email' => 'docente_upload@cam48.test',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $alumnoAjeno = Alumno::create([
            'matricula' => 'ALUM_AJENO_UP',
            'nombre' => 'Alumno',
            'apellido' => 'Ajeno',
            'grado' => '2',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $file = UploadedFile::fake()->create('evaluacion.pdf', 500, 'application/pdf');

        $response = $this->actingAs($userDocente)->post('/expedientes', [
            'matricula' => $alumnoAjeno->matricula,
            'archivo' => $file,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Paginación y búsqueda: la búsqueda no revela metadatos fuera del alcance del docente.
     */
    public function test_busqueda_no_revela_metadatos_fuera_del_alcance_autorizado(): void
    {
        $userDocente = User::create([
            'nombre_completo' => 'Docente 1A Search',
            'correo' => 'docente_search@cam48.test',
            'password' => Hash::make('password123'),
            'tipo_usuario' => 'Docente',
        ]);

        Personal::create([
            'id' => 9107,
            'nombre_completo' => 'Docente 1A Search',
            'email' => 'docente_search@cam48.test',
            'grado_grupo' => '1 A',
            'activo' => 1,
        ]);

        $alumno1A = Alumno::create([
            'matricula' => 'ALUM_SEARCH_1A',
            'nombre' => 'RICARDO',
            'apellido' => 'HERNANDEZ',
            'grado' => '1',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        $alumno2A = Alumno::create([
            'matricula' => 'ALUM_SEARCH_2A',
            'nombre' => 'RICARDO',
            'apellido' => 'HERNANDEZ',
            'grado' => '2',
            'grupo' => 'A',
            'activo' => 1,
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno1A->matricula,
            'nombre_original' => 'reporte_ricardo_1a.pdf',
            'nombre_servidor' => 'ricardo_1a.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        ArchivoSistema::create([
            'matricula' => $alumno2A->matricula,
            'nombre_original' => 'reporte_ricardo_2a.pdf',
            'nombre_servidor' => 'ricardo_2a.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'fecha_subida' => now(),
        ]);

        // Docente busca "RICARDO"
        $response = $this->actingAs($userDocente)->get('/expedientes?busqueda=RICARDO');
        $response->assertOk();

        $archivos = $response->viewData('page')['props']['archivos']['data'];
        $this->assertCount(1, $archivos);
        $this->assertSame('ALUM_SEARCH_1A', $archivos[0]['matricula']);
        $this->assertSame('reporte_ricardo_1a.pdf', $archivos[0]['nombre_original']);
    }
}
