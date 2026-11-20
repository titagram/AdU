# L25 — SSRF: il server va dove il browser non può

**Capitolo:** 6 · **OWASP:** A10 SSRF · **Servizio:** `api`

## Obiettivo

Usare l'API come ponte verso la rete interna.

## Dove guardare

`POST /api/allegati/da-url` con `{"url": "..."}`.

## Cosa si ottiene

- `http://mail:8025/api/v1/messages` → tutte le email della giornata, compresi i collegamenti di
  recupero password di L17;
- `http://admin:admin@anagrafe/admin` → l'anagrafe (L20), che da fuori non esiste;
- `http://banca/verifica` → «chiave non valida», cioè il servizio si raggiunge e serve solo la
  chiave che la SPA espone (L09).

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AllegatiController.php` | `file_get_contents` sull'URL del client |
| `api/app/Http/Controllers/Api/AllegatiController.php` | nessun controllo su host, schema o redirect |
| `docker-compose.yml:97` | i servizi interni sono su `backend`, non pubblicati: è l'SSRF a renderli raggiungibili |

## Trappole

- Il servizio interno si chiama con il nome del container (`mail`, `anagrafe`, `banca`), non con
  `localhost`: dentro il container dell'API `localhost` è l'API stessa.
- `file_get_contents` con `ignore_errors` restituisce anche le risposte `4xx`: un `401` è già una
  prova che l'host esiste.
- Il Basic auth nell'URL è il modo per attraversare un SSRF che non consente header.

## Collegamento

È il laboratorio che chiude il cerchio: la stessa API che doveva scaricare un allegato legge la
posta di tutti e l'anagrafe. La correzione: lista di domini ammessi, niente schema `file://`,
risoluzione DNS controllata, e i servizi interni autenticati con segreti veri.
