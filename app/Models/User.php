<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Modelo Eloquent para la tabla `usuarios`.
 *
 * @property int $id PK auto-incremental
 * @property string $nombre_completo Nombre completo del usuario (columna real)
 * @property string $correo Correo electrónico (columna real, alias de 'email')
 * @property string $password Contraseña hasheada
 * @property string|null $tipo_usuario Tipo/rol: 'Director', 'Docente', etc.
 * @property string|null $telefono Teléfono de contacto
 * @property string|null $remember_token Token de "remember me"
 * @property string|null $email_verified_at Fecha de verificación de correo
 * @property string|null $updated_at Última actualización
 * @property string|null $fecha_registro Fecha de alta (CREATED_AT)
 * @property-read string $email  Alias accessor de correo
 * @property-read string $name   Alias accessor de nombre_completo
 * @property-read string $role   Alias accessor de tipo_usuario
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * La tabla real en la base de datos.
     */
    protected $table = 'usuarios';

    /**
     * El campo que se usa como username para autenticación.
     */
    protected $primaryKey = 'id';

    /**
     * Mapeo de campos de Laravel → columnas reales de la tabla.
     * Laravel usa internamente 'email' y 'name'; los mapeamos aquí.
     */
    protected $fillable = [
        'nombre_completo',
        'correo',
        'password',
        'tipo_usuario',
        'telefono',
        'remember_token',
        'email_verified_at',
        'updated_at',
        'name',
        'email',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * La tabla usa 'fecha_registro' como created_at pero no tiene updated_at nativo.
     * Usamos timestamps parciales.
     */
    public $timestamps = true;

    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = 'updated_at';

    /**
     * Atributos casteados.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Aliases: Laravel internamente espera 'email' y 'name'
    // Los siguientes accessors permiten compatibilidad sin renombrar columnas
    // ──────────────────────────────────────────────────────────────

    public function getEmailAttribute(): string
    {
        return $this->attributes['correo'] ?? '';
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['correo'] = $value;
    }

    public function getNameAttribute(): string
    {
        return $this->attributes['nombre_completo'] ?? '';
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['nombre_completo'] = $value;
    }

    public function getRoleAttribute(): string
    {
        return $this->attributes['tipo_usuario'] ?? '';
    }

    public function setRoleAttribute(string $value): void
    {
        $this->attributes['tipo_usuario'] = $value;
    }

    /**
     * Alias interno de compatibilidad para el perfil Director.
     */
    public function isAdmin(): bool
    {
        return $this->isDirector();
    }

    public function isDirector(): bool
    {
        return $this->canonicalRole() === 'Director';
    }

    public function isSecretaria(): bool
    {
        return $this->canonicalRole() === 'Secretaria';
    }

    public function isOperador(): bool
    {
        return $this->canonicalRole() === 'Operador';
    }

    public function canonicalRole(): ?string
    {
        return match (strtolower(trim($this->role))) {
            'director', 'directivo' => 'Director',
            'secretaria', 'secretario' => 'Secretaria',
            'operador' => 'Operador',
            default => null,
        };
    }

    /**
     * Compatibilidad interna para Director y Secretaria; no sustituye Gates por operación.
     */
    public function isPersonal(): bool
    {
        return $this->isDirector() || $this->isSecretaria();
    }

    /**
     * Comprueba si el usuario tiene permisos para operar el kiosco o escáner QR.
     */
    public function isKiosco(): bool
    {
        return $this->isDirector() || $this->isOperador();
    }

    /**
     * Campo usado por Laravel Auth para buscar el usuario al hacer login.
     */
    public function getAuthIdentifierName(): string
    {
        return 'correo';
    }
}
