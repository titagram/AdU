# Catalogo dei laboratori

26 schede. La numerazione è stabile: le schede individuali in `docs/labs/NN-*.md` useranno lo
stesso identificativo, e il codice citerà `L<nn>` nei commenti `// lab:`.

Ogni scheda individua **un finding**, non un file: due finding sullo stesso endpoint sono due
schede (L01 e L02 condividono `/api/cartelle`).

Legenda colonne:

- **Cap.** — capitolo del copione in cui viene scoperto;
- **Dove** — servizio e endpoint previsti (i percorsi sono di progetto, non ancora implementati);
- **Si ottiene** — cosa guadagna chi attacca, in una riga;
- **Seed** — cosa deve esistere nel database perché il lab funzioni davvero.

---

## A01 — Broken Access Control

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L01 | IDOR in lettura | 3 | `api` · `GET /api/cartelle/{id}`, `/api/pratiche/{id}`, `/api/rimborsi/{id}` | dati fiscali di altri contribuenti, PDF compresi | 200 contribuenti con pratiche e rimborsi popolati |
| L02 | IDOR in scrittura | 3 | `api` · `PATCH /api/cartelle/{id}` | azzera o modifica l'importo di una cartella non propria | la cartella di Giulia Neri a € 1.247,00 |
| L03 | Forced browsing | 3 | `api` · `GET /api/admin/export` | CSV con tutti i contribuenti | ruolo `admin` assente dal middleware **solo** su questo endpoint |
| L04 | Path traversal | 3 | `api` · `GET /api/pratiche/{id}/allegato?file=` | legge file fuori dalla cartella allegati (`.env`, sorgente) | un allegato reale in `storage/allegati/` come bersaglio "pulito" |
| L05 | Mass assignment | 3 e 6 | `api` `PATCH /api/profilo` · `PATCH /sportello/utenti/{id}` | **due vettori**: impersona un cittadino (`codice_fiscale`); da funzionario si promuove ad amministratore (`ruolo`) | `$fillable` permissivo solo nell'editor sportello (cancello I2) |
| L06 | CSRF sul cambio IBAN | 6 | `api` · `POST /sportello/rimborso/{id}/iban` | sposta un rimborso su un conto scelto da chi attacca | sessione cookie valida; nessun token CSRF sul modulo |
| L07 | Open redirect | 2 | `api` · `GET /sportello/entra?ritorno=` | rimanda a un dominio esterno dopo il login | — |

## A02 — Cryptographic Failures

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L08 | Password in MD5 | 2 | `db` · tabella `utenti` | hash deboli, compatibili con i dizionari | password dei cittadini da un dizionario di 20 voci |
| L09 | Segreti e token | 2 | `web` · bundle SPA; `api` · JWT | API key della banca nel JavaScript; token senza scadenza, senza revoca, in `localStorage` | token con `exp` assente; API key in una costante del bundle (I4: **non** è la chiave JWT) |

## A03 — Injection

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L10 | SQLi sul login | 2 | `api` · `POST /api/login` | autenticazione come **Mario Rossi** (prima riga) | riga 1 di `utenti` = cittadino (I1) |
| L11 | SQLi UNION e error-based | 5 | `api` · `GET /sportello/pratiche?cf=` e `?sort=` | dump di `utenti` con gli hash; errori SQL in pagina | `SELECT` a 6 colonne; `sort` non parametrizzabile |
| L12 | SQLi blind e time-based | 5 | `api` · `GET /sportello/verifica?protocollo=` (pubblico) | risposte sì/no e ritardi misurabili | due messaggi distinti: "in lavorazione" / "non trovato" |
| L13 | Command injection | 5 | `api` · `POST /sportello/report` | esecuzione di comandi tramite il generatore di PDF | un convertitore invocato con `exec()` e argomenti concatenati |
| L14 | XSS riflesso | 4 | `api` · `POST /api/contatti` | esegue script nel browser della vittima, senza autenticazione | il modulo rimanda a schermo il testo inviato con `{!! !!}` |
| L15 | XSS stored | 4 | `api` · `POST /api/pratiche/{id}/nota` (scrive il cittadino) → `GET /sportello/pratica/{id}` (la legge il funzionario) | **furto del token del funzionario** quando apre la pratica | un funzionario che apre le pratiche (finestra in incognito) |
| L16 | XSS DOM | 4 | `web` · SPA, parametro `?torna=` | esecuzione solo lato client, invisibile al server | `location.search` inserito con `innerHTML` |

## A04 — Insecure Design

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L17 | Recupero credenziali prevedibile | 2 | `api` · `POST /api/recupera-password`, `POST /api/rimborsi/{id}/iban` | token di reset ricavabile (CF + data); OTP a 4 cifre forzabile | `password_reset` senza entropia; OTP senza lockout; `reset_disabilitato` sull'admin (I5) |
| L18 | Race condition sul rimborso | 6 | `api` · `POST /api/rimborsi/{id}/richiedi` | due rimborsi per la stessa pratica | controllo "già richiesto" separato dalla scrittura; finestra di ~200 ms |

