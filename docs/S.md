# S: prodej, plánování (session 4)

Větev `feature/tools-sell` (z `main` 6bdf396, 8. 10. 2026), zadání `docs/prompts/nastroje.md`, část „Session 4 –
Prodej, plánování, obrázky“ (řádek 498). Romanovo zadání přes řídící session: jen to, co nepotřebuje jeho
rozhodnutí – `/tools/cost`, `/tools/profit`, `/tools/plan`, `/tools/vendors`. `/tools/image` (poskytovatel),
`/tools/photo` (rembg) a záložka „popište to“ u figurky počkají; `/tools/listing` nebylo v zadání téhle kola.
Druhé kolo (8. 10. odpoledne, po Romanově rozhodnutí: obrázky Gemini, rembg na serveru): **studio** –
`/tools/image`, `/tools/listing`, `/tools/photo`, část 9 níže.

Vlastní soubory session 4: `App\Domain\Sell\{Cost,Profit,Plan}` (čistá aritmetika, testy počítají s ní),
`SellToolsController`, `config/sell.php` (sazby s datem a zdrojem), `resources/js/site/sell.ts` (zrcadlo
aritmetiky, stránky počítají v prohlížeči), `resources/views/tools/sell.blade.php` + `tools/sell/*`,
`lang/<loc>/sell.php`, `tools_seo/{cost,profit,plan,vendors}.php`, `engines/python/sell_cards.py` (karty),
`tests/Feature/SellToolsTest.php`, model `SellPlan` + migrace `sell_plans`, `VendorsController`,
`Admin\EventController` + `admin/events/*`, modely `MarketEvent` (tabulka `market_events`; `events` už je log
návštěv) a `EventSave`, migrace `market_events_and_saves`, `database/seeders/MarketEventsSeeder.php`. Sdílené soubory
jen přídavky: `config/tools.php`, `routes/web.php`, `lang/<loc>/tools.php`, `resources/js/site/boot.ts` (jeden import
a volání), `resources/views/admin/nav.blade.php` (položka „Akce“), `resources/views/account/_calculation.blade.php`
(odkaz „Kolik by mě to stálo doma“ na `/tools/cost?from=`, jen když trasa existuje – na přání řídící session).
Do `param.*`, `ParametricGenerator`, `shape_kinds` ani do souborů farmy jsem nesahal.

## 1. Co vzniklo

| nástroj | adresa | co dělá |
|---|---|---|
| Náklady tisku | `/tools/cost` | filament, proud, opotřebení, zmetky, práce, ostatní → náklad na kus, cena s marží, výdělek hodiny tisku; `?from=<kalkulace>` předvyplní gramy a hodiny a ukáže „vytisknout u nás stojí X“; přihlášený s profilem tiskárny má svou sazbu a cenu filamentu |
| Zisk prodejce | `/tools/profit` | platforma (Etsy, Fler, Shopify, vlastní e‑shop, trh, farma matplace), DPH, cizí měna, cena, náklad, sleva, doprava účtovaná a skutečná, kusy za měsíc, fixní náklady, stánek → poplatky po položkách, čistý příjem, zisk, marže, bod zvratu, tři ceny vedle sebe; sazby s datem a zdrojem |
| Plán prodeje | `/tools/plan` | produkty (náklad, cena, kusy/měsíc, nafoceno, vystaveno), fixní náklady, kanál, první měsíc, sezóna (presety + vlastní) → 12 měsíců tržby/náklady/zisk/kumulovaně, bod zvratu, SVG graf, tabulka; týdenní seznam kroků z toho, co chybí; localStorage, u účtu `sell_plans` (až 20); CSV v prohlížeči, PDF přes dompdf |
| Kde prodávat | `/tools/vendors` | město + země (CZ/SK), okruh 10–100 km, období 90 dní / rok, druhy akcí → akce z tabulky `market_events` kolem města (geokódování přes `Geocoder`, vzdušná čára), mapa Leaflet/OSM, seznam s termínem, poplatkem za stánek, webem, `.ics`; „hodí se pro vás“ přes asistenta (3 akce + co vyrobit + odkaz na nástroj, denní limit); uložené akce u účtu + společný `.ics`; formulář „navrhnout akci“; stálý seznam kanálů; admin `/admin/events` |

