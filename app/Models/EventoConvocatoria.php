<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla `evento_convocatorias`.
 * Gestiona la convocatoria explícita de tutores a eventos/juntas escolares
 * y la verificación de su asistencia presencial.
 *
 * @property int $id PK
 * @property int $evento_id FK → eventos.id
 * @property int $tutor_id FK → tutores.id
 * @property string|null $alumno_matricula FK → alumnos.matricula
 * @property string|null $canal_notificacion (telegram, whatsapp, presencial)
 * @property bool $notificado
 * @property bool $asistio
 * @property Carbon|null $fecha_asistencia
 * @property-read Evento $evento
 * @property-read Tutor $tutor
 * @property-read Alumno|null $alumno
 */
class EventoConvocatoria extends Model
{
    protected $table = 'evento_convocatorias';

    protected $fillable = [
        'evento_id',
        'tutor_id',
        'alumno_matricula',
        'canal_notificacion',
        'notificado',
        'asistio',
        'fecha_asistencia',
    ];

    protected $casts = [
        'notificado' => 'boolean',
        'asistio' => 'boolean',
        'fecha_asistencia' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class, 'tutor_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_matricula', 'matricula');
    }
}
