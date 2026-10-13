<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Total de alumnos activos
        $totalAlumnos = Alumno::where('activo', 1)->count();

        // Asistencias de hoy (alumnos únicos registrados hoy usando relación polimórfica)
        $asistenciasHoy = DB::table('registros_asistencia')
            ->whereDate('fecha', today())
            ->where('asistible_type', Alumno::class)
            ->distinct('asistible_id')
            ->count('asistible_id');

        $stats = [
            'totalAlumnos' => $totalAlumnos,
            'asistenciasHoy' => $asistenciasHoy,
            'pendientes' => max(0, $totalAlumnos - $asistenciasHoy),
        ];

        // Tabla de expedientes paginada — columnas reales de la tabla 'alumnos'
        $alumnos = Alumno::where('activo', 1)
            ->with('tutor')
            ->orderBy('apellido')
            ->paginate(15)
            ->through(fn ($a) => [
                'matricula' => $a->matricula,
                'nombre' => $a->nombre,
                'apellido' => $a->apellido,
                'nombre_completo' => trim($a->nombre.' '.$a->apellido),
                'sexo' => $a->sexo,
                'nivel_educativo' => $a->nivel_educativo,
                'grado' => $a->grado,
                'grupo' => $a->grupo,
                'discapacidad' => $a->discapacidad,
                'foto_perfil' => null,  // columna no existe en tu BD
                'tutor' => optional($a->tutor)->t1_nombre ?? 'Sin asignar',
            ]);

        // Evento próximo
        $proximoEvento = Evento::where('fecha_hora', '>=', now())
            ->orderBy('fecha_hora', 'asc')
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'alumnos' => $alumnos,
            'proximoEvento' => $proximoEvento,
        ]);
    }
}
