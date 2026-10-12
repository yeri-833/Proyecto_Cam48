<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// Las operaciones administrativas de sincronización se han trasladado a rutas autenticadas en web.php
Route::any('/telegram/sync', function () {
    return response()->json([
        'status' => 'error',
        'message' => 'No autorizado. La sincronización requiere una sesión administrativa activa.'
    ], 401);
});
