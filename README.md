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

**M0 — documentazione.** Non c'è ancora codice applicativo: vedi
[`docs/DESIGN.md`](docs/DESIGN.md) §14 per lo stato puntuale e i limiti.

| Milestone | Contenuto |
|---|---|
| M0 | documenti di progetto (questa milestone) |
| M1 | stack Docker, schema, seeder, portale che si avvia |
| M2–M6 | i laboratori, un blocco per capitolo del corso |

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
| [`docs/artefatti/`](docs/artefatti/) | la mail del direttore, la scheda account, il ritaglio |

## Avvio (previsto in M1)

```bash
docker compose up -d                              # portale su http://127.0.0.1:8080
docker compose exec api php artisan lab:reset     # ripristino dello stato pulito
docker compose down -v                            # distruzione completa
```

Requisiti: Docker Engine con il plugin Compose. Il PHP lo porta l'immagine: sull'host non serve
installare nulla.

## Perché esiste

Perché le applicazioni insicure non nascono da programmatori incompetenti, ma da protezioni
disattivate, controlli commentati e nessuno che paghi qualcuno per guardare. Un laboratorio che
fa toccare con mano questa differenza insegna più di dieci lezioni sui principi.
