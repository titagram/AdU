# L26 — Caricamento non sicuro

**Capitolo:** 3 · **OWASP:** A04 Insecure Design · **Servizio:** `api`

## Obiettivo

Caricare un file che il server servirà come pagina, non come allegato.

## Dove guardare

`POST /api/pratiche/{id}/allegato` (multipart) e poi
`GET /api/pratiche/{id}/allegato?file=<nome>`.

## Cosa si ottiene

Un `.html` con dentro uno script viene accettato perché il client dichiara
`Content-Type: text/plain`, conservato con il nome originale e restituito dal dominio dell'API
come `text/html`. Il browser lo esegue: è una XSS che non passa da nessun campo di testo, quindi
nessun filtro di input la vede.

Lo stesso vale per un `.svg`, che è un documento con script, e per un `.pdf` che contiene
JavaScript.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:242` | `POST /api/pratiche/{id}/allegato` |
| `api/app/Http/Controllers/Api/AreaController.php:255` | i tipi ammessi sono confrontati con `getClientMimeType()`, cioè con quello che dice il client |
| `api/app/Http/Controllers/Api/AreaController.php:263` | il nome è `getClientOriginalName()` |
| `api/app/Http/Controllers/Api/AreaController.php:266` | il file finisce in `storage/app/allegati/{id}` e viene servito con il tipo dedotto dall'estensione |

## Trappole

- Senza token la rotta risponde `401`: il caricamento è autenticato, e questo basta a farlo
  sembrare sicuro.
- Il tipo vero del file non viene controllato: `file -i` sul file caricato dice `text/html` e
  il portale non se ne accorge.
- Il file resta: `lab:reset` non pulisce `storage/app/allegati`. Va cancellato a mano, e questo
  è già una lezione.

## Collegamento

È la seconda via per l'esecuzione nel browser, più difficile da filtrare della XSS stored del
capitolo 4: lì c'è un campo di testo, qui un file. La correzione: nome generato dal server,
estensione derivata dal tipo verificato, `Content-Disposition: attachment`, e i file serviti da
un dominio separato.
