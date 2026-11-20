# L03 — Forced browsing

**Capitolo:** 3 · **OWASP:** A01 Broken Access Control · **Servizio:** `api`

## Obiettivo

Scaricare l'elenco completo dei contribuenti dell'agenzia.

## Dove guardare

`GET /api/admin/export` con il token di un cittadino qualsiasi.

## Cosa si ottiene

Un CSV con codice fiscale, nome, cognome, email e ruolo di **tutti** gli assistiti. Duecentoquattro
righe, nel database del laboratorio.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/routes/api.php:41` | la rotta è dentro il gruppo `auth.jwt`, e nient'altro |
| `api/app/Http/Controllers/Api/AreaController.php:196` | la query non filtra per utente: è un export pensato per gli uffici |

La rotta si chiama `/admin/...`: il nome suggerisce un controllo che non c'è. Il middleware
verifica che tu sia qualcuno, non che tu sia qualcuno con un ruolo.

## Trappole

- Senza token la risposta è `401`: il laboratorio sembra protetto. Il controllo è di
  autenticazione, non di autorizzazione, e la differenza è tutta qui.
- Il CSV si apre con un foglio di calcolo: attenzione alle formule, il file contiene dati di
  persone reali in un sistema vero.
- Il ruolo compare nel file: si scopre chi è funzionario e chi amministratore, cioè il bersaglio
  del capitolo 4.

## Collegamento

Chiude il primo giro di raccolta: L01 legge un fascicolo, L03 li legge tutti. Ed è il dato che
serve al capitolo 6 per scegliere la vittima della scalata.
