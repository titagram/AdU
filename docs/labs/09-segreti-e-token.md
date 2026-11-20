# L09 — Segreti nel client e token che non muoiono

**Capitolo:** 2 · **OWASP:** A02 Cryptographic Failures · **Servizio:** `api`, `web`

## Obiettivo

Trovare quello che il portale consegna al browser e che il browser non dovrebbe avere, e
accorgersi che l'accesso non scade mai.

## Dove guardare

Il sorgente della SPA (`web/app.js`), DevTools → Application → Local Storage, e l'API.

## Cosa si ottiene

Una chiave condivisa con il servizio bancario, scritta in chiaro nel file che ogni visitatore
scarica. Non è la chiave che firma i token — ed è importante che non lo sia: è quella che apre
il servizio che verifica gli IBAN (invariante I4). Serve a mostrare che «i segreti nel client»
non è un problema astratto: si può usare, e il capitolo 6 lo usa.

Poi il token: il payload ha `iat`, `sub`, `jti`, `ruolo` e non ha `exp`. Il logout risponde `200`
e il token continua a valere. Un token rubato oggi vale domani, e non c'è modo di revocarlo.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `web/app.js:26` | `CHIAVE_BANCA`, residuo di quando la verifica IBAN la faceva il browser |
| `api/app/Support/Jwt.php:31-38` | il payload non contiene `exp` |
| `api/app/Support/Jwt.php:47` | `verifica()` non guarda più la tabella `sessioni` |
| `api/app/Http/Controllers/Api/AuthController.php` (`logout`) | risponde `200` senza revocare niente |

La tabella `sessioni` continua a riempirsi a ogni accesso: è il tipo di codice che resta in
produzione perché «tanto il logout lo fa il client».

## Trappole

- Il token in `localStorage` non scade, ma il portale **sì**: la SPA tratta il `401` come
  «sessione scaduta», quindi chi riusa un token vecchio vede un messaggio fuorviante. Non è il
  difetto: è il difetto che nasconde l'altro.
- La chiave della banca non si può usare dal browser dell'attaccante: il servizio sta su una rete
  interna. Serve un'altra strada per raggiungerlo — ed è il capitolo 6.
- Chi cerca la chiave JWT nel bundle non la trova, e conclude che non c'è niente. La lezione è
  che un segreto esposto è pericoloso anche quando non è quello «importante».

## Collegamento

È la premessa del capitolo 4: la XSS stored ruba un token che non scade, quindi il furto vale
per sempre. E la chiave della banca è il primo pezzo della catena del capitolo 6.
