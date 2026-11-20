<?php
/**
 * Sportello telematico dell'Agenzia delle Uscite — versione 3.2 (2019).
 *
 * È l'applicazione che il nuovo portale doveva sostituire e che invece è rimasta in esercizio
 * per gli uffici territoriali. Autenticazione a sessione PHP, password in MD5, query costruite
 * per concatenazione: tutto quello che nel 2019 sembrava normale.
 *
 * DIFETTI INTENZIONALI (capitoli 2–6), tutti documentati nella guida del docente:
 *  - nessun `session_regenerate_id()` dopo l'accesso (L22, sessione fissa);
 *  - `/sportello/esci` non chiude niente (L22);
 *  - `?ritorno=` finisce in un `Location:` senza controlli (L07);
 *  - query concatenate, con `MYSQL_ATTR_MULTI_STATEMENTS` attivo (L10, L11, L12, L14, L23).
 */

session_start();

const SPORTELLO = 'Sportello telematico — Agenzia delle Uscite';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // L'attributo che permette più istruzioni in una sola `query()` è la ragione per cui
        // il laboratorio sulle query impilate funziona su questa applicazione e non sull'API.
        $pdo = new PDO(
            'mysql:host=db;dbname=adu;charset=utf8mb4',
            'adu',
            'adu',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ],
        );
    }

    return $pdo;
}

function e(?string $testo): string
{
    return htmlspecialchars((string) $testo, ENT_QUOTES, 'UTF-8');
}

