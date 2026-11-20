# Agenzia delle Uscite — documento di progetto

> **Applicazione deliberatamente vulnerabile a scopo didattico.**
> Non va esposta su Internet, non va usata con dati reali, non contiene dati di persone reali.
> Le vulnerabilità sono intenzionali e documentate; le correzioni non sono nel codice.

Stato: **M0 — solo documentazione. Nessuna riga di applicazione è stata scritta.**
Le sezioni 9, 10 e 11 descrivono ciò che verrà costruito in M1; la sezione 14 elenca cosa
non è ancora stato verificato.

---

## 1. Scopo e limiti

| | |
|---|---|
| Cosa è | un portale fiscale fittizio ("Agenzia delle Uscite") con vulnerabilità intenzionali, per un corso di sicurezza applicativa |
| A chi serve | sei sessioni da due ore, sei capitoli narrativi (`COPIONE.md`) |
| Chi sono gli studenti | programmatori senior: i difetti devono essere di **contesto**, non di sintassi |
| Cosa non è | un exploit kit, un laboratorio con soluzioni incorporate, un clone di un sito reale |
| Cosa non contiene | loghi o identità visive di enti reali, dati di persone reali, credenziali reali, payload pronti |

Regole di sicurezza del progetto:

1. **Solo locale.** Tutti i servizi pubblicati ascoltano su `127.0.0.1`. Nessun `ports:` sui
   servizi interni. La rete `backend` è marcata `internal: true`.
2. **Dati inventati.** Nomi, codici fiscali, indirizzi, importi e protocolli sono generati.
   I codici fiscali sono sintatticamente validi (checksum corretto) ma non appartengono a
   persone reali.
3. **Nessuna somiglianza di marca.** Il registro visivo è "istituzionale sobrio": niente
   stemmi, loghi o layout ricalcati su enti esistenti. La parodia sta nel nome e nel tono.
4. **Niente exploit nel repository.** Le schede dei lab descrivono obiettivo e impatto; i
   passaggi operativi restano al docente.

---

## 2. Il modello di minaccia narrativo

