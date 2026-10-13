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

    // Asistencias polimórficas (asistible_type, asistible_id)
    public function asistencias(): MorphMany
    {
        return $this->morphMany(RegistroAsistencia::class, 'asistible');
    }
}
