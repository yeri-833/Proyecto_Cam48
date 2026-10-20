<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo Eloquent para la tabla `personal`.
 *
 * @property int $id PK auto-incremental
 * @property string|null $rfc RFC del trabajador
 * @property string $nombre_completo Nombre completo (columna real)
 * @property string|null $categoria Categoría laboral
 * @property string|null $funcion Función o cargo
 * @property string|null $plaza Número de plaza
 * @property string|null $grado_grupo Grado y grupo que atiende
 * @property string|null $telefono Teléfono de contacto
 * @property string|null $email Correo electrónico
 * @property bool $activo Si el trabajador está activo
 * @property string|null $foto_perfil Ruta relativa de foto en storage
 * @property string|null $telegram_chat_id Chat ID de Telegram
 * @property string|null $creado_por Usuario que lo registró
 * @property string|null $fecha_registro Fecha de alta
 * @property-read Collection<int, RegistroAsistencia> $asistencias
 */
class Personal extends Model
{
    protected $table = 'personal';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id', 'rfc', 'nombre_completo', 'categoria', 'funcion',
        'plaza', 'grado_grupo', 'telefono', 'email', 'activo',
        'foto_perfil', 'telegram_chat_id', 'creado_por', 'fecha_registro',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return strtoupper($this->attributes['nombre_completo'] ?? '');
    }

    /**
     * Parsea y normaliza una asignación escolar de grado y grupo.
     * Retorna array con ['grado' => string, 'grupo' => string] o null si es ambigua o inválida.
     *
     * @return array{grado: string, grupo: string}|null
     */
    public static function parseGradoGrupo(?string $gradoGrupo): ?array
    {
        if (empty($gradoGrupo)) {
            return null;
        }

        $clean = trim($gradoGrupo);

        // Formatos admitidos: '1° A', '1 A', '1°A', '1A', '1-A', '1 - A'
        if (preg_match('/^(\d+)\s*°?\s*[-–—]?\s*([A-Za-z]+)$/u', $clean, $matches)) {
            return [
                'grado' => $matches[1],
                'grupo' => strtoupper($matches[2]),
            ];
        }

        return null;
    }

    /** @return array{grado: string, grupo: string}|null */
    public static function verifiedAssignmentFor(User $user): ?array
    {
        if (! $user->isPersonal() || trim($user->email) === '') {
            return null;
        }

        $fichas = static::where('email', $user->email)
            ->where('activo', 1)
            ->limit(2)
            ->get(['grado_grupo']);

        // Varias fichas activas no demuestran una asignación única.
        if ($fichas->count() !== 1) {
            return null;
        }

        return static::parseGradoGrupo($fichas->first()->grado_grupo);
    }

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias(): MorphMany
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }
}
