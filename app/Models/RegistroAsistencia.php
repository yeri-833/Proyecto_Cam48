<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo Eloquent para la tabla `registros_asistencia`.
 *
 * @property int $id PK auto-incremental
 * @property string $asistible_type FQCN del modelo polimórfico (Alumno, Tutor, Personal)
 * @property int|string $asistible_id PK del registro polimórfico
 * @property string|null $id_persona ID heredado del esquema anterior (transición)
 * @property string|null $rol Rol heredado del esquema anterior (transición)
 * @property string $fecha Fecha del registro (YYYY-MM-DD)
 * @property string $hora Hora del registro (HH:MM:SS)
 * @property int|null $evento_id FK opcional hacia eventos.id
 * @property string|null $modo_registro 'QR' | 'Manual' | etc.
 * @property int|null $total Conteo agregado en consultas estadísticas
 * @property int|null $asistencias Conteo agregado en consultas de ranking
 * @property-read Alumno|Tutor|Personal|null $asistible  Relación polimórfica
 */
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
        'evento_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'evento_id' => 'integer',
    ];

    public function asistible(): MorphTo
    {
        return $this->morphTo();
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }
}
