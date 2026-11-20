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
    /**
     * DIFETTO INTENZIONALE (L18, capitolo 6): controllo e scrittura non sono atomici.
     *
     * Due richieste simultanee leggono lo stesso stato `approvato`, passano entrambe il
     * controllo e creano due richieste di pagamento. Nessuna transazione, nessun lock, nessun
     * vincolo di unicità: la corsa la vince chi arriva secondo.
     */
    public function richiedi(Request $request, int $id)
    {
        $utente = $request->attributes->get('utente');

        $rimborso = DB::table('rimborsi')->where('id', $id)
            ->where('codice_fiscale', $utente->codice_fiscale)->first();

        if ($rimborso === null) {
            return response()->json(['errore' => 'Rimborso non trovato.'], 404);
        }

        if ($rimborso->stato !== 'approvato') {
            return response()->json(['errore' => 'Il rimborso non è in uno stato richiedibile.'], 409);
        }

        // Pausa didattica: è la finestra in cui due richieste possono leggere lo stesso stato.
        usleep(200000);

        DB::table('richieste_pagamento')->insert([
            'rimborso_id' => $id,
            'importo' => $rimborso->importo,
            'iban' => $rimborso->iban,
            'creata_il' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rimborsi')->where('id', $id)->update(['stato' => 'richiesto', 'updated_at' => now()]);

        return response()->json(['stato' => 'richiesto', 'richiesta' => DB::table('richieste_pagamento')->where('rimborso_id', $id)->count()]);
    }

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
