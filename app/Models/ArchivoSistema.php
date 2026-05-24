<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoSistema extends Model
{
    protected $table = 'archivos_sistema';
    public $timestamps = false; // Usa 'fecha_subida' por defecto de la BD original

    protected $fillable = [
        'matricula',
        'nombre_original',
        'nombre_servidor',
        'tipo_mime',
        'tamano_bytes',
        'fecha_subida'
    ];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'matricula', 'matricula');
    }
}
