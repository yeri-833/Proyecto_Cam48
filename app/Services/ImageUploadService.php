<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadService
{
    /**
     * Procesa, comprime y guarda una imagen.
     */
    public function procesarYGuardar($file, $path = 'fotos/alumnos/', $width = 800)
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());
        
        // Escalar manteniendo proporción
        $image->scaleDown(width: $width);
        
        // Convertir a JPG al 80% de calidad
        $encoded = $image->toJpeg(quality: 80);
        
        $filename = $path . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, $encoded->toString());
        
        return $filename;
    }
}
