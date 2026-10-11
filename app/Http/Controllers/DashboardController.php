<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\RegistroAsistencia;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class DashboardController extends Controller
{
    public function index()
    {
        // Caché de estadísticas por 5 minutos (300 segundos) para optimizar consultas a la BD
        $stats = Cache::remember('dashboard_stats_' . date('Ymd'), 300, function () {
            $totalAlumnos = Alumno::where('activo', 1)->count();
            $asistenciasHoy = RegistroAsistencia::whereDate('fecha', today())->count();
            return [
                'totalAlumnos' => $totalAlumnos,
                'asistenciasHoy' => $asistenciasHoy,
                'pendientes' => max(0, $totalAlumnos - $asistenciasHoy),
            ];
        });

        // Tabla de expedientes paginada
        $alumnos = Alumno::where('activo', 1)
            ->with('tutor')
            ->orderBy('apellido')
            ->paginate(15)
            ->through(fn ($a) => [
                'matricula' => $a->matricula,
                'nombre' => $a->nombre,
                'apellido' => $a->apellido,
                'nombre_completo' => $a->nombre_completo,
                'sexo' => $a->sexo,
                'nivel_educativo' => $a->nivel_educativo,
                'grado' => $a->grado,
                'grupo' => $a->grupo,
                'discapacidad' => $a->discapacidad,
                'foto_perfil' => $a->foto_perfil,
                'tutor' => $a->tutor?->t1_nombre ?? 'Sin asignar',
            ]);

        // Evento próximo más cercano
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
