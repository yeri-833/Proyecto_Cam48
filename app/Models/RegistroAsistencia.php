<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroAsistencia extends Model
{
    protected $table = 'registros_asistencia';
    public $timestamps = false;

    protected $fillable = [
        'id_persona', 'rol', 'fecha', 'hora'
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}
