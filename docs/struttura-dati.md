# Struttura dati di VA Tracker

Stato: struttura concettuale, schema fisico e flussi applicativi dell'MVP concordati nel brainstorming. Lo schema fisico è descritto nella sezione 6 e tutti i flussi nella sezione 13 della specifica funzionale; modelli e migration del dominio non sono ancora implementati.

## 1. Entità principali

| Entità | Responsabilità |
| --- | --- |
| Utente Laravel | Utente associato ai PAC; la base tecnica prevede già l'utente locale. |
| Titolo (`Instrument`) | Strumento condiviso tra più PAC e sede dell'ultima quotazione indicativa. |
| PAC (`Plan`) | Piano con incremento mensile fisso, calendario, situazione di apertura e possibile collegamento al precedente. |
| Rilevazione mensile (`Investment`) | Registrazione conclusa del mese, anche con zero quote acquistate, con prezzo storico e fotografia del calcolo. |

Le relazioni ordinarie saranno esplicite tramite chiavi esterne. Questa struttura non richiede relazioni polimorfiche.

## 2. Titolo e quotazione condivisa

Il catalogo titoli è separato dai PAC. Uno stesso titolo può essere associato a più PAC, ciascuno con incremento, obiettivi e quantità propri.

Il modello mantiene il nome `Instrument`. L'anagrafica dell'MVP contiene ticker univoco, nome del titolo, ultimo prezzo e data della quotazione, oltre all'ID interno e ai timestamp gestiti dall'applicazione.

- **Ticker (`ticker`):** obbligatorio e univoco nel catalogo, per esempio `SWDA`. Il campo si chiamerà `ticker` anche nel database, con colonna non nullable e vincolo univoco. Viene normalizzato in maiuscolo e senza spazi iniziali o finali, così `SWDA`, `swda` e ` SWDA ` identificano lo stesso ticker e non possono creare titoli distinti.
- **Nome:** obbligatorio, liberamente scelto dall'utente e non univoco; nomi uguali sono consentiti per ticker differenti.
- **Ultimo prezzo:** facoltativo, positivo e fino a sei decimali.
- **Data della quotazione:** richiesta quando si inserisce un prezzo.

Il ticker è il codice visibile che distingue il titolo nel catalogo; i collegamenti con i PAC continuano a usare l'ID interno automatico del titolo tramite `instrument_id`. Più PAC possono selezionare lo stesso titolo tramite il ticker e condividere la relativa quotazione, mantenendo posizioni e storici indipendenti. L'unicità del solo ticker è una convenzione del catalogo dell'MVP, che non distingue quotazioni su mercati diversi.

ISIN, mercato e campo valuta sono esclusi dall'anagrafica dell'MVP. Non è previsto un campo `identifier` separato dal ticker. L'applicazione lavora implicitamente in euro.

L'aggiornamento della quotazione corrente rivaluta tutti i PAC associati allo stesso titolo. Non modifica il prezzo storico delle rilevazioni, non crea investimenti e non alimenta la liquidità virtuale.

### Aggiornamento dalla rilevazione mensile

La rilevazione conserva la data esatta del prezzo, distinta dal periodo del PAC. La data del prezzo determina l'ordine cronologico delle quotazioni; a parità di data prevale l'ultimo prezzo inserito o importato. Per l'MVP non è richiesto l'orario del prezzo.

Al salvataggio di una nuova rilevazione, prezzo e data aggiornano automaticamente la quotazione condivisa se questa non è ancora presente oppure se la data del prezzo è più recente o uguale a quella conservata sul titolo. A parità di data, un prezzo differente sostituisce quello corrente senza richiedere conferma e senza calcolare medie automatiche. Una rilevazione inserita o importata con data del prezzo precedente non sovrascrive una quotazione più recente.

La quotazione condivisa viene usata per il valore indicativo di tutte le quote dello stesso titolo in tutti i PAC associati. L'aggiornamento non modifica i prezzi storici né i risultati e i saldi delle rilevazioni già salvate.

La correzione dell'ultima rilevazione modifica il prezzo storico e i risultati di quella rilevazione, senza aggiornare automaticamente la quotazione condivisa. Quest'ultima rimane gestibile separatamente tramite l'aggiornamento del solo prezzo e della relativa data, senza creare un investimento.

