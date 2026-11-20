<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AllegatiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContattiController;
use App\Http\Controllers\Api\RimborsoController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie della SPA.
 *
 * Le rotte pubbliche stanno sopra, quelle autenticate sotto `auth.jwt`. La distinzione è
 * l'unica cosa che separa un visitatore da un contribuente, quindi va tenuta esplicita:
 * in M5 il corso mostrerà cosa succede quando un endpoint finisce nel gruppo sbagliato (L03).
 */

Route::get('/health', [AreaController::class, 'health']);
// DIFETTO INTENZIONALE (L22, capitolo 2): nessun limite ai tentativi. La storia del `.git`
// racconta perché: «rate limit disattivato per il collaudo, riattivare prima della produzione».
Route::post('/login', [AuthController::class, 'login']);

Route::match(['get', 'post'], '/contatti', [ContattiController::class, 'mostra']);

Route::post('/recupera-password', [AuthController::class, 'recupera']);
Route::post('/reimposta-password', [AuthController::class, 'reimposta']);

Route::middleware('auth.jwt')->group(function () {
    Route::post('/rimborsi/{id}/iban', [RimborsoController::class, 'cambiaIban'])->whereNumber('id');
    Route::post('/rimborsi/{id}/richiedi', [RimborsoController::class, 'richiedi'])->whereNumber('id');
    Route::post('/allegati/da-url', [AllegatiController::class, 'daUrl']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AreaController::class, 'me']);
    Route::get('/cartelle', [AreaController::class, 'cartelle']);
    Route::get('/cartelle/{id}', [AreaController::class, 'cartella'])->whereNumber('id');
    Route::get('/pratiche', [AreaController::class, 'pratiche']);
    Route::get('/pratiche/{id}', [AreaController::class, 'pratica'])->whereNumber('id');
    Route::get('/rimborsi', [AreaController::class, 'rimborsi']);
    Route::get('/rimborsi/{id}', [AreaController::class, 'rimborso'])->whereNumber('id');

    // Capitolo 3 — accessi e scritture.
    Route::patch('/cartelle/{id}', [AreaController::class, 'aggiornaCartella'])->whereNumber('id');
    Route::patch('/profilo', [AreaController::class, 'aggiornaProfilo']);
    Route::post('/profilo/preferenze', [AreaController::class, 'salvaPreferenze']);
    Route::post('/pratiche/importa', [AreaController::class, 'importaPratiche']);
    Route::get('/admin/export', [AreaController::class, 'esportaContribuenti']);
    Route::get('/pratiche/{id}/allegato', [AreaController::class, 'scaricaAllegato'])->whereNumber('id');
    Route::post('/pratiche/{id}/allegato', [AreaController::class, 'caricaAllegato'])->whereNumber('id');

    // Capitolo 4 — le note che il funzionario legge.
    Route::post('/pratiche/{id}/nota', [AreaController::class, 'scriviNota'])->whereNumber('id');
    Route::get('/sportello/pratiche', [AreaController::class, 'praticheDelFunzionario']);
});
