# L22 — Enumerazione, rate limit, sessioni

**Capitolo:** 2 · **OWASP:** A07 Identification and Authentication Failures · **Servizio:** `api`

## Obiettivo

Sapere chi esiste, entrare come chi non si conosce la password, e restare dentro dopo essere
usciti.

## Dove guardare

`POST /api/login` per i primi due punti; lo sportello legacy (`/sportello/`) per gli altri.

## Cosa si ottiene

**Enumerazione.** Il login risponde «Codice fiscale non registrato.» oppure «Password errata.».
Sono due messaggi per la stessa cosa, e la differenza è l'informazione: con un elenco di codici
fiscali si scopre chi è assistito dall'agenzia.

**Nessun limite.** Dieci, cento, mille tentativi: la risposta arriva sempre, senza attese. La
storia del `.git` (L19) spiega anche perché: il limitatore è stato spento il 2 aprile 2025
«per il collaudo» ed è tornato solo a fine settembre.

**Sessione fissa.** Lo sportello apre la sessione prima dell'accesso e non la rigenera dopo. Chi
riesce a far usare alla vittima un identificativo di sessione che conosce — un link, un
sottodominio, un computer condiviso — si ritrova autenticato al suo posto. Nel laboratorio:
`PHPSESSID=attaccante123` scelto prima, vittima che accede, e la stessa sessione serve le
pratiche della vittima.

**Uscita che non chiude.** `/sportello/esci` mostra una pagina di cortesia e non tocca la
sessione: il cookie resta valido, le pratiche restano raggiungibili.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AuthController.php:41-49` | i due messaggi distinti, da due query diverse |
| `api/routes/api.php` | il login non ha `throttle`: il middleware è stato tolto |
| `api/public/sportello/index.php:16` | `session_start()` senza `session_regenerate_id()` dopo l'accesso |
| `api/public/sportello/index.php:175` | `/esci` non distrugge la sessione né cancella il cookie |

## Trappole

- L'enumerazione si vede **solo** quando la query principale non trova nulla: se il codice
  fiscale esiste e la password è giusta, il messaggio non c'è. Va confrontato il caso «esiste» con
  il caso «non esiste» — cioè due richieste, non una.
- La sessione fissa richiede due browser o due jar di cookie: con uno solo sembra che tutto
  funzioni, perché il cookie è sempre lo stesso.
- Dopo `/esci` la pagina *dice* che la sessione è chiusa. È la lezione: l'interfaccia dichiara
  una cosa che il server non ha fatto.
- Il token dell'API non c'entra: quello non scade per L09. Qui si parla del cookie dello
  sportello, che è un altro sistema di autenticazione nello stesso portale.

## Collegamento

È il laboratorio che lega il capitolo 1 al resto: l'enumerazione dà i nomi, il dizionario (L08)
dà le password, la sessione fissa dà l'accesso senza password. La correzione tocca tre cose
diverse — messaggio unico, contatore, rigenerazione — e va spiegata come tre decisioni, non come
una patch.
