# L07 — Reindirizzamento aperto

**Capitolo:** 2 · **OWASP:** A01 Broken Access Control · **Servizio:** `api` (`/sportello/`)

## Obiettivo

Portare una persona su un sito che non è il nostro, partendo da un collegamento che *è* il
nostro e che il browser mostra come tale.

## Dove guardare

`GET http://<host>:8081/sportello/?ritorno=<indirizzo>`, poi l'accesso.

## Cosa si ottiene

Dopo l'accesso il browser va dove dice il parametro. Non è un'imitazione: il dominio nella barra
degli indirizzi è quello dell'agenzia, il certificato è quello dell'agenzia, e la pagina che si
apre è quella dell'attaccante. Il collegamento si può mandare per email a un contribuente e
reggere qualunque sospetto ragionevole: «apri questo link dell'agenzia e accedi».

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/public/sportello/index.php:105` | legge `?ritorno=` dalla richiesta |
| `api/public/sportello/index.php:110` | lo rimette nell'azione del form |
| `api/public/sportello/index.php:141-142` | lo usa come destinazione di `Location:` senza controllare schema né host |

## Trappole

- Con `//esempio.example` il browser interpreta lo schema dal documento corrente: funziona
  anche restando su `http`. È il caso che i filtri scritti a mano sbagliano più spesso.
- Il parametro non deve essere per forza in `POST`: passa dalla query string perché lo sportello
  lo trascina nel form.
- La pagina di accesso è di un'applicazione diversa dalla SPA: chi cerca la vulnerabilità nel
  portale nuovo non la trova.

## Collegamento

È l'apripista del phishing che serve al capitolo 4: la falsa pagina di accesso che ruba il token
vive sullo stesso dominio dell'agenzia, quindi è credibile. La correzione — accettare solo
percorsi relativi, o una lista di destinazioni — va spiegata insieme alla differenza fra
«validare l'input» e «non fidarsi dell'output».
