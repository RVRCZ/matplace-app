# AN · Prodejní cesta, část web (session 3, 25.–26. 10. 2026)

Zadání `docs/prompts/prodejni-cesta-web.md` (Roman 25. 10.; celé zadání `docs/prompts/prodejni-cesta.md`). Větev
`feature/sales-path-web` z `origin/main` bf112cb, worktree `C:\matplace-edit-wt`. Cíl: víc dokončených objednávek tisku.
Nic nenasazeno, nic nemergováno; stránka zakázky, platba a texty farmy jsou session 2, hlavní stránka řídící.

## 1. Slepá cesta `/models`

Jedno místo pravdy: `DesignerModel::enoughToShow()` (práh `config/catalog.php` → `menu_from` = 6, keš 10 min pod
`nav.models` jako dřív). Používá ho menu (`layouts/app.blade.php`), úvod Inspirace (`inspiration/index.blade.php`: bez
karet jen dvě věty `models.inspiration.lead_alone`, s kartami věta s odkazem jako dřív), stránka `/models`
(`robots noindex, follow`; hreflang zůstává, canonical ne, stejně jako u stránek Inspirace bez vlastního textu, které tak
čeká `CatalogSeoTest`) a sitemapa (`Sitemaps::pages` přidá `models.index` až s kartami). Prázdný stav `/models` vede
dál: vlastní soubor (`/`, kalkulace), Inspirace (`/model`), nástroje (`/tools`). Stránka ani cesta nezrušena.

## 2. Stránkování ve třech jazycích

`lang/{cs,en,es}/pagination.php` (previous, next) a JSON klíče `Showing`, `to`, `of`, `results`, `Pagination
Navigation`, `Go to page :page` přes `lang/src/pagination.json`. Výchozí `pagination::tailwind` Laravelu zůstává;
test vykreslí `LengthAwarePaginator` ve třech jazycích („Zobrazeno 49 až 96 z 100 výsledků“, „Mostrando … de …“).

## 3. Orientační cena s dopravou

`App\Support\ShippingHint::text()`: z `config/farm.php` `shipping.zones` v měně návštěvníka, pro zemi, kam nejspíš
objedná (země účtu; jinak koruny = CZ, španělština = ES, jinak nejlevnější zóna v zahraničí = SK), výdejní místo nebo
domů, co je levnější; „osobní odběr zdarma“ jen když ho farma nabízí (`Shipping::pickup()`). Nic, dokud farma není
návštěvníkovi nabízena (`FarmGate::offered`, jako tlačítko). Server větu složí (`calc.price.shipping[_pickup]`,
cs/en/es), `ConfigController::payload` ji dá do `MP_CONFIG.shipping_hint`, `calculator.ts` ji ukáže pod „Orientační cena
tisku u nás“ (`#price-shipping`) u odhadu i u přesného výsledku. Výpočet ceny tisku beze změny. Příklad cs: „+ doprava
od 99 Kč (Zásilkovna), přesně podle doručení zvoleného v objednávce.“; en/euro: „+ delivery from €5.90 (Packeta)…“.

## 4. Nástroje: záměry a hledání

`config/tool_intents.php` (nový soubor, ne klíč v `config/tools.php`: každý klíč tam čte patnáct míst jako nástroj)
+ `App\Domain\Tools\ToolIntents`: dárek = kategorie names + `gifts`; z fotky = nástroje, které berou fotku nebo obrázek
(`ToolInput` photo, picture, text_or_picture; ověřeno z konfigurace, 29 nástrojů); domácnost = home; náhradní díl =
parts + repair, check, calc (+ spare, dnes vypnutý); pro firmu = qr, logo, badge, sign, nameplate, stencil, stamp,
coaster, keychain, lightbox, text (11). Řádek záměrů nad kategoriemi (`tools.aims`, `tools.aim.*`), jeden filtr pro
stránku: záměr nebo kategorie, ne obojí; v adrese jako `#gift`. Kategorie, sbalení na telefonu a chipy v řádku
zachovány. Hledání: `tools.keywords` doplněny o účely u 22 nástrojů (kolega, kancelář, firma, narozeniny, visačka na
klíče, náhradní knoflík, dárek z fotky…), `tools.synonyms` o pět skupin (kolega, firma, příležitosti, náhradní díl,
rodina) ve třech jazycích. HTML: `data-words` jen na velké kartě, řádek telefonu je hledá podle klíče (−13 kB);
stránka má lokálně 326 kB, z toho 182 obrázků `<picture>` se srcset (každý nástroj dvakrát: mřížka a řádek telefonu)
81 kB + 55 kB SVG, to je další páka (níže).

