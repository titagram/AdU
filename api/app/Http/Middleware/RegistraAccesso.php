<?php

namespace App\Http\Middleware;

use App\Models\LogAccesso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro degli accessi.
 *
 * In M1 registra tutto, in UTC e con il codice fiscale di chi ha fatto la richiesta.
 * In M5 le omissioni saranno deliberate: niente login falliti, niente correlazione, ora
 * locale, `User-Agent` concatenato senza escape.
 */
class RegistraAccesso
{
    public function handle(Request $request, Closure $next): Response
    {
        $risposta = $next($request);

        $utente = $request->attributes->get('utente');

        LogAccesso::create([
            'avvenuto_il' => now()->utc(),
            'codice_fiscale' => $request->input('codice_fiscale') ?? $utente?->codice_fiscale,
            'ip' => $request->ip(),
            'metodo' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $risposta->getStatusCode(),
            'user_agent' => $request->userAgent(),
        ]);

        return $risposta;
    }
}
