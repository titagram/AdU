# L17 — Recupero credenziali prevedibile e OTP debole

**Capitolo:** 2 · **OWASP:** A07 Identification and Authentication Failures · **Servizio:** `api`

## Obiettivo

Prendere il controllo dell'account di un'altra persona senza indovinare la sua password, e
cambiare l'IBAN di un rimborso che non è il proprio.

## Dove guardare

`POST /api/recupera-password`, Mailpit su `http://<host>:8025`, `POST /api/reimposta-password`,
`POST /api/rimborsi/{id}/iban`.

## Cosa si ottiene

Due cose, entrambe sproporzionate rispetto allo sforzo.

**Il collegamento di recupero si costruisce.** Il token è il codice fiscale più la data di oggi.
Chi conosce il codice fiscale di una persona — e dopo L22 lo conoscono tutti — si fabbrica il
suo collegamento e le cambia la password. Non serve aver chiesto il recupero, non arriva nessuna
email, non resta nessuna traccia: `reimposta` non cerca il token nella tabella, lo **ricalcola**.

Nel percorso del laboratorio: si prende il codice fiscale di **Sara Bianchi** (funzionario), si
genera il token, si imposta una password, si accede. Il token restituito ha `ruolo: funzionario`.

**Il codice di conferma dell'IBAN è di quattro cifre e non c'è nessun limite.** Diecimila
richieste, un minuto, e il portale non se ne accorge: nessun ritardo, nessun blocco, nessun
contatore. Nel laboratorio basta mostrare tre tentativi sbagliati accettati senza conseguenze e
poi quello giusto.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AuthController.php:119` | il token è `cf` + `Ymd` |
| `api/app/Http/Controllers/Api/AuthController.php:169-171` | la verifica ricalcola lo stesso valore invece di leggerlo dal database |
| `api/app/Http/Controllers/Api/RimborsoController.php` | confronto dell'OTP senza contatore né ritardo |
| `api/config/app.php`, invariante I5 | l'account amministrativo ha `reset_disabilitato`: il recupero risponde `403` e va mostrato, altrimenti sembra una dimenticanza |

## Trappole

- Il recupero sull'amministratore è **rifiutato**: è l'invariante I5. Se il laboratorio «non
  funziona» su quell'account, sta funzionando.
- La password nuova deve avere almeno sei caratteri, altrimenti la risposta è `422` e sembra
  un rifiuto dell'attacco: è solo la validazione.
- L'OTP non si legge dall'email: non viene spedito. Si ricava dall'identificativo del rimborso
  (`id * 7 % 10000`), il che è un difetto in più — e un'ottima domanda da fare in aula.
- Dopo il reset, l'account resta modificato: `lab:reset` lo riporta com'era.

## Collegamento

Chiude la scalata del capitolo 2: da nessun account a funzionario. Il funzionario è il
prerequisito del capitolo 4, perché è l'unico che legge le note dei cittadini — e quindi il
bersaglio della XSS stored.