L'azione ordinaria di aggiornamento della quotazione non accetta una data precedente a quella corrente. La modalità esplicita **Correggi quotazione corrente** permette invece di correggere la coppia prezzo/data dopo un riepilogo e una conferma, anche retrodatando una data inserita per errore. Entrambe operano su `latest_price` e `latest_price_date`, senza aggiungere campi o righe storiche e senza modificare gli investimenti. Una modifica concorrente della quotazione richiede di verificare il valore aggiornato prima di applicare la richiesta precedente.

## 3. PAC e situazione di apertura

Il PAC contiene almeno il riferimento al titolo, l'incremento mensile fisso, il calendario del piano, lo stato e l'eventuale collegamento al PAC precedente.

### Campi richiesti per un PAC da zero

| Campo | Nome tecnico | Regola |
| --- | --- | --- |
| Titolo | `instrument_id` | Obbligatorio, selezionato dal catalogo condiviso. |
| Incremento mensile | `monthly_increment` | Obbligatorio, maggiore di zero, massimo due decimali; memorizzato in centesimi. Resta fisso durante il piano. |
| Mese di inizio | `start_period` | Obbligatorio, mese e anno; precompilato con il mese corrente. |

### Campi aggiuntivi per un PAC con saldo iniziale

| Campo | Nome tecnico | Regola |
| --- | --- | --- |
| Ultimo mese coperto | `opening_period` | Obbligatorio per il saldo iniziale; mese e anno uguali o successivi all'inizio originario. |
| Quote già possedute | `opening_quantity` | Obbligatorio per il saldo iniziale; numero intero non negativo. |
| Capitale già investito | `opening_invested_amount` | Obbligatorio per il saldo iniziale; importo non negativo, massimo due decimali, memorizzato in centesimi. |
| Liquidità virtuale iniziale | `opening_virtual_liquidity` | Importo non negativo, massimo due decimali, memorizzato in centesimi; predefinito a zero. |

### Dati calcolati e gestiti dall'applicazione

L'obiettivo cumulativo di apertura viene calcolato automaticamente: è zero per un PAC da zero, deriva dal calendario originario e dall'incremento mensile per un saldo iniziale, oppure viene ereditato dal predecessore in caso di prosecuzione.

Utente proprietario, stato attivo/chiuso, eventuale PAC precedente e mese di chiusura sono gestiti dall'applicazione. Non richiedono campi aggiuntivi nel modulo ordinario. Identificativi interni e timestamp sono anch'essi automatici.

### Modalità di apertura e continuità

La stessa struttura copre:

- **Partenza da zero:** saldi iniziali a zero.
- **PAC esistente con saldo di apertura:** situazione dichiarata dall'utente e obiettivo coerente con il calendario originario.
- **Prosecuzione:** saldi ereditati dal PAC precedente alla chiusura.

Il mese del saldo iniziale indica l'ultimo mese già coperto dalla posizione inserita. Le nuove registrazioni partono dal mese successivo. La data originale di inizio e l'incremento mensile permettono di calcolare l'obiettivo cumulativo già previsto dal piano a quella data.

Per esempio, con partenza a gennaio e incremento di 750 euro, un saldo riferito a ottobre ha un obiettivo cumulativo di 7.500 euro; la prima nuova rilevazione è novembre, con obiettivo di 8.250 euro. Quote, capitale investito e liquidità virtuale di apertura descrivono tutti la situazione dopo la rilevazione di ottobre.

Il saldo di apertura non è un investimento. Non introduce un acquisto fittizio né un nuovo incremento del contatore virtuale.

La relazione con il predecessore conserva la continuità del piano. Nella panoramica corrente i saldi trasferiti sono conteggiati una sola volta tramite il piano attivo della catena.

### Vincoli e modificabilità

