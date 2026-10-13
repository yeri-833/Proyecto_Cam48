<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    /**
     * Procesa, comprime, corrige orientación y guarda una imagen usando Intervention Image v4.
     *
     * @param  UploadedFile  $file  Archivo subido por el usuario.
     * @param  string  $path  Ruta relativa dentro del disco 'public' (termina en /).
     * @param  int  $width  Ancho máximo al que se escala la imagen.
     * @return string Ruta relativa almacenada (para guardar en BD).
     */
    public function procesarYGuardar(UploadedFile $file, string $path = 'fotos/alumnos/', int $width = 800): string
    {
        $manager = new ImageManager(new Driver);

        // v4: decodePath() reemplaza a read()
        $image = $manager->decodePath($file->getRealPath());

        // Corregir orientación EXIF automáticamente antes de escalar
        $image->scaleDown(width: $width);

        // v4: encode(new JpegEncoder()) reemplaza a toJpeg()
        $encoded = $image->encode(new JpegEncoder(quality: 80));

        $filename = $path.uniqid().'.jpg';
        Storage::disk('public')->put($filename, $encoded->toString());

        return $filename;
    }
}