Stránky stojí na vlastním layoutu `tools/sell.blade.php`: levý panel s číslovanými sekcemi a navigací jako
u nástrojů, vpravo výsledek (tabulka, graf, mapa) místo vieweru. Aritmetika je **dvakrát**: v PHP
(`App\Domain\Sell`, testované čísly) a v `sell.ts` (stránka počítá při psaní, nic neposílá na server). Nastavení
stránek přežívá obnovení (localStorage, klíč podle měny); `?cost=&price=` z nákladů do zisku a do plánu.

Měna: vstupy jsou v měně návštěvníka (Kč / € podle přepínače v hlavičce); výchozí hodnoty z configu jsou v Kč
a pro eura se přepočítají kurzem z `config/farm.php`. Poplatky v USD (Etsy inzerát, Shopify plán) jdou přes
`config/sell.php` `rates` (USD 21,5, EUR 25 k 8. 10. 2026).

## 2. Rozhodnutí a proč

1. **Vlastní layout místo `tools/page.blade.php`**: stránka nástrojů je stavěná kolem vieweru, stavového řádku a
   karty s cenou; bez geometrie by z ní zbyl prázdný canvas. `sell.blade.php` bere z ní panel se sekcemi
   (`x-tool-section`, číslování CSS čítačem, navigace) a pravý sloupec nechává stránce. `ToolPageTest` tyhle stránky
   přeskakuje (nejsou v mapě modulů, jako kalkulačka a dárky); katalog, karty, SEO a texty je kontrolují.
2. **Sazby jen v `config/sell.php` s `as_of` a `source`**, test hlídá, že každá platforma má datum, zdroj a název ve
   třech jazycích; stránka ukazuje „sazby k …“. Roman je před nasazením projde (sekce 5). Poznámky k platformám
   jsou v `lang/<loc>/sell.php` (`platform.<key>.note`), ne v configu.
3. **Zmetky násobí jen strojní náklad** (materiál, proud, opotřebení): práce a obal se platí za povedený kus.
4. **Zisk se počítá z ceny po slevě plus účtovaná doprava** (z toho berou platformy provizi), skutečná doprava se
   odečte; DPH plátce se vyjme z celé platby. Měsíční poplatky a stánek se rozpočítají na kusy, které zadáte.
5. **Plán má sezónu jako násobky po měsících** (presety rovnoměrně / Vánoce / léto a trhy / škola, nebo vlastních
   12 čísel); začíná libovolným měsícem; bod zvratu = první měsíc s kladnou kumulací.
6. **Seznam kroků je odvozený, ne psaný**: z chybějícího nákladu, ceny, ceny pod nákladem, nenafoceno, nevystaveno,
   bez kanálu, bez fixních nákladů. Odškrtnuté kroky se ukládají v plánu (`done`), takže přežijí i uložení k účtu.
7. **PDF přes dompdf z blade šablony**, odesílá se skrytým formulářem s JSON (CSRF z layoutu), žádný nový balíček;
   CSV vzniká v prohlížeči.
8. **Karty bez modelu kreslí `sell_cards.py`** (PIL) na stejném béžovém pozadí jako rendery: účtenka, tři sloupce
   ceny, graf, mapa. Test karet kontroluje jen generátory proti studiovému pozadí, tyhle jen rozměr a velikost.
9. **Akce: tři stavy**, `verify` (první dávka ze seederu – 43 akcí CZ/SK podle paměti, termíny jako obvykle v roce),
   `suggested` (formulář návštěvníka, honeypot `website`, limit 10/h) a `verified` (admin). Veřejná stránka ukazuje
   `verified` i `verify` (u těch s poznámkou „termín k ověření“), návrhy ne. Seeder je idempotentní (název + město).
