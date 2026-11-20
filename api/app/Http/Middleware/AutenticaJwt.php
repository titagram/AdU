<?php

namespace App\Http\Middleware;

use App\Models\Utente;
use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticazione della SPA.
 *
 * Il ruolo viene riletto dal database a ogni richiesta: un token emesso ieri non serve a
 * mantenere i permessi che l'utente ha perso oggi. In M2 il ruolo verrà preso dal token,
 * che è più comodo e sbagliato.
 */
class AutenticaJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['errore' => 'Autenticazione richiesta.'], 401);
        }

        try {
            $claim = Jwt::verifica($token);
        } catch (\Throwable) {
            return response()->json(['errore' => 'Sessione scaduta o non valida.'], 401);
        }

        $utente = Utente::find($claim['sub'] ?? null);

        if ($utente === null || ! $utente->attivo) {
            return response()->json(['errore' => 'Utente non abilitato.'], 401);
        }

        $request->attributes->set('utente', $utente);
        $request->attributes->set('jti', $claim['jti'] ?? null);

        return $next($request);
    }
}
