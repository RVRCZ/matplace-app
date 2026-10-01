# Krok F: admin

Větev `feature/f-admin` (z `feature/e-seo`). Admin má vedle farmy nové sekce: katalog, kolekce, obsah, Meta,
statistiky, AI aktivitu a e‑maily. Všechno je za rolí `admin` a s `noindex`. Nad všemi stránkami adminu je
jedna společná navigace (`admin/nav.blade.php`), farma a YouTube zůstaly, kde byly.

## Co vzniklo

**Katalog (`/admin/catalog`)**
- *Hledání* (`/admin/catalog/search`): jeden dotaz do Printables, MakerWorld a MakerOnline
  (`App\Engines\Search\MakerOnlineSearch`, přenesené volání `importSearch` starého adminu). U výsledku je
  zaškrtávátko; co už v katalogu je, je označené. Pod výsledky pole na seznam adres (jedna na řádek, nejvýš 50).
- *Import* (`App\Domain\Catalog\CatalogImporter`): u Printables a MakerWorld přečte stránku modelu (popis,
  obrázek, licence, autor, štítky), u ostatních zdrojů uloží to, co řeklo hledání. Jen odkazy a obrázky, nikdy
  soubory. Každý běh je řádek `designer_imports` s `designer_profile_id = null`; `/admin/catalog/imports/{id}`
  ukáže, co se stalo s každou adresou. Stejná adresa se nezdvojí, sledovací parametry se z adres odstraní.
- *Správa inspiračních modelů*: seznam s filtrem (text, zdroj, kategorie, skryté, bez kategorie), skrýt/zobrazit,
  úprava názvu, licence, štítků, kategorie a popisů ve třech jazycích.
- *Karty designérů* (`/admin/catalog/cards`): seznam, hledání, kategorie, viditelnost, **přeslicování** (stejný job,
  který kartu připravil poprvé: rozměry, gramy a čas se přepíší).
- *AI kategorie* (`App\Domain\Catalog\CategoryClassifier`, job `ClassifyModel`, `php artisan
  matplace:classify-catalog [--limit=200] [--dry-run]`, tlačítko v adminu): asistent dostane obrázek, název, popis
  a náš seznam kategorií, vrátí kategorii, jistotu, důvod a „obrázek neodpovídá názvu“. Jistota ≥ 0,6 bez
  nesouladu se zapíše; ostatní čeká ve frontě `/admin/catalog/review` s důvodem a člověk potvrdí, vybere jinou,
  nebo nechá, jak bylo. Kategorii, která v seznamu není, nepřijme.
- *AI texty* (`App\Domain\Catalog\ModelTexts`): „popis z obrázku“ a „přepsat popis“ vrátí návrh ve třech
  jazycích do formuláře. Neuloží se, dokud admin neuloží.

**Kolekce (`/admin/collections`, veřejně `/collections` a `/collections/{slug}`)**
- CRUD, obálka, položky z obou katalogů (přidávají se adresou nebo slugem, po řádcích), pořadí, „doplnit
  překlady“ (stejný překladač jako u popisů modelů).
- *Návrhy* (`App\Domain\Catalog\CollectionSuggester`): skupiny modelů se společným štítkem v jedné kategorii,
  nejvýš 10 témat po 12 modelech; asistent je jedním voláním pojmenuje ve třech jazycích. Kolekce vznikne skrytá,
  až když ji admin z návrhu založí. Modely, které už v nějaké kolekci jsou, se znovu nenavrhují.
- Veřejná stránka existuje jen u kolekce `visible` a jen v jazycích, ve kterých má název. Položka se ukáže jen
  v jazyce, ve kterém má text. Skrytou kolekci vidí admin jako náhled (`noindex`). Stránka má `ItemList`,
  hreflang, OG obrázek; odkaz „Kolekce“ je v patičce, jakmile v daném jazyce nějaká je; sitemapa
  `sitemap-collections-{jazyk}.xml` už z kroku E.

**Obsah (`/admin/content`)**
- *Blog*: seznam, editor (Markdown, tři jazyky, čeština povinná), perex, obálka, nahrání obrázku do textu,
  uložit / zveřejnit / stáhnout / smazat, plánované zveřejnění datem. Koncept vidí admin na veřejné adrese jako
  náhled. Převzaté HTML články se dají upravit taky; při každém uložení projdou čističem z kroku E.