- Sono consentiti più PAC sullo stesso titolo, con incrementi, obiettivi e quantità indipendenti.
- L'incremento mensile resta fisso: per cambiarlo si crea un PAC successore indicando il nuovo incremento e il mese di decorrenza.
- Dopo la prima rilevazione, titolo, mese iniziale e saldi di apertura diventano in sola lettura.
- Il blocco della configurazione è conservato anche se vengono successivamente eliminate tutte le rilevazioni, tramite `configuration_locked_at`.
- Prima del blocco, un PAC attivo senza predecessore e senza rilevazioni può correggere titolo, inizio e situazione di apertura; l'obiettivo iniziale viene ricalcolato. L'incremento resta fisso anche in questa fase.
- Nel successore, titolo e saldi ereditati sono bloccati fin dalla creazione.
- Un PAC può avere un solo successore: la catena di prosecuzione non ammette diramazioni.
- Il successore entra in funzione con un passaggio immediato e decorrenza non futura, successiva a inizio, saldo di apertura e ultima rilevazione del predecessore quando presenti. La chiusura del predecessore è il mese precedente alla nuova decorrenza; non è previsto uno stato programmato.
- Un PAC è eliminabile soltanto se non contiene rilevazioni e non è collegato a una catena di prosecuzione.
- Un PAC attivo autonomo senza rilevazioni resta eliminabile anche se la configurazione era stata bloccata; il blocco impedisce la modifica della configurazione, non aggiunge un vincolo alla cancellazione del PAC vuoto.
- I PAC chiusi e tutte le loro rilevazioni sono in sola lettura.

I saldi correnti si leggono dall'ultima rilevazione oppure dalla situazione di apertura quando non ci sono ancora registrazioni. Il valore indicativo corrente dei titoli usa invece l'ultima quotazione condivisa nell'anagrafica del titolo.

## 4. Rilevazione mensile

Ogni rilevazione appartiene a un solo PAC. Per ciascun PAC è ammessa una sola registrazione per mese e anno.

Il periodo di una nuova rilevazione deve essere strettamente successivo al periodo dell'ultima rilevazione salvata nello stesso PAC. Il confronto usa mese e anno del periodo, non la data del prezzo né l'ordine di inserimento. Non sono consentiti inserimenti intermedi o retroattivi rispetto all'ultima rilevazione salvata.

In assenza di rilevazioni, il periodo deve essere uguale o successivo al mese di inizio del PAC da zero, oppure successivo all'ultimo mese coperto dal saldo di apertura. Si possono saltare mesi, che rimangono non rilevati.

### Campi del modulo

| Campo | Nome tecnico | Regola |
| --- | --- | --- |
| PAC | `plan_id` | Determinato dal dettaglio del PAC da cui si apre il modulo; il titolo deriva dal PAC. Bloccato dopo il salvataggio. |
| Periodo | `period` | Mese e anno obbligatori; univoco nel PAC e strettamente successivo all'ultima rilevazione salvata, se presente. In assenza di rilevazioni rispetta il calendario di apertura. Bloccato dopo il salvataggio. |
| Prezzo unitario | `unit_price` | Obbligatorio, maggiore di zero, fino a sei decimali. |
| Data del prezzo | `price_date` | Data esatta obbligatoria, distinta dal periodo; precompilata con la data odierna alla creazione del modulo e selezionabile dall'utente. Bloccata dopo il salvataggio. |
| Quote effettivamente acquistate | `purchased_quantity` | Obbligatorio, numero intero non negativo; precompilato con le quote proposte e confermato dall'utente. |

Il prezzo inserito è unico per rilevazione e acquisto. Le quote effettive possono differire da quelle proposte e devono rappresentare ciò che è stato realmente acquistato. Le commissioni sono escluse dalla prima versione.

Zero quote acquistate è un valore valido: il salvataggio conclude la rilevazione mensile anche senza un acquisto. L'anteprima non modifica i saldi.

### Dati automatici e fotografia del calcolo

Le quote già possedute derivano dall'ultima rilevazione o dal saldo di apertura. L'obiettivo cumulativo deriva dal calendario e dall'incremento mensile del PAC. Valori dei titoli, importo teorico VA, quote e controvalore proposti, controvalore effettivo, incremento della liquidità virtuale e saldi risultanti sono calcolati automaticamente. L'anteprima mostra anche la segnalazione informativa sul range dell'investimento.

La rilevazione conserva gli input e la fotografia dei risultati necessari per leggere lo storico, fra cui:

- periodo, prezzo storico e data del prezzo;
- quote possedute prima dell'operazione;
- obiettivo cumulativo del mese;
- valore dei titoli prima dell'acquisto e importo teorico VA;
- quote proposte e quote effettive;
- controvalore dell'acquisto;
- incremento della liquidità virtuale;
- saldi risultanti dopo l'operazione.

La fotografia del calcolo è distinta dalla quotazione corrente condivisa sul titolo.

