# L24 — Logging insufficiente

**Capitolo:** 1 · **OWASP:** A09 Security Logging and Monitoring Failures · **Servizio:** `api`

## Obiettivo

Ricostruire l'attacco della giornata con i registri del portale. Poi scoprire che non si può,
e capire perché.

## Dove guardare

`GET http://<host>:8081/admin/log`, e il registro che l'applicazione scrive in `log_accessi`.

## Cosa si ottiene

Le ultime cento richieste con contribuente, IP, percorso, esito e `User-Agent`. Le righe ci sono,
in ordine, e sembrano un registro serio. Poi si cerca il tentativo di accesso fallito appena
fatto e non c'è. Non c'è nessun tentativo fallito, mai: la condizione che scarta le risposte
`401` è la prima riga del middleware.

Le altre tre omissioni si vedono meno e contano quanto quella:

- nessun identificativo di correlazione: non si può dire «queste quaranta richieste sono un
  solo attacco»;
- l'ora è in **UTC senza dirlo**: chi legge il registro dall'Italia sbaglia di un'ora
  d'inverno e di due d'estate, e dalla riga non ha modo di accorgersene;
- lo `User-Agent` arriva nel database così com'è e la pagina lo rimanda al browser senza
  escape: chi vuole sporcare le prove scrive le righe che preferisce, e chi apre il registro
  esegue il suo codice.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Middleware/RegistraAccesso.php:32` | scarta le risposte `401` e `403`: i tentativi falliti non esistono |
| `api/app/Http/Middleware/RegistraAccesso.php:39` | scrive l'istante in UTC, come stringa senza alcun marcatore di fuso |
| `api/app/Http/Controllers/DiagnosticaController.php:64` | il registro è pubblico: nessun token, nessun ruolo |
| `api/app/Http/Controllers/DiagnosticaController.php:72` | `User-Agent` concatenato nell'HTML senza escape |
| `api/config/app.php:70` | il fuso dell'applicazione è UTC, e nel registro non viene mai dichiarato |

## Trappole

- Il registro **non** contiene i tentativi falliti scritti a mano: vanno generati. Sbagliare la
  password dieci volte e ricaricare la pagina è il primo passo, e non cambia niente — ed è
  esattamente il punto.
- L'iniezione non si vede nel database: lo `User-Agent` è già lì. Si vede aprendo la pagina, e
  per questo è anche una XSS che colpisce chi indaga.
- Chi cerca un file `app.jsonl` non lo trova: in questo progetto il registro è una tabella.

## Collegamento

È il finding che regge il capitolo della difesa: senza eventi di autenticazione, ogni attacco
raccontato nei capitoli 2–6 è invisibile. Il furto del token del funzionario (L15) non lascerà
traccia. È anche il motivo per cui la domanda «chi ha consultato i dati dei rimborsi?» resta
senza risposta.