- *Bannery*: obrázek, popisek (alt), odkaz, jazyk nebo všechny, aktivní, pořadí. Ukazují se na úvodní stránce pod
  kalkulačkou.
- Statické stránky zůstaly v `lang/*/pages.php`, admin je needituje (podle zadání).

**Meta (`/admin/content/meta`)**
- `App\Engines\Social\MetaClient` (`GraphMetaClient` = Graph API, `FakeMetaClient` pro testy a vývoj).
- Příspěvek o kartě modelu nebo kolekci: asistent navrhne text (`ai_calls`, druh `social`), admin vidí náhled
  s obrázkem a odkazem, text upraví a teprve pak zveřejní na Facebook stránku a/nebo Instagram. Odkaz nese
  `utm_source`, `utm_medium=social`, `utm_campaign=post`. Každý pokus je v `social_posts` i s chybou, kterou
  Meta vrátila.
- Conversions API: `order_paid` (Purchase) a `register` (CompleteRegistration) jen od návštěvníka, který povolil
  marketingové cookies; e‑mail odchází jen jako SHA‑256. Stejné `event_id` dostane pixel v prohlížeči, takže
  Meta konverzi počítá jednou.
- Reklamy: jen čtení kampaní, útraty, zobrazení a kliků za 7 a 30 dní.

**Statistiky (`/admin/stats`)** – `App\Domain\Stats\Funnel`, zdroj jsou vlastní `events`
- Tři cesty: zákazník (návštěva → nahrání/generování → kalkulace → zaplacený tisk), majitel tiskárny (návštěva →
  stránka modelu nebo výstup nástroje → stažení), designér (návštěva → registrace → profil → import → soubor pro
  farmu). Krok počítá návštěvníky, kteří udělali jej i všechny kroky před ním.
- Přepínače: 7/30/90 dní, zdroj, jazyk, nástroj. Tabulky podle zdroje a jazyka, tabulka nástrojů (návštěvy →
  výstupy → stažení / objednávky), atribuce designérům (návštěvy přes `ref`, návštěvníci, tisky, stažení,
  registrace; stejná data, jaká vidí designér u sebe).
- `php artisan matplace:events-rollup [--months=13] [--dry-run]` (měsíčně 2. den ve 3:50): události starší 13
  měsíců sečte do `events_daily` (den, druh, zdroj, jazyk: kolik událostí, kolik návštěvníků) a smaže.

**Vyhledávání (`/admin/stats/search`)**: `search_queries` plní `SearchController::text`. Nejčastější dotazy,
dotazy bez výsledku v našem katalogu, podle jazyka, export CSV (středník, UTF‑8 s BOM pro Excel).

**AI aktivita (`/admin/ai`)**: každé volání AI jde přes `AiUsage::record()` do `ai_calls`: generování 3D
(`generate`), popis a kontrola fotky (`describe`, `moderate`, `inspect`), rada k tisku (`advise`), překlad
(`translate`), kategorie (`classify`), texty modelů (`text`), kolekce (`collections`), příspěvky (`social`),
e‑maily (`email`). Stránka: náklady za období, na návštěvníka, na zaplacenou objednávku, podle druhu, po dnech
a po měsících. Ceník je v `config/ai.php` (`prices`: USD za milion tokenů nebo za volání, kurz `AI_USD_CZK`).

**E‑maily (`/admin/emails`)** – `App\Domain\Mail\Outbox`
- Admin zadá adresáta, jazyk a pokyn (případně podklad, třeba zprávu, na kterou se odpovídá); asistent napíše
  koncept do `outgoing_emails` (`draft`). Admin ho upraví a **schválí** (odešle se přesně uložený text), nebo
  **zamítne**. Bez schválení neodejde nic. Odeslaný e‑mail už nejde změnit ani poslat znovu.
- Systémové notifikace (ověření, objednávka, zásilka…) odcházejí hned jako dosud a v seznamu jsou jako `sent`.

**Asistent** (`App\Engines\Ai\Assistant`, `ClaudeAssistant`, `FakeAssistant`): jedno místo pro všechny textové
úlohy adminu. Odpověď je vždy JSON podle schématu, model a úsilí jsou v `config/ai.php`.

## Rozhodnutí nad rámec zadání

1. **Zprávy z Messengeru a Instagramu jsem nepřenesl.** `MetaInboxService` starého webu potřebuje webhook,
   ověřování podpisu, vlákna, stav přečtení a odpovědi v okně 24 hodin; to je víc než den práce a bez schválené
   aplikace u Mety se to nedá vyzkoušet. Odpovědi se dál píšou v Meta Business Suite.
