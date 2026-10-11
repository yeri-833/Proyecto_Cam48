<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroAsistencia extends Model
{
    protected $table = 'registros_asistencia';
    public $timestamps = true;

    protected $fillable = [
        'asistible_type', 'asistible_id', 'fecha', 'hora'
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function asistible()
    {
        return $this->morphTo();
    }
}
