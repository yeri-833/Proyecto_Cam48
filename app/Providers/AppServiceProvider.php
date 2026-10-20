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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Registro de Policies
        Gate::policy(ArchivoSistema::class, ArchivoSistemaPolicy::class);
        Gate::policy(Alumno::class, AlumnoPolicy::class);

        // ─────────────────────────────────────────────────────────────
        // MATRIZ DE AUTORIZACIÓN POR ROLES Y OPERACIONES — FAMILYLOCK CAM #48
        // ─────────────────────────────────────────────────────────────

        // Roles base
        Gate::define('admin-access', fn (User $user): bool => $user->isAdmin());
        Gate::define('personal-access', fn (User $user): bool => $user->isPersonal());
        Gate::define('kiosco-access', fn (User $user): bool => $user->isKiosco());

        // Administración global y destructiva
        Gate::define('gestionar-usuarios', fn (User $user): bool => $user->isAdmin());
        Gate::define('eliminar-registros', fn (User $user): bool => $user->isAdmin());
        Gate::define('telegram.sincronizar', fn (User $user): bool => $user->isAdmin());

        // 1. Módulo Alumnos
        Gate::define('alumnos.consultar', fn (User $user): bool => $user->isPersonal());
        Gate::define('alumnos.crear', fn (User $user): bool => Gate::forUser($user)->allows('create', Alumno::class));
        Gate::define('alumnos.actualizar', fn (User $user): bool => Gate::forUser($user)->allows('updateAny', Alumno::class));
        Gate::define('alumnos.eliminar', fn (User $user): bool => $user->isAdmin());

        // 2. Módulo Personal (Nómina y Directorio)
        Gate::define('personal.consultar', fn (User $user): bool => $user->isPersonal());
        Gate::define('personal.crear', fn (User $user): bool => $user->isAdmin());
        Gate::define('personal.actualizar', fn (User $user): bool => $user->isAdmin());
        Gate::define('personal.eliminar', fn (User $user): bool => $user->isAdmin());

        // 3. Módulo Tutores
        Gate::define('tutores.consultar', fn (User $user): bool => $user->isPersonal());
        Gate::define('tutores.crear', fn (User $user): bool => $user->isPersonal());
        Gate::define('tutores.actualizar', fn (User $user): bool => $user->isPersonal());
        Gate::define('tutores.eliminar', fn (User $user): bool => $user->isAdmin());

        // 4. Módulo Historial de Asistencia
        Gate::define('historial.consultar', fn (User $user): bool => $user->isPersonal());
        Gate::define('historial.exportar', fn (User $user): bool => $user->isPersonal());
        Gate::define('historial.eliminar', fn (User $user): bool => $user->isAdmin());

        // 5. Estadísticas y Reportes
        Gate::define('estadisticas.consultar', fn (User $user): bool => $user->isPersonal());

        // 6. Credenciales QR
        Gate::define('credenciales.generar', fn (User $user): bool => $user->isPersonal());

        // 7. Eventos y Agenda
        Gate::define('eventos.consultar', fn (User $user): bool => $user->isPersonal());
        Gate::define('eventos.crear', fn (User $user): bool => $user->isPersonal());
        Gate::define('eventos.eliminar', fn (User $user): bool => $user->isAdmin());

        // 8. Mensajería y Notificaciones
        Gate::define('notificaciones.tutores', fn (User $user): bool => $user->isPersonal());
        Gate::define('notificaciones.personal', fn (User $user): bool => $user->isAdmin());

        // 9. Terminal de Asistencia (Escáner QR)
        Gate::define('asistencia.escanear', fn (User $user): bool => $user->isKiosco());
    }
}
