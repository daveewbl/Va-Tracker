# VA Tracker

Dashboard personale per tracciare piani di accumulo in value averaging (VA).

Il progetto registra gli investimenti effettuati, calcola le quantità proposte dal VA e confronta il valore dei titoli con un obiettivo mensile cumulativo. Non prevede vendite: gli importi teorici negativi alimentano un contatore di **liquidità virtuale**, separato dal patrimonio e dai calcoli delle rate successive.

## Stato del progetto

Al 6 novembre 2026 è stato concordato il funzionamento principale. Questo repository contiene la documentazione iniziale; lo stack tecnologico e l'implementazione devono ancora essere definiti.

## Documentazione

- [Funzionamento concordato](docs/funzionamento.md): terminologia, calcoli, registrazioni, liquidità virtuale, avvio e prosecuzione dei PAC, vincoli sullo storico e dettagli ancora da definire.
- [Esempi e scenari funzionali](docs/scenari.md): casi numerici e comportamenti attesi da usare come riferimento durante la progettazione e l'implementazione.

La specifica funzionale è il riferimento per le regole del prodotto. Gli esempi la illustrano senza introdurre regole aggiuntive.

## Percorso di lavoro

1. Completare gli eventuali dettagli funzionali rimasti aperti.
2. Concordare stack tecnologico, persistenza dei dati e modalità di utilizzo.
3. Implementare la prima versione essenziale.

## Riferimenti

Il brainstorming iniziale ha utilizzato il foglio personale `PAC - SWDA - Value Averaging.xlsx`. Il file è un riferimento per comprendere il metodo utilizzato: le regole concordate in seguito prevalgono sulle formule del foglio. Il file originale e i dati personali non sono inclusi nel repository.
