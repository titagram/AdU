<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utente;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        $codice = strtoupper($dati['codice_fiscale']);

        // DIFETTO INTENZIONALE (L10, capitolo 2): l'identità dell'utente è decisa da una query
        // costruita per concatenazione. La password entra come MD5 (L08), quindi il confronto
        // vive nella clausola `WHERE`: `' OR 1=1#` basta a farsi restituire la prima riga.
        $riga = DB::selectOne(
            "SELECT * FROM utenti WHERE codice_fiscale = '{$codice}' AND password = '".md5($dati['password'])."' LIMIT 1"
        );

        $utente = $riga === null ? null : Utente::hydrate([(array) $riga])->first();

        if ($utente === null) {
            // DIFETTO INTENZIONALE (L22): una seconda query dice se il codice fiscale esiste,
            // e i due messaggi lo rivelano anche quando la query principale non c'entra.
            $esiste = DB::selectOne("SELECT id FROM utenti WHERE codice_fiscale = '{$codice}'");

            return response()->json(
                ['errore' => $esiste !== null ? 'Password errata.' : 'Codice fiscale non registrato.'],
                401,
            );
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

    /**
     * DIFETTO INTENZIONALE (L17, capitolo 2): il collegamento di recupero è prevedibile.
     *
     * Il token è il codice fiscale più la data del giorno: chi conosce il codice fiscale di
     * una persona — e in questo portale lo conoscono tutti, vedi L22 — può costruirsi il
     * collegamento di quella persona per oggi, senza ricevere nessuna email.
     */
    public function recupera(Request $request)
    {
        $dati = $request->validate([
            'codice_fiscale' => ['required', 'string', 'max:16'],
        ]);

        $cf = strtoupper($dati['codice_fiscale']);
        $utente = Utente::where('codice_fiscale', $cf)->first();

        if ($utente !== null && $utente->reset_disabilitato) {
            // Invariante I5: sull'account amministrativo il recupero è rifiutato, e va mostrato.
            return response()->json([
                'errore' => 'Il recupero non è disponibile per questo profilo. Si rivolga all\'ufficio.',
            ], 403);
        }

        if ($utente !== null) {
            $token = $cf.date('Ymd');
            $tokenPortale = bin2hex(random_bytes(32));

            DB::table('recuperi_password')->insert([
                'codice_fiscale' => $cf,
                'token' => $token,
                'token_portale_hash' => hash('sha256', $tokenPortale),
                'scadenza' => now()->addDay(),
                'usato' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $collegamento = rtrim((string) config('adu.portale_url'), '/')
                .'/#/reimposta?codice='.$tokenPortale;

            Mail::raw(
                "Gentile contribuente,\n\n"
                ."per reimpostare la password usi questo collegamento:\n".$collegamento."\n\n"
                ."Il collegamento resta valido fino a domani.\n\n"
                ."Agenzia delle Uscite — Sportello telematico",
                fn ($messaggio) => $messaggio
                    ->to($utente->email)
                    ->subject('Recupero della password — Agenzia delle Uscite'),
            );
        }

        // Messaggio uguale in ogni caso: qui l'esistenza dell'utente non si rivela.
        return response()->json([
            'messaggio' => 'Se il codice fiscale è registrato, riceverà un messaggio con le istruzioni.',
        ]);
    }

    public function reimpostaDalPortale(Request $request): JsonResponse
    {
        $dati = $request->validate([
            'codice' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $aggiornata = DB::transaction(function () use ($dati) {
            $recupero = DB::table('recuperi_password')
                ->where('token_portale_hash', hash('sha256', $dati['codice']))
                ->where('usato', false)
                ->where('scadenza', '>', now())
                ->lockForUpdate()
                ->first();

            if ($recupero === null) {
                return false;
            }

            $utente = Utente::where('codice_fiscale', $recupero->codice_fiscale)
                ->where('attivo', true)
                ->where('reset_disabilitato', false)
                ->first();

            if ($utente === null) {
                return false;
            }

            // Il formato di memorizzazione deve essere compatibile con il login del laboratorio.
            $utente->forceFill(['password' => md5($dati['password'])])->save();
            DB::table('recuperi_password')->where('id', $recupero->id)
                ->update(['usato' => true, 'updated_at' => now()]);

            return true;
        });

        if (! $aggiornata) {
            return response()->json(['errore' => 'Collegamento non valido o scaduto.'], 401);
        }

        return response()->json(['messaggio' => 'Password aggiornata. Può accedere con le nuove credenziali.']);
    }

    public function reimposta(Request $request)
    {
        $dati = $request->validate([
            'codice_fiscale' => ['required', 'string', 'max:16'],
            'token' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:6', 'max:72'],
        ]);

        $cf = strtoupper($dati['codice_fiscale']);

        $utente = Utente::where('codice_fiscale', $cf)->first();

        if ($utente === null || $utente->reset_disabilitato) {
            return response()->json(['errore' => 'Collegamento non valido o scaduto.'], 401);
        }

        // DIFETTO INTENZIONALE (L17): il token non si cerca, si ricalcola. Chi indovina la
        // regola — codice fiscale più data — non ha bisogno di aver mai chiesto il recupero:
        // il collegamento di chiunque è valido oggi, senza email e senza traccia.
        $atteso = $cf.date('Ymd');

        if (! hash_equals($atteso, $dati['token'])) {
            return response()->json(['errore' => 'Collegamento non valido o scaduto.'], 401);
        }

        $utente->forceFill(['password' => md5($dati['password'])])->save();

        DB::table('recuperi_password')
            ->where('codice_fiscale', $cf)
            ->update(['usato' => true, 'updated_at' => now()]);

        return response()->json(['messaggio' => 'Password aggiornata. Può accedere con le nuove credenziali.']);
    }
}
