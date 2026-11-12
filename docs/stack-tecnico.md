# Stack tecnico di VA Tracker

Stato: decisioni tecniche concordate nel brainstorming del 6 novembre 2026.

Questo documento descrive lo stack scelto. Lo scaffolding tecnico è ora presente nel repository, con dipendenze installate, panel Filament, tema dedicato, SQLite e accesso locale. Lo schema fisico, il dominio PAC e le schermate dell’MVP sono implementati. Le regole del prodotto e tutti i flussi dell'MVP sono definiti nella [specifica funzionale](funzionamento.md), con la [struttura dei dati e lo schema fisico](struttura-dati.md) concordati.

## 1. Impostazione generale

VA Tracker sarà un'unica applicazione Laravel, utilizzata inizialmente in locale con Herd. Filament sarà l'interfaccia completa dell'applicazione, con un tema dedicato.

Le schermate gestiranno presentazione e interazione. Le Actions applicative gestiranno le operazioni del PAC; il calcolo VA sarà condiviso tra anteprima e salvataggio, e potrà essere riutilizzato per una futura importazione.

Le versioni effettive sono registrate in `composer.lock` e `package-lock.json`.

## 2. Componenti e dipendenze

| Componente | Famiglia di versione di riferimento | Ruolo |
| --- | --- | --- |
| PHP | 8.4 | Runtime locale di Herd. |
| Laravel | 13.x | Framework, Eloquent, autenticazione, autorizzazioni e transazioni. |
| Filament | 5.x | Dashboard, pagine dei PAC, storico, form e tema dedicato. |
| Livewire | 4.x | Interazioni reattive e anteprima del calcolo nei form. |
| Tailwind CSS | 4.1 o successiva compatibile con Filament 5 | Stile del tema e delle viste personalizzate. |
| `lorisleiva/laravel-actions` | 2.x | Operazioni applicative riutilizzabili. |
| `spatie/laravel-data` | 4.x | Input tipizzati delle operazioni e risultati del calcolo. |
| `cknow/laravel-money` | 8.x compatibile con Laravel 13 | Oggetti monetari e cast intero degli importi. |
| `laravel/ai` | 1.x | SDK per future funzionalità AI dell'applicazione. |
| `laravel/mcp` | 1.x | Base per eventuali strumenti e integrazioni MCP dell'applicazione. |
| `laravel/boost` | 2.x; dipendenza di sviluppo | Strumenti e contesto per assistere lo sviluppo. |
| `spatie/laravel-login-link` | 1.x; dipendenza di sviluppo | Link di accesso rapido nel solo ambiente locale. |

Le famiglie indicate descrivono le scelte del brainstorming. L'installazione ha risolto Laravel 13.40.0, Filament 5.11.1 e Livewire 4.4.8; il riferimento completo per le versioni è nei lockfile. La baseline PHP locale è 8.4. La compatibilità tra le dipendenze deve essere mantenuta anche nei successivi aggiornamenti.

Livewire e Tailwind fanno già parte della base tecnologica di Filament; saranno usati all'interno della stessa applicazione.

### Strumenti di sviluppo

Pest per i test automatici e Laravel Pint per la formattazione del codice sono installati come dipendenze di sviluppo. Le verifiche del dominio saranno aggiunte insieme alle relative funzionalità.

## 3. Organizzazione della logica

### Filament e Livewire

Filament ospiterà la dashboard, l'elenco dei PAC, il dettaglio del singolo piano, lo storico e il modulo della rilevazione mensile.

