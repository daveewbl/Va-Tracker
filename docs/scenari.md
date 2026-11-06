# Esempi e scenari funzionali

Questi scenari illustrano la [specifica funzionale](funzionamento.md). I dati sono esemplificativi; non ricostruiscono lo storico personale del foglio Excel.

Salvo indicazione diversa, il prezzo di rilevazione coincide con quello di esecuzione, le quote effettive coincidono con quelle proposte e non sono considerate commissioni.

## 1. Tre condizioni principali del VA

Condizioni comuni: incremento mensile di 750 euro, obiettivo cumulativo del mese di 3.000 euro e 20 quote già possedute. Le righe rappresentano scenari alternativi, non mesi consecutivi.

| Prezzo | Valore prima dell'acquisto | Importo teorico VA | Quote proposte | Controvalore | Incremento liquidità virtuale | Indicazione |
| ---: | ---: | ---: | ---: | ---: | ---: | --- |
| 130,00 € | 2.600,00 € | 400,00 € | 3 | 390,00 € | 0,00 € | Nel range |
| 160,00 € | 3.200,00 € | −200,00 € | 0 | 0,00 € | 200,00 € | Liquidità virtuale |
| 100,00 € | 2.000,00 € | 1.000,00 € | 10 | 1.000,00 € | 0,00 € | Superamento consentito |

Nello scenario negativo restano 20 quote: non viene effettuata una vendita. I 200 euro non vengono sommati ai 3.200 euro per ottenere un patrimonio di 3.400 euro.

Nello scenario positivo da 390 euro non si aggiungono 360 euro al contatore come differenza rispetto all'incremento mensile di 750 euro.

## 2. Primo investimento

| Dato | Valore |
| --- | ---: |
| Obiettivo iniziale | 0,00 € |
| Incremento mensile | 750,00 € |
| Obiettivo del primo mese | 750,00 € |
| Quote già possedute | 0 |
| Prezzo | 93,53 € |
| Valore dei titoli prima dell'acquisto | 0,00 € |
| Importo teorico VA | 750,00 € |
| Quote proposte | 8 |
| Controvalore proposto | 748,24 € |
| Quote dopo l'acquisto | 8 |
| Incremento liquidità virtuale | 0,00 € |

Il primo investimento segue la stessa formula dei mesi successivi. Il valore del portafoglio iniziale uguale a zero non deve annullare l'importo teorico da investire.

## 3. Arrotondamento e soglie

### Acquisto finale sopra soglia per arrotondamento

Si consideri un primo mese con obiettivo e incremento di 750 euro, zero quote e prezzo di 100 euro:

```text
Importo teorico = 750 euro
Quote proposte = arrotonda(7,5) = 8
Controvalore proposto = 800 euro
```

La segnalazione principale è "nel range", perché l'importo teorico coincide con l'incremento. Una nota evidenzia i 50 euro di superamento nel controvalore proposto. L'acquisto è consentito.

### Zero esatto

Con obiettivo di 3.000 euro, 30 quote possedute e prezzo di 100 euro, l'importo teorico è zero. Il caso rientra nel range ordinario: zero quote proposte, nessun incremento della liquidità virtuale e nessuno stato dedicato allo zero.

### Importo positivo inferiore a un euro

Con obiettivo di 3.000 euro, 30 quote possedute e prezzo di 99,99 euro, il valore dei titoli è 2.999,70 euro. L'importo teorico è 0,30 euro e rientra nel range. L'arrotondamento produce zero quote; la liquidità virtuale non cresce.

## 4. Calendario e mesi mancanti

PAC nuovo da zero, con partenza a settembre 2026 e incremento mensile di 750 euro:

| Periodo | Obiettivo | Rilevazione |
| --- | ---: | --- |
| Settembre 2026 | 750,00 € | Registrata |
| Ottobre 2026 | 1.500,00 € | Non rilevata |
| Novembre 2026 | 2.250,00 € | Da registrare |

L'obiettivo di novembre è 2.250 euro, anche se nello storico esiste una sola registrazione precedente. Il mese di ottobre non genera incrementi automatici della liquidità virtuale.

Una seconda registrazione nello stesso mese e nello stesso PAC non è ammessa.

## 5. Situazione iniziale di un PAC esistente

Un PAC è partito da zero a gennaio 2026, con incremento mensile costante di 750 euro. Si inserisce un saldo di apertura riferito alla situazione dopo il dodicesimo periodo, dicembre 2026.

L'obiettivo cumulativo a quella data è `12 × 750 = 9.000 euro`, calcolato automaticamente. Quote, capitale investito e liquidità virtuale di apertura descrivono la situazione esistente e non generano un nuovo acquisto.

La prima registrazione successiva, gennaio 2027, usa un obiettivo di 9.750 euro. Il valore di dicembre non viene trattato come obiettivo di apertura precedente al dodicesimo mese, evitando un incremento aggiuntivo nello stesso periodo.

## 6. Prosecuzione con un nuovo incremento

Alla chiusura di dicembre 2026, il PAC precedente ha:

| Dato | Valore |
| --- | ---: |
| Quote possedute | 100 |
| Capitale investito | 8.500,00 € |
| Obiettivo cumulativo | 9.000,00 € |
| Liquidità virtuale | 200,00 € |

Si crea il successore con incremento mensile di 1.000 euro da gennaio 2027.

- Il nuovo PAC apre con gli stessi saldi.
- L'obiettivo di gennaio è 10.000 euro.
- Il precedente rimane consultabile e interamente in sola lettura.
- Prima di nuovi acquisti, la panoramica corrente conta 100 quote, 8.500 euro di capitale investito e 200 euro di liquidità virtuale, senza duplicare i saldi tra predecessore e successore.
- I 200 euro restano separati dal patrimonio effettivo.

## 7. Modifica dell'ultima registrazione

Il contatore prima dell'ultimo mese è di 100 euro. L'ultima registrazione contiene un importo teorico di −200 euro: il saldo diventa 300 euro.

L'utente corregge l'ultima registrazione e l'importo teorico risulta −150 euro. Il nuovo saldo è:

```text
100 + 150 = 250 euro
```

Non è `300 + 150 = 450 euro`: il contributo precedente del mese viene sostituito.

Le registrazioni precedenti non sono modificabili dall'app. Dopo la chiusura del PAC per crearne il successore, anche l'ultima registrazione è bloccata.

## 8. Aggiornamento del solo prezzo

Una posizione ha 20 quote e ultimo prezzo registrato di 130 euro: il valore indicativo è 2.600 euro.

Aggiornando soltanto il prezzo a 140 euro, il valore indicativo diventa 2.800 euro. Quote, capitale investito, registrazioni mensili e liquidità virtuale rimangono invariati. La vista mostra la data del nuovo prezzo.
