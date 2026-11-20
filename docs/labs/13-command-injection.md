# L13 — Iniezione di comandi

**Capitolo:** 5 · **OWASP:** A03 Injection · **Servizio:** `/sportello/`

## Obiettivo

Eseguire comandi sul server che ospita il portale.

## Dove guardare

`GET /sportello/report?dal=...&al=...` con la sessione di un funzionario.

## Cosa si ottiene

`al=2025-12-31; id` restituisce `uid=33(www-data)` dentro la pagina del report. Da lì si legge il
`.env` (L04 lo aveva già mostrato), si scrive un file nella DocumentRoot e si esegue codice.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/public/sportello/index.php:283` | `shell_exec()` con le date concatenate al comando |
| `api/public/sportello/report.php` | lo script di stampa, che prende gli argomenti dalla riga di comando |

Il difetto nasce da una buona intenzione: il report esisteva come script a sé, e invece di
riscriverlo è stato richiamato dalla pagina. La concatenazione è arrivata dopo.

## Trappole

- Il comando viene eseguito con `2>&1`: gli errori arrivano nella pagina e sono la prima cosa da
  guardare quando «non funziona».
- Lo script sta in `public/sportello/`: il percorso assoluto è quello dentro il container, non
  quello dell'host.
- Il ruolo serve: con un account cittadino la pagina risponde «riservata al personale». Il
  funzionario si ottiene con L17.

## Collegamento

È il punto più alto della scala tecnica: dall'iniezione in una pagina alla shell. La correzione
non è «filtrare i caratteri» — è non passare dalla shell: `escapeshellarg`, oppure eseguire il
report come codice interno con parametri.
