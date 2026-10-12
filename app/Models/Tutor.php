<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    protected $table = 'tutores';
    public $timestamps = false;

    protected $fillable = [
        'matricula_alumno',
        't1_nombre',
        't1_parentesco',
        't1_telefono',
        't1_direccion',
        't1_profesion',
        't2_nombre',
        't2_parentesco',
        't2_telefono',
    ];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'matricula_alumno', 'matricula');
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias()
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }

    public function getNombreCompletoAttribute(): string
    {
        return strtoupper(trim($this->t1_nombre ?? ''));
    }
}

