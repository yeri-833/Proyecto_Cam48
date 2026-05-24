<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tutor extends Model
{
    use SoftDeletes;
    protected $table = 'tutores';
    public $timestamps = false;

    protected $fillable = [
        'matricula_alumno', 't1_nombre', 't1_apellido', 't1_telefono', 't1_email', 't1_telegram_chat_id', 't1_foto_perfil', 't2_foto_perfil'
    ];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'matricula_alumno', 'matricula');
    }
}
