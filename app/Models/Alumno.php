<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alumno extends Model
{
    protected $table = 'alumnos';
    protected $primaryKey = 'matricula';
    public $incrementing = false;
    protected $keyType = 'string';

    // La tabla solo tiene 'fecha_registro', no 'updated_at'
    public $timestamps = true;
    const CREATED_AT = 'fecha_registro';
    const UPDATED_AT = null;

    protected $fillable = [
        'matricula', 'nombre', 'apellido', 'sexo', 'curp',
        'nivel_educativo', 'grado', 'grupo', 'discapacidad', 'activo',
        'fecha_nacimiento', 'edad', 'comentarios', 'rol',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tutor()
    {
        return $this->hasOne(Tutor::class, 'matricula_alumno', 'matricula');
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias()
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }

    // Accessor para nombre completo
    public function getNombreCompletoAttribute(): string
    {
        return strtoupper(trim(($this->nombre ?? '') . ' ' . ($this->apellido ?? '')));
    }
}

