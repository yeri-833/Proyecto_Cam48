<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var User|null $user */
        $user = auth()->user();

        $alumnosQuery = Alumno::where('activo', 1);
        if ($user && ! $user->isAdmin()) {
            $alumnosQuery->accessibleTo($user);
        }

        // Total de alumnos activos según el alcance del usuario
        $totalAlumnos = (clone $alumnosQuery)->count();

        // Asistencias de hoy (alumnos únicos registrados hoy usando relación polimórfica)
        $asistenciasQuery = DB::table('registros_asistencia')
            ->whereDate('fecha', today())
            ->where('asistible_type', Alumno::class);

        if ($user && ! $user->isAdmin()) {
            $asistenciasQuery->whereIn('asistible_id', (clone $alumnosQuery)->select('matricula'));
        }

        $asistenciasHoy = $asistenciasQuery
            ->distinct('asistible_id')
            ->count('asistible_id');

        $stats = [
            'totalAlumnos' => $totalAlumnos,
            'asistenciasHoy' => $asistenciasHoy,
            'pendientes' => max(0, $totalAlumnos - $asistenciasHoy),
        ];

        // Tabla de expedientes paginada — Contrato íntegro de datos con el frontend
        $alumnos = (clone $alumnosQuery)
            ->with('tutor')
            ->orderBy('apellido')
            ->paginate(15)
            ->through(fn ($a) => [
                'matricula' => $a->matricula,
                'nombre' => $a->nombre,
                'apellido' => $a->apellido,
                'nombre_completo' => trim($a->nombre.' '.$a->apellido),
                'sexo' => $a->sexo,
                'curp' => $a->curp,
                'fecha_nacimiento' => $a->fecha_nacimiento ? substr((string) $a->fecha_nacimiento, 0, 10) : null,
                'edad' => $a->edad !== null ? (int) $a->edad : null,
                'nivel_educativo' => $a->nivel_educativo,
                'grado' => $a->grado,
                'grupo' => $a->grupo,
                'discapacidad' => $a->discapacidad,
                'comentarios' => $a->comentarios,
                'foto_perfil' => $a->foto_perfil,
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
