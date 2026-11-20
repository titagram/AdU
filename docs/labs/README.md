# Schede dei laboratori

Una scheda per finding, numerata come in [`../CATALOGO_LAB.md`](../CATALOGO_LAB.md):

```
labs/01-idor-lettura.md
labs/02-idor-scrittura.md
...
labs/26-upload.md
```

## Quando vengono scritte

Non ora. Ogni scheda cita il **file e la riga** del codice che contiene il difetto, quindi
nasce insieme al codice del laboratorio (M2–M6). Una scheda con un riferimento inventato è
peggio di nessuna scheda: manda lo studente a cercare in un punto che non esiste.

## Struttura della scheda

| Sezione | Contenuto |
|---|---|
| Obiettivo | in una riga, cosa deve ottenere chi attacca |
| Dove guardare | il componente e l'endpoint, senza rivelare la tecnica |
| Cosa si ottiene | l'impatto concreto, in linguaggio di business |
| Nel codice | `file:linea` reale, con il perché di quella scelta |
| Trappole | gli errori tipici che fanno perdere tempo |
| Collegamento | quali altri finding alimenta o da quali dipende |

Niente suggerimenti progressivi: la classe è di programmatori senior e i passaggi operativi
restano al docente. Niente soluzione: il rimedio si spiega a voce, come deciso per il corso.