## A05 — Security Misconfiguration

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L19 | Esposizione e configurazione | 1 | `web`, `api` · `/.git`, `/robots.txt`, `/backup/`, `/debug`, CORS | sorgente completo, dump del 2019, query SQL, stack trace, XSS cross-origin | `.git` finto con una storia plausibile; dump del 2019 in `/backup/`; `Access-Control-Allow-Origin: *` |
| L20 | Credenziali predefinite | 6 | `anagrafe` · `/admin` | archivio completo dei contribuenti | `admin/admin` attivo **solo** qui (I2) |

## A06 — Vulnerable and Outdated Components

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L21 | Dipendenze obsolete | 1 | `api` · `GET /admin/dipendenze` | elenco di librerie note e non aggiornate | pagina **documentativa**: nessuna dipendenza vulnerabile reale installata |

## A07 — Identification and Authentication Failures

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L22 | Autenticazione e sessioni | 2 | `api` · `POST /api/login`, logout, `/sportello/*` | enumera gli utenti, forza le password, fissa la sessione, resta dentro dopo il logout | nessun rate limit (il controllo è commentato); policy password solo per lo staff (I3) |

## A08 — Software and Data Integrity Failures

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L23 | Deserializzazione insicura | 3 | `api` · `POST /api/profilo/preferenze`, import pratiche | oggetti costruiti da input non fidato (`unserialize`), codice valutato (`eval`) | un campo `preferenze` in base64 nel profilo |

> Nota: la "prototype pollution" classica è un difetto JavaScript e qui non si applica. In un
> backend PHP l'equivalente reale è l'**object injection** via `unserialize()`: la scheda usa
> il termine corretto invece di forzare la categoria.

## A09 — Security Logging and Monitoring Failures

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L24 | Logging insufficiente | 1 | `api` · `logs/app.jsonl`, `GET /api/admin/log` | consulta i log di tutti; i login falliti non esistono; inietta righe false via `User-Agent` | log popolati da traffico plausibile, con le omissioni descritte in `DESIGN.md` §11 |

## A10 — Server-Side Request Forgery

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L25 | SSRF | 6 | `api` · `POST /api/allegati/da-url` | raggiunge `anagrafe` e `banca`, che dal browser non esistono; `file:///` per leggere file locali | rete `backend` `internal: true`; servizi interni senza `ports:` |

## Trasversali (fuori dal Top 10, dentro il corso)

| ID | Titolo | Cap. | Dove | Si ottiene | Seed |
|---|---|---|---|---|---|
| L26 | Upload non sicuro | 3 | `api` · `POST /api/pratiche/{id}/allegato` | carica file eseguiti o interpretati (`.html`, `.svg`) | validazione solo sul `Content-Type` dichiarato dal client |

---

## Copertura delle tecniche di SQLi

| Tecnica | Lab | Endpoint | Cosa deve esserci perché funzioni |
|---|---|---|---|
| Classica / auth bypass | L10 | `POST /api/login` | prima riga `utenti` = cittadino (I1) |
| UNION | L11 | `GET /sportello/pratiche?cf=` | `SELECT` a **6 colonne** testuali; `utenti` leggibile |
| Error-based su identificatore | L11 | `GET /sportello/pratiche?sort=` | `sort` concatenato: `ORDER BY` non ammette placeholder |
| Blind boolean | L12 | `GET /sportello/verifica?protocollo=` | due risposte distinguibili, nessun rate limit |
| Blind time-based | L12 | stesso endpoint | nessuna cache davanti all'endpoint |
| Stacked (scrittura) | L02, L18 | `POST /sportello/archivio` | `PDO::query()` con multi-istruzione abilitata |

> Le tecniche di secondo ordine e il TOCTOU "da manuale" sono esclusi per scelta: la classe
> resta su 26 schede, ognuna con un premio narrativo.

---

## Requisiti di seed, in sintesi

| Requisito | Perché |
|---|---|
| 200 contribuenti, ~600 pratiche, ~400 rimborsi | volume credibile per la ricerca, il dump e l'export |
| **Mario Rossi** come prima riga di `utenti` | I1: il bypass non deve dare privilegi |
| **Giulia Neri** come account dimostrativo, con € 1.247,00 di debito | obiettivo dichiarato, distinto dal primo record |
| 3 funzionari con password conformi alla policy | I3: il brute force non arriva al funzionario |
| 1 amministratore con password robusta e `reset_disabilitato` | I5: niente scorciatoie al gradino finale |
| 20 password da dizionario per i cittadini | il brute force deve essere questione di secondi |
| un allegato "riservato" di Sara Bianchi | il colpo di scena del capitolo 3 |
| `admin/admin` solo su `anagrafe` | I2: la scorciatoia esiste, ma in fondo alla strada |

## Verifica delle invarianti

Prima di dichiarare chiuso M6, ogni invariante va provata con un tentativo reale:

| # | Prova | Esito atteso |
|---|---|---|
| I1 | `' OR 1=1-- ` sul login | si entra come Mario Rossi, **non** come amministratore |
| I2 | percorso più breve anonimo → amministratore | non esiste senza passare da funzionario |
| I3 | brute force sul funzionario con il dizionario | fallisce |
| I4 | analisi del bundle SPA | nessuna chiave che firmi token |
| I5 | reset password sull'account amministrativo | rifiutato |
