<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\Personal;
use App\Models\User;

class ArchivoSistemaPolicy
{
    /**
     * Determina si el usuario puede ver la lista general del módulo de expedientes.
     */
    public function viewAny(User $user): bool
    {
        return $user->isPersonal();
    }

    /**
     * Determina si el usuario puede ver o descargar un documento confidencial específico.
     */
    public function view(User $user, ArchivoSistema $archivo): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $alumno = $archivo->alumno;
        if (! $alumno) {
            return false;
        }

        return $this->canAccessAlumno($user, $alumno);
    }

    /**
     * Verifica si el usuario tiene autorización sobre un alumno específico.
     * Aplica principio de fail-secure y mínimo privilegio (OWASP A01).
     */
    public function canAccessAlumno(User $user, Alumno $alumno): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isPersonal()) {
            return false;
        }

        /** @var Personal|null $personal */
        $personal = Personal::where('email', $user->email)
            ->where('activo', 1)
            ->first();

        if ($personal && ! empty($personal->grado_grupo)) {
            $grupoAlumno = trim(($alumno->grado ?? '').' '.($alumno->grupo ?? ''));
            $grupoAlumnoSinEspacio = trim(($alumno->grado ?? '').($alumno->grupo ?? ''));
            $gradoGrupoDocente = trim($personal->grado_grupo);

            if (
                strcasecmp($gradoGrupoDocente, $grupoAlumno) === 0
                || strcasecmp($gradoGrupoDocente, $grupoAlumnoSinEspacio) === 0
            ) {
                return true;
            }

            // Normalización para compatibilidad con símbolos de grado (ej. '1° A' vs '1A')
            $docenteNorm = str_replace(['°', ' '], '', strtolower($gradoGrupoDocente));
            $alumnoNorm = str_replace(['°', ' '], '', strtolower($grupoAlumnoSinEspacio));
            if (! empty($docenteNorm) && $docenteNorm === $alumnoNorm) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina si el usuario puede subir documentos al expediente escolar.
     * Si se proporciona el alumno destino, se valida que pertenezca a la asignación autorizada.
     */
    public function create(User $user, ?Alumno $alumno = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isPersonal()) {
            return false;
        }

        if ($alumno !== null) {
            return $this->canAccessAlumno($user, $alumno);
        }

        // Si no se especifica el alumno, debe tener ficha de personal docente activa con grupo asignado
        /** @var Personal|null $personal */
        $personal = Personal::where('email', $user->email)->where('activo', 1)->first();

        return $personal !== null && ! empty($personal->grado_grupo);
    }

    /**
     * Determina si el usuario puede eliminar un documento confidencial.
     * Acción destructiva reservada exclusivamente para Administradores.
     */
    public function delete(User $user, ArchivoSistema $archivo): bool
    {
        return $user->isAdmin();
    }
}
