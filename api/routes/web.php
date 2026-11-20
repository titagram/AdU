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

// DIFETTO INTENZIONALE (L19): le due pagine che il capitolo 1 fa trovare. Nessuna
// autenticazione, esattamente come sono arrivate in produzione.
Route::get('/debug', [DiagnosticaController::class, 'console']);
Route::get('/admin/dipendenze', [DiagnosticaController::class, 'dipendenze']);
Route::get('/admin/log', [DiagnosticaController::class, 'registro']);
