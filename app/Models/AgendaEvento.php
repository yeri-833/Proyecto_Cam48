<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgendaEvento extends Model
{
    protected $table = 'agenda_eventos';
    public $timestamps = false;

    protected $fillable = [
        'title', 'start_date', 'end_date', 'color'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];
}
