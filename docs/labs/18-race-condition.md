# L18 — Race condition sul rimborso

**Capitolo:** 6 · **OWASP:** A04 Insecure Design · **Servizio:** `api`

## Obiettivo

Farsi pagare tre volte lo stesso rimborso.

## Dove guardare

`POST /api/rimborsi/{id}/richiedi` con il token del proprietario.

## Cosa si ottiene

Tre richieste di pagamento per un solo rimborso: la risposta dice `"richiesta": 3`. Il controllo
«lo stato è `approvato`?» e la scrittura non sono atomici: tre richieste simultanee leggono tutte
lo stesso stato e passano tutte.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/RimborsoController.php:26` | `richiedi()`: legge, controlla, scrive, in tre passi separati |
| `api/app/Http/Controllers/Api/RimborsoController.php:37` | il controllo sullo stato: qui due richieste leggono lo stesso `approvato` |
| `api/app/Http/Controllers/Api/RimborsoController.php:42` | `usleep(200000)` apre la finestra: senza, la corsa è difficile da vincere a mano |
| `api/app/Http/Controllers/Api/RimborsoController.php:44` | l'inserimento in `richieste_pagamento`, senza vincolo di unicità |
| `api/database/migrations/2025_11_04_000001_create_richieste_pagamento.php` | la tabella che conta i danni |

## Trappole

- Le richieste vanno lanciate **insieme**: con `curl` in sequenza la seconda trova lo stato
  `richiesto` e risponde `409`. Serve un ciclo di `&` e `wait`.
- Il rimborso deve essere in stato `approvato`: dopo il primo giro diventa `richiesto`, quindi
  serve `lab:reset` per ripetere.
- Tre richieste sono tre pagamenti: il danno è reale, non una nota nel log.

## Collegamento

È il laboratorio che sposta l'attenzione dal «cosa manca» al «quando succede». La correzione non
è un `if` in più: è una transazione con lock, o un vincolo di unicità su `rimborso_id`.
