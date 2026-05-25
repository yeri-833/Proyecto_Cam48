<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alumno extends Model
{
    protected $table = 'alumnos';
    protected $primaryKey = 'matricula';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'matricula', 'nombre', 'apellido', 'sexo', 'curp', 'foto_perfil',
        'nivel_educativo', 'grado', 'grupo', 'discapacidad', 'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tutores()
    {
        return $this->hasOne(Tutor::class, 'matricula_alumno', 'matricula');
    }

    public function asistencias()
    {
        return $this->hasMany(RegistroAsistencia::class, 'id_persona', 'matricula');
    }

    // Accessor para nombre completo
    public function getNombreCompletoAttribute()
    {
        return strtoupper($this->nombre . ' ' . $this->apellido);
    }
}
