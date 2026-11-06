# VA Tracker

Dashboard personale per tracciare piani di accumulo in value averaging (VA).

Il progetto registra gli investimenti effettuati, calcola le quantità proposte dal VA e confronta il valore dei titoli con un obiettivo mensile cumulativo. Non prevede vendite: gli importi teorici negativi alimentano un contatore di **liquidità virtuale**, separato dal patrimonio e dai calcoli delle rate successive.

## Stato del progetto

Al 6 novembre 2026 sono stati concordati il funzionamento principale, lo stack tecnologico e la struttura concettuale dei dati. Lo scaffolding Laravel/Filament è presente; schema e flussi del dominio PAC sono ancora in progettazione.

## Documentazione

- [Funzionamento concordato](docs/funzionamento.md): terminologia, calcoli, registrazioni, liquidità virtuale, avvio e prosecuzione dei PAC, vincoli sullo storico e dettagli ancora da definire.
- [Esempi e scenari funzionali](docs/scenari.md): casi numerici e comportamenti attesi da usare come riferimento durante la progettazione e l'implementazione.
- [Stack tecnico](docs/stack-tecnico.md): componenti, persistenza, precisione dei dati economici, convenzioni Laravel e strumenti di sviluppo.
- [Struttura dati](docs/struttura-dati.md): titolo condiviso, PAC, situazione di apertura, rilevazioni mensili e quotazioni.

La specifica funzionale è il riferimento per le regole del prodotto. Gli esempi la illustrano senza introdurre regole aggiuntive.

## Percorso di lavoro

1. Completare gli eventuali dettagli funzionali rimasti aperti.
2. Progettare lo schema dei dati e verificare le dipendenze dello stack concordato.
3. Implementare la prima versione essenziale.

## Riferimenti

Il brainstorming iniziale ha utilizzato il foglio personale `PAC - SWDA - Value Averaging.xlsx`. Il file è un riferimento per comprendere il metodo utilizzato: le regole concordate in seguito prevalgono sulle formule del foglio. Il file originale e i dati personali non sono inclusi nel repository.
