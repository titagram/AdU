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

**M1 — il portale si avvia.** Stack Docker a sette servizi, schema a tredici tabelle, seeder
deterministico, API con autenticazione e SPA funzionante. Il quadro puntuale delle verifiche è
in [`docs/DESIGN.md`](docs/DESIGN.md) §14.

| Milestone | Contenuto |
|---|---|
| M0 | documenti di progetto |
| M1 | stack Docker, schema, seeder, portale che si avvia |
| M2–M6 | i laboratori, un blocco per capitolo del corso |

> Il codice di M1 è **corretto**: nessuna vulnerabilità è ancora presente. M2–M6 introducono i
> difetti uno per capitolo, quindi il `git log` del progetto mostra il commit esatto che rompe
> ogni controllo. Per un corso di sicurezza quel diff vale più di cento slide.

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

| Servizio | Indirizzo | Note |
|---|---|---|
| Portale | <http://127.0.0.1:8080> | la SPA |
| API | <http://127.0.0.1:8081> | `GET /api/health` per un controllo rapido |
| Posta | <http://127.0.0.1:8025> | Mailpit: tutto ciò che il portale spedisce |
| `esca` | <http://127.0.0.1:8026> | il raccoglitore dell'attaccante, non fa parte del portale |

Tutti i binding sono su `127.0.0.1`: da un'altra macchina della rete questi indirizzi non
rispondono, ed è voluto.

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
