<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo Eloquent para la tabla `tutores`.
 *
 * @property int $id PK auto-incremental
 * @property string $matricula_alumno FK → alumnos.matricula
 * @property string|null $t1_nombre Nombre completo tutor 1
 * @property string|null $t1_parentesco Parentesco tutor 1
 * @property string|null $t1_telefono Teléfono tutor 1
 * @property string|null $t1_direccion Dirección tutor 1
 * @property string|null $t1_profesion Profesión tutor 1
 * @property string|null $t1_foto_perfil Ruta foto tutor 1 en storage
 * @property string|null $t1_telegram_chat_id Chat ID de Telegram tutor 1
 * @property string|null $t2_nombre Nombre completo tutor 2
 * @property string|null $t2_parentesco Parentesco tutor 2
 * @property string|null $t2_telefono Teléfono tutor 2
 * @property-read string      $nombre_completo  Accessor: t1_nombre en mayúsculas
 * @property-read Alumno|null $alumno           Alumno relacionado
 * @property-read Collection<int, RegistroAsistencia> $asistencias
 */
class Tutor extends Model
{
    protected $table = 'tutores';

    public $timestamps = false;

    protected $fillable = [
        'matricula_alumno',
        't1_nombre',
        't1_parentesco',
        't1_telefono',
        't1_direccion',
        't1_profesion',
        't1_foto_perfil',
        't1_telegram_chat_id',
        't2_nombre',
        't2_parentesco',
        't2_telefono',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'matricula_alumno', 'matricula');
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias(): MorphMany
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }

    public function getNombreCompletoAttribute(): string
    {
        return strtoupper(trim($this->t1_nombre ?? ''));
    }
}