10. **Hledání počítá vzdálenost v PHP** nad všemi veřejnými akcemi v období (desítky až stovky řádků), ne SQL
    s bounding boxem: při tomhle množství je to rychlejší než indexy a nezávislé na SQLite/MySQL.
11. **Mapa je Leaflet z unpkg a dlaždice OpenStreetMap**, načtené jen na téhle stránce (`@push('head')`, `defer`),
    žádný nový npm balíček (sdílené `package.json`); bez skriptu zůstane seznam, mapa je doplněk.
12. **„Hodí se pro vás“ jde přes `Assistant::ask('vendors', …)`** se schématem `{picks: [{id, why, make, tool}]}`;
    nástroj jen z našeho seznamu klíčů (jinak prázdný), cizí id se zahodí, max tři. Denní limit přes cache na
    uživatele nebo IP (`ai.daily_limits.vendors_fit`, výchozí 10, klíč v `config/ai.php` nepřidán – `config()` s
    výchozí hodnotou), admin bez limitu. Popis výrobků i akce jdou do promptu jako data.
13. **`.ics` bez balíčku**: celodenní události (`DTSTART;VALUE=DATE`, `DTEND` o den později), `GEO`, `URL`; jedna
    akce veřejně, všechny uložené po přihlášení.

## 3. Rychlost

Všechny tři stránky počítají v prohlížeči při každém úhozu (desítky mikrosekund); server jen vykreslí stránku.
PDF plánu (dompdf, jedna stránka A4, DejaVu): 0,4–0,6 s lokálně. Hledání akcí: geokódování z cache 0 ms (první
dotaz na nové město jde na Nominatim, do 8 s), výpočet vzdáleností nad 43 akcemi pod 5 ms; asistent 2–6 s.

## 4. Testy

`SellToolsTest` (6): náklad kusu (výchozí hodnoty do haléře, bez zmetků/práce/marže, meze), stránka nákladů ve
třech jazycích + předvyplnění z kalkulace (gramy a hodiny na kus, naše cena) + filtr „Prodej“ v katalogu; zisk
(Etsy po položkách, DPH a cizí měna, Fler, trh se stánkem, Shopify s měsíčním plánem, farma bez poplatků, bod
zvratu včetně „nikdy“, sleva, neznámá platforma → Etsy); stránka zisku s předáním nákladu a ceny + každá platforma
má datum, zdroj a název ve třech jazycích; rok prodeje (měsíce, bod zvratu, kroky a jejich odškrtnutí, Vánoce od
listopadu, vlastní sezóna, limit 20 produktů); stránka plánu, PDF (hlavička, `%PDF`, název souboru), plány účtu
(host 401, uložení, přepsání stejného názvu, cizí plán nejde smazat, limit 20); akce (stránka ve třech jazycích,
seeder 43 akcí se souřadnicemi, druhy a stavy, idempotence; hledání kolem Prahy z geocache bez sítě: 50 km / 90
dní, rok, 100 km s druhem, vzdálenost 0 u Staroměstského, špatný okruh 422, neznámé město 422; `.ics` jedné akce;
asistent s `FakeAssistant` – výběr, cizí id pryč, odkaz na nástroj, denní počet a 429; návrh akce čeká na admina a
není veřejný, honeypot; uložení k účtu a společný `.ics`; admin: seznam, filtr, ověření s geokódováním, úprava,
založení, smazání, nepřihlášený pryč). Související: `SeoTest`,
`ToolsCatalogTest`, `ToolCardsTest`, `ToolsFlowTest` (vlastní předpony `sell_pdf`, `sell_plans`), `ToolPageTest`.

## 5. Co není ověřené (Roman)

