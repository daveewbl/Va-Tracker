# Funzionamento di VA Tracker

Stato: specifica concordata nel brainstorming; MVP locale implementato il 12 novembre 2026.

Le decisioni confermate sono descritte nelle sezioni principali. Le funzionalità rimandate dopo l'MVP sono raccolte nella sezione 11. Le scelte di implementazione sono descritte nello [stack tecnico](stack-tecnico.md), con struttura concettuale e schema fisico concordati nella [struttura dati](struttura-dati.md). La sezione 13 raccoglie i flussi applicativi concordati dell'MVP. Le funzioni dell’MVP sono implementate: catalogo e quotazioni, PAC, rilevazioni, successori, dashboard e storico. Importazione, esportazione e ripristino restano esclusi.

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
| Data del prezzo | Giorno esatto a cui si riferisce il prezzo inserito, distinto dal periodo del PAC. Determina l'ordine cronologico delle quotazioni. |

Il termine ambiguo "target" viene quindi distinto in **incremento mensile** e **obiettivo cumulativo**.

## 3. Calendario e obiettivi

È ammessa una sola registrazione per mese per ciascun PAC.

Le nuove rilevazioni si inseriscono soltanto in un periodo strettamente successivo all'ultima rilevazione salvata del PAC. Il confronto riguarda mese e anno del periodo, non la data del prezzo o la data di inserimento. Non è consentito aggiungere una rilevazione in un mese mancante se esistono già rilevazioni successive.

Se non ci sono rilevazioni, il primo periodo registrabile è il mese di inizio per un PAC da zero oppure il mese successivo all'ultimo mese coperto dal saldo di apertura. Non è obbligatorio registrare tutti i mesi intermedi: quelli saltati rimangono non rilevati.

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

Il modulo si apre dal dettaglio del PAC e richiede periodo (mese e anno), prezzo unitario, data del prezzo e quote effettivamente acquistate. Il PAC è già selezionato e determina il titolo. Le quote effettive sono precompilate con la proposta del calcolo VA e devono essere confermate dall'utente come numero intero non negativo. Zero quote è ammesso.

La data del prezzo è obbligatoria e precompilata con la data odierna alla creazione del modulo; l'utente può selezionare la data effettiva del prezzo prima del salvataggio. È distinta dal mese della rilevazione: per esempio, il periodo può essere novembre 2026 e la data del prezzo il 10 novembre 2026. L'obiettivo cumulativo e l'unicità mensile dipendono dal periodo del PAC, non dalla data del prezzo.

