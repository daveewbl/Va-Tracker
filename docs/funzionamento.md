# Funzionamento di VA Tracker

Stato: specifica funzionale iniziale concordata nel brainstorming del 6 novembre 2026.

Le decisioni confermate sono descritte nelle sezioni principali. I dettagli che richiedono ancora una scelta sono raccolti alla fine. Il documento non sceglie uno stack tecnologico e non descrive un'applicazione già implementata.

## 1. Scopo

VA Tracker è una dashboard personale per seguire uno o più PAC in value averaging. Ogni PAC ha un titolo, un incremento mensile fisso, un obiettivo cumulativo e uno storico delle registrazioni concluse.

Prima del salvataggio l'utente vede i calcoli proposti. Una registrazione salvata rappresenta un investimento effettivamente effettuato oppure una rilevazione mensile conclusa con zero acquisti. L'anteprima non è un'operazione effettuata e non modifica i saldi.

Non sono previste vendite. Le indicazioni sul range dell'investimento sono informative: un superamento non limita l'acquisto.

## 2. Terminologia

| Termine | Significato |
| --- | --- |
| Incremento mensile | Incremento fisso dell'obiettivo a ogni mese del piano; è anche la soglia per segnalare un investimento superiore al previsto. |
| Obiettivo cumulativo | Valore che i titoli dovrebbero raggiungere secondo il piano alla fine del periodo, dopo l'eventuale acquisto. Non è il capitale versato né un guadagno. |
| Valore dei titoli prima dell'acquisto | Quote già possedute moltiplicate per il prezzo inserito nella rilevazione. |
| Importo teorico VA | Differenza fra obiettivo cumulativo del mese e valore dei titoli prima dell'acquisto. Può essere negativo. |
| Quote proposte | Quantità intera risultante dal calcolo VA, con minimo zero. |
| Quote effettive | Quantità realmente acquistata e confermata dall'utente. Determina le quote possedute nei periodi successivi. |
| Controvalore dell'acquisto | Quote effettive moltiplicate per il prezzo unitario dell'acquisto. |
| Liquidità virtuale | Contatore cumulativo degli importi teorici VA negativi registrati. Non è denaro disponibile. |
| Periodo | Mese e anno a cui si riferisce una registrazione. |

Il termine ambiguo "target" viene quindi distinto in **incremento mensile** e **obiettivo cumulativo**.

## 3. Calendario e obiettivi

È ammessa una sola registrazione per mese per ciascun PAC.

L'obiettivo segue il calendario, indipendentemente dal numero di registrazioni salvate. Saltare un mese non interrompe la crescita dell'obiettivo e non sposta la numerazione dei periodi successivi.

Per un PAC con incremento costante:

```text
T(n) = T0 + n × I
```

Dove:

- `T(n)` è l'obiettivo cumulativo del periodo `n`.
- `T0` è l'obiettivo cumulativo di apertura, prima del primo periodo del piano.
- `I` è l'incremento mensile fisso del PAC.
- `n` è il numero di periodi mensili trascorsi dalla sua apertura, con primo periodo uguale a 1.

Per un nuovo PAC da zero, `T0 = 0`. L'anno deve essere noto: il solo nome del mese non identifica un periodo.

### Mese non rilevato e mese con zero acquisti

- Un mese senza registrazione rimane **non rilevato**. Non vengono inventati prezzi, acquisti o incrementi della liquidità virtuale.
- Una registrazione con zero quote è invece una rilevazione mensile conclusa. Se il suo importo teorico VA è negativo, incrementa la liquidità virtuale.

L'avanzamento dell'obiettivo per calendario non crea automaticamente registrazioni.

## 4. Calcolo della registrazione mensile

Definizioni:

- `Qprima`: quote effettivamente possedute prima dell'operazione, inclusa l'eventuale posizione di apertura.
- `P`: prezzo unitario inserito dall'utente per il calcolo.
- `T`: obiettivo cumulativo del mese selezionato.
- `D`: importo teorico VA.
- `Qproposte`: quote da acquistare proposte dalla dashboard.

```text
Vprima = Qprima × P
D = T − Vprima
Qproposte = arrotonda_intero_più_vicino(max(0, D) / P)
Aproposto = Qproposte × P
```

Il prezzo deve essere presente e maggiore di zero prima di calcolare le quote. Un prezzo mancante non equivale a zero.

### Arrotondamento

Si mantiene l'arrotondamento all'intero più vicino usato nel foglio di partenza. Per le quantità non negative, una frazione esattamente pari a `0,5` viene arrotondata all'intero superiore.

Il controvalore risultante può essere superiore o inferiore all'importo teorico. Non si impone un tetto all'acquisto. Le quantità negative non vengono mai proposte come operazioni effettive.

