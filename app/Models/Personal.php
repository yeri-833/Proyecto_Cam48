<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id', 'rfc', 'nombre_completo', 'categoria', 'funcion',
        'plaza', 'grado_grupo', 'telefono', 'activo', 'creado_por', 'fecha_registro'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function getNombreCompletoAttribute()
    {
        return strtoupper($this->attributes['nombre_completo'] ?? '');
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias()
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }
}

