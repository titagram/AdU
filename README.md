# Agenzia delle Uscite

Portale fiscale **fittizio** e **deliberatamente vulnerabile**, costruito come materiale
didattico per un corso di sicurezza applicativa su OWASP Top 10.

> ## ⚠️ Attenzione
>
> Questa applicazione contiene vulnerabilità **intenzionali**. Non è un prodotto e non va
> eseguita su una macchina raggiungibile da Internet, non va usata con dati reali e non va
> presentata come un sistema sicuro.
>
> Contiene: SQL injection, XSS (riflesso, stored, DOM), IDOR, CSRF, path traversal, SSRF,
> deserializzazione insicura, credenziali in chiaro in MD5, esposizione del repository git,
> credenziali predefinite, logging insufficiente, e altro ancora.
>
> Tutti i dati sono generati. L'ente, i dipendenti, i contribuenti e il giornale non esistono.
> Nessun logo o identità visiva appartiene a enti reali.

## Stato del progetto

**Capitolo 2 — l'autenticazione.** Password in MD5, login concatenato, enumerazione senza
limiti, sessione fissa, uscita che non chiude, token che non scade, recupero credenziali
prevedibile. Lo sportello del 2019 entra in scena come secondo sistema di autenticazione.

**Capitolo 1 — la ricognizione.** Lo stack a sette servizi funziona, il portale del cittadino
si usa, e la superficie pubblica è quella che il corso fa esplorare per prima: `.git` esposto,
backup del 2019, console di diagnostica, `robots.txt`, CORS aperto, registro degli accessi
incompleto. Il quadro puntuale delle verifiche è in [`docs/DESIGN.md`](docs/DESIGN.md) §14.

| Milestone | Contenuto |
|---|---|
| M0 | documenti di progetto |
| M1 | stack Docker, schema, seeder, portale che si avvia |
| M2–M6 | i laboratori, un blocco per capitolo del corso |

> M1 è nato **corretto**, e i difetti entrano uno per capitolo: il `git log` mostra il commit
> esatto che rompe ogni controllo, e per un corso di sicurezza quel diff vale più di cento
> slide. Il primo è già dentro: il login dice se il codice fiscale esiste (L22), così il
> capitolo sull'autenticazione può cominciare dalla pagina di accesso.

## Come è fatto il corso

Sei capitoli narrativi da due ore: da una segnalazione giornalistica alla compromissione
completa del portale. Il copione è in [`docs/COPIONE.md`](docs/COPIONE.md), il catalogo dei 26
finding in [`docs/CATALOGO_LAB.md`](docs/CATALOGO_LAB.md).

Lo scopo dichiarato agli studenti è far sparire la propria posizione debitoria di € 1.247,00.
Lo scopo reale è capire quanto in profondità si arriva, e dove passa la linea.

## Documenti

| File | Cosa contiene |
|---|---|
| [`docs/DESIGN.md`](docs/DESIGN.md) | architettura, invarianti, superfici, modello dati, logging |
| [`docs/COPIONE.md`](docs/COPIONE.md) | i sei capitoli e l'epilogo |
| [`docs/CATALOGO_LAB.md`](docs/CATALOGO_LAB.md) | le 26 schede, i requisiti di seed, le invarianti |
| [`docs/RAPPORTO_AUDIT.md`](docs/RAPPORTO_AUDIT.md) | modello di rapporto per gli studenti |
| [`docs/docente/GUIDA_SFRUTTAMENTO.md`](docs/docente/GUIDA_SFRUTTAMENTO.md) | guida operativa per il docente — **da non consegnare alla classe** |
| [`docs/artefatti/`](docs/artefatti/) | la mail del direttore, la scheda account, il ritaglio |
| [`.env.example`](.env.example) | dove esporre il laboratorio e quali origini ammette l'API |

## Avvio

