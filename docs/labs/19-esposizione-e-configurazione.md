# L19 — Esposizione e configurazione

**Capitolo:** 1 · **OWASP:** A05 Security Misconfiguration · **Servizio:** `api`

## Obiettivo

Leggere il portale che l'ente ha messo in esercizio: il suo sorgente, la sua storia, le sue
query e i dati personali che ci passano — senza avere nessun account.

## Dove guardare

Il servizio `api` sulla porta `8081`. Non la SPA: il portale da solo non lascia vedere niente.

## Cosa si ottiene

Il sorgente completo dell'applicazione e cinque commit di storia. Fra questi, uno che ha
disattivato il limite ai tentativi di accesso «per il collaudo» ed è rimasto in esercizio per
cinque mesi e mezzo. Più un dump del 2019 con gli hash delle password, la console che mostra le
query appena eseguite (quindi i codici fiscali di chi ha fatto accesso), una pagina con le
librerie dichiarate e un CORS aperto a qualunque origine.

Per chi insegna, il valore non è l'elenco: è che la prima domanda del corso — *chi ha consultato
i dati dei rimborsi?* — trova qui la sua prima risposta parziale, e non è una risposta buona.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/docker/entrypoint.sh:10-15` | copia `.git`, `backup/` e `robots.txt` dentro `public/`, cioè nel sito che Apache serve |
| `api/routes/web.php:18` | `GET /debug`, console di diagnostica senza autenticazione |
| `api/routes/web.php:20` | `GET /admin/log`, registro degli accessi **senza** controllo di ruolo |
| `api/app/Http/Controllers/DiagnosticaController.php:34` | il parametro `?sql=` finisce in `DB::select()` senza filtri |
| `api/app/Http/Middleware/RegistraQuery.php:11` | ogni query dell'API viene annotata su file, valori compresi |
| `api/config/adu.php:15` | le origini ammesse per CORS sono `*` |
| `api/.env.example:6` | `APP_DEBUG=true`: le eccezioni escono con stack trace e percorsi assoluti |

Il `.git` non è un file inventato: è un repository vero, costruito da
`api/risorse/genera-storia.py`, con l'albero dei sorgenti dell'applicazione. Serve a rendere il
colpo di scena del capitolo 1 verificabile invece che raccontato.

## Trappole

- Il clone con `git clone` **non** funziona: senza `git-http-backend` il server non parla il
  protocollo smart. Gli oggetti sono però lì, e si leggono a mano — è il momento in cui si
  capisce come è fatto un repository.
- `/.git/index` è binario e non serve: bastano `HEAD`, `refs/heads/main` e `objects/`.
- `/backup/` risponde con l'elenco solo perché `backup/.htaccess` attiva `Options +Indexes`.
  Senza quello il dump ci sarebbe lo stesso, ma non si troverebbe.
- La console `/debug` mostra le query **della richiesta in corso** più le ultime annotate:
  per vedere un codice fiscale bisogna che qualcuno abbia fatto accesso prima.

## Collegamento

Alimenta quasi tutto il resto. L'enumerazione (L22) parte dal sorgente letto qui; le credenziali
del dump (L08) si provano altrove; il registro incompleto (L24) è la ragione per cui, dopo il
furto dei dati, non si saprà chi è entrato. Il commento trovato nella storia di git spiega
perché il limitatore (L22) è stato spento: ed è la prova che l'ente lo sapeva.
