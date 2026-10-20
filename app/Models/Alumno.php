<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo Eloquent para la tabla `alumnos`.
 *
 * @property string $matricula Clave única de alumno (PK)
 * @property string $nombre Primer nombre
 * @property string $apellido Apellidos
 * @property string|null $sexo 'M' | 'F'
 * @property string|null $curp CURP de 18 caracteres
 * @property string|null $nivel_educativo Nivel educativo (Básica, etc.)
 * @property string|null $grado Grado escolar
 * @property string|null $grupo Grupo (A, B, C…)
 * @property string|null $discapacidad Tipo de discapacidad si aplica
 * @property bool $activo Si el alumno está activo
 * @property string|null $fecha_nacimiento Fecha de nacimiento
 * @property int|null $edad Edad calculada
 * @property string|null $comentarios Observaciones generales
 * @property string|null $rol Rol dentro del sistema
 * @property string|null $foto_perfil Ruta relativa de la foto en storage
 * @property string|null $fecha_registro Fecha de alta (CREATED_AT)
 * @property-read string       $nombre_completo  Accessor: nombre + apellido en mayúsculas
 * @property-read Tutor|null   $tutor            Relación 1:1 con tutores
 * @property-read Collection<int, RegistroAsistencia> $asistencias
 */
class Alumno extends Model
{
    protected $table = 'alumnos';

    protected $primaryKey = 'matricula';

    public $incrementing = false;

    protected $keyType = 'string';

    // La tabla solo tiene 'fecha_registro', no 'updated_at'
    public $timestamps = true;

    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = null;

    protected $fillable = [
        'matricula', 'nombre', 'apellido', 'sexo', 'curp',
        'nivel_educativo', 'grado', 'grupo', 'discapacidad', 'activo',
        'fecha_nacimiento', 'edad', 'comentarios', 'rol', 'foto_perfil',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * @param  Builder<Alumno>  $query
     * @return Builder<Alumno>
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $asignacion = Personal::verifiedAssignmentFor($user);
        if ($asignacion === null) {
            return $query->whereRaw('0 = 1');
        }

        // HEX evita equivalencias por acentos de la intercalación institucional.
        return $query->where('alumnos.activo', 1)
            ->whereRaw('HEX(TRIM(alumnos.grado)) IN (?, ?)', [
                strtoupper(bin2hex($asignacion['grado'])),
                strtoupper(bin2hex($asignacion['grado'].'°')),
            ])
            ->whereRaw('HEX(UPPER(TRIM(alumnos.grupo))) = ?', [strtoupper(bin2hex($asignacion['grupo']))])
            ->whereRaw('HEX(LOWER(TRIM(alumnos.grupo))) = ?', [strtoupper(bin2hex(strtolower($asignacion['grupo'])))]);
    }

    public function tutor(): HasOne
    {
        return $this->hasOne(Tutor::class, 'matricula_alumno', 'matricula');
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias(): MorphMany
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }

    // Accessor para nombre completo
    public function getNombreCompletoAttribute(): string
    {
        return strtoupper(trim(($this->nombre ?? '').' '.($this->apellido ?? '')));
    }
}
