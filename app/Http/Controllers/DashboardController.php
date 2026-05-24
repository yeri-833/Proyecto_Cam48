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
            ->with('tutores')
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
                'tutor' => $a->tutores?->t1_nombre ?? 'Sin asignar',
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

    public function updateAlumno(\Illuminate\Http\Request $request, $matricula)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'nivel_educativo' => 'nullable|string|max:50',
            'grado' => 'nullable|string|max:20',
            'grupo' => 'nullable|string|max:10',
            'discapacidad' => 'nullable|string|max:100',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $alumno = Alumno::where('matricula', $matricula)->firstOrFail();
        
        $data = [
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'nivel_educativo' => $request->nivel_educativo,
            'grado' => $request->grado,
            'grupo' => $request->grupo,
            'discapacidad' => $request->discapacidad,
        ];

        if ($request->hasFile('foto_perfil')) {
            if ($alumno->foto_perfil && Storage::disk('public')->exists($alumno->foto_perfil)) {
                Storage::disk('public')->delete($alumno->foto_perfil);
            }
            
            // Compresión de imagen con Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('foto_perfil')->getRealPath());
            $image->scaleDown(width: 800);
            $encoded = $image->toJpeg(quality: 80);
            
            $filename = 'fotos/alumnos/' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $encoded->toString());
            
            $data['foto_perfil'] = $filename;
        }

        $alumno->update($data);

        return redirect()->back()->with('success', 'Expediente del alumno actualizado correctamente.');
    }
    public function storeAlumno(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'matricula' => 'required|string|unique:alumnos,matricula|max:20',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'sexo' => 'nullable|string|in:M,F',
            'nivel_educativo' => 'nullable|string|max:50',
            'grado' => 'nullable|string|max:20',
            'grupo' => 'nullable|string|max:10',
            'discapacidad' => 'nullable|string|max:100',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'matricula' => strtoupper($request->matricula),
            'nombre' => strtoupper($request->nombre),
            'apellido' => strtoupper($request->apellido),
            'sexo' => $request->sexo ?? 'M',
            'nivel_educativo' => $request->nivel_educativo,
            'grado' => $request->grado,
            'grupo' => $request->grupo,
            'discapacidad' => $request->discapacidad,
            'activo' => 1,
        ];

        if ($request->hasFile('foto_perfil')) {
            // Compresión de imagen con Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('foto_perfil')->getRealPath());
            $image->scaleDown(width: 800);
            $encoded = $image->toJpeg(quality: 80);
            
            $filename = 'fotos/alumnos/' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $encoded->toString());
            
            $data['foto_perfil'] = $filename;
        }

        Alumno::create($data);

        return redirect()->back()->with('success', 'Alumno registrado correctamente.');
    }
}
