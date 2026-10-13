<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Tutor;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CredencialController extends Controller
{
    /**
     * Muestra la vista del generador de credenciales QR.
     */
    public function index()
    {
        Gate::authorize('credenciales.generar');

        $alumnos = Alumno::where('activo', 1)->get()->map(function ($a) {
            return [
                'id' => $a->matricula,
                'nombre' => $a->nombre,
                'apellido' => $a->apellido,
                'rol' => 'Alumno',
                'foto_perfil' => $a->foto_perfil,
                'signature' => hash_hmac('sha256', $a->matricula.'Alumno', config('app.key')),
            ];
        });

        $personal = Personal::where('activo', 1)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre_completo,
                'apellido' => '',
                'rol' => $p->rol ?? 'Docente',
                'foto_perfil' => $p->foto_perfil,
                'signature' => hash_hmac('sha256', $p->id.($p->rol ?? 'Docente'), config('app.key')),
            ];
        });

        $tutores = Tutor::all()->map(function ($t) {
            return [
                'id' => (string) $t->id,
                'nombre' => $t->t1_nombre,
                'apellido' => '',
                'rol' => 'Tutor',
                'foto_perfil' => null,
                'signature' => hash_hmac('sha256', (string) $t->id.'Tutor', config('app.key')),
            ];
        });

        return Inertia::render('Credenciales/Index', [
            'alumnos' => $alumnos,
            'personal' => $personal,
            'tutores' => $tutores,
        ]);
    }
}
