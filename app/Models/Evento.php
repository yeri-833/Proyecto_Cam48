<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes;
    protected $table = 'eventos';
    public $timestamps = false;

    protected $fillable = [
        'nombre_evento', 'fecha_hora', 'ubicacion'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];
}
