# L14 — XSS riflessa

**Capitolo:** 4 · **OWASP:** A03 Injection · **Servizio:** `api`

## Obiettivo

Eseguire codice nel browser di chi apre un collegamento, dal dominio dell'agenzia.

## Dove guardare

`GET http://<host>:8081/api/contatti?nome=...&messaggio=...`

## Cosa si ottiene

La pagina di conferma contiene quello che hai scritto, così com'è. Con
`<script>alert(document.domain)</script>` lo script gira, e il dominio è quello dell'API: stessa
origine della porta `8081`, dove abitano token e allegati.

Il modulo è pubblico e accetta `GET`: il payload viaggia in un collegamento che si manda per
email e che, aperto, esegue senza chiedere niente a nessuno.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/ContattiController.php:20` | legge `nome` e `messaggio` dalla richiesta |
| `api/app/Http/Controllers/Api/ContattiController.php:33` | li concatena nell'HTML senza `htmlspecialchars` |
| `api/routes/api.php` | la rotta accetta `GET` e `POST`: la variante `GET` è quella condivisibile |

## Trappole

- Il payload nei parametri va codificato: `<script>` dentro un URL senza `encodeURIComponent`
  arriva troncato e sembra che il portale filtri.
- La pagina non ha CSP e non ha `X-Content-Type-Options`: nel capitolo di difesa si parte da qui.
- Aprire la pagina nel browser è diverso da leggerne il sorgente: `curl` mostra la stringa, il
  browser la esegue.

## Collegamento

È la versione facile della XSS: non c'è niente da conservare, quindi niente da ripulire in
ingresso. Serve a introdurre la differenza fra *riflessa* (L14), *conservata* (L15) e *nel DOM*
(L16), che è il vero obiettivo del capitolo.
