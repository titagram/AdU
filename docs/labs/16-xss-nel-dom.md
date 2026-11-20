# L16 — XSS nel DOM

**Capitolo:** 4 · **OWASP:** A03 Injection · **Servizio:** `web`

## Obiettivo

Eseguire codice senza che il server veda niente: nessuna richiesta, nessun log, nessun payload
da ripulire.

## Dove guardare

`http://<host>:8080/?torna=<img src=x onerror=alert(document.domain)>#/accedi`

## Cosa si ottiene

Lo script gira nell'origine del portale. Il parametro non arriva mai all'API e non compare in
nessuna risposta: chi cerca l'attacco nei log del server non trova nulla.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `web/app.js:297` | legge `torna` dall'indirizzo |
| `web/app.js:300` | lo scrive nella pagina con `innerHTML` |

La funzione che dovrebbe proteggere esiste (`testo()` per l'escape) e viene usata altrove: qui
no, perché la striscia di benvenuto doveva permettere il grassetto.

## Trappole

- Il frammento `#/accedi` deve restare: senza hash la SPA non instrada e non si vede niente.
- Il payload nell'URL va codificato, altrimenti il browser lo tronca.
- `alert()` viene bloccato dai browser moderni in certi contesti: usa `document.body.dataset.x`
  o una richiesta a un dominio tuo, e verifica dal DOM.

## Collegamento

È il laboratorio che smonta l'idea che la sicurezza sia solo lato server. Chi corregge deve
toccare il client, e per farlo bisogna che qualcuno nel team sappia leggerlo.