La formula vale anche per il primo investimento: se le quote possedute sono zero, `Vprima = 0` e l'importo teorico coincide con l'obiettivo del primo mese.

### Conferma dell'eseguito

La quantità calcolata viene proposta all'utente, che può confermare le quote effettivamente acquistate. Se l'eseguito differisce dalla proposta, lo storico deve rappresentare l'eseguito.

```text
Aeffettivo = Qeffettive × Peseguito
Qdopo = Qprima + Qeffettive
```

Il dettaglio dei campi per distinguere prezzo di rilevazione e prezzo effettivo di esecuzione è ancora da definire. Negli esempi numerici i due prezzi coincidono.

## 5. Segnalazioni informative

La segnalazione principale usa l'importo teorico `D` e l'incremento mensile `I`, come nel ragionamento del foglio originale.

| Condizione | Indicazione |
| --- | --- |
| `D < 0` | Nessun acquisto proposto; incremento della liquidità virtuale. |
| `0 ≤ D ≤ I` | Importo nel range. |
| `D > I` | Superamento dell'incremento mensile; acquisto consentito. |

Non esistono stati dedicati per l'importo esattamente zero o per gli importi positivi inferiori a 1 euro. Non è previsto un minimo obbligatorio di 1 euro.

Se `D = 0`, il risultato è zero quote proposte e nessun incremento della liquidità virtuale, senza una segnalazione specifica.

L'effetto dell'arrotondamento resta visibile: se l'importo teorico rientra nel range ma il controvalore proposto supera l'incremento mensile, la dashboard segnala che il superamento deriva dall'arrotondamento. Il salvataggio resta consentito.

## 6. Liquidità virtuale

Ogni registrazione mensile conclusa contribuisce al contatore in questo modo:

```text
IncrementoLiquidità = max(0, −D)
LiquiditàDopo = LiquiditàPrima + IncrementoLiquidità
```

Regole confermate:

- L'importo accumulato è il valore assoluto dell'importo teorico VA negativo, non il controvalore di una vendita arrotondata.
- Non viene effettuata alcuna vendita e le quote possedute rimangono invariate nel caso negativo.
- Il contatore non è denaro disponibile, non viene investito e non finanzia acquisti futuri.
- Non entra nella valorizzazione dei titoli né nella formula VA dei mesi successivi.
- Non viene sommato al patrimonio come se fosse liquidità reale.
- Le differenze positive tra incremento mensile e controvalore acquistato non alimentano il contatore.
- La registrazione contribuisce una sola volta. Modificarla non aggiunge un secondo incremento.

La liquidità virtuale è la somma degli scostamenti negativi rilevati nei mesi registrati. Non è una misura del guadagno realizzato o del rendimento del PAC: uno stesso sovravalore persistente può influenzare più rilevazioni.

## 7. Valutazione del portafoglio

Per ciascun PAC, il valore indicativo dei titoli è calcolabile automaticamente:

```text
ValoreTitoli = QuotePossedute × UltimoPrezzoInserito
```

Il calcolo richiede quantità effettive attendibili. La dashboard mostra la data del prezzo usato: il valore è riferito all'ultima rilevazione, non è una quotazione in tempo reale.

Un aggiornamento del solo prezzo serve a rivalutare la posizione. Non crea un investimento, non consuma la registrazione mensile e non incrementa la liquidità virtuale.

Il confronto VA usa il valore dei titoli **prima** dell'acquisto, escludendo la liquidità virtuale. La vista del PAC può mostrare anche il valore dopo l'acquisto. A parità di prezzo, quest'ultimo è `Vprima + Aeffettivo`, prima delle eventuali commissioni.

La panoramica riporta almeno quote possedute, capitale effettivamente investito, valore indicativo dei titoli, obiettivo cumulativo, liquidità virtuale e storico delle registrazioni. Il capitale investito non comprende gli incrementi del contatore virtuale.

## 8. Modalità di avvio

Sono previste tre possibilità.

### Nuovo PAC da zero

Si indicano il titolo, il mese e anno di inizio e l'incremento mensile. Le quote iniziali, il capitale iniziale e l'obiettivo di apertura sono zero; la liquidità virtuale parte da zero.

### Importazione dello storico

Si ricostruisce il PAC con le operazioni già effettuate. È prevista un'anteprima da verificare prima della conferma dell'importazione.

Il foglio usato nel brainstorming è un riferimento, non una specifica da copiare alla lettera. Per un'importazione attendibile occorre almeno:

- associare ogni mese al suo anno;
- verificare che le quantità proposte dal foglio coincidano con gli acquisti effettivi;
- distinguere eventuali quantità teoriche negative dalle operazioni reali, dato che il piano non prevede vendite;
- ricostruire la liquidità virtuale secondo le regole di VA Tracker, senza assumere che il saldo del foglio abbia lo stesso significato.