- **Sazby v `config/sell.php`** jsou z veřejných ceníků k 8. 10. 2026 podle mé paměti, ne z živého načtení:
  Etsy 0,20 USD + 6,5 % + 4 % + 10 Kč + 2,5 % převod; Fler 11 %; Shopify Basic 39 USD měsíčně + brána 1,4 % + 3 Kč
  + 2 % Shopify; Shoptet 390 Kč + brána 1,3 % + 3 Kč; trh terminál 1 %; kanály (Amazon Handmade 15 %, Aukro 7–9 %,
  komise 20–40 %). Před nasazením porovnat s ceníky v `source` a opravit čísla i `as_of`.
- Kurzy USD 21,5 a EUR 25 (ČNB) – totéž.
- Výchozí náklady (filament 600 Kč/kg, 6,5 Kč/kWh, tiskárna 12 000 Kč na 5 000 h, sazba 250 Kč/h) jsou odhady pro
  Česko 2026; stránka je říká jako výchozí, člověk si je přepíše.
- Stránky jsem viděl jen v headless Chrome (snímky nákladů, zisku, plánu); odškrtávání kroků, uložení k účtu a
  stažení CSV/PDF jsem proklikal jen testy, ne myší.
- Odkaz z kalkulace na `/tools/cost?from=` je v seznamu uložených kalkulací v účtu (`account/_calculation`);
  do samotné stránky kalkulace (JS, `calculator.ts` session 0) jsem nesahal.
- **Akce v `MarketEventsSeeder`**: 43 trhů a akcí CZ/SK s termíny „jako obvykle“ (vánoční trhy 27. 11.–23. 12.
  2026, Maker Faire Brno 17.–18. 10. 2026, Comic-Con Prague duben 2027…) a odkazy podle paměti, ne z živých webů;
  souřadnice jsou středy měst, ne adresy. Všechny mají stav „k ověření“; Roman je projde v `/admin/events` (tlačítko
  „ověřit“ doplní souřadnice, když chybí). Poplatky za stánek jsou většinou „podle stánku“ – doplnit z webů.
- Geokódování nového města jde na Nominatim (OpenStreetMap) s limitem 1 dotaz/s; při výpadku stránka řekne, že
  město nenašla. V testech je geocache naplněná ručně, síť se nevolá.
- Asistenta jsem zkoušel jen s `FakeAssistant`; s Claude ověřit, že vrací `tool` jen z nabídnutých klíčů (schéma to
  vynucuje enumem) a smysluplné „co vyrobit“.
- Mapu jsem viděl v headless Chrome (dlaždice se načetly), piny a kruh okruhu po hledání jen v kódu.

## 6. Nasazení (Roman)

Migrace `2026_10_15_100000_sell_plans` (tabulka `sell_plans`) a `2026_10_15_100100_market_events_and_saves`
(`market_events`, `event_saves`). Po migraci **`php artisan db:seed --class=MarketEventsSeeder`** (43 akcí ke
schválení; lze pouštět opakovaně). Nic nového v `.env`, žádný nový balíček (dompdf už je, PIL jen pro karty
v repozitáři, Leaflet z CDN). `npm run build` (od tohoto kola s `tsc --noEmit` napřed), `php artisan migrate`,
`view:clear`. Karty `public/img/tools/{cost,profit,plan,vendors}-*` jsou v repozitáři. Volitelně do `config/ai.php`
`daily_limits.vendors_fit` (výchozí 10 v kódu).

## 7. Co zbývá

Z obou kol nic rozpracovaného. Dál podle Romana: záložka „popište to“ u figurky (3D model z popisu; obrázek z popisu
teď umí `/tools/image`, takže cesta popis → obrázek → `/tools/figure` je o jeden krok), `/tools/flexi` jen na jeho
slovo, a to, co je v části 9.4.

## 8. Stav

8. 10. 2026 dopoledne: `/tools/cost`, `/tools/profit`, `/tools/plan` hotové (d94527a). Poledne: `/tools/vendors`
s adminem, seederem a `.ics`; `SellToolsTest` 7 testů; testy stránek, katalogu, karet, SEO, toku a adminu zelené;
build s tsc čistý. Odpoledne: studio (`/tools/image`, `/tools/listing`, `/tools/photo`), `StudioToolsTest` 6 testů,
dávka SEO + stránky + tok + prodej + obrázky zelená, build s tsc čistý; jeden skutečný obrázek z Gemini (pro model,
13,8 s, 1 337 tokenů, zaúčtováno 1,84 Kč) – kočka z profilu, po prahování čistá silueta.

