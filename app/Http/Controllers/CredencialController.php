<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CredencialController extends Controller
{
    /**
     * Muestra la vista del generador de credenciales QR optimizada para catálogos escolares.
     */
    public function index(Request $request)
    {
        Gate::authorize('credenciales.generar');

        /** @var User $user */
        $user = $request->user();
        $filtro = $request->query('filtro', 'alumnos');
        $busqueda = trim((string) $request->query('busqueda', ''));

        // 1. Alumnos: filtrado eficiente con alcance autorizado para docentes
        $alumnosQuery = Alumno::where('activo', 1);
        if (! $user->isAdmin()) {
            $alumnosQuery->accessibleTo($user);
        }
        if (! empty($busqueda) && in_array($filtro, ['alumnos', 'todos'])) {
            $alumnosQuery->where(function ($q) use ($busqueda) {
                $q->where('matricula', 'LIKE', "%{$busqueda}%")
                    ->orWhere('nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('apellido', 'LIKE', "%{$busqueda}%");
            });
        }

        $alumnos = $alumnosQuery
            ->select('matricula', 'nombre', 'apellido', 'foto_perfil')
            ->orderBy('apellido')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->matricula,
                'nombre' => $a->nombre,
                'apellido' => $a->apellido,
                'rol' => 'Alumno',
                'foto_perfil' => $a->foto_perfil,
                'signature' => hash_hmac('sha256', $a->matricula.'Alumno', config('app.key')),
            ]);

        // 2. Personal: consulta indexada y ordenada
        $personalQuery = Personal::where('activo', 1);
        if (! $user->isDirector()) {
            $personalQuery->whereRaw('0 = 1');
        }
        if (! empty($busqueda) && in_array($filtro, ['personal', 'todos'])) {
            $personalQuery->where(function ($q) use ($busqueda) {
                $q->where('id', 'LIKE', "%{$busqueda}%")
                    ->orWhere('nombre_completo', 'LIKE', "%{$busqueda}%");
            });
        }

        $personal = $personalQuery
            ->select('id', 'nombre_completo', 'foto_perfil')
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn ($p) => [
                'id' => (string) $p->id,
                'nombre' => $p->nombre_completo,
                'apellido' => '',
                'rol' => 'Docente',
                'foto_perfil' => $p->foto_perfil,
                'signature' => hash_hmac('sha256', (string) $p->id.'Docente', config('app.key')),
            ]);

        // 3. Tutores: consulta indexada y ordenada
        $tutoresQuery = Tutor::query();
        if (! empty($busqueda) && in_array($filtro, ['tutores', 'todos'])) {
            $tutoresQuery->where(function ($q) use ($busqueda) {
                $q->where('id', 'LIKE', "%{$busqueda}%")
                    ->orWhere('t1_nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('matricula_alumno', 'LIKE', "%{$busqueda}%");
            });
        }

        $tutores = $tutoresQuery
            ->select('id', 't1_nombre')
            ->orderBy('t1_nombre')
            ->get()
            ->map(fn ($t) => [
                'id' => (string) $t->id,
                'nombre' => $t->t1_nombre,
                'apellido' => '',
                'rol' => 'Tutor',
                'foto_perfil' => null,
                'signature' => hash_hmac('sha256', (string) $t->id.'Tutor', config('app.key')),
            ]);

        return Inertia::render('Credenciales/Index', [
            'alumnos' => $alumnos,
            'personal' => $personal,
            'tutores' => $tutores,
            'filtros' => [
                'filtro' => $filtro,
                'busqueda' => $busqueda,
            ],
        ]);
    }
}
