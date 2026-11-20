# L10 — Iniezione SQL sul login

**Capitolo:** 2 · **OWASP:** A03 Injection · **Servizio:** `api`

## Obiettivo

Entrare senza credenziali.

## Dove guardare

`POST http://<host>:8081/api/login`, corpo JSON.

## Cosa si ottiene

`{"codice_fiscale": "' OR 1=1#", "password": "x"}` restituisce un token. Il token è di Mario
Rossi, la prima riga della tabella: la query non chiede più «chi sei», chiede «dammi una riga»,
e la prima riga è un cittadino qualsiasi.

È l'invariante I1 del progetto, ed è anche il punto didattico: l'attaccante non sceglie *chi*
essere, e questo rende l'attacco meno utile e più istruttivo. Per scegliere c'è la variante con
`UNION`, che è il laboratorio L11.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AuthController.php:41` | la query si costruisce concatenando codice fiscale e MD5 della password |
| `api/app/Http/Controllers/Api/AuthController.php:49` | la seconda query, quella dell'enumerazione, è concatenata allo stesso modo |

Non c'è nessuna sanificazione da aggirare: non c'è proprio la separazione fra codice e dati.

## Trappole

- `#` commenta il resto della riga in MySQL; la password che segue viene ignorata. Chi usa `--`
  deve mettere uno spazio dopo, altrimenti non funziona, e passa dieci minuti a capire perché.
- La risposta è un `200` con un token, non un errore: chi cerca un messaggio di SQL nel corpo
  della risposta non lo trova. L'iniezione riuscita è silenziosa.
- Il codice fiscale viene reso maiuscolo prima della query: i payload con lettere non ne
  soffrono, ma chi prova a indovinare la sintassi parte da lì.
- Il rate limit non c'è (L22): si può iterare senza aspettare.

## Collegamento

Si combina con L08 (MD5) e con L22 (enumerazione, nessun limite). Il rimedio da spiegare non è
«filtrare gli apici»: è un prepared statement, cioè il confine fra codice e dati che qui non
esiste. E la migrazione a `bcrypt` toglie all'attacco la parte di forza bruta.
