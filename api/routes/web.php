<?php

use App\Http\Controllers\DiagnosticaController;
use Illuminate\Support\Facades\Route;

/*
 * Il portale non ha interfaccia server-side: la SPA è servita da `web` su un'altra origine.
 * Questa rotta esiste solo per chi apre l'API nel browser e si chiede dove sia il sito.
 */
Route::get('/', fn () => response()->json([
    'servizio' => 'Agenzia delle Uscite — API',
    'portale' => 'http://127.0.0.1:8080',
    'stato' => 'ok',
]));

// La console SQL è un laboratorio successivo: nella ricognizione iniziale la route
// non esiste. Quando il docente la abilita, resta volutamente pubblica.
if (config('adu.debug_console_enabled')) {
    Route::get('/debug', [DiagnosticaController::class, 'console']);
}

// Queste pagine restano scopribili durante la ricognizione iniziale.
Route::get('/admin/dipendenze', [DiagnosticaController::class, 'dipendenze']);
Route::get('/admin/log', [DiagnosticaController::class, 'registro']);
