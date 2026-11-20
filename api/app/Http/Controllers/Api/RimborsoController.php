<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cambio dell'IBAN di un rimborso.
 *
 * DIFETTO INTENZIONALE (L17, capitolo 2): il codice di conferma è di quattro cifre e non
 * esiste alcun limite ai tentativi. Diecimila richieste sono un minuto di lavoro, e il
 * portale non si accorge di nulla — non perché il controllo sia fatto male, ma perché
 * nessuno ha mai contato i tentativi.
 */
class RimborsoController extends Controller
{
    public function cambiaIban(Request $request, int $id)
    {
        $dati = $request->validate([
            'iban' => ['required', 'string', 'max:34'],
            'otp' => ['required', 'string', 'size:4'],
        ]);

        $utente = $request->attributes->get('utente');

        $rimborso = DB::table('rimborsi')
            ->where('id', $id)
            ->where('codice_fiscale', $utente->codice_fiscale)
            ->first();

        if ($rimborso === null) {
            return response()->json(['errore' => 'Rimborso non trovato.'], 404);
        }

        if ((string) $rimborso->otp !== $dati['otp']) {
            // Nessun ritardo, nessun blocco, nessun contatore: si può riprovare subito.
            return response()->json(['errore' => 'Codice di conferma non valido.'], 401);
        }

        $iban = strtoupper(preg_replace('/\s+/', '', $dati['iban']));

        DB::table('rimborsi')->where('id', $id)->update([
            'iban' => $iban,
            'updated_at' => now(),
        ]);

        return response()->json(['iban' => $iban, 'stato' => 'aggiornato']);
    }
}