2. **AI obrázek k modelu jsem odložil.** Nová aplikace nemá generátor obrázků (jen generátor 3D modelů a popis
   fotek), přidával bych novou službu. Popis z obrázku a přepsání popisu hotové jsou.
3. **Návrh kolekcí nehledá skupiny pomocí AI.** `collectionSuggest` starého webu bral modely s po sobě jdoucími
   ID, což v novém katalogu nic neznamená. Skupiny najde program (společný štítek v kategorii), AI je jen
   pojmenuje. Je to levnější (jedno volání) a návrh se dá vysvětlit: u každého je vidět, podle čeho vznikl.
4. **Návštěva je vlastní událost `visit`.** Zadání začíná všechny cesty „návštěvou“, ale `events` ji neměly.
   Middleware `RecordVisit` zapíše první stránku návštěvníka (jednou za relaci prohlížeče, bez robotů) a otevření
   stránky nástroje (jednou za půl hodiny). Zobrazení modelů se zapisovala už dřív.
5. **Návštěvník ve statistikách je anonymní relace**, ne účet. Událost, která vznikne mimo prohlížeč (job
   dokončí soubor designéra), se přičte k poslední relaci téhož přihlášeného uživatele.
6. **Zaplacený tisk modelu z katalogu není konec „zákaznické“ cesty**, protože ta podle zadání vede přes nahrání
   a kalkulaci. V tabulkách podle zdroje a jazyka se ale počítají všechny zaplacené tisky; tam je vidět celek.
7. **`search_queries` nemají ani číslo relace.** Aby šlo poznat „jeden člověk hledal třikrát“, nese řádek
   jednosměrný otisk (HMAC z relace a data), který se každý den mění a nedá se převést zpět. Z dotazu se před
   uložením vymažou e‑mailové adresy a dlouhá čísla (telefony).
8. **Seznam odeslaných systémových e‑mailů neobsahuje funkční odkazy.** Ověřovací odkaz nebo odkaz na změnu
   hesla by v tabulce byl klíč k cizímu účtu; ukládá se adresa bez parametrů a bez dlouhých náhodných částí.
9. **Systémové e‑maily se zapisují posluchačem `MessageSent`**, ne u každého místa, které posílá. Nic se tím
   nezapomene, a když zápis selže, e‑mail přesto odejde.
10. **Podklad k e‑mailu jde asistentovi odděleně od pokynu** a s upozorněním, že pokyny v něm nemá plnit.
    Zákazník tak nemůže textem své zprávy změnit, co asistent napíše; poslední slovo má stejně admin.
11. **Nová tabulka `social_posts`** (zadání ji nemá): bez ní by nebylo vidět, co a kdy se zveřejnilo a proč
    to selhalo.
12. **`designer_models` dostaly `ai_confidence`, `ai_mismatch`, `ai_reason`, `ai_category_id`,
    `ai_checked_at`; `catalog_models` `ai_category_id` a `ai_checked_at`.** Návrh AI se drží vedle skutečné
    kategorie, aby fronta ke kontrole nepřepisovala nic, co zadal člověk.
13. **Conversions API posílá server jen se souhlasem z cookie `consent`.** Bez marketingového souhlasu neodejde
    nic, ani anonymně. Vlastní `events` se zapisují vždy.
14. **Asistent je Claude Opus 5.5 s nízkým úsilím** (`ANTHROPIC_ASSISTANT_MODEL`, `ANTHROPIC_ASSISTANT_EFFORT`).
    Úlohy adminu jsou krátké; kategorie a texty jsou na tom spolehlivé a levné. Dá se změnit v `.env`.
15. **Neadmin je z `/admin/*` přesměrován do účtu s hláškou** (stejně jako dosud u farmy), nedostane 403.
16. **Stránka `/collections` bez jediné veřejné kolekce vrací 404**, aby ve vyhledávačích nebyla prázdná stránka.

## Co zbývá / na co si dát pozor

- Meta: nic z toho jsem nemohl zkusit proti skutečnému účtu (lokálně běží `ENGINE_SOCIAL=fake`). Volání jsou
  přepsaná ze starého webu a z dokumentace Graph API `v21.0`; první příspěvek a první konverzi je potřeba
  ověřit na betě (konverze s `META_CAPI_TEST_CODE` v nástroji Test Events).
