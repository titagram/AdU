<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * DIFETTO INTENZIONALE (L14, capitolo 4): XSS riflessa sul modulo dei contatti.
 *
 * La conferma è una pagina HTML costruita concatenando quello che ha scritto il visitatore.
 * Non c'è escape, e non serve: la pagina si può chiedere anche con `GET`, quindi il payload
 * viaggia in un collegamento che si può mandare a chiunque.
 */
class ContattiController extends Controller
{
    private const STILE = 'body{font:15px/1.55 system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem}'
        .'h1{font-size:1.2rem}.riquadro{background:#eef3f7;border:1px solid #cfdae3;padding:1rem;margin:1rem 0}';

    public function mostra(Request $request)
    {
        $nome = (string) $request->input('nome', '');
        $messaggio = (string) $request->input('messaggio', '');

        if ($messaggio === '') {
            return response($this->modulo())->header('Content-Type', 'text/html; charset=utf-8');
        }

        // Nessun `htmlspecialchars`: quello che ha scritto il visitatore torna al browser così
        // com'è, e il browser lo esegue.
        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8">'
            .'<title>Segnalazione registrata</title><style>'.self::STILE.'</style></head><body>'
            .'<h1>Segnalazione registrata</h1>'
            .'<div class="riquadro"><strong>'.($nome !== '' ? $nome : 'Contribuente').'</strong><br>'
            .$messaggio.'</div>'
            .'<p>Le risponderemo all\'indirizzo indicato entro cinque giorni lavorativi.</p>'
            .'</body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function modulo(): string
    {
        return '<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Contatti</title>'
            .'<style>'.self::STILE.'</style></head><body><h1>Scrivi all\'agenzia</h1>'
            .'<form method="get" action="/api/contatti">'
            .'<p><input name="nome" placeholder="Nome e cognome"></p>'
            .'<p><textarea name="messaggio" rows="6" cols="50" placeholder="La sua segnalazione"></textarea></p>'
            .'<p><button type="submit">Invia</button></p></form></body></html>';
    }
}