## 9. Studio: obrázek z popisu, texty inzerátu, produktová fotka (druhé kolo)

Vlastní soubory: `App\Engines\Image\{ImageGenerator,ImageResult,GeminiImageGenerator,FakeImageGenerator}`,
`App\Engines\Photo\{BackgroundRemover,RembgBackgroundRemover,FakeBackgroundRemover,NoBackgroundRemover}`,
`App\Domain\Tools\{ImageMaker,PhotoCut}`, `StudioToolsController`, `resources/js/site/studio.ts` (volá se ze
`sell.ts` podle `data-sell`), `tools/sell/{image,listing,photo}.blade.php`, `engines/python/photo_cut.py` (rembg),
`engines/python/photo_backgrounds.py` → `public/img/backgrounds/*.jpg`, `tools_seo/{image,listing,photo}.php` ×3,
karty `public/img/tools/{image,listing,photo}-*` (ze `sell_cards.py`), `tests/Feature/StudioToolsTest.php`.
Přídavky do sdílených: `config/ai.php` (blok `gemini`, limity, cena `gemini-3-pro-image`), `config/engines.php`
(`image`, `photo`, `photo_home`), `EngineServiceProvider` (dvě vazby), `phpunit.xml` (`ENGINE_IMAGE=fake`,
`ENGINE_PHOTO=fake`), `PruneData` (jeden řádek: `PhotoCut::prune`), `config/tools.php`, `routes/web.php`,
`lang/<loc>/{sell,tools}.php`.

### 9.1 `/tools/image` – obrázek z popisu

- **Motor**: `ImageGenerator::fromText(prompt, style, size)` → `ImageResult` (bajty, mime, model, tokeny, ms).
  `GeminiImageGenerator`: v1beta `models/{model}:generateContent`, hlavička `x-goog-api-key`, tělo
  `contents[{parts[{text}]}]` + `generationConfig.responseModalities ["IMAGE"]`, čte `candidates[0].content.parts[]`
  `inlineData{mimeType,data}`; chyba HTTP → `EngineException` se stavem a prvními 200 znaky zprávy, v níž je
  cokoli ve tvaru `AQ.…` nahrazeno tečkami (klíč se nikdy nedostane do logu ani do odpovědi). Výchozí model
  **`gemini-3-pro-image`** (Romanovo rozhodnutí 8. 10.), `cheap_model` `gemini-3.1-flash-lite-image` se zapne
  `GEMINI_IMAGE_CHEAP=true`. Účtování `AiUsage::record('image', model, …)`; `prices.models` má
  `gemini-3-pro-image` 0,08 USD za volání (nový řádek před obecným `gemini` 0,04, protože se hledá předponou).
  `FakeImageGenerator` kreslí GD kočku (silueta/linka/barvy) a popis do rohu; `$fail` shodí další volání.
- **Prompt** (`GeminiImageGenerator::prompt`): za styl doplní, co nástroje chtějí – silueta „one flat black shape
  on pure white, no grey, no outline, no text, no frame, suitable for cutting out“, linka „uniform thick black
  lines“, barvy „at most six flat solid colours, no gradients“; tvar čtverec / 4:3 / 3:4. Skutečný výstup pro
  modelu: čistá kočka, viz 8.
