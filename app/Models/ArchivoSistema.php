<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla `archivos_sistema`.
 *
 * @property int $id PK auto-incremental
 * @property string $matricula FK → alumnos.matricula
 * @property string $nombre_original Nombre original del archivo subido
 * @property string $nombre_servidor Nombre almacenado en el servidor
 * @property string|null $tipo_mime MIME type del archivo
 * @property int|null $tamano_bytes Tamaño en bytes
 * @property string|null $fecha_subida Fecha y hora de la subida
 * @property string|null $url Ruta de visualización (asignada en controlador)
 * @property string|null $download_url Ruta de descarga (asignada en controlador)
 * @property string|null $fecha_formateada Fecha legible (asignada en controlador)
 * @property string|null $tamano_legible Tamaño legible (asignada en controlador)
 * @property-read Alumno|null $alumno           Alumno propietario del archivo
 */
class ArchivoSistema extends Model
{
    protected $table = 'archivos_sistema';

    public $timestamps = false; // Usa 'fecha_subida' por defecto de la BD original

    protected $fillable = [
        'matricula',
        'nombre_original',
        'nombre_servidor',
        'tipo_mime',
        'tamano_bytes',
        'fecha_subida',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'matricula', 'matricula');
    }
}
