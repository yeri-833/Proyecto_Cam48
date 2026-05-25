<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$alumno = \App\Models\Alumno::whereNotNull('matricula')->first();
if ($alumno) {
    echo "Alumno encontrado: " . $alumno->nombre_completo . "\n";
    \App\Jobs\NotificarAsistenciaJob::dispatch($alumno, 'ENTRADA', '08:00 AM', $alumno->nombre_completo);
    echo "Job despachado correctamente.\n";
} else {
    echo "No se encontraron alumnos en la BD.\n";
}
