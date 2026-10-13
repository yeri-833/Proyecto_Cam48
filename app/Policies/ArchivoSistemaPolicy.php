<?php

namespace App\Policies;

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
     *
     * CONSIDERACIONES DE SEGURIDAD ESCOLAR (OWASP A01 / ASVS):
     * - Administradores y Directivos tienen acceso completo a expedientes.
     * - Para Docentes/Personal:
     *   LIMITACIÓN ARQUITECTÓNICA CONFIRMADA: En el esquema actual de la base de datos
     *   no existe una tabla pivote de asignación docente-alumno, ni clave foránea entre
     *   `usuarios` y `personal`. La columna `grado_grupo` en `personal` no tiene relación
     *   relacional estricta con `alumnos.grado` y `alumnos.grupo`.
     *   Por principio de mínimo privilegio y fail-secure, el acceso a documentos específicos
     *   permanece restringido a Administradores y a docentes con grupo coincidente verificado,
     *   requiriendo decisión directiva institucional antes de habilitar permisos amplios.
     */
    public function view(User $user, ArchivoSistema $archivo): bool
    {
        // 1. Directores y Administradores tienen acceso institucional irrestricto
        if ($user->isAdmin()) {
            return true;
        }

        // 2. Si no es personal escolar, bloqueo inmediato (HTTP 403)
        if (! $user->isPersonal()) {
            return false;
        }

        // 3. Verificación de relación autorizada Docente <-> Alumno
        $alumno = $archivo->alumno;
        if (! $alumno) {
            return false;
        }

        // Buscamos si el usuario tiene ficha de Personal vinculada por correo
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
        }

        // Sin coincidencia verificable ni autorización institucional, denegar acceso por defecto (403)
        return false;
    }

    /**
     * Determina si el usuario puede subir documentos al expediente del alumno.
     */
    public function create(User $user): bool
    {
        return $user->isPersonal();
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
