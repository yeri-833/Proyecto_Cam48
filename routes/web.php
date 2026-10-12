<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::put('/alumnos/{matricula}', [App\Http\Controllers\AlumnoController::class, 'update'])
    ->middleware(['auth'])->name('alumnos.update');

Route::post('/alumnos', [App\Http\Controllers\AlumnoController::class, 'store'])
    ->middleware(['auth'])->name('alumnos.store');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Módulo de Personal
    Route::resource('personal', App\Http\Controllers\PersonalController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/personal/{id}/mensaje', [App\Http\Controllers\PersonalController::class, 'enviarMensaje'])->name('personal.mensaje');
    Route::post('/personal/mensaje-masivo', [App\Http\Controllers\PersonalController::class, 'enviarMensajeMasivo'])->name('personal.mensajeMasivo');
    Route::post('/personal/{id}/invitacion-telegram', [App\Http\Controllers\PersonalController::class, 'enviarInvitacionTelegram'])->name('personal.invitacion');

    // Módulo de Asistencia (Escáner QR)
    Route::get('/escaner', [App\Http\Controllers\AsistenciaController::class, 'escaner'])->name('asistencia.escaner');
    Route::post('/escaner/procesar', [App\Http\Controllers\AsistenciaController::class, 'procesarQr'])
        ->middleware('throttle:60,1')
        ->name('asistencia.procesarQr');
    Route::post('/escaner/desbloquear', [App\Http\Controllers\AsistenciaController::class, 'desbloquearKiosco'])->name('asistencia.desbloquear');

    // Historial Diario de Accesos
    Route::get('/historial/exportar', [App\Http\Controllers\HistorialController::class, 'exportar'])->name('historial.exportar');
    Route::resource('historial', App\Http\Controllers\HistorialController::class)->only(['index', 'destroy']);

    // Generador de Credenciales QR
    Route::get('/credenciales', [App\Http\Controllers\CredencialController::class, 'index'])->name('credenciales.index');

    // Estadísticas y Gráficas
    Route::get('/estadisticas', [App\Http\Controllers\EstadisticasController::class, 'index'])->name('estadisticas.index');

    // Directorio de Tutores
    Route::resource('tutores', App\Http\Controllers\TutorController::class)->only(['index', 'store', 'destroy']);
    Route::post('/tutores/{id}/mensaje', [App\Http\Controllers\TutorController::class, 'enviarMensaje'])->name('tutores.mensaje');
    Route::post('/tutores/{id}/invitacion-telegram', [App\Http\Controllers\TutorController::class, 'enviarInvitacionTelegram'])->name('tutores.invitacion');
    
    // Expediente Digital de Alumnos (Rutas protegidas con autenticación)
    Route::get('/expedientes/{id}/ver', [App\Http\Controllers\ExpedienteController::class, 'ver'])->name('expedientes.ver');
    Route::get('/expedientes/{id}/descargar', [App\Http\Controllers\ExpedienteController::class, 'descargar'])->name('expedientes.descargar');
    Route::resource('expedientes', App\Http\Controllers\ExpedienteController::class)->only(['index', 'store', 'destroy']);

    // Sincronización Segura de Telegram (Autenticada, con rate limiting y bloqueo atómico)
    Route::get('/telegram/sync', function () {
        $lock = \Illuminate\Support\Facades\Cache::lock('telegram_sync_lock', 10);
        if (!$lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Sincronización en curso, por favor espere.'
            ], 429);
        }

        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('telegram:sync');
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'status' => $exitCode === 0 ? 'ok' : 'error',
                'message' => $exitCode === 0 ? 'Sincronización ejecutada correctamente' : 'Error en la sincronización',
                'output' => trim($output)
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en sincronización segura de Telegram: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al sincronizar con Telegram'
            ], 500);
        } finally {
            $lock->release();
        }
    })->middleware('throttle:20,1')->name('telegram.sync');

    // Agenda y Eventos
    Route::resource('eventos', App\Http\Controllers\EventoController::class)->only(['index', 'store', 'destroy']);
});

require __DIR__.'/auth.php';
