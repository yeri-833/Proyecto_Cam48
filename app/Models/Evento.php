<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla `eventos`.
 *
 * @property int $id PK auto-incremental
 * @property string $nombre_evento Nombre del evento
 * @property Carbon $fecha_hora Fecha y hora del evento (cast datetime)
 * @property string|null $ubicacion Lugar del evento
 * @property string|null $fecha_formateada Fecha legible (asignado en controlador)
 * @property string|null $hora_formateada Hora legible (asignado en controlador)
 * @property bool|null $es_hoy Si el evento es hoy (asignado en controlador)
 */
class Evento extends Model
{
    protected $table = 'eventos';

    public $timestamps = false;

    protected $fillable = [
        'nombre_evento', 'fecha_hora', 'ubicacion',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];
}