L'anteprima mostra quote già possedute, obiettivo cumulativo, valore prima dell'acquisto, importo teorico VA, quote e controvalore proposti, controvalore effettivo, saldi dopo l'operazione e segnalazioni sul range. Questi dati sono calcolati automaticamente e l'anteprima non modifica i saldi. I campi sono descritti nella [struttura dati](struttura-dati.md#4-rilevazione-mensile).

Il percorso dall'apertura del modulo al salvataggio, compresa la gestione delle modifiche successive all'anteprima, è descritto nel [flusso di registrazione mensile](#registrare-una-rilevazione-mensile).

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
Aeffettivo = Qeffettive × P
Qdopo = Qprima + Qeffettive
```

Nella prima versione si usa un unico prezzo per rilevazione e acquisto. Il prezzo conserva fino a sei decimali; i risultati monetari finali vengono arrotondati ai centesimi con metà verso l'alto, mantenendo prima la precisione richiesta dai calcoli. Le commissioni sono escluse dalla prima versione.

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
- Non viene effettuata alcuna vendita; con zero quote effettivamente acquistate le quote possedute rimangono invariate, come nella proposta VA del caso negativo. Le quote risultanti usano sempre l'eseguito confermato dall'utente.
- Il contatore non è denaro disponibile, non viene investito e non finanzia acquisti futuri.
- Non entra nella valorizzazione dei titoli né nella formula VA dei mesi successivi.
- Non viene sommato al patrimonio come se fosse liquidità reale.
- Le differenze positive tra incremento mensile e controvalore acquistato non alimentano il contatore.
- La registrazione contribuisce una sola volta. Modificarla non aggiunge un secondo incremento.

La liquidità virtuale è la somma degli scostamenti negativi rilevati nei mesi registrati. Non è una misura del guadagno realizzato o del rendimento del PAC: uno stesso sovravalore persistente può influenzare più rilevazioni.

## 7. Valutazione del portafoglio

Il catalogo condiviso identifica ogni titolo con un ticker obbligatorio e univoco, per esempio `SWDA`. Il campo si chiama `ticker` anche nel database, dove sarà obbligatorio e protetto da un vincolo univoco. Il ticker viene normalizzato in maiuscolo rimuovendo gli spazi iniziali e finali. Il nome del titolo è obbligatorio ma liberamente scelto e può ripetersi: l'unicità riguarda il ticker. Il modello applicativo mantiene il nome `Instrument`. L'MVP non richiede ISIN, mercato o campo valuta e lavora implicitamente in euro; l'unicità del solo ticker vale all'interno del catalogo personale.

Per creare più PAC sullo stesso titolo si seleziona la medesima anagrafica tramite il ticker, condividendo la quotazione corrente. Quote, incremento mensile e storico restano propri di ciascun PAC.

Per ciascun PAC, il valore indicativo dei titoli è calcolabile automaticamente:

```text
ValoreTitoli = QuotePossedute × QuotazioneCorrenteDelTitolo
```

Il calcolo richiede quantità effettive attendibili. La quotazione corrente è conservata sul titolo e condivisa da tutti i PAC associati a quel titolo. La dashboard mostra la data del prezzo usato: il valore è riferito alla quotazione corrente conservata sul titolo, non è una quotazione in tempo reale. Il prezzo storico e la data del prezzo di ciascuna rilevazione mensile rimangono separati dalla quotazione corrente.

Una nuova rilevazione aggiorna automaticamente prezzo e data della quotazione condivisa quando questa è assente oppure quando la data del prezzo della rilevazione è più recente. A parità di data prevale l'ultimo prezzo inserito, anche se differente, senza richiedere conferma. Il confronto usa prima la data del prezzo, non il periodo del PAC; l'ordine di inserimento risolve soltanto la parità di data. Le stesse regole valgono per l'importazione. Inserimenti e importazioni storiche con una data del prezzo precedente non sovrascrivono una quotazione più recente.

Tutte le quote dello stesso titolo, anche se distribuite fra più PAC, sono valorizzate nella panoramica con la stessa quotazione corrente. L'aggiornamento non modifica prezzi di acquisto, controvalori, capitale investito, calcoli VA o liquidità virtuale delle rilevazioni già salvate. Non vengono calcolate medie automatiche tra i prezzi inseriti e per l'MVP non è richiesto l'orario del prezzo.

La modifica del prezzo nell'ultima rilevazione corregge lo storico e i relativi calcoli, senza aggiornare automaticamente la quotazione condivisa. L'aggiornamento della quotazione corrente rimane un'operazione separata disponibile sul titolo.

Un aggiornamento del solo prezzo serve a rivalutare la posizione. Non crea un investimento, non consuma la registrazione mensile e non incrementa la liquidità virtuale.

L'aggiornamento ordinario della quotazione accetta date uguali o successive a quella corrente. Un'eventuale data errata della quotazione corrente si corregge tramite la modalità esplicita **Correggi quotazione corrente**, che può sostituire anche la data con una precedente dopo aver mostrato la coppia prezzo/data precedente e quella corretta. Questa correzione riguarda soltanto l'anagrafica e non riscrive le rilevazioni storiche. I prezzi delle nuove rilevazioni continuano a seguire il confronto cronologico automatico già concordato.

Il confronto VA usa il valore dei titoli **prima** dell'acquisto, escludendo la liquidità virtuale. La vista del PAC può mostrare anche il valore dopo l'acquisto. A parità di prezzo, quest'ultimo è `Vprima + Aeffettivo`.

La panoramica riporta almeno quote possedute, capitale effettivamente investito, valore indicativo dei titoli, obiettivo cumulativo, liquidità virtuale e storico delle registrazioni. Il capitale investito non comprende gli incrementi del contatore virtuale.

### Dashboard dell'MVP

La dashboard è la pagina iniziale con la situazione corrente dei PAC attivi. L'organizzazione della schermata è delegata alla progettazione dell'interfaccia; la struttura scelta mette al centro la tabella dei PAC e l'accesso alle rilevazioni.

In alto sono mostrati capitale effettivamente investito e valore corrente dei titoli dei PAC attivi. Un riquadro distinto mostra la liquidità virtuale, senza sommarla al valore dei titoli. I PAC chiusi sono consultabili dallo storico e non contribuiscono ai totali correnti: nella catena di prosecuzione si conteggia soltanto la posizione attiva.

La tabella dei PAC attivi riporta:

- ticker e nome del titolo, con mese di inizio e incremento per distinguere più PAC sullo stesso titolo;
- incremento mensile e obiettivo cumulativo del mese corrente, indicando esplicitamente mese e anno di riferimento;
- quote possedute, capitale investito e valore corrente;
- ultimo periodo registrato, oppure indicazione dell'assenza di rilevazioni;
- accesso al dettaglio del PAC e all'inserimento di una nuova rilevazione.

La quotazione usata e la relativa data sono visibili insieme alla valorizzazione del PAC. Lo stato del mese corrente evidenzia se la rilevazione è già presente o manca, solo per i mesi registrabili secondo il calendario del piano; prima dell'inizio o per mesi già coperti dal saldo di apertura non viene richiesta una nuova rilevazione. I mesi saltati non diventano automaticamente operazioni da recuperare.

Le azioni principali della pagina sono **Nuovo PAC** e **Gestisci titoli e quotazioni**. Le segnalazioni VA sul range sono mostrate nel modulo della rilevazione dopo la scelta di periodo e prezzo; la dashboard non anticipa una proposta di acquisto usando dati non confermati.

Se un PAC possiede quote ma il titolo non ha una quotazione disponibile, il valore è indicato come non disponibile. Il riepilogo mostra come parziale la somma dei valori calcolabili e segnala i PAC esclusi, senza attribuire loro valore zero. Un PAC con zero quote ha invece valore zero anche in assenza di quotazione.

Se non ci sono PAC, la pagina mostra uno stato iniziale con l'azione per creare il primo piano; se esistono soltanto PAC chiusi, offre anche l'accesso allo storico. Su schermi piccoli il riepilogo e le informazioni dei PAC si dispongono verticalmente, mantenendo accessibili le azioni principali. Lo storico completo e il confronto grafico del singolo PAC trovano posto nel suo dettaglio; il primo MVP non richiede un grafico aggregato nella dashboard.

### Dettaglio del PAC nell'MVP

Le scelte di presentazione del dettaglio del PAC sono delegate e seguono le regole del dominio già concordate. La pagina è organizzata in intestazione, riepilogo, grafico e storico mensile, con una sezione secondaria per configurazione e situazione di apertura.

**Intestazione e azioni.** Mostra ticker, nome del titolo, stato attivo/chiuso, incremento mensile e mese di inizio. Gli eventuali collegamenti al PAC precedente e al successore permettono di percorrere la catena senza mescolare i rispettivi storici. Per un PAC attivo, l'azione principale è **Registra rilevazione**; sono disponibili l'accesso all'aggiornamento della quotazione del titolo e **Crea nuovo PAC da questo**. Le azioni di modifica della configurazione e di eliminazione del PAC sono disponibili soltanto quando consentite dai vincoli delle sezioni 8 e 9 e della struttura dati. L'interfaccia non consente di modificare direttamente l'incremento mensile di un PAC esistente.

**Riepilogo.** Mostra quote possedute, capitale effettivamente investito, valore corrente dei titoli e obiettivo cumulativo con il relativo periodo di riferimento. Il prezzo usato per la valorizzazione e la sua data sono espliciti. La liquidità virtuale compare in un riquadro distinto e non entra nel valore dei titoli. I saldi derivano dall'ultima rilevazione oppure dalla situazione di apertura; l'obiettivo del mese segue il calendario. Prima dell'inizio del piano non viene presentato un obiettivo come già maturato. Se mancano quotazioni, si applicano le stesse regole della dashboard: valore non disponibile per quote positive e valore zero per zero quote.

**Grafico storico.** Confronta l'obiettivo cumulativo con il valore dei titoli rilevato prima dell'acquisto, usando il periodo del PAC sull'asse orizzontale e gli importi in euro sull'asse verticale. L'obiettivo segue tutti i mesi del calendario del piano; i valori storici sono presenti soltanto nei mesi registrati e usano il prezzo di quella rilevazione. Nei mesi non rilevati la serie dei valori dei titoli resta interrotta, senza interpolazioni o valori inventati. L'aggiornamento della quotazione corrente non modifica il grafico storico. Il dettaglio di ogni punto mostra periodo, data del prezzo, obiettivo e valore prima dell'acquisto. Il grafico visualizza gli ultimi 180 mesi del piano; lo storico tabellare rimane completo. La liquidità virtuale non è sommata alla serie dei titoli.

**Storico mensile.** La tabella è ordinata inizialmente dal periodo più recente e riporta periodo, prezzo storico con relativa data, obiettivo, valore prima dell'acquisto, importo teorico VA, quote effettivamente acquistate, controvalore effettivo e segnalazione sul range. La segnalazione mantiene il significato concordato: dipende dall'importo teorico VA, con indicazione dell'eventuale superamento per arrotondamento. Zero acquisti resta una rilevazione conclusa. Aprendo una riga si consultano anche quote prima e dopo, quote e controvalore proposti, capitale investito risultante, incremento e saldo della liquidità virtuale. Prezzi fino a sei decimali restano consultabili senza perdere precisione; gli importi monetari sono mostrati ai centesimi.

Soltanto l'ultima riga cronologica di un PAC attivo offre **Modifica** ed **Elimina**, anche se si cambia l'ordinamento o si scorre lo storico. La modifica riguarda soltanto prezzo e quote effettive; PAC, periodo e data del prezzo sono bloccati. L'eliminazione richiede conferma e ripristina i saldi precedenti secondo la sezione 10. Le altre righe sono consultabili in sola lettura. Il nuovo inserimento rispetta sempre il vincolo di periodo successivo all'ultima rilevazione salvata.

**Configurazione e apertura.** Una sezione secondaria raccoglie calendario, incremento e saldi di apertura, compresi l'ultimo mese coperto e l'obiettivo cumulativo iniziale. Il saldo di apertura è riconoscibile come tale e non compare fra gli investimenti. Non genera un punto fittizio nel grafico dei valori dei titoli: senza prezzo storico non è possibile ricostruirne la valorizzazione. In assenza di rilevazioni il riepilogo usa i saldi iniziali, lo storico mostra uno stato vuoto con l'azione per la prima registrazione e il grafico mostra soltanto gli obiettivi disponibili.

**PAC chiuso.** La pagina è interamente in sola lettura: non offre nuovi inserimenti, correzioni, eliminazioni o creazione di un ulteriore successore. Mostra mese di chiusura, saldi conservati e collegamento al successore. Il riepilogo usa i saldi di chiusura e le valorizzazioni storiche disponibili, con prezzo e data di riferimento espliciti, senza rivalutare il PAC chiuso alla quotazione corrente. L'obiettivo e il grafico si fermano alla chiusura; per seguire la posizione corrente si apre il successore.

Su schermi piccoli il riepilogo si dispone verticalmente; lo storico mantiene visibili periodo e dati principali, con accesso ai dettagli della singola rilevazione senza comprimere tutte le colonne nella larghezza dello schermo. Grafico e tabella leggono le stesse fotografie storiche e rimangono coerenti dopo la modifica o l'eliminazione consentita dell'ultima rilevazione.

## 8. Modalità di avvio

Nell'MVP sono previste due modalità di avvio: un nuovo PAC da zero oppure un PAC già avviato con saldo di apertura. L'importazione dello storico è rimandata a una fase successiva; le indicazioni già discusse sono conservate di seguito come riferimento futuro.

Il percorso del modulo, del riepilogo e del salvataggio è descritto nel [flusso di apertura del PAC](#aprire-un-pac).

### Nuovo PAC da zero

Si indicano il titolo, il mese e anno di inizio e l'incremento mensile. Le quote iniziali, il capitale iniziale e l'obiettivo di apertura sono zero; la liquidità virtuale parte da zero.

Titolo, incremento mensile e mese di inizio sono obbligatori. L'incremento deve essere maggiore di zero e avere al massimo due decimali. Il mese di inizio è precompilato con il mese corrente. Sono consentiti più PAC sullo stesso titolo, con configurazioni e posizioni indipendenti.

### Importazione dello storico (fase successiva, esclusa dall'MVP)

L'importazione non sarà implementata nel primo MVP. La scelta dell'importer nativo di Filament con colonne mappabili e le personalizzazioni discusse restano un riferimento per quando si riprenderà questa funzionalità, senza richiedere ora di completarne la progettazione. Il perimetro inizialmente proposto prevede l'avvio dal dettaglio di un PAC attivo senza rilevazioni, rispettando l'eventuale saldo di apertura.

Il formato iniziale è CSV, esportabile dal foglio Excel esistente; non è imposto un modello con intestazioni fisse. Dopo il caricamento l'utente associa le colonne del file a periodo (mese e anno), prezzo unitario, data del prezzo e quote effettivamente acquistate. Il PAC e il titolo derivano dal contesto della schermata.

Il flusso prevede:

1. Caricamento del CSV e mappatura delle quattro colonne richieste.
2. Validazione di tutte le righe e ordinamento per periodo, anche se il file non è ordinato.
3. Anteprima dei calcoli VA e dei saldi ricostruiti in sequenza dalla situazione di apertura, senza modificare i dati del PAC o la quotazione condivisa.
4. Conferma e salvataggio dell'intero storico soltanto se tutte le righe sono valide. Non sono consentite importazioni parziali: un errore impedisce il salvataggio dell'intero insieme.

L'anteprima segnala periodi duplicati, dati mancanti, valori non validi e periodi incompatibili con il calendario o il saldo di apertura. I mesi mancanti restano non rilevati. Ogni riga rispetta le stesse regole della rilevazione manuale; la data storica del prezzo deve essere fornita, senza sostituirla automaticamente con la data odierna.

Obiettivi, controvalori, saldi e liquidità virtuale vengono ricalcolati dall'applicazione: le colonne di risultati già calcolati nel foglio non sono usate come fonte autorevole. Alla conferma la quotazione condivisa segue le regole di data e parità già descritte nella sezione 7.

Il foglio usato nel brainstorming è un riferimento, non una specifica da copiare alla lettera. Per un'importazione attendibile occorre almeno:

- associare ogni mese al suo anno;
- verificare che le quantità proposte dal foglio coincidano con gli acquisti effettivi;
- distinguere eventuali quantità teoriche negative dalle operazioni reali, dato che il piano non prevede vendite;
- ricostruire la liquidità virtuale secondo le regole di VA Tracker, senza assumere che il saldo del foglio abbia lo stesso significato.

Quando si riprenderà l'importazione, andranno definiti i formati testuali accettati per periodi, date e numeri e verificata la compatibilità con i dati del foglio originale. Questi dettagli non bloccano l'MVP. Il perimetro discusso non comprende la riconciliazione automatica di uno storico precedente al saldo di apertura né la riscrittura di rilevazioni già salvate.

### Situazione iniziale di un PAC già avviato

Si parte da una fotografia della posizione esistente: data di riferimento, quote possedute, capitale già investito e liquidità virtuale già accumulata.

L'obiettivo cumulativo alla data di riferimento viene calcolato dalla data originale di inizio e dall'incremento mensile, per il tratto a incremento costante rappresentato dal PAC. Il mese del saldo iniziale è l'ultimo mese già coperto dalla posizione inserita: le nuove registrazioni partono dal mese successivo, senza contare due volte il mese già compreso nella situazione iniziale. Quote, capitale investito e liquidità virtuale di apertura si riferiscono tutti allo stesso mese coperto.

La fotografia è un **saldo di apertura**, distinto da un nuovo acquisto. Non genera un investimento fittizio né un nuovo incremento della liquidità virtuale.

L'ultimo mese coperto è obbligatorio e non può precedere l'inizio originario del PAC. Le quote già possedute sono un numero intero non negativo; il capitale già investito è un importo non negativo. La liquidità virtuale iniziale è non negativa e predefinita a zero. Gli importi di apertura hanno al massimo due decimali.

### Gestione dei dati del PAC

L'applicazione gestisce automaticamente utente proprietario, stato attivo/chiuso, collegamento al predecessore e mese di chiusura. L'obiettivo cumulativo di apertura è calcolato o ereditato, senza un campo da compilare nel modulo ordinario.

Dopo la prima rilevazione, titolo, mese iniziale e saldi di apertura diventano in sola lettura. Un PAC è eliminabile soltanto se non contiene rilevazioni e non è collegato a una catena di prosecuzione. I campi e i relativi nomi tecnici sono descritti nella [struttura dati](struttura-dati.md#3-pac-e-situazione-di-apertura).

## 9. Incremento fisso e prosecuzione in un nuovo PAC

L'incremento mensile di un PAC non viene modificato durante il piano. Per cambiarlo si usa l'azione **Crea nuovo PAC da questo**.

Si indica il nuovo incremento e il mese di decorrenza. Il PAC successore eredita la situazione del precedente alla chiusura:

- quote possedute;
- capitale effettivamente investito;
- obiettivo cumulativo;
- liquidità virtuale accumulata.

Nell'MVP il passaggio è immediato: la decorrenza può essere nel mese corrente o in un mese passato compatibile con lo storico, mai in un mese futuro. Non sono previsti cambi programmati. La decorrenza deve essere successiva al mese di inizio del predecessore, all'eventuale ultimo mese coperto dal suo saldo di apertura e alla sua ultima rilevazione, quando presenti. Il mese di chiusura del predecessore è quello immediatamente precedente alla decorrenza.

L'obiettivo ereditato è la base di apertura del nuovo piano. Il nuovo incremento si applica ai suoi periodi successivi, mantenendo la continuità degli obiettivi. Anche eventuali mesi non rilevati devono essere considerati nel calendario del piano precedente fino alla chiusura.

Il PAC precedente viene chiuso e rimane consultabile come storico. Tutte le sue registrazioni diventano in sola lettura, inclusa l'ultima. Il nuovo piano mantiene il collegamento con quello precedente.

Ogni PAC può avere un solo successore, formando una catena senza diramazioni. Nel successore, titolo e saldi ereditati sono bloccati fin dalla creazione.

L'apertura del successore non è un acquisto né un incremento della liquidità virtuale. Nella panoramica corrente la posizione trasferita e il contatore ereditato vengono conteggiati una sola volta; il PAC precedente conserva i propri valori storici senza duplicare i saldi correnti.

## 10. Modificabilità dello storico

Nell'app è modificabile soltanto l'ultima registrazione in ordine cronologico di un PAC attivo.

- Gli unici campi modificabili sono il prezzo unitario e le quote effettivamente acquistate.
- PAC, periodo e data del prezzo rimangono bloccati dopo il salvataggio; i risultati calcolati non sono modificabili direttamente.
- Le registrazioni precedenti sono in sola lettura.
- I PAC chiusi sono interamente in sola lettura.
- Non esiste una funzione ordinaria per correggere o eliminare registrazioni precedenti all'ultima.
- Le correzioni straordinarie dello storico avvengono direttamente sul database, fuori dalle funzioni dell'app.

La modifica dell'ultima registrazione ricalcola i risultati e aggiorna i saldi risultanti a partire dalla situazione precedente già nota, senza ricalcolare le registrazioni precedenti. Per la liquidità virtuale sostituisce il contributo originario di quel mese con quello corretto:

```text
LiquiditàCorretta = LiquiditàPrimaDelMese + NuovoIncrementoLiquiditàDelMese
```

Non vengono sostituite le quantità realmente acquistate nelle altre registrazioni con nuove quantità teoriche.

### Eliminazione dell'ultima rilevazione

È consentito eliminare soltanto l'ultima rilevazione in ordine cronologico di un PAC attivo, dopo una conferma esplicita nell'app. Le rilevazioni precedenti e quelle dei PAC chiusi non sono eliminabili.

L'eliminazione ripristina quote possedute, capitale investito e liquidità virtuale ai saldi della rilevazione precedente oppure ai saldi di apertura, se non rimangono registrazioni. Il contributo della rilevazione eliminata alla liquidità virtuale viene quindi rimosso. Le rilevazioni precedenti non vengono ricalcolate.

Il mese eliminato torna non rilevato: non equivale a una rilevazione con zero acquisti. Il calendario e la crescita dell'obiettivo rimangono invariati. La quotazione corrente del titolo e la relativa data rimangono invariate, anche se erano state aggiornate dalla rilevazione eliminata.

L'operazione corregge i dati dell'applicazione: non rappresenta una vendita e non annulla l'acquisto effettuato presso il broker. Permette, per esempio, di eliminare un'ultima rilevazione con periodo errato e reinserirla nel mese corretto, rispettando i vincoli di registrazione.

Se è stato dimenticato un acquisto in un mese precedente all'ultima rilevazione, occorre rimuovere le rilevazioni successive eliminando ogni volta soltanto l'ultima, inserire il mese mancante e reinserire in ordine cronologico quelle rimosse. I prezzi e le quote effettivamente acquistate devono rappresentare le operazioni reali; i calcoli e i saldi delle rilevazioni reinserite vengono ricostruiti dalla posizione corretta. Non è prevista una riscrittura automatica dello storico già salvato.

## 11. Funzionalità rimandate dopo l'MVP

Le seguenti funzionalità sono escluse dal primo MVP e non ne bloccano l'implementazione:

- Importazione dello storico: le scelte già discusse sono conservate nella sezione 8 come riferimento futuro. Formati e compatibilità con il foglio originale saranno ripresi in quella fase.
- Esportazione dello storico e ripristino completo dei dati tramite l'applicazione: formati, interfaccia e comportamento saranno definiti dopo l'MVP.

Il backup tecnico del database resta distinto da queste funzionalità applicative: la conservazione di una copia dei dati non richiede nell'MVP schermate di esportazione o ripristino. L'eventuale recupero da un backup avviene a livello tecnico, fuori dai flussi ordinari dell'applicazione.

## 12. Confine della prima fase

Il primo MVP comprende l'inserimento manuale delle rilevazioni e l'avvio dei PAC da zero o con saldo iniziale, con dashboard e dettaglio del PAC definiti nella sezione 7. Importazione, esportazione e ripristino dei dati tramite l'applicazione sono rimandati dopo l'MVP, insieme alla definizione dei relativi formati. Le indicazioni sull'importazione nella sezione 8 non sono requisiti da implementare per il primo MVP.

La fase attuale documenta funzionamento, schema fisico e flussi applicativi concordati dell'MVP, con lo [stack tecnico](stack-tecnico.md): applicazione locale con Herd, Filament con tema dedicato e SQLite iniziale, con possibile passaggio a MySQL in presenza di limiti concreti. Il dominio PAC e le schermate sono implementati con test automatici sui calcoli, sui vincoli e sui flussi applicativi. La verifica dell’interfaccia locale usa il browser integrato. Collegamenti a broker, esecuzione automatica di ordini e quotazioni automatiche non sono stati concordati.

## 13. Flussi applicativi dell'MVP

Ogni flusso corrisponde a un'operazione applicativa distinta. L'interfaccia Filament gestisce la sequenza delle schermate e gli input; le operazioni applicative verificano le regole e salvano i dati. Il medesimo calcolatore VA è usato per anteprima e salvataggio.

| Flusso | Comportamento |
| --- | --- |
| Gestire un titolo | Creare o modificare ticker e nome rispettando normalizzazione e unicità. |
| Aggiornare la quotazione | Aggiornare prezzo e data senza creare investimenti o modificare lo storico. |
| Aprire un PAC | Partire da zero o da un saldo iniziale, calcolando l'obiettivo di apertura. |
| Registrare una rilevazione | Mostrare l'anteprima, confermare le quote effettive e salvare la fotografia del calcolo. |
| Correggere l'ultima rilevazione | Modificare prezzo e quote, ricostruendo i risultati dalla posizione precedente. |
| Eliminare l'ultima rilevazione | Ripristinare i saldi precedenti dopo conferma. |
| Creare un successore | Trasferire i saldi e chiudere il predecessore in un'unica operazione. |
| Gestire la configurazione del PAC | Consentire modifiche ed eliminazione soltanto nei casi concordati. |

La lettura della dashboard, del dettaglio, del grafico e dello storico non crea rilevazioni né modifica saldi o quotazioni. Importazione, esportazione e ripristino tramite l'applicazione restano esclusi dall'MVP.

### Gestire un titolo nel catalogo

1. **Accesso al modulo.** L'utente apre **Titoli** oppure crea un titolo durante l'apertura di un PAC.
2. **Ticker e nome.** Entrambi sono obbligatori. Il ticker viene normalizzato in maiuscolo senza spazi esterni e verificato come univoco, rispettando il limite di 32 caratteri; il nome è liberamente scelto, può ripetersi e ha un limite di 255 caratteri. Un ticker già presente impedisce la creazione di un duplicato, senza sovrascrivere l'anagrafica esistente.
3. **Eventuale quotazione iniziale.** La creazione può comprendere un prezzo iniziale positivo, fino a sei decimali, con data obbligatoria. Prezzo e data possono anche essere entrambi assenti: il titolo rimane selezionabile per creare un PAC. Non vengono create rilevazioni o posizioni per effetto della quotazione iniziale.
4. **Salvataggio.** Il server valida i dati e verifica nuovamente l'unicità del ticker, protetta anche dal vincolo nel database. Crea l'anagrafica con il relativo ID interno e l'eventuale coppia prezzo/data. Se il flusso proviene dal modulo di apertura del PAC, vi ritorna con il titolo creato già selezionato; altrimenti torna al catalogo aggiornato.

Il nome è modificabile anche se il titolo è associato a PAC. Il ticker può essere corretto mantenendo lo stesso ID interno e tutti i collegamenti, purché il nuovo valore normalizzato sia univoco. La modifica di ticker o nome aggiorna l'anagrafica condivisa ma non altera quotazioni, quantità, saldi o calcoli storici. Per rappresentare uno strumento finanziario diverso si crea un nuovo titolo, senza riutilizzare l'anagrafica esistente per cambiare lo strumento dei PAC collegati.

L'eliminazione è consentita soltanto se nessun PAC è associato al titolo, compresi quelli chiusi. Il server verifica il vincolo anche al salvataggio dell'operazione e le chiavi esterne impediscono cancellazioni che lascerebbero PAC senza titolo. Non sono previste cancellazioni a cascata o fusioni automatiche di anagrafiche.

Gli aggiornamenti successivi della quotazione usano un'azione dedicata, separata dalla modifica di ticker e nome. La gestione del titolo non genera investimenti né modifica la liquidità virtuale.

### Aprire un PAC

1. **Scelta della modalità.** L'utente sceglie **Da zero** oppure **Con saldo iniziale**. La prosecuzione di un piano esistente non passa da questo modulo: usa l'azione dedicata dal PAC precedente.
2. **Selezione del titolo.** Il catalogo mostra ticker e nome del titolo. Se il titolo non è presente, l'utente può crearlo e poi completare il PAC. La selezione fa riferimento all'ID interno del titolo.
3. **Configurazione del piano.** L'utente inserisce incremento mensile e mese di inizio. Per un PAC con saldo iniziale, l'inizio è quello originario del tratto rappresentato a incremento costante. L'incremento è positivo e ha al massimo due decimali; l'inizio richiede mese e anno.
4. **Eventuale saldo iniziale.** Nella modalità con saldo si inseriscono ultimo mese coperto, quote possedute, capitale già investito e liquidità virtuale iniziale. L'ultimo mese coperto non precede l'inizio originario; quantità e importi rispettano i vincoli concordati. Nella modalità da zero i saldi iniziali sono impostati automaticamente a zero.
5. **Riepilogo.** L'app mostra configurazione, saldi, obiettivo cumulativo di apertura calcolato e primo mese registrabile. Da zero, l'obiettivo di apertura è zero e il primo mese registrabile è quello di inizio. Con saldo iniziale, l'obiettivo è l'incremento moltiplicato per il numero di mesi dall'inizio al mese coperto, inclusi entrambi; la prima registrazione è possibile dal mese successivo. Il riepilogo non crea record o investimenti.
6. **Salvataggio e accesso al dettaglio.** Il server valida nuovamente i dati, verifica il titolo selezionato, normalizza i periodi e ricalcola l'obiettivo iniziale. Crea il PAC attivo con proprietario assegnato dall'utente autenticato, `previous_plan_id`, `closed_period` e `configuration_locked_at` null. Il salvataggio del piano avviene in una transazione; in caso di errore non viene creato un PAC parziale. Dopo il successo si apre il dettaglio del nuovo PAC.

| Modalità | Obiettivo di apertura | Primo mese registrabile |
| --- | --- | --- |
| Da zero | Zero. | Mese di inizio. |
| Con saldo iniziale | Incremento × mesi dall'inizio al mese coperto, inclusi. | Mese successivo all'ultimo coperto. |

Per esempio, inizio a gennaio 2026, incremento di 750 euro e saldo riferito a ottobre producono un obiettivo di apertura di 7.500 euro. La prima rilevazione può essere novembre, con obiettivo di 8.250 euro. È comunque possibile lasciare mesi non rilevati rispettando il calendario e i vincoli di inserimento.

L'apertura crea soltanto il PAC: non genera una rilevazione fittizia, non incrementa il contatore virtuale e non aggiorna la quotazione del titolo. Un prezzo non è richiesto per dichiarare il saldo iniziale. Prima della prima rilevazione il dettaglio mostra i saldi di apertura e uno storico vuoto; il blocco della configurazione avverrà secondo le regole già definite.

### Registrare una rilevazione mensile

1. **Apertura dal PAC.** Titolo e piano sono già determinati. L'app verifica l'accesso e lo stato attivo del PAC, quindi recupera la posizione dall'ultima rilevazione oppure dal saldo di apertura. Non viene ancora salvata alcuna rilevazione.
2. **Inserimento dei dati.** L'utente seleziona il periodo, inserisce il prezzo e verifica la data del prezzo precompilata con oggi. Il periodo rispetta il calendario di apertura ed è strettamente successivo all'ultima rilevazione salvata; prezzo e data sono validati secondo i vincoli concordati.
3. **Anteprima VA.** L'app mostra posizione precedente, obiettivo del periodo, valore prima dell'acquisto, importo teorico VA, quote e controvalore proposti, segnalazioni e saldi risultanti. Il calcolo conserva la precisione richiesta prima degli arrotondamenti. L'anteprima non modifica né il PAC né la quotazione del titolo.
4. **Conferma dell'eseguito.** L'utente conferma le quote effettivamente acquistate, precompilate con la proposta e modificabili prima del salvataggio; anche zero è valido. L'anteprima aggiorna controvalore effettivo e saldi risultanti usando tale quantità. Una quantità effettiva già confermata dall'utente non viene sostituita silenziosamente da una nuova proposta.
5. **Salvataggio autorevole.** Il server rilegge lo stato del PAC e verifica nuovamente accesso, stato attivo, periodo, unicità e dati inseriti. Ricalcola l'intera fotografia usando il calcolatore condiviso, senza accettare dal browser risultati o saldi come fonte autorevole. In un'unica transazione salva la rilevazione, imposta `configuration_locked_at` se non è già valorizzato e aggiorna la quotazione condivisa quando previsto dalle regole di data e parità. Un errore non lascia modifiche parziali.
6. **Ritorno al dettaglio.** Dopo il salvataggio riuscito, riepilogo, grafico e storico mostrano la nuova rilevazione e i saldi risultanti. Il salvataggio registra un'operazione già conclusa e non esegue ordini presso il broker.

### Stato cambiato dopo l'anteprima

Se, fra anteprima e conferma, un'altra operazione cambia la posizione o la configurazione del PAC in modo rilevante per il calcolo, la registrazione non viene salvata sulla base di un'anteprima superata. L'app conserva gli input e le quote effettive inserite, aggiorna l'anteprima usando lo stato corrente e richiede di verificarla prima di completare il salvataggio. Non aggiorna la quotazione condivisa né applica scritture parziali durante questo passaggio.

Se il cambiamento rende l'operazione non più consentita, per esempio perché il PAC è stato chiuso o il periodo è già occupato, il salvataggio viene rifiutato con un messaggio comprensibile e senza creare una seconda registrazione. Non si sposta automaticamente la rilevazione su un altro periodo.

### Aggiornare o correggere la quotazione corrente

1. **Apertura dell'azione.** Da **Titoli** o dal dettaglio di un PAC attivo, l'utente apre **Aggiorna quotazione**. Il modulo mostra ticker, nome, quotazione e data attualmente conservate, con la nuova data precompilata con oggi.
2. **Nuovi dati.** L'utente inserisce prezzo positivo fino a sei decimali e data. In modalità ordinaria, una data più recente aggiorna la quotazione; a parità di data prevale l'ultimo salvataggio riuscito, senza una conferma aggiuntiva. Una data precedente genera un errore e non sostituisce la quotazione corrente. Non viene creata una quotazione storica separata.
3. **Correzione esplicita.** Se deve correggere il prezzo o la data già conservati, l'utente seleziona **Correggi quotazione corrente**. Il riepilogo mostra prezzo e data prima e dopo; una conferma esplicita consente di correggere anche una data impostata per errore troppo avanti. Questa modalità non viene mai attivata automaticamente dall'inserimento di una rilevazione passata.
4. **Salvataggio.** Il server valida i dati e rilegge il titolo in una transazione. Se la quotazione è cambiata mentre il modulo era aperto, conserva gli input e mostra il valore aggiornato prima di applicare la modifica; non sovrascrive silenziosamente una modifica concorrente. Una quotazione assente può essere impostata indicando entrambi i campi. Se prezzo e data coincidono già con quelli richiesti, non occorre una seconda modifica.
5. **Aggiornamento delle viste.** Dopo il successo, tutti i PAC attivi associati al titolo usano la quotazione salvata per la valorizzazione corrente. La vista conferma prezzo e data effettivamente conservati. Quantità, capitale investito, liquidità virtuale e fotografie storiche rimangono invariati; i PAC chiusi continuano a mostrare i propri dati storici.

Il modulo ordinario e la modalità di correzione operano sulla stessa coppia `latest_price`/`latest_price_date`, senza nuovi campi di dominio. La correzione della quotazione e la correzione di una rilevazione restano indipendenti: per correggere entrambe si eseguono entrambe le operazioni. Una quotazione già presente non viene cancellata lasciando vuoto il modulo di aggiornamento.

### Correggere l'ultima rilevazione

1. **Accesso e verifica.** L'utente apre **Modifica** dall'ultima rilevazione cronologica di un proprio PAC attivo. Il server verifica le stesse condizioni anche se l'azione viene richiesta direttamente. La posizione di partenza è quella precedente alla rilevazione selezionata, oppure il saldo di apertura se si tratta della prima.
2. **Campi modificabili.** Il modulo conserva PAC, periodo e data del prezzo in sola lettura. Sono modificabili soltanto prezzo unitario e quote effettivamente acquistate, con i vincoli già concordati. Un errore di periodo o data si corregge eliminando e reinserendo la rilevazione nel rispetto dell'ordine cronologico.
3. **Anteprima della correzione.** Il calcolatore condiviso ricostruisce la fotografia del mese a partire dalla posizione precedente. L'utente vede i valori originali e quelli corretti, compresi controvalore, quote risultanti, capitale investito e contributo/saldo della liquidità virtuale. Le quote effettive rimangono quelle inserite dall'utente, anche se cambia la proposta VA.
4. **Salvataggio.** In una transazione il server rilegge PAC, rilevazione e posizione precedente, verifica che il PAC sia ancora attivo e che il record sia ancora l'ultimo, quindi ricalcola e sostituisce la fotografia di quel mese. Il contributo virtuale sostituisce quello originario e non viene aggiunto una seconda volta. Il blocco della configurazione rimane impostato.
5. **Esito.** Il dettaglio mostra riepilogo, grafico e storico aggiornati. Le rilevazioni precedenti e la quotazione corrente del titolo non vengono modificate.

Se la rilevazione o la posizione di partenza cambiano dopo l'anteprima, il salvataggio si ferma e l'anteprima viene aggiornata conservando i valori inseriti. Se nel frattempo è stata aggiunta una rilevazione successiva, il PAC è stato chiuso o il record è stato eliminato, l'operazione non è più consentita e non produce scritture. Non si trasferisce la correzione su un altro record.

### Eliminare l'ultima rilevazione

1. **Richiesta.** L'azione è disponibile soltanto sull'ultima rilevazione cronologica di un PAC attivo; il server verifica proprietario, stato e posizione del record anche al momento della richiesta.
2. **Riepilogo degli effetti.** Il messaggio identifica periodo, prezzo, quote e controvalore della rilevazione e mostra i saldi attuali e quelli che rimarrebbero dopo l'eliminazione. Indica che il mese tornerà non rilevato e che l'operazione corregge i dati dell'app, senza annullare acquisti presso il broker.
3. **Conferma e rilettura.** Dopo la conferma esplicita, il server rilegge lo stato in una transazione. Se il record o i saldi sono cambiati rispetto al riepilogo, chiede di verificare un nuovo riepilogo prima di procedere. Una rilevazione già eliminata non viene sostituita automaticamente con la nuova ultima riga come bersaglio dell'azione.
4. **Eliminazione.** Se i vincoli sono ancora rispettati, elimina soltanto il record selezionato. I saldi correnti si leggono dalla rilevazione precedente oppure dall'apertura; il contributo virtuale eliminato non viene più conteggiato. `configuration_locked_at`, quotazione corrente e relativa data rimangono invariati.
5. **Esito.** Si torna al dettaglio aggiornato del PAC. L'eventuale rilevazione precedente diventa la nuova ultima, mentre il periodo eliminato torna disponibile secondo il calendario e l'ordine di inserimento. Un PAC rimasto senza rilevazioni mostra nuovamente lo storico vuoto con i propri saldi iniziali.

La chiusura del PAC o la comparsa di una rilevazione successiva prima della conferma impediscono l'eliminazione. Non è prevista un'eliminazione multipla che aggiri questi controlli; per recuperare un mese dimenticato si procede ogni volta dall'ultima rilevazione.

### Modificare la configurazione o eliminare un PAC

La configurazione è modificabile soltanto per un PAC attivo senza rilevazioni, senza predecessore e con `configuration_locked_at` null. La selezione del titolo, il mese di inizio e la situazione di apertura possono essere corretti, compreso il passaggio fra partenza da zero e saldo iniziale. Proprietario, stato, collegamenti di prosecuzione e campi di blocco non sono modificabili dal modulo. L'incremento mensile rimane fisso fin dalla creazione: per variarlo si usa il successore oppure si elimina e ricrea un PAC quando ne ricorrono i requisiti.

1. **Modifica ammessa.** Il modulo mostra soltanto i campi consentiti e applica le medesime regole dell'apertura. Il riepilogo ricalcola obiettivo iniziale e primo mese registrabile dai dati corretti; non crea investimenti o quotazioni.
2. **Salvataggio della configurazione.** Il server rilegge il PAC in una transazione e verifica nuovamente stato, assenza di rilevazioni e blocco. Se il piano è cambiato mentre il modulo era aperto, aggiorna il riepilogo conservando gli input oppure rifiuta l'operazione se è diventata non consentita. Non azzera un blocco già impostato e non sovrascrive una modifica concorrente. Il salvataggio aggiorna soltanto la configurazione ammessa, senza creare rilevazioni o aggiornare il titolo.
3. **Richiesta di eliminazione del PAC.** È consentita soltanto per un PAC attivo senza rilevazioni e senza alcun collegamento di prosecuzione, né predecessore né successore. Una conferma identifica il piano tramite ticker, inizio e incremento e chiarisce che verranno rimossi il piano e i suoi eventuali saldi di apertura, mentre il titolo rimarrà nel catalogo.
4. **Eliminazione confermata.** Il server rilegge e verifica i requisiti nella transazione, eliminando soltanto il PAC selezionato. Se nel frattempo è comparsa una rilevazione o è cambiato il piano, non applica la vecchia conferma. Titolo, quotazione e altri PAC restano invariati. Dopo il successo si torna all'elenco dei PAC o alla dashboard.

L'eliminazione resta ammessa per un PAC autonomo rimasto senza rilevazioni anche se `configuration_locked_at` è valorizzato: tale campo impedisce di riscrivere la configurazione, ma non aggiunge un divieto di cancellazione ai requisiti già concordati. I PAC chiusi e quelli appartenenti a una catena non sono eliminabili da questo flusso.

### Creare un PAC successore

1. **Accesso dal predecessore.** L'utente apre **Crea nuovo PAC da questo** da un proprio PAC attivo che non abbia già un successore. Il titolo e il proprietario sono ereditati e non sono selezionabili liberamente nel nuovo piano.
2. **Incremento e decorrenza.** L'utente indica il nuovo incremento positivo e il mese di decorrenza. Il mese deve essere successivo al mese di inizio del predecessore, all'eventuale `opening_period` e all'ultima rilevazione del predecessore. Deve inoltre essere uguale o precedente al mese corrente. Il modulo può precompilare il mese corrente quando valido; altrimenti mostra il primo periodo compatibile e spiega se occorre attendere quel mese. Non crea un passaggio programmato con decorrenza futura.
3. **Calcolo dell'apertura.** Il server determina il mese di chiusura come il mese precedente alla decorrenza. Quote, capitale investito e liquidità virtuale derivano dall'ultima rilevazione del predecessore oppure dai suoi saldi iniziali. L'obiettivo ereditato è quello previsto dal calendario del predecessore alla chiusura: parte dall'obiettivo di apertura e aggiunge gli incrementi maturati fino a quel mese, compresi quelli dei mesi non rilevati. Nessun mese mancante genera acquisti o contributi virtuali fittizi.
4. **Riepilogo e conferma.** La vista mostra vecchio e nuovo incremento, chiusura e decorrenza, quote/capitale/liquidità trasferiti, obiettivo ereditato e obiettivo del primo mese del successore. Specifica che la conferma chiude subito il predecessore rendendo anche la sua ultima rilevazione non più modificabile o eliminabile. Il riepilogo non modifica i saldi e non crea record.
5. **Salvataggio atomico.** Dopo conferma il server rilegge il predecessore e ne verifica accesso, stato, assenza di successore, periodi e saldi. Se la base è cambiata, aggiorna il riepilogo prima di procedere; se i requisiti non valgono più, rifiuta l'operazione. Nella stessa transazione imposta `status = closed` e `closed_period` sul predecessore e crea il successore attivo con `previous_plan_id`, titolo e proprietario ereditati, `start_period` uguale alla decorrenza, `opening_period` uguale alla chiusura e saldi/obiettivo ereditati. `configuration_locked_at` del successore è impostato subito. Un errore annulla l'intera operazione, senza lasciare un predecessore chiuso privo del successore appena richiesto.
6. **Esito.** Si apre il dettaglio del nuovo PAC, con storico delle rilevazioni vuoto, saldi di apertura ereditati e collegamento al precedente. La prima nuova rilevazione è registrabile dalla decorrenza, con obiettivo pari all'obiettivo ereditato più il nuovo incremento. Il predecessore rimane consultabile con il proprio storico e i collegamenti della catena.

Non vengono creati acquisti, trasferimenti monetari o incrementi della liquidità virtuale per effetto del passaggio. La quotazione del titolo rimane invariata e i saldi correnti della catena vengono conteggiati una sola volta tramite il successore attivo. Il predecessore conserva i saldi storici anche quando l'ultimo prezzo rilevato precede il mese di chiusura: non si inventa una quotazione per i mesi mancanti.

Due conferme concorrenti sullo stesso predecessore non possono creare diramazioni: stato, transazione e vincolo univoco su `previous_plan_id` proteggono l'operazione. Una seconda richiesta ormai superata non chiude o modifica il successore già creato.

### Stato della progettazione dei flussi

I flussi dell'MVP sono definiti: gestione di titoli e quotazioni, apertura del PAC, registrazione mensile, correzione ed eliminazione dell'ultima rilevazione, creazione del successore e configurazione o eliminazione del PAC nei casi consentiti. I flussi sono implementati e coperti dalle verifiche descritte nello stack tecnico. Importazione, esportazione, ripristino e cambi di incremento programmati per mesi futuri sono esclusi dall'MVP.
