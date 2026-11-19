<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\User;

class ArchivoSistemaPolicy
{
    /**
     * Determina si el usuario puede ver la lista general del módulo de expedientes.
     */
    public function viewAny(User $user): bool
    {
        return $user->isDirector();
    }

    /**
     * Determina si el usuario puede ver o descargar un documento confidencial específico.
     */
    public function view(User $user, ArchivoSistema $archivo): bool
    {
        return $user->isDirector();
    }

    /**
     * Verifica si el usuario tiene autorización sobre un alumno específico.
     * Aplica principio de fail-secure y mínimo privilegio (OWASP A01).
     */
    public function canAccessAlumno(User $user, Alumno $alumno): bool
    {
        return $user->isDirector();
    }

    /**
     * Determina si el usuario puede subir documentos al expediente escolar.
     * La carga de documentos privados está reservada al Director.
     */
    public function create(User $user, ?Alumno $alumno = null): bool
    {
        return $user->isDirector();
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
