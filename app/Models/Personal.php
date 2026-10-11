<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'nombre_completo', 'email', 'foto_perfil', 'rfc', 'categoria', 'funcion',
        'plaza', 'grado_grupo', 'telefono', 'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function getNombreCompletoAttribute()
    {
        return strtoupper($this->attributes['nombre_completo'] ?? '');
    }

    public function asistencias()
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }
}