### Modifica dell'ultima rilevazione

Soltanto l'ultima rilevazione in ordine cronologico di un PAC attivo è modificabile. Gli unici campi modificabili sono prezzo unitario e quote effettivamente acquistate; PAC, periodo e data del prezzo rimangono bloccati. I dati calcolati non sono modificabili direttamente.

La modifica parte dalla situazione precedente già nota e ricalcola i risultati e i saldi dell'ultima rilevazione, senza ricalcolare le rilevazioni pregresse. Il contributo alla liquidità virtuale viene sostituito, senza accumularlo una seconda volta. Le rilevazioni precedenti e tutte quelle dei PAC chiusi rimangono in sola lettura, come previsto dalla [specifica funzionale](funzionamento.md#10-modificabilità-dello-storico).

### Eliminazione dell'ultima rilevazione

Soltanto l'ultima rilevazione in ordine cronologico di un PAC attivo è eliminabile, con conferma esplicita nell'app. Non sono eliminabili le rilevazioni precedenti né quelle di un PAC chiuso.

I saldi correnti tornano a quelli della rilevazione precedente oppure ai saldi di apertura se non rimangono registrazioni: quote possedute, capitale investito e liquidità virtuale non comprendono più il contributo della rilevazione eliminata. Lo storico precedente non viene ricalcolato.

Il periodo eliminato torna non rilevato e può essere nuovamente registrato nel rispetto dei vincoli del PAC. L'eliminazione non modifica il calendario degli obiettivi né prezzo e data della quotazione corrente condivisa sul titolo. È una correzione dei dati dell'applicazione, non una vendita o l'annullamento dell'acquisto presso il broker.

Per inserire un mese dimenticato occorre prima eliminare le rilevazioni successive, procedendo ogni volta dall'ultima di un PAC attivo, e poi reinserire i dati in ordine cronologico. Non vengono inserite rilevazioni fra quelle già salvate né riscritte automaticamente le registrazioni successive.

### Importazione e mappatura delle colonne (fase successiva)

L'importazione è esclusa dal primo MVP. I dettagli qui conservati sono un riferimento per una fase successiva e non richiedono ora modelli, tabelle o flussi dedicati all'importazione. L'MVP gestisce le rilevazioni manuali e i saldi di apertura già descritti.

La scelta discussa per la futura importazione è l'importer nativo di Filament con colonne CSV mappabili. Le intestazioni del file possono essere associate a `period`, `unit_price`, `price_date` e `purchased_quantity`; `plan_id` deriva dal PAC attivo selezionato e non dal file. Il perimetro inizialmente proposto richiede un PAC senza rilevazioni e il rispetto dell'eventuale saldo di apertura.

Tutte le righe vengono validate e ordinate per periodo prima dell'anteprima. Periodi duplicati, campi richiesti mancanti e valori incompatibili con i vincoli impediscono l'importazione completa. I mesi assenti non generano rilevazioni. La data del prezzo deve essere presente per ogni riga, senza assegnare automaticamente la data odierna ai dati storici.

L'anteprima ricostruisce in sequenza le fotografie del calcolo e i saldi a partire dalla situazione di apertura. I risultati già calcolati nel foglio non vengono importati come saldi autorevoli. Il salvataggio confermato comprende tutte le rilevazioni e gli aggiornamenti della quotazione condivisa previsti dalle relative regole, oppure non applica alcuna modifica. Non sono previsti inserimenti parziali, aggiornamenti di rilevazioni esistenti o riconciliazioni dello storico precedente al saldo di apertura.

## 5. Rappresentazione economica

- Importi monetari in centesimi, memorizzati come interi con Laravel Money e `MoneyIntegerCast`.
- Prezzi unitari fino a sei decimali, memorizzati come interi a scala `1.000.000` tramite cast dedicato.
- Quote intere.
- Calcoli con la precisione prevista e arrotondamento dei risultati monetari ai centesimi alla fine, con metà verso l'alto.
- Arrotondamento delle quote proposte all'intero più vicino, con metà all'intero superiore per i valori non negativi.

La riduzione monetaria ai centesimi non deve precedere il calcolo del rapporto che determina le quote proposte.

## 6. Schema fisico dell'MVP

