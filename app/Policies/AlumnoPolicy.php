<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\User;

class AlumnoPolicy
{
    public function view(User $user, Alumno $alumno): bool
    {
        return $user->isDirector() || $user->isSecretaria();
    }

    public function create(User $user): bool
    {
        return $user->isDirector() || $user->isSecretaria();
    }

    public function updateAny(User $user): bool
    {
        return $user->isDirector() || $user->isSecretaria();
    }

    public function update(User $user, Alumno $alumno): bool
    {
        return $this->updateAny($user) && $this->view($user, $alumno);
    }

    public function updateAssignment(User $user, Alumno $alumno): bool
    {
        return $user->isDirector() || $user->isSecretaria();
    }
}
