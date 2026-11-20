<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utente;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Accesso.
     *
     * DIFETTO INTENZIONALE (L22, capitolo 2): il metodo distingue due casi che l'utente non
     * dovrebbe poter distinguere — «codice fiscale non registrato» e «password errata». Chi
     * prova un codice fiscale alla volta scopre quali esistono nell'archivio, e da lì passa
     * alle password del dizionario (L08). La correzione è una sola risposta per ogni
     * credenziale sbagliata, indipendente dal ramo che l'ha generata.
     *
     * Il resto dell'hash è ancora corretto: `Hash::check`, nessuna query costruita a mano.
     * La concatenazione arriva con L10, nel capitolo 2.
     */
    public function login(Request $request): JsonResponse
    {
        $dati = $request->validate([
            'codice_fiscale' => ['required', 'string', 'max:16'],
            'password' => ['required', 'string'],
        ]);

        $utente = Utente::where('codice_fiscale', strtoupper($dati['codice_fiscale']))->first();

        if ($utente === null) {
            return response()->json(['errore' => 'Codice fiscale non registrato.'], 401);
        }

        if (! Hash::check($dati['password'], $utente->password)) {
            return response()->json(['errore' => 'Password errata.'], 401);
        }

        if (! $utente->attivo) {
            return response()->json(['errore' => 'Utente non abilitato.'], 403);
        }

        [$token, $scadenza] = Jwt::emetti($utente);

        $utente->forceFill(['ultimo_accesso' => now()])->save();

        return response()->json([
            'token' => $token,
            'scadenza' => $scadenza->toIso8601String(),
            'utente' => $this->profilo($utente),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $jti = $request->attributes->get('jti');

        if (is_string($jti)) {
            Jwt::revoca($jti);
        }

        return response()->json(['esito' => 'uscita effettuata']);
    }

    public function profilo(Utente $utente): array
    {
        return [
            'id' => $utente->id,
            'codice_fiscale' => $utente->codice_fiscale,
            'nome' => $utente->nome,
            'cognome' => $utente->cognome,
            'email' => $utente->email,
            'ruolo' => $utente->ruolo,
        ];
    }
}
