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
 * @property-read Collection<int, Alumno> $alumnos_vinculados Alumnos asociados a la familia
 * @property-read string      $nombres_alumnos  Nombres de todos los alumnos asociados
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
        't1_email',
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

    /**
     * Relación explícita N:M de alumnos vinculados a este tutor (tabla alumno_tutor).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Alumno, $this>
     */
    public function alumnos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_tutor', 'tutor_id', 'alumno_matricula', 'id', 'matricula')
            ->withPivot(['parentesco', 'es_tutor_principal']);
    }

    /**
     * Convocatorias explícitas a juntas y eventos escolares.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<EventoConvocatoria, $this>
     */
    public function convocatorias(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventoConvocatoria::class, 'tutor_id');
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias(): MorphMany
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }

    public function getNombreCompletoAttribute(): string
    {
        return mb_strtoupper(trim($this->t1_nombre ?? ''), 'UTF-8');
    }

    /**
     * Clave canónica de identidad familiar independiente del tutor.
     */
    public function getFamiliaKeyAttribute(): string
    {
        return app(\App\Services\FamiliaService::class)->obtenerClaveFamilia($this);
    }

    /**
     * Retorna todos los alumnos asociados a la familia de este tutor.
     * Si existen registros explícitos en la tabla `alumno_tutor`, se devuelven éstos con máxima fidelidad.
     * De lo contrario, se aplica la detección estricta mediante coincidencia de nombre y contacto/dirección.
     *
     * @return Collection<int, Alumno>
     */
    public function getAlumnosVinculadosAttribute(): Collection
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('alumno_tutor')) {
            $vinculados = $this->alumnos;
            if ($vinculados->isNotEmpty()) {
                return $vinculados;
            }
        }

        $matriculas = array_filter([(string) $this->matricula_alumno]);
        $nombre = trim(mb_strtoupper((string) ($this->t1_nombre ?? ''), 'UTF-8'));

        if ($nombre !== '') {
            $query = self::query()
                ->where('id', '!=', $this->id)
                ->whereRaw('TRIM(UPPER(t1_nombre)) = ?', [$nombre])
                ->where(function ($sub) {
                    $hasCondition = false;
                    if (! empty($this->t1_telefono)) {
                        $sub->where('t1_telefono', $this->t1_telefono);
                        $hasCondition = true;
                    }
                    if (! empty($this->t1_email)) {
                        if ($hasCondition) {
                            $sub->orWhere('t1_email', $this->t1_email);
                        } else {
                            $sub->where('t1_email', $this->t1_email);
                            $hasCondition = true;
                        }
                    }
                    if (! empty($this->t1_direccion)) {
                        if ($hasCondition) {
                            $sub->orWhere('t1_direccion', $this->t1_direccion);
                        } else {
                            $sub->where('t1_direccion', $this->t1_direccion);
                            $hasCondition = true;
                        }
                    }
                    if (! $hasCondition) {
                        $sub->whereRaw('1 = 0');
                    }
                });

            $hermanosMatriculas = $query->whereNotNull('matricula_alumno')
                ->pluck('matricula_alumno')
                ->all();

            $matriculas = array_unique(array_filter(array_merge($matriculas, $hermanosMatriculas)));
        }

        if (empty($matriculas)) {
            return new Collection;
        }

        return Alumno::whereIn('matricula', $matriculas)->get();
    }

    /**
     * Retorna los nombres de todos los alumnos vinculados a la familia.
     */
    public function getNombresAlumnosAttribute(): string
    {
        $alumnos = $this->alumnos_vinculados;
        if ($alumnos->isEmpty()) {
            return $this->alumno ? $this->alumno->nombre_completo : '';
        }

        return $alumnos->map(fn (Alumno $a) => $a->nombre_completo)->implode(', ');
    }
}
