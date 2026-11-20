<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie della SPA.
 *
 * Le rotte pubbliche stanno sopra, quelle autenticate sotto `auth.jwt`. La distinzione è
 * l'unica cosa che separa un visitatore da un contribuente, quindi va tenuta esplicita:
 * in M5 il corso mostrerà cosa succede quando un endpoint finisce nel gruppo sbagliato (L03).
 */

Route::get('/health', [AreaController::class, 'health']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth.jwt')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AreaController::class, 'me']);
    Route::get('/cartelle', [AreaController::class, 'cartelle']);
    Route::get('/cartelle/{id}', [AreaController::class, 'cartella'])->whereNumber('id');
    Route::get('/pratiche', [AreaController::class, 'pratiche']);
    Route::get('/pratiche/{id}', [AreaController::class, 'pratica'])->whereNumber('id');
    Route::get('/rimborsi', [AreaController::class, 'rimborsi']);
});