Uno studente, un browser, nessuna credenziale iniziale (a parte l'account che riceve).
L'obiettivo dichiarato è far sparire la propria posizione debitoria; l'obiettivo reale è
capire quanto in profondità si arriva.

La scalata dei privilegi ha **cinque cancelli**. Sono invarianti di progetto: se una
modifica futura ne apre uno, il corso perde una sessione.

| # | Invariante | Perché |
|---|---|---|
| I1 | Il bypass di autenticazione non restituisce un privilegio superiore a quello della **prima riga** di `utenti` | `' OR 1=1-- ` restituisce il primo record: se fosse l'amministratore, il corso finisce in dieci secondi |
| I2 | Nessun endpoint **anonimo** concede più dello scalino successivo | altrimenti la ricognizione sostituisce l'escalation |
| I3 | Le password deboli sono dei **cittadini**, non dello staff | realistico (rotazione a 90 giorni per gli interni) e impedisce al brute force di arrivare al funzionario |
| I4 | I segreti esposti **non firmano i token** | il segreto esposto è la API key della banca, non la chiave JWT |
| I5 | L'account amministrativo **non ha recupero password self-service** | chiude la scorciatoia più insidiosa, dato che il token di reset è prevedibile |

Conseguenza operativa: `admin/admin` esiste, ma **sul servizio interno `anagrafe`**,
raggiungibile solo via SSRF (L25).

---

## 3. Architettura dei servizi

Sette container, due reti.

```
                 host (127.0.0.1)
                        |
        +---------------+----------------+
        |                                |
   :8080 web (SPA)                 :8081 api (Laravel)
        |                                |
        +-------- rete frontend ---------+
                                         |
                        +----------------+----------------+
                        |        rete backend (internal)  |
                        |                                 |
                     db:3306   mail:1025/8025   anagrafe   banca   esca
```

| Servizio | Immagine / ruolo | Pubblicato | Reti |
|---|---|---|---|
| `web` | nginx, serve la SPA (file statici, JS a moduli ES) | `127.0.0.1:8080` | frontend |
| `api` | Laravel (php-fpm + nginx interno), API JSON e modulo legacy | `127.0.0.1:8081` | frontend, backend |
| `db` | MySQL 8, schema e seed | no | backend |
| `mail` | Mailpit: email di reset e PEC finta | `127.0.0.1:8025` (UI) | backend |
| `anagrafe` | "Anagrafe tributaria": archivio vero, `admin/admin`, senza auth | **no** | backend |
| `banca` | mock "Verifica IBAN", richiede API key | **no** | backend |
| `esca` | raccoglitore: mostra i token ricevuti dal payload XSS | **no** | backend |

Note progettuali:

- **`anagrafe` e `banca` non sono esposti**: è la ragione d'essere della SSRF (L25). Senza
  questa separazione il lab diventa "un altro path sullo stesso servizio".
- **`esca`** simula il server dell'attaccante: sostituisce Burp Collaborator e rende visibile
  il furto del token (L15 → L09).
- **Niente Redis**: le sessioni del modulo legacy sono su file, i token della SPA sono JWT.
  Meno servizi da installare, e la storia del furto del token non ne ha bisogno.
- **`fascicolo` accorpato in `api`**: gli allegati sono un volume montato. Il bucket pubblico
  si dimostra lo stesso.

---

## 4. Stack e motivazioni

| Scelta | Motivo | Alternativa scartata |
|---|---|---|
| Laravel ultima versione stabile (fissata in M1) | le protezioni giuste sono il *default*, quindi ogni difetto è un errore riconoscibile del developer | una versione vecchia "notoriamente vulnerabile": insegnerebbe un bug del framework, non un errore di progetto |
| PHP 8.3 nell'immagine | l'host Debian 12 ha PHP 8.2, ma non serve installarne nessuno: lo porta il container | PHP sull'host: dipendenze da gestire su ogni macchina della scuola |
| MySQL 8 | fedele alla realtà dei gestionali pubblici, e `PDO::query` consente le query impilate | MariaDB: compatibile, ma il comportamento multi-istruzione va verificato |
| SPA in JS vanilla, moduli ES | due origini reali (CORS dimostrabile), `localStorage` credibile, **zero build step** | Vue/React + Vite: aggiunge Node e un passaggio che può fallire in aula |
| Blade + CSS scritto a mano | un solo comando per partire | Tailwind: introduce il build step che vogliamo evitare |

---

## 5. Superfici applicative

Tre superfici, tre registri. La separazione non è estetica: ogni superficie ha un modello di
autenticazione diverso, e quindi difetti diversi.

### 5.1 Sito pubblico (SPA, nessuna autenticazione)

| Endpoint | Cosa fa | Lab |
|---|---|---|
| `GET /` | home, avvisi, banner cookie inutile | — |
| `GET /servizi` | elenco servizi | — |
| `POST /api/contatti` | "Scrivi all'ufficio", rimanda a schermo il testo | L14 (XSS riflesso) |
| `POST /api/login` | autenticazione, messaggi distinti | L22, L10 |
| `POST /api/recupera-password` | invia il token via email | L17 |
| `GET /sportello/verifica?protocollo=` | controllo pubblico dello stato pratica | L12 (blind e time-based) |
| `GET /robots.txt` | elenca `/admin`, `/backup/`, `/legacy/`, `/debug` | L03, L19 |
| `GET /.git/...` | repository esposto | L19 |
| `GET /backup/` | directory listing + dump del 2019 | L19 |
| `GET /debug` | ultime query eseguite | L19 |

### 5.2 Area riservata del cittadino (SPA + JWT)

| Endpoint | Cosa fa | Lab |
|---|---|---|
| `GET /api/me` | profilo corrente | — |
| `GET /api/cartelle` | le mie posizioni debitorie | — |
| `GET /api/cartelle/{id}` | dettaglio cartella | L01 |
| `PATCH /api/cartelle/{id}` | annulla / modifica importo | L02 |
| `GET /api/pratiche`, `/api/pratiche/{id}` | le mie pratiche | L01 |
| `GET /api/pratiche/{id}/allegato?file=` | scarica un allegato | L04 |
| `GET /api/rimborsi/{id}` | dettaglio rimborso e PDF | L01 |
| `POST /api/rimborsi/{id}/richiedi` | richiede il rimborso | L18 |
| `PATCH /api/profilo` | aggiorna il profilo | L05 |
| `POST /api/profilo/preferenze` | salva le preferenze | L23 |
| `POST /api/rimborsi/{id}/iban` | cambio IBAN con OTP | L17 |
| `POST /api/allegati/da-url` | allega un documento da un indirizzo | L25 |

### 5.3 Sportello legacy (HTML, sessione PHP con cookie)

Modulo scritto nel 2019 e mai migrato. Usa una connessione PDO propria e `->query()`, quindi
**è l'unico punto del sistema con potere di scrittura arbitrario** (query impilate).

| Endpoint | Cosa fa | Lab |
|---|---|---|
| `GET /sportello/pratiche?cf=&sort=&page=` | ricerca pratiche | L11, L12 |
| `GET /sportello/pratica/{id}` | dettaglio, note interne | L15 |
| `POST /sportello/pratica/{id}/nota` | aggiunge una nota | L15 |
| `POST /sportello/report` | genera il PDF della pratica | L13 |
| `PATCH /sportello/utenti/{id}` | editor utenti | L05 (escalation) |
| `POST /sportello/archivio` | archivia protocollo (query impilata) | L02, L18 |
| `POST /sportello/rimborso/{id}/iban` | cambio IBAN lato funzionario | L06 (CSRF) |
| `GET /sportello/entra?ritorno=` | redirect dopo il login | L07 |

### 5.4 Backoffice

| Endpoint | Cosa fa | Lab |
|---|---|---|
| `GET /api/admin/export` | export di tutti i contribuenti | L03 |
| `GET /api/admin/log` | consultazione log | L24 |
| `GET /admin/dipendenze` | elenco librerie | L21 |
| `GET /api/admin/utenti` | gestione utenti (**protetto correttamente**) | — |

> `PATCH /sportello/utenti/{id}` e `GET /api/admin/utenti` sono gli unici endpoint con un
> controllo di ruolo corretto. È voluto: sono il cancello dell'invariante I2.

---

## 6. Autenticazione e sessione

Un solo ingresso (`POST /api/login`), due credenziali:

| Meccanismo | Dove | Difetti intenzionali |
|---|---|---|
| JWT in `Authorization: Bearer` | SPA, area riservata | nessuna scadenza, nessuna revoca, nessun legame con IP/UA, logout solo client-side |
| Cookie di sessione PHP | `/sportello/*` | token non rigenerato al login (session fixation), logout che non distrugge la sessione |

Il cookie è impostato dallo stesso login: è la "SSO di migrazione" che un fornitore avrebbe
davvero lasciato in piedi. Serve a tenere in vita il CSRF (L06), che con i Bearer token non
esisterebbe.

Login (L10, L22):

```php
// api/app/Http/Controllers/AuthController.php  (previsto)
$u = DB::select("SELECT * FROM utenti WHERE codice_fiscale = '$cf'");
if (empty($u))      return response()->json(['errore' => 'Codice fiscale non presente in archivio.'], 401);
if ($u[0]->password !== md5($password)) return response()->json(['errore' => 'Password non corretta.'], 401);
```

- due messaggi distinti → enumerazione (L22);
- nessun rate limit: il controllo esiste nel codice ma è commentato con un `// TODO` (L22);
- confronto con MD5 (L08);
- `' OR 1=1-- ` restituisce la prima riga: **Mario Rossi** (I1).

---

## 7. La scalata dei privilegi

| Da | A | Vettore | Cancello |
|---|---|---|---|
| anonimo | cittadino | enumerazione + brute force, oppure SQLi sul login | — |
| cittadino | altro cittadino | IDOR su cartelle/pratiche/rimborsi, path traversal, mass assignment del CF | serve un account |
| cittadino | **funzionario** | XSS stored → furto del JWT dalla postazione del funzionario | il funzionario deve aprire la pratica |
| funzionario | **amministratore** | `PATCH /sportello/utenti/{id}` con `$fillable` che accetta `ruolo` | serve un account funzionario |

L'ultimo gradino non è la forgiatura di un token: il segreto che firma i JWT non è esposto
(I4). È il classico `$fillable` condiviso fra il profilo del cittadino (che filtra i campi) e
l'editor utenti del backoffice (che non li filtra) — un difetto di riuso, non di sintassi.

---

## 8. Capacità di scrittura

| Gradino | Capacità | Dove | Requisiti |
|---|---|---|---|
| mirata | modifico una cartella che non è mia | `PATCH /api/cartelle/{id}`, nessun controllo di proprietà | autenticato |
| arbitraria | `UPDATE`, `DELETE`, qualsiasi cosa | `POST /sportello/archivio` con `PDO::query` | funzionario |

Perché serve il secondo: una SQLi dentro una `SELECT` di Laravel passa da `prepare()` e i
prepared statement eseguono **una** istruzione. Le query impilate funzionano solo su
`PDO::query()`, che è ciò che il modulo legacy usa da sempre. La domanda "perché qui
funziona e là no?" è materiale didattico, non un incidente.

`PATCH /api/cartelle/{id}` **non** deve accettare `codice_fiscale` né `ruolo`: se li
accettasse diventerebbe un secondo modo di impersonare e il capitolo 4 perderebbe il premio.

---

## 9. Modello dati (previsto, M1)

| Tabella | Colonne principali | Note |
|---|---|---|
| `utenti` | `id, codice_fiscale, nome, cognome, email, password, ruolo, attivo, reset_disabilitato, ultimo_accesso` | `password` in MD5; **riga 1 = Mario Rossi (cittadino)** |
| `contribuenti` | `codice_fiscale, data_nascita, comune_nascita, indirizzo, cap, provincia, pec, telefono` | anagrafica separata dall'autenticazione |
| `cartelle` | `id, codice_fiscale, protocollo, importo, anno, stato, scadenza` | stato: `in_sospeso`, `pagata`, `annullata` |
| `pratiche` | `id, protocollo, codice_fiscale, servizio, stato, aperta_il, funzionario_id` | |
| `note_pratiche` | `id, pratica_id, autore_id, corpo, creato_il` | rese con `{!! !!}` → L15 |
| `allegati` | `id, pratica_id, nome_file, percorso, mime, dimensione` | |
| `dichiarazioni` | `id, codice_fiscale, anno, reddito, imposta, stato` | |
| `rimborsi` | `id, codice_fiscale, dichiarazione_id, importo, iban, stato` | |
| `iban` | `id, codice_fiscale, iban, intestatario, verificato` | CSRF su cambio |
| `otp` | `id, codice_fiscale, codice, scadenza, usato` | 4 cifre, nessun lockout |
| `password_reset` | `id, codice_fiscale, token, scadenza, usato` | token prevedibile |
| `log_accessi` | `id, ts, codice_fiscale, ip, metodo, path, status, user_agent` | senza login falliti |

Vincoli di progetto sul seed:

- **`utenti` riga 1** = cittadino (Mario Rossi), non l'amministratore → I1;
- account dimostrativo dello studente = **Giulia Neri**, con una posizione debitoria di
  € 1.247,00, **diverso** dal primo record;
- amministratore: password robusta, `reset_disabilitato = 1`;
- funzionari: password conformi alla policy interna (nessuna password da dizionario) → I3;
- 200 contribuenti, ~600 pratiche, ~400 rimborsi, 3 funzionari, 1 amministratore.

---

## 10. Dati di esempio

Generati dal seeder, deterministici (fisso il seme casuale) così che le demo siano ripetibili
e i riferimenti nelle schede non cambino a ogni `lab:reset`.

- codici fiscali **sintatticamente validi** (checksum calcolato), assegnati a nomi inventati;
- numeri di protocollo in stile `2025/0001234`;
- importi con i centesimi, date coerenti con l'anno fiscale;
- nessun nome di persona nota, nessun indirizzo reale.

---

## 11. Logging (pensato per una lezione futura con Splunk o WAF)

`api` scrive su un volume montato `logs/app.jsonl`, una riga JSON per richiesta:

```json
{"ts":"2025-11-03 09:12:44","user":"s.nome","ip":"172.19.0.4","method":"GET","path":"/api/sportello/pratica/118","status":200,"ua":"Mozilla/5.0 ..."}
```

Omissioni **intenzionali**, che rendono il capitolo difesa interessante:

- i **login falliti non sono registrati** (l'evento più utile per ricostruire un attacco);
- nessun identificativo di correlazione: l'attacco non si vede come catena;
- timestamp in **ora locale senza fuso**;
- `user_agent` scritto **senza escape** → log injection.

Il volume è leggibile dall'host (`./logs/`) e ingeribile da Splunk; il formato è documentato
qui perché non cambi quando arriverà il modulo di difesa.

---

## 12. Catalogo dei difetti

Vedi [`CATALOGO_LAB.md`](CATALOGO_LAB.md): 26 schede, ciascuna con voce OWASP, capitolo,
servizio, endpoint previsto, tecnica e requisito di seed.

Le schede individuali (`docs/labs/NN-*.md`) saranno scritte **insieme al codice del relativo
lab**, perché devono citare `file:linea` reali: una scheda con un riferimento inventato è
peggio di nessuna scheda.

---

## 13. Comandi operativi (previsti, M1)

```bash
docker compose up -d            # avvio
docker compose exec api php artisan lab:reset   # ripristino stato pulito
docker compose down -v          # distruzione completa
```

`lab:reset` deve: rifare `migrate:fresh --seed`, svuotare allegati e log, ripristinare i file
"sporchi" (`/.git` finto, `/backup/`). Senza questo comando il laboratorio si usa una volta
sola.

---

## 14. Stato e verifiche

| Elemento | Stato |
|---|---|
| Documentazione (questo file, copione, catalogo, rapporto) | fatto in M0 |
| Stack Docker, schema, seeder | non iniziato (M1) |
| Lab L01–L26 | non iniziati (M2–M6) |
| Verifica di avvio dello stack | **non eseguita** (prevista in M1) |
| Repository remoto | **da definire**: manca l'URL del repository GitHub |

Limiti noti: nessuna parte di questo documento è stata verificata su un sistema in esecuzione.
Le scelte tecniche (in particolare il comportamento multi-istruzione di `PDO::query` su MySQL
8 e il conteggio delle colonne per la UNION) vanno confermate in M1 con una prova reale, non
per assunzione.

---

## 15. Cosa resta fuori dal perimetro

- il **team di difesa** (Splunk, WAF, patch del codice): capitolo successivo, non implementato;
- exploit pronti all'uso e payload: restano al docente;
- CVE reali simulate con dipendenze vecchie: L21 è documentativo, non installa nulla di
  genuinamente vulnerabile;
- deploy pubblico, HTTPS, autenticazione reale, pagamenti reali.
