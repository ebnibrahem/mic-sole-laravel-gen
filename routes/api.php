<?php

use Illuminate\Support\Facades\Route;
use MicSoleLaravelGen\Http\Controllers\CrudGeneratorController;

// CRUD Generator API Routes
Route::prefix('api/v1/generator')->group(function () {
    Route::post('/generate', [CrudGeneratorController::class, 'generate'])->name('mic-sole.generator.generate');
});

