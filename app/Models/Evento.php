<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $table = 'eventos';
    public $timestamps = false;

    protected $fillable = [
        'nombre_evento', 'fecha_hora', 'ubicacion'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];
}
