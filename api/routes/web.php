<?php

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
