# L20 — Credenziali predefinite e servizio interno

**Capitolo:** 6 · **OWASP:** A07 Identification and Authentication Failures · **Servizio:** `anagrafe`

## Obiettivo

Entrare nell'anagrafe tributaria, che dal portale non si vede.

## Dove guardare

Il servizio `anagrafe` vive solo sulla rete interna: nessuna porta pubblicata. Si raggiunge con
L25 (SSRF) oppure dall'interno del laboratorio:
`http://admin:admin@anagrafe/admin`.

## Cosa si ottiene

`200` con l'elenco degli endpoint e la versione del servizio. Dalla stessa famiglia di rotte
(`/cittadini`, `/redditi`, `/utenze`) escono i dati di tutti i contribuenti.

Senza credenziali la risposta è `401`, quindi il servizio *non* è aperto: è protetto da una
password che è il nome utente.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `servizi/anagrafe/index.php:29` | `admin`/`admin`, scritti nel corpo del file |
| `servizi/anagrafe/index.php:55` | `/admin` senza rate limit e senza blocco |
| `docker-compose.yml:97` | il servizio è solo su `backend`: raggiungibile dall'API, non da fuori |

## Trappole

- Dal browser l'indirizzo non esiste. Va raggiunto dal container o via SSRF.
- Il Basic auth si può passare nell'URL (`http://admin:admin@host/`): è il modo per farlo
  attraverso un SSRF che non permette header.
- Le credenziali sono le prime che si provano, e funzionano: è esattamente il motivo per cui il
  laboratorio esiste.

## Collegamento

Chiude la catena dei servizi interni: SSRF (L25) + credenziali predefinite (L20) = anagrafe
completa. La correzione è un segreto per servizio, non una password, e un servizio interno non
dovrebbe avere un pannello di amministrazione raggiungibile senza rete controllata.
