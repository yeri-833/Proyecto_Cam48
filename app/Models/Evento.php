<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function asistencias(): HasMany
    {
        return $this->hasMany(RegistroAsistencia::class, 'evento_id');
    }

    /**
     * Convocatorias individuales registradas para este evento escolar.
     *
     * @return HasMany<EventoConvocatoria, $this>
     */
    public function convocatorias(): HasMany
    {
        return $this->hasMany(EventoConvocatoria::class, 'evento_id');
    }

    /**
     * Tutores explícitamente convocados a este evento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Tutor, $this>
     */
    public function tutoresConvocados(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tutor::class, 'evento_convocatorias', 'evento_id', 'tutor_id')
            ->withPivot(['alumno_matricula', 'canal_notificacion', 'notificado', 'asistio', 'fecha_asistencia'])
            ->withTimestamps();
    }
}
