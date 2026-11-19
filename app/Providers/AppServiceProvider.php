<?php

namespace App\Providers;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\User;
use App\Policies\AlumnoPolicy;
use App\Policies\ArchivoSistemaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(ArchivoSistema::class, ArchivoSistemaPolicy::class);
        Gate::policy(Alumno::class, AlumnoPolicy::class);

        foreach ([
            'admin-access', 'gestionar-usuarios', 'gestionar-permisos',
            'gestionar-configuracion', 'gestionar-respaldos', 'eliminar-registros',
            'telegram.sincronizar', 'personal.consultar', 'personal.crear',
            'personal.actualizar', 'personal.eliminar', 'alumnos.eliminar',
            'tutores.eliminar', 'historial.eliminar', 'notificaciones.personal',
            'expedientes.consultar', 'expedientes.gestionar',
            'eventos.eliminar', 'convocatorias.desconvocar',
        ] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->isDirector());
        }

        foreach ([
            'dashboard.consultar', 'alumnos.consultar', 'alumnos.crear',
            'alumnos.actualizar', 'tutores.consultar', 'tutores.crear',
            'tutores.actualizar', 'eventos.consultar', 'eventos.crear',
            'eventos.actualizar', 'convocatorias.consultar',
            'convocatorias.gestionar',
            'asistencias.corregir', 'asistencias.manual', 'historial.consultar', 'historial.exportar',
            'estadisticas.consultar', 'notificaciones.tutores',
            'credenciales.generar', 'credenciales.imprimir',
        ] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->isDirector() || $user->isSecretaria());
        }

        foreach (['kiosco-access', 'asistencia.escanear', 'asistencias.escanear'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->isDirector() || $user->isOperador());
        }

        Gate::define('asistencias.registrar', fn (User $user): bool => $user->canonicalRole() !== null);
        Gate::define('personal-access', fn (User $user): bool => $user->isDirector() || $user->isSecretaria());
    }
}
