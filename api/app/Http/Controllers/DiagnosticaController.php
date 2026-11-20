<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DIFETTO INTENZIONALE (L19): console di diagnostica e pagina delle dipendenze, entrambe
 * pubbliche. Servono al capitolo 1: da qui gli studenti leggono le query del portale (con i
 * codici fiscali degli altri utenti), eseguono SQL a mano libera e scoprono quali librerie
 * il portale dichiara di usare.
 */
class DiagnosticaController extends Controller
{
    private const STILE = <<<'CSS'
    body{font:15px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;max-width:74rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a}
    h1{font:600 1.3rem system-ui,sans-serif} h2{font:600 1rem system-ui,sans-serif;margin-top:2rem}
    pre,table{background:#f4f6f8;border:1px solid #d5dbe1;padding:.8rem;overflow-x:auto;font-size:.82rem}
    td,th{padding:.25rem .6rem;border-bottom:1px solid #e2e7ec;text-align:left;vertical-align:top}
    code{background:#eef1f4;padding:.1rem .3rem}
    form{margin:1rem 0} input{font:inherit;width:100%;padding:.5rem;box-sizing:border-box}
    .nota{color:#666;font-size:.85rem}
    CSS;

    public function console(Request $request)
    {
        $sql = (string) $request->query('sql', '');
        $esito = null;

        if ($sql !== '') {
            // Nessuna gestione dell'errore: se la query è sbagliata si vede la pagina di
            // debug del framework, che è precisamente ciò che il portale non dovrebbe mostrare.
            $esito = DB::select($sql);
        }

        $file = storage_path('app/debug/query.log');
        $righe = is_file($file) ? array_slice(file($file, FILE_IGNORE_NEW_LINES), -40) : [];

        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8">'
            .'<title>Console di diagnostica</title><style>'.self::STILE.'</style></head><body>'
            .'<h1>Console di diagnostica</h1>'
            .'<p class="nota">Ufficio Sistemi Informativi · ambiente di esercizio. Strumento temporaneo, '
            .'da rimuovere prima del rilascio.</p>'
            .'<form method="get"><input name="sql" value="'.htmlspecialchars($sql).'" '
            .'placeholder="SELECT * FROM utenti LIMIT 5"></form>';

        if ($sql !== '') {
            $html .= '<h2>Risultato</h2><pre>'.htmlspecialchars(
                is_string($esito) ? $esito : json_encode($esito, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ).'</pre>';
        }

        $html .= '<h2>Ultime query eseguite</h2><pre>'.htmlspecialchars(implode("\n", $righe)).'</pre></body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * DIFETTO INTENZIONALE (L24): il registro degli accessi è consultabile da chiunque, senza
     * autenticazione, e lo `User-Agent` viene rimandato al browser così com'è. Chi vuole
     * sporcare le prove scrive una riga falsa nel registro con una richiesta qualsiasi.
     */
    public function registro()
    {
        $righe = \App\Models\LogAccesso::orderByDesc('id')->limit(100)->get();

        $tabella = '';
        foreach ($righe as $r) {
            $tabella .= '<tr><td>'.$r->avvenuto_il->format('d/m/Y H:i:s').'</td><td>'
                .($r->codice_fiscale ?? '—').'</td><td>'.$r->ip.'</td><td>'.$r->metodo.'</td><td>'
                .$r->path.'</td><td>'.$r->status.'</td><td>'.$r->user_agent.'</td></tr>';
        }

        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8">'
            .'<title>Registro degli accessi</title><style>'.self::STILE.'</style></head><body>'
            .'<h1>Registro degli accessi</h1>'
            .'<p class="nota">Ultime 100 richieste. Le righe con esito 401 non compaiono: i tentativi '
            .'falliti non vengono registrati.</p>'
            .'<table><thead><tr><th>Quando</th><th>Contribuente</th><th>IP</th><th>Metodo</th>'
            .'<th>Percorso</th><th>Esito</th><th>Client</th></tr></thead><tbody>'.$tabella
            .'</tbody></table></body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function dipendenze()
    {
        $pacchetti = [
            ['laravel/framework', '13.43.0', 'ok', '—'],
            ['firebase/php-jwt', '7.2.0', 'ok', '—'],
            ['php', '8.4', 'ok', '—'],
            ['phpunit/phpunit', '12.5', 'ok', 'solo sviluppo'],
            ['phpoffice/phpspreadsheet', '1.18.0', 'obsoleto', 'CVE-2024-24822 — espansione di formule'],
            ['guzzlehttp/guzzle', '7.4.0', 'obsoleto', 'CVE-2022-31042 — header Cookie in redirect'],
            ['symfony/process', '5.4.0', 'obsoleto', 'CVE-2022-24828 — injection in argomenti'],
            ['twig/twig', '3.3.0', 'obsoleto', 'CVE-2022-39261 — path traversal in template'],
            ['monolog/monolog', '2.3.5', 'obsoleto', 'CVE-2022-24960 — deserializzazione nel parser'],
            ['dompdf/dompdf', '1.2.0', 'obsoleto', 'CVE-2023-24831 — lettura di file locali'],
        ];

        $righe = '';
        foreach ($pacchetti as [$nome, $versione, $stato, $nota]) {
            $righe .= '<tr><td>'.htmlspecialchars($nome).'</td><td>'.htmlspecialchars($versione).'</td><td>'
                .htmlspecialchars($stato).'</td><td>'.htmlspecialchars($nota).'</td></tr>';
        }

        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8">'
            .'<title>Dipendenze del portale</title><style>'.self::STILE.'</style></head><body>'
            .'<h1>Dipendenze del portale</h1>'
            .'<p class="nota">Elenco generato dal servizio di manutenzione. Le versioni in uso sono '
            .'quelle di questo ambiente dimostrativo: il documento serve a discutere la politica di '
            .'aggiornamento, non a installare pacchetti vulnerabili.</p>'
            .'<table><thead><tr><th>Pacchetto</th><th>Versione</th><th>Stato</th><th>Note</th></tr></thead>'
            .'<tbody>'.$righe.'</tbody></table></body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
