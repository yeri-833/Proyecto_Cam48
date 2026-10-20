<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\User;

class AlumnoPolicy
{
    public function view(User $user, Alumno $alumno): bool
    {
        return $user->isAdmin() || Alumno::query()
            ->accessibleTo($user)
            ->whereKey($alumno->getKey())
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function updateAny(User $user): bool
    {
        return $user->isAdmin() || (
            in_array(strtolower($user->role), ['docente', 'profesor', 'maestro'], true)
            && Personal::verifiedAssignmentFor($user) !== null
        );
    }

    public function update(User $user, Alumno $alumno): bool
    {
        return $this->updateAny($user) && $this->view($user, $alumno);
    }

    public function updateAssignment(User $user, Alumno $alumno): bool
    {
        return $user->isAdmin();
    }
}
