<?php

use Illuminate\Support\Facades\Route;
use MicSoleLaravelGen\Http\Controllers\CrudGeneratorController;

Route::post('/generator', [CrudGeneratorController::class, 'generate']);
Route::get('/generator', function () {
    return view('generator');
});
