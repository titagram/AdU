# L11 — Iniezione SQL con `UNION`

**Capitolo:** 5 · **OWASP:** A03 Injection · **Servizio:** `/sportello/`

## Obiettivo

Leggere tutta la tabella `utenti` — hash compresi — da una pagina che mostra pratiche.

## Dove guardare

`GET /sportello/pratiche?cf=...` e `?sort=...`, con la sessione dello sportello.

## Cosa si ottiene

Con sei colonne, l'elenco delle proprie pratiche diventa l'elenco dei contribuenti:
`?cf=' UNION SELECT codice_fiscale,email,password,'2025-01-01',ruolo,1 FROM utenti LIMIT 3#`
restituisce codice fiscale, email e hash MD5 di ognuno.

Con `?sort=extractvalue(1,concat(0x7e,(SELECT password FROM utenti WHERE id=1)))#` il messaggio
di errore del database porta con sé il valore: estrazione senza vedere la tabella.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/public/sportello/index.php:202` | `cf` e `sort` finiscono nella query per concatenazione, su una sola riga |
| `api/public/sportello/index.php:206` | in caso di errore il messaggio del database torna al browser |

Le colonne sono sei perché la pagina mostra protocollo, servizio, stato, data, codice fiscale e
id: è il numero che l'UNION deve rispettare, e si scopre provando `ORDER BY 1..7`.

## Trappole

- **`#` commenta solo fino a fine riga.** Se la query è su più righe, `ORDER BY` e `LIMIT`
  sopravvivono e l'UNION va in errore di sintassi. In questo sportello la query è su una riga
  sola, quindi `#` basta; altrove serve `/*`.
- I tipi devono essere compatibili: la quarta colonna è una data, quindi `'2025-01-01'` e non
  `ruolo`. Un tipo sbagliato dà un errore che sembra un payload sbagliato.
- Il numero di colonne si trova con `ORDER BY N`: alla prima che fallisce, hai il numero giusto.
- L'error-based con `extractvalue` è deprecato ma presente in MySQL 8: se sparisce, si usa la
  tecnica con `COUNT(*)` e `FLOOR(RAND(0)*2)`.

## Collegamento

È la porta d'ingresso ai dati di tutti, e la ragione per cui L08 conta: gli hash escono da qui
già pronti per il dizionario. La correzione è un prepared statement, non un filtro sulla stringa.
