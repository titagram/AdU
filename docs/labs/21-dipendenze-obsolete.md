# L21 — Dipendenze obsolete

**Capitolo:** 1 · **OWASP:** A06 Vulnerable and Outdated Components · **Servizio:** `api`

## Obiettivo

Scoprire quali librerie il portale dichiara di usare e con quale politica di aggiornamento.

## Dove guardare

`GET http://<host>:8081/admin/dipendenze`. La pagina non è protetta.

## Cosa si ottiene

Una tabella con pacchetto, versione, stato e nota. Cinque voci su dieci risultano obsolete, con
il riferimento al CVE e una descrizione di una riga. Non si ottiene l'esecuzione di codice:
nessuna di quelle librerie è davvero installata con quelle versioni.

È un laboratorio di **processo**, non di exploit. Serve a discutere chi aggiorna, chi verifica e
chi decide che una vulnerabilità nota può aspettare il prossimo trimestre. In un corso per
programmatori senior è la lezione più facile da snobbare e la più utile.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/routes/web.php:19` | la pagina è raggiungibile senza autenticazione |
| `api/app/Http/Controllers/DiagnosticaController.php:87` | l'elenco è una costante nel controller: documentazione, non inventario generato |

Il fatto che l'elenco sia scritto a mano è parte della lezione: un inventario che nessuno
rigenera è già vecchio il giorno dopo averlo pubblicato.

## Trappole

- Chi cerca un exploit perde tempo: qui non c'è niente da sfruttare.
- Il confronto va fatto con `composer.lock` esposto da L19, che invece contiene le versioni
  **vere** installate. Le due liste non coincidono, e questo è un finding a sé: la
  documentazione dice una cosa, il sistema ne esegue un'altra.

## Collegamento

Si chiude solo davanti a `composer.lock` (L19). È anche il pretesto con cui il capitolo 5
giustifica l'inclusione di un motore di conversione PDF (L13): una libreria vecchia che nessuno
vuole aggiornare.
