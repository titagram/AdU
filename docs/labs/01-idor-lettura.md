# L01 — IDOR in lettura

**Capitolo:** 3 · **OWASP:** A01 Broken Access Control · **Servizio:** `api`

## Obiettivo

Leggere il fascicolo fiscale di un'altra persona autenticandosi come se stessi.

## Dove guardare

`GET /api/cartelle/{id}`, `GET /api/pratiche/{id}`, `GET /api/rimborsi/{id}` con il token di Mario
Rossi (che è il primo utente del database e ha una password nel dizionario).

## Cosa si ottiene

I dati di Giulia Neri: cartella `2025/000481`, importo, protocollo, stato, e la pratica
`2025/004102`. Nessuna credenziale di Giulia, nessun indizio: solo un numero intero incrementale.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:47` | `Cartella::find($id)` — il filtro sul proprietario non c'è più |
| `api/app/Http/Controllers/Api/AreaController.php:71` | idem sulla pratica |
| `api/app/Http/Controllers/Api/AreaController.php:102` | idem sul rimborso, rotta nuova |
| `api/routes/api.php:30` | la rotta non ha vincoli oltre al numero |

Il commento nel codice racconta la causa reale: il filtro esisteva, è stato tolto quando la SPA
ha cominciato a chiedere la cartella «per id dopo il login», perché tanto l'id lo conosce solo
chi ha la cartella.

## Trappole

- Gli id sono sequenziali: 1, 2, 3. Chi parte da 1000 non trova nulla e conclude che il filtro
  c'è.
- Il token di Mario si ottiene con L08 (`password`) o con L10 (`' OR 1=1#`).
- `GET /api/cartelle` (senza id) **filtra** ancora per utente: il difetto è solo sulle rotte
  puntuali. Confrontare le due risposte è l'esercizio.

## Collegamento

È il primo gradino verso i dati di tutti: con L03 si scarica l'elenco dei codici fiscali, con
L22 si scopre chi esiste, con L01 si legge il fascicolo. La correzione non è «controllare l'id»:
è che ogni rotta che restituisce un oggetto si chieda di chi è l'oggetto.
