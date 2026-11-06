# Struttura dati di VA Tracker

Stato: struttura concettuale concordata nel brainstorming del 6 novembre 2026. Modelli, migration e flussi del dominio devono ancora essere progettati nel dettaglio e implementati.

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

Il titolo contiene nome, eventuali identificativi come ticker o ISIN, ultima quotazione e relativa data. Il dettaglio dei campi obbligatori sarà fissato nel passaggio successivo di progettazione.

L'aggiornamento della quotazione corrente rivaluta tutti i PAC associati allo stesso titolo. Non modifica il prezzo storico delle rilevazioni, non crea investimenti e non alimenta la liquidità virtuale.

## 3. PAC e situazione di apertura

Il PAC contiene almeno il riferimento al titolo, l'incremento mensile fisso, il calendario del piano, lo stato e l'eventuale collegamento al PAC precedente.

La situazione di apertura comprende:

- quote già possedute;
- capitale già investito;
- obiettivo cumulativo di apertura;
- liquidità virtuale iniziale;
- periodo a cui si riferiscono i saldi.

La stessa struttura copre:

- **Partenza da zero:** saldi iniziali a zero.
- **PAC esistente con saldo di apertura:** situazione dichiarata dall'utente e obiettivo coerente con il calendario originario.
- **Prosecuzione:** saldi ereditati dal PAC precedente alla chiusura.

Il mese del saldo iniziale indica l'ultimo mese già coperto dalla posizione inserita. Le nuove registrazioni partono dal mese successivo. La data originale di inizio e l'incremento mensile permettono di calcolare l'obiettivo cumulativo già previsto dal piano a quella data.

Per esempio, con partenza a gennaio e incremento di 750 euro, un saldo riferito a ottobre ha un obiettivo cumulativo di 7.500 euro; la prima nuova rilevazione è novembre, con obiettivo di 8.250 euro. Quote, capitale investito e liquidità virtuale di apertura descrivono tutti la situazione dopo la rilevazione di ottobre.

Il saldo di apertura non è un investimento. Non introduce un acquisto fittizio né un nuovo incremento del contatore virtuale.

La relazione con il predecessore conserva la continuità del piano. Nella panoramica corrente i saldi trasferiti sono conteggiati una sola volta tramite il piano attivo della catena.

## 4. Rilevazione mensile

Ogni rilevazione appartiene a un solo PAC. Per ciascun PAC è ammessa una sola registrazione per mese e anno.

Il prezzo inserito è unico per rilevazione e acquisto. Le quote effettive possono differire da quelle proposte e devono rappresentare ciò che è stato realmente acquistato. Le commissioni sono escluse dalla prima versione.

La rilevazione conserva gli input e la fotografia dei risultati necessari per leggere lo storico, fra cui:

- periodo e prezzo storico;
- quote possedute prima dell'operazione;
- obiettivo cumulativo del mese;
- valore dei titoli prima dell'acquisto e importo teorico VA;
- quote proposte e quote effettive;
- controvalore dell'acquisto;
- incremento della liquidità virtuale;
- saldi risultanti dopo l'operazione.

La fotografia del calcolo è distinta dalla quotazione corrente condivisa sul titolo.

La modifica dell'ultima registrazione parte dalla situazione precedente già nota, senza ricalcolare le rilevazioni pregresse. I vincoli sullo storico rimangono quelli della [specifica funzionale](funzionamento.md).

## 5. Rappresentazione economica

- Importi monetari in centesimi, memorizzati come interi con Laravel Money e `MoneyIntegerCast`.
- Prezzi unitari fino a sei decimali, memorizzati come interi a scala `1.000.000` tramite cast dedicato.
- Quote intere.
- Calcoli con la precisione prevista e arrotondamento dei risultati monetari ai centesimi alla fine, con metà verso l'alto.
- Arrotondamento delle quote proposte all'intero più vicino, con metà all'intero superiore per i valori non negativi.

La riduzione monetaria ai centesimi non deve precedere il calcolo del rapporto che determina le quote proposte.

## 6. Passaggio successivo di progettazione

La struttura qui descritta è confermata. Rimangono da definire i campi fisici e i relativi vincoli, in particolare:

- identificativi obbligatori del titolo e gestione dei duplicati;
- campi modificabili nell'ultima rilevazione;
- aggiornamento della quotazione condivisa quando si registra o importa un investimento passato;
- operazioni ancora aperte nella specifica funzionale, compresi importazione e gestione dei mesi mancanti.

Lo scaffolding tecnico non implementa ancora queste entità del dominio.
