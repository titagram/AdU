<?php

/**
 * Servizio bancario — verifica degli IBAN.
 *
 * Gira solo dentro `backend`. L'API del portale lo chiama con una chiave condivisa,
 * `X-Api-Key`, che in M4 finirà nel bundle della SPA: è il segreto esposto dell'invariante I4
 * (esposto sì, ma non è quello che firma i token).
 */

const CHIAVE_API = 'banca-chiave-servizio-2025';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== CHIAVE_API) {
    http_response_code(401);
    echo json_encode(['errore' => 'chiave non valida']);
    exit;
}

$percorso = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($percorso === '/health') {
    echo json_encode(['stato' => 'ok']);
    exit;
}

if ($percorso !== '/verifica') {
    http_response_code(404);
    echo json_encode(['errore' => 'risorsa non trovata']);
    exit;
}

$corpo = json_decode(file_get_contents('php://input') ?: '[]', true);
$iban = strtoupper(preg_replace('/\s+/', '', (string) ($corpo['iban'] ?? '')));

// Controllo sintattico minimo: non è un validatore completo, e non deve esserlo.
$valido = (bool) preg_match('/^IT\d{2}[A-Z]\d{10}[A-Z0-9]{12}$/', $iban);

echo json_encode([
    'iban' => $iban,
    'valido' => $valido,
    'banca' => $valido ? 'Istituto di credito cooperativo (fittizio)' : null,
    'verificato_il' => gmdate('c'),
]);
