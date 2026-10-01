# Krok B: designérský profil a portfolio

Větev `feature/b-designers` (z `feature/a-account`). Designér bez tiskárny má veřejné portfolio `/d/{slug}`,
převezme karty z Printables a MakerWorldu, k vybraným nahraje soubor, nastaví odměnu, dostane odkazy s `?ref=`
a vidí, co mu přinášejí.

## Co vzniklo

**Data** (`2026_10_03_100000_designers_events_ai_calls`): `designer_profiles`, `designer_models`,
`designer_model_images`, `designer_imports`, `events`, `ai_calls`; `farm_orders.designer_model_id` + `royalty_czk`
a `credit_transactions.designer_model_id` (+ `type` rozšířen na 20 znaků). Sloupce objednávky a knihy používá až
krok C, přidané jsou tady, aby přehled designéra nemusel zkoumat, co už v databázi je.

**Zapnutí** (`POST /account/designer/enable`, jen ověřený e‑mail): profil + role `designer`, slug ze jména.
Profil je neveřejný do první viditelné karty (pak se zveřejní sám, jednou), nebo ho designér zapne ručně.

**Ověření účtu** (`App\Domain\Designer\OwnershipCheck`): kód `matplace-<16 hex>` vložený tam, kam může psát
jen majitel:
- Printables: bio profilu (GraphQL `user(id: "@handle")`), nebo popis vlastního modelu;
- MakerWorld: popis vlastního modelu (viz rozhodnutí 1).
Uloží se, kdo je autor u zdroje (`printables_user_id`, `makerworld_uid`). Jeden účet u zdroje = jeden designér.

**Import** (`PortfolioImporter`, job `ImportDesignerModel` po jednom modelu, průběh se dotahuje po 3 s):
seznam vlastních modelů (Printables) nebo odkazy (obě platformy), jedno zaškrtnutí „jsem autor“. Karta dostane
název, popis v jazyce zdroje + překlad do chybějících jazyků, až 8 obrázků (překódované do WebP, malá verze
pro mřížky), licenci zdroje, příznak remixu s odkazem na původní model, názvy souborů u zdroje. Duplicita
(stejný model u stejného zdroje) se přeskočí. Limity: 100 modelů na import, 500 karet na profil.

**Engines** (všechno ven jde přes rozhraní s falešnou implementací):
- `App\Engines\Import\{ModelSource, PrintablesSource, MakerWorldSource, FakeSource, Sources}` (`ENGINE_IMPORT=fake` v testech);
- `App\Engines\Translate\{Translator, ClaudeTranslator, FakeTranslator}` (`ENGINE_TRANSLATOR=fake` v testech);
- `App\Support\AiUsage::record()` → `ai_calls`, ceník v `config/ai.php` (`prices`).

**Karta** (`/account/designer/models/{id}`): texty ve třech jazycích („prázdné jazyky doplnit překladem“),
odměna za kus, povolení stažení + licence (CC BY, CC BY‑SA, CC BY‑NC, CC0), potvrzení licence původního modelu
u remixu, viditelnost, obrázky, soubor, odkaz ke sdílení s hotovým textem cs/en/es. Ruční karta bez importu.

**Soubor** (`CardFiles`, job `PrepareDesignerFile`): STL, 3MF nebo ZIP; potvrzení „jsem autor a uděluji matplace
právo tisknout“; kontrola nástrojem „check“ (`ModelCheck`), při chybě se soubor nepoužije a karta řekne proč;
po kontrole jeden slice výchozím materiálem → `slice_summary` (rozměry, gramy, minuty). Remix bez potvrzení
soubor nepřijme.

**Hromadný ZIP** (`/account/designer/upload`, `ZipMatcher`): normalizace názvů + Jaro‑Winkler; ≥ 0,90 přiřadí,
0,75–0,90 nabídne k potvrzení, jinak nepřiřazeno; vše jde ručně změnit. Jedno potvrzení autorství pro celý zip.

**Veřejné portfolio** `/d/{slug}` (+ `/en/…`, `/es/…`): avatar, cover, bio, odkazy, mřížka karet se štítky
„Lze vytisknout“ / „Stáhnout zdarma“ / „Na Printables“, filtr „lze vytisknout“, `Person` JSON‑LD, hreflang,
OG obrázek `/og/designer/{slug}.png` (avatar + 3 covery, GD). Skrytý profil = 404; majitel vidí náhled s `noindex`.

**Odkazy a měření**: `?ref={slug}` na kterékoli stránce → cookie `ref` (30 dní) + `events` typ `ref_visit`
(middleware `RememberReferral`). `App\Support\Track`: zápis událostí, zdroj návštěvy (google, seznam, bing,
facebook, instagram, designer, direct, other), zobrazení stránky jednou za půl hodiny na návštěvníka, roboti se
nepočítají. Přehled `/account/designer`: návštěvy 7/30 dní, z toho přes ref, příchody z odkazů, tisky, odměny,
kredit, karty s filtrem (bez souboru, se souborem, skryté).

**Smazání účtu**: posluchač `AccountErasing` skryje karty, smaže obrázky a soubory karet, profil anonymizuje,
`events.user_id` vynuluje.

## Rozhodnutí nad rámec zadání

1. **MakerWorld nemá veřejný profil ani výpis modelů.** Ověřeno 1. 10. 2026: všechno kromě
   `/api/v1/design-service/design/{id}` vrací Cloudflare výzvu (403), včetně stránky `/@handle`. Ověření přes bio
   tedy nejde. Kód se vkládá do **popisu jednoho vlastního modelu**; z API modelu známe autora (`designCreator.uid`).
   Modely se vkládají jako odkazy. Stejná cesta (kód v popisu modelu) funguje jako záloha i pro Printables.
