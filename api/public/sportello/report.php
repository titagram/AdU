<?php
/**
 * Generatore del report delle pratiche — script del 2019, invocato dallo sportello con
 * `shell_exec()`. Prende le date dalla riga di comando e interroga il database.
 */

$opzioni = getopt('', ['dal:', 'al:']);

$dal = $opzioni['dal'] ?? '1970-01-01';
$al = $opzioni['al'] ?? '2100-12-31';

$pdo = new PDO('mysql:host=db;dbname=adu;charset=utf8mb4', 'adu', 'adu', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$sql = "SELECT COUNT(*) AS totale FROM pratiche WHERE aperta_il BETWEEN '{$dal}' AND '{$al}'";

try {
    $riga = $pdo->query($sql)->fetch();
    printf("Report dal %s al %s\n", $dal, $al);
    printf("Pratiche: %d\n", $riga['totale']);
} catch (Throwable $e) {
    printf("Errore nella generazione del report: %s\n", $e->getMessage());
}
