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
    Route::get('/personal', [App\Http\Controllers\PersonalController::class, 'index'])->name('personal.index');
    Route::post('/personal', [App\Http\Controllers\PersonalController::class, 'store'])->name('personal.store');
    Route::put('/personal/{id}', [App\Http\Controllers\PersonalController::class, 'update'])->name('personal.update');
    Route::delete('/personal/{id}', [App\Http\Controllers\PersonalController::class, 'destroy'])->name('personal.destroy');
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
    Route::get('/historial', [App\Http\Controllers\HistorialController::class, 'index'])->name('historial.index');
    Route::get('/historial/exportar', [App\Http\Controllers\HistorialController::class, 'exportar'])->name('historial.exportar');
    Route::delete('/historial/{id}', [App\Http\Controllers\HistorialController::class, 'destroy'])->name('historial.destroy');

    // Generador de Credenciales QR
    Route::get('/credenciales', [App\Http\Controllers\CredencialController::class, 'index'])->name('credenciales.index');

    // Estadísticas y Gráficas
    Route::get('/estadisticas', [App\Http\Controllers\EstadisticasController::class, 'index'])->name('estadisticas.index');

    // Directorio de Tutores
    Route::get('/tutores', [App\Http\Controllers\TutorController::class, 'index'])->name('tutores.index');
    Route::post('/tutores', [App\Http\Controllers\TutorController::class, 'store'])->name('tutores.store');
    Route::post('/tutores/{id}/mensaje', [App\Http\Controllers\TutorController::class, 'enviarMensaje'])->name('tutores.mensaje');
    Route::post('/tutores/{id}/invitacion-telegram', [App\Http\Controllers\TutorController::class, 'enviarInvitacionTelegram'])->name('tutores.invitacion');
    Route::delete('/tutores/{id}', [App\Http\Controllers\TutorController::class, 'destroy'])->name('tutores.destroy');
    
    // Background Sync (Telegram)
    Route::get('/api/telegram/sync', [App\Http\Controllers\TutorController::class, 'syncTelegram'])
        ->middleware('throttle:30,1')->name('telegram.sync');

    // Expediente Digital de Alumnos
    Route::get('/expedientes', [App\Http\Controllers\ExpedienteController::class, 'index'])->name('expedientes.index');
    Route::post('/expedientes', [App\Http\Controllers\ExpedienteController::class, 'store'])->name('expedientes.store');
    Route::delete('/expedientes/{id}', [App\Http\Controllers\ExpedienteController::class, 'destroy'])->name('expedientes.destroy');

    // Agenda y Eventos
    Route::get('/eventos', [App\Http\Controllers\EventoController::class, 'index'])->name('eventos.index');
    Route::post('/eventos', [App\Http\Controllers\EventoController::class, 'store'])->name('eventos.store');
    Route::delete('/eventos/{id}', [App\Http\Controllers\EventoController::class, 'destroy'])->name('eventos.destroy');
});

require __DIR__.'/auth.php';