- **Po modelu** (`ImageMaker::prepare`): silueta a linka → šedotón, práh 128, čistě černá/bílá PNG (modely nechávají
  šedé okraje a slabé pozadí; nástroje chtějí tvrdý obrys); barvy → JPEG 90 beze změn; delší strana nejvýš
  1 600 px. Výsledek jde do **„mých obrázků“** (`Artwork::store` s vlastníkem účet/anonymní session), takže ho
  každý nástroj s obrázkem najde v okně obrázku pod záložkou „moje obrázky“ (30 dní). Do `param.ts` (session 1)
  jsem nesahal: přímé `?artwork=` by vyžadovalo jeho úpravu – stránka místo toho ukazuje tlačítka nástrojů
  (`ParametricGenerator::ARTWORK` + compose, filament_art, colors) a větu, kde obrázek najít. Stažení PNG; obrázky
  z téhle návštěvy v pásu pod výsledkem.
- **Limity** (`config('ai.daily_limits.*')`, cache do půlnoci): `image_guest` **2** bez účtu (IP), `image_user`
  **10** s účtem, `image_global` **150** pro celý web (pro model: ≈ 12 USD/den strop; limit se dá změnit přes
  `AI_LIMIT_IMAGE_*`); admin bez limitu; neúspěšné volání se nepočítá (počítá se až po úspěchu – model
  neúčtuje nic, když nevrátí obrázek, resp. účtuje tokeny promptu, zanedbatelné). Odpovědi 429 `daily_limit` /
  `site_limit`, 503 bez klíče (stránka pak ukazuje `note-warn`), 502 když model nevrátí obrázek (např.
  `IMAGE_SAFETY`).
- Trasy `GET /tools/image`, `POST /api/tools/image` (throttle `studio_image` 10/min).

### 9.2 `/tools/listing` – texty inzerátu

- `Assistant::ask('listing', …)` se schématem `{title, description, tags[], materials[], keywords[], alt}`; systémový
  prompt podle platformy (Etsy: název ≤ 140, 13 štítků ≤ 20 znaků, první věty popisu; Fler: název ≤ 60, 10–15
  štítků; vlastní e‑shop: název ≤ 70 jako titulek, klíčová slova jako meta popis; stránka modelu: co, k čemu,
  velikost tisku), jazyk cs/en/es, tón věcný/osobní/hravý; zákaz vymýšlení rozměrů a materiálů, bez emoji.
  Odpověď se **ořeže na limity platformy** (`platformLimits`) i kdyby model přetekl.
- Fotka (JPG/PNG/WebP ≤ 12 MB) jde modelu jako cesta (`ClaudeAssistant` ji převádí na base64), uložená jen do
  `storage/app/tmp/listing/` a smazaná ve `finally`. `?model=<slug>` předvyplní název a popis z `CatalogModel`
  (odkaz z katalogu jsem nepřidával – soubor katalogu není můj; stačí `route('tools.listing', ['model' => slug])`).
- Limity `listing` **5**/den na návštěvníka, `listing_global` **200**; stejný vzor jako `vendors_fit` (ten jsem
  přidal do `config/ai.php` jako výchozí 10). Trasy `GET /tools/listing`, `POST /api/tools/listing`
  (`studio_listing` 10/min). Kopírování po částech i celé (vlastní handler ve `studio.ts`, ne `[data-copy]` z
  `designer.ts`, aby se text skládal až po odpovědi).

### 9.3 `/tools/photo` – produktová fotka bez pozadí

- **Motor** `BackgroundRemover::cut(src, dst)` → `{width, height, coverage}`. `RembgBackgroundRemover` spouští
  `engines/python/photo_cut.py` (rembg, `new_session("u2net")`, `post_process_mask`, ořez na obsah + 2 % okraj,
  RGBA PNG; HEIC přes pillow‑heif, je‑li) s **`U2NET_HOME`** z `config('engines.photo_home')`
  (`/opt/matplace-py/u2net`, přes `Process::env`, jak chtěla řídící session); `available()` = `photo_cut.py
  --probe` (import rembg + onnxruntime) **cachované hodinu** (`Cache`), takže bez rembg (lokálně) stránka ukáže
  `note-warn` a API 503, bez pádu. `ENGINE_PHOTO` výchozí `rembg`, `fake` v testech (elipsa uprostřed zůstane,
  okolí průhledné, ≤ 400 px), `none` vypne.
