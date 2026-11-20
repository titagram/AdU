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
 * Ogni token porta un `jti` registrato in `sessioni`: senza quella riga il token non vale
 * nulla, ed è così che il logout riesce davvero a revocarlo. In M2 questa verifica sparirà.
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
            'exp' => $scadenza->timestamp,
        ], config('adu.jwt_secret'), 'HS256');

        return [$token, $scadenza];
    }

    /**
     * @throws \Firebase\JWT\ExpiredException|\UnexpectedValueException
     */
    public static function verifica(string $token): array
    {
        $claim = (array) FirebaseJwt::decode($token, new Key(config('adu.jwt_secret'), 'HS256'));

        $sessione = Sessione::where('jti', $claim['jti'] ?? '')->first();

        if ($sessione === null || $sessione->revocato) {
            throw new \UnexpectedValueException('Sessione non valida o revocata.');
        }

        return $claim;
    }

    public static function revoca(string $jti): void
    {
        Sessione::where('jti', $jti)->update(['revocato' => true]);
    }
}
