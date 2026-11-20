<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * DIFETTO INTENZIONALE (L25, capitolo 6): SSRF.
 *
 * Il portale scarica un allegato da un indirizzo indicato dal client. Non c'è lista di domini
 * ammessi e non c'è nessun controllo sullo schema: dall'API si raggiungono i servizi che vivono
 * nella rete interna, quelli che da fuori non esistono.
 *
 * La catena del capitolo: l'indirizzo di Mailpit restituisce tutte le email della giornata,
 * compresi i collegamenti di recupero password (L17); il servizio bancario risponde solo con la
 * chiave — che è nel bundle della SPA (L09).
 */
class AllegatiController extends Controller
{
    public function daUrl(Request $request)
    {
        $dati = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        $contesto = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
        ]);

        $corpo = @file_get_contents($dati['url'], false, $contesto);

        if ($corpo === false) {
            return response()->json(['errore' => 'Indirizzo non raggiungibile.'], 422);
        }

        return response()->json([
            'url' => $dati['url'],
            'lunghezza' => strlen($corpo),
            'contenuto' => substr($corpo, 0, 2000),
        ]);
    }
}
