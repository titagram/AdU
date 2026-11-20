# L06 — CSRF sul cambio IBAN

**Capitolo:** 6 · **OWASP:** A01 Broken Access Control · **Servizio:** `/sportello/`, `esca`

## Obiettivo

Cambiare l'IBAN di un rimborso senza toccare il portale: basta che la vittima apra una pagina.

## Dove guardare

`servizi/esca/csrf.html` (la pagina dell'attaccante) e `POST /sportello/rimborso/{id}/iban`.

## Cosa si ottiene

Il rimborso parte verso il conto dell'attaccante. La vittima ha fatto tutto da sola: la richiesta
è partita dal suo browser, con il suo cookie, dal modulo della pagina-esca.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/public/sportello/index.php:100` | la POST sull'IBAN si riconosce dal solo cookie di sessione |
| `api/public/sportello/index.php:104` | nessun token, nessun controllo di origine, nessuna conferma |
| `servizi/esca/csrf.html` | il form che punta al portale e invia da solo |

## Trappole

- Il cookie di sessione è `SameSite=Lax` di default: una POST cross-site **non** porta il cookie.
  Nel laboratorio il form parte dalla pagina-esca e il browser considera la richiesta
  same-site solo se la vittima ha già una sessione attiva e il cookie è `SameSite=None`. In
  pratica: apri la pagina-esca dal portale (`esca` è sulla rete del laboratorio) e verifica con
  l'header `Cookie` nella richiesta.
- L'id del rimborso è nel form: la vittima deve averne uno. Il seed ne dà uno a Giulia.
- Senza la sessione la risposta è un redirect al login, non un errore.

## Collegamento

È l'unico laboratorio in cui il difetto non sta nel codice del portale ma in quello che il
browser fa per abitudine. La correzione: token anti-CSRF legato alla sessione, più `SameSite`
esplicito; e il cambio IBAN dovrebbe chiedere una conferma fuori banda.