## 5. Důvěra jen z pravdivých věcí

`calculator/_trust.blade.php` na konci pravého sloupce kalkulace (na telefonu až za cenou a tlačítky): a) čísla farmy
z `App\Domain\Farm\PublicStats::summary()` jen když třída existuje (session 2, feature/sales-path-farm 56461b0; do
sloučení se čísla neukážou, test má zástupnou třídu); b) poslední zveřejněné video (`FarmVideo` published, titulek
z DB), YouTube nocookie přehrávač až po kliknutí, před ním nic z YouTube (ani náhled; po kliknutí autoplay jako na
stránkách nástrojů); c) ceny skutečných kalkulací z `config/site.php example_items` jako řádky bez obrázků (fotky
výtisků nejsou), odkaz na kalkulaci; v eurech se žádná cena nevymýšlí. Bez recenzí. Blok jen když je farma nabízena.

## 6. Texty

`pages.php` (cs/en/es) „Co děláme“, „Jak to funguje“ (krok 1 „zatím nic neplatíte“, krok 2 „bez registrace a bez
placení“, krok 3 „až na konci objednávky zaplatíte kartou nebo z kreditu“), „Tiskneme sami“, FAQ „Jak se platí a co je
kredit?“ v jednotném znění ze zadání (+ věta o dobírce), „Co když tisk nezačne“ a „Mohu zrušit“ mluví o „částce“, ne
o kreditu. **Obchodní podmínky (`pages.terms`, `business_terms`) jsem nechal**: mluví o platbě jen z kreditu
(§ platba), změna textu smlouvy je na Romanovi (doporučení níže). `/tools` lead už vykal; tykání bylo jen ve výchozím
popisu `app.subline`, který stránka `/tools` teď nepoužívá (§7). `AuditRestTest` scan klíčů rozšířen o
`tools/index`, `tools/card`, `tools/card_row`, `models/index`, `inspiration/index`.

## 7. SEO titulky a popisy

| stránka | titulek (cs) | popis (cs) |
|---|---|---|
| `/tools` | Nástroje pro 3D tisk: z fotky, jména či QR · matplace (53) | „58 nástrojů zdarma: z fotky, jména, loga nebo rozměrů vznikne model, hned vidíte cenu tisku a můžete ho stáhnout nebo objednat.“ (číslo z konfigurace) |
| `/model` | 3D modely k tisku z Printables a MakerWorldu · matplace (55) | stávající |
| `/models` | Modely od designérů k tisku u nás · matplace (54) | stávající |
| `/business` | 3D tisk pro firmy: QR cedulky, loga, visačky · matplace (55) | „Malé série pro firmy…“ |

Klíče `tools.seo.title/description`, `models.seo_title`, `models.inspiration.seo_title` (h1 zůstávají krátké);
en/es stejně. Test ověřuje délky (≤ 60 / ≤ 155) a Open Graph na všech čtyřech stránkách ve třech jazycích.

## 8. Kurátorské stránky

- Dárky se jménem: `/tools/gifts` kryje jména (kategorie names) a je v záměru „Dárek“ (karta mezi prvními). Bez nové stránky.
- Náhradní díly: 4 nástroje + nahrání + poptávka jen s marketplace → jen záměr „Náhradní díl“ na `/tools`.
- 3D tisk pro firmy: 11 nástrojů → stránka `/business` (`PageController::PAGES`, texty `pages.business` cs/en/es):
  nástroje jako karty, ceny ze skutečných kalkulací v měně návštěvníka, postup objednání v jednotném znění, doprava
  z `config/farm.php` (`:cz_point` …, `LandingPage::numbers()` dosazuje `PageController`), kdo tiskne (matplace s.r.o.,
  IČO), čtyři otázky = FAQPage JSON-LD. Canonical a hreflang z layoutu, v sitemapě (`PageController::PAGES`), odkaz ze
  záměru „Pro firmu“ na `/tools`. Route se jmenuje `tools.business` (`PageController::routeName`): hlavní stránka řídící
  na ni přepne odkaz „Pro firmu“ sama, jakmile existuje. Šablona `pages/static.blade.php` umí v sekci `tools` a `prices`, `h1` zvlášť od titulku.

