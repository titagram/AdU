<?php

namespace App\Http\Middleware;

use App\Models\LogAccesso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro degli accessi.
 *
 * DIFETTO INTENZIONALE (L24, capitolo 1): il registro c'è, ma non serve a ricostruire un
 * attacco. Le omissioni sono quattro e tutte plausibili:
 *
 *  1. i tentativi falliti non si registrano — proprio quelli che direbbero «qualcuno sta
 *     provando migliaia di codici fiscali»;
 *  2. nessun identificativo di correlazione: le righe non si legano in una catena;
 *  3. ore in UTC senza dichiararlo: chi le legge dall'Italia sbaglia di un'ora d'inverno e di
 *     due d'estate, e non ha modo di accorgersene dalla riga;
 *  4. `User-Agent` registrato così com'è, senza ripulire: la pagina che mostra i log lo
 *     rimanda al browser senza escape (L25 della guida: log injection).
 *
 * La correzione non è aggiungere campi: è decidere cosa un registro deve raccontare.
 */
class RegistraAccesso
{
    public function handle(Request $request, Closure $next): Response
    {
        $risposta = $next($request);

        // Un tentativo fallito non è un accesso: non si registra.
        if (in_array($risposta->getStatusCode(), [401, 403], true)) {
            return $risposta;
        }

        $utente = $request->attributes->get('utente');

        LogAccesso::create([
            'avvenuto_il' => now()->format('Y-m-d H:i:s'),
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
