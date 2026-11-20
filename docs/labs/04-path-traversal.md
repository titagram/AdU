# L04 — Path traversal

**Capitolo:** 3 · **OWASP:** A01 Broken Access Control · **Servizio:** `api`

## Obiettivo

Leggere un file del server che non è un allegato, partendo dall'endpoint degli allegati.

## Dove guardare

`GET /api/pratiche/1/allegato?file=../../../../.env`.

## Cosa si ottiene

Il `.env` dell'API, con `APP_KEY`, la connessione al database e il segreto di firma dei token.
Da lì il passo successivo è firmarsi un token da amministratore — ed è la ragione per cui questo
laboratorio vale più di quanto sembri.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:217` | il nome del file arriva dal client |
| `api/app/Http/Controllers/Api/AreaController.php:228` | `storage_path('app/allegati/'.$id.'/'.$file)` — nessuna normalizzazione |
| `api/app/Http/Controllers/Api/AreaController.php:230` | `is_file()` è l'unico controllo: verifica che il percorso esista, non che sia lecito |

## Trappole

- Il percorso parte da `storage/app/allegati/{id}/`: il numero di `../` dipende dall'id e dalla
  posizione. Quattro livelli portano a `/var/www/html`; chi ne mette tre legge `storage/`.
- `curl` normalizza i `..` nel percorso **prima** di inviarli se stai costruendo l'URL a mano:
  `--path-as-is` serve. Il browser non li normalizza nella query string, quindi l'attacco
  funziona comunque da lì.
- L'allegato legittimo (`ricevuta.pdf`) esiste: provalo prima, per verificare che l'endpoint
  funzioni e che stai solo cambiando il nome.

## Collegamento

È il ponte fra il capitolo 3 e il capitolo 6: senza il `.env` la scalata all'amministratore
richiedeva di indovinare il segreto di firma. Con il `.env` è una firma. La correzione: separare
nome logico e percorso, e conservare gli allegati fuori dalla document root.
