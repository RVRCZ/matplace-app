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
