# Il copione — sei capitoli

> Come si legge questo documento: le righe in *corsivo* sono da leggere ad alta voce e durano
> circa venti secondi. Ogni capitolo si apre su un artefatto e si chiude su una soglia.
> Il registro è quello di un fascicolo d'inchiesta: il docente è il consulente ingaggiato
> dall'ente, la classe è il team.

Destinatari: programmatori senior. I difetti che contano qui sono di **contesto**, non di
sintassi: riuso sbagliato, invarianti violate, fiducia mal riposta.

---

## Prologo — la mail del Direttore

*Artefatto in [`artefatti/00-mail-direttore.md`](artefatti/00-mail-direttore.md), consegnato
prima del capitolo 1.*

Il 3 novembre un giornalista locale pubblica un articolo con una ventina di nominativi e
altrettanti importi: «li ho trovati in un link pubblico, senza fare nulla di illegale».
Il direttore chiede una relazione entro trenta giorni. Le credenziali di prova arriveranno
"a parte".

Non arriveranno. Tranne una: la scheda dell'account dimostrativo, che è l'altro artefatto.

---

## Capitolo 1 — *"C'è qualcosa che non va"*

> *"Cominciamo da quello che vede uno che non ha nessun permesso. Nessuna password, nessun
> account, solo l'indirizzo del portale. Se bastasse questo per entrare, non staremmo qui a
> parlare del giornalista."*

**Artefatto:** l'URL del portale e la scheda account (non ancora usata).

**Cosa emerge** (L19, L21, L24): `.git` esposto — si legge il sorgente; `/backup/` con un dump
del 2019 e directory listing; stack trace in chiaro;
`robots.txt` che elenca `/admin`, `/backup/`, `/sportello/`; CORS `*`; pagina delle
dipendenze con librerie obsolete.

La console SQL `/debug` resta spenta in questa fase: si abilita con
`ADU_DEBUG_CONSOLE_ENABLED=true` solo nella lezione sugli hash del database in esercizio.

**Il colpo di scena:** nella storia di git c'è un commit con un commento lasciato lì:
`// rate limit disattivato per il collaudo, riattivare prima della produzione`. Non è un bug,
è un debito. Lo trovano ora e non sanno ancora cosa farsene.

**La domanda che resta:** *chi ha consultato i dati dei rimborsi?* L'ente non lo sa, perché i
login falliti non vengono registrati. È il primo finding che non si aggiusta con una patch.

---

## Capitolo 2 — *"Chi sei davvero"*

> *"L'helpdesk ha aperto un ticket: troppi tentativi di accesso falliti in una notte. È il tipo
> di segnale che si nota solo se qualcuno li registra. Noi li registriamo da stamattina."*

**Artefatto:** si usa finalmente la scheda account — *Giulia Neri*, con la sua posizione
debitoria.

**Cosa emerge** (L22, L10, L08, L09, L17, L07): il login distingue *"codice fiscale non presente in
archivio"* da *"password non corretta"*, quindi si enumera; il rate limit è quello commentato
al capitolo 1; le password dei cittadini sono deboli perché nessuna policy le governa; la
query del login concatena la stringa, e `' OR 1=1-- ` entra come **Mario Rossi** — una persona
che lo studente non conosce, e la cui cartella vede solo perché ha sbagliato la password.
Password conservate in MD5. Token in `localStorage`, senza scadenza, non revocato dal logout.
Recupero password con token prevedibile.

**Il colpo di scena:** il token di Mario non scade e non si invalida. Non serve niente di
sofisticato: serve che qualcuno lo legga. La classe chiude il capitolo con un token in mano e
la sensazione che sia troppo facile — che è il momento giusto per chiedersi *di chi altro*.

---

## Capitolo 3 — *"Quello che non dovresti vedere"*

> *"Il giornalista dice di aver aperto un link pubblico. Non ha violato niente: ha cambiato un
> numero. La differenza tra un attacco e un errore di battitura, in questo portale, è un
> numero."*

**Artefatto:** lo screenshot dell'articolo (in `artefatti/`), con i nomi oscurati.

**Cosa emerge** (L01, L02, L03, L04, L05, L23, L26): IDOR in lettura su cartelle, pratiche,
rimborsi e PDF; IDOR **in scrittura** sulla cartella (che è la prima tappa verso l'obiettivo
dichiarato); forced browsing su `GET /api/admin/export`, che restituisce tutti i contribuenti;
path traversal sugli allegati; mass assignment che accetta `codice_fiscale` e permette di
indossare un'altra identità; le preferenze del profilo deserializzate senza controllo; un
upload che si fida del `Content-Type` dichiarato dal browser.

**Il colpo di scena:** una delle cartelle che aprono appartiene a **Sara Bianchi**, funzionario
interno, e contiene un allegato marcato *riservato — istruttoria in corso*. Da "qualcuno ha
sbagliato" si passa a "qui c'è un problema penale".