Si mantengono `users` e le tabelle tecniche già presenti in Laravel. Si aggiungono soltanto `instruments`, `plans` e `investments`, associate rispettivamente ai modelli `Instrument`, `Plan` e `Investment`. Il catalogo titoli è condiviso nell'applicazione personale; il proprietario del PAC è registrato in `plans.user_id`.

### Convenzioni comuni

- Ogni nuova tabella ha `id` numerico automatico come chiave primaria e i timestamp `created_at` e `updated_at`.
- I campi sono obbligatori salvo quelli indicati esplicitamente come nullable.
- Gli importi monetari sono interi `BIGINT` in centesimi. I prezzi sono interi `BIGINT` a scala `1.000.000`; non si usano `FLOAT` per valori economici.
- Le quantità sono interi `BIGINT` non negativi. In SQLite gli interi sono a 64 bit; la definizione delle migration mantiene la compatibilità con l'eventuale passaggio a MySQL.
- I campi che rappresentano un periodo sono `DATE` normalizzate al primo giorno del mese, per esempio `2026-11-01`. Non sono date di esecuzione.
- `price_date` e `latest_price_date` conservano invece il giorno effettivo del prezzo.
- Non sono previsti soft delete per le nuove tabelle: le eliminazioni consentite rispettano i vincoli del dominio e le chiavi esterne restrittive.

### Tabella `instruments`

| Campo | Tipo logico | Vincoli e significato |
| --- | --- | --- |
| `id` | ID intero | Chiave primaria automatica. |
| `ticker` | `VARCHAR(32)` | Obbligatorio, univoco, in maiuscolo senza spazi iniziali o finali. |
| `name` | `VARCHAR(255)` | Obbligatorio; nomi duplicati consentiti. |
| `latest_price` | `BIGINT`, nullable | Prezzo corrente a sei decimali; maggiore di zero quando presente. |
| `latest_price_date` | `DATE`, nullable | Data della quotazione corrente. |
| `created_at`, `updated_at` | Timestamp | Gestiti dall'applicazione. |

Prezzo e data devono essere entrambi presenti oppure entrambi assenti. Il ticker non sostituisce la chiave primaria interna. Non si introduce una tabella separata per lo storico delle quotazioni: i prezzi delle rilevazioni rimangono in `investments`.

### Tabella `plans`

| Campo | Tipo logico | Vincoli e significato |
| --- | --- | --- |
| `id` | ID intero | Chiave primaria automatica. |
| `user_id` | FK → `users.id` | Proprietario obbligatorio. |
| `instrument_id` | FK → `instruments.id` | Titolo obbligatorio. |
| `monthly_increment` | `BIGINT` | Centesimi; maggiore di zero e fisso durante il piano. |
| `start_period` | `DATE` | Mese di inizio del piano. |
| `status` | `VARCHAR(16)` | Valori ammessi `active` e `closed`; predefinito `active`. |
| `previous_plan_id` | FK → `plans.id`, nullable | Univoco quando presente; ammette un solo successore per predecessore. |
| `opening_period` | `DATE`, nullable | Ultimo mese coperto dalla situazione di apertura. |
| `opening_quantity` | `BIGINT` | Quote iniziali non negative; predefinito zero. |
| `opening_invested_amount` | `BIGINT` | Capitale iniziale in centesimi, non negativo; predefinito zero. |
| `opening_target_amount` | `BIGINT` | Obiettivo cumulativo iniziale in centesimi, non negativo; predefinito zero. |
| `opening_virtual_liquidity` | `BIGINT` | Saldo virtuale iniziale in centesimi, non negativo; predefinito zero. |
| `closed_period` | `DATE`, nullable | Ultimo mese coperto dal PAC chiuso. |
| `configuration_locked_at` | `DATETIME`, nullable | Momento dal quale titolo, inizio e saldi di apertura non sono più modificabili. |
| `created_at`, `updated_at` | Timestamp | Gestiti dall'applicazione. |

Le modalità di apertura sono rappresentate senza una tabella o un campo tipo aggiuntivo:

- **Da zero:** `previous_plan_id` e `opening_period` null, saldi di apertura a zero.
- **Con saldo iniziale:** `previous_plan_id` null e `opening_period` valorizzato, uguale o successivo all'inizio originario; obiettivo iniziale calcolato dal calendario.
- **Successore:** `previous_plan_id` valorizzato, saldi ereditati e `opening_period` uguale al mese precedente al nuovo `start_period`, coincidente con la chiusura del predecessore. L'obiettivo ereditato considera anche i mesi non rilevati fino alla chiusura.

