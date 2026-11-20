# L23 — Deserializzazione insicura e codice valutato

**Capitolo:** 3 · **OWASP:** A08 Software and Data Integrity Failures · **Servizio:** `api`

## Obiettivo

Far costruire al server un oggetto che non esiste nel suo codice, e far eseguire al server una
condizione che non è un filtro.

## Dove guardare

`POST /api/profilo/preferenze` e `POST /api/pratiche/importa`.

## Cosa si ottiene

**Preferenze.** Il campo `preferenze` è un blob PHP serializzato in base64, riletto con
`unserialize` senza lista di classi ammesse. Un oggetto `stdClass` costruito a mano arriva
intatto nel database. Con una classe dell'applicazione fra i modelli disponibili, il passo
successivo è un magic method, e da lì l'esecuzione.

**Import.** La `condizione` di filtro è PHP, valutato con `eval`. Passando `true` si importano
tutte le pratiche; passando `1); ...` si esce dal contesto e si esegue altro. Il laboratorio si
ferma a mostrare che il codice valutato esiste e risponde: l'esecuzione arbitraria la decide
chi insegna, non la scheda.

## Nel codice

| Riga | Cosa fa |
|---|---|
| `api/app/Http/Controllers/Api/AreaController.php:152` | `unserialize(base64_decode(...))` su input del client |
| `api/app/Http/Controllers/Api/AreaController.php:162` | il risultato viene salvato così com'è in `utenti.preferenze` |
| `api/app/Http/Controllers/Api/AreaController.php:172` | la `condizione` arriva dalla richiesta |
| `api/app/Http/Controllers/Api/AreaController.php:183` | `eval('return (bool) ('.$condizione.');')` |

## Trappole

- Il payload deve essere base64 valido, e il PHP serializzato ha una forma rigida: un carattere
  sbagliato nella lunghezza della stringa e `unserialize` restituisce `false` con un errore che
  sembra un problema di rete.
- `eval` restituisce il valore del `return`: una condizione che non ritorna un booleano non
  filtra niente e non dà errore.
- Il finto `json` del campo `preferenze` non è JSON: è una stringa. Chi prova a mandare un
  oggetto JSON riceve `422`.

## Collegamento

È il laboratorio che rompe l'idea che «i dati sono inerti». La correzione: JSON con schema
esplicito, mai `unserialize` su input esterno; e nessun `eval`, in nessun caso, perché la
condizione di filtro si esprime con un piccolo linguaggio o con parametri, non con PHP.
