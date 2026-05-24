<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CredencialController extends Controller
{
    /**
     * Muestra la vista del generador de credenciales QR.
     */
    public function index()
    {
        $alumnos = Alumno::where('activo', 1)->get()->map(function ($a) {
            return [
                'id' => $a->matricula,
                'nombre' => $a->nombre,
                'apellido' => $a->apellido,
                'rol' => 'Alumno',
                'foto_perfil' => $a->foto_perfil
            ];
        });

        $personal = Personal::where('activo', 1)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre_completo,
                'apellido' => '',
                'rol' => $p->rol ?? 'Docente',
                'foto_perfil' => $p->foto_perfil
            ];
        });

        $tutores = Tutor::all()->map(function ($t) {
            return [
                'id' => $t->t1_telefono,
                'nombre' => $t->t1_nombre,
                'apellido' => '',
                'rol' => 'Tutor',
                'foto_perfil' => $t->t1_foto_perfil
            ];
        });

        return Inertia::render('Credenciales/Index', [
            'alumnos' => $alumnos,
            'personal' => $personal,
            'tutores' => $tutores
        ]);
    }
}
