<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\RegistroAsistencia;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // KPIs - Exactamente la misma lógica de api_stats.php
        $totalAlumnos = Alumno::where('activo', 1)->count();
        $asistenciasHoy = RegistroAsistencia::whereDate('fecha', today())->count();
        $pendientes = max(0, $totalAlumnos - $asistenciasHoy);

        // Tabla de expedientes: Alumnos activos con su tutor
        $alumnos = Alumno::where('activo', 1)
            ->with('tutores')
            ->orderBy('apellido')
            ->get()
            ->map(fn ($a) => [
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

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalAlumnos' => $totalAlumnos,
                'asistenciasHoy' => $asistenciasHoy,
                'pendientes' => $pendientes,
            ],
            'alumnos' => $alumnos,
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
            if ($alumno->foto_perfil && \Illuminate\Support\Facades\Storage::disk('public')->exists($alumno->foto_perfil)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($alumno->foto_perfil);
            }
            $data['foto_perfil'] = $request->file('foto_perfil')->store('fotos/alumnos', 'public');
        }

        $alumno->update($data);

        return redirect()->back()->with('success', 'Expediente del alumno actualizado correctamente.');
    }
}
