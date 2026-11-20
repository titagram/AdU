# Rapporto di audit — modello da compilare

> Una copia per squadra. Non è un esercizio di stile: è il documento che il direttore
> dell'ente userà per decidere cosa finanziare e cosa rimandare. Un finding che non si può
> riprodurre non vale niente, e un finding senza impatto non si può ordinare.

## Intestazione

| | |
|---|---|
| Squadra / studente | |
| Data | |
| Portale analizzato | Agenzia delle Uscite — `http://127.0.0.1:8080` |
| Perimetro | tutto ciò che è raggiungibile senza credenziali, più l'account assegnato |
| Account iniziale | *(il codice fiscale della scheda)* |

## Scheda del finding

Compilare una scheda per ogni finding. Il numero è progressivo e non ha relazione con `L01…L26`.

---

### F\<nn\> — \<titolo in una riga\>

| Campo | Contenuto |
|---|---|
| **Componente** | es. `api` · `GET /api/cartelle/{id}` |
| **Categoria OWASP** | es. A01 Broken Access Control |
| **Gravità** | critica / alta / media / bassa (vedi scala sotto) |
| **Riproduzione** | i passaggi minimi, numerati, che chiunque può ripetere |
| **Prova** | cosa si vede: risposta, schermata, riga di log (niente screenshot di dati altrui più di quanto serve) |
| **Impatto** | cosa può fare concretamente chi sfrutta il difetto, e su quante persone |
| **Causa** | la riga o il ragionamento sbagliato — non "manca il controllo", ma *perché* manca |
| **Rimedio** | la correzione minima che chiude il finding |
| **Stato** | aperto / accettato / correggibile in \<n\> ore |

## Scala di gravità

Non serve CVSS. Serve che due studenti diano lo stesso punteggio allo stesso finding.

| Gravità | Criterio | Esempio dal catalogo |
|---|---|---|
| **Critica** | porta a privilegi superiori, o espone dati di tutti, o consente scritture | L05 (escalation), L11 (dump), L19 (`.git`) |
| **Alta** | espone dati personali di terzi, o consente azioni a nome altrui | L01, L02, L15, L06 |
| **Media** | richiede interazione della vittima, o espone informazioni utili ad attaccare | L14, L16, L24, L12 |
| **Bassa** | igiene, difese in profondità, informazioni senza impatto diretto | L07, L21, L26 |

Regola pratica: **se il danno ricade su chi non ha fatto nulla, la gravità sale di un
gradino.** È lo stesso principio che regge l'epilogo.

## Sintesi finale

| | |
|---|---|
| Finding totali | |
| Critici | |
| Tempo complessivo stimato per chiudere i critici | |
| Il finding più costoso *economicamente* (non il più grave) | |
| Il finding più costoso *in termini di reputazione* | |
| Cosa rifaremmo diversamente nel modo in cui abbiamo lavorato | |

## Domande a cui il rapporto deve rispondere

1. Il giornalista dell'articolo poteva ottenere quei dati? Attraverso quale finding?
2. Quanti dei 26 difetti richiedono **un account** e quanti no? Perché la differenza conta per
   chi deve mettere in sicurezza il sistema?
3. Se doveste chiudere tre soli finding con il budget di una settimana, quali scegliereste, e
   cosa lascereste aperto?
4. Qual è il difetto che **non** è un errore di programmazione? (Suggerimento: capitolo 1.)
