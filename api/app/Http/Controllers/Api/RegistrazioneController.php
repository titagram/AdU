<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registrazione del cittadino.
 *
 * Il portale vero la fece nel 2021, quando lo sportello fisico non bastava più: la scheda
 * consegnata allo sportello resta la via normale, ma chi arriva da SPID poteva registrarsi da
 * solo. Qui è rifatta con le debolezze del resto del laboratorio:
 *
 * - la password è salvata in MD5, come quelle del seed (L08): coerente con il resto, e
 *   volutamente sbagliata;
 * - il codice fiscale è controllato solo nella forma, non nel carattere di controllo: un codice
 *   inventato ma ben formato viene accettato;
 * - i messaggi dicono se un codice fiscale o un'email esistono già, come il login (L22).
 *
 * Una scelta invece è deliberata e va nella direzione opposta al resto: il ruolo **non** arriva
 * dal corpo della richiesta. Se lo facesse, chiunque potrebbe registrarsi come funzionario e la
 * scala dei privilegi del corso (invariante I2) salterebbe dal primo gradino all'ultimo. Il
 * difetto di assegnazione di massa resta dove si insegna (L05), non qui.
 */
class RegistrazioneController extends Controller
{
    public function registra(Request $request)
    {
        $dati = $request->validate([
            'nome' => ['required', 'string', 'max:60'],
            'cognome' => ['required', 'string', 'max:60'],
            'codice_fiscale' => ['required', 'string', 'regex:/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/'],
            'email' => ['required', 'email', 'max:120'],
            'data_nascita' => ['required', 'date', 'before:today'],
            'sesso' => ['required', 'in:M,F'],
            'comune_nascita' => ['required', 'string', 'max:80'],
            'indirizzo' => ['required', 'string', 'max:120'],
            'cap' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'provincia' => ['required', 'string', 'size:2'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ], [
            'codice_fiscale.regex' => 'Il codice fiscale deve avere sedici caratteri nel formato previsto.',
            'cap.regex' => 'Il CAP deve essere di cinque cifre.',
            'provincia.size' => 'La provincia va indicata con la sigla di due lettere.',
        ]);

        $cf = strtoupper($dati['codice_fiscale']);

        // Enumeration, come sul login: le due risposte distinguono «esiste» da «non esiste».
        if (DB::table('utenti')->where('codice_fiscale', $cf)->exists()) {
            return response()->json(['errore' => 'Questo codice fiscale risulta già registrato.'], 409);
        }

        if (DB::table('utenti')->where('email', $dati['email'])->exists()) {
            return response()->json(['errore' => 'Questa email risulta già registrata.'], 409);
        }

        $adesso = now();
        $slug = strtolower(str_replace(' ', '.', $dati['nome'].'.'.$dati['cognome']));

        DB::table('contribuenti')->insert([
            'codice_fiscale' => $cf,
            'nome' => $dati['nome'],
            'cognome' => $dati['cognome'],
            'data_nascita' => $dati['data_nascita'],
            'sesso' => $dati['sesso'],
            'comune_nascita' => $dati['comune_nascita'],
            'indirizzo' => $dati['indirizzo'],
            'cap' => $dati['cap'],
            'provincia' => strtoupper($dati['provincia']),
            'pec' => $slug.'@pec.example.it',
            'telefono' => null,
            'created_at' => $adesso,
            'updated_at' => $adesso,
        ]);

        $id = DB::table('utenti')->insertGetId([
            'codice_fiscale' => $cf,
            'nome' => $dati['nome'],
            'cognome' => $dati['cognome'],
            'email' => $dati['email'],
            'password' => md5($dati['password']),
            'ruolo' => 'cittadino',
            'attivo' => true,
            'reset_disabilitato' => false,
            'created_at' => $adesso,
            'updated_at' => $adesso,
        ]);

        return response()->json([
            'utente' => ['id' => $id, 'codice_fiscale' => $cf, 'email' => $dati['email']],
            'messaggio' => 'Account creato. Ora puoi accedere con il codice fiscale e la password scelta.',
        ], 201);
    }
}
