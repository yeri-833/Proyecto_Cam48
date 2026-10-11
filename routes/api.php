<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/telegram/sync', function () {
    $exitCode = Artisan::call('telegram:sync');
    $output = Artisan::output();
    
    if ($exitCode === 0) {
        return response()->json([
            'status' => 'ok',
            'message' => 'Sincronización ejecutada correctamente',
            'output' => trim($output)
        ]);
    }
    
    return response()->json([
        'status' => 'error',
        'message' => 'Error al sincronizar Telegram',
        'output' => trim($output)
    ], 500);
})->middleware('throttle:30,1')->name('api.telegram.sync');