- Instagram chce veřejně dostupný obrázek. Na betě za heslem nebo bez opravy nginx z kroku E (`/og/*.png`)
  příspěvek selže; chyba se ukáže v adminu.
- Starší `ai_calls` (před tímto krokem) existují jen pro překlady. Náklady za dřívější generování a popisy fotek
  ve statistice nejsou.
- Události zapsané před nasazením nemají `visit`. Cesty za starší období začnou až prvním krokem, který se
  tehdy zapisoval; čísla dávají smysl od nasazení dál.
- Editor blogu je obyčejné textové pole s Markdownem, bez živého náhledu; náhled je uložený koncept na veřejné
  adrese.

## Nové `.env` klíče

| klíč | výchozí | k čemu |
|---|---|---|
| `ENGINE_ASSISTANT` | `claude` | `fake` = bez volání ven (testy, vývoj) |
| `ANTHROPIC_ASSISTANT_MODEL` | `claude-opus-5-5` | model pro kategorie, texty, kolekce, příspěvky a e‑maily |
| `ANTHROPIC_ASSISTANT_EFFORT` | `low` | úsilí modelu |
| `ENGINE_SOCIAL` | `meta` | `fake` = nic neodchází na Facebook ani Instagram |
| `META_SYSTEM_TOKEN` | – | token systémového uživatele (příspěvky, čtení reklam) |
| `META_PAGE_ID` | – | Facebook stránka |
| `META_IG_ID` | – | Instagram účet propojený se stránkou |
| `META_AD_ACCOUNT_ID` | – | reklamní účet (jen čtení) |
| `META_CAPI_TOKEN` | – | Conversions API; bez něj se konverze ze serveru neposílají |
| `META_CAPI_TEST_CODE` | – | kód z Test Events, jen na zkoušku |
| `AI_USD_CZK` | `23` | kurz pro přepočet ceny AI |

`ANTHROPIC_API_KEY` už existuje (překlady). Bez klíčů Mety stránka `/admin/content/meta` řekne, co chybí.

## Testy

`tests/Feature/AdminContentTest.php` (16 testů): sekce jen pro admina; hledání v MakerOnline a import (z výsledků
i z adres, log, nic se nezdvojí, licence, úprava a skrytí); kategorie s fake AI (jistá se zapíše, pod 0,6 a
nesoulad do fronty, neexistující kategorie se nepřijme, fronta, dávka, AI text jen jako návrh); karty designérů
(kategorie, fronta, přeslicování); kolekce veřejná jen `visible` a jen v jazycích, které má (stránka, `ItemList`,
sitemapa, patička); návrhy kolekcí; blog (koncept, zveřejnění, stažení, obrázek, čištění HTML); bannery; příspěvek
na Facebook a Instagram s náhledem a s odmítnutím od Mety; konverze jen se souhlasem a s hashem e‑mailu; cesty
z připravených událostí včetně rozpadu podle zdroje, jazyka, nástroje a designéra a složení starých událostí;
návštěva a nástroj jednou na návštěvníka; hledání bez osobních údajů a export CSV; `ai_calls` při překladu a cena
na stránce; AI e‑mail neodejde bez schválení, zamítnutý nikdy; systémový e‑mail je v seznamu jako odeslaný a bez
funkčního odkazu.

## Nasazení (Roman)

```
php artisan migrate --force
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker

php artisan matplace:classify-catalog --dry-run    # kolik modelů je bez kategorie
php artisan matplace:classify-catalog --limit=50   # první dávka; každé volání je vidět v /admin/ai
php artisan matplace:events-rollup --dry-run       # dnes nic, události tak staré nejsou
```

- `.env`: klíče z tabulky výše. Na betě nech `ENGINE_SOCIAL=fake`, dokud nebude token Mety; po doplnění
  `ENGINE_SOCIAL=meta` a `php artisan optimize`.
- Klasifikace celého inspiračního katalogu (4 900 modelů) je 4 900 volání asistenta s obrázkem. Pusť nejdřív
  dávku 50, podívej se do `/admin/ai`, kolik stála, a do `/admin/catalog/review`, jak dopadla; teprve pak zbytek.
- Scheduler už běží (krok E); přibyl v něm měsíční `matplace:events-rollup`.
- Worker musí běžet: import, klasifikace a přeslicování jsou joby ve frontě.
- Hledání v cizích katalozích z adminu chodí ze serveru; platí pro něj `IMPORT_HTTP_PROXY` z kroku B.
