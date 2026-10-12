<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    /**
     * Campo usado por Laravel Auth para buscar el usuario al hacer login.
     */
    public function getAuthIdentifierName(): string
    {
        return 'correo';
    }
}
