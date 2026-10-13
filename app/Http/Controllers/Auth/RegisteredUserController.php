<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Muestra la vista de registro (restringido exclusivamente a Administradores autenticados).
     */
    public function create(): Response
    {
        Gate::authorize('gestionar-usuarios');

        return Inertia::render('Auth/Register');
    }

    /**
     * Procesa la creación de un nuevo usuario institucional por parte de un Administrador.
     *
     * Previene elevación arbitraria de privilegios validando una lista blanca de roles
     * y preserva la sesión activa del administrador sin cerrarla ni suplantarla.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-usuarios');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.',correo',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'tipo_usuario' => [
                'required',
                'string',
                Rule::in(['Docente', 'Administrativo', 'Operador', 'Director', 'Personal']),
            ],
        ], [
            'tipo_usuario.in' => 'El rol seleccionado no es válido en la estructura institucional.',
        ]);

        $user = User::create([
            'nombre_completo' => $request->name,
            'correo' => $request->email,
            'password' => Hash::make($request->password),
            'tipo_usuario' => $request->tipo_usuario,
        ]);

        event(new Registered($user));

        // Preservación estricta de la sesión del administrador
        return redirect()->route('dashboard')->with(
            'success',
            "Usuario institucional [{$user->name}] registrado exitosamente con rol [{$user->tipo_usuario}]."
        );
    }
}
