# S: prodej, plánování (session 4)

Větev `feature/tools-sell` (z `main` 6bdf396, 8. 10. 2026), zadání `docs/prompts/nastroje.md`, část „Session 4 –
Prodej, plánování, obrázky“ (řádek 498). Romanovo zadání přes řídící session: jen to, co nepotřebuje jeho
rozhodnutí – `/tools/cost`, `/tools/profit`, `/tools/plan`, `/tools/vendors`. `/tools/image` (poskytovatel),
`/tools/photo` (rembg) a záložka „popište to“ u figurky počkají; `/tools/listing` nebylo v zadání téhle kola.

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

Z tohoto kola nic. Dál podle Romana: `/tools/listing` (text inzerátu přes asistenta, denní limit), `/tools/image`
(motor `ImageGenerator` + Fake, poskytovatel), `/tools/photo` (rembg), záložka „popište to“ u figurky.

## 8. Stav

8. 10. 2026 dopoledne: `/tools/cost`, `/tools/profit`, `/tools/plan` hotové (d94527a). Poledne: `/tools/vendors`
s adminem, seederem a `.ics`; `SellToolsTest` 7 testů; testy stránek, katalogu, karet, SEO, toku a adminu zelené;
build s tsc čistý.
