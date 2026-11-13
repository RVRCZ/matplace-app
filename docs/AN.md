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

**Objednávka bez účtu (session G, od 28. 10. 2026):** `order_created` a `checkout_started` vznikají i bez `user_id`,
`order_paid` se připojí k návštěvě, která zakázku založila, a `register` s `meta.kind = checkout` přijde až PO `order_paid`
(účet vzniká ve webhooku platby). Trychtýř to unese beze změny logiky: kroky se počítají podle značek návštěvy bez ohledu
na pořadí a krok prodejní cesty „Registrace, účet, nebo zakázka bez účtu“ (`register|register_visit|order_created`) host
projde přes `order_created`; návštěva se počítá jednou (značky jsou booleovské). Sloupec „registrace“ v rozpadu podle
zdroje teď zahrnuje i účty založené platbou (to je správně: účet vznikl). `refund` není čin člověka (není v
`Track::ACTIONS`), do cest ani do lidí nevstupuje. Test v `SalesPathWebTest`.

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

### 12.4 Opravy kvality z auditu (úkoly 1 až 6) a fotky v bloku důvěry

- **Plurály a jazyk (úkol 1).** Věty s počtem přes `trans_choice` (cs 1 / 2 až 4 / 5+, en a es 1 / jiné), texty v
  `lang/src/plurals.json`: blok důvěry `calc.trust.printed`, `calc.trust.days`, `calc.trust.videos` (`_trust.blade.php`);
  medián předání se zaokrouhluje nahoru a čte se „obvykle do jednoho dne“ / „do 4 dnů“ („within“), ne „1 days“; limit
  busty a mazlíčka `figure.limits` (figure.blade.php, pet.blade.php, 4 místa); `calc.days` v kalkulaci (`plural()` v
  calculator.ts vybírá tvar podle posledního čísla rozsahu, „2–4 dny“, „5–7 dní“; text cestuje v JSON s tvary
  oddělenými `|`). **Hlavní stránka:** `home.benefit.free_tools` tiskne `calculator/home.blade.php` (řídící), proto jen
  připraven klíč `home.benefit.free_tools_n` ve třech jazycích; řádek 44 stačí změnit na
  `$b === 'free_tools' ? trans_choice('home.benefit.free_tools_n', $n = (int) config('ai.daily_limits.generate_guest'), ['n' => $n]) : __('home.benefit.'.$b, [...])`
  a starý klíč smazat (dnes na /en/ „3 model a day“). Titulek videa z farmy (`FarmVideo.title`, česky z DB) jen na cs;
  en a es dostanou „A print from our farm: <nástroj>“ (nástroj přes `order.modelFile`, když model vznikl nástrojem),
  jinak „A print from our farm“. Projito `lang/*.json` i `lang/*/*.php`: zbývá `farm.slices_left` („ještě :n modelů“,
  lang/*/farm.php, session 2) a `printer.public.lead` („do :n dní“, tržiště, vypnuto). Test
  `test_sentences_with_numbers_follow_the_count_in_three_languages` (/, /en, /es s 1 a 3).
- **`href="#"` (úkol 2).** „Upravit tvar a rozměry“ vede na `/tools` (skript přepíše na nástroj souboru; lišta je do té
  doby skrytá), „Jen samotný model (.stl)“ a odkaz v dialogu výběru tiskárny jsou `<a>` bez `href`, dokud skript nezná
  soubor (`download.ts` nastaví, `printer_pick.ts` nastaví nebo odebere). Na stránkách nástrojů žádné další; dva
  zůstávají na stránce zakázky (`farm/order.blade.php` `#farm-short`, `#farm-topup`, session 2). Test: žádné `href="#"`
  na /, /tools/qr, /tools/box.
- **Obrázky (úkol 3).** Karty „Náš výběr“ na /tools: první dvě `loading="eager"`, ostatní `lazy`; `fetchpriority="high"`
  u nich není, má ho logo v hlavičce (jedno na stránku). `tools/picture` má nový parametr `priority` (výchozí = `eager`,
  stránky nástrojů tedy beze změny), `card_row` ho propouští. Řádek karty má 96 px, při 3× DPR 288 px: 480w stačí,
  400w by ušetřilo jen pár kB na kartu, nedělám. Test `test_the_first_two_picks_of_the_tools_page_load_at_once`.
- **Inline konfigurace (úkol 4).** `MP_CONFIG` a `MP_I18N` (konstanty kalkulace + 205 textů skriptů) už v HTML nejsou:
  `GET /api/calc-config?l=<jazyk>&m=<další krok>&v=<hash obsahu>` (`ConfigController::calc`, `App\Support\CalculatorTexts`,
  `NextStep::force`) vrací `{config, i18n}` s `Cache-Control: public, max-age=86400`; verze v adrese se mění s obsahem
  (i s dopravní nápovědou podle měny), takže se nikdy nečte zastaralá kopie a dva návštěvníci nesdílí jednu. Stránka
  nese jen `window.MP_CONFIG_URL` a `<link rel="modulepreload">` na chunk kalkulace; `app.ts` stáhne JSON (při chybě
  jednou zopakuje, pak ukáže `#mp-config-error` a kalkulaci nabootuje bez cen) a teprve potom chunk importuje (ten čte
  globály při načtení modulu). Stránky check, mold a repair si své texty vkládají dál samy. Měření lokálně (dev server
  8013, `/` cs): HTML 120 423 B → 96 953 B (gzip 28 958 → 21 241), inline skripty 30 549 B → 6 832 B (8 skriptů);
  JSON 23 840 B (gzip 7 821), první návštěva o jeden požadavek navíc souběžně s chunkem, další den z cache. Na produkci
  (128 kB) čekej ~104 kB. Ověřeno v headless Chrome, že JSON dorazí a kalkulace nabootuje (`review_round2.mjs --then`).
  **3D prohlížeč líně až po souboru (posudek, nedělám):** po vykreslení se hned stahuje chunk `boot` 256 kB (gzip 81 kB)
  + `BufferGeometryUtils` 544 kB (gzip 137 kB) + `viewer` 48 kB + `loaders` 34 kB. Rozdělit na „před souborem“ (nahrání,
  odhad, formulář, ~80 kB) a „po souboru“ (three.js, prohlížeč, parsery) je 1 až 2 dny práce: dynamické importy v
  calculator.ts, mini prohlížeče karet a stránky nástrojů ho potřebují hned, `check_bundle` a `check_preview_colors`
  upravit. Úspora ~600 kB přenosu (gzip ~230 kB) pro návštěvníka, který soubor nenahraje; na LCP vliv nemá (chunk se
  stahuje až po vykreslení), šetří data, baterii a práci hlavního vlákna. Doporučení: až po změření INP na telefonu.
- **Strukturovaná data (úkol 5).** `/about` a `/contact`: `Organization` v plné podobě (`Schema::organization(true)`):
  `legalName` matplace s.r.o., `address` (PostalAddress Rybná 716/24, Praha 1, 110 00, CZ), `identifier` IČO 22499008
  jako `PropertyValue`, `email`, logo, `sameAs` (YouTube a sítě z `config/seo.php`); údaje v `config/seo.php`
  `organization` (DIČ přes `SEO_VAT_ID`, zatím prázdné = vynecháno). Hlavní stránka nese krátkou podobu jako dřív.
  `/materials`: `ItemList` materiálů (název, kotva karty, popis = lead stránky). `LocalBusiness` až s osobním odběrem
  a hodinami (Roman, D17). Test `test_about_contact_and_materials_carry_structured_data` čte JSON-LD ze stránek.
- **Rozcestník výš (úkol 6, posudek, nedělám).** „Kam dál“ (`landing.titles.next`: podobné nástroje + ke čtení) je část
  `next` partialu `tools/_landing`, vkládaná z `tools/_content` na konci obsahu každé stránky s nástrojem včetně hlavní
  (layout `app.blade.php` za `@yield('content')`, po bannerech). Na hlavní stránce leží za kalkulací, příklady a FAQ,
  hluboko pod ohybem; návštěvník bez souboru k němu nedojde. Jedna řádka odkazů hned pod blokem nástrojů by byla buď
  duplicita (+1 až 2 kB HTML, proti zadání) nebo přesun v `home.blade.php` (řídící). Pod dlaždicemi nástrojů nemá
  „Podobné nástroje“ smysl (dlaždice jsou nástroje); smysl má „Ke čtení“ (3 články) – doporučuji řídící zvážit v
  `home.blade.php`. `#search-section` beze změny.
- **Fotky v bloku důvěry (dohoda se session 2).** `PublicStats::latestPhotos(4)` (jen když metoda existuje): mřížka
  čtyř fotek, WebP přes `<picture>`, `loading="lazy"`, alt „Výtisk z farmy matplace: <nástroj | model zákazníka>“
  (nástroj přes `config/tools.php`, titulek v jazyce stránky). Bez fotek se nic nevykreslí.
- **Testy po dávkách B a C (opraveno 27. 10. večer, větev `fix/tests-after-calc-config`):** šest testů četlo texty
  kalkulace z HTML nebo hledalo holé `<link rel="alternate"` (RSS je také alternate). Pomocník `TestCase::calcConfig()`
  stáhne JSON z `window.MP_CONFIG_URL` a vrátí `config` + `i18n` (CustomColorTest, MarketplaceSwitchTest, ToolsFlowTest ×2);
  LocaleUrlTest hledá `<link rel="alternate" hreflang=`. PapelPortraitTest padal jinak: `putenv('PAPEL_REMBG=off')` se do
  Pythonu nedostal (Symfony Process dává potomkovi jen proměnné z `$_SERVER` při startu), takže test sledoval, co má
  stroj nainstalované; `PythonTool::switches()` předává `PAPEL_REMBG` jménem.

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

### 12.6 Katalog Inspirace: dvě cesty k rozhodnutí (úkol 11, nic neprovedeno)

Stav: 8 592 modelů, 375 indexovaných (`CATALOG_INDEX_REQUIRES=enriched,price,file`: obohacený text, cena a soubor;
`CatalogModel::isIndexable`, sitemap i `noindex,follow` to respektují). Cena dnes (`ModelPricing::quoteCatalog`): z karty
autora, když je rozřezaná (`basis` card), jinak z `est_minutes`/`est_grams` starého webu v nejlevnějším nahraném druhu
(`basis` estimate). Počty po druzích neznám (produkční DB nečtu); řídící je zjistí jedním dotazem:
`CatalogModel::shown()->where('file_available', true)->count()` a `->whereNotNull('model_file_id')->count()`.

**(a) Hodnota navíc na detailu**: cena ve třech materiálech a doba tisku z kalkulace souboru (jen `file_available`),
`Product` + `Offer`.
- Co: příkaz `catalog:slice [--limit=N] [--indexed]` rozřeže soubor modelu (`CalculationService::sliceNow`, mimo špičku,
  fronta farmy má přednost) a uloží minuty, gramy a datum do nových sloupců (`slice_minutes`, `slice_grams`, `sliced_at`);
  detail ukáže tabulku PLA / PETG / ASA (ceny `PriceCalculator` s ceníkem farmy, `FarmPricingProfile`, měna návštěvníka)
  a dobu tisku; JSON-LD `Product` (`name`, `image` ze zdroje, `description`) s `offers` jako `AggregateOffer`
  (`lowPrice`/`highPrice`, `priceCurrency`, `availability` InStock) – Google u Offer vyžaduje cenu a měnu, obojí máme.
- Práce: jedna session, 6 až 8 h (migrace, příkaz, výpočet cen z hotových tříd, blok v `inspiration/show`,
  `Schema::product()`, testy s falešným slicerem). Stroj: řez 20 až 90 s na model (docs/M.md), 375 souborů ≈ 3 až 9 h
  fronty; celý katalog se souborem řádově dny, po dávkách v noci.
- Riziko: licence modelů (CC BY-NC a podobně) – `Offer` na tisk cizího NC modelu je nabídka prodeje. Doporučení:
  `offers` jen u modelů, jejichž licence prodej dovoluje (sloupec licence je třeba doplnit při obohacení), u ostatních
  `Product` bez `offers` a cena jen v textu stránky. Přínos: rich results s cenou u 375 stran; CTR před a po je vidět
  v importu Search Console (§12.3) po 4 týdnech.

**(b) Přepínač: indexovat jen modely s kalkulací**, ostatní `noindex`.
- Co: nový požadavek `calculation` do `CatalogModel::INDEX_NEEDS` (splněn, když `quoteCatalog()['basis'] === 'card'`
  nebo po (a) `sliced_at` není null), `CATALOG_INDEX_REQUIRES=enriched,calculation,file`; sitemap a `noindex` už jdou přes
  `isIndexable()`, mění se jen podmínka.
- Práce: 2 h včetně testu. Dopad: bez (a) klesne index z 375 na počet rozřezaných karet autorů (odhadem jednotky až
  desítky) a ztratí se dnešní dlouhý ocas; s (a) zůstane 375, jen s přesnější cenou.
- Doporučení: (b) samostatně ne. Nejdřív (a) na 375 indexovaných, po 4 týdnech srovnat CTR ze Search Console a pak
  zvažovat rozšíření indexu o další obohacené modely se souborem, ne zúžení. Roman rozhodne, bez jeho slova se nic
  z toho nedělá.

## 13. Katalog Inspirace: cena ve třech materiálech a doba tisku z řezu souboru (větev `feature/catalog-value`, 27. 10. 2026)

Roman rozhodl variantu (a) z §12.6; (b) se nedělá. Nic z toho neběželo na produkci: řezání spustí řídící po nasazení.

### 13.1 Co je kde

- **`php artisan catalog:slice`** (`App\Console\Commands\SliceCatalog`): rozřeže soubor modelu v tomto procesu (žádná fronta,
  `SliceCalculation::dispatchSync`) s výchozími parametry kalkulace (`PLA`, standard, výplň 15 %, 1 ks) a zapíše do
  `catalog_models` sloupce `slice_minutes`, `slice_grams`, `slice_material`, `sliced_at`, `slice_error` (120 znaků) a
  `slice_calculation_id` (FK na `calculations`, při smazání null; migrace `2026_10_27_200000`, šířky proti MariaDB:
  minuty unsigned int, gramy decimal(8,1)). Kalkulace vznikne přes `CalculationService::create(..., sliceNow: false)`,
  patří nikomu (bez session i uživatele), hotový řez stejného souboru a nastavení se znovu použije, takže opakovaný běh
  nad modelem nic nestojí. `--limit` (výchozí 50), `--sleep` sekund mezi modely (výchozí 10), `--ids`, `--indexed` (jen
  indexovatelné), `--force` (znovu přes výsledek i chybu), `--dry-run` (vypíše kandidáty a počty, nic neřeže).
  **Pořadí:** nejdřív indexovatelné modely (`scopeIndexable`, dnes 375), pak ostatní se souborem; v obou podle
  návštěvnosti. Limit se bere z tohoto pořadí, takže `--limit=50` po nocích projde nejdřív indexované.
  **Řezat lze jen model, jehož soubor držíme (`model_file_id`)**: `file_available` samo žádný soubor nenese (importér ho
  nastavuje false, LocalCatalogSearch ho jen čte), příkaz vypíše, kolik takových modelů je. Chybějící nebo nehotový
  soubor = `slice_error` (`file_missing`, `file_not_ready: …`, `file_missing_on_disk`) a běh jde dál; návratový kód
  FAILURE jen když nic neprošlo.
- **Model (`CatalogModel`)**: `hasSlice()`, relace `sliceCalculation()` a `modelFile()`; řez platí jako „cena“ pro
  index (`scopeIndexReady` i `indexGaps`), takže obohacený model se souborem je po řezu indexovatelný i bez čísel starého webu.
- **Ceny (`ModelPricing::sliced`)**: jeden kus v materiálech `config('catalog.value_materials')` = PLA, PETG, ASA: gramy
  podle hustot (`config/materials.php`), minuty podle rychlosti materiálu (`props.time`) – stejně jako porovnání
  materiálů v kalkulačce (`renderCompare`); ceník farmy pro daný materiál přes `FarmPricingProfile::profile($code)`
  (druh toho materiálu, když je nahraný, jinak nejlevnější druh se štítkem „nejlevnější druh u nás“) a `PriceEngine::price`
  (časový faktor tiskárny → zobrazená doba tisku je doba na farmě). Měna návštěvníka (`Currency::current()`).
  `quoteCatalog` bere řez hned za kartou autora (`basis` slice), před odhadem starého webu.
- **Stránka `/model/{slug}`**: blok `#model-calc` pod CTA: tabulka materiál / doba tisku / cena za kus, poznámka
  s datem řezu a textem, že barvu, materiál a počet kusů vybere objednávka; řádek „Odhad tisku“ (čísla starého webu) se
  po řezu skryje. **Objednat** (`models.inspiration.calc_open`) vede na stránku kalkulace `/c/<token>` – tam je model
  načtený, kalkulačka a cesta na farmu; jen když licence prodej dovoluje (`license_restricted` false). U nekomerční
  licence zůstává tabulka (co by to stálo) i původní text „vytiskněte si ho pro sebe“, bez odkazu.
- **JSON-LD**: `Schema::product()` = `Product` (název, popis, obrázky, url) s `offers` jako `AggregateOffer`
  (`lowPrice`/`highPrice` ze tří materiálů, `priceCurrency` podle návštěvníka, `offerCount` 3, `availability` InStock,
  `itemCondition` New, `seller` = naše Organization), jen u prodejných modelů; `CreativeWork` zůstává na všech.
- **Admin `/admin/catalog`**: ve sloupci obohacení „řez 27. 10.“ (title: minuty a gramy) nebo „řez selhal“ (title: chyba).
- Texty `models.inspiration.calc_*` v `lang/*/models.php` (cs/en/es). Test `tests/Feature/CatalogValueTest` (pořadí,
  zápis, chyby, dry-run, stránka, tři materiály, Product/Offer v Kč i €, nekomerční licence bez Offer).

### 13.2 Jak to spustit na produkci (řídící)

1. Nasadit (migrace přidá sloupce), `php artisan catalog:slice --dry-run` – vypíše počty: kolik modelů má soubor, kolik má
   jen `file_available`, kolik je indexovatelných a kolik kandidátů.
2. V noci `php artisan catalog:slice --limit=60 --sleep=15` (Orca 20–90 s na model, s pauzou ≈ 1 model/min → hodina na
   běh); opakovat, dokud `Candidates` neklesne na 0. Indexované jdou první. Řezy jdou týmž slicerem jako objednávky
   (`CachedSlicer` → Orca), proto `--sleep`: fronta farmy má mít volno.
3. Zkontrolovat pár stránek (`/model/<slug>` s `#model-calc`, Rich Results Test na `Product`), `/admin/catalog` ukazuje „řez“.
4. Chyby `slice_error` v adminu; `--force --ids=…` po opravě souboru.

### 13.3 Co zůstává

- Počty modelů se souborem na produkci neznám (DB nečtu); jestli jich je málo, cena ve třech materiálech se objeví jen
  u nich – `file_available` bez souboru by chtělo import souborů (odkud?), to je rozhodnutí Romana.
- Licence: `Offer` jen kde `license_restricted` false (CC0, CC BY, CC BY-SA, free_commercial); u CC BY zůstává povinnost
  uvést autora – objednávka ji píše do poznámky (`attribution()`), stránka ji říká.
- Google může u `Product` hlásit chybějící `review`/`aggregateRating` jako doporučení; recenze nemáme, nevymýšlíme.
- Stránka kalkulace `/c/<token>` z katalogu: kalkulace patří nikomu; pokud by se někdy kalkulace promazávaly, FK dá null
  a stránka ukáže tabulku bez tlačítka (`$sellable` to hlídá).

## 14. Search Console natrvalo přes API (větev `feature/search-console-api`, 27. 10. 2026)

Roman: čísla ze Search Console bez ručního exportu. Brief `docs/prompts/search-console-api.md`. Nic nevolalo Google
naostro: účet připojí Roman v adminu, první stažení pak spustí tlačítko nebo noc.

### 14.1 Co je kde

- **Připojení** (`App\Domain\Stats\SearchConsoleClient`, podle `YouTubeClient`, bez SDK): stejný OAuth klient jako YouTube
  (`config/seo.php` `search_console.client_id/secret` = `SEARCH_CONSOLE_CLIENT_ID` nebo `YOUTUBE_CLIENT_ID`), rozsah jen
  `webmasters.readonly`, `access_type=offline` + `prompt=consent` (vždy refresh token), callback přesně
  `route('admin.search_console.callback')` = `https://matplace.com/admin/search-console/callback`. Po výměně kódu se
  přečte `sites.list`; vlastnost se vybere sama, když je jediná nebo je to `sc-domain:<host>` / `APP_URL/`, jinak ji
  vybere admin. Tokeny šifrované v `search_console_accounts` (`SearchConsoleAccount`, jeden řádek), odpojení = revoke.
- **Admin `/admin/search-console`** (`Admin\SearchConsoleController`, chip „Search Console“ ve statistikách, sekce
  Statistiky v admin navigaci): stav (.env, připojeno, vlastnost), výběr vlastnosti, „Stáhnout teď“, „Odpojit“, poslední
  stažení a chyba, posledních 14 dní z databáze. Bez připojení vypíše, co má Roman kliknout v Google Cloud.
- **Data** `search_console_rows` (migrace `2026_10_27_210000`): den, stránka (1000), dotaz (500, null = součet stránky),
  země (ISO alpha-3 malými, `cze`), kliknutí, zobrazení, CTR, pozice; unikát `row_key` = sha1(den|stránka|dotaz|země),
  protože MariaDB neuindexuje stránku vedle dotazu; index (den, země). Řádky bez dotazu jsou součty stránek (Search
  Console v řádcích s dotazem anonymní dotazy vynechává, oba druhy se nikdy nesečtou na stejné číslo).
- **Stahování** (`SearchConsolePull`, příkaz `searchconsole:pull [--days=3] [--since=YYYY-MM-DD]`): po dnech dva dotazy
  `searchanalytics.query` (`page,country` a `page,query,country`), `rowLimit` 25 000 a `startRow` dokud odpověď není
  kratší, `type=web`; upsert po 500 (idempotentní: stejný den podruhé = přepis). První běh účtu bez `--since` jde
  16 měsíců zpět (tolik API drží, `first_pull_months`), `--since` dřív se zkrátí na to. 429 = jedna pauza
  (`retry_seconds` 60) a opakování; `invalid_grant`/401 zapíše `last_error` a řekne „připojte znovu“. Rozvrh
  `routes/console.php` denně 5:20 `--days=3` (Search Console má 2 až 3 dny zpoždění). Kvóta: 2 dotazy na den dat,
  16 měsíců ≈ 1 000 dotazů + stránkování, hluboko pod limitem.
- **Čtení** (`App\Domain\Stats\SearchConsole::current(od, do, země)`): z databáze, když má řádky (`source` api: součty
  stránek se počítají, dotazy jen pojmenují top 5), jinak nejnovější CSV v `docs/data` jako dřív (`source` csv).
  Stejný tvar pro oba zdroje (společný `aggregate`), navíc `by_locale` (jazyk podle prefixu adresy) u každého nástroje.
  `/admin/stats/tools` bere období podle zvolených týdnů (4/12/26), řádek „Data ze Search Console (API) od … do …“,
  filtr země (všechny / Česko / ostatní), rozpad zobrazení po jazycích pod číslem nástroje; vzhled jinak stejný.
- Test `tests/Feature/SearchConsoleApiTest` (6 testů, falešný Google): připojení a callback, výběr vlastnosti, jen admin,
  stahování po dnech a stránkách odpovědi (429 jednou), upsert dvakrát = stejné počty, první běh 16 měsíců, odmítnutý
  grant, statistiky z DB s filtrem země i z CSV.

### 14.2 Co klikne Roman (podle briefu)

Google Cloud, projekt s YouTube: zapnout „Google Search Console API“; do OAuth klienta přidat redirect URI
`https://matplace.com/admin/search-console/callback`; na obrazovce souhlasu (režim testování) rozsah
`webmasters.readonly` a svůj účet mezi testovacími uživateli. Účet musí být vlastník nebo uživatel vlastnosti
matplace.com. Pak `/admin/search-console` → Připojit → (vybrat vlastnost) → Stáhnout teď (první běh 16 měsíců, pár
minut) – nebo nechat noci.

### 14.3 Co zůstává

- **Odpojení a sdílený grant (oprava 28. 10. večer, větev `fix/shared-google-grant`):** Googlův `revoke` zneplatní celé
  oprávnění uživatele pro OAuth klienta, všechny rozsahy. Když Roman v adminu Search Console odpojil a znovu připojil,
  přestal fungovat i YouTube (stejný klient, stejný účet; `youtube:stats` „Token has been expired or revoked“). Teď
  `SearchConsoleClient::disconnect` token jen zapomene, pokud klient sdílí s YouTube (`sharesClientWithYouTube`), revoke volá
  jen s vlastním klientem; `YouTubeClient::disconnect` revoke přeskočí, dokud Search Console čte stejným klientem. Potvrzení
  v adminu to říkají. YouTube Roman připojí znovu ručně. Test v `SearchConsoleApiTest`.
- Země v CSV nejsou: filtr se ukazuje jen u dat z API.
- Stránka `/model/<slug>` v Search Console: řádky má, do nástrojů se nepočítá (spadá do „ostatní stránky“, top 20);
  pro katalog (§13) by stálo za to přidat součet „inspirace“ – malá úprava `SearchConsole::aggregate`.
- Výmaz starých řádků neděláme (16 měsíců × stovky řádků denně = statisíce řádků, v pořádku).

## 15. Návštěvnost, třetí dávka: soubory k modelům ručně, fotky výtisků u nástrojů, Search Console týden po týdnu (větev `feature/traffic-photos-console`, 28. 10. 2026)

### 15.1 Katalog: soubor k modelu nahraný ručně (Romanova varianta b)

Produkce nedrží žádný soubor modelu (§13.3), stahování ze zdrojů Roman odložil; soubory stáhne ručně a nahraje.
- **`/admin/catalog/files`** (chip „Soubory“): **Top 30 modelů bez souboru** podle zobrazení stránek `/model/<slug>`
  v Search Console za 90 dní (`SearchConsole::modelPages`, jazyky dohromady), doplněno nejnavštěvovanějšími
  (`view_count`); bez řádků Search Console jen podle návštěvnosti. U každého odkaz na zdroj, licence, čísla a formulář
  „Nahrát soubor“. Pod tím **modely se souborem**: stav zpracování, stav řezu (datum, minuty, gramy, odkaz na kalkulaci
  `/c/<token>`, nebo chyba), „Rozřezat znovu“ a nahrazení souboru.
- **Nahrání** (`CatalogController::file`, i v detailu modelu v boxu „Soubor modelu“): formáty kalkulačky
  (`ConverterChain::inputFormats`, STL/3MF/OBJ…), limit `uploads.max_mb`; soubor se uloží jako `ModelFile`
  s `origin = catalog`, `origin_ref` = id modelu, bez vlastníka (nikde ke stažení; `matplace:prune` ho nechá, dokud na
  něj model ukazuje), `model_file_id` + `file_available` se nastaví, starý řez se smaže. Pak ve frontě
  `ProcessModelFile` a za ním řetězem `SliceCatalogModel` (nová úloha, `CatalogSlicer` = logika vyňatá z `catalog:slice`,
  obě cesty sdílí). Výsledek i chyba (`slice_error`, stav souboru) jsou vidět v adminu. „Rozřezat znovu“ dá řez do fronty.
- Test `CatalogValueTest`: nahrání (ne-admin, špatný formát, krychle → soubor připraven a řez hotový, stránka indexovaná a
  s `#model-calc`, znovu řez, nový soubor nahradí starý), stránka Soubory (pořadí bez a se Search Console, skryté modely
  vynechané, formulář u každého, zdroj k stažení).

### 15.2 Fotky výtisků na stránkách nástrojů

- **`App\Domain\Tools\ToolPhotos`**: zaplacené dokončené zakázky s fotkou (`photo_path`, `PrintPhotos`), jejichž model
  vznikl nástrojem (`model_files.origin_ref`; busta z fotky = `origin generated`), nejnovější první, nejvíc čtyři, hodinu
  v cache per nástroj. **Souhlas:** jen zakázky s `video_consent`, výjimkou nástroje, které berou jen rozměry
  (`ToolInput::of` = `sizes`: krabička, váza, organizér…), jejich výtisk nenese nic o majiteli. Fotka je výtisk na pozadí,
  ne člověk; cena zákazníka se neukazuje nikde.
- **Blok „Takhle to vytiskli“** (`tools/_photos.blade.php`, v `tools/_content` za vstupní stránkou): mřížka 2 × 2
  (na větším displeji 4), `<picture>` s WebP, `loading="lazy"`, alt „Výtisk z nástroje <název>, <materiál>, <datum>“,
  pod fotkou materiál a datum. Bez fotek se nic nevykreslí.
- **`calculator/_examples`**: karta příkladu bez ručně vybrané fotky (`photo` null v `config/site.php`) vezme nejnovější
  fotku výtisku stejného nástroje a odloží štítek „náhled“; Romanův ruční výběr má přednost.
- Texty `tools.photos.*` (`lang/src/tool_photos.json`). Test `ToolPhotosTest` (souhlas vs. rozměrový nástroj, nejvíc 4,
  nejnovější první, busta = figure, en datum, cena mimo blok; příklad bere fotku).

### 15.3 Search Console: týden proti týdnu a dotazy bez kliknutí

- `/admin/stats/tools` (jen se zdrojem API): **„Týden proti týdnu: zobrazení → kliknutí → použití“** pro poslední
  kalendářní týden, který má Search Console celý (neděle ≤ nejnovější datum dat), proti týdnu před ním, per nástroj
  s rozdílem (zelené plus, červené minus); použití = unikátní návštěvníci s nástrojem v tom týdnu (`ToolUse::weekly`),
  zobrazení a kliknutí `SearchConsole::weeklyByTool` (součty stránek po týdnech od pondělí). Respektuje filtr země.
- **„Dotazy bez kliknutí (28 dní)“**: `SearchConsole::zeroClickQueries` – dotazy se součtem kliknutí 0, podle zobrazení,
  s pozicí a jednou ze stránek, které se u nich ukázaly; 20 řádků, námět pro N1 a blog.
- Dnes (28. 10.) je vlastnost nová a historie se teprve doplňuje: tabulka týdnů se objeví, až bude celý týden dat.
- Test v `SearchConsoleApiTest` (týdny po nástrojích, dotaz bez kliku, filtr země, stránka).

### 15.4 Co zůstává

- Nahrání souboru je ruční: 30 modelů = 30 stažení u zdroje; stránka ukazuje další, jak ubývají.
- Sada s více díly: nahrát jeden hlavní díl, cena je pak za něj (v adminu to není řečeno u modelu; poznámka je v hlavičce).
- Fotky: dnes 12 složených fotek v produkci; které nástroje mají výtisk s fotkou, se ukáže po nasazení.

## 16. Inspirace ve vyhledávání: titulky s naší hodnotou a staré adresy `?lang=` (větev `feature/inspiration-seo-titles`, 28. 10. 2026)

Search Console (16 měsíců): stránky nástrojů bez zobrazení, hlavní stránka 394/84, stránky `/model/…` desítky zobrazení
a skoro žádné kliknutí (wood-decking-spacer 142/0, drybox 96/0, cursed-furby-mask?lang=en 64/12).

### 16.1 Titulek a popis detailu (`App\Domain\Catalog\InspirationSeo`)

- Titulek: „<název>: cena 3D tisku a stažení modelu · matplace“ (en „3D printing price and model download“, es „precio de
  impresión 3D y descarga del modelo“); název zkrácený na 45 znaků (`…`), aby slova titulku zůstala celá; dřív jen
  „<název> · 3D model · matplace“.
- Popis: s řezem souboru (§13) „Cena 3D tisku „<název>“: 99 Kč (PLA), 110 Kč (PETG), 130 Kč (ASA), doba tisku 1 h 30 min.
  Soubor stáhnete u zdroje, licence CC BY…“ (měna návštěvníka, nejkratší doba ze tří); bez řezu „„<název>“: spočítáme cenu
  3D tisku, vytiskneme a pošleme. Soubor stáhnete u zdroje.“ a za tím náš text stránky, pokud se vejde (obohacené modely).
  Vždy nejvýš 155 znaků (`Str::limit` 152 + `…`). Texty `models.inspiration.seo_title_one`, `seo_desc_sliced`,
  `seo_desc_plain` v `lang/*/models.php`. Test ve třech jazycích s limity délky (`CatalogValueTest`).
- Zbývá: titulek i s brandem má až ~95 znaků; Google ukáže ~60, zkrátí za názvem. Dřívější „· 3D model“ v titulku
  nikde jinde nebyl.

### 16.2 Staré adresy `?lang=en` a jazykové verze

- `/model/<slug>?lang=en` → 301 na `/en/model/<slug>` (middleware `SetLocale`, platilo už dřív); stránka v jazyce má
  `canonical` na sebe a `hreflang` cs/en/x-default (jen indexovatelné stránky; `noindex, follow` bez canonicalu zůstává).
- **Nález:** model bez textu v tom jazyce (`CatalogModel::locales()` = jen cs) dával na `/en/model/<slug>` 404 „jen česky“
  – a tam vedl i 301 ze starého `?lang=en`. Teď `InspirationController::show` pošle 301 na českou stránku
  (`Locales::urlFor('cs')`), takže řetěz `?lang=en → /en/… → /model/…` končí na stránce, ne na 404. Jen pro Inspiraci;
  články a ostatní stránky nechávám (LocaleUrlTest).
- `/admin/catalog/files`: sloupec „Zobrazení SC“ i u modelů se souborem (součet jazyků za 90 dní).

## 17. Dvě drobnosti obsahové session: tácek puzzle a kalkulace souboru s díly přes podložku (větev `fix/puzzle-tray-and-parts-calc`, 28. 10. 2026)

### 17.1 Puzzle: tácek (`frame`) se zapíná na nejvyšší úrovni těla

`POST /api/files/{uuid}/edit` bere nastavení nástroje **vedle `op`** (`{op: "puzzle", rows, cols, lock, frame: true}`), tak
to posílá i stránka (`edit.ts`); `ModelEditor::rules()` zná jen tyto klíče, a co validace nezná, zahodí. Obsahová session
poslala `params.frame = true` (tvar generátorů, `/api/tools/param`), vlajka tedy propadla a vznikly jen `piece_1…12`.
Skript sám tácek umí (`edit_tool.py puzzle`, díl `frame`, výška min(H, 10) + dno, lem podle `notes.frame`). Oprava:
koncový bod `edit` i `edit/analysis` teď nastavení zabalená v `params` před validací vyzvedne (`EditApiController::unwrapped`,
nejvyšší úroveň má přednost), takže obě podoby fungují; test v `ModelEditTest`.

### 17.2 Kalkulace souboru s díly většího než podložka (posudek, nic neprovedeno)

Případ: vrstvený obraz z filamentu 180 mm z 5 desek s rámem = jeden soubor 232 × 551 × 25 mm (desky vedle sebe,
`tool_params.parts`), `POST /api/calculations` ho řeže jako jednu podložku, Orca ho odmítne („Slice failed (no gcode)“,
`/c/9rs4xrseapqu`), zůstane hrubý odhad a chybová hláška. Objednávka přitom umí tisk po dílech (`FarmOrder::isByParts`,
`partPlates`, AL.md §10 a §11), veřejná kalkulace ne.

Co se dá udělat (Roman vybere):
- **(a) Řezat po dílech i v kalkulaci**: když má soubor `parts` a celek přesahuje `pricing.bed_mm`, `SliceCalculation` rozřeže
  každý díl zvlášť (díly leží jako samostatné STL u designu, `api/tools/edit` je tak ukládá; `EditApiController::part`) a sečte
  minuty a gramy, výsledek nese počet podložek; cena ze součtu, stejná čísla jako pak u objednávky. Práce ~1 den: cesta
  v `SliceCalculation` (N řezů po sobě = minuty čekání → jít přes `rough_only` jako velký model a dořezat na pozadí),
  `applySlice` se součtem, `calc.fit.plates` v textu, test s falešným slicerem; na produkci 5 × 20–90 s na kalkulaci.
- **(b) Nehlásit chybu, říct pravdu**: v `CalculationService::create` poznat „díly + přesah podložky“ (bbox souboru proti
  `pricing.bed_mm`, `Dimensions::fits`) a jít cestou velkého modelu: zůstat u odhadu (`sliceNow` false), v odpovědi důvod
  (`rough_reason = by_parts`), stránka řekne „Větší než podložka: vytiskneme po dílech, přesnou cenu spočítáme při objednávce“
  (nový klíč vedle `calc.status.big_only`) a nepollí. Práce 2 až 3 h, žádný řez navíc. Odhad ceny přitom je hrubý (bez
  podpěr a rámu), ale pravdivě označený; objednávka ho dořeže po dílech jako dnes.
- **(c) Nic**: nechat chybu. Nedoporučuji, hláška „Slice failed“ u hotového designu vypadá jako rozbitý nástroj.

Doporučení: (b) hned (malé, odstraní chybu a lže se méně), (a) až bude obraz z filamentu objednávaný častěji; čísla z (a)
beztak zákazník dostane na stránce objednávky.

**Provedeno (b), 28. 10. večer, větev `feature/calc-by-parts`:** `CalculationService::printedByParts()` (soubor má aspoň dva
díly, `ModelFile::parts()`, a jeho obálka × měřítko se nevejde na `pricing.bed_mm` ani otočená, `Dimensions::fits`) →
`create()` nechá kalkulaci u odhadu s `rough.by_parts = true` a nic nezařadí do fronty (i když stránka řez chtěla);
odpověď API to nese v `rough`. `calculator.ts` (`setBigOnly`) ukáže „Větší než podložka: vytiskneme po dílech, přesnou cenu
spočítáme při objednávce.“ (`calc.status.by_parts`, cs/en/es, v `CalculatorTexts`) a nepollí; odhad zůstává. Test
`ByPartsCalculationTest`. **Další krok (a)**, až se obraz z filamentu bude objednávat: řezat díly zvlášť a sečíst
(viz výše), čísla pak budou stejná jako na stránce objednávky.

## 18. Rozložení bez skoků: stránka nástroje a kalkulace (větev `feature/layout-sticky-tools-calc`, 28. 10. 2026)

Zadání `docs/prompts/rozlozeni-bez-skoku.md` (Roman, vzor paramet.io). Session G dělá stránku zakázky (AO.md §5) a převzala
společné kusy níže (commit 19dfe46c a navazující). Žádná změna výpočtů ani textů kroků.

### 18.1 Společné kusy (app.css, `resources/js/site/sticky.ts`, `components/action-bar.blade.php`)

- Třídy: `.stick-stage` (sloupec, co zůstává od lg: sticky, výška okna bez okrajů a bez spodních lišt, flex sloupec, náhled
  `min-h-0 flex-1` ustoupí, tlačítka ne), `.stick-top` (karta přilepená nahoře sloupce), `.stick-col` (sloupec voleb
  s vlastním posuvníkem, výška okna bez lišt, scroll-padding nahoře 3,5 rem a dole 5,5 rem), `.stick-scroll` (totéž, výšku
  dá řádek mřížky), `.stick-fit` (mřížka vyplní okno pod tím, co je nad ní: `--fit-top` změří `fitWindow()`),
  `.stick-actions` (řádek akcí přilepený ke spodku sloupce), `.action-bar` + `.action-bar-inner/-price/-sub` (lišta
  přilepená ke spodku okna nad cookie lištou: `bottom: var(--consent-h)`, `padding-bottom` se safe-area), `.flash-change`
  (prstenec 1,2 s). Výšky `.stick-stage`, `.stick-col` a `.stick-fit` odečítají `--consent-h` i `--action-bar-h`
  (změřeno session G: bez toho bylo při 1366×768 215 px karty pod lištami). `body.has-action-bar` a `html
  { scroll-padding-bottom }` dávají stránce místo pod lištou. `.btn-primary` má `aria-disabled:opacity-60`.
  `.stick-scroll`/`.stick-col` mají `overflow-anchor: none`, aby okno nesledovalo, co se hýbe uvnitř.
- `sticky.ts`: `isInView(el)` (v okně nad spodními lištami i uvnitř každého rolujícího předka), `revealIfHidden(el, block)`
  (roluje jen když cíl není vidět: nejbližší rolující box o nejmenší kus, `start` pod jeho scroll-padding; okno jen když
  je sám box mimo obraz; bez boxu okno `scrollIntoView nearest`), `focusCalm(el)` (`focus({preventScroll})` +
  reveal), `flash(el)`, `fitWindow(el)`, `mountActionBars()` (každý `[data-action-bar]`: zrcadlí tlačítko stránky –
  text nebo jeho `data-bar-label`, `disabled` jen z `disabled`, `aria-disabled` zvlášť a klik se dál předá, `hidden` –
  a prvek s cenou včetně `suffix` a druhého řádku; při změně čísla lišta blikne; `--action-bar-h` a `body.has-action-bar`
  přes ResizeObserver a MutationObserver na `hidden`).
- `<x-action-bar for= price= suffix= sub= label= class= hidden>`: lišta je zrcadlo, skripty stránek pro ni nic nemění.
  Volá se z `site/boot.ts`.

### 18.2 Stránka nástroje (`tools/page.blade.php`, `tool_page.ts`)

- Od lg mřížka `#tool-page.stick-fit` vyplní okno pod hlavičkou (řádky `auto` + `minmax(0,1fr)`), panel `#tool-panel.stick-scroll`
  roluje uvnitř svého řádku s lištou kroků `sticky top-0` nahoře, `#tool-stage` vyplní oba řádky (`lg:h-full`, už ne
  sticky: nemá kam utíkat) – náhled `min-h-0 flex-1`, karta tlačítek pod ním vždy vidět; při 1366×768 se vejde obojí
  (snímky `docs/img/audit/{qr,box,figure}-{390,768,1280,1366}-cs-st.webp`: h1, náhled i hlavní akce na prvním
  obrazu ve všech šířkách, bez vodorovného posuvu). Text o nástroji následuje pod mřížkou jako dřív.
- Telefon: `<x-action-bar for="tool-go" sub="tool-status" class="lg:hidden">` – „Spočítat cenu“ (zrcadlo `#tool-go`,
  který je na telefonu skrytý, `max-lg:hidden lg:contents`, aby oranžové tlačítko bylo jedno) a pod ním stav modelu
  (`#tool-status`: „vytvářím…“, hotovo s rozměry). Po dokončení modelu se stránka nehýbe (`revealStage()` jen blikne
  kartou náhledu); dřív skočila nahoru k vieweru.
- Kroky v liště: klik přepíná sekci uvnitř panelu (`revealIfHidden(sec, 'start')` pod sticky lištu), bez `#hash`
  a bez pohybu stránky; `reveal()` modulů (chyby, výsledky, barvy) roluje panel jen když cíl není vidět, prstenec zůstává.
  Na telefonu (panel není rolující) se stránka posune k sekci, jen když není vidět.
- Měření (headless Chrome, `scripts/review_round2.mjs --flows=jumps-tool`, čtení polohy lišty kroků a náhledu na obrazu
  před a po kliku): 1366×768 – klik na poslední krok: okno 0 → 0, panel 0 → 685, náhled 65 → 65; klik na první krok:
  okno 0 → 0, panel 685 → 36. 390: stránka roluje k sekci (900 → 0 lišta kroků), jak má. Harness dřív každý klikaný prvek
  centroval (`scrollIntoView center`) a sám tak hýbal oknem o 132 px: teď roluje jen k prvku mimo obraz.

### 18.3 Kalkulace (`calculator/index.blade.php`, `calculator.ts`)

- Od lg: viewer `.stick-top` (zůstává), karta ceny `#price-card.stick-top` nahoře pravého sloupce **s „Vytisknout u nás“**
  (`farm.cta` přesunuta do karty ceny, dřív až pod volbami); pod ní rolují se stránkou velikost a množství, materiály,
  kvalita, podpěry, Přepočítat, stažení, příklady a důvěra. Telefon: karta ceny první, viewer, pak volby (jako dřív);
  `<x-action-bar id="calc-bar" for="cta-farm" price=… sub=… class="lg:hidden" hidden>` se ukáže s výsledkem
  (`showResult`), schová s návratem na úvod; cena z `#price-orient-amount` (nový span s částkou v řádku farmy; s tržištěm
  `#price-main` + měna), druhý řádek doba tisku.
- Skoky: `showResult()` už neroluje na vrch stránky (jen když výsledek není vidět), Přepočítat neroluje k ceně – karta
  ceny blikne, až čísla dorazí (`flashNext` → `flash(#price-card)`), lišta telefonu blikne sama při změně čísla;
  `download.ts`, `inquiry.ts`, `search.ts` přes `revealIfHidden`, souhlas u busty přes `focusCalm`.
- Měření (`--flows=jumps-calc`, tlačítko Přepočítat na obraz jako u člověka, pak klik): 390 – tlačítko 398 → 398,
  viewer −1200 → −1200; 1366 – tlačítko 360 → 360, karta ceny 12 → 12 (sticky), viewer −934 → −934. Nic se nehnulo.
  Snímky `flow-file-price-{390,768,1280,1366}-cs-*.webp`.

### 18.4 Testy a co zůstává

- `StickyLayoutTest` (třídy a lišty na obou stránkách, jen oranžové tlačítko navíc v liště), `ToolPageTest` (počet
  oranžových tlačítek bez lišty), `ToolsFlowTest`, `CalculationFlowTest`, `AuditRestTest`, `FarmGuestCheckoutTest`,
  `FarmSalesPathTest`, `FarmPagesTest` zelené; pint, tsc, build.
- Klávesnice na Androidu: lišta je `fixed`, Chrome ji s vizuálním viewportem posune; `scroll-padding-bottom` drží pole nad
  ní. Ověřeno jen v emulaci (rozměr okna), ne s otevřenou klávesnicí.
- Stránky `check`, `mold`, `repair` mají vlastní layout mimo `tools/page` – beze změny; kdyby chtěly lištu, stačí
  `<x-action-bar>`.

## 19. Namalované barvy v 3MF: náhled, řádek barev a dvě ceny v kalkulaci (větev `feature/calc-3mf-colors`, 28. 10. 2026)

Zadání `docs/prompts/import-3mf-barvy.md` (Roman, TODO #50, želva z Anycubic Slicer Next). Rozbor při nahrání, data na
souboru, cena, zakázka a farma dělá session 2 (`feature/import-3mf-colors`, AL.md §14); tvar dat domluven zprávami:
- `file.groups: [{hex, from, to}]` (v `GET /api/files/{uuid}` i `calculation.file`): trojúhelníky `model.stl` seřazené po
  barvách, `to` výlučně, souvisle od 0 do počtu; sloučená drobná barva je ve skupině barvy svého tělesa (náhled = tisk).
- `file.colors: [{hex, share, merged, into?}]` podle podílu, práh sloučení 1 % (session 2, kvůli želvě), `file.colors_info:
  {count, merged, too_many, status}`; jedna barva = `colors` null.
- `calculation.colors: {one, multi, count}` (done i rough, měna jako `prices[0].total`).
- `POST farm.orders.store` s `colors: 'painted' | 'one'`; farm.ts čte `data-colors` tlačítka `#cta-farm`.

Moje část:
- **Náhled** (`resources/js/calc/painted.ts`): `piecesFromGroups()` ověří, že běhy pokryjí model přesně jednou a v pořadí
  (jinak náhled zůstane jednobarevný jako dnes), `paintGroups(viewer, groups, triangles)` = `setPieces` + `setPieceColor`
  jako u dílů nástroje, `colorDots()` tečky. Kalkulace (`showPainted`): jakmile přijde soubor se `groups`, načte jednou
  serverové seřazené STL místo prohlížečovy kopie a obarví ho; kamera stojí (`keepView` jen na tu výměnu). Zakázka je
  session 2 (farm.ts): u by_parts už barví `loadParts`, jinak stačí `paintGroups` po `setGeometry`.
- **Řádek barev v kartě ceny** (`#calc-colors`, `renderColors`): tečky hlavních barev a „Soubor má 2 namalované barvy
  (a 2 drobné, sloučené)“ (tvary pro `plural()`, cs/en/es), pod tím „Barevně od X · Jednobarevně Y“ z `calculation.colors`.
  Tlačítko farmy se u namalovaného souboru (≥ 2 hlavní barvy) jmenuje „Vytisknout barevně“ (`data-colors="painted"`,
  i lišta telefonu přes `data-bar-label`), pod ním „Vytisknout jednobarevně“ (`#cta-farm-one`): nastaví `data-colors="one"`
  a klikne na tlačítko farmy, pak vrátí. Bez barev je vše skryté a kalkulace je jako dnes. Karta roste dolů, nic nad ní
  se nehýbe (§18).
- **`/tools/colors`**: nad nahráním odstavec `edit.colors.painted` (Bambu Studio, OrcaSlicer, Anycubic Slicer Next,
  PrusaSlicer). Pojem „namalované barvy“ v slovníku AH.md §9.4.
- Test `PaintedColorsPageTest` (místa v kalkulaci, texty ve 3 jazycích a v JSON kalkulace, odstavec nástroje).

**Přejímka na želvě (28. 10. večer)**: místní větev s API session 2 (`539547ff`) a touto větví, místní databáze s migrací,
nahrání `turtlescutieone2.3mf` v headless Chrome (1366 i 390): náhled zeleno žlutý (`docs/img/painted/turtle-calc-*.webp`),
tečky `#80FF80` a `#FFFF00`, „Soubor má 2 namalované barvy (a 2 drobné, sloučené)“, „Barevně od 190 Kč · Jednobarevně
120 Kč“ (falešný slicer; session 2 hlásí s Orcou 79/210), „Vytisknout barevně“ v kartě i v liště telefonu, „Vytisknout
jednobarevně“ pod ním. Nález a oprava (b6a72a91): kalkulace vrácená hned hotová (stejný soubor a nastavení) přeskočila
dvě ceny; `renderColors` je teď v `renderPrecise` a v `setBigOnly`, takže je dostane každá cesta.

### 19.1 Modrý náhled po druhém nahrání a jedna cena, když jsou obě stejné (větev `fix/painted-preview-repeat`, 29. 10. 2026)

**Nález řídící na produkci (28. 10.)**: želva nahraná podruhé měla po přesném výpočtu náhled celý modrý, řádek barev
a dvě ceny přitom byly. **Příčina**: `showPainted` si soubor označil jako obarvený (`paintedFor`) ještě před načtením
seřazeného STL. Když to jediné načtení selhalo (síť, zaneprázdněný server), náhled zůstal z prohlížečovy kopie
(modrý) a žádná další odpověď už načtení nezopakovala; po přesném výpočtu se kalkulace přestane ptát. Druhá díra
stejného druhu: kalkulace vrácená hned hotová (`requestPrecise`, `requestRough`) soubor vůbec nepřevzala, takže barvy
z ní dostal jen řádek s cenami (b6a72a91), náhled ne.

**Oprava** (`resources/js/calc/calculator.ts`):
- `takeFile(c.file)` převezme soubor z každé odpovědi kalkulace (poll, vytvořená hotová, odhad) a zavolá `showPainted`.
- `showPainted` označí soubor až po načtení; nepovedené načtení zkusí znovu samo (4 pokusy, 1,5 / 3 / 4,5 s), dokud je
  soubor zobrazený. Souběžné odpovědi ho nenačtou dvakrát (`paintingFor`).
- Plátno náhledu nese `data-painted`: `loading`, `colors`, `mismatch` (běhy nesedí na STL), `failed` (ani 4. pokus).
  Kontrola zvenku (headless Chrome, konzole) tak nemusí soudit barvu z obrázku.

**Ověření** (místně, fronta přes databázi a worker jako na produkci, falešný slicer, `scripts/review_round2.mjs
--flows=painted-twice,painted-503,painted-same --file=<želva>`):

| průchod | starý kód | oprava |
|---|---|---|
| želva dvakrát za sebou | barevně, barevně | barevně, barevně (`painted=colors`) |
| první `model.stl` odpoví 503 | **modrá**, řádek barev a ceny ano (`turtle-stl503-before-1366.webp`) | barevně (`turtle-stl503-after-1366.webp`) |

Místně se modrý náhled bez simulované chyby neukázal: zpracování želvy tu trvá 15 s, takže kalkulace nikdy nepřijde
hotová hned. Cestu „vytvořená hotová“ pokrývá `takeFile` podle kódu, ne pokusem.

**Jedna cena** (zpráva řídící 29. 10.): když `calculation.colors.one` a `multi` vyjdou po zaokrouhlení na stejný text
(želva: obě na minimu ceníku), místo „Barevně od X · Jednobarevně X“ je jeden řádek `calc.colors.price_same`
„Barevně i jednobarevně stejně: 120 Kč“ (cs/en/es; `turtle-same-price-390.webp`, ověřeno přepsáním odpovědi v toku
`painted-same`). Zaokrouhlení samo je session 2 (`feature/painted-price`).

## 20. Kalkulace drží barvy z nástroje a pravý sloupec roluje v okně s rámečkem (větev `feature/calc-colors-panel`, 29. 10. 2026)

Zadání `docs/prompts/kalkulace-barvy-panel.md` (Roman u krabičky, TODO #54). Stránku zakázky jsem neměnil.

### 20.1 Barvy z předchozí stránky

Příčina: kalkulace barvila díly nástroje jen ve dvou případech. Buď tělesa podle `parts_bbox` (`partsByColour`), a to
jen při dvou různých barvách, nebo deska s textem po výšce (`twoColorRegions`). Krabička s víčkem v jedné mědi tak zůstala
ve výchozí modré. Medaile a podtácek jsou ploché, proto je viewer stínoval po výšce (tyrkysová).

Teď v `showServerStl` (každé načtení serverového STL), když nezabral ani jeden z obou dřívějších případů:
- **Jedna barva** (všechny `tool_params.part_colors` stejné): celý model v ní (`painted.ts toolPaint` → `paintPieces`
  s jedním kusem). Funguje i pro soubory vytvořené dřív, třeba Romanovu krabičku.
- **Uložené kusy** (`tool_params.pieces` s rozsahy trojúhelníků přes tento soubor: obraz z filamentu, upravený model):
  každý kus v barvě svého dílu, když rozsahy pokryjí soubor přesně.
- **Více barev v jednom tělese** (medaile s motivem zapuštěným v ploše, podtácek s lemem). Uložené STL je jedno sjednocené
  těleso a jeho díly v něm nejdou rozlišit; sestavení s kusy má jiný počet trojúhelníků (medaile 229 068 proti 226 616).
  Proto nový `GET /api/tools/param/{file}/pieces` (`ToolsApiController::paramPieces`, middleware `file`, throttle 30/min)
  vrátí stejný tvar sestavený jako na stránce nástroje (`build(..., pieces: true)`) a hlavičku
  `X-Pieces: [{name, tris}]`. Uloží se jednou vedle souboru (`pieces.stl`, `pieces.json`), další dotaz jde z disku.
  `file.tool.pieces_url` je jen u parametrických návrhů s ≥ 2 různými barvami dílů a bez uložených kusů. Kalkulace mezitím
  ukáže celý model v barvě těla, potom načte kusy a vymění model se stejnou kamerou (`keepView`). Nekryjí-li rozsahy STL,
  zůstane tělo (`data-painted="mismatch"`). Načtení zkusí 3× s odstupem. Čísla (velikost, cena) zůstávají z uloženého modelu.
- `#viewer[data-painted]` platí i tady: `colors` (obarveno), `loading`, `mismatch`, `failed`. STL bez nástroje marker nemá.
- `viewer.ts afterGeometry` maže barvy kusů předchozího modelu (`pieceColors` zůstávaly a mohly obarvit další soubor).

Ověřeno (headless Chrome, `scripts/review_round2.mjs --flows=colors-box,colors-medallion,colors-coaster,colors-stl`, celá
cesta stránka nástroje → „Spočítat cenu“ → `/c/<kód>`, 1366, 1280 a 390 px; místní farma bez měděné cívky, proto řádek
„U nás vytiskneme“ ukazuje bílou):

| model | `data-painted` | náhled |
|---|---|---|
| krabička s víčkem (předvolba lidded, měď 2×) | colors | obě části měděné (`box-calc-1280/390.webp`) |
| medaile 1. místo (zlatá + červená, zapuštěná) | colors | zlatá, zespodu červená „1“ a lem (`medal-calc-below-1280.webp`, nový krok harnessu `drag`) |
| podtácek golf (béžová, černý lem a golfista) | colors | béžový kotouč, černý lem i golfista (`coaster-calc-1280/390.webp`) |
| STL z disku (kostka) | – | výchozí modrá jako dřív (`stl-calc-1280.webp`) |

Snímky jsou v `docs/img/tool-colors/`.

### 20.2 Pravý sloupec jako okno s rámečkem

- Od lg: mřížka `#calc-grid.stick-fit` vyplní okno pod hlavičkou (`--fit-top` měří `fitWindow` při prvním `showResult`).
  Viewer `#calc-stage` vyplní levou stranu (`lg:h-full`, plátno `lg:h-full` místo `70vh`). Pravý sloupec `#calc-panel` je
  `stick-scroll` s rámečkem `lg:rounded-2xl lg:border lg:border-slate-200`. Karta ceny s „Objednat tisk“ drží nahoře okna
  (`lg:sticky lg:top-0`), volby rolují pod ní. Telefon beze změny (`max-lg:contents`, lišta `action-bar`).
- Panel stránky nástroje `#tool-panel` má stejný rámeček (`box-tool-1280.webp`).
- Skok po „Přepočítat“: čísla, která přijdou nad tlačítko (karta ceny −16 px, seznam profilů +48 px), posunula tlačítko
  v panelu o 32 px. Chromovo vlastní ukotvení (`overflow-anchor: auto` na panelu) si vzalo za kotvu sticky kartu
  ceny a posun zhoršilo na 48 px, proto zůstává vypnuté. Místo něj `sticky.ts holdOnResize(box, skip)`: zapamatuje si
  ovládací prvek, na který se v panelu naposledy klepnulo nebo na který přešel fokus (mimo kartu ceny), a když obsah nad
  ním změní výšku (ResizeObserver na dětech panelu), posune panel o tolik, aby prvek zůstal na místě.
- Měření (`--flows=jumps-panel`, nový krok `press` je skutečný klik myši přes CDP; `click` volá jen `el.click()` bez
  pointer událostí; počty kusů se píšou, když je pole vidět):

| šířka | okno | viewer | karta ceny | Přepočítat (před → po výpočtu) |
|---|---|---|---|---|
| 1366 × 768 | 0 → 0 | 66 → 66 | 66 → 66 | 691 → 691 |
| 1280 × 800 | 0 → 0 | 66 → 66 | 66 → 66 | 723 → 723 |
| 390 (`jumps-calc`) | stránka | – | – | 366 → 366 |

  Stránka nástroje (`jumps-tool`, 1366): okno 0 → 0, náhled 65 → 65 při přepínání kroků, jako v §18.2.
- Snímky `panel-scrolled-1280.webp` (panel dole, karta ceny a viewer stojí) a `box-calc-390.webp`.

### 20.3 Testy

`CalcToolColorsTest` (nový: medaile dá kusy jednou a pak je bere z disku, rozsahy pokryjí vrácené STL; měděná krabička
`pieces_url` nemá, dvoubarevná ano; nahraný soubor 404), `StickyLayoutTest` (mřížka, panel s rámečkem, karta ceny první
v panelu, viewer na výšku mřížky, rámeček panelu nástroje), `PaintedColorsPageTest`, `CalculationFlowTest`, `ToolPageTest`
a celá sada; pint, tsc, build.

## 21. Nástroj na hlavní straně z adminu, ikony pohledů, jedna položka projektu (TODO #56)

Zadání `docs/prompts/admin-domu-ikony-pohledu.md`, větev `feature/admin-home-view-icons`, 31. 10. 2026.

### 21.1 A: přepínač „Na hlavní straně“

- Migrace `tool_flags.home` (boolean, nullable), popis pravidel v `docs/TOOLS-ADMIN.md`. `ToolVisibility::onHome()` a
  `wantsHome()`, hlavní strana čte `onHome()`, `config/home.php` je jen výchozí seznam a strop.
- Klíč cache je `tool_flags_v2`: tvar hodnoty se změnil a staré položce pod starým klíčem nic nehrozí. Dotaz je
  `get()`, ne výběr sloupců: kód nasazený před migrací dál čte přepínač „veřejný“, skrytý nástroj se neodkryje.
- Řádek posílá políčko při každém uložení. Hodnota shodná s tím, co platí teď, nic nezapíše, takže uložení poznámky
  nezamrazí `null` na `true`.
- Se 13 nástroji v configu a stropem 8 byl nově zapnutý nástroj čtrnáctý a na stránce se neukázal. Vyřešeno v části D
  (§21.4): ručně zapnuté jdou první.

### 21.2 B: ikony pohledů

- Vlastní symboly `i-view-iso|top|front|side|bottom` v `partials/icons.blade.php`: krychle s vyplněnou stěnou. Strany
  odpovídají kameře: v `iso` je `front` (+z) vlevo a `side` (+x) vpravo; `bottom` je krychle viděná zespodu.
- Tlačítko má `title` a `aria-label` (`toolpage.view.*`), 44 × 44 px na všech šířkách (`.tool-view-btn`), aktivní je tmavé.
  Tlačítko „Pohled“ na telefonu ukazuje krychli zvoleného pohledu (`data-view-now`), „Přizpůsobit“ ji vrací na `iso`.
- Figurka (`data-view` jsou tam pole pro fotky) a kalkulace lištu pohledů nemají, nebylo co sjednocovat.
- Ověřeno `--flows=views-box` na 1280 a 390 px: pět tlačítek 44 × 44, stisk přepne `aria-pressed` i ikonu tlačítka, bez
  vodorovného posuvu. Snímky `docs/img/views-icons-1280.png`, `views-icons-390.png`.

### 21.3 C: stahování

- Nabídka má jednu položku `toolpage.download.project` s dovětkem o další stránce (`tool_page.ts`, `edit.ts`, `art.ts`,
  `param.ts`); `slicer=` zmizel z odkazů i z `download.ts`, výrobce předřazuje zapamatovaná tiskárna.
- `resources/js/calc/parts.ts` `partLabel(part, kind, family, i18n)` používá stránka nástroje i panel kalkulace.
  `file.tool.family` říká `shape`; rodinu `edit` pozná panel podle seznamu, který už měl. `CalculatorTexts::partNames()`
  předává všechny názvy dílů z lang souborů, ne ručním seznamem.
- Nad rámec zadání: panel teď pojmenuje i díly upravovacích nástrojů (`piece_1` → „Díl 1“) a vrstvy skladby („Vrstva 1“).
- `param.part.body` = „Jen tělo“; krabička dál „Jen krabička“ (`param.part.body.box`, nově i v seznamu `param.blade.php`).
  `FarmPagesTest` porovnával těla s obecným klíčem, a protože lightbox má vlastní „Jen tělo“, srovnává teď s klíčem krabičky.
- `download.stl` = „Celý model (STL)“, stejně jako na stránce nástroje.
- Texty: `lang/src/part_labels_2.json`. `lang_add.py` na celý `param.json` by přepsal 8 novějších překladů, proto jen
  změněné klíče.
- Ověřeno v Chrome (`--flows=menu-*`, 1280 a 390 px):

| nástroj | nabídka: díly | kalkulace: díly |
|---|---|---|
| krabička | Jen krabička, Jen víčko | Jen krabička · Jen víčko |
| přívěsek | | Podklad · Barva 1 · Lem |
| podtácek | Podklad, Barva 1, Lem | Podklad · Barva 1 · Lem |
| medaile | Medaile a řetěz, Barva 1 | Medaile a řetěz · Barva 1 |

  Přívěsek jsem vyrobil přes API s obrázkem o jedné barvě, takže „Barva 2“ a „Barva 3“ ze snímku Romana se v běhu
  neobjevily; `partLabel` je sestavuje stejně a `OrderService` je ověřený testem.
- **Nesoulad se stránkou objednávky (vyřešeno v §21.4):** medaile tam říká „Placka a řetěz“ a „Číslo a lem“ (`param.part.*.medallion`, test
  `FarmPagesTest`, objednávka 194), nástroj a kalkulace „Medaile a řetěz“ a „Barva 1“. Stránka objednávky patří session 2,
  neměnil jsem ji. Srovnání `partLabel` s `OrderService::partLabel` přes všechny díly všech generátorů a upravovacích nástrojů (cs): 130 ze 133 stejně; rozdíl je medaile (2) a dílek puzzle, který objednávka ukazuje surově `piece_2` (panel „Díl 2“).
- Snímky `docs/img/download-one-project-nastroj.png`, `download-one-project-kalkulace.png`.

### 21.4 D: ručně zapnuté první, jeden název dílu (dodatek zadání, 1. 11. 2026)

Větev `feature/home-first-part-names`.

- **Pořadí:** `ToolVisibility::onHome()` dá dopředu nástroje s `home = true` (v pořadí configu, pak katalogu), za ně zbytek
  `config('home.tools')` bez vypnutých; strop `tools_shown` a štítek `popular` beze změny. Zapnutý nástroj se tak ukáže i při
  plném configu, dokud zapnutých není víc než strop.
- **Admin:** řádek ručně zapnutého nástroje říká „Zapnutý ručně: na hlavní straně jde první.“, poznámka nad tabulkou to říká
  taky. JSON vrací `home_hints` pro všechny řádky místo `home_hint`, protože zapnutí jednoho posune jiný přes strop.
- **Vedlejší účinek:** nástroj z configu, který správce vypnul a znovu zapnul, má `home = true` a jde taky dopředu. Odpovídá to
  zadání a řádek to říká, zpět na místo v configu ho z adminu vrátit nejde.
- **Jeden název:** `parts.ts` i `OrderService::partLabel` berou první `param.part.<díl>.<druh>`. Medaile všude „Medaile a řetěz“
  a „Číslo a lem“ (en „Medal and chain“, „Number and rim“; es „Medalla y cadena“, „Número y borde“). Klíč
  `shape.part.body.medallion` zrušen, ať druhé slovo nemůže utéct. Barevný řádek medaile na stránce nástroje teď taky říká
  „Číslo a lem“, ne „Barva 1“.
- **Puzzle:** nový `param.part.piece` („Díl :n“), objednávka u upravovacích nástrojů končí na `param.part.<díl>`, `parts.ts` taky.
- **Stránka nástroje:** `CalculatorTexts::partNamesOf($kind)` předá `param.part.*.<druh>` bez ručního seznamu v `param.blade.php`.
  Nově tak dostane svůj název i vykrajovátko („Jen vykrajovátko“), které v seznamu chybělo.
- **Změněno v souborech objednávky (session 2):** `OrderService::partLabel` (poslední položka řetězu u upravovacích nástrojů),
  `lang/src/order_part_labels.json` (text medaile, `param.part.piece`), `FarmPagesTest` (očekávaná „Medaile a řetěz“).
- **Ověřeno:** porovnání `parts.ts` a `OrderService::partLabel` přes všechny díly generátorů a úprav v cs: 133 ze 133 stejně
  (předtím 130). Chrome lokálně: `--flows=admin-home` (cs, 1280 px), krabička první na hlavní straně, řádek osmého nástroje
  po uložení „v pořadí je 9.“; `--flows=menu-medallion` (cs, en, 1280 px) a panel kalkulace (cs): „Medaile a řetěz · Číslo a lem“.
  Stránka objednávky jen testem (`OrderService::partLabel`), v prohlížeči ne; es jen testem.
- Snímky `docs/img/admin-tools-home-first-1280.png`, `part-names-medal-nastroj.png`, `part-names-medal-kalkulace.png`.

## 22. Rozcestník na hlavní straně: fotka, text nebo soubor (TODO #78)

Zadání `docs/prompts/rozcestnik-hlavni-strana.md` (Roman 8. 11. 2026, schválený klikací návrh v scratchpadu řídící
session), větev `feature/home-router` z `origin/main` 868b6050, worktree `C:/matplace-edit-wt`, 8.–9. 11. 2026.
Nic nenasazeno, nic nemergováno. V tomto worktree není `ANTHROPIC_API_KEY`: vývoj a testy běžely s `FakeAssistant`
a s klíčovou náhradou (§22.3), skutečný model tu nikdo nevolal.

### 22.1 A: stránka

- `resources/views/calculator/home.blade.php` je přepsaná: nahoře rozcestník (vlevo eyebrow, H1 „Z fotky vyrobíme
  dárek. Z nápadu věc.“, podnadpis, řádek důvěry; vpravo karta `#intake-form`: plocha pro fotku nebo 3D soubor
  s náhledem a „odebrat“, textové pole `#intake-text` s popiskem a příklady v placeholderu, čtyři štítky
  `[data-fill]`, tlačítko `#intake-submit` „Navrhnout“ 60 px vysoké a na telefonu přes celou šířku, věta „Zdarma
  a bez registrace…“, věta o smazání fotky po 30 dnech, odpověď asistenta `#intake-assist`). Pod tím „Po odeslání se
  stane jedna ze tří věcí“ (`#router-paths`), „Dárky, které umíme z vaší fotky“ (`#router-gifts`, 8 karet
  `data-gift`, obrázky `tools.picture`, tj. `img/tools/<klíč>-800.jpg`), pás „Do Vánoc stihneme…“ (`[data-deadline]`),
  „Takhle to vypadá, když to přijde“ (`#router-prints`, jen když farma má vyfocené výtisky těchto nástrojů,
  `ToolPhotos`; lokálně a zatím i na produkci prázdné, sekce se neukáže), „Máte vlastní model? Cena ze souboru během
  minuty“ (`#router-file`: dovnitř se přesunul původní `calculator.inputs` s `#dropzone`, `#file-input`,
  `#search-form` a řádkem výhod, tlačítka „Vyfotit“ a „Popsat slovy“ zůstaly pro skripty, ale skryté:
  `inputs.blade.php` s `fileOnly`). Zbytek stránky beze změny: pět cest, nástroje, příklady, tři kroky, SEO část
  `tools._content` pro `calc`.
- Texty: `lang/src/home_router.json` (92 klíčů `router.*`, cs/en/es), `farm.admin.mail.intake*` přímo ve třech
  `farm.php`. Datum „do Vánoc“ a lhůta odpovědi jsou nastavení farmy: `config/farm.php settings.christmas_deadline`
  (`2026-12-15`, prázdné = stránka o Vánocích mlčí, prošlé datum taky) a `intake_reply_hours` (24); obojí jde
  změnit v `/admin/farm/settings` („Ostatní“). Datum se píše podle jazyka (cs „15. 12.“, en „15 December“, es
  „15 de diciembre“); en/es tedy vánoční větu mají, dokud je datum nastavené.
- Cena „od“ u karet: `IntakePage::fromPrice()` bere nejlevnější skutečnou kalkulaci nástroje z `config/site.php
  example_items` (jen v korunách). Dnes ji má jen busta (99 Kč); ostatní karty cenu neukazují, žádné číslo není
  vymyšlené. Karty respektují `ToolVisibility::canOpen` a u busty a mazlíčka generátor.
- Vstup pro fotku je `<input accept="image/*,.heic,.heif">` **bez** `capture="environment"`: s ním Android Chrome
  i iOS Safari otevřou rovnou fotoaparát a galerii nenabídnou, tlačítko ale slibuje „Vyfotit **nebo vybrat** fotku“;
  bez atributu nabídne telefon obojí. Odchylka od návrhu, viz zpráva řídící.
- Kontrola: `scripts/review_round2.mjs --pages=home --widths=390,1280 --langs=cs,en`: H1, podnadpis i hlavní akce
  na první obrazovce, `hScroll: false`, žádný cíl pod 44 px (štítky `min-h-11`). Snímky v scratchpadu session 3
  (`router-shots/home-{390,1280}-{cs,en}-a.webp`, `-full.webp`, `-answer.webp` s odpovědí asistenta).

### 22.2 B: příjem

- `POST /intake` (`IntakeController@store`, `IntakeService::create`), v obou jazykových dvojčatech, CSRF jako jinde
  (skript posílá `X-CSRF-TOKEN`), `throttle:intake` = 5 za hodinu na anonymní relaci **a** 5 na IP (admin bez
  limitu). Vstup `photo` (jpg, png, webp, heic, heif, do 15 MB), nebo `model` (formáty kalkulačky, do
  `uploads.max_mb`), `text` do 2000 znaků; bez fotky, souboru i textu 422 s `router.need_input`; 429 při limitu.
  Odpověď 201 JSON: `token, route, tool_key, tool, summary, params, questions, wants_shape, has_photo, hours, file,
  actions {tool, calc, request}`.
- Fotka: originál `storage/app/private/intake/<token>/original.<ext>` (disk `local`), vedle `model.jpg` z
  `photo_tool.py normalize … 2000` (EXIF otočení, delší strana 2000 px, HEIC přes `pillow_heif`; stejný skript jako
  foto-box). Když Python neodpoví, JPEG/PNG/WebP jde modelu i nástrojům tak, jak přišel; HEIC se odmítne
  `router.photo_unreadable` a řádek se smaže. Prohlížeč posílá fotku už zmenšenou (`calc/photo.ts shrinkPhoto`,
  HEIC ve Windows Chrome projde beze změny a převede ho server).
- Tabulky `intakes` a `intake_requests` (migrace `2026_11_09_100000_create_intakes.php`, **nespuštěná** ve
  worktree; PHPUnit běží v paměti). `intakes`: token 24 znaků, relace / účet, druh, cesty k fotce, `model_file_id`,
  text, jazyk, `route`, `tool_key`, `params`, `confidence`, `summary`, `answer` (celá odpověď modelu včetně vět ve
  třech jazycích a otázek), `wants_shape`, `model`, tokeny, `cost_czk`, `duration_ms`, `status` (new | opened |
  human | closed), `opened_at`, `photos_pruned_at`.
- Mazání: `matplace:prune` → `IntakeService::prunePhotos()` smaže složku fotek intakes starších 30 dnů a zapíše
  `photos_pruned_at`; řádek s textem a rozhodnutím zůstává (řádek „router photos removed: N“). Soubor modelu ze
  souborové cesty jde dnešní cestou `ModelLibrary::unordered` (30 dnů bez objednávky).
- `GET /intake/{token}/file`: `model.jpg` jen podle tokenu, `X-Robots-Tag: noindex, nofollow`, `Cache-Control:
  private, max-age=600` (`response()->file()` je od Symfony `public`, proto `setPrivate()`).

### 22.3 C: rozhodování

- `App\Domain\Intake\IntakeRouter`: jedno volání `Assistant::ask('intake', …)` s obrázkem; `config/ai.php`
  `kind_models.intake = env('ANTHROPIC_INTAKE_MODEL', 'claude-sonnet-5-5')`, nové `kind_timeouts.intake = 20` s
  (`ClaudeAssistant` nově čte limit po druhu, ostatní druhy beze změny). Systémový prompt nese katalog
  `IntakeRouter::TOOLS` (figure, pet, charm, medallion, map, topper, coaster, earrings: co berou, co dělají, rozsah
  hlavní velikosti, počet barev) a pravidla ze zadání; odpověď je JSON schéma `route, tool_key, params {size_mm,
  colors[], copies, text, place, kind}, summary {cs,en,es}, questions[], confidence`.
- Pravidla domu nad odpovědí (`IntakeRouter::decide`): neznámá cesta, neznámý nástroj, `file` bez souboru,
  `confidence < 0.6` → `human`; `shape` → `human` + `wants_shape` (fáze 1, tvar dělá Roman ručně); parametry jen ve
  tvaru, který nástroj bere (velikost v rozsahu nástroje, max. 6 barev, kusy ≤ 100, text ≤ 60 znaků, `kind` jen
  figure/pet); věta pro zákazníka v jeho jazyce, při přepsání modelu pravidly věta domu (`router.summary.fallback` /
  `router.summary.shape`, obě s `:hours`). Chyba API nebo limit → `human`, věta „ozveme se do N hodin“, v `answer`
  `failed` + chyba. Cena a čas volání se čtou z `ai_calls` (subjekt `intake`) do `intakes`.
- Bez klíče (`Assistant::available()` false) rozhoduje `IntakeRouter::guess()`: skupiny slov cs/en/es v pořadí
  člověk (díl, rozměry, závit, „aby pasovalo“…), tvar (soška, maskot, hračka, drak), mazlíček, člověk na fotce,
  dort, mapa, medaile, podtácek, náušnice, přívěsek; velikost z „12 cm“ / „40 mm“, kusy z „4 kusy“, „dva kusy“;
  fotka bez slov → tvar; jinak člověk. Zapíše se `model = keywords`. V testech je `FakeAssistant` (dostupný), takže
  tam se chodí cestou modelu s podstrčenou odpovědí.
- Zlatý soubor `tests/fixtures/intake/cases.json` (18 případů cs/en/es, s fotkou i bez, očekávaná cesta, nástroj,
  `wants_shape`, parametry). `IntakeTest::test_the_golden_cases…` ho žene přes klíčovou náhradu;
  `php artisan matplace:intake-cases [--photo=cesta] [--only=1,2]` ho požene přes skutečný model, až bude klíč
  (tabulka očekáváno / dostalo, jistota, ms, tokeny, věta cs).
- **Čísla:** skutečné volání tu neproběhlo. Odhad z promptu: systém + uživatel ≈ 1 100–1 300 tokenů, fotka 2000 px
  ≈ 1 500 tokenů (API ji zmenší na 1568 px), odpověď ≈ 200–300 tokenů; Sonnet 5.5 (2 / 10 USD za milion) ≈ 0,01
  USD ≈ 0,25 Kč na rozhodnutí; doba podle zkušenosti s `design` druhem 4–8 s, limit 20 s. Skutečná čísla ukáže
  `/admin/intake` (sloupec „jistota · ms · Kč“) a `/admin/ai` po nasazení.

### 22.4 D: předání do nástroje

- `App\Http\Middleware\IntakeHandoff` (ve skupině web za `ToolGate`): GET stránky nástroje s `?intake=<token>` (24
  znaků) najde intake, `IntakeService::handoff()` ho poprvé označí `opened` + událost `intake_tool_opened`, a do
  `request()->attributes['intake']` dá `{token, tool, file, name, params, text}`; `tools/page.blade.php` to vloží do
  `window.MP_TOOL.intake`. Hlavní strana a katalog token ignorují, cizí token nic neotevře, fotka bez `file` = null.
- Jeden háček pro všechny moduly: `tool_page.ts` po startu modulu volá `calc/intake_preload.ts preloadIntake()`:
  nejdřív parametry (`#figure-size` / `[data-param=width]` / `[data-param=size]` ← `size_mm` v mezích pole,
  `[name=kind]` nebo `[name=style]` ← `kind`, `[data-text=line1]` a `[name=pedestal_name]` ← `text`,
  `[data-text=name]` + `#map-place` + klik na `#map-search` ← `place`), pak fotku: `fetch` z `intake.file`, `File`
  přes `DataTransfer` do `input[type=file][data-intake-photo]` a `change`. Pole označují `_figure_photos.blade.php`
  (`#figure-photo`: busta i mazlíček), `param.blade.php` (`#param-artwork`: charm, coaster, earrings, medallion…;
  skrytý vstup měl dosud jen dialog, `param.ts` mu dal `onchange` → `uploadArtwork` → `setArtwork`) a
  `_papel_upload.blade.php` (`#param-artwork-file`). Souhlas u busty zůstává na zákazníkovi.
- Ověřeno testem (`IntakeToolPreloadTest`: figure, pet-figurine, charm, map, cake-topper, medallion, coaster,
  earrings dostanou payload; figure/pet/charm mají označené pole). V prohlížeči neprošlo: lokální dev.sqlite
  nemá tabulku `intakes` (migrace se ve worktree nespouští), takže stránka nástroje s tokenem tu nejde otevřít.

### 22.5 E: vezme si to člověk — rozhodnutí

- **Tržiště se neohýbá.** Důvody: `Route::middleware('feature:marketplace')` drží `/q/{quote}` i `/i/{inquiry}` na
  404, dokud je tržiště vypnuté (produkce: `FEATURE_MARKETPLACE` false, farma fáze 1); `InquiryService::create`
  potřebuje `Calculation`, `createSparePart` rozesílá `PrinterProfile`ům a naše farma žádný profil tiskaře nemá;
  `QuoteBuilder::fromCalculation` chce `PrinterProfile` a jeho ceník. Použít tok nabídek by znamenalo zapnout část
  tržiště nebo založit falešného tiskaře. Proto jednoduchá tabulka `intake_requests` (e-mail, jméno, telefon,
  `consent_at`, `wants_shape`, stav new | answered | closed, `reply`, `replied_at`, `replied_by`, jazyk) a
  administrace `/admin/intake` (sekce „Rozcestník“ v `admin/nav.blade.php`): seznam (filtr „Poptávky člověku“ /
  „Všechna zadání“, jistota · ms · Kč, model), detail (fotka přes `/admin/intake/{token}/photo`, text, rozhodnutí,
  parametry, otázky, věty ve třech jazycích, co řekl model a co rozhodla pravidla), pole pro odpověď (předmět +
  text → `PlainMessage` zákazníkovi v jeho jazyce, stav `answered`), „Uzavřít bez odpovědi“ / „Otevřít znovu“.
- Zákazník: `POST /intake/{token}/request {email, consent}` (`throttle:10,60`), bez souhlasu 422
  `router.request.need_consent`; vznikne `IntakeRequest` (stejný intake znovu = přepis, ne dvojice), intake
  `status = human`, mail `App\Mail\IntakeReceived` („Máme vaši poptávku, ozveme se do :hours hodin“ + jeho zadání)
  ve frontě, obsluze `OrderFlow::alertAdmin(…, 'inquiry', null, url)` s novým druhem `inquiry` (`ADMIN_KINDS`,
  přepínač `notify_inquiry` v nastavení farmy, zaškrtávátko „poptávka z rozcestníku“), `alertAdmin` dostal volitelný
  `$url` (tlačítko mailu vede do `/admin/intake/<token>`).

### 22.6 F: soubor

- 3D soubor z karty jde přes `UploadController::accept()` (vyčleněno ze `store()`, stejná kontrola formátu, uložení,
  `ProcessModelFile`, událost `upload`, `measure`), intake dostane `model_file_id`, `route = file`, bez volání
  modelu. Odpověď má `actions.calc = /?open=<uuid>&note=<text>`; `calculator.ts` už `?open=` umí (otevře soubor,
  skryje `#hero`), `site/intake.ts noteFromAddress()` přečte `note` dřív, než kalkulačka adresu vyčistí, a ukáže ji
  v `#calc-note` („Vaše poznámka“, v kartě ceny v `index.blade.php`). Stránka zakázky: `OrderController::show` dá
  `intakeNote` = poslední text téhož návštěvníka (účet nebo relace) k témuž `model_file_id`, `order.blade.php` jím
  předvyplní `textarea[name=note]` (cizí objednávka téhož souboru ho nedostane). Úpravy z textu („zmenšit na
  10 cm“) jsou fáze 3, nic se z nich nečte.

### 22.7 G: měření

- Události (`Track::event`, podmět `intake`): `intake_submitted {kind, has_text}`, `intake_routed {route, tool,
  confidence, ms, tokens}`, `intake_tool_opened {tool}`, `intake_human_created {wants_shape}`, `intake_file_to_calc
  {kind = přípona}`. Pojmenování podtržítkem jako dosavadní typy (zadání psalo `intake.submitted`; tečka by se
  tloukla s `failed:<krok>` a s konvencí AJ.md). `Track::META` nově pouští `route, confidence, ms, tokens,
  has_text, wants_shape` (čísla, true/false, kódy; text zadání se do `events` nedostane), `intake_submitted` je
  v `Track::ACTIONS` (potvrdí návštěvu jako lidskou).
- Trychtýř `/admin/stats`: cesta „Rozcestník“ (`Funnel::PATHS['intake']`): návštěva → odesláno → rozhodnuto
  (asistent, nebo soubor do kalkulačky) → otevřel nástroj / nechal e-mail / soubor v kalkulačce → zaplaceno; popisky
  kroků v `funnel.blade.php`.

### 22.8 H: testy a ověření

- Nové: `HomeRouterPageTest` (5 testů: H1 a sekce ve třech jazycích bez syrových klíčů, vánoční věta podle
  nastavení včetně prošlého data a formátů cs/en/es, cena „od“ jen u busty, pořadí sekcí a vstup kalkulačky,
  karty podle `ToolVisibility`), `IntakeTest` (14: text → nástroj a události, jazyk odpovědi, fotka EXIF → 48×64 a
  předání modelu, HEIC → JPEG, bez Pythonu přes `Process::fake`, odmítnutí prázdného zadání a ne-obrázku, nízká
  jistota / tvar / rozbitá odpověď / `file` bez souboru / selhání API, ořez parametrů, zlaté případy + živý asistent
  bez klíče, soubor → kalkulačka a poznámka v zakázce, 5 za hodinu, poptávka s maily a přepínačem, administrace
  s odpovědí, mazání po 30 dnech přes `matplace:prune`), `IntakeToolPreloadTest` (4). Fixtures
  `tests/fixtures/intake/{photo.jpg (EXIF 6), photo.heic, cases.json}`.
- Upraveno: `CalculationFlowTest::test_homepage_keeps_the_working_entrance…` čeká nový H1.
- Prošlo (po jedné sadě, `php -d memory_limit=2G vendor/bin/phpunit --filter …`): HomeRouterPageTest 5/112,
  IntakeTest 14/285, IntakeToolPreloadTest 4/63, HomeWaysTest 3/60, CalculationFlowTest 10/149, SeoTest 12/4324,
  LandingPagesTest 5/3665, ToolPageTest 25/4710, FarmAdminNotifyTest 7/162, AuditEventsTest 5/94, FarmPagesTest,
  FarmOrderFilesPruneTest, FarmGuestCheckoutTest, FarmBigModelFlowTest, StickyLayoutTest, FigureToolTest,
  PetFigurineTest (viz zpráva řídící pro konečný seznam). `npm run build` (tsc + vite + kontroly) prošel:
  `app-*.js` 38,1 kB (gzip 13,1 kB; `site/intake.ts` je v něm), `boot-*.js` 269,9 kB beze změny podstaty,
  `app.css` 96,5 kB. `vendor/bin/pint --test` na změněných souborech čistý.

### 22.9 Co rozhodne Roman

- Datum „do Vánoc“ (`christmas_deadline`, dnes 15. 12. 2026) a lhůta „ozveme se do“ (`intake_reply_hours`, 24).
- Ceny „od“ u karet: dnes jen busta (99 Kč z kalkulace vxauvlvidinz). Ostatní dostanou cenu, až v `config/site.php
  example_items` přibude jejich skutečná kalkulace (nebo jiný zdroj); nic se nevymýšlí.
- Texty mailů: zákazníkovi `router.mail.received.*`, odpověď píše obsluha v adminu (předmět předvyplněn
  `router.mail.reply.subject`); obsluze `farm.admin.mail.intake*`.
- Příklady ve štítcích (`router.chip.*` a `router.text_placeholder`), věty u tří cest (bez cen, bez „2 minut“ u
  tvaru: fáze 1 ho dělá člověk).
- `capture` u vstupu fotky (§22.1), model (`ANTHROPIC_INTAKE_MODEL`, výchozí Sonnet 5.5) a limit 5/h.

### 22.10 Co zbývá

- Nasazení: migrace `intakes` + `intake_requests`, `php artisan config:cache` (nové klíče `farm.settings.*`,
  `ai.kind_timeouts`), kontrola `pillow_heif` v `/opt/matplace-py` (je, 1.8), klíč `ANTHROPIC_API_KEY` tam je;
  první skutečné rozhodnutí prohlédnout v `/admin/intake` a čísla doplnit sem.
- Fáze 2: `shape` přes `GenerationService` (dnes `wants_shape` + člověk). Fáze 3: úpravy souboru z textu.
- Fotky od Stelly na karty dárků přes správu fotek nástrojů (§15), až budou.
- Stránka nástroje s předanou fotkou v prohlížeči (lokálně bez tabulky nešlo; po nasazení projít
  `/tools/figure?intake=…` z opravdového zadání na telefonu).

### 22.11 2. kolo: méně člověka, nástroje pro obrázek, „Zkusit hned bez návrháře“ (TODO #80)

Zadání `docs/prompts/rozcestnik-kolo2.md` (Roman 9. 11. 2026 po prvním ostrém testu: u známých obrázků šlo zadání
dvakrát člověku, až „bez návrháře“ v textu dostalo nástroj). Větev `feature/home-router-2` z `origin/main` d9d7b96a,
worktree `C:/matplace-edit-wt`, 9. 11. 2026 večer. Nic nenasazeno, nic nemergováno, migrace žádná (tabulky stačí:
historie je v `intakes.answer`). Klíč API ve worktree není: vše s `FakeAssistant` a zlatým souborem.

**A. Katalog a pravidla (`IntakeRouter`).** `TOOLS` má 14 nástrojů: k původním osmi přibyly `filament_art` (obraz
z vrstev filamentu na zeď, 50–250 mm, 8 barev), `papel` (portrét vystřihovánkou, 2 barvy), `sign` a `text` (cedulka,
stojící nápis; velikost = výška písma 4–80 mm; `input => text`, fotku nedostanou), `cutter` (vykrajovátko z obrysu),
`keychain` (klíčenka se jménem; `query => ['form' => 1]`, předání otevře rychlý formulář s polem pro obrázek). U každého
`takes`, `makes`, nově `when` (kdy ho zvolit), rozsah a barvy; katalog v promptu nese i veřejné názvy cs/en/es
(`tools.<klíč>.title`). Systémový prompt: hlavní pravidlo „obrázek nikdy člověku“ (fotka, kresba, malba, logo, známý
motiv či postava → nejbližší nástroj pro obrázek: plochý motiv coaster / filament_art / charm / earrings / sign, portrét
figure nebo papel, zvíře pet), člověk jen pro díly s rozměry, tolerancemi, závity, „aby pasovalo“ a věci bez smyslu
i s otázkami, `shape` jen pro skutečně prostorovou věc bez šablony a i pak s `alternatives`; při nejistotě mezi
nástroji zvolit nástroj a ptát se. `MIN_CONFIDENCE` 0.6 → **0.45** a jiný význam: nástroj pod prahem zůstává nástrojem,
jen se přidají otázky (modelovy, jinak `router.question.size` „Jak velké to má být?“ a `router.question.colors`
„Kolik barev?“ v jazyce návštěvníka); člověk je pro `human` od modelu, rozbitou odpověď a chybu API. Schéma: nová pole
`alternatives` (pole řetězců, v `decide()` jen známé klíče bez zvoleného, max. 3) a `reason` (věta pro administraci,
≤ 300 znaků); **unií (typ s null) je 6** (tool_key, size_mm, copies, text, place, kind; `IntakeRouter::unionCount()`),
limit API je 16 (TODO #79).

**B. Tlačítko a reroute.** Odpověď s cestou `human` (i `wants_shape`) má `actions.reroute = POST
/intake/{token}/reroute` (`throttle:intake`, tedy v týchž 5/h; u nástroje a po přepočtu `null`) a `rerouted`.
`site/intake.ts` kreslí v rámečku poptávky vedle „Odeslat poptávku“ tlačítko `router.act.without_designer` („Zkusit hned
bez návrháře“ / „Try it now without a designer“ / „Probar ahora sin diseñador“); po stisku `router.act.rerouting`
(„Hledáme nejbližší šablonu…“), odpověď vymění bublinu stejným `render()`, bez nového odeslání formuláře; 429 →
`router.too_many`. `IntakeService::reroute()`: nástroj beze změny (nic se nevolá), soubor 422, jinak
`IntakeRouter::reroute()`: dosavadní rozhodnutí (cesta, nástroj, parametry, jistota, věta, `wants_shape`, model,
tokeny, Kč, odpověď modelu bez historie) do `answer.history[]` s `at` a `origin` (`initial` | `reroute`), nový dotaz se
`system(true)` (pokyn „člověka ani tvar nenabízej, vyber nejbližší nástroj a řekni zákazníkovi, co se změní: zploštění
do reliéfu, jen obrys, zjednodušení“) a řádkem v uživatelské části. Když model přesto vrátí `human`/`shape` (nebo
volání selže): `force()` vezme první známou alternativu (nejdřív z nové odpovědi, pak z původní; busta a mazlíček bez
fotky se přeskočí), jinak nástroj podle slov zadání (`WORDS`), jinak podle druhu vstupu: fotka → `coaster`, jen text →
`sign`; věta domu `router.summary.forced` („Zkusíme to hned bez návrháře: :tool. Co se do šablony nevejde,
zjednodušíme…“), `answer.forced` = `alternatives` | `words` | `kind`. `route`/`tool_key`/`params`/`confidence`/`summary`
se přepíšou, `wants_shape` = false, `status` zůstává, tokeny a Kč se sčítají (model = poslední volání). Událost
`intake_rerouted {from: human|shape, to, tool, confidence, ms}` (`Track::META` nově `from`, `to`).

**C. Věty bez vnitřních názvů.** V promptu „nikdy nepiš klíč nástroje, jmenuj ho veřejným názvem z katalogu v jazyce
věty“. Pojistka `IntakeRouter::publicNames()` nad každou ze tří vět: klíč v uvozovkách nebo zpětných apostrofech
(`figure`, „pet“), klíč hned za slovem nástroj/tool/herramienta (i s dvojtečkou: „nástroj figure“ → „nástroj Busta
nebo figurka z fotky“) a klíč, který není slovem žádného jazyka (`filament_art`), se nahradí `tools.<klíč>.title`
v jazyce věty; jedním průchodem, aby se vložený název znovu nečetl jako klíč. Obyčejná slova shodná s klíči (map, sign,
text, figure, pet, charm) zůstávají slovy.

**D. Drobnosti.** Text mimo UTF‑8 → 422 `router.text_invalid` („Text se nepodařilo přečíst, zkuste ho napsat znovu.“;
`mb_check_encoding` před validací, dřív by to padlo až v databázi). `/admin/intake/{token}`: „Proč“ (`reason`), „Šlo
by i“ (alternativy s názvy), řádek „Po tlačítku Zkusit hned bez návrháře“ (+ podle čeho byl nástroj vynucen) a
„Historie rozhodnutí“ (původní + každý přepočet: čas, cesta, nástroj, jistota, důvod, alternativy, věta); seznam má
u řádku „bez návrháře (z člověka/tvaru)“. Trychtýř `/admin/stats`: řádek „Rozcestník bez návrháře“ =
`Funnel::reroutes()` (člověk nabídnut = `intake_routed` s `route = human`, tlačítko stisknuto = `intake_rerouted`,
kam to šlo podle `tool`). Blade pozor: `fotky@endif` se nepřeloží, před `@endif` musí být mezera.

**E. Testy.** `tests/fixtures/intake/cases.json` 18 → 30 případů (nové: známý obraz → filament_art/coaster cs/en/es,
logo oddílu → charm/keychain, dětská kresba → earrings/charm, portrét → figure/papel, papel picado → papel/figure,
cedulka → sign, nápis HOME → text/sign, vykrajovátko → cutter, klíčenka se jménem → keychain/sign/charm; `tools` =
více přijatelných nástrojů, `alternatives: true` = tvar musí jmenovat alternativy); `guess()` zná slova pro papel,
cutter, filament_art (před charm, jehož „obráz“ bral i „obraz“), keychain, text, sign. `matplace:intake-cases` bere
`tools` a trvá na `wants_shape`. Nový `IntakeRerouteTest` (6 testů: člověk → tlačítko → nástroj s historií, událostí a
součtem tokenů, opakovaný stisk nic nevolá; tvar → tlačítko, model trvá → alternativy původní i nové odpovědi; bez
alternativ slova (pes + fotka → pet) a druh vstupu (fotka → coaster, text → sign), busta bez fotky se přeskočí, pád
druhého volání → první alternativa + `failed`; 5/h včetně tlačítka; nástroj beze změny a soubor 422; admin vidí
historii, trychtýř počítá). `IntakeTest` upraven a rozšířen (17 testů: práh 0.45 s otázkami v jazyce stránky, tvar
s alternativami a tlačítkem, katalog 14 nástrojů s názvy ve třech jazycích a pravidly v promptu, 6 unií, klíčenka
s `form=1`, sanitizer vět, 422 u kódování). `IntakeToolPreloadTest` + 1 (filament-art, papel-picado, cookie-cutter a
rychlý formulář klíčenky mají označené pole, sign/text dostanou parametry bez fotky). Prošlo po jedné sadě:
IntakeTest 17/405, IntakeRerouteTest 6/146, IntakeToolPreloadTest 5/94, HomeRouterPageTest 5/112, StatsTest 21/273,
AuditEventsTest 5/94, ToolPageTest 25/4710, FilamentArtTest 7/145, LandingPagesTest 5/3665; `npm run build` (app 39,0 kB, boot 270,3 kB), `pint --test`
čistý. Snímky s odpovědí a tlačítkem (390 a 1280 px, cs/en) v scratchpadu session 3 (`router2-shots/home-*-answer*.webp`).

**Co rozhodne Roman.** Práh 0.45 (nebo jiný; už neznamená člověka, jen otázky). Zda „shape“ nechat jako cestu (dnes
člověk + `wants_shape` + alternativy + tlačítko), nebo ji ve fázi 2 schovat úplně za nástroje. Text tlačítka („Zkusit
hned bez návrháře“) a věty `router.summary.forced`, `router.question.*`. Po nasazení: první skutečná rozhodnutí
s novým katalogem v `/admin/intake` („Proč“), `matplace:intake-cases` nad 30 případy s klíčem.

## 23. Úvodní strana „Co chcete vyrobit?“: text první, ochrana proti spamu, sezonní nápověda

Zadání `docs/prompts/uvodni-strana-co-chcete-vyrobit.md` (Roman 12. 11. 2026: Stella chtěla „čelenku s rohy na
Mikuláše“ bez fotky; z 13 přání cizích návštěvníků 9.–12. 11. bylo 8 spam a 3 beze změny odeslané příklady). Větev
`feature/home-what-to-make` z `origin/main` 328f7b25, worktree `C:/matplace-edit-wt`, 12. 11. 2026. Nic nenasazeno,
nic nemergováno. **Migrace žádná**: stav `spam` se vejde do `intakes.status` (varchar 16), důvod zadržení je
v `intakes.answer.spam`, úpravy událostí v tabulce `farm_settings` pod klíčem `home.hints`. Skutečný model tu nikdo
nevolal (ve worktree není klíč): vše s `FakeAssistant` a s klíčovou náhradou.

### 23.1 Stránka

- `resources/views/calculator/home.blade.php`, první obrazovka (`#router`, jeden sloupec uprostřed, max. 672 px):
  H1 „Co chcete vyrobit?“ (`router.title`; en „What would you like to make?“, es „¿Qué quiere fabricar?“), pod ním
  rámeček `#intake-box` s polem `#intake-text` (velké písmo, na telefonu 152 px vysoké, aby se vešla celá nápověda)
  a ve spodním řádku téhož rámečku „Přidat fotku“ (`label#intake-photo-btn` → `#intake-photo`, dál bez `capture`:
  telefon nabídne foťák i galerii) a „Diktovat“ (`#intake-mic`, v HTML skryté, ukáže ho až skript). Vybraná fotka
  nebo soubor se ukáže v rámečku pod textem (`#intake-preview` s náhledem a „odebrat“). Pod rámečkem štítky
  `#intake-hints` (§23.3), tlačítko `#intake-submit` „Vytvořit“ (60 px, na telefonu přes celou šířku), malý odkaz
  `#intake-file-link` „Mám hotový 3D soubor“ (`label` pro `#intake-model`; nahrání souboru jde dál dnešní cestou do
  kalkulačky), věta „Zdarma a bez registrace…“, řádek důvěry a věta o smazání fotky. Původní levý sloupec (eyebrow,
  podnadpis) a plocha pro fotku `#intake-drop` jsou pryč; vše pod tím (tři cesty, dárky, Vánoce, vlastní model, pět
  cest, nástroje, kroky) zůstalo ve stejném pořadí níž. Přetažení souboru na formulář funguje dál.
- Štítky na telefonu: dva řádky, které se posouvají do strany (`grid-rows-2 grid-flow-col overflow-x-auto`), od
  640 px zalomený řádek uprostřed. Důvod: šest štítků pod sebou by tlačítko vytlačilo z první obrazovky.
  `scripts/review_round2.mjs --pages=home --widths=390,1280 --langs=cs,en,es`: H1 i tlačítko na první obrazovce ve
  všech šesti kombinacích, `hScroll: false`, bez chyb konzole. Snímky `docs/img/home-what/home-{390,1280}-{cs,en,es}-1.webp`
  a `home-390-cs-chip.webp` (po kliknutí na štítek a prvním stisku „Vytvořit“).
- Diktování (`site/intake.ts dictation()`): `SpeechRecognition` / `webkitSpeechRecognition` (Chrome, Edge, Safari;
  Firefox ho nemá, tam se tlačítko neukáže). Stisk začne poslouchat v jazyce stránky (cs-CZ, en-US, es-ES), slyšené
  se připisuje za to, co v poli už je, další stisk nebo ticho skončí; odmítnutý mikrofon → `router.mic.denied`.
  `Permissions-Policy: microphone=(self)` platí od Shorts (#81). V Chrome rozpoznávání běží na serverech Googlu:
  na skutečném telefonu neověřeno.
- Štítek po kliknutí jen vepíše vzorové přání do pole. Když ho návštěvník pošle beze změny a bez fotky, první stisk
  „Vytvořit“ nic neodešle a pod tlačítkem řekne „To je náš příklad. Upravte ho podle sebe (jméno, velikost, barvy),
  nebo ho odešlete znovu tak, jak je.“; druhý stisk odešle (nad rámec zadání, kvůli třem odeslaným příkladům).
- Přání bez fotky, které neumí žádný nástroj: cesty se nemění (`human`, případně `wants_shape`), ale věta domu už
  nemluví o fotce, kterou nikdo neposlal: `router.summary.fallback_text` („Tohle si raději prohlédne náš 3D návrhář.
  Nechte nám e-mail, ozveme se do :hours hodin s návrhem a cenou. Fotka nebo náčrt pomůže, nutná není.“)
  a `router.summary.shape_text`; štítek odpovědi „Navrhne náš návrhář“ místo „Tvar podle fotky“. Pod odpovědí je
  pole pro e-mail jako dosud. Model, který sám řekne `human`, si svou větu nechává.

### 23.2 Ochrana proti spamu (`App\Domain\Intake\IntakeSpam`, `config/home.php intake.spam`)

Vše běží v `IntakeService::create()` po uložení řádku a **před** `IntakeRouter::route()`: co pravidla zadrží, dostane
`status = spam`, `route` zůstane prázdná, `cost_czk` prázdné, v `answer.spam` jsou `rules`, `points`, `reply`, `at`;
událost `intake_spam {kind, code = první pravidlo}` místo `intake_submitted` (ta by potvrdila návštěvu jako lidskou).
Odesílatel dostane 201 s `held` a větou `router.held` („Děkujeme, zprávu jsme přijali. Pokud jde o přání k výrobě,
popište prosím, co má vzniknout…“), bez nástroje, bez pole pro e-mail, bez „bez návrháře“; token zadrženého přání
neotevře `/request` (404) ani `/reroute` (422).

| pravidlo | co hlídá | váha |
|---|---|---|
| `honeypot` | neviditelné pole `topic` (mimo obrazovku, `tabindex=-1`, `aria-hidden`) je vyplněné | zadrží samo |
| `no_stamp` | chybí nebo nesedí razítko stránky `started` (čas vykreslení + HMAC klíčem aplikace); posílá ho jen skript stránky, holý POST formuláře ho nemá | zadrží samo (`require_stamp`) |
| `too_fast` | od vykreslení stránky uběhlo méně než `min_seconds` (3 s) | zadrží samo |
| `markup` | HTML značka, `href=`, `[url=…]` | 3 |
| `links` / `link` | dva a více odkazů / jeden odkaz mimo `link_hosts` (Printables, Thingiverse, MakerWorld, Thangs, Cults3D, MyMiniFactory, YouTube, matplace) | 3 / 2 |
| `strong` | fráze, kterou říká jen reklama (`fantastic post`, `writer for your`, `improve your posture`, `rent a car`, `seo`, `backlink`, `click here`…) jako celá slova | 3 |
| `weak` | slova, která reklama říká často (`blog`, `your website`, `we offer`, `free shipping`, `discount`, `best price`…) | 1 za každé, nejvýš 2 |
| `script` | víc než polovina písmen je v jiném písmu než `scripts` (Latin) a není fotka | 3 |
| `language` | aspoň 4 slova, žádná číslice, žádné slovo z `known_words` (malá slova cs, sk, en, es, de, pl) ani slovo, které zná rozcestník (`IntakeRouter::knows`), a není fotka | 3 |
| `long` | delší než 600 znaků bez fotky | 1 |

Zadrží `threshold` = 3 body. Text u 3D souboru se nečte (je to poznámka pro kalkulačku, model se nevolá), past
a razítko platí i tam. Limit počtu: `throttle:intake` = 5 za hodinu na relaci i na IP a nově 20 za den na IP
(`intake.limits`), zadržená přání se do limitu počítají.

**„Chtěl jsem znát vaši cenu“** (`price_only`): krátký text (do 12 slov, bez číslice, bez fotky), který obsahuje slovo
o ceně a nejmenuje nic, co rozcestník zná. Není to reklama, ale model nemá co rozhodnout: přání se uloží jako
zadržené (pravidlo `price_only`), model se nevolá a odesílatel místo neutrální věty dostane odpověď o ceně
(`router.price_only`: „Cena záleží na tom, co má vzniknout… Napište, co chcete vyrobit, a cenu uvidíte předem…
Máte hotový 3D soubor? Nahrajte ho…“). Skutečný návštěvník tak dostane, na co se ptal, a může napsat přání; robot nic
nestojí. Dotaz na cenu, který jmenuje věc („Kolik stojí busta z fotky 12 cm?“), jde modelu. Islandská a maďarská
verze téže věty padají na pravidlo `language` a dostanou neutrální větu.

**Čísla** (test `HomeWhatToMakeTest::test_the_rules_hold…`, vzorky `tests/fixtures/intake/spam.json`): z 10 vzorků
podle produkčních typů 9.–12. 11. zadrženo 10 (9 neutrální věta, 1 odpověď o ceně); doslova jsem měl jen věty citované
v zadání, odstavec půjčovny aut, pokračování reklamy na držení těla a oba arabské odstavce jsou dopsané podle popisu
typu. Tři další vymyšlené reklamy (dva odkazy, `[url]` s fotkou, azbuka) zadrženy také. Poctivé: 4 z produkce („Busta
mého táty…“, „Figurka našeho psa…“ s fotkou, „A broken wheel…“ s fotkou, „mia“) a Stellina čelenka prošly všechny; 30
zlatých případů rozcestníku prošlo 30; 110 příkladů ze štítků (všechny události × jazyky) prošlo 110; ze 14 dalších
vymyšlených poctivých textů (odkaz na Printables, odkaz na e-shop, slovensky, německy, polsky, arabsky s fotkou, dotaz
na cenu věci…) prošlo 12, jeden dotaz „Dobrý den, chtěl bych znát cenu.“ dostal odpověď o ceně (záměr) a **jeden je zadržen
neprávem**: ukrajinsky psané přání azbukou bez fotky („Потрібна фігурка собаки 10 см“). To je cena pravidla „cizí
písmo bez fotky“ ze zadání; s fotkou projde. Viz otázky.

**Administrace** (`/admin/intake`): oba pohledy („Poptávky člověku“, „Všechna zadání“) spam neukazují; přepínač
„Ukázat i spam (N)“ ho přidá (šedé řádky s důvodem). Detail zadrženého: proč (pravidla, body, co dostal odesílatel)
a tlačítko „Není to spam: vrátit a nechat vyhodnotit“ → stav `new`, jedno placené volání modelu, v `answer.released`
zůstane, co ho zadrželo. Opačně „Označit jako spam“ u každého jiného zadání (pravidlo `manual`; rozhodnutí zůstane,
vrácení už model nevolá).

**Kdyby pravidla nestačila, další stupeň** (nic z toho není hotové): (1) nechat model v téže odpovědi říct `spam:
true` (bez dalšího volání, jen pole ve schématu; takové přání pak nepůjde do poptávek člověku), (2) neviditelná
kontrola prohlížeče Cloudflare Turnstile (bez obrázků, ale třetí strana a souhlas), (3) přísnější limit pro relace
bez potvrzené návštěvy (`meta.js`).

Slabá místa, o kterých vím: záložka otevřená přes nasazení pošle formulář starým skriptem bez razítka → zadrženo
(`no_stamp`), odesílatel dostane neutrální větu; správce to vrátí. Reklama psaná latinkou, bez odkazu a bez známé
fráze projde k modelu (0,25 Kč) a skončí u člověka.

### 23.3 Sezonní nápověda (`App\Domain\Intake\IntakeHints`, `config/home.php intake.hints`)

Štítek = krátký název + vzorové přání (`data-fill`), kód `<událost>.<n>` (`data-hint`) pro měření. Ukáže se `total`
= 6: nejvýš `seasonal` = 3 z událostí, jejichž okno je dnes v jazyce stránky otevřené (nejdřív ta, která končí
nejdřív, od každé jeden příklad, pak druhé), zbytek stálé (`always`): nejdřív „Stálé příklady“ (busta, pes,
náhradní díl, cedulka), pak po jednom z narozenin a svatby. Bez otevřené události jen stálé. Okno = od `lead` dní
před dnem události do `stop` dní před ním (výroba a doprava), den je `MM-DD`, `easter`, nebo `sunday:N:M` (N-tá neděle
měsíce M), jeden pro všechny jazyky, nebo po jazycích; jazyk, který den nemá, událost nenabízí. Vánoce končí dnem
z nastavení farmy `christmas_deadline`, když je nastavený. `:year` v textu = rok události (+ `year_offset`).

| událost | jazyky | den | nabízí se (nejbližší) | příklady (cs štítky) → kam vedou |
|---|---|---|---|---|
| Stálé příklady | cs en es | celý rok | | Busta z fotky → figure · Figurka našeho psa → pet · Náhradní díl → člověk · Cedulka se jménem → sign |
| Narozeniny | cs en es | celý rok | | Zápich do dortu → topper · Klíčenka se jménem → keychain |
| Svatba | cs en es | celý rok | | Zápich na svatební dort → topper · Jmenovky na svatební stůl → sign |
| Mikuláš | cs | 5. 12. | 5. 11. – 1. 12. 2026 | Čelenka s rohy na Mikuláše → člověk · Přívěsek čert a anděl → charm · Jmenovka na balíček → sign |
| Vánoce | cs (24. 12.), en es (25. 12.) | | 4. 11. (en es 5. 11.) – 15. 12. 2026 (nastavení farmy; bez něj cs 15. 12., en es 16. 12.) | Ozdoba na stromek se jménem → charm · Podtácky s rodinnou fotkou → coaster · Vykrajovátko na cukroví → cutter · 3D mapa našeho města → map |
| Silvestr | cs en es | 31. 12. | 1. 12. – 27. 12. 2026 | Nápis 2027 na stůl → text · Zápich do dortu na Nový rok → topper |
| Tři králové | es | 6. 1. | 1. 12. 2026 – 1. 1. 2027 | Un llavero con nombre → keychain · Una figura de nuestra mascota → pet |
| Valentýn | cs en es | 14. 2. | 10. 1. – 10. 2. 2027 | Přívěsek srdce se jmény → charm · Nápis LOVE na poličku → text · Podtácek s naší fotkou → coaster |
| Velikonoce | cs en es | neděle (28. 3. 2027) | 21. 2. – 23. 3. 2027 | Vykrajovátko zajíček → cutter · Jmenovka na košík → sign · Přívěsek kuřátko → charm |
| Den matek | cs en 2. neděle května (9. 5. 2027), es 1. neděle (2. 5.) | | cs en 9. 4. – 4. 5., es 2. 4. – 27. 4. 2027 | Přívěsek z dětské kresby → charm · Nápis MAMINKA na poličku → text · Podtácek s fotkou dětí → coaster |
| Den otců | cs en 3. neděle června (20. 6. 2027), es 19. 3. | | cs en 21. 5. – 15. 6., es 17. 2. – 14. 3. 2027 | Klíčenka pro tatínka → keychain · Medaile pro tatínka → medallion · Podtácek pod pivo se jménem → coaster |
| Konec školního roku | cs 30. 6., en es 20. 6. | | cs 26. 5. – 25. 6., en es 16. 5. – 15. 6. 2027 | Medaile za vysvědčení → medallion · Cedulka pro paní učitelku → sign · Přívěsek s logem třídy → charm |
| Začátek školy | cs en 1. 9., es 10. 9. | | cs en 1. 8. – 29. 8., es 10. 8. – 7. 9. 2027 | Jmenovka na aktovku → sign · Klíčenka ke skříňce → keychain · Zápich do dortu pro prvňáčka → topper |
| Halloween a Dušičky | cs en es | 31. 10. | 26. 9. – 27. 10. 2027 | Vykrajovátko dýně → cutter · Přívěsek netopýr → charm · Cedulka Pozor, straší → sign |

Dnes (12. 11. 2026) tedy cs: Čelenka s rohy na Mikuláše · Ozdoba na stromek se jménem · Přívěsek čert a anděl · Busta
z fotky · Figurka našeho psa · Náhradní díl; en a es: tři vánoční + tytéž tři stálé. Texty všech příkladů ve třech
jazycích: `lang/src/home_what.json` (`router.hint.<událost>.<n>` a `.fill`).

**Kam příklady vedou.** `config/home.php` má u každého příkladu očekávanou cestu (`examples`). Ověřeno je jen
klíčovou náhradou (`IntakeRouter::guess`, tedy to, co by se stalo bez modelu): `php artisan matplace:intake-cases
--hints --keywords` → 110 ze 110 podle očekávání, a totéž drží test. **Skutečným modelem neověřeno** (klíč tu není
a kredit se neutrácí): `php artisan matplace:intake-cases --hints` požene všech 110 příkladů přes model (asi 110
volání ≈ 28 Kč), s `--only` jen vybrané. Jediný příklad, který úmyslně končí u člověka mimo náhradní díl, je čelenka
s rohy (Stellino přání): žádný nástroj ji neumí, dostane větu §23.1 a pole pro e-mail. Kvůli ozdobě na stromek zná
klíčová náhrada nově „ozdoba na strom…“, `ornament`, `adorno de/para el árbol` → charm; prompt modelu jsem neměnil.

**Administrace** `/admin/intake/hints` (odkaz „Příklady pod polem“ na `/admin/intake`): nahoře co je dnes na hlavní
straně ve třech jazycích; u každé události zaškrtávátko (vypnout), „nabízet od / do“ (den.měsíc., každý rok; prázdné
= podle kalendáře; správcovo „do“ má přednost i před termínem farmy) a pro každý jazyk pole s řádky `Štítek |
Vzorové přání` (nejvýš 4; prázdné pole vrátí texty ze slovníku). Uloží se jen to, co se liší od konfigurace
(`farm_settings`, klíč `home.hints`), takže pozdější změna slovníku se projeví u všeho, co správce nepřepsal. Jazyky
události a pravidlo dne (pohyblivé svátky) jsou v konfiguraci, ne v administraci. Přepsaný příklad si správce musí
vyzkoušet sám (stránka to říká).

### 23.4 Měření

- Prohlížeč hlásí `POST /intake/event {type, code}` (`throttle:60,1`, vždy 204): `intake_view` (první obrazovka
  ukázána skriptem; ne když se stránka otevřela s `?open=` do kalkulačky), `intake_chip {code: mikulas.1}` (každé
  kliknutí), `intake_photo` (první přidaná fotka), `intake_voice` (první použití diktování). Podmět `intake`, do
  `meta` jde jen kód tvaru `<událost>.<číslo>`.
- Odeslání: `intake_submitted` má nově `code` = `text` | `text_photo` | `photo` | `file` a u přání ze štítku
  `hint` (který) a `edited` (true = upraveno, false = posláno tak, jak bylo); `Track::META` + `hint`, `edited`.
  Zadržené: `intake_spam {kind, code}` (kód = první pravidlo, `price_only` u dotazu na cenu).
- Provozní relace: vše jde přes `Track::event`, takže prohlížeč, ze kterého se kdy přihlásil správce
  (`anonymous_sessions.staff`), a naše testovací běhy (`mp_test`) se nepočítají; roboti podle user-agenta také ne.
- Za měsíc: `Event::where('type', 'intake_submitted')` po `meta->code` řekne, čím lidé začínají; `intake_chip` po
  `meta->code`, které štítky táhnou; `intake_view` → `intake_submitted` je konverze první obrazovky. Do trychtýře
  `/admin/stats` jsem nové události nepřidával (cesta „Rozcestník“ začíná návštěvou jako dosud).

### 23.5 Testy

- Nový `HomeWhatToMakeTest` (12 testů): titulek, popis, og a strukturovaná data ve třech jazycích (§23.6); pořadí a přítomnost prvků první obrazovky ve třech jazycích (H1, pole, fotka
  a diktování v rámečku pole, štítky, tlačítko, odkaz na soubor, zbytek níž; razítko a past ve formuláři); štítky podle
  pevného data a jazyka (12. 11., 2. 12., 1. 1., 15. 7., pohyblivé svátky, termín farmy); každý příklad někam vede
  a není spam; přání bez fotky bez nástroje → poctivá věta a e-mail; úprava událostí v administraci (vypnout, data,
  texty, chybný řádek, návrat k výchozím); pravidla nad vzorky; spam se uloží a model se nevolá (past, rychlost,
  razítko, cena); administrace spam skrývá a umí ho vrátit; limity z konfigurace; měřené události; provozní relace.
- Upraveno: `IntakeTest`, `IntakeRerouteTest` (posílají bez stránky → `require_stamp` vypnuté, pravidla nad textem
  běží dál; nová pole události; věta bez fotky), `HomeRouterPageTest` (příklad ze štítku), `CalculationFlowTest`
  (nový nadpis).

### 23.6 Titulek, popis a strukturovaná data (dodatek řídící 12. 11. 2026)

Titulek hlavní strany říkal „Cena 3D tisku online: kalkulačka z vašeho modelu“, obsah mluví o výrobě z fotky nebo
popisu. Nově (`lang/{cs,en,es}/tools_seo/calc.php`, `title` + „ · matplace“, délky v závorce):

- cs (58): „3D tisk na zakázku: dárek z fotky, díl z popisu · matplace“; popis (148): „Napište, co chcete vyrobit, nebo
  přidejte fotku. Výroba podle fotky nebo popisu: busta, figurka, dárek se jménem i náhradní díl. Cenu vidíte předem.“
- en (59): „Custom 3D printing from a photo or a description · matplace“; popis (150): „Tell us what you want made, or
  add a photo. Custom 3D prints from a photo or a description: busts, figurines, name gifts, spare parts. Price up front.“
- es (58): „Impresión 3D a medida desde una foto o un texto · matplace“; popis (149): „Escriba qué quiere fabricar o
  añada una foto. Impresión 3D a medida: bustos, figuras, regalos con nombre y piezas de repuesto. Precio antes de pagar.“

`og:title` a `og:description` jdou z téhož (layout), takže sedí samy. Obrázek náhledu odkazu hlavní strany je nově
karta webu `/og/site/home.png` („3D tisk na zakázku“ + nová věta `site.og.line` „Napište, co chcete vyrobit, nebo
přidejte fotku. Cenu vidíte předem, vytiskneme a pošleme.“) místo karty nástroje s nadpisem „Cena ze souboru“.
Strukturovaná data: `WebSite` má nově `description` = popis stránky; `HowTo` zůstává návodem kalkulačky (kroky jsou
o nahrání souboru a ta část je na stránce níž) a má vlastní větu `howto_description` (dřívější popis), aby neříkal
něco jiného než své kroky; `FAQPage` a `Organization` beze změny. H2 a texty části o kalkulačce níž na stránce (`h1`,
`intro`, `steps`, `faq` v `calc.php`) jsem neměnil. Položka menu „Cena ze souboru“ zůstává.

### 23.7 Co rozhodne Roman

- Azbuka: pustit ji k modelu (`intake.spam.scripts` + `Cyrillic`)? Ukrajinsky psané přání bez fotky se dnes zadrží;
  cenou by byla ruská reklama u modelu (0,25 Kč kus).
- `min_seconds` 3 s a „žádné razítko = spam“: nechat, nebo po nasazení první den jen značit a nezadržovat?
- Dotaz jen na cenu: odpověď o ceně bez modelu (dnes), nebo ho posílat modelu.
- Upozornění „To je náš příklad…“ před odesláním nezměněného příkladu: nechat?
- Texty příkladů a data oken (tabulka §23.3); en/es Den matek, Den otců a školní data jsou odhad pro
  „obecnou“ angličtinu a Španělsko.
- Tlačítko „bez návrháře“ u přání bez fotky (čelenka) dnes vynutí cedulku se jménem; schovat ho tam?
- Věta u pole pro e-mail a souhlas mluví o „fotce a popisu“ i bez fotky (texty 1. kola, neměnil jsem).

### 23.8 „Chci medaili s reliéfem z fotky“ (ostrá zkouška řídící 13. 11. 2026)

Ostrý model dal nástroj medaile (jistota 0,6) a bez fotky o ni žádal, s fotkou sliboval „medaili s reliéfem podle
fotky“; nástroj medaile přitom fotku nebere (místo, číslo, motiv, text).

- **Prompt** (`IntakeRouter::system()`, i pro „bez návrháře“): nový odstavec „What a tool takes is all it takes“: nikdy
  neslibovat ani nežádat vstup, který `takes` zvoleného nástroje neuvádí; u kombinace, kterou neumí jeden nástroj,
  platí dál „obrázek není práce pro člověka“, ale věta má říct, co nástroj umí a co ne, do `alternatives` patří
  nástroje, které fotku berou (relief, coaster, charm, filament_art), nejlepší z nich jménem ve větě; otázky jen
  takové, které zvolený nástroj využije.
- **Katalog**: `relief` („Reliéf a litofanie z fotky“, veřejný nástroj `/tools/relief`) v `IntakeRouter::TOOLS`
  chyběl, model ho nemohl nabídnout. Doplněn (bere fotku, 40–250 mm, 1 barva), klíčová náhrada zná „reliéf“,
  „litofanie“, „lithophane“, „relieve“. Stránka nástroje dostane předanou fotku (`#relief-photo` má
  `data-intake-photo`) a šířku (`#relief-w`); v prohlížeči neověřeno (lokálně není tabulka `intakes`).
- **Klíčová náhrada** (`guess()`): nástroj zvolený bez věty modelu už nedostane větu návrháře, ale větu domu
  `router.summary.tool` („Tohle umí náš nástroj :tool. Otevřete ho, velikost a barvy doladíte v náhledu.“) a když
  slova jmenují i jiný nástroj, `router.summary.tool_also` („Hodit se může i: …“) + tyto nástroje v `alternatives`
  (`alsoNamed()`). Pro toto přání: medaile, „Hodit se může i: Reliéf a litofanie z fotky“. Náhrada neumí říct, co
  nástroj neumí; to je na modelu.
- **Zlaté případy**: 30 → 32 (toto přání s fotkou i bez: `tool`, medallion nebo relief, s alternativami). Hájím obojí:
  hlavní věc je medaile, ale jediný nástroj, který z fotky udělá reliéf, je relief.
- **Skutečný model tu neběžel** (klíč není, kredit se neutrácí): pravidlo je ověřené jen testem nad textem promptu.
  Po nasazení: `php artisan matplace:intake-cases --only=31,32` (dvě volání) a ostrá zkouška řídící.

## 24. Úvodní strana rovnou tvoří: nástroj s hotovým návrhem, jinak volný návrh podle textu

Zadání `zadani-uvodni-strana-rovnou-tvorit.md` (Roman 13. 11. 2026: „Nemůžeme mít nástroj na vše… Pokud je jasné, že
na to máme nástroj, ať se rovnou otevře nástroj a vygeneruje se nějaký návrh podle textu, nebo se použije fotka. Pokud
nástroj není, tak ať se otevře editor, kde už bude vygenerovaný návrh podle textu, a ne tenhle dvojitý dialog.“) a
rozhodnutí o počtech z téhož dne (`koncepce-jedna-dilna.md` §9: 10× zdarma, po přihlášení dalších 10×, dál placeně;
cena zatím není). Větev `feature/home-create-direct` z `origin/main` 7dc7f357, worktree `C:/matplace-edit-wt`,
13. 11. 2026. Nic nenasazeno, nic nemergováno, **migrace žádná**. Skutečný model ani skutečný generátor tu nikdo
nevolal: vše s `FakeAssistant`, s klíčovou náhradou a s `FakeGenerator` (krychle).

### 24.1 Jak cesta vypadá

Jedno zmáčknutí „Vytvořit“ = `POST /intake` jako dosud; odpověď má nově `go` (adresa, která se otevře sama) a skript
`site/intake.ts` na ni rovnou přejde. Pod tlačítkem se mezitím píše „Otevírám nástroj …“ / „Vytvářím návrh podle
vašeho popisu…“ / „Otevírám kalkulačku s vaším souborem…“. Karta ASISTENT se dvěma tlačítky už není.

| co asistent rozhodl | co se stane po zmáčknutí |
|---|---|
| nástroj, jistota ≥ práh (§24.2) | otevře se stránka nástroje s `?intake=<token>`: formulář vyplněný z textu (§24.3), fotka předaná jako vstup nebo zdobení, náhled se kreslí hned; pod názvem řádek „Asistent pochopil: … · Chtěl jsem něco jiného“ |
| nástroj, jistota pod prahem | volný návrh zapnutý a počty dovolí → volný návrh (§24.4); jinak se nástroj otevře stejně a řádek začíná „Asistent si nebyl jistý, tohle je nejbližší nástroj.“ |
| tvar bez šablony (`shape`; v klíčové náhradě i text, který nic nejmenuje, a není to díl) | volný návrh zapnutý → kalkulačka s logem z teček a pak s návrhem; vypnutý / vyčerpaný → **jedna** karta: věta, nástroje, které by to uměly také (odkazy rovnou s přáním), pole pro e-mail návrháři |
| člověk (díl s rozměry, „aby pasovalo“, nesmysl, selhání volání) | karta s větou a polem pro e-mail jako dosud; je-li volný návrh zapnutý, je na ní i tlačítko „Zkusit volný návrh“ s větou, co volný návrh neumí. Sám se u dílu nespustí. |
| 3D soubor | otevře se kalkulačka se souborem (dřív karta s tlačítkem) |
| zadrženo jako reklama / dotaz na cenu | věta jako dosud, nic se nevolá ani negeneruje |

**„Chtěl jsem něco jiného“** (v řádku nástroje i nad volným návrhem) vede na `/?wish=<token>`: skript si přání přečte
(`GET /intake/{token}`, `noindex`), vrátí slova do pole a pod tlačítkem ukáže kartu „Zkusit jinak“: nástroje, které
by to uměly také (i ten původní), „Zkusit volný návrh“ / „Otevřít návrh“ a **„Raději poptávka designérovi“**, která
teprve otevře pole pro e-mail. Poptávka člověku je tedy výslovná volba; sama se nabízí jen u dílů a tam, kde není co
otevřít. Souhlas a mazání fotek po 30 dnech beze změny. Měří se `intake_again` (návrat) a `intake_designed`
(`code` = auto | button, `kind` = text | photo, `from` = odkud).

Stav farmy: úvodní strana se řídí `FarmState` jako dosud (texty `.closed`, titulky a H1 beze změny). Zavřená farma
volný návrh nespouští (nic se negeneruje pro věc, kterou nejde objednat), nástroje se otevírají dál.

### 24.2 Práh jistoty: 0,6

`IntakeRouter::DIRECT_CONFIDENCE = 0.6`, výchozí hodnota `config/home.php intake.design.direct_confidence`, mění se
v `/admin/intake/design` bez nasazení. Od prahu se nástroj otevře bez poznámky; pod ním jde přání na volný návrh
(je-li zapnutý), jinak se nástroj otevře s poznámkou. Dřívější práh 0,45 (`MIN_CONFIDENCE`, jen přidává otázky)
zůstal, u nástroje se ale otázky už neukazují, protože karta není.

Z čeho číslo je, poctivě: dat je málo. (1) Zlaté případy jdou jen klíčovou náhradou, která u nástroje říká vždy 0,7
a u textu bez shody 0,3; všech 26 případů s nástrojem je tedy nad prahem a otevřou se rovnou, žádný případ bez
nástroje nad práh nepřeleze (test `test_the_golden_cases_end_on_a_design_after_one_press`). (2) Jediné ostré číslo,
které je zapsané: „Chci medaili s reliéfem z fotky“ dostalo správný nástroj s jistotou 0,6 (§23.8), proto práh
nesmí být výš. (3) První ostrá zkouška 9. 11. (§22.11): u známých obrázků model dával jistotu pod 0,6 a volil
„nejbližší šablonu“; to je přesně pásmo, kde má teď přijít volný návrh, nebo aspoň poznámka. Po nasazení je potřeba
pustit `matplace:intake-cases` nad zlatými případy se skutečným modelem a podívat se v `/admin/intake` na jistoty
prvních přání; práh je jedno pole v administraci.

### 24.3 Co který nástroj převezme z textu (`App\Domain\Intake\IntakePrefill`)

Model vrací v `params` nově i `text2` (druhý řádek), `number` (číslo samo o sobě: věk na zápichu, místo na medaili)
a `shape` (obrys); schéma má 10 unií z 16. Co model nevyplní (a všechno, když rozhoduje klíčová náhrada), doplní
slova přání (`IntakePrefill::fromWords`, cs/en/es): velikost („12 cm“, „40 mm“), kusy, jméno nebo nápis (v uvozovkách,
nebo za „se jménem“, „nápis“, „with the name“, „that says“, „con el nombre“…), číslo („číslem 2“, „5 let“, „2.
místo“), rok jako druhý řádek, barvy (16 základních barev palety), počet barev („tři barvy“), tvar (srdce, hvězda,
kruh, čtverec, obdélník, ovál, šestiúhelník, štít, mrak, kost, domek), stužka / řetízek, materiál (PLA, PETG, TPU,
ABS, ASA). `IntakePrefill::values()` to přepíše do hodnot formuláře daného nástroje (klíče `ParametricGenerator`
`FIELDS`, `TEXTS`, `CHOICES`, `FLAGS`; čísla oříznutá do mezí, texty do délky pole, tvar jen takový, jaký nástroj má)
a stránka je použije jako uložený návrh (`param.ts`, `applyValues`). `IntakePrefill::understood()` z toho skládá řádek
„Asistent pochopil“: jen to, co se do formuláře opravdu dostalo.

| nástroj | velikost | text | další |
|---|---|---|---|
| figure (busta, figurka) | výška | jméno na podstavec | `kind` bust / figure (jen od modelu), fotka do pole fotky; souhlas dává zákazník na stránce, generování se samo nespustí |
| pet (mazlíček) | výška | jméno na podstavec | styl (jen od modelu), fotka; totéž |
| charm (přívěsek) | šířka | 1. a 2. řádek | tvar (srdce, hvězda, kruh, ovál, obdélník, šestiúhelník), počet barev obrázku, fotka / obrázek |
| ornament (ozdoba) | šířka | jméno + rok na 2. řádek | tvar (kruh, hvězda), počet barev, fotka; `decor = photo` → reliéf |
| keychain (klíčenka, rychlý formulář) | šířka | 1. a 2. řádek | tvar (obdélník, kruh), počet barev, fotka; `decor = photo` |
| medallion (medaile) | šířka | nápis | **místo 1–3 → předvolba place1–3** (číslo vpředu, zlatá / stříbrná / bronzová; i z jedné barvy „zlatá“), nápis pak na 2. řádek; stužka / řetízek; tvar; `decor = photo` |
| map (mapa) | strana | — | místo (jen od modelu; hledání se spustí samo) |
| topper (zápich) | šířka | jméno | číslo (šablona „číslo“), šablona srdce / hvězda / kruh |
| coaster (podtácek) | šířka | 1. a 2. řádek | tvar (kruh, čtverec, šestiúhelník), počet barev, fotka; `decor = photo` |
| relief | šířka | — | fotka |
| filament_art (obraz) | šířka | — | fotka |
| earrings (náušnice) | šířka | 1. a 2. řádek | tvar (kruh), počet barev, obrázek |
| papel | šířka | — | fotka |
| sign (cedulka) | výška písma | až 3 řádky („Jana / Nováková“) | tvar (20 tvarů cedulky), **dvě barvy = destička + písmo** (`two_color`, `part_colors`), jedna barva = celá cedulka; fotku nedostane |
| text (stojící nápis) | výška písma | 1 řádek | jedna barva |
| cutter (vykrajovátko) | šířka | 1. a 2. řádek | obrázek |
| všechny se sekcí „Materiál a počet kusů“ | | | počet kusů, materiál |

Co se nepřebírá: barvy u nástrojů s obrázkem (barvy jsou z obrázku; bere se jen jejich počet), písmo, zdobení textem,
u mapy místo z klíčové náhrady, u busty a mazlíčka barvy. Test `test_every_tool_of_the_catalogue_takes_what_the_words_say`
(21 přání v `tests/fixtures/intake/direct.json`, každý nástroj katalogu aspoň jednou; hodnoty jsou vždy pole formuláře
a v mezích).

### 24.4 Volný návrh (`App\Domain\Intake\IntakeDesign`)

**Zvolená cesta: stávající generátor modelů (`GenerationService`, na produkci Tripo) z textu, výsledek v kalkulačce.**
Proč: je to jediná cesta, která už celá existuje a je ostrá. `GenerateModel` udělá z textu síť, `ModelNormalizer` ji
uzavře a položí, vznikne `ModelFile` (`origin = generated`) a jde stejnou přípravou a kontrolou jako nahraný soubor.
Kalkulačka na úvodní straně už umí vše, co zadání chce od „editoru“: 3D náhled, velikost, materiál a barvu, cenu,
objednávku, stažení a „Chcete na modelu něco změnit?“ (nová generace z původního zadání + úpravy). Zavrženo: návrhář
stojánků (`DesignAgent`) umí jen parametry jednoho nástroje; Meshy nebylo nikdy zavoláno (Y.md §11) a přepíná se
jednou proměnnou, kdyby zkouška vyšla líp; „recept“ z koncepce je krok 3–4, ne tohle zadání.

- Model v rozhodnutí vrací `prompt_en` (věc anglicky pro generátor, bez jmen, rozměrů a barev) a `photo` (none |
  person | animal | object | picture). Generátor dostane `prompt_en`; bez modelu slova návštěvníka. Z fotky se návrh
  dělá jen tehdy, když model řekl `object` nebo `animal` (fotka projde stejnou kontrolou jako u busty, kopie pro běh
  se po něm smaže, přání si svou drží 30 dnů). **Z fotky člověka se volný návrh nedělá nikdy**: použijí se jen slova;
  busta má svůj nástroj se souhlasem.
- Stránka: `/?design=<generování>&wish=<přání>` (`calculator.ts openDesign`). Hned se ukáže kalkulačka s logem z teček
  a procenty, nad ní řádek „Návrh podle vašich slov: „…“ · Chtěl jsem něco jiného“ a věta „Tvar vymyslela umělá
  inteligence podle popisu: je přibližný a rozměry nejsou přesné…“. U věci, která se nosí nebo má někam pasovat
  (čelenka, náramek, držák, kryt…; `IntakeDesign::mustFit`), přibude „…nevíme, jestli bude sedět: na míru ji umí náš
  návrhář.“ Nikde se neslibuje, že bude sedět. Když generování selže nebo model nejde připravit k tisku, řekne to
  („Návrh se nepovedlo vytvořit…“ / „…nepodařilo připravit k tisku…“) s tlačítky „Popsat jinak“ a „Raději poptávka
  designérovi“.
- Co volné generování umí a neumí (psáno i zákazníkovi): tvar podle popisu, ozdobné a hravé věci. Neumí přesné
  rozměry, díly na míru, závity, věci, které mají někam pasovat, text na modelu spolehlivě, podobu konkrétního
  člověka. To patří parametrickým nástrojům nebo návrháři.

**Přepínač a počty** (`/admin/intake/design`, uloženo ve `farm_settings` pod `home.design`, výchozí v
`config/home.php intake.design`):

| nastavení | výchozí | význam |
|---|---|---|
| Volný návrh zapnutý | **vypnuto** | dokud ho Roman nezapne, nic se negeneruje; navíc musí být nastaven generátor (`ENGINE_GENERATOR`) |
| Zdarma bez účtu | 10 | návrhů a úprav slovy na návštěvníka, **celkem, ne za den** |
| Dalších zdarma po přihlášení | 10 | účet má tedy 20 |
| Cena dalšího | prázdná | Kč s DPH z kreditu; prázdné = neprodává se, placená volba se nikde neukáže |
| Návrhů celkem za den | 100 | strop nákladů celého webu (počítá i placené) |
| Jistota pro přímé otevření nástroje | 0,6 | §24.2 |

Jak se počítá: **jedno použití = jedno spuštění generátoru** označené `description.intake` (první návrh nebo úprava
slovy v kalkulačce). Nepočítá se kopie dřívějšího stejného výsledku (stejné zadání podruhé je zdarma a hned),
nepovedený běh a placený běh. Návštěvník bez účtu = cookie prohlížeče `mp_sid` (anonymní relace) **nebo** IP adresa
za posledních 30 dní, co je víc (smazaná cookie nedá dalších deset; sdílená adresa není zablokovaná navždy). Po
přihlášení se počítají běhy účtu plus běhy téže relace prohlížeče před přihlášením, takže vyčerpaných 10 zůstává
a přibude dalších 10; z jiného zařízení má účet svých 20 bez toho, co kdo udělal anonymně jinde. Správce se
nepočítá. Po vyčerpání: bez účtu věta „…Přihlaste se a dostanete dalších 10…“ a tlačítko Přihlásit se; s účtem a bez
ceny „…Další zatím nenabízíme. Zkuste některý nástroj, nebo to předejte našemu návrháři.“; s cenou „…Další stojí
X Kč z kreditu“ a tlačítko „Další návrh za X Kč“ (v kalkulačce „Upravit za X Kč“): strhne se až tímto stiskem,
nikdy samo; nestačí-li kredit, věta s chybějící částkou a „Dobít kredit“. Denní strop: „Dnešní volné návrhy jsou
vyčerpané, zítra to půjde znovu…“. Před tím vším stojí ochrana úvodní strany (§23.2: zadržené přání se nedostane
k modelu ani ke generátoru) a `throttle:intake` (5 za hodinu, 20 za den na adresu; tlačítko „Zkusit volný návrh“ se
počítá do téhož). Stará denní kvóta generování (`generate_guest` 1/den…) pro tyto běhy neplatí, pro bustu, figurku
a „Popsat slovy“ platí dál beze změny.

**Ukládání:** nic nového se neukládá „na později“. Návrh je `ModelFile` jako každé generování: anonymní návštěvník ho
v relaci vidí, objedná a stáhne; trvalé uložení projektu pro přihlášené je práce C z koncepce (§9), tady není.
Pozor: adresa `/?design=…` funguje komukoli, kdo ji zná, stejně jako dnešní `/?open=<uuid>` a odkazy na generování
(viz otázky).

### 24.5 Ceny (odhad, nic neměřeno)

| co | dodavatel | cena |
|---|---|---|
| rozhodnutí asistenta (každé přání) | Claude Sonnet 5.5 | ≈ 0,25–0,3 Kč (§22.3; prompt je o pár set tokenů delší) |
| volný návrh z textu | Tripo, geometrie `detailed` (produkční výchozí) | 30 kreditů = 0,30 USD ≈ **6,9 Kč** |
| | Tripo, geometrie `standard` | 10 kreditů = 0,10 USD ≈ 2,3 Kč |
| volný návrh z fotky věci | Tripo `detailed` / `standard` | 40 / 20 kreditů ≈ 9,2 / 4,6 Kč, + kontrola fotky (Haiku, haléře) |
| totéž | Meshy 7.1 | 20–25 kreditů; cena kreditu 0,02 USD je **předpoklad** (Y.md §11) ≈ 9–11,5 Kč; nikdy nezavoláno |

Kredity Tripa podle komentáře v `TripoGenerator` (text 10, fotka 20, jemná geometrie +20; 1 kredit = 0,01 USD), kurz
23 Kč. Deset volných návrhů jednoho návštěvníka je tedy až ≈ 69 Kč, denní strop 100 návrhů až ≈ 690 Kč. Skutečné
částky ukáže `/admin/ai` po prvních bězích; `/admin/intake/design` ukazuje odhad podle ceníku a dnešní počet.

### 24.6 Soubory

- Nové: `app/Domain/Intake/IntakePrefill.php`, `IntakeDesign.php`, `resources/views/admin/intake/design.blade.php`,
  `lang/src/home_direct.json` (68 klíčů `router.*`, `refine.*`), `tests/Feature/HomeCreateDirectTest.php`,
  `tests/fixtures/intake/direct.json`, `scripts/shots_home_direct.mjs`.
- `IntakeRouter`: `DIRECT_CONFIDENCE`, `PHOTO_SUBJECTS`, `technical()`, schéma (+ `text2`, `number`, `shape`,
  `prompt_en`, `photo`), pravidlo modelu pro `shape` („volný návrh“ místo „člověk udělá tvar“), parametry; katalog
  `TOOLS`, slova `WORDS` a nápovědy jsem **neměnil** (souběh s `feature/headband-tool`).
- `IntakeService`: `describe()` (+ `go`, `again`, `text`, `fit`, `unsure`, `others`, `design_note`, `login`,
  `actions.design`, `start_design`, `pay_design`), `startDesign()`, `handoff()` (+ `values`, `understood`, `unsure`,
  `back`, `words`); `IntakeController` `show`, `design`; trasy `GET /intake/{token}`, `POST /intake/{token}/design`,
  `/admin/intake/design`.
- `GenerationService`: `fromIntake()`, vlastní počty pro běhy s `description.intake`, `refine(..., pay)`;
  `GenerationController::refine` vrací u volného návrhu větu a `can_pay`.
- Skripty: `site/intake.ts`, `calc/intake_preload.ts` (`showUnderstood`), `calc/param.ts`, `calc/tool_page.ts`,
  `calc/calculator.ts` (`openDesign`, placená úprava slovy); `calculator/index.blade.php` (`#design-line`),
  `calculator/home.blade.php` (texty, druhá ze tří cest podle přepínače).

### 24.7 Testy a ověření

- Nový `HomeCreateDirectTest` (18 testů): jasný nástroj → `go` s parametry ve formuláři a řádek cs/en; fotka jako
  vstup i jako zdobení, klíčenka s `form=1`; nejistý nástroj při vypnutém návrhu; přání bez nástroje při vypnutém
  návrhu (jedna karta); volný návrh z textu (generování, soubor prošel přípravou, jde upravit slovy, cesta zpět);
  věc, která se nosí (žádný slib); nejistý nástroj → návrh, práh z administrace; díl s rozměry zůstává člověku
  a návrh je tlačítko; fotka člověka nikdy není zdrojem návrhu, fotka věci ano; počty 10 + 10 + cena / nic (bez účtu,
  po přihlášení, bez ceny, s cenou, málo kreditu, placený stisk, úprava slovy); denní strop, nepovedený běh, kopie,
  správce; zadržené přání, zavřená farma, jedno slovo; klíčová náhrada → návrh; zlaté případy po jednom zmáčknutí
  (26 s nástrojem se otevřou rovnou, díly zůstanou u člověka, zbytek při zapnutém návrhu jde na návrh); každý nástroj
  katalogu a jeho hodnoty; prompt a schéma; úvodní strana a administrace; výchozí hodnoty (vypnuto, 10, 10, bez ceny).
- Upraveno: `IntakeTest`, `IntakeDecorTest`, `HomeWhatToMakeTest` (počet unií ve schématu 7 → 10). Nápovědy 110/110
  drží stávající `HomeWhatToMakeTest` beze změny.
- `npx tsc --noEmit -p .`, `npm run build` (app 47,7 kB, boot 287 kB), `pint --dirty` čisté.
- **Snímky** `docs/img/home-direct/` (390 a 1280 px, cs), `node scripts/shots_home_direct.mjs --base=… --payloads=tests/fixtures/intake/direct_payloads.json`:
  `tool-*` (úvodní strana → „Otevírám nástroj…“ → cedulka s řádkem, červená destička s bílým „Novákovi“, 2 kusy →
  „Chtěl jsem něco jiného“ → karta „Zkusit jinak“ → pole pro e-mail až po stisku), `medal-*` (2. místo: stříbrná
  medaile, „2“ a „Turnaj Stod“, poutko na stužku), `design-*` (logo z teček s řádkem nad ním → návrh v kalkulačce
  s cenou a polem „Chcete na modelu něco změnit?“ → cesta zpět), `off-*` (přání bez nástroje při vypnutém návrhu),
  `limit-*` (vyčerpané volné návrhy bez účtu). **Jak snímky vznikly:** místní databáze nemá tabulku `intakes`
  (migrace se ve worktree nespouští), proto odpovědi `POST /intake`, `GET /intake/{token}` a předání stránce nástroje
  nejdou z místního serveru: zapsal je skutečný kód v běhu PHPUnit s podvrženým asistentem a generátorem
  (`direct_payloads.json`) a skript je stránce podstrčí (`fetch`, `window.MP_TOOL.intake`). Úvodní strana, stránka
  nástroje a její náhled (skutečný `param_tool.py`), generování (falešný generátor: krychle), kalkulačka a cena jsou
  místního serveru. Na všech snímcích bez vodorovného posunu a bez chyb skriptu.
- Místní háček: `php artisan serve` nepředá proměnné prostředí Pythonu a první dotaz na engine po startu vyprší;
  snímky běžely proti `php -S 127.0.0.1:8020 …/server.php` s `PYTHON_BIN` na Python s knihovnami.

### 24.8 Co není ověřené

- **Skutečný model**: nové pravidlo `shape` → volný návrh, pole `prompt_en`, `photo`, `text2`, `number`, `shape` a to,
  jaké jistoty model dává, jsou ověřené jen proti podvržené odpovědi a textu promptu. Po nasazení
  `php artisan matplace:intake-cases` (32 volání ≈ 10 Kč) a první přání v `/admin/intake`.
- **Skutečné generování z textu**: jak dobře Tripo udělá „čelenku s rohy“ nebo „sošku draka“ z anglické věty (a z české
  bez modelu), jak dlouho to trvá a co to opravdu stojí. Kvalita volného návrhu je hlavní neznámá celé cesty.
  Fronta: lokálně a v testech běží synchronně, na produkci přes pracovníky fronty (jako busta).
- **Skutečný tisk** volného návrhu; tisknutelnost hlídá stejná příprava jako u nahraného souboru.
- **Předání fotky** stránce nástroje v prohlížeči (lokálně není tabulka; platí od §22.4) a nástroje mimo cedulku
  a medaili v prohlížeči: hodnoty ostatních jsou ověřené testem proti definici formuláře, ne pohledem. Mapa: hledání
  místa od modelu; busta a mazlíček se nespustí samy (souhlas, placené generování).
- Placená cesta (cena, kredit) je ověřená testem; dokud Roman cenu nenastaví, nikdo ji neuvidí.
- Skutečný telefon (dotyk, pomalá síť, návrat tlačítkem Zpět).

### 24.9 Nasazení a co rozhodne Roman

Nasazení nese: žádnou migraci; `config:cache` (nové `home.intake.design`), `npm run build`. Po nasazení se **chování
změní hned i s vypnutým návrhem**: po „Vytvořit“ se nástroj otevírá sám (karta se dvěma tlačítky zmizí), soubor jde
rovnou do kalkulačky. Volný návrh zůstane vypnutý, dokud ho Roman nezapne v `/admin/intake/design`; na produkci je
generátor Tripo, takže zapnutím začnou placená volání.

- Práh 0,6 (§24.2) po prvních ostrých jistotách.
- Volný návrh zapnout, a s jakou geometrií: `detailed` ≈ 6,9 Kč, `standard` ≈ 2,3 Kč za návrh z textu (dnes platí
  produkční `TRIPO_GEOMETRY_QUALITY`, pro volný návrh se nepřepíná).
- Cena dalšího návrhu po 20 volných (pole v administraci), a zda 10 + 10 má být napořád, nebo se obnovovat (dnes
  napořád; anonymní adresa se počítá 30 dní).
- Díl s rozměry: dnes zůstává u člověka a volný návrh je jen tlačítko s poctivou větou. Má se i u něj generovat
  rovnou?
- Přání bez nástroje při vypnutém / vyčerpaném návrhu ukazuje pole pro e-mail rovnou (záchrana). Nechat, nebo i tam
  schovat za tlačítko?
- Nejistý nástroj při zapnutém návrhu jde na volný návrh a nástroj je až na cestě zpět. U plochých věcí z obrázku
  (přívěsek, podtácek) může být nástroj lepší než 3D generování; po ostré zkoušce případně obrátit.
- Adresa návrhu `/?design=…` (a po výpočtu `/c/<kód>`) funguje komukoli, kdo ji má, jako dnes u každého modelu
  a kalkulace. Rozhodnutí „žádné sdílení odkazem pro nepřihlášené“ by znamenalo vázat generování a soubory na relaci;
  to je změna napříč kalkulačkou, tady není.
- Texty: „Chtěl jsem něco jiného“, „Raději poptávka designérovi“ (jinde na webu „návrhář“), věty `router.design.*`.
- Trychtýř `/admin/stats`: krok „dostal něco“ počítá nově i `intake_designed`.