function vista(string $titolo, string $corpo): void
{
    $utente = $_SESSION['cf'] ?? null;
    $voce = $utente === null
        ? '<a href="/sportello/">Accesso</a>'
        : '<span>'.e($utente).'</span> · <a href="/sportello/esci">Esci</a>';

    echo <<<HTML
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>{$titolo} — Sportello telematico</title>
<style>
body{font:14px/1.5 Verdana,Geneva,sans-serif;margin:0;background:#eef1f5;color:#222}
header{background:#1d3f6e;color:#fff;padding:.8rem 1.4rem;display:flex;justify-content:space-between;align-items:center}
header a{color:#fff}
h1{font-size:1.15rem;margin:1.4rem 0 .8rem}
main{max-width:52rem;margin:0 auto;padding:0 1.4rem 3rem}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{text-align:left;padding:.45rem .6rem;border-bottom:1px solid #dde3ea;font-size:.9rem}
th{background:#e3e9f1}
form{background:#fff;padding:1.2rem;border:1px solid #d3dbe4;max-width:26rem}
label{display:block;margin:.7rem 0 .2rem;font-weight:bold;font-size:.85rem}
input{width:100%;padding:.45rem;box-sizing:border-box;border:1px solid #b9c3cf}
button{margin-top:1rem;padding:.5rem 1.1rem;background:#1d3f6e;color:#fff;border:0;cursor:pointer}
.errore{background:#fbe4e4;border:1px solid #e2b4b4;padding:.6rem .8rem;margin:1rem 0;max-width:26rem}
footer{margin-top:2rem;color:#666;font-size:.78rem;border-top:1px solid #d3dbe4;padding-top:.6rem}
</style>
</head>
<body>
<header><strong>Agenzia delle Uscite · Sportello telematico</strong><nav>{$voce}</nav></header>
<main>
<h1>{$titolo}</h1>
{$corpo}
<footer>Sportello telematico versione 3.2 — 2019. Sostituito dal nuovo portale, resta attivo per gli
uffici territoriali in attesa della migrazione dei fascicoli.</footer>
</main>
</body>
</html>
HTML;
}

function redirect(string $destinazione): void
{
    header('Location: '.$destinazione, true, 302);
    exit;
}

$percorso = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$percorso = '/'.trim(substr($percorso, strlen('/sportello')), '/');

// DIFETTO INTENZIONALE (L15, capitolo 4): il funzionario apre la nota del cittadino.
if (preg_match('#^/pratica/(\d+)$#', $percorso, $pezzi) === 1) {
    if (! isset($_SESSION['cf'])) {
        redirect('/sportello/');
    }

    if (! in_array($_SESSION['ruolo'] ?? '', ['funzionario', 'amministratore'], true)) {
        vista('Riservata al personale', '<p>Questa pagina è riservata agli uffici.</p>');
        exit;
    }

    $pratica = db()->query("SELECT * FROM pratiche WHERE id = ".(int) $pezzi[1])->fetch();

    if ($pratica === false) {
        vista('Pratica inesistente', '<p>Nessuna pratica con questo numero.</p>');
        exit;
    }

    $note = db()->query(
        "SELECT n.corpo, n.creata_il, u.email FROM note_pratiche n
         LEFT JOIN utenti u ON u.id = n.autore_id
         WHERE n.pratica_id = ".(int) $pezzi[1]." ORDER BY n.creata_il"
    )->fetchAll();

    $elenco = '';
    foreach ($note as $n) {
        // Nessun escape: la nota è del cittadino e il funzionario si fida del portale.
        $elenco .= '<div style="background:#fff;border:1px solid #d3dbe4;padding:.8rem;margin:.6rem 0">'
            .$n['corpo'].'<div style="color:#666;font-size:.78rem">'
            .e((string) $n['email']).' · '.e(substr((string) $n['creata_il'], 0, 16)).'</div></div>';
    }

    vista('Pratica '.$pratica['protocollo'], '
        <p><strong>Servizio:</strong> '.e($pratica['servizio']).' · <strong>Stato:</strong> '
        .e($pratica['stato']).'</p>
        <h2 style="font-size:1rem">Note del cittadino</h2>
        '.($elenco !== '' ? $elenco : '<p>Nessuna nota.</p>').'
    ');
    exit;
}

switch ($percorso) {
    case '/':
        if (isset($_SESSION['cf'])) {
            redirect('/sportello/pratiche');
        }

        $ritorno = $_GET['ritorno'] ?? '';
        $errore = $_GET['errore'] ?? '';

        vista('Accesso allo sportello', '
            '.($errore !== '' ? '<p class="errore">'.e($errore).'</p>' : '').'
            <form method="post" action="/sportello/entra'.($ritorno !== '' ? '?ritorno='.urlencode($ritorno) : '').'">
              <label for="cf">Codice fiscale</label>
              <input id="cf" name="codice_fiscale" maxlength="16" autocomplete="off">
              <label for="pw">Password</label>
              <input id="pw" name="password" type="password">
              <button type="submit">Entra</button>
            </form>
            <p>Per il rilascio delle credenziali rivolgersi al proprio ufficio territoriale.</p>
        ');
        break;

    case '/entra':
        $cf = strtoupper((string) ($_POST['codice_fiscale'] ?? ''));
        $password = md5((string) ($_POST['password'] ?? ''));

        // Query costruita per concatenazione: la password è un MD5, quindi il confronto sta
        // nella query. È il difetto L10 del capitolo 2, e la ragione per cui esiste L08.
        $sql = "SELECT * FROM utenti WHERE codice_fiscale = '{$cf}' AND password = '{$password}' LIMIT 1";
        $utente = db()->query($sql)->fetch();

        if ($utente === false) {
            redirect('/sportello/?errore='.urlencode('Credenziali non valide.'));
        }

        // Nessun `session_regenerate_id()`: l'identificativo di sessione resta quello che il
        // browser aveva prima dell'accesso. Chi lo conosceva continua a conoscerlo.
        $_SESSION['cf'] = $utente['codice_fiscale'];
        $_SESSION['ruolo'] = $utente['ruolo'];
        $_SESSION['entrato_il'] = date('Y-m-d H:i:s');

        // `?ritorno=` arriva dal form, quindi dal browser, quindi da chiunque.
        if (! empty($_REQUEST['ritorno'])) {
            redirect((string) $_REQUEST['ritorno']);
        }

        redirect('/sportello/pratiche');
        break;

    case '/pratiche':
        if (! isset($_SESSION['cf'])) {
            redirect('/sportello/');
        }

        // DIFETTO INTENZIONALE (L11, capitolo 5): codice fiscale e ordinamento arrivano dalla
        // richiesta e finiscono nella query per concatenazione. Le colonne sono sei, e questo
        // è il numero che serve per l'UNION.
        $cf = (string) ($_GET['cf'] ?? $_SESSION['cf']);
        $sort = (string) ($_GET['sort'] ?? 'p.aperta_il DESC');

        // Una riga sola, come la scriveva chi ha fatto lo sportello nel 2019: è anche la ragione
        // per cui il commento `#` funziona e il payload del laboratorio è quello classico.
        $sql = "SELECT p.protocollo, p.servizio, p.stato, p.aperta_il, p.codice_fiscale, p.id FROM pratiche p WHERE p.codice_fiscale = '{$cf}' ORDER BY {$sort} LIMIT 20";

        try {
            $righe = db()->query($sql)->fetchAll();
        } catch (Throwable $e) {
            // DIFETTO INTENZIONALE (L11, variante error-based): il messaggio del database
            // arriva al browser senza essere filtrato.
            vista('Errore nella consultazione', '<p>Si è verificato un errore.</p><pre>'
                .e($e->getMessage()).'</pre>');
            exit;
        }

        $tabella = '';
        foreach ($righe as $r) {
            $tabella .= '<tr><td>'.e($r['protocollo']).'</td><td>'.e($r['servizio']).'</td><td>'
                .e($r['stato']).'</td><td>'.e(substr((string) $r['aperta_il'], 0, 10)).'</td><td>'
                .e($r['codice_fiscale']).'</td></tr>';
        }

        vista('Le mie pratiche', '
            <table><thead><tr><th>Protocollo</th><th>Servizio</th><th>Stato</th><th>Aperta il</th>'
            .'<th>Contribuente</th></tr></thead>
            <tbody>'.($tabella !== '' ? $tabella : '<tr><td colspan="5">Nessuna pratica.</td></tr>').'</tbody></table>
            <p>Per il dettaglio di una pratica rivolgersi all\'ufficio competente.</p>
        ');
        break;

    case '/verifica':
        // DIFETTO INTENZIONALE (L12, capitolo 5): verifica pubblica dello stato di una pratica,
        // con il numero di protocollo concatenato. Nessuna autenticazione, nessun limite.
        $protocollo = (string) ($_GET['protocollo'] ?? '');
        $esito = null;

        if ($protocollo !== '') {
            try {
                $esito = db()->query(
                    "SELECT protocollo, stato FROM pratiche WHERE protocollo = '{$protocollo}' LIMIT 1"
                )->fetch();
            } catch (Throwable $e) {
                vista("Verifica dello stato", '<p>Errore nella verifica.</p><pre>'.e($e->getMessage()).'</pre>');
                exit;
            }
        }

        $risposta = '';
        if ($protocollo !== '') {
            $risposta = $esito === false
                ? '<p class="errore">Nessuna pratica con questo numero.</p>'
                : '<p>La pratica <strong>'.e((string) $esito['protocollo']).'</strong> risulta '
                    .e((string) $esito['stato']).'.</p>';
        }

        vista('Verifica dello stato', '
            <p>Inserisca il numero di protocollo che le è stato rilasciato.</p>
            <form method="get" action="/sportello/verifica">
              <label for="protocollo">Numero di protocollo</label>
              <input id="protocollo" name="protocollo" placeholder="2025/004102">
              <button type="submit">Verifica</button>
            </form>
            '.$risposta.'
        ');
        break;

    case '/report':
        if (! isset($_SESSION['cf'])) {
            redirect('/sportello/');
        }

        if (! in_array($_SESSION['ruolo'] ?? '', ['funzionario', 'amministratore'], true)) {
            vista('Riservata al personale', '<p>Questa pagina è riservata agli uffici.</p>');
            exit;
        }

        $dal = (string) ($_GET['dal'] ?? '');
        $al = (string) ($_GET['al'] ?? '');
        $uscita = null;

        if ($dal !== '' && $al !== '') {
            // DIFETTO INTENZIONALE (L13, capitolo 5): le date finiscono in un comando di shell.
            // Lo script di stampa è vecchio e prende gli argomenti dalla riga di comando.
            $comando = 'php /var/www/html/public/sportello/report.php --dal='.$dal.' --al='.$al.' 2>&1';
            $uscita = shell_exec($comando);
        }

        vista('Report delle pratiche', '
            <form method="get" action="/sportello/report">
              <label for="dal">Dal</label><input id="dal" name="dal" value="2025-01-01">
              <label for="al">Al</label><input id="al" name="al" value="2025-12-31">
              <button type="submit">Genera</button>
            </form>
            '.($uscita !== null ? '<pre>'.e((string) $uscita).'</pre>' : '').'
        ');
        break;

    case '/esci':
        // DIFETTO INTENZIONALE (L22): l'uscita è solo una pagina di cortesia. Il cookie di
        // sessione e i dati sul server restano validi: chi ha una copia del cookie rientra.
        vista('Uscita effettuata', '
            <p>La sessione è stata chiusa. Grazie per aver usato lo sportello telematico.</p>
            <p><a href="/sportello/">Torna all\'accesso</a></p>
        ');
        break;

    default:
        vista('Pagina non trovata', '<p>La pagina richiesta non esiste.</p>');
}
