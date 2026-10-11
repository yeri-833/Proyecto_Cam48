<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Tutor;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class EstadisticasController extends Controller
{
    /**
     * Calcula las métricas y datos para el Dashboard de Estadísticas
     */
    public function index()
    {
        // 1. KPIs Generales
        $diasConAsistencia = DB::table('registros_asistencia')->distinct('fecha')->count('fecha');
        
        $totalAlumnos = Alumno::where('activo', 1)->count();
        $asistenciasHoy = DB::table('registros_asistencia')
                            ->where('fecha', Carbon::today()->toDateString())
                            ->distinct('id_persona')
                            ->count('id_persona');
                            
        $promedioHoy = ($totalAlumnos > 0) ? round(($asistenciasHoy / $totalAlumnos) * 100) : 0;
        
        // Tutores sin alumnos (Inactivos hipotéticos)
        // Ojo: en la versión legacy se usaba 'id NOT IN (SELECT matricula_alumno FROM alumnos)'
        // En nuestro modelo, la clave foránea es matricula_alumno en la tabla alumnos vinculada a t1_telefono.
        $tutoresInactivos = Tutor::whereNotIn('t1_telefono', function($query) {
            $query->select('matricula_alumno')->from('alumnos')->whereNotNull('matricula_alumno');
        })->count();

        // 2. Gráfica de Barras (Últimos 7 días con actividad)
        $asistenciasDias = DB::table('registros_asistencia')
            ->select('fecha', DB::raw("COUNT(*) as total"))
            ->groupBy('fecha')
            ->orderBy('fecha', 'desc')
            ->limit(7)
            ->get()
            ->map(function ($item) {
                return [
                    'dia' => Carbon::parse($item->fecha)->format('d/m'),
                    'total' => (int) $item->total,
                ];
            })
            ->reverse()
            ->values(); // Reset keys for JSON array

        // 3. Gráfica de Dona (Participación por Rol General)
        $participacionRoles = DB::table('registros_asistencia')
            ->select('rol', DB::raw("COUNT(*) as total"))
            ->groupBy('rol')
            ->get()
            ->map(function ($item) {
                // Estandarizar roles
                if ($item->rol === 'Alumno') return ['tipo' => 'Alumnos', 'total' => $item->total];
                if ($item->rol === 'Tutor') return ['tipo' => 'Tutores', 'total' => $item->total];
                return ['tipo' => 'Personal', 'total' => $item->total]; // Agrupar docentes, administrativos, etc.
            })
            ->groupBy('tipo')
            ->map(function ($group, $key) {
                return ['tipo' => $key, 'total' => $group->sum('total')];
            })
            ->values();

        // 4. Ranking de Tutores más comprometidos
        $topTutoresIds = DB::table('registros_asistencia')
            ->where('rol', 'Tutor')
            ->select('id_persona', DB::raw('count(*) as asistencias'))
            ->groupBy('id_persona')
            ->orderByDesc('asistencias')
            ->limit(20)
            ->get();

        // Mapear con datos reales del tutor y su alumno vinculado
        $rankingTutores = [];
        foreach ($topTutoresIds as $registro) {
            $tutor = Tutor::where('t1_telefono', $registro->id_persona)
                        ->orWhere('matricula_alumno', $registro->id_persona)
                        ->with('alumno')
                        ->first();
            
            if ($tutor) {
                $rankingTutores[] = [
                    'nombre_tutor' => strtoupper($tutor->t1_nombre . ' ' . $tutor->t1_apellido),
                    'telefono' => $tutor->t1_telefono,
                    'nombre_alumno' => $tutor->alumno ? strtoupper($tutor->alumno->nombre_completo) : 'Sin alumno',
                    'matricula_alumno' => $tutor->matricula_alumno,
                    'asistencias' => $registro->asistencias
                ];
            }
        }

        return Inertia::render('Estadisticas/Index', [
            'kpis' => [
                'diasActivos' => $diasConAsistencia,
                'promedioAsistenciaHoy' => $promedioHoy,
                'tutoresInactivos' => $tutoresInactivos
            ],
            'graficaDias' => $asistenciasDias,
            'graficaRoles' => $participacionRoles,
            'rankingTutores' => $rankingTutores
        ]);
    }
}
