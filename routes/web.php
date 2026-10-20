<?php

use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\CredencialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadisticasController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TutorController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:personal-access'])->name('dashboard');

Route::put('/alumnos/{matricula}', [AlumnoController::class, 'update'])
    ->middleware(['auth', 'can:alumnos.actualizar'])->name('alumnos.update');

Route::post('/alumnos', [AlumnoController::class, 'store'])
    ->middleware(['auth', 'can:alumnos.crear'])->name('alumnos.store');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Módulo de Personal (Directorio / Nómina Institucional)
    Route::get('/personal', [PersonalController::class, 'index'])
        ->middleware('can:personal.consultar')->name('personal.index');
    Route::post('/personal', [PersonalController::class, 'store'])
        ->middleware('can:personal.crear')->name('personal.store');
    Route::put('/personal/{id}', [PersonalController::class, 'update'])
        ->middleware('can:personal.actualizar')->name('personal.update');
    Route::delete('/personal/{id}', [PersonalController::class, 'destroy'])
        ->middleware('can:personal.eliminar')->name('personal.destroy');
    Route::post('/personal/{id}/mensaje', [PersonalController::class, 'enviarMensaje'])
        ->middleware('can:notificaciones.personal')->name('personal.mensaje');
    Route::post('/personal/mensaje-masivo', [PersonalController::class, 'enviarMensajeMasivo'])
        ->middleware('can:notificaciones.personal')->name('personal.mensajeMasivo');
    Route::post('/personal/{id}/invitacion-telegram', [PersonalController::class, 'enviarInvitacionTelegram'])
        ->middleware('can:notificaciones.personal')->name('personal.invitacion');

    // Módulo de Asistencia (Escáner QR)
    Route::get('/escaner', [AsistenciaController::class, 'escaner'])
        ->middleware('can:asistencia.escanear')->name('asistencia.escaner');
    Route::post('/escaner/procesar', [AsistenciaController::class, 'procesarQr'])
        ->middleware(['can:asistencia.escanear', 'throttle:60,1'])
        ->name('asistencia.procesarQr');
    Route::post('/escaner/desbloquear', [AsistenciaController::class, 'desbloquearKiosco'])
        ->middleware('can:asistencia.escanear')->name('asistencia.desbloquear');

    // Historial Diario de Accesos
    Route::get('/historial/exportar', [HistorialController::class, 'exportar'])
        ->middleware('can:historial.exportar')->name('historial.exportar');
    Route::get('/historial', [HistorialController::class, 'index'])
        ->middleware('can:historial.consultar')->name('historial.index');
    Route::delete('/historial/{id}', [HistorialController::class, 'destroy'])
        ->middleware('can:historial.eliminar')->name('historial.destroy');

    // Generador de Credenciales QR
    Route::get('/credenciales', [CredencialController::class, 'index'])
        ->middleware('can:credenciales.generar')->name('credenciales.index');

    // Estadísticas y Gráficas
    Route::get('/estadisticas', [EstadisticasController::class, 'index'])
        ->middleware('can:estadisticas.consultar')->name('estadisticas.index');

    // Directorio de Tutores
    Route::get('/tutores', [TutorController::class, 'index'])
        ->middleware('can:tutores.consultar')->name('tutores.index');
    Route::post('/tutores', [TutorController::class, 'store'])
        ->middleware('can:tutores.crear')->name('tutores.store');
    Route::delete('/tutores/{id}', [TutorController::class, 'destroy'])
        ->middleware('can:tutores.eliminar')->name('tutores.destroy');
    Route::post('/tutores/{id}/mensaje', [TutorController::class, 'enviarMensaje'])
        ->middleware('can:notificaciones.tutores')->name('tutores.mensaje');
    Route::post('/tutores/{id}/invitacion-telegram', [TutorController::class, 'enviarInvitacionTelegram'])
        ->middleware('can:notificaciones.tutores')->name('tutores.invitacion');

    // Expediente Digital de Alumnos (Rutas protegidas con autenticación y Policy)
    Route::get('/expedientes', [ExpedienteController::class, 'index'])
        ->middleware('can:personal-access')->name('expedientes.index');
    Route::post('/expedientes', [ExpedienteController::class, 'store'])
        ->middleware('can:personal-access')->name('expedientes.store');
    Route::get('/expedientes/{id}/ver', [ExpedienteController::class, 'ver'])
        ->middleware('can:personal-access')->name('expedientes.ver');
    Route::get('/expedientes/{id}/descargar', [ExpedienteController::class, 'descargar'])
        ->middleware('can:personal-access')->name('expedientes.descargar');
    Route::delete('/expedientes/{id}', [ExpedienteController::class, 'destroy'])
        ->middleware('can:admin-access')->name('expedientes.destroy');

    // Sincronización Segura de Telegram (Autenticada, con rate limiting y bloqueo atómico)
    Route::get('/telegram/sync', function () {
        $lock = null;
        $lockAdquirido = false;
        try {
            $lock = Cache::lock('telegram_sync_lock', 10);
            $lockAdquirido = $lock->get();
            if (! $lockAdquirido) {
                return response()->json([
                    'status' => 'busy',
                    'message' => 'Sincronización en curso, por favor espere.',
                ], 429);
            }

            $exitCode = Artisan::call('telegram:sync');
            $mensaje = $exitCode === 0 ? 'Sincronización ejecutada correctamente' : 'No fue posible completar la sincronización';

            return response()->json([
                'status' => $exitCode === 0 ? 'ok' : 'error',
                'message' => $mensaje,
                'output' => $mensaje,
                'code' => $exitCode === 0 ? 'TELEGRAM_SYNC_OK' : 'TELEGRAM_SYNC_FAILED',
            ]);
        } catch (Throwable $exception) {
            Log::error('[TELEGRAM_SYNC_FAILED] No fue posible sincronizar Telegram.', [
                'exception_type' => $exception::class,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al sincronizar con Telegram',
                'code' => 'TELEGRAM_SYNC_FAILED',
            ], 500);
        } finally {
            if ($lockAdquirido && $lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable $exception) {
                    Log::error('[TELEGRAM_SYNC_LOCK_RELEASE_FAILED] No fue posible liberar el bloqueo de sincronización.', [
                        'exception_type' => $exception::class,
                    ]);
                }
            }
        }
    })->middleware(['can:telegram.sincronizar', 'throttle:20,1'])->name('telegram.sync');

    // Agenda y Eventos
    Route::get('/eventos', [EventoController::class, 'index'])
        ->middleware('can:eventos.consultar')->name('eventos.index');
    Route::post('/eventos', [EventoController::class, 'store'])
        ->middleware('can:eventos.crear')->name('eventos.store');
    Route::delete('/eventos/{id}', [EventoController::class, 'destroy'])
        ->middleware('can:eventos.eliminar')->name('eventos.destroy');
});

require __DIR__.'/auth.php';
