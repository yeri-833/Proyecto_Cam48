<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Evento;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EstadisticasController extends Controller
{
    /**
     * Resuelve la clave canónica de familia independiente de la identidad individual del tutor.
     * Delega en FamiliaService para garantizar consistencia entre hermanos y aislamiento de identidades.
     */
    private function getFamiliaKey(Tutor $tutor): string
    {
        return $tutor->familia_key;
    }

    /**
     * Calcula las métricas y datos para el Dashboard de Estadísticas.
     * Usa el esquema polimórfico (asistible_type / asistible_id) y
     * métricas avanzadas de participación de padres y tutores.
     */
    public function index()
    {
        Gate::authorize('estadisticas.consultar');

        // ─────────────────────────────────────────────────────────
        // 1. KPIs Generales
        // ─────────────────────────────────────────────────────────
        $diasConAsistencia = RegistroAsistencia::distinct('fecha')->count('fecha');

        $totalAlumnos = Alumno::where('activo', 1)->count();

        $asistenciasHoy = RegistroAsistencia::where('asistible_type', Alumno::class)
            ->whereDate('fecha', Carbon::today())
            ->distinct('asistible_id')
            ->count('asistible_id');

        $promedioHoy = ($totalAlumnos > 0) ? round(($asistenciasHoy / $totalAlumnos) * 100) : 0;

        // Tutores sin alumnos vinculados (pendientes)
        $tutoresInactivos = Tutor::whereDoesntHave('alumno')->count();

        // ─────────────────────────────────────────────────────────
        // 2. Gráfica de Barras: últimos 7 días con actividad
        // ─────────────────────────────────────────────────────────
        $asistenciasDias = RegistroAsistencia::select('fecha', DB::raw('COUNT(*) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha', 'desc')
            ->limit(7)
            ->get()
            ->map(fn ($item) => [
                'dia' => Carbon::parse($item->fecha)->format('d/m'),
                'total' => (int) $item->total,
            ])
            ->reverse()
            ->values();

        // ─────────────────────────────────────────────────────────
        // 3. Gráfica de Dona: participación por tipo de persona
        //    Mapeamos asistible_type → etiqueta amigable
        // ─────────────────────────────────────────────────────────
        $typeMap = [
            Alumno::class => 'Alumnos',
            Tutor::class => 'Tutores',
            Personal::class => 'Personal',
        ];

        $participacionRoles = RegistroAsistencia::select('asistible_type', DB::raw('COUNT(*) as total'))
            ->groupBy('asistible_type')
            ->get()
            ->map(fn ($item) => [
                'tipo' => $typeMap[$item->asistible_type] ?? 'Otro',
                'total' => (int) $item->total,
            ])
            ->groupBy('tipo')
            ->map(fn ($group, $key) => [
                'tipo' => $key,
                'total' => $group->sum('total'),
            ])
            ->values();

        // ─────────────────────────────────────────────────────────
        // 4. Ranking de Tutores más comprometidos (Top 20)
        //    Deduplica escaneos duplicados en la misma junta o día (máximo 1 asistencia por sesión).
        // ─────────────────────────────────────────────────────────
        $driver = DB::connection()->getDriverName();
        $castEvento = $driver === 'sqlite' ? 'CAST(evento_id AS TEXT)' : 'CAST(evento_id AS CHAR)';
        $sesionExpr = "COALESCE({$castEvento}, fecha)";

        $topTutoresRaw = RegistroAsistencia::where('asistible_type', Tutor::class)
            ->select('asistible_id', DB::raw("{$sesionExpr} as sesion_id"))
            ->distinct()
            ->get()
            ->groupBy('asistible_id')
            ->map(fn ($group, $asistibleId) => (object) [
                'asistible_id' => $asistibleId,
                'asistencias' => $group->count(),
            ])
            ->sortByDesc('asistencias')
            ->take(20)
            ->values();

        $tutorIds = $topTutoresRaw
            ->pluck('asistible_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        /** @var Collection<int, Tutor> $tutoresMap */
        $tutoresMap = ! empty($tutorIds)
            ? Tutor::with('alumno')->whereIn('id', $tutorIds)->get()->keyBy('id')
            : collect();

        $rankingTutores = [];
        foreach ($topTutoresRaw as $registro) {
            $tutorId = is_numeric($registro->asistible_id) ? (int) $registro->asistible_id : null;
            $tutor = $tutorId ? $tutoresMap->get($tutorId) : null;

            if ($tutor) {
                $nombreAlumno = $tutor->nombres_alumnos;
                if (empty($nombreAlumno)) {
                    $nombreAlumno = $tutor->alumno
                        ? strtoupper($tutor->alumno->nombre_completo)
                        : 'Sin alumno';
                }

                $rankingTutores[] = [
                    'nombre_tutor' => strtoupper(trim($tutor->t1_nombre ?? '')),
                    'telefono' => $tutor->t1_telefono,
                    'nombre_alumno' => $nombreAlumno,
                    'matricula_alumno' => $tutor->matricula_alumno,
                    'asistencias' => (int) $registro->asistencias,
                ];
            }
        }

        // ─────────────────────────────────────────────────────────
        // 5. Asistencia de Tutores a Juntas y Eventos (Convocados vs Asistentes)
        //    Distingue:
        //    A. Familias convocadas (familias únicas de alumnos activos)
        //    B. Familias asistentes (familias únicas con asistencia registrada)
        //    C. Tutores asistentes (personas físicas tutores presentes)
        // ─────────────────────────────────────────────────────────
        $tutoresActivos = Tutor::with('alumno')
            ->whereHas('alumno', fn ($q) => $q->where('activo', 1))
            ->get();

        if ($tutoresActivos->isEmpty()) {
            $tutoresActivos = Tutor::all();
        }

        $totalFamiliasConvocadas = $tutoresActivos->unique(fn ($t) => $this->getFamiliaKey($t))->count();

        $participacionEventos = Evento::orderBy('fecha_hora', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($evento) use ($totalFamiliasConvocadas) {
                $fechaEvento = Carbon::parse($evento->fecha_hora)->toDateString();

                // 1. Determinar convocados reales: consultar tabla evento_convocatorias si existe
                $tieneConvocatoriasExplicitas = \Illuminate\Support\Facades\Schema::hasTable('evento_convocatorias')
                    && DB::table('evento_convocatorias')->where('evento_id', $evento->id)->exists();

                if ($tieneConvocatoriasExplicitas) {
                    $convocadosTutorIds = DB::table('evento_convocatorias')
                        ->where('evento_id', $evento->id)
                        ->pluck('tutor_id')
                        ->unique()
                        ->all();

                    $tutoresConvocados = Tutor::whereIn('id', $convocadosTutorIds)->get();
                    $familiasConvocadasCount = $tutoresConvocados->unique(fn ($t) => $this->getFamiliaKey($t))->count();
                    $tutoresConvocadosCount = count($convocadosTutorIds);
                } else {
                    // Fallback para eventos históricos generales
                    $familiasConvocadasCount = $totalFamiliasConvocadas;
                    $tutoresConvocadosCount = $totalFamiliasConvocadas;
                }

                // 2. Determinar asistencias reales deduplicadas
                $asistenciasTutorIds = RegistroAsistencia::where('asistible_type', Tutor::class)
                    ->where('evento_id', $evento->id)
                    ->pluck('asistible_id')
                    ->filter(fn ($id) => is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique();

                // Si no hay registros con evento_id explícito, fallback por fecha para eventos históricos
                if ($asistenciasTutorIds->isEmpty()) {
                    $asistenciasTutorIds = RegistroAsistencia::where('asistible_type', Tutor::class)
                        ->whereNull('evento_id')
                        ->whereDate('fecha', $fechaEvento)
                        ->pluck('asistible_id')
                        ->filter(fn ($id) => is_numeric($id))
                        ->map(fn ($id) => (int) $id)
                        ->unique();
                }

                $tutoresAsistentes = Tutor::whereIn('id', $asistenciasTutorIds)->get();
                $familiasAsistentes = $tutoresAsistentes->unique(fn ($t) => $this->getFamiliaKey($t))->count();
                $tutoresPersonasCount = $asistenciasTutorIds->count();

                return [
                    'id' => $evento->id,
                    'nombre' => $evento->nombre_evento,
                    'fecha' => Carbon::parse($evento->fecha_hora)->format('d/m/Y'),
                    'convocados' => $familiasConvocadasCount,
                    'tutores_convocados' => $tutoresConvocadosCount,
                    'asistentes' => $familiasAsistentes,
                    'tutores_asistentes' => $tutoresPersonasCount,
                    'porcentaje' => $familiasConvocadasCount > 0 ? round(($familiasAsistentes / $familiasConvocadasCount) * 100) : 0,
                ];
            });

        return Inertia::render('Estadisticas/Index', [
            'kpis' => [
                'diasActivos' => $diasConAsistencia,
                'promedioAsistenciaHoy' => $promedioHoy,
                'tutoresInactivos' => $tutoresInactivos,
            ],
            'graficaDias' => $asistenciasDias,
            'graficaRoles' => $participacionRoles,
            'rankingTutores' => $rankingTutores,
            'participacionEventos' => $participacionEventos,
        ]);
    }
}