Il vincolo che il mese di apertura non preceda l'inizio vale per il saldo iniziale di un PAC esistente, non per il successore, la cui apertura precede la nuova decorrenza. Non si aggiunge `next_plan_id`: il successore si individua tramite `previous_plan_id`.

La creazione del successore è immediata e accetta soltanto una decorrenza non futura e successiva a `start_period`, `opening_period` e ultimo `investments.period` del predecessore quando presenti. Il predecessore chiude nel mese precedente alla decorrenza; il successore eredita la posizione e l'obiettivo maturato fino alla chiusura. Non sono necessari uno stato programmato, nuove tabelle o attività pianificate.

`configuration_locked_at` viene impostato al primo salvataggio di una rilevazione oppure subito alla creazione di un successore. Non viene azzerato eliminando le rilevazioni. L'incremento mensile rimane fisso indipendentemente da questo campo.

Non si duplicano su `plans` i saldi correnti di quote, capitale e liquidità virtuale: si leggono dall'ultima rilevazione o dai campi di apertura. Per un PAC chiuso, i saldi e le valorizzazioni storiche restano consultabili senza usare la quotazione corrente del titolo.

### Tabella `investments`

| Campo | Tipo logico | Vincoli e significato |
| --- | --- | --- |
| `id` | ID intero | Chiave primaria automatica. |
| `plan_id` | FK → `plans.id` | PAC obbligatorio. |
| `period` | `DATE` | Mese della rilevazione; univoco insieme a `plan_id`. |
| `unit_price` | `BIGINT` | Prezzo a sei decimali, maggiore di zero. |
| `price_date` | `DATE` | Giorno effettivo del prezzo, obbligatorio. |
| `quantity_before` | `BIGINT` | Quote possedute prima, non negative. |
| `target_amount` | `BIGINT` | Obiettivo cumulativo del mese in centesimi, non negativo. |
| `portfolio_value_before` | `BIGINT` | Valore prima dell'acquisto in centesimi, non negativo. |
| `theoretical_investment` | `BIGINT` con segno | Importo teorico VA in centesimi; può essere negativo. |
| `proposed_quantity` | `BIGINT` | Quote proposte, non negative. |
| `proposed_amount` | `BIGINT` | Controvalore proposto in centesimi, non negativo. |
| `purchased_quantity` | `BIGINT` | Quote effettivamente acquistate, non negative. |
| `purchased_amount` | `BIGINT` | Controvalore effettivo in centesimi, non negativo. |
| `quantity_after` | `BIGINT` | Quote risultanti, non negative. |
| `invested_amount_after` | `BIGINT` | Capitale investito risultante in centesimi, non negativo. |
| `virtual_liquidity_increase` | `BIGINT` | Incremento virtuale del mese in centesimi, non negativo. |
| `virtual_liquidity_after` | `BIGINT` | Saldo virtuale risultante in centesimi, non negativo. |
| `range_status` | `VARCHAR(32)` | `in_range`, `above_increment` o `virtual_liquidity`. |
| `rounding_exceeds_increment` | `BOOLEAN` | Indica il superamento dell'incremento dovuto all'arrotondamento delle quote proposte. |
| `created_at`, `updated_at` | Timestamp | Gestiti dall'applicazione. |

Gli input, i risultati e le segnalazioni sono obbligatori. Le operazioni applicative compilano l'intera fotografia del calcolo; i risultati non vengono accettati come valori autorevoli dal browser. Non si replicano `user_id` o `instrument_id`: si ricavano da `plan_id`.

Le segnalazioni e le quote proposte sono determinate con la precisione completa prima dell'arrotondamento monetario. `range_status` distingue l'importo teorico negativo, quello compreso fra zero e incremento inclusi e quello superiore all'incremento. `rounding_exceeds_increment` identifica il caso in cui l'importo teorico è nel range ma il controvalore proposto supera l'incremento per effetto dell'arrotondamento delle quote. Non si ricava la classificazione dai soli importi già arrotondati ai centesimi, né si riduce anticipatamente la precisione per calcolare le quantità.

### Indici e vincoli nel database

