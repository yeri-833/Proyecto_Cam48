<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroAsistencia extends Model
{
    protected $table = 'registros_asistencia';
    public $timestamps = false;

    protected $fillable = [
        'asistible_type',
        'asistible_id',
        'id_persona',
        'rol',
        'fecha',
        'hora',
        'modo_registro',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function asistible()
    {
        return $this->morphTo();
    }
}

