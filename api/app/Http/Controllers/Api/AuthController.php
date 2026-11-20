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
     * M1: una sola risposta per credenziali sbagliate (niente enumerazione), hash verificato
     * con `Hash::check`, nessuna query costruita a mano. In M2 questo metodo diventerà il
     * laboratorio L10/L22: due messaggi distinti e concatenazione di stringhe.
     */
    public function login(Request $request): JsonResponse
    {
        $dati = $request->validate([
            'codice_fiscale' => ['required', 'string', 'max:16'],
            'password' => ['required', 'string'],
        ]);

        $utente = Utente::where('codice_fiscale', strtoupper($dati['codice_fiscale']))->first();

        if ($utente === null || ! Hash::check($dati['password'], $utente->password)) {
            return response()->json(['errore' => 'Credenziali non valide.'], 401);
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
