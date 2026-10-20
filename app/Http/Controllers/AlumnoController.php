<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AlumnoController extends Controller
{
    public function store(Request $request, ImageUploadService $imageService): RedirectResponse
    {
        Gate::authorize('create', Alumno::class);

        $request->validate([
            'matricula' => 'required|string|unique:alumnos,matricula|max:20',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'sexo' => 'nullable|string|in:M,F',
            'nivel_educativo' => 'nullable|string|max:50',
            'grado' => 'nullable|string|max:20',
            'grupo' => 'nullable|string|max:10',
            'curp' => 'nullable|string|max:18',
            'fecha_nacimiento' => 'nullable|date',
            'edad' => 'nullable|integer|min:0',
            'comentarios' => 'nullable|string|max:1000',
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
            'curp' => $request->curp ? strtoupper($request->curp) : null,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'edad' => $request->edad,
            'comentarios' => $request->comentarios,
            'discapacidad' => $request->discapacidad,
            'activo' => 1,
        ];

        if ($request->hasFile('foto_perfil')) {
            $data['foto_perfil'] = $imageService->procesarYGuardar($request->file('foto_perfil'));
        }

        Alumno::create($data);

        return redirect()->back()->with('success', 'Alumno registrado correctamente.');
    }

    public function update(Request $request, string $matricula, ImageUploadService $imageService): RedirectResponse
    {
        $alumno = Alumno::where('matricula', $matricula)->firstOrFail();
        Gate::authorize('update', $alumno);

        $camposDeAsignacion = ['matricula', 'nivel_educativo', 'grado', 'grupo', 'activo', 'rol'];
        $puedeAsignar = Gate::allows('updateAssignment', $alumno);
        if (! $puedeAsignar) {
            foreach ($request->only($camposDeAsignacion) as $campo => $valor) {
                $esValorSimple = is_scalar($valor) || $valor === null;
                $sinCambio = $esValorSimple && ($campo === 'activo'
                    ? $request->boolean($campo) === $alumno->activo
                    : trim((string) $valor) === trim((string) $alumno->getAttribute($campo)));

                if (! $sinCambio) {
                    Gate::authorize('updateAssignment', $alumno);
                }
            }
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'sexo' => 'nullable|string|in:M,F',
            'nivel_educativo' => 'nullable|string|max:50',
            'grado' => 'nullable|string|max:20',
            'grupo' => 'nullable|string|max:10',
            'curp' => 'nullable|string|max:18',
            'fecha_nacimiento' => 'nullable|date',
            'edad' => 'nullable|integer|min:0',
            'comentarios' => 'nullable|string|max:1000',
            'discapacidad' => 'nullable|string|max:100',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'activo' => 'nullable|boolean',
        ]);

        $data = Arr::except($validated, ['foto_perfil']);
        $data['nombre'] = strtoupper($validated['nombre']);
        $data['apellido'] = strtoupper($validated['apellido']);
        if (array_key_exists('curp', $data) && $data['curp'] !== null) {
            $data['curp'] = strtoupper($data['curp']);
        }

        if (! $puedeAsignar) {
            $data = Arr::except($data, $camposDeAsignacion);
        } elseif (array_key_exists('activo', $data)) {
            $data['activo'] = (bool) $data['activo'];
        }

        if ($request->hasFile('foto_perfil')) {
            if ($alumno->foto_perfil && Storage::disk('public')->exists($alumno->foto_perfil)) {
                Storage::disk('public')->delete($alumno->foto_perfil);
            }

            $data['foto_perfil'] = $imageService->procesarYGuardar($request->file('foto_perfil'));
        }

        $alumno->update($data);

        return redirect()->back()->with('success', 'Expediente del alumno actualizado correctamente.');
    }
}
