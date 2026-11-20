# L02 — IDOR in scrittura

**Capitolo:** 3 · **OWASP:** A01 Broken Access Control · **Servizio:** `api`

## Obiettivo

Modificare un dato fiscale che non ci appartiene, e firmarlo a nome di qualcun altro.

## Dove guardare

`PATCH /api/cartelle/{id}` con il token di Mario.

## Cosa si ottiene

`{"importo": "1.00"}` azzera la cartella di Giulia. Con `{"codice_fiscale": "..."}` la cartella
cambia proprietario, e il vero intestatario non la vede più.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:117` | `Cartella::find($id)` senza controllo di proprietà |
| `api/app/Http/Controllers/Api/AreaController.php:126` | `$cartella->update($request->all())` — assegnazione di massa |
| `api/app/Models/Cartella.php:11` | `$fillable` include `codice_fiscale`: è il permesso che rende possibile il secondo passo |

## Trappole

- La scrittura non restituisce un errore: restituisce l'oggetto aggiornato, con il nuovo
  proprietario. Chi non guarda il codice fiscale nella risposta crede di aver modificato la
  propria cartella.
- `PATCH` e non `PUT`: il client manda solo i campi che cambia, quindi il corpo sembra innocuo.
- Il danno è silenzioso e non recuperabile: dopo `lab:reset` torna tutto, ma in un sistema vero
  quella cartella è rotta e nessuno sa perché.

## Collegamento

L'IDOR in scrittura è la ragione per cui il solo controllo in lettura non basta: la stessa
classe di errore, su un metodo diverso, produce un danno diverso. La correzione è la stessa
(L01) più una policy di autorizzazione sull'oggetto, non sulla rotta.
