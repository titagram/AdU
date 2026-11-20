<?php

/**
 * Anagrafe tributaria — servizio interno.
 *
 * Non ha porte pubblicate: esiste solo dentro `backend` e la raggiunge il portale per
 * arricchire le pratiche. Espone l'archivio vero, più di quanto il portale mostri.
 *
 * Credenziali predefinite `admin`/`admin` (invariante I2: valgono qui, non sul portale).
 * Il laboratorio L20 insegna che "interno" non significa "protetto".
 */

// Apache non passa sempre l'header Authorization a PHP: le credenziali di base vanno lette
// anche da PHP_AUTH_USER, che è la via canonica e l'unica che sopravvive al passaggio.
$utente = null;
$password = null;

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $utente = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'] ?? '';
} else {
    $intestazione = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/^Basic\s+(.+)$/i', $intestazione, $m)) {
        [$utente, $password] = array_pad(explode(':', base64_decode($m[1]), 2), 2, '');
    }
}

if ($utente !== 'admin' || $password !== 'admin') {
    header('WWW-Authenticate: Basic realm="Anagrafe tributaria"');
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['errore' => 'autenticazione richiesta']);
    exit;
}

$percorso = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    $pdo = new PDO(
        'mysql:host=db;port=3306;dbname=adu;charset=utf8mb4',
        'adu',
        'adu',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['errore' => 'archivio non disponibile']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($percorso === '/' || $percorso === '/admin') {
    echo json_encode([
        'servizio' => 'Anagrafe tributaria',
        'versione' => '3.2.1',
        'endpoint' => ['/cittadini', '/cittadini/{codice_fiscale}', '/redditi', '/utenze'],
        'nota' => 'accesso riservato agli uffici',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (preg_match('#^/cittadini/([A-Z0-9]{16})$#', $percorso, $m)) {
    $st = $pdo->prepare('SELECT * FROM contribuenti WHERE codice_fiscale = ?');
    $st->execute([$m[1]]);
    $riga = $st->fetch();

    if ($riga === false) {
        http_response_code(404);
        echo json_encode(['errore' => 'non presente in archivio']);
        exit;
    }

    echo json_encode($riga, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($percorso === '/cittadini') {
    $righe = $pdo->query('SELECT * FROM contribuenti ORDER BY cognome, nome')->fetchAll();
    echo json_encode(['totale' => count($righe), 'cittadini' => $righe], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($percorso === '/redditi') {
    $righe = $pdo->query('SELECT codice_fiscale, anno, reddito, imposta FROM dichiarazioni ORDER BY anno DESC')->fetchAll();
    echo json_encode(['totale' => count($righe), 'dichiarazioni' => $righe], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($percorso === '/utenze') {
    $righe = $pdo->query('SELECT codice_fiscale, nome, cognome, email, ruolo FROM utenti')->fetchAll();
    echo json_encode(['totale' => count($righe), 'utenze' => $righe], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['errore' => 'risorsa non trovata']);