Il formato supportato e il trattamento di dati mancanti o incompatibili verranno definiti prima di implementare l'importazione.

### Situazione iniziale di un PAC già avviato

Si parte da una fotografia della posizione esistente: data di riferimento, quote possedute, capitale già investito e liquidità virtuale già accumulata.

L'obiettivo cumulativo alla data di riferimento viene calcolato dalla data originale di inizio e dall'incremento mensile, per il tratto a incremento costante rappresentato dal PAC. Il primo periodo registrato dopo l'apertura deve usare il successivo obiettivo corretto, senza contare due volte un mese già compreso nella situazione iniziale.

La fotografia è un **saldo di apertura**, distinto da un nuovo acquisto. Non genera un investimento fittizio né un nuovo incremento della liquidità virtuale.

## 9. Incremento fisso e prosecuzione in un nuovo PAC

L'incremento mensile di un PAC non viene modificato durante il piano. Per cambiarlo si usa l'azione **Crea nuovo PAC da questo**.

Si indica il nuovo incremento e il mese di decorrenza. Il PAC successore eredita la situazione del precedente alla chiusura:

- quote possedute;
- capitale effettivamente investito;
- obiettivo cumulativo;
- liquidità virtuale accumulata.

L'obiettivo ereditato è la base di apertura del nuovo piano. Il nuovo incremento si applica ai suoi periodi successivi, mantenendo la continuità degli obiettivi. Anche eventuali mesi non rilevati devono essere considerati nel calendario del piano precedente fino alla chiusura.

Il PAC precedente viene chiuso e rimane consultabile come storico. Tutte le sue registrazioni diventano in sola lettura, inclusa l'ultima. Il nuovo piano mantiene il collegamento con quello precedente.

L'apertura del successore non è un acquisto né un incremento della liquidità virtuale. Nella panoramica corrente la posizione trasferita e il contatore ereditato vengono conteggiati una sola volta; il PAC precedente conserva i propri valori storici senza duplicare i saldi correnti.

## 10. Modificabilità dello storico

Nell'app è modificabile soltanto l'ultima registrazione in ordine cronologico di un PAC attivo.

- Le registrazioni precedenti sono in sola lettura.
- I PAC chiusi sono interamente in sola lettura.
- Non esiste una funzione ordinaria per correggere o eliminare registrazioni pregresse.
- Le correzioni straordinarie dello storico avvengono direttamente sul database, fuori dalle funzioni dell'app.

La modifica dell'ultima registrazione aggiorna i saldi risultanti senza ricalcolare le registrazioni precedenti. Per la liquidità virtuale sostituisce il contributo originario di quel mese con quello corretto:

```text
LiquiditàCorretta = LiquiditàPrimaDelMese + NuovoIncrementoLiquiditàDelMese
```

Non vengono sostituite le quantità realmente acquistate nelle altre registrazioni con nuove quantità teoriche.

## 11. Dettagli ancora da definire

Questi punti non modificano le decisioni confermate e non sono requisiti già approvati:

- Distinzione fra prezzo di rilevazione e prezzo di esecuzione; eventuali commissioni e loro inclusione nel capitale investito.
- Numero di decimali ammessi e momento dell'arrotondamento monetario. I prezzi non dovranno perdere precisione solo perché gli importi vengono visualizzati in euro con due decimali.
- Possibilità di eliminare l'ultima registrazione. Al momento è confermata solo la sua modificabilità, mentre lo storico precedente resta bloccato.
- Inserimento retroattivo di un mese mancante quando esistono già registrazioni successive: non deve introdurre implicitamente una riscrittura dello storico bloccato.
- Formato di importazione, gestione delle anomalie e possibile riconciliazione di uno storico importato dopo un saldo di apertura, rispettando il vincolo sullo storico.
- Campi identificativi del titolo, valute e perimetro iniziale degli strumenti. Un titolo per PAC, importi in euro e identificazione tramite ticker o ISIN sono la proposta iniziale.
- Dettaglio delle viste e dei grafici. Una vista con storico e confronto tra valore dei titoli e obiettivo è stata proposta nel brainstorming.
- Esportazione e ripristino completo dei dati, proposti come funzionalità di conservazione dello storico; formati e comportamento devono essere concordati.
- Stack tecnologico, modalità di accesso, persistenza e distribuzione dell'applicazione.

## 12. Confine della prima fase

La fase attuale documenta il funzionamento. La scelta tecnologica precederà l'implementazione. Collegamenti a broker, esecuzione automatica di ordini e quotazioni automatiche non sono stati concordati.
