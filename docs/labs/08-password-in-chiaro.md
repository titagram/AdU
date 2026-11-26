# L08 — Hash deboli e password riutilizzate

**Capitolo:** 2 · **OWASP:** A02 Cryptographic Failures · **Servizio:** `api`, `db`

## Obiettivo

Recuperare la password di un contribuente senza attaccare il portale: leggendo il database.

## Dove guardare

Il dump del 2019 (L19), la tabella `utenti` del database di esercizio (via `/debug?sql=` quando
il docente abilita `ADU_DEBUG_CONSOLE_ENABLED=true`), e il seed del laboratorio.

## Cosa si ottiene

Le password sono in MD5 senza sale. Mario Rossi ha `5f4dcc3b5aa765d61d8327deb882cf99`, che è
`password`: non serve un calcolatore, serve una ricerca. Le altre sono nel dizionario del
laboratorio, e un attaccante non deve nemmeno provarle tutte: le prova contro il login (L22).

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/database/seeders/DatabaseSeeder.php:219` | `md5($password)`: nessun sale, nessun costo |
| `api/app/Http/Controllers/Api/AuthController.php:41` | il confronto avviene nella query, quindi l'hash è la password |
| `api/public/sportello/index.php:123` | lo sportello legacy fa lo stesso, e resta la ragione «di compatibilità» |

## Trappole

- `Hash::check()` su un MD5 restituisce `false`, non un errore: chi riusa il codice di Laravel
  crede che il test funzioni e invece non verifica nulla.
- La password di Mario è la più comune al mondo: la lezione non è «ha scelto male», è «il sistema
  ha reso inutile qualunque scelta decente togliendo il sale».
- Il dump ha uno schema vecchio: gli hash sono diversi da quelli in esercizio, ma la debolezza è
  la stessa.

## Collegamento

Senza L08, L10 sarebbe un esercizio elegante ma inutile: con un hash serio l'iniezione
restituirebbe una riga che non si può usare per entrare. Insieme, le due cose rendono il login
un cancello di carta. La correzione è una sola: `bcrypt`/`argon2`, e migrazione al primo accesso.
