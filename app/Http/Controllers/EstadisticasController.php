<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EstadisticasController extends Controller
{
    /**
     * Calcula las métricas y datos para el Dashboard de Estadísticas.
     * Usa el esquema polimórfico (asistible_type / asistible_id).
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
        // ─────────────────────────────────────────────────────────
        $topTutoresRaw = RegistroAsistencia::where('asistible_type', Tutor::class)
            ->select('asistible_id', DB::raw('COUNT(*) as asistencias'))
            ->groupBy('asistible_id')
            ->orderByDesc('asistencias')
            ->limit(20)
            ->get();

        $rankingTutores = [];
        foreach ($topTutoresRaw as $registro) {
            $tutor = is_numeric($registro->asistible_id)
                ? Tutor::with('alumno')->find((int) $registro->asistible_id)
                : null;

            if ($tutor) {
                $rankingTutores[] = [
                    'nombre_tutor' => strtoupper(trim($tutor->t1_nombre)),
                    'telefono' => $tutor->t1_telefono,
                    'nombre_alumno' => $tutor->alumno
                                          ? strtoupper($tutor->alumno->nombre_completo)
                                          : 'Sin alumno',
                    'matricula_alumno' => $tutor->matricula_alumno,
                    'asistencias' => (int) $registro->asistencias,
                ];
            }
        }

        return Inertia::render('Estadisticas/Index', [
            'kpis' => [
                'diasActivos' => $diasConAsistencia,
                'promedioAsistenciaHoy' => $promedioHoy,
                'tutoresInactivos' => $tutoresInactivos,
            ],
            'graficaDias' => $asistenciasDias,
            'graficaRoles' => $participacionRoles,
            'rankingTutores' => $rankingTutores,
        ]);
    }
}