## 9. Měření trychtýře

- Odmítnuté nahrání: server zapíše `failed` se `step` = `upload` a `kind` = `too_big` | `missing` | `format`
  (`UploadController::store`); prohlížeč totéž pro soubor odmítnutý před nahráním (formát, velikost) přes
  `POST /api/refused` (`RefusedController`, throttle 30/min, přijme jen step `upload` a dva kódy, nic o souboru).
  Chyba sítě při nahrání se nehlásí (server ji nevidí a prohlížeč by zdvojil chyby validace).
- `Funnel`: nová cesta `sales`: návštěva → nahrání začalo (`upload` | `failed:upload`) → nahráno → kalkulace → cena
  ukázána → Vytisknout (`order_started`) → registrace (`register` | stránka `/register` ve `visit`/`page` | zakázka
  založená s účtem) → zakázka → platba začala (`checkout_started` | `topup_started`) → zaplaceno. Chyby: nový krok
  `upload`, `checkout` počítá i `failed:pay` (session 2); podíl „dobití dokončená“ (`topup_started` → `topup`).
  `/admin/stats` cestu ukazuje první, s názvy kroků.

## 10. Ověření (26. 10., lokálně, dev server 8013, `scripts/review_round2.mjs`, 390 i 1280 px)

| scénář | jak | výsledek |
|---|---|---|
| nahrání kostky 20 mm, cena, nový řádek s dopravou | test `SalesPathWebTest` (cs „99 Kč“, euro „5,90“, es „9,90“, bez farmy nic) | zelené; lokálně se řádek neukáže, farma tu není veřejná |
| `/tools` hledání „krabička“ i „krabicka“, „dárek pro kolegu“ | `--then` vepsání do pole, snímek | krabička: Krabička na míru + organizér (+ světelný nápis přes synonymum box); kolegu: busta, klíčenka, podtácek, dárek se jménem |
| záměr „Pro firmu“ | klik, snímek | 11 karet, odkaz na `/business` |
| `/business`, `/models`, `/c/<token>` | snímky | bez vodorovného posuvu, bez chyb JS; malé cíle jen stávající chipy řazení na `/models` |
| `/model` ↔ `/models` prázdný i naplněný stav | test (keš `nav.models`) | zelené |
| stránkování cs/en/es | test | zelené |
| sitemapa, JSON-LD | test (`matplace:sitemap`, FAQPage) | zelené |
| stažení modelu, model z nástroje | beze změny v této větvi (netknuto) | neověřováno znovu |

Testy: `SalesPathWebTest` 8 testů; spolu s `WebRound2Test`, `CatalogSeoTest`, `ToolPageTest`, `AuditRestTest`,
`SeoTest`, `LandingPagesTest`, `StatsTest`, `AuditEventsTest` zelené; pint, `tsc`, `npm run build` čisté. Plná sada:
viz konec.

## 11. Co zůstalo a doporučení

- Čísla farmy v bloku důvěry se ukážou až po sloučení `PublicStats` (session 2).
- Obchodní podmínky stále říkají „platí se z kreditu“; sjednocení je změna smluvního textu (verze `terms_version`),
  rozhodne Roman.
- `/tools` 326 kB: 182 `<picture>` (každý nástroj dvakrát) = 136 kB; další krok by byl jeden `<img>` bez srcset v řádku
  telefonu, nebo jeden seznam karet přeskládaný CSS.
- Trychtýř: krok „registrace“ je průchozí i pro přihlášené (zakázka založená s účtem), jinak by cesta přihlášeným
  končila; „platba začala“ z kreditu bez dobití nemá vlastní událost, zaplacení z kreditu jde rovnou do `order_paid`.