La dashboard iniziale seguirà la struttura della [specifica funzionale](funzionamento.md#dashboard-dellmvp): riepilogo dei PAC attivi, liquidità virtuale separata, tabella centrale e azioni per creare PAC, gestire quotazioni e registrare rilevazioni. Le scelte di presentazione sono delegate e seguiranno il tema dedicato già predisposto. L'interfaccia gestirà stato senza PAC, valori non disponibili e totali parziali, mostrando la data delle quotazioni e adattandosi agli schermi piccoli. Non sono richiesti un nuovo nome del PAC né un grafico aggregato nella dashboard.

Il [dettaglio del PAC](funzionamento.md#dettaglio-del-pac-nellmvp) avrà intestazione e azioni, riepilogo dei saldi, grafico obiettivo/valore storico prima dell'acquisto, tabella delle rilevazioni e sezione secondaria per configurazione e apertura. Anche queste scelte di presentazione sono delegate. Grafico e storico useranno le fotografie salvate delle rilevazioni, senza applicare retroattivamente la quotazione corrente né interpolare valori nei mesi non rilevati. Il saldo iniziale resterà distinto dagli investimenti.

La disponibilità delle azioni nel dettaglio seguirà i vincoli applicativi: modifica ed eliminazione soltanto dell'ultima rilevazione cronologica di un PAC attivo, con soli prezzo e quote modificabili; creazione di un unico successore; PAC chiusi in sola lettura con saldi e valorizzazioni storiche conservati. I controlli non dipenderanno dall'ordinamento visivo della tabella. Il layout gestirà anche assenza di rilevazioni, prezzi mancanti e schermi piccoli, senza introdurre nuovi campi di dominio.

L'inserimento del prezzo aggiornerà l'anteprima del valore dei titoli, dell'importo teorico VA, delle quote proposte, del controvalore e della segnalazione. Una pagina personalizzata all'interno del panel potrà essere usata per rendere chiaro questo flusso.

Il salvataggio ricalcolerà i risultati sul server usando lo stato corrente del PAC. I risultati mostrati nel browser non saranno usati come fonte autorevole dei saldi.

Il [flusso di registrazione mensile](funzionamento.md#registrare-una-rilevazione-mensile) comprende apertura dal PAC, inserimento dei dati, anteprima, conferma delle quote effettive, salvataggio autorevole e ritorno al dettaglio. Se lo stato rilevante cambia dopo l'anteprima, l'interfaccia conserva gli input e aggiorna l'anteprima prima di completare il salvataggio. PAC chiuso o periodo non più disponibile impediscono l'operazione; non vengono cambiati automaticamente periodo o quote effettive.

Il [flusso di apertura del PAC](funzionamento.md#aprire-un-pac) comprende scelta fra partenza da zero e saldo iniziale, selezione o creazione del titolo, configurazione, eventuali saldi, riepilogo e salvataggio. L'operazione applicativa assegna il proprietario autenticato, ricalcola l'obiettivo iniziale e crea il piano attivo senza predecessore, chiusura o blocco della configurazione. Non crea rilevazioni né modifica la quotazione del titolo; il prezzo non è un requisito per dichiarare il saldo iniziale. Il successore rimane un flusso distinto.

La [gestione dei titoli](funzionamento.md#gestire-un-titolo-nel-catalogo) permette creazione dal catalogo o dal modulo PAC, eventuale quotazione iniziale e modifica di nome e ticker mantenendo l'ID interno. Il ticker normalizzato rimane univoco anche in caso di correzione. Le cancellazioni sono consentite soltanto in assenza di PAC collegati, inclusi quelli chiusi. Gli aggiornamenti successivi della quotazione restano un'operazione separata; nessuna di queste modifiche all'anagrafica riscrive le fotografie degli investimenti.

### Laravel Actions

Le operazioni del prodotto saranno espresse tramite Actions applicative distinte, secondo l'[elenco dei flussi concordati](funzionamento.md#13-flussi-applicativi-dellmvp): gestione di ticker e nome del titolo, aggiornamento della quotazione, apertura del PAC da zero o con saldo iniziale, registrazione mensile, correzione ed eliminazione dell'ultima rilevazione, creazione del successore e gestione della configurazione o eliminazione del PAC quando consentite.

Le Actions di Filament rappresenteranno le interazioni della schermata e richiameranno queste operazioni. Le due tipologie di Action hanno quindi responsabilità distinte.

La registrazione mensile rileggerà e verificherà lo stato prima di salvare. La nuova rilevazione, l'eventuale prima impostazione di `configuration_locked_at` e l'aggiornamento ammesso della quotazione condivisa saranno applicati nella stessa transazione. La gestione della concorrenza dovrà impedire salvataggi basati su anteprime superate e funzionare sul database SQLite previsto per l'MVP.

Aggiornamento ordinario e correzione esplicita della quotazione useranno la stessa coppia prezzo/data del titolo; soltanto la correzione confermata potrà retrodatare la quotazione corrente. Le operazioni controlleranno che la base mostrata all'utente non sia stata sostituita da un salvataggio concorrente.

Correzione ed eliminazione delle rilevazioni verificheranno nella transazione che il record richiesto sia ancora l'ultimo di un PAC attivo. La correzione sostituirà la fotografia dal saldo precedente senza aggiornare la quotazione condivisa; l'eliminazione rimuoverà soltanto il record selezionato, conservando quotazione e blocco della configurazione. Una conferma superata non autorizzerà implicitamente l'operazione su un altro record.

La modifica della configurazione richiederà un PAC attivo senza predecessore, senza rilevazioni e senza blocco, mentre l'eliminazione richiederà assenza di rilevazioni e di tutti i collegamenti di prosecuzione. Titoli e altri PAC non saranno cancellati a cascata. Questi controlli verranno applicati sul server indipendentemente dalla disponibilità delle azioni nell'interfaccia.

La creazione del successore applicherà nella stessa transazione chiusura del predecessore, apertura del nuovo piano, ereditarietà dei saldi e blocco immediato della configurazione del successore. La decorrenza sarà successiva a inizio, apertura e ultima rilevazione del predecessore e non futura. Il trasferimento dell'obiettivo considererà il calendario fino alla chiusura, anche nei mesi non rilevati. Non sono previsti scheduler o stati per passaggi futuri programmati. Stato riletto e vincolo univoco sul predecessore impediranno la creazione di due successori concorrenti.

### Laravel Data e calcolatore VA

I Data object descriveranno gli input delle operazioni e i risultati strutturati del calcolo. La conversione di prezzi e importi avverrà esplicitamente ai confini del sistema, prima della loro elaborazione.

Il calcolatore VA sarà indipendente dalle schermate e riutilizzato nei diversi flussi. Le formule resteranno deterministiche e verificabili con gli [scenari documentati](scenari.md).

### Importer nativo di Filament (fase successiva)

L'importazione dello storico è esclusa dal primo MVP: non sono richiesti ora importer, flussi, infrastruttura o verifiche specifiche dell'importazione. Le scelte seguenti restano come riferimento per quando la funzionalità sarà ripresa.

L'importazione dello storico userà `ImportAction` e le colonne mappabili native di Filament 5 come base dell'interfaccia. Il formato iniziale è CSV, esportabile da Excel, senza imporre intestazioni fisse; la lettura diretta di file XLSX non fa parte del flusso iniziale. Il PAC è determinato dalla schermata e le colonne mappabili rappresentano periodo, prezzo unitario, data del prezzo e quote effettivamente acquistate.

Il flusso standard di Filament prevede elaborazione per righe e gestione delle righe fallite, con possibilità di importare quelle valide. Il dominio PAC richiede personalizzazioni per validare l'intero insieme prima del salvataggio, ordinarlo per periodo, produrre l'anteprima VA e confermare tutte le registrazioni insieme. L'importazione standard per righe non garantisce da sola questi requisiti.

Il percorso personalizzato richiamerà il calcolatore VA e le operazioni applicative condivise. La conferma verificherà nuovamente lo stato del PAC e applicherà in una singola transazione tutte le rilevazioni, i saldi e gli eventuali aggiornamenti della quotazione condivisa: nessuna scrittura di dominio avverrà durante l'anteprima e un errore non lascerà importazioni parziali. Il perimetro inizialmente proposto richiede un PAC attivo e privo di rilevazioni; rimangono validi i vincoli del saldo di apertura e dell'inserimento cronologico.

## 4. Importi monetari e prezzi unitari

### Importi monetari

Per gli importi in EUR si useranno `cknow/laravel-money` e `MoneyIntegerCast`, con colonne intere `BIGINT` in unità minime della valuta.

Esempi:

| Importo | Valore memorizzato in centesimi |
| --- | ---: |
| 750,00 € | 75000 |
| 748,24 € | 74824 |
| −200,00 € | −20000 |

Gli importi che possono essere negativi, come l'importo teorico VA, richiedono interi con segno.

La conversione tra euro inseriti nel form e centesimi memorizzati sarà esplicita. I campi economici non saranno interpretati implicitamente come euro o centesimi a seconda del tipo PHP ricevuto.

L'uso del cast è illustrato da questa configurazione, allineata ai nomi dei campi dello schema fisico concordato. L'esempio raccoglie campi di modelli diversi: `monthly_increment` appartiene a `Plan`, mentre gli altri due appartengono a `Investment`; ciascun cast sarà dichiarato sul relativo modello.

```php
use Cknow\Money\Casts\MoneyIntegerCast;

protected function casts(): array
{
    return [
        'monthly_increment' => MoneyIntegerCast::class . ':EUR',
        'theoretical_investment' => MoneyIntegerCast::class . ':EUR',
        'virtual_liquidity_after' => MoneyIntegerCast::class . ':EUR',
    ];
}
```

### Prezzi unitari fino a sei decimali

Il prezzo unitario deve poter conservare fino a sei decimali. Sarà memorizzato anch'esso come intero `BIGINT`, con un cast dedicato e fattore di scala `1.000.000`.

| Prezzo | Intero memorizzato |
| --- | ---: |
| 93,53 € | 93530000 |
| 123,318 € | 123318000 |
| 123,318456 € | 123318456 |

Il cast del prezzo sarà distinto da quello degli importi monetari in centesimi. Il prezzo non dovrà perdere i decimali aggiuntivi prima di essere moltiplicato per le quote o usato nel rapporto che determina le quote proposte.

I calcoli usano interi con controllo preventivo di overflow attraverso `App\Support\Decimal`, senza conversioni intermedie a `float`. Il calcolatore conserva la scala a un milionesimo fino ai risultati monetari finali. Gli input che superano i limiti di calcolo vengono rifiutati con un errore di validazione.

### Arrotondamenti

È già confermato l'arrotondamento delle quantità all'intero più vicino, con metà all'intero superiore per i valori non negativi.

I risultati monetari finali vengono arrotondati ai centesimi con metà verso l'alto, dopo aver conservato la precisione prevista dai calcoli. La conversione in centesimi non deve introdurre un arrotondamento anticipato del prezzo unitario o del rapporto che determina le quote proposte. Nella prima versione si usa un prezzo unico per rilevazione e acquisto e non sono previste commissioni.

## 5. Database

Si partirà da SQLite per l'utilizzo locale. Se una funzionalità concreta dovesse incontrare un limite rilevante, si passerà a MySQL.

La persistenza dei dati applicativi userà Eloquent e le migration Laravel. Le funzionalità necessarie dovranno essere verificate sul database effettivamente scelto, in particolare per vincoli, transazioni e gestione di modifiche concorrenti.

Lo [schema fisico concordato](struttura-dati.md#6-schema-fisico-dellmvp) aggiunge `instruments`, `plans` e `investments`, mantenendo `users` e le tabelle tecniche esistenti. Le migration implementeranno chiavi esterne restrittive, ticker univoco, unicità di PAC/periodo e del predecessore, indici delle query principali e controlli locali ai record. Il blocco della configurazione sarà conservato in `plans.configuration_locked_at`; i saldi correnti si leggeranno dall'ultima rilevazione o dall'apertura, senza duplicarli su `plans`. Le fotografie mensili conserveranno anche le segnalazioni determinate prima dell'arrotondamento ai centesimi.

Il passaggio a MySQL, se necessario, comprenderà schema, trasferimento dei dati e verifica dei saldi; non sarà trattato come la sola modifica della connessione.

Il file del database locale e i dati personali esportati rimarranno fuori dal versionamento del codice.

Il primo MVP non comprende funzioni applicative di esportazione o ripristino. Il backup tecnico del database rimane una misura operativa separata, senza richiedere ora schermate, formati di scambio o flussi applicativi dedicati; esportazione e ripristino nell'interfaccia saranno progettati dopo l'MVP.

## 6. Coerenza delle registrazioni e storico

L'applicazione applicherà sul server i vincoli già concordati:

- una sola registrazione per PAC e periodo, anche tramite un vincolo univoco nel database;
- modifica ammessa soltanto per l'ultima registrazione cronologica di un PAC attivo;
- storico dei PAC chiusi interamente in sola lettura;
- creazione del successore e chiusura del predecessore nella stessa transazione;
- nessun doppio conteggio dei saldi trasferiti;
- sostituzione del contributo alla liquidità virtuale quando si modifica l'ultima registrazione.

Ogni registrazione conserverà gli input e una fotografia dei risultati del calcolo necessari a leggere lo storico. La visualizzazione dello storico non dovrà richiedere la riscrittura delle registrazioni pregresse.

Gli aggiornamenti del solo prezzo resteranno distinti dalle registrazioni mensili e non contribuiranno al contatore virtuale.

## 7. Convenzioni del provider applicativo

Nel provider applicativo saranno adottate le seguenti convenzioni:

```php
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

Date::use(CarbonImmutable::class);

Model::shouldBeStrict(! app()->isProduction());
Model::unguard();

DB::prohibitDestructiveCommands(
    app()->isProduction(),
);

RequestException::dontTruncate();
```

I salvataggi saranno costruiti con i campi preparati esplicitamente nelle Actions e nei Data object. La validazione e le autorizzazioni applicative rimangono responsabilità dei flussi di scrittura.

### Morph map solo quando necessarie

Se verranno introdotte relazioni polimorfiche, i relativi alias saranno centralizzati nello stesso provider tramite `Relation::enforceMorphMap([...])`.

La mappatura `'user' => User::class` discussa nel brainstorming era soltanto un esempio: non è una mappatura iniziale obbligatoria e non giustifica l'introduzione di relazioni polimorfiche non necessarie.

## 8. Accesso locale e panel provider

Si userà `spatie/laravel-login-link` come dipendenza di sviluppo. L'utente locale previsto è **`test@example.com`**, creato tramite un seeder locale.

Il panel provider aggiungerà il link prima del form di login tramite il render hook indicato:

```php
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

// Nella configurazione del panel:
->renderHook(
    PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
    fn (): ?string => $this->localLoginLinks(),
)
```

Il metodo del provider sarà:

```php
private function localLoginLinks(): ?string
{
    if (! app()->isLocal()) {
        return null;
    }

    return Blade::render(<<<'HTML'
        <div class="flex space-x-2 leading-none">
            <strong>Login As: </strong>
            <div>
                <x-login-link
                    class="underline"
                    email="test@example.com"
                    label="Test"
                />
            </div>
        </div>
        HTML
    );
}
```

La configurazione del pacchetto manterrà il solo ambiente `local` e includerà l'host effettivo del progetto in Herd tra gli host ammessi. L'host sarà coerente con `APP_URL`; il valore predefinito del pacchetto ammette solo `localhost`.

## 9. Laravel AI, Boost e MCP

Sono richiesti:

- **Laravel Boost**, come dipendenza di sviluppo, con la relativa configurazione degli strumenti per assistere lo sviluppo.
- **Laravel AI SDK**, come dipendenza applicativa per future funzionalità AI.
- **Laravel MCP**, come dipendenza applicativa esplicita per eventuali integrazioni MCP.

Boost include già Laravel MCP tra le proprie dipendenze. La dichiarazione esplicita di `laravel/mcp` manterrà il pacchetto nel perimetro applicativo richiesto, indipendentemente dalla presenza delle dipendenze di sviluppo.

L'installazione dei pacchetti e la configurazione degli strumenti di sviluppo saranno distinte dalla progettazione di funzioni AI rivolte all'utente. Provider AI, modelli, credenziali, agenti e strumenti applicativi verranno definiti quando saranno introdotte funzionalità che li richiedono.

## 10. Verifiche previste

I test si concentreranno sui comportamenti del prodotto:

- calcoli VA, segnalazioni e arrotondamento delle quote;
- conversione tra importi in euro, centesimi e prezzi a sei decimali;
- conservazione della precisione prima della conversione monetaria finale;
- distinzione tra liquidità virtuale e patrimonio;
- avanzamento degli obiettivi per calendario e mesi non rilevati;
- unicità mensile, autorizzazione alla modifica dell'ultima registrazione e blocco dei PAC chiusi;
- continuità dei saldi e assenza di doppio conteggio nella prosecuzione;
- modifica dell'ultima registrazione senza accumulare due volte il contributo virtuale;
- disponibilità del login rapido nel solo ambiente locale previsto.

Le verifiche che riguardano i dati saranno eseguite anche su SQLite. In caso di passaggio a MySQL verranno ripetute sul database di destinazione.

Le verifiche su mappatura delle colonne, ordinamento, anteprima e salvataggio completo dell'importazione saranno aggiunte quando si implementerà la relativa funzionalità, dopo il primo MVP.

## 11. Passaggio all'implementazione

Lo stack, il perimetro funzionale, lo schema fisico e tutti i flussi dell'MVP sono definiti. La sezione 13 della [specifica funzionale](funzionamento.md) dettaglia le operazioni e i relativi controlli. Il successore usa un passaggio immediato con decorrenza non futura. Non rimangono scelte di prodotto bloccanti per iniziare l'implementazione.

Sono presenti migration e vincoli SQLite, modelli e cast, calcolatore VA, Actions transazionali, Data object e pagine Filament. Le transazioni SQLite usano `IMMEDIATE` con attesa fino a 5 secondi: lo stato viene riletto dopo l’acquisizione del blocco di scrittura. Le anteprime conservano una firma dello stato e degli input; un cambiamento richiede una nuova verifica prima del salvataggio. I test Pest coprono calcoli, precisione, vincoli, autorizzazioni, operazioni senza scritture parziali e interazioni Livewire. La build del tema passa tramite `npm run build`.

L’MVP funziona in locale. La pubblicazione dell’applicazione resta una fase separata e non è stata eseguita.

Importazione, esportazione e ripristino dei dati tramite l'applicazione, insieme ai relativi formati, sono rimandati dopo il primo MVP e non bloccano l'implementazione delle rilevazioni manuali e dell'avvio con saldo iniziale.

## 12. Riferimenti tecnici

Requisiti verificati durante il brainstorming; da ricontrollare al momento dell'installazione:

- [Release e supporto Laravel 13](https://laravel.com/docs/13.x/releases).
- [Installazione Filament 5](https://filamentphp.com/docs/5.x/introduction/installation) e [integrazione con Livewire 4](https://filamentphp.com/insights/danharrin-filament-v5-blueprint).
- [Import action di Filament 5](https://filamentphp.com/docs/5.x/actions/import): CSV, mappatura delle colonne e comportamento standard delle righe non valide.
- [Laravel Actions](https://packagist.org/packages/lorisleiva/laravel-actions) e [Laravel Data](https://packagist.org/packages/spatie/laravel-data).
- [Laravel Money e relativi cast](https://github.com/cknow/laravel-money#casts).
- [Laravel Login Link e configurazione](https://github.com/spatie/laravel-login-link#installation).
- [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk), [Laravel Boost](https://laravel.com/docs/13.x/boost) e [Laravel MCP](https://laravel.com/docs/13.x/mcp).
- [Dipendenze dichiarate da Laravel Boost](https://packagist.org/packages/laravel/boost).
