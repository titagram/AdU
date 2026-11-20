# L05 — Assegnazione di massa

**Capitolo:** 3 e 6 · **OWASP:** A01 Broken Access Control · **Servizio:** `api`, `/sportello/`

## Obiettivo

Cambiare i campi che il client non dovrebbe poter cambiare: l'identità fiscale prima, il ruolo
poi.

## Dove guardare

`PATCH /api/profilo` per il primo vettore; `PATCH /sportello/utenti/{id}` per il secondo
(capitolo 6).

## Cosa si ottiene

**Primo vettore.** `{"codice_fiscale": "NREGLI90A41H501G"}` sul proprio profilo: da quel momento
il portale mostra i dati di Giulia. Non serve indovinare id o token: si cambia la propria identità.
È un IDOR al contrario, e funziona perché il corpo della richiesta finisce direttamente nel
modello.

**Secondo vettore.** Con un account funzionario, `{"ruolo": "amministratore"}` sull'utente
indicato: il ruolo è fra i campi assegnabili, quindi il portale lo accetta.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:137` | `PATCH /api/profilo` |
| `api/app/Http/Controllers/Api/AreaController.php:142` | `$utente->update($request->all())` |
| `api/app/Models/Utente.php:15` | `$fillable` include `codice_fiscale`, `ruolo`, `attivo`, `reset_disabilitato` |

Il modello dichiara che quei campi sono assegnabili: non è un errore del controller, è una
decisione presa una volta e mai più discussa.

## Trappole

- Il portale non restituisce errore se il campo esiste: restituisce l'utente aggiornato. Va
  guardato il campo, non lo stato HTTP.
- Dopo il cambio di codice fiscale l'account continua a funzionare, ma il fascicolo è un altro:
  è la dimostrazione più netta della differenza fra identità e credenziale.
- `attivo: false` su sé stessi è un modo per disattivarsi: se lo fai, `lab:reset`.

## Collegamento

Il primo vettore è nel capitolo 3 (identità), il secondo nel capitolo 6 (ruolo): stessa causa,
due conseguenze di gravità diversa. È il laboratorio che spiega perché `$fillable` è una
decisione di sicurezza e non una comodità.