```bash
docker compose up -d --build                      # primo avvio: costruisce e popola il database
docker compose exec api php artisan adu:stato     # fotografia dei dati caricati
docker compose exec api php artisan lab:reset     # ripristino dello stato pulito
docker compose down -v                            # distruzione completa, volumi compresi
docker compose --profile test run --rm test       # suite di test (SQLite in memoria)
```

Requisiti: Docker Engine con il **plugin Compose** (`docker compose version` deve rispondere).
Il PHP lo porta l'immagine: sull'host non serve installare nulla. Il primo avvio richiede
qualche minuto, perché costruisce l'immagine e scarica le dipendenze.

Il seed gira una volta sola: se il database è già popolato, `up` non lo tocca. Per tornare allo
stato iniziale c'è `lab:reset`.

### Indirizzi

| Servizio | Percorso | Note |
|---|---|---|
| Portale | porta `8080` | la SPA |
| API | porta `8081` | `GET /api/health` per un controllo rapido |
| Posta | porta `8025` | Mailpit: tutto ciò che il portale spedisce |
| `esca` | porta `8026` | il raccoglitore dell'attaccante, non fa parte del portale |

Il prefisso dipende da `ADU_BIND_IP`: con il valore predefinito è `127.0.0.1`, quindi gli
indirizzi sono `http://127.0.0.1:8080` e così via, e da un'altra macchina non rispondono.

### Aprirlo sulla tailnet

Per usare il portale dal portatile o dal telefono, senza esporlo a Internet, si lega alle porte
all'indirizzo Tailscale di questa macchina:

```bash
tailscale ip -4                    # per esempio 100.75.95.33
cp .env.example .env               # poi modificare le due righe
```

```ini
ADU_BIND_IP=100.75.95.33
ADU_CORS_ORIGINS=http://100.75.95.33:8080
```

```bash
docker compose up -d
```

Da quel momento il portale risponde su `http://<indirizzo-tailscale>:8080` a tutti i
dispositivi della tailnet, e solo a quelli: il traffico viaggia dentro WireGuard, quindi non
serve un certificato. `ADU_CORS_ORIGINS` deve contenere **esattamente** l'indirizzo con cui apri
il portale, altrimenti il browser blocca le risposte dell'API.

Due trappole, entrambe già viste:

- se Tailscale è spento, l'indirizzo non esiste e Docker non riesce a legare le porte: i
  container non partono. `tailscale status` prima di `compose up`;
- se in aula cambia l'indirizzo della macchina, vanno aggiornate entrambe le righe di `.env`.

> **Questa applicazione è deliberatamente vulnerabile.** Sulla tailnet la vedono i tuoi
> dispositivi e nessun altro, ed è il compromesso giusto per una lezione. Non va mai legata a
> un indirizzo pubblico né messa dietro un tunnel aperto: chi la raggiunge può leggere i dati di
> tutti i contribuenti finti e, dopo M2, eseguire codice sul server.

### Accessi

| Persona | Codice fiscale | Password | Ruolo |
|---|---|---|---|
| Giulia Neri | `NREGLI90A41H501G` | `ciao123` | cittadino (account dimostrativo) |
| Mario Rossi | `RSSMRA85M01F205T` | `password` | cittadino (prima riga della tabella) |
| Sara Bianchi | `BNCSRA79C54L219X` | `Bianchi!2025#Sportello` | funzionario |
| Aldo Marchetti | — | `Amministratore!2025#Sicuro` | amministratore |

Le credenziali delle persone inventate sono materiale del corso: i cittadini hanno password
deboli perché devono poter essere indovinate, il personale interno no. La scheda account
consegnata agli studenti è in [`docs/artefatti/01-scheda-account.md`](docs/artefatti/01-scheda-account.md).

## Perché esiste

Perché le applicazioni insicure non nascono da programmatori incompetenti, ma da protezioni
disattivate, controlli commentati e nessuno che paghi qualcuno per guardare. Un laboratorio che
fa toccare con mano questa differenza insegna più di dieci lezioni sui principi.
