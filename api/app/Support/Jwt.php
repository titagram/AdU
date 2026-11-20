<?php

namespace App\Support;

use App\Models\Sessione;
use App\Models\Utente;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

/**
 * Emissione e verifica dei token.
 *
 * DIFETTO INTENZIONALE (L09, capitolo 2): il token non scade e non è revocabile.
 *
 * Il payload non porta `exp`, e `verifica()` non guarda più la riga in `sessioni`. La tabella
 * continua a riempirsi a ogni accesso — e nessuno la legge: è il tipo di codice che resta in
 * produzione perché «tanto il logout lo fa il client, che cancella il token dal browser».
 * Chi ha una copia del token entra finché il segreto di firma non cambia.
 */
class Jwt
{
    public static function emetti(Utente $utente): array
    {
        $jti = (string) Str::uuid();
        $scadenza = now()->addMinutes(config('adu.jwt_ttl'));

        Sessione::create([
            'utente_id' => $utente->id,
            'jti' => $jti,
            'emesso_il' => now(),
            'scadenza' => $scadenza,
        ]);

        $token = FirebaseJwt::encode([
            'iss' => config('adu.jwt_issuer'),
            'sub' => $utente->id,
            'jti' => $jti,
            'ruolo' => $utente->ruolo,
            'iat' => now()->timestamp,
        ], config('adu.jwt_secret'), 'HS256');

        return [$token, $scadenza];
    }

    /**
     * @throws \Firebase\JWT\ExpiredException|\UnexpectedValueException
     */
    public static function verifica(string $token): array
    {
        return (array) FirebaseJwt::decode($token, new Key(config('adu.jwt_secret'), 'HS256'));
    }

    public static function revoca(string $jti): void
    {
        Sessione::where('jti', $jti)->update(['revocato' => true]);
    }
}