- `UNIQUE(instruments.ticker)` con controllo della normalizzazione del ticker.
- `UNIQUE(investments.plan_id, investments.period)` per una sola rilevazione mensile per PAC.
- `UNIQUE(plans.previous_plan_id)` sui valori non null, con più null consentiti.
- Chiavi esterne restrittive per `plans.user_id`, `plans.instrument_id`, `plans.previous_plan_id` e `investments.plan_id`, senza cancellazioni a cascata dello storico.
- Controlli locali alla riga su prezzi positivi, quantità e importi non negativi salvo `theoretical_investment`, valori ammessi di stato e segnalazione, presenza congiunta di quotazione/data e periodi normalizzati al primo giorno del mese.
- Coerenza fra stato e chiusura: `active` richiede `closed_period` null, `closed` richiede un periodo di chiusura.
- Indice `plans(user_id, status)` per la dashboard e indice `plans(instrument_id)` per i PAC associati a un titolo.

L'indice composto delle rilevazioni serve anche alla ricerca dell'ultimo periodo del PAC; non si aggiunge un indice ridondante sul solo `plan_id`. I controlli numerici e temporali dovranno essere effettivamente verificati su SQLite, senza affidarsi soltanto al nome del tipo della colonna. L'eventuale passaggio a MySQL dovrà conservare gli stessi invarianti.

### Vincoli delle operazioni applicative

Le regole che richiedono lo stato corrente o più record vengono applicate sul server, con rilettura dello stato e transazioni:

- nuovo periodo successivo all'ultima rilevazione e compatibile con l'apertura;
- modifica o eliminazione soltanto dell'ultima rilevazione cronologica di un PAC attivo;
- rispetto del blocco permanente della configurazione e dell'incremento fisso;
- impossibilità di cancellare PAC con rilevazioni o collegamenti di prosecuzione;
- predecessore dello stesso proprietario e titolo, assenza di cicli e continuità dei periodi e dei saldi;
- chiusura del predecessore e creazione del successore come operazione unica;
- ricalcolo autorevole della fotografia mensile e aggiornamento della quotazione condivisa secondo data e parità.

Le verifiche non dipendono dall'ordine visivo delle righe o dai dati di una precedente anteprima. La gestione della concorrenza deve essere compatibile con SQLite e impedire che due schermate aperte contemporaneamente creino saldi incoerenti. Non si assume che il solo blocco dell'interfaccia protegga questi vincoli.

Non si aggiungono tabelle dedicate a liquidità virtuale, importazioni, esportazioni o backup per il primo MVP.

## 7. Stato dell’implementazione

Struttura concettuale, schema fisico, ticker univoco con nome libero e limiti di modifica sono confermati. L'[elenco dei flussi applicativi e i flussi dettagliati](funzionamento.md#13-flussi-applicativi-dellmvp) sono documentati nella specifica funzionale. La gestione dei titoli conserva l'ID interno anche quando si corregge il ticker e impedisce di eliminare titoli collegati a PAC. L'apertura assegna il proprietario autenticato e calcola i saldi iniziali senza creare investimenti o aggiornare la quotazione. Il salvataggio mensile verifica lo stato corrente, conserva le quote effettive dell'utente e applica rilevazione, blocco iniziale della configurazione e aggiornamento ammesso della quotazione nella stessa transazione; un'anteprima superata richiede un nuovo controllo prima del salvataggio.

Tutti i flussi dell'MVP sono dettagliati nella sezione 13 della specifica funzionale. Migration, modelli, cast, calcolatore, operazioni applicative e schermate sono implementati e verificati con test sui comportamenti concordati. I cambi di incremento programmati per mesi futuri non fanno parte dell'MVP: il successore usa soltanto il passaggio immediato.

Importazione, esportazione e ripristino dei dati tramite l'applicazione saranno ripresi dopo l'MVP; i relativi formati e flussi non sono decisioni necessarie per implementare il primo MVP. Il backup tecnico del database resta separato dalle funzionalità applicative di esportazione e ripristino.

Lo schema dell’MVP è implementato nelle migration del 12 novembre 2026. I test verificano su SQLite unicità, chiavi esterne restrittive, interi effettivi, date valide, arrotondamenti e continuità dei saldi. I timestamp tecnici `created_at` e `updated_at` seguono la convenzione nullable di Laravel; i campi di dominio mantengono la nullabilità indicata nelle tabelle.
