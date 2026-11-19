<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Personal;
use App\Models\User;
use App\Policies\ArchivoSistemaPolicy;
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
    public function test_listado_selector_y_policy_deniegan_todos_los_formatos_docentes(string $asignacion, string $grado, string $grupo): void
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

        $this->assertExpedientesDenegadosSinEfectos($docente, [$alumno], [$archivo]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function formatosDeAsignacion(): array
    {
        return [
            'espacios y simbolo' => ['1° A', ' 1° ', ' a '],
            'guion' => ['1 - A', ' 1 ', ' A '],
            'compacto' => ['2b', '2°', 'b'],
            'grupo de varias letras' => ['10 AB', ' 10° ', 'aB'],
            'solo grupo' => ['A', '1', 'A'],
            'varias asignaciones' => ['1 A / 2 B', '1', 'A'],
            'sin asignacion' => ['', '1', 'A'],
            'otro grado' => ['1 A', '2', 'A'],
            'otro grupo' => ['1 A', '1', 'B'],
            'simbolos repetidos' => ['1 A', '1°°', 'A'],
            'grupo con acento no equivalente' => ['1 A', '1', 'Á'],
            'grupo con i sin punto no equivalente' => ['1 I', '1', 'ı'],
            'grupo con s larga no equivalente' => ['1 S', '1', 'ſ'],
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

        $this->assertExpedientesDenegadosSinEfectos($docente, [$alumno], [$archivo]);
    }

    /**
     * Docente asignado a 1° A:
     * No obtiene acceso a alumnos ni documentos de ningun grado o grupo.
     */
    public function test_docente_1_a_no_visualiza_alumnos_ni_expedientes_de_ningun_grupo(): void
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

        $this->assertExpedientesDenegadosSinEfectos(
            $userDocente,
            [$alumno1A, $alumno2A, $alumno1B],
            ArchivoSistema::all()->all(),
        );
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

        $this->assertExpedientesDenegadosSinEfectos($userDocente, [$alumno1A], ArchivoSistema::all()->all());
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

        $this->assertExpedientesDenegadosSinEfectos($userAmbiguo, Alumno::all()->all(), []);
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

        $this->assertExpedientesDenegadosSinEfectos($userUnknown, [], []);
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
        $this->assertSame('Contenido confidencial', Storage::disk('local')->get('expedientes/serv_secreto_3c.pdf'));
        $this->assertExpedientesDenegadosSinEfectos($userDocente, [$alumnoAjeno], [$archivoAjeno]);
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
        $this->assertDatabaseCount('archivos_sistema', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertExpedientesDenegadosSinEfectos($userDocente, [$alumnoAjeno], []);
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
        $response->assertForbidden();
        $response->assertDontSee('ALUM_SEARCH_1A')->assertDontSee('ALUM_SEARCH_2A');
        $response->assertDontSee('reporte_ricardo_1a.pdf')->assertDontSee('reporte_ricardo_2a.pdf');
        $this->assertExpedientesDenegadosSinEfectos(
            $userDocente,
            [$alumno1A, $alumno2A],
            ArchivoSistema::all()->all(),
        );
    }

    /**
     * @param  array<int, Alumno>  $alumnos
     * @param  array<int, ArchivoSistema>  $archivos
     */
    private function assertExpedientesDenegadosSinEfectos(User $usuario, array $alumnos, array $archivos): void
    {
        $policy = new ArchivoSistemaPolicy;
        $alumnosAntes = Alumno::all()->map->getAttributes()->all();
        $archivosAntes = ArchivoSistema::all()->map->getAttributes()->all();
        $almacenamientoAntes = [];
        foreach (['local', 'public'] as $disco) {
            foreach (Storage::disk($disco)->allFiles() as $ruta) {
                $almacenamientoAntes[$disco][$ruta] = Storage::disk($disco)->get($ruta);
            }
        }

        $this->assertFalse($policy->viewAny($usuario));
        $this->assertFalse($policy->create($usuario));
        $this->actingAs($usuario);
        $listado = $this->get(route('expedientes.index'))->assertForbidden();
        $busqueda = $this->get(route('expedientes.index', ['busqueda' => 'Alumno']))->assertForbidden();
        foreach ($alumnos as $alumno) {
            $this->assertFalse($policy->canAccessAlumno($usuario, $alumno));
            $this->assertFalse($policy->create($usuario, $alumno));
            $listado->assertDontSee($alumno->matricula);
            $busqueda->assertDontSee($alumno->matricula);
            $this->post(route('expedientes.store'), [
                'matricula' => $alumno->matricula,
                'archivo' => UploadedFile::fake()->create('denegado.pdf', 1, 'application/pdf'),
            ])->assertForbidden();
        }
        foreach ($archivos as $archivo) {
            $this->assertFalse($policy->view($usuario, $archivo));
            $this->assertFalse($policy->delete($usuario, $archivo));
            $listado->assertDontSee($archivo->nombre_original)->assertDontSee($archivo->nombre_servidor);
            $busqueda->assertDontSee($archivo->nombre_original)->assertDontSee($archivo->nombre_servidor);
            $visualizacion = $this->get(route('expedientes.ver', $archivo->id))->assertForbidden();
            $descarga = $this->get(route('expedientes.descargar', $archivo->id))->assertForbidden();
            $visualizacion->assertDontSee($archivo->nombre_original)->assertDontSee($archivo->nombre_servidor);
            $descarga->assertDontSee($archivo->nombre_original)->assertDontSee($archivo->nombre_servidor);
            $ruta = 'expedientes/'.$archivo->nombre_servidor;
            if (Storage::disk('local')->exists($ruta)) {
                $contenido = Storage::disk('local')->get($ruta);
                $visualizacion->assertDontSee($contenido);
                $descarga->assertDontSee($contenido);
            }
            $this->delete(route('expedientes.destroy', $archivo->id))->assertForbidden();
        }

        $this->assertSame($alumnosAntes, Alumno::all()->map->getAttributes()->all());
        $this->assertSame($archivosAntes, ArchivoSistema::all()->map->getAttributes()->all());
        $almacenamientoDespues = [];
        foreach (['local', 'public'] as $disco) {
            foreach (Storage::disk($disco)->allFiles() as $ruta) {
                $almacenamientoDespues[$disco][$ruta] = Storage::disk($disco)->get($ruta);
            }
        }
        $this->assertSame($almacenamientoAntes, $almacenamientoDespues);
    }
}
