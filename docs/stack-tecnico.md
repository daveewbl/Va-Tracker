# Stack tecnico di VA Tracker

Stato: decisioni tecniche concordate nel brainstorming del 6 novembre 2026.

Questo documento descrive lo stack scelto. Lo scaffolding tecnico è ora presente nel repository, con dipendenze installate, panel Filament, tema dedicato, SQLite e accesso locale. Schema e flussi del dominio PAC restano da implementare. Le regole del prodotto rimangono definite nella [specifica funzionale](funzionamento.md), con la [struttura concettuale dei dati](struttura-dati.md) concordata.

## 1. Impostazione generale

VA Tracker sarà un'unica applicazione Laravel, utilizzata inizialmente in locale con Herd. Filament sarà l'interfaccia completa dell'applicazione, con un tema dedicato.

Le schermate gestiranno presentazione e interazione. Le Actions applicative gestiranno le operazioni del PAC; il calcolo VA sarà condiviso tra anteprima, salvataggio e importazione.

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

L'inserimento del prezzo aggiornerà l'anteprima del valore dei titoli, dell'importo teorico VA, delle quote proposte, del controvalore e della segnalazione. Una pagina personalizzata all'interno del panel potrà essere usata per rendere chiaro questo flusso.

Il salvataggio ricalcolerà i risultati sul server usando lo stato corrente del PAC. I risultati mostrati nel browser non saranno usati come fonte autorevole dei saldi.

### Laravel Actions

Le operazioni del prodotto saranno espresse tramite Actions applicative. Gli esempi comprendono:

- creare un PAC da zero o con situazione iniziale;
- registrare la rilevazione mensile;
- modificare l'ultima rilevazione ammessa;
- creare il PAC successore e chiudere il precedente;
- importare uno storico verificato.

Le Actions di Filament rappresenteranno le interazioni della schermata e richiameranno queste operazioni. Le due tipologie di Action hanno quindi responsabilità distinte.

### Laravel Data e calcolatore VA

I Data object descriveranno gli input delle operazioni e i risultati strutturati del calcolo. La conversione di prezzi e importi avverrà esplicitamente ai confini del sistema, prima della loro elaborazione.

Il calcolatore VA sarà indipendente dalle schermate e riutilizzato nei diversi flussi. Le formule resteranno deterministiche e verificabili con gli [scenari documentati](scenari.md).

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

L'uso del cast è illustrato da questa configurazione; nomi dei campi e schema definitivo saranno definiti durante la progettazione dei dati:

```php
use Cknow\Money\Casts\MoneyIntegerCast;

protected function casts(): array
{
    return [
        'monthly_increment' => MoneyIntegerCast::class . ':EUR',
        'theoretical_investment' => MoneyIntegerCast::class . ':EUR',
        'virtual_liquidity' => MoneyIntegerCast::class . ':EUR',
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

I calcoli manterranno questa precisione attraverso una rappresentazione esatta, evitando conversioni intermedie a `float`. La scelta tecnica puntuale dell'aritmetica di supporto sarà verificata in implementazione; il solo cast monetario non risolve automaticamente l'aritmetica a scala diversa.

### Arrotondamenti

È già confermato l'arrotondamento delle quantità all'intero più vicino, con metà all'intero superiore per i valori non negativi.

I risultati monetari finali vengono arrotondati ai centesimi con metà verso l'alto, dopo aver conservato la precisione prevista dai calcoli. La conversione in centesimi non deve introdurre un arrotondamento anticipato del prezzo unitario o del rapporto che determina le quote proposte. Nella prima versione si usa un prezzo unico per rilevazione e acquisto e non sono previste commissioni.

## 5. Database

Si partirà da SQLite per l'utilizzo locale. Se una funzionalità concreta dovesse incontrare un limite rilevante, si passerà a MySQL.

La persistenza dei dati applicativi userà Eloquent e le migration Laravel. Le funzionalità necessarie dovranno essere verificate sul database effettivamente scelto, in particolare per vincoli, transazioni e gestione di modifiche concorrenti.

Il passaggio a MySQL, se necessario, comprenderà schema, trasferimento dei dati e verifica dei saldi; non sarà trattato come la sola modifica della connessione.

Il file del database locale e i dati personali esportati rimarranno fuori dal versionamento del codice.

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

## 11. Decisioni rimanenti prima dell'implementazione

Lo stack principale è definito. Rimangono da fissare dettagli operativi già separati dalla scelta dello stack:

- formato dell'importazione e trattamento delle anomalie dello storico;
- comportamento delle altre operazioni ancora aperte nella sezione 11 della [specifica funzionale](funzionamento.md);
- schema fisico dei dati e dettaglio dei flussi applicativi, a partire dalla struttura concettuale già concordata.

Lo scaffolding tecnico è completato. Le funzioni del PAC e la pubblicazione dell'applicazione sono fasi separate.

## 12. Riferimenti tecnici

Requisiti verificati durante il brainstorming; da ricontrollare al momento dell'installazione:

- [Release e supporto Laravel 13](https://laravel.com/docs/13.x/releases).
- [Installazione Filament 5](https://filamentphp.com/docs/5.x/introduction/installation) e [integrazione con Livewire 4](https://filamentphp.com/insights/danharrin-filament-v5-blueprint).
- [Laravel Actions](https://packagist.org/packages/lorisleiva/laravel-actions) e [Laravel Data](https://packagist.org/packages/spatie/laravel-data).
- [Laravel Money e relativi cast](https://github.com/cknow/laravel-money#casts).
- [Laravel Login Link e configurazione](https://github.com/spatie/laravel-login-link#installation).
- [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk), [Laravel Boost](https://laravel.com/docs/13.x/boost) e [Laravel MCP](https://laravel.com/docs/13.x/mcp).
- [Dipendenze dichiarate da Laravel Boost](https://packagist.org/packages/laravel/boost).