- Výřez leží v `storage/app/tmp/photo/<uuid>.png` **jeden den** (`PhotoCut::prune` z `matplace:prune`), id je
  neuhodnutelné, `GET /api/tools/photo/{id}`. **Skládání a ukládání je v prohlížeči** (`studio.ts`, canvas):
  pozadí bílé / světlý přechod / dřevo / mramor / beton / papír / plátno / průhledné, stín jako měkká elipsa
  (radiální gradient, žádný `ctx.filter`, funguje všude), velikost věci 50–98 %, na střed / k zemi, výstup
  1000/1500/2000 px čtverec, JPG 0,92 nebo PNG; až 6 fotek, fronta po jedné, stažení jedné nebo všech. Nic
  složeného se neukládá na server.
- **Pozadí** `public/img/backgrounds/{wood,marble,concrete,paper,linen}.jpg` jsou **kreslená** PIL skriptem
  `photo_backgrounds.py` (šum, rozmazání, tónování), 1 200 px, 20–105 kB; žádná licence cizích fotek. Mramor je
  „mramorovitý“, ne fotorealistický – když bude Roman chtít fotky, stačí soubory vyměnit.
- Trasy `GET /tools/photo`, `POST /api/tools/photo` (`studio_photo` 12/min, 12 MB, jpg/png/webp/heic),
  `GET /api/tools/photo/{id}`.

### 9.4 Co není ověřené (Roman)

- **Gemini pro model**: jeden skutečný obrázek (silueta kočky, 13,8 s). Linku a barvy jsem s modelem nezkoušel
  (jen prompt a Fake); barevný obrázek pro `filament_art` chce ploché barvy – pokud model dává přechody, zkusit
  `cheap_model`, nebo do `prepare` přidat kvantizaci barev (`imagetruecolortopalette`), to je pár řádků.
- Model `gemini-3-pro-image` trvá ~14 s; stránka ukazuje „Kreslím…“, timeout 120 s. Limity 2/10/150 jsou můj
  návrh (150 × 0,08 USD = 12 USD/den strop); zapsat do `.env`, pokud má být jinak.
- **rembg**: lokálně není, `photo_cut.py` jsem pustil jen s chybějícím rembg (správný JSON `rembg_missing`) a
  `--probe`; na serveru ověřit `php artisan tinker --execute='dump(app(\App\Engines\Photo\BackgroundRemover::class)->available());'`
  (po `cache:clear`, probe se drží hodinu) a jednu fotku přes stránku. Kvalita u2net na lesklých a průhledných
  dílech je, jaká je – stránka to říká v SEO textu.
- **Stránky** viděné jen v headless Chrome (snímky: obrázek, inzerát, fotka – formuláře a prázdné stavy); tvorbu
  obrázku, psaní inzerátu a skládání fotky jsem proklikal testy a jedním skutečným voláním Gemini z tinkeru, ne
  myší v prohlížeči. `studio.ts` prošlo `tsc` a buildem.
- **Katalog**: `image` je v kategoriích „obrázky“ a „prodej“, `listing` a `photo` v „prodej“. Nabídky „použít
  v nástroji“ berou všechny nástroje s obrázkem (`ParametricGenerator::ARTWORK`) – 25 tlačítek, možná moc; snadno
  se zkrátí na seznam v `StudioToolsController::pictureTools`.
- **Nasazení** (k části 6 navíc): do `.env` `GEMINI_API_KEY` (už je), volitelně `ENGINE_PHOTO=rembg` (výchozí),
  `U2NET_HOME=/opt/matplace-py/u2net` (výchozí v configu), pak `config:cache`, `cache:clear` (probe), `view:clear`,
  `npm run build`. **Žádná nová migrace** (studio nemá tabulky; `AiCall` je stávající). Nový PHP balíček žádný;
  Python na serveru: rembg + onnxruntime + pillow‑heif (volitelné) v `/opt/matplace-py` – podle řídící session už je.
