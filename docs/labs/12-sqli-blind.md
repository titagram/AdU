# L12 — Iniezione SQL cieca e a tempo

**Capitolo:** 5 · **OWASP:** A03 Injection · **Servizio:** `/sportello/` (pubblico)

## Obiettivo

Estrarre dati da una pagina che non li mostra, usando solo «sì» e «no» — e poi il tempo.

## Dove guardare

`GET /sportello/verifica?protocollo=...`, senza autenticazione.

## Cosa si ottiene

La pagina dice solo se una pratica esiste. Con `2025/004102' AND '1'='1` risponde «risulta
in_lavorazione»; con `2025/004102' AND '1'='2` risponde «Nessuna pratica». Un bit per richiesta:
con `SUBSTRING((SELECT password FROM utenti WHERE id=1),1,1)='5'` si estrae l'hash un carattere
alla volta.

Con `2025/004102' AND SLEEP(3)#` la risposta arriva dopo tre secondi: il canale è il tempo, e
non serve nessuna differenza nel corpo della pagina.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/public/sportello/index.php:238` | il protocollo arriva dalla query string e finisce nella query |
| `api/public/sportello/index.php:240` | l'errore del database torna al browser |

La rotta è **pubblica**: la verifica dello stato di una pratica è un servizio per i contribuenti,
e non richiede sessione.

## Trappole

- `' OR SLEEP(3)#` sembra la variante ovvia e **non funziona come si aspetta**: il `#` cancella il
  `LIMIT 1`, quindi `SLEEP` viene valutato per ogni riga e la richiesta dura mezz'ora. La forma
  corretta è `AND SLEEP(3)`, che si valuta solo sulla riga che corrisponde.
- La condizione booleana va scritta in modo che sia vera o falsa, non «vera e rumorosa»: le
  risposte uguali con tempi diversi sono il segnale.
- Il tempo si misura con `curl -w '%{time_total}'`, non a occhio.

## Collegamento

È la stessa iniezione di L11 senza il lusso di vedere l'output: il laboratorio in cui si capisce
che il canale non è la pagina. E spiega perché il rate limit (L22) conta anche sulle rotte
pubbliche.