- Chyba sítě při nahrání se neměří (viz §9).
- Na produkci neověřeno (headless Chrome s `mp_test=1` nebyl spuštěn; stránky se ověřily lokálně a testy).

## 12. Soubory

`config/{catalog,tool_intents}.php`, `app/Models/DesignerModel.php` (`enoughToShow`), `app/Support/{ShippingHint,
Sitemaps,LandingPage}.php`, `app/Domain/Tools/ToolIntents.php`, `app/Domain/Stats/Funnel.php`,
`app/Http/Controllers/{PageController,Api/ConfigController,Api/UploadController,Api/RefusedController}.php`,
`routes/web.php` (`api.refused`), `resources/views/{layouts/app,inspiration/index,models/index,tools/index,
tools/card_row,calculator/index,calculator/_trust,pages/static,admin/stats/funnel}.blade.php`,
`resources/js/calc/calculator.ts`, `lang/{cs,en,es}/{models,tools,pages,pagination}.php`, `lang/src/{pagination,
calc_shipping,calc_trust}.json` → `lang/{cs,en,es}.json`, `tests/Feature/{SalesPathWebTest,AuditRestTest}.php`.

## 12. Audit webu z 26. 10. a návštěvnost (větev `feature/audit-web-2`, 27. 10. 2026)

Zadání `docs/prompts/audit-web-2.md` (úkoly 1 až 6 kvalita, 7 až 11 návštěvnost mají přednost). Větev z `origin/main`
48fcf13. Nic nenasazeno, nic nemergováno. Netknuto: `calculator/home.blade.php`, farma, stránka zakázky, `tools_seo`, `landing`.

### 12.1 Sdílet výsledek (úkol 7)

- Kalkulace: tlačítko „Sdílet“ bylo (schránka + Web Share API); nově po sdílení pošle `POST /api/shared`
  (`ShareController`, throttle 30/min) s `kind=calc`, `tool` = nástroj, ze kterého model vznikl, a `how` = share | copy.
- Stránky nástrojů: tlačítko `#tool-share` vedle „Stáhnout model“ (`tools/page.blade.php`, ikona `i-share`), skryté do
  výsledku; `Stage.shareable(url)` ho zapne z `fileResult()` s odkazem `/?open=<uuid>` (kalkulace otevře týž model a vede
  k ceně i ke stažení). Web Share API, jinak schránka a „Odkaz zkopírován“ (cs/en/es v `toolpage.php`).
- Měření: událost `share` (meta `kind`, `tool`, `code` = share | copy, nic o modelu) je v `Track::ACTIONS` (čin člověka)
  i `Track::SHARED` (jde do GA se souhlasem). Test `AuditWeb2Test`.

### 12.2 Vložitelná kalkulačka (úkol 8)

- `/embed` (`EmbedController::index`, indexovatelná, v patičce „Kalkulačka na váš web“): kód `<iframe>` ke zkopírování,
  živý náhled, pravidla; texty `lang/{cs,en,es}/embed.php`. Kód nese adresu `/embed/calc` v jazyce stránky.
- `/embed/calc` (`layouts/embed.blade.php`: bez hlavičky, patičky, lišty cookies i skriptů třetích stran; `noindex`):
  nahrání → server změří (čeká na `ready`) → `POST /api/calculations` (velký model `rough_only`) → hned odhad
  (`rough.price_min…max`, doba jako rozpětí), pak přesná cena po řezu → „Otevřít na matplace.com“ = `/c/<kód>` s
  `utm_source=embed&utm_medium=iframe&utm_campaign=<host stránky>`, nový panel. Skript `resources/js/embed.ts`
  (vlastní vstup Vite, 3,7 kB, bez three.js; jen `calc/api.ts` a `site/money.ts`).
- Cookies: v cizím rámu se `mp_sid` (SameSite=Lax) neposílá, každý požadavek dostane novou anonymní session; soubor i
  kalkulace jsou „ničí“ a patří tomu, kdo má odkaz; `/c/<kód>` se otevírá v prvním kontextu jako obvykle. Nic nebylo
  třeba uvolňovat.