2. **Každá importovaná karta se kontroluje proti ověřenému autorovi.** Zadání chtělo jen zaškrtnutí „jsem autor“.
   Protože API obou zdrojů vrací autora modelu, cizí model se neimportuje („model nepatří k ověřenému účtu“).
3. **Printables z Hetzneru vrací 403** (známé z kroku 3). Na serveru je proto nutné nastavit `IMPORT_HTTP_PROXY`,
   jinak ověření i import Printables skončí hláškou „zdroj nás teď nepustil“ a zbývají ruční karty. MakerWorld API
   modelu z Romanova PC funguje; ze serveru je potřeba vyzkoušet.
4. **Překladač je nový.** „Existující překladová služba“ v nové aplikaci nebyla. `ClaudeTranslator` volá Messages API
   stejným způsobem jako `PrintAdvisor` (HTTP, strukturovaný výstup, `fallbacks: default`). Výchozí model
   `claude-opus-5-5`, effort `low`; jde změnit přes `ANTHROPIC_TRANSLATE_MODEL` (např. na Haiku kvůli ceně
   hromadného překladu katalogu v kroku C; to je Romanovo rozhodnutí). Popis designéra se překládá věrně, bez
   přepisování; pro katalog je připravený styl `catalog` (krátký neutrální popis bez kupónů a odkazů).
5. **ZIP k jedné kartě bere první model.** Spojování více dílů do jednoho souboru pipeline neumí, takže podle
   zadání „jinak jen první“; ostatní soubory zipu se vypíšou.
6. **Párování zipu zná i názvy souborů u zdroje.** Printables je vrací (`stls { name }`), takže karta „Vase“
   se spáruje se souborem `wavy_vase_body.stl` najisto.
7. **Nový soubor, který neprojde kontrolou, nenahradí ten dobrý.** Karta s funkčním souborem zůstane k tisku
   a ukáže, proč nový neprošel.
8. **Kartu nejde smazat, dokud se podle ní tiskne** (zaplaceno, ve frontě, tiskne se): jinak by designér přišel
   o odměnu.
9. **Obrázky importu jen z hostů zdrojů** (`engines.import.image_hosts`), překódované u nás; žádné cizí URL
   v našich stránkách.
10. **OG obrázek kreslí čisté GD** (`App\Support\OgImage`), bez Intervention Image: žádná nová závislost.
    Krok E do stejné třídy přidá další šablony.
11. **Oprava záložní kontroly sítě.** `PhpStlRepair` (bez Pythonu, lokálně a v testech) hlásil každý model jako
    děravý, protože uzavřenost vůbec nezjišťoval. Teď se ptá `StlTopology`.
12. **Past Eloquentu:** uvnitř tříd modelů `->visible` nečte sloupec, ale chráněný seznam serializovaných
    atributů (a to i u jiného modelu). `DesignerModel::isPrintable()` proto používá `getAttribute('visible')`.
    Stejnou chybu má `PrinterProfile::isReady()` (za vypnutým tržištěm, neopravováno).
13. **Kategorie karty** (`catalog_categories`) přijde s krokem C, kde tabulka vzniká.

## Co zbývá

- Stránka `/models/{slug}` a katalog `/models` (krok C). Do té doby karta se souborem v portfoliu odkazuje na zdroj.
- Připsání odměny, strop 30 %, vlastní tisk bez odměny (krok C).
- Události do GA4 / pixelu a funnel (kroky E, F); `events` se plní už teď.
- Výplaty (IBAN, IČO, DIČ jsou ve schématu, nikde se nezobrazují).

## Nové `.env` klíče

| klíč | výchozí | k čemu |
|---|---|---|
| `ENGINE_IMPORT` | `live` | `fake` = zdroje v paměti (testy) |
| `IMPORT_HTTP_PROXY` | – | proxy pro Printables / MakerWorld (na serveru nutné kvůli 403) |
| `ENGINE_TRANSLATOR` | `claude` | `fake` = bez volání API |
| `ANTHROPIC_TRANSLATE_MODEL` | `claude-opus-5-5` | model pro překlady |
| `ANTHROPIC_TRANSLATE_EFFORT` | `low` | |
| `AI_USD_CZK` | `23` | kurz pro přepočet ceny AI volání |

## Testy

`tests/Feature/DesignerTest.php` (13 testů): zapnutí profilu, ověření tokenem (Printables bio, MakerWorld popis
modelu), import s duplicitou, cizím modelem, obrázky a překlady, překlad jen chybějících jazyků, ruční karta,
soubor (kontrola, slice, odmítnutí), remix bez potvrzení, párování názvů (jisté / k potvrzení / nepřiřazené),
hromadný zip, portfolio 404 a náhled, OG 1200 × 630, `?ref=` cookie + událost + čísla v přehledu, smazání účtu.

## Nasazení (Roman)

```
php artisan migrate --force
php artisan storage:link          # obrázky portfolia jsou na disku public (designers/…)
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker
```

- `.env`: `IMPORT_HTTP_PROXY=…` (bez něj Printables ze serveru nepůjde), `ANTHROPIC_API_KEY` už je.
- Nahrávání: karta přijme soubor do 100 MB, hromadný zip do 500 MB. `upload_max_filesize`, `post_max_size`
  (php.ini) a `client_max_body_size` (nginx) musí 500 MB pustit, jinak velký zip skončí chybou serveru.
- Fronta: import běží na workeru (`matplace-worker`), jeden model po druhém.