**Il muro:** Sara non si impersona con una password, perché il suo account è interno e ha una
policy vera. Serve un altro modo.

---

## Capitolo 4 — *"Il ponte"*

> *"Sara apre le sue pratiche ogni mattina alle nove e dodici. Non è un dettaglio: è la ragione
> per cui un attacco che ha bisogno di lei può funzionare."*

**Artefatto:** la finestra in incognito — la postazione di Sara.

**Cosa emerge** (L14, L15, L16): XSS **riflesso** nel modulo "Scrivi all'ufficio", che non
richiede nemmeno un account; XSS **stored** nelle note della pratica; XSS **DOM** nel parametro
`?torna=`, visibile solo leggendo il JavaScript della SPA.

**Il colpo di scena:** il payload non fa danni visibili. Spedisce il token di Sara a un
servizio che non dovrebbe esistere, e quel servizio lo mostra in chiaro. La classe guarda il
token di un dipendente pubblico comparire sullo schermo.

**La soglia:** *"Fino a un minuto fa stavate leggendo dati sbagliati. Adesso state usando
l'identità di una persona. In un sistema vero questa è la linea, e sta scritta nell'articolo
615-ter."*

---

## Capitolo 5 — *"Parlare al database"*

> *"Ora avete i permessi. E i permessi, in un portale che nessuno ha mai guardato, sono la
> chiave di tutto il resto."*

**Artefatto:** nessuno: si lavora con il token di Sara.

**Cosa emerge** (L11, L12, L13): ricerca pratiche con **UNION** (sei colonne); iniezione
sull'identificatore di ordinamento, non parametrizzabile, che espone gli errori; **blind
boolean** e **time-based** sull'endpoint pubblico di verifica protocolli; command injection sul
generatore di PDF.

**Il colpo di scena:** il dump non contiene solo i cittadini. Contiene **la riga
dell'amministratore** — password robusta, recupero disabilitato, ultimo accesso nel 2022. La
classe capisce perché l'unico account ben protetto è quello che protegge tutti gli altri. E
capisce l'altra cosa: che l'amministratore è raggiungibile **senza conoscerne la password**.

---

## Capitolo 6 — *"I soldi e il perimetro"*

> *"Un ente pubblico non muore per una query. Muore quando il rimborso di qualcuno finisce sul
> conto di qualcun altro."*

**Cosa emerge** (L05, L06, L18, L25, L20): il `$fillable` condiviso che permette a un
funzionario di promuoversi ad amministratore; il cambio IBAN senza token CSRF, che sposta il
rimborso con un form nascosto; il race condition sul doppio rimborso; e infine la **SSRF** che
raggiunge l'anagrafe interna, dove c'è `admin/admin` e soprattutto **l'archivio vero**: il
portale è solo una copia.

**Il colpo di scena finale:** il servizio interno era protetto dall'esterno, non dagli
attacchi. *"Non era esposto su Internet. Era esposto a voi."*

**Il debrief:** il direttore chiede cosa fare. La classe ordina i finding per impatto
economico e legislativo, e scopre che i due più cari non sono quelli tecnici: sono il rate
limit commentato e il log che non c'è.

**Ultima battuta:** *"Il portale non era insicuro perché qualcuno era incompetente. Era
insicuro perché nessuno aveva mai pagato qualcuno per guardarlo."*

---

## Epilogo — il verdetto

Se la classe arriva in fondo, si apre `/epilogo` (accessibile dal funzionario in su, non
prima). La pagina legge lo stato del database e stampa un allineamento in stile D&D.

| Cosa hanno fatto | Allineamento |
|---|---|
| niente, solo guardare | Legale neutro |
| leggere i dati di altri contribuenti | Neutrale |
| azzerare la propria cartella | Caotico neutrale |
| cancellarsi dal database | Caotico neutrale — *variante "sparizione"* |
| svuotare l'archivio | Caotico malvagio |

Gli assi non sono un vezzo: **Legge ↔ Caos** misura se hanno rispettato le regole del
sistema, **Bene ↔ Male** misura se il danno ricade su di loro o su persone che non
c'entrano. È un modo travestito di insegnare il raggio d'azione, cioè la cosa che un rapporto
di audit deve quantificare.

Il verdetto si calcola **dal database**, non da un flag del browser: è l'unica cosa che non
possono falsificare, perché è l'unica che legge i fatti.

---

## Materiali

| File | Quando |
|---|---|
| `artefatti/00-mail-direttore.md` | prima del capitolo 1 |
| `artefatti/01-scheda-account.md` | prima del capitolo 2 |
| `artefatti/02-ritaglio-giornale.md` | capitolo 3 |
| `RAPPORTO_AUDIT.md` | da compilare, una copia per squadra o studente |
| `labs/NN-*.md` | consegnate capitolo per capitolo (scritte in M2–M6) |