- Měření: `Track::classify` → zdroj `embed` pro `utm_source=embed` (`Funnel::SOURCES`, v `/admin/stats` „Vložená
  kalkulačka“); každé načtení rámu z cizího webu zapíše událost `embed` s `meta.host` (jen hostname, nový klíč v
  `Track::META`), tabulka hostů přijde se stránkou měření (§12.3).
- CSP: dnes Report-Only, rám funguje. Pro režim enforce je třeba `frame-ancestors *` a žádné `X-Frame-Options` jen pro
  `/embed/*`. Návrh pro `config/security.php` (řídící zapracuje do `SecurityHeaders`):
  ```php
  'paths' => [
      'embed/*' => ['directives' => ['frame-ancestors' => ['*']], 'headers_enforced' => ['X-Frame-Options' => null]],
  ],
  ```
  (první vzor, který `$request->is()` trefí, překryje direktivy a hlavičky; `null` hlavičku vynechá).

### 12.3 Měření návštěvnosti: nástroje týdně a Search Console (úkol 9)

- `/admin/stats/tools` (`StatsController::tools`, chip „Nástroje týdně“ ve všech statistikách): **unikátní návštěvníci,
  kteří v týdnu použili aspoň jeden nástroj** (`App\Domain\Stats\ToolUse::weekly`): prohlížeče (nebo přihlášení bez
  session) s událostí `calculation`, `generate`, `download`, `order_started` nebo `share` nesoucí `tool`; pohled na
  stránku nástroje se nepočítá, naši (`meta.staff`) ne. Týdny od pondělí (4, 12 nebo 26), celkem a po jazycích; tabulka
  po nástrojích za poslední čtyři týdny.
- **Search Console** (`App\Domain\Stats\SearchConsole`): export uložený jako `docs/data/search-console-<datum>.csv`
  (nejnovější podle jména; adresář `seo.search_console_dir`), oddělovač podle první řádky, sloupce podle názvů
  (Page/Query/Clicks/Impressions/Position i česky), čísla s mezerami a čárkou. Stránky nástrojů ve všech jazycích se
  sečtou k nástroji (`/tools/<slug>` → klíč; hlavní stránka = `calc`), vedle použití tak stojí zobrazení → kliknutí
  (CTR, vážená pozice, až pět dotazů, když je export má); ostatní stránky zvlášť. Bez souboru stránka říká, kam ho dát.
  Roman export dodá.
- Tamtéž tabulka **vložené kalkulačky**: hosty, kde se rám načetl, za 30 dní (`ToolUse::embeds`).

### 12.4 (vyhrazeno: úkoly 1 až 6 níže)

### 12.5 Návraty: e-mail s výsledkem a RSS (úkol 10)

- **„Pošleme vám výsledek“** u busty a mazlíčka: zatímco se model dělá (2 až 4 minuty), pod stavem je formulář s e-mailem
  (`#figure-notify` ve `figure.blade.php` i `pet.blade.php`, logika `figure.ts`: ukáže se se známým tokenem, i po
  návratu na adresu běžícího modelu, zmizí po dokončení). `POST /api/generate/{token}/notify` (throttle 10/min) uloží
  `notify_email` a `notify_locale` (migrace `2026_10_27_100000`), hotový model odpoví `done`, nepovedený 410. Úloha
  `GenerateModel` po `done` zavolá `GenerationReady::sendFor()`: jeden e-mail (`mail.plain`, texty `figure.mail.*`
  cs/en/es) s odkazem na stránku nástroje s `?generation=<token>` v jazyce návštěvníka; adresa se po odeslání smaže
  (`notify_email` null, `notified_at`), podruhé se nepošle. Žádný účet, žádný newsletter; souhlas je ve větě u pole.
  Událost `notify` (meta kind) pro měření návratů.
- **RSS** `/feed` (`FeedController`, i `/en/feed`, `/es/feed`): zveřejněné články v jazyce adresy a nástroje s datem
  ověření tiskem (`verified` je jediné datum, které nástroj má; bez něj se v kanálu neobjeví, dnes 6 nástrojů), 50
  položek, `Cache-Control` 10 min, `<link rel="alternate" type="application/rss+xml">` v hlavičce každé stránky a odkaz
  „RSS“ v patičce. Nové nástroje bez data ověření by potřebovaly klíč `since` v `config/tools.php` (doporučení).
