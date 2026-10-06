# Zadání: všechny nástroje ze stlbuddy.com v matplace (70 nástrojů, čtyři session)

Datum: 6. 10. 2026. Předloha: `https://stlbuddy.com/tools` (70 nástrojů k 6. 10. 2026 večer – ten den přibyl Photo to Papel Picado;
seznam roste, při startu každé session ho znovu porovnej s tabulkou níže; placené členství 10–32 $/měs.). Roman chce
**všechny** v matplace – zdarma, bez kreditů a členství (jediná výjimka jsou denní limity AI generování, které
už máme), a každý nástroj končí tam, kde končí ty naše: **cena → výtisk z farmy / stažení STL / projekt pro
vlastní tiskárnu.** Jejich stránky nástrojů jsou JavaScript za přihlášením, takže z webu je vidět jen název a
jedna věta; Roman ale poslal **screenshoty dvanácti předloh zevnitř** (`docs/img/stlbuddy-*.png`: modular,
coaster, lithophane, earrings, potion, cookie, marble, vendors, flexi, magnet, topper a `layered-art` = video
hotového vrstveného obrazu) – prohlédni si všechny, než začneš, je z nich vidět jejich úroveň i jejich chyby.
Nekopíruj jejich rozhraní, vezmi myšlenku a postav ji po našem a **lépe** (sekce „Lepší než předloha“ níže).

Práce je na **čtyři session**, každá ve vlastním worktree z `C:\matplace-app` (Laravel 12), větev z `main`
(`main` je nasazený na matplace.com; ověř `git log origin/main -1`, co je živé):

| session | větev | worktree | dokument |
|---|---|---|---|
| 0 Základ: stránka, viewer, barvy, knihovna, vzhled | `feature/tools-base` | `C:\matplace-base-wt` | `docs/N.md` – zadání `prompt_nastroje_0.md` |
| 1 Tvar z obrázku a jména | `feature/tools-shapes` | `C:\matplace-shapes-wt` | `docs/P.md` |
| 2 Díly, obaly, hračky | `feature/tools-parts` | `C:\matplace-parts-wt` | `docs/Q.md` |
| 3 Úprava modelu | `feature/tools-edit` | `C:\matplace-edit-wt` | `docs/R.md` |
| 4 Prodej, plánování, obrázky | `feature/tools-sell` | `C:\matplace-sell-wt` | `docs/S.md` |

(`docs/O.md` je obsazené návštěvností.) **Session 0 jde první a sama** (společná stránka nástroje, viewer,
paleta barev farmy, knihovna obrázků, vzhled – zadání `docs/prompts/nastroje-0.md`); po ní session 1 a 3 mohou
běžet souběžně (jiné soubory); session 2 až po
sloučení session 1 (sdílí `ParametricGenerator`, `config/tools.php` a stránku nástroje); session 4 kdykoli.
Každá session nejdřív slije aktuální `main` a na konci znovu, než předá.

Pravidla jako vždy: nikdy `git add -A`, nikdy `git stash` (stash je společný s jinými session); testy
`php -d memory_limit=2G vendor/bin/phpunit` (celá sada ~16 min, pouštěj na pozadí a čti log; v testech
`ENGINE_SLICER=fake`); `vendor/bin/pint --dirty`; po změně TS `npm run build`. Nové texty **jen do skupinových
souborů** `lang/{cs,en,es}/<skupina>.php` (`param.php` pro popisky polí a varování, nový `tools.php` pro názvy a
nápovědy nástrojů, `tools_seo/<nástroj>.php` pro stránku), ne do sdílených `lang/*.json`, které upravují jiné
session. Vždy všechny tři jazyky. Na server se nic nenasazuje – nasazení dělá Roman podle `docs/DEPLOY-BETA.md`;
každý dokument končí sekcí „Nasazení (Roman)“ včetně nových balíčků v `/opt/matplace-py` a nových klíčů `.env`.

## Co už máme (22 nástrojů, `config/tools.php`)

Cena ze souboru (`calc`), oprava (`repair`), kontrola (`check`), forma na odlévání (`mold`), organizér, modulární
organizér z misek, krabička, stojánek na telefon, držák na cokoliv, víčko/zátka/krytka, držák kabelů, váza a
květináč, busta/figurka z fotky (`figure`, Tripo), reliéf a litofanie (`relief`), dárky se jménem (`gifts`,
rozcestník), cedulka/jmenovka/klíčenka (`sign`), QR cedulka, logo do 3D, vykrajovátko, razítko, šablona,
světelný nápis. Tím je z 70 předloh **osm hotových** (lithophane, storage tray, storage drawer, modular storage,
mold, cookie cutter, 3D print cost, image to STL) a dalších pět je rozšíření existujícího nástroje.

## Jak se u nás nástroj staví (mapa kódu – přečti si ho, tohle je jen orientace)

- **Katalog**: `config/tools.php` (`route`, `intent` file|create|spare, `categories`, `available`, `seo.examples`);
  stránka `/tools` (`resources/views/tools/index.blade.php`, filtry podle kategorií); test `ToolsCatalogTest`,
  `SeoTest`. Nástroj je v katalogu **jen když opravdu funguje** – žádné karty „připravujeme“.
- **Parametrický nástroj** = jeden „kind“: konstanty v `app/Domain/Tools/ParametricGenerator.php` (`FIELDS`
  min/max/výchozí/krok, `CHOICES`, `TEXTS`, `FLAGS`, `FLAGS_ON`, `WHEN` podmíněné zobrazení, `MAIN` hlavní pole,
  `PRESETS`, `ARTWORK` = nástroj bere obrázek/SVG, `PARTS` jména dílů) + builder v Pythonu
  (`engines/python/param_tool.py` pro užitkové, `creative_kinds.py` `BUILDERS` pro tvořivé; manifold3d, přesné
  mm, Z nahoru, výsledek `parts` = slovník dílů a `notes` s varováními `notes.warnings` → `param.warn.*`).
  Text, SVG a rastr jdou vždy přes `engines/python/shape2d.py` (`load`, `text`, `svg`, `raster`, `printability`,
  limit 60 000 bodů, rastr max 360 px na delší stranu). Stránka je společná `tools/param.blade.php` +
  `resources/js/calc/param.ts` (náhled `POST /api/tools/param/preview` – vrací STL, díly se vybarví ve vieweru;
  `POST /api/tools/param` založí `ModelFile` → kalkulace, farma, stažení, projekt Orca/Prusa 3MF s barvami přes
  `app/Engines/Project/*`, `ColorChange.php` = výměna filamentu ve výšce). Trasa:
  `Route::get('/tools/<slug>', [ToolsController::class, 'param'])->defaults('kind', '<kind>')`.
  Vzor s obrázkem i textem, dvěma barvami a razítkem navíc: `cutter` (`creative_kinds.cutter`, `tools_seo/cutter.php`,
  `CreativeToolsTest`). Vzor s mřížkou dílů: `modular` (díly `bin_<x>x<y>`, `tray`).
- **Nástroj „mám soubor“** (forma, oprava, kontrola): nahrání nebo `?from=<uuid>` z kalkulačky, Python přes
  `App\Engines\Repair\PythonTool::runScript('<tool>.py', [...], timeout)`, výsledek jako nový `ModelFile`
  (`kind`), stránka s viewerem a výsledkem (`tools/mold.blade.php`, `calc/mold.ts`, `MoldGenerator`,
  `mold_tool.py` s režimem `analyse` a stavbou). Dlouhé operace patří do fronty jako job s fázemi a časy
  (vzor `PrepareFarmOrder`, `calculations.timings` z `docs/M.md`), stránka se ptá na stav.
- **AI**: `App\Engines\Generator` (Tripo: `fromImage`, `fromText` – text už je v motoru, jen nemá stránku),
  `App\Engines\Ai\Assistant::ask(kind, system, user, schema, images)` (Claude, strukturovaná odpověď),
  denní limity `config/ai.php` (`daily_limits`), admin bez limitů.
- **Python na serveru** (`/opt/matplace-py`, `deploy/server-setup.sh`): trimesh, numpy, scipy, networkx,
  pymeshfix, pillow, manifold3d, lxml, svgelements, segno, fonttools, scikit-image; cadquery/OCP pro STEP.
  Fonty: DejaVu (z dompdf) + `engines/fonts/` (Pacifico, NotoEmoji, OFL). Každé volání = nový proces Pythonu
  (~0,7 s import), server má 4 vCPU – náhled parametrického nástroje musí být do ~2 s, těžší věci do fronty.
- **SEO stránka nástroje**: `lang/<loc>/tools_seo/<tool>.php` (`title`, `description`, `h1`, `intro[]`,
  `steps[]`, `faq[]`, `examples[]`) ve třech jazycích + tři ukázky v `config/tools.php` vykreslené
  `php artisan matplace:tool-examples` do `public/img/tool-examples/<tool>-<n>.png` (lokálně potřebuješ Python
  s balíčky ze `server-setup.sh`: `pip install trimesh numpy scipy pillow manifold3d svgelements segno fonttools scikit-image`).
- Tisková farma má podložku **250 × 250 × 250 mm** (Kobra S1) a vícebarevný tisk (ACE): díly po barvách mají smysl.

## Mapa 70 nástrojů → matplace

Názvy u nás jsou **obecné a české** (Bogg Bag, Bath & Body Works, Stanley, koozie, badge reel, Etsy jsou americké
značky a zvyky): značka smí být nanejvýš v názvu předvolby („pro tašku Bogg“, „svíčka Bath & Body Works 3 knoty“)
a ve FAQ jako „kompatibilní s“. Sloupec „co“: **máme** = beze změny · **preset** = jen předvolba/volba u
existujícího nástroje · **rozšířit** = úprava existujícího · **nový** = nový kind/stránka · **?** = rozhodnutí Romana.

| # | stlbuddy | u nás (trasa / kind) | co | session |
|---|---|---|---|---|
| 1 | Lithophane Maker | `relief`: 9 tvarů, lampa, úpravy fotky, náhled s podsvícením | rozšířit | 3 |
| 2 | Business Planner | `/tools/plan` plán prodeje + týdenní plán kroků | nový | 4 |
| 3 | Bogg Bag Charm Builder | `shape` produkt `bag_charm` (přívěsek s čepem) | nový | 1 |
| 4 | Modular Storage Builder | `modular` máme (miska s přihrádkami); stavebnice modulů s rybinami a šuplíky = kind `drawers` | nový | 2 |
| 5 | Make It a Potion | `/tools/potion` z STL (dutá láhev z modelu) | nový | 3 |
| 6 | Coaster Maker | `shape` produkt `coaster` (fotka v barvách filamentů) | nový | 1 |
| 7 | Christmas Ornament Maker | `shape` produkt `ornament` | nový | 1 |
| 8 | Coffee Sleeve Maker | `sleeve` preset `cup` + stěna z **propojeného písma** (jména, citáty) | nový | 2 |
| 9 | Earring Maker | `shape` produkt `earrings` (pár, očko tažením po obrysu) | nový | 1 |
| 10 | Image to Sticky Note Holder | `shape` produkt `notes` (deska + miska 78 mm) | nový | 1 |
| 11 | Sports Spirit Bell Maker | kind `bell` (kužel / míč / helma / silueta obrázku, logo, srdce) | nový | 2 |
| 12 | Gingerbread Name Ornament Factory | `sign` tvar `gingerbread` + poleva (2 barvy) | rozšířit | 1 |
| 13 | Potion Set Builder | kind `bottle` (25 tvarů, šroubovací zátka, pohár, kotlík, sada) | nový | 2 |
| 14 | Image to Hair Tie Holder | `shape` produkt `hair_tie` (deska s ramenem) | nový | 1 |
| 15 | Image Creator | `/tools/image` obrázek z popisu + 2D skládání (tvary, text, grafika) → SVG | nový ? | 4 |
| 16 | Image to Badge Reel | `shape` produkt `badge` (jmenovka na klip) | nový | 1 |
| 17 | Tic Tac Toe Builder | kind `ttt` (3×3 až 9×9, figurky klasické nebo z fotky) | nový | 2 |
| 18 | Image to Straw Topper | `shape` produkt `straw` (klip na brčko) | nový | 1 |
| 19 | Cookie Decorator | kind `cookie`: 40 tvarů sušenek + poleva kreslená „sáčkem“ v barvách | nový | 1 |
| 20 | Image to Cookie | kind `cookie` z vlastního obrázku (tvar i poleva z kresby) | nový | 1 |
| 21 | Bath & Body Works Candle Holder | `shape` produkt `candle_stand` (silueta z fotky + plošina s lemem na kuželovém čepu) | nový | 1 |
| 22 | Nameplate Maker (48 designů) | `sign` tvary destičky (15+) a motivy | rozšířit | 1 |
| 23 | Bathroom Organizer | `shape` produkt `organizer` (přihrádky podle vyfocených předmětů) + `organizer` preset `bathroom` | nový+preset | 1, 2 |
| 24 | Color Splitter | `/tools/colors` barvený 3MF → díly po barvách | nový | 3 |
| 25 | MyFit (brnění na tělo) | `wearable` „podle míry“; tělový model ne | ? | 3 |
| 26 | Hype Chain Factory | `shape` produkt `medallion` + články řetězu | nový | 1 |
| 27 | Name Organizer | kind `name_cup` (jméno jako stojánek na tužky) | nový | 1 |
| 28 | Letter & Character Clickers | `shape` produkt `clicker` s písmeny | nový ? | 1 |
| 29 | Make It A Puzzle | `/tools/puzzle` (puzzle zámky, nebo skryté kolíky) | nový | 3 |
| 30 | Photo to Charm | `shape` produkt `charm` | nový | 1 |
| 31 | Filament Art Factory | `/tools/filament-art` obrázek ve vrstvách barev + **vrstvený obraz v rámu** (video) | nový | 3 |
| 32 | Photo to Trinket Tray (dřív Catch Tray) | `shape` produkt `tray` (barevný obrázek ve dně) | nový | 1 |
| 33 | Cake Topper | skladba vrstev (`compose`) produkt `topper`: šablony, texty, motivy, zápich | nový | 1 |
| 34 | Etsy Profit Calculator | `/tools/profit` zisk prodejce (Etsy, Fler, e‑shop…) | nový | 4 |
| 35 | 3D Print Cost Calculator | `calc` máme; `/tools/cost` náklady vlastní tiskárny | máme+nový | 4 |
| 36 | Magnet Maker | `shape` produkt `magnet` (barvy po výškách, kapsa na magnet s presety) | nový | 1 |
| 37 | Flexi Cutter | `/tools/flexi-cut` klouby do modelu | nový | 3 |
| 38 | Ice Cream Pint Maker | **z STL**: `/tools/holder-from-model` dutina na kelímek v modelu; bez modelu `sleeve` preset `pint` | nový | 3 (+2) |
| 39 | Koozie Maker | **z STL**: `/tools/holder-from-model` dutina na plechovku (330, slim, 500) v modelu; bez modelu `sleeve` preset `can` | nový | 3 (+2) |
| 40 | Bath & Body Works Soap Holder | kind `soap` (mýdlenka; z obrázku i z STL) | nový | 2 (+3) |
| 41 | Sliding Fidget Maker | `/tools/slider` posuvný fidget do plochého modelu | nový, volitelné | 3 |
| 42 | SVG to STL | `logo` režim `extrude` (bez destičky) + vlastní karta | rozšířit | 1 |
| 43 | Bracket Maker | kind `bracket` (L, U, Z, konzola) | nový | 2 |
| 44 | Bolt Factory | kind `bolt` (šroub, matice, podložka; závit z `cap`) | nový | 2 |
| 45 | Storage Tray Generator | `organizer` | máme | – |
| 46 | Image to Nail Saver | `shape` produkt `opener` (otvírák plechovek) | nový | 1 |
| 47 | Image to Clicker | `shape` produkt `clicker` | nový ? | 1 |
| 48 | Name Maker | kind `name_letter` (velké písmeno se jménem) | nový | 1 |
| 49 | Keychain Maker | `sign` preset `keyring` + motivy, poloha otvoru | rozšířit | 1 |
| 50 | Text Maker | `sign` styl `name`: 3 řádky, stojící s podstavcem | rozšířit | 1 |
| 51 | Sports Turtles | hotový model želvy – nemáme; viz rozhodnutí | ? | 2 |
| 52 | Storage Drawer Organizer | `organizer` preset `drawer` | máme | – |
| 53 | Etsy Listing Generator | `/tools/listing` text inzerátu (Assistant) | nový | 4 |
| 54 | Evil Box | kind `evilbox` (dárková krabička se skrytou západkou) | nový | 2 |
| 55 | Marble Machine | kind `marble` (stavebnice ověřených dílů; jejich generátor trasy spadl) | nový, poslední | 2 |
| 56 | Flexi Maker | kind `flexi` (roztomilé zvíře z primitiv: hlava, oči, doplňky, články, ocas) | nový | 3 |
| 57 | Mandalorian Forge | ne (cizí IP); obecné „přilba na míru“ = `wearable` | ? | 3 |
| 58 | Photo to Organizer | `shape` produkt `organizer`: předměty vyfocené na A4 → přihrádky podle nich | nový | 1 |
| 59 | Mold Maker | `mold` | máme | – |
| 60 | Listing Photo Enhancer | `/tools/photo` foto produktu bez pozadí | nový ? | 4 |
| 61 | Make It Wearable | `/tools/wearable` dutý, průzory, díly, na míru | nový | 3 |
| 62 | Make It Life Size | `/tools/life-size` měřítko + dutý + díly | nový | 3 |
| 63 | Clicker Factory | `shape` produkt `clicker` | nový ? | 1 |
| 64 | Image to STL | `figure` máme; + záložka „popište to“ (`fromText`) | rozšířit | 4 |
| 65 | Cookie Cutter Factory | `cutter` | máme | – |
| 66 | Dice Generator | kind `dice` | nový | 2 |
| 67 | Letter Bead Factory | kind `beads` | nový | 1 |
| 68 | Model Splitter | `/tools/split` | nový | 3 |
| 69 | Vendor Opportunity Finder | `/tools/vendors` trhy a akce v okolí na mapě (CZ/SK) + co na ně vyrobit | nový | 4 |
| 70 | Photo to Papel Picado (nový 6. 10.) | `shape` produkt `papel` (prolamovaný panel z portrétu s květinovým okrajem) | nový | 1 |

## Lepší než předloha

Roman: „musíme to udělat lépe.“ Co mají oni (ze screenshotů): kroky vlevo (Obrázek → Rozměry → Barvy → Tisk),
nahrání nebo knihovna obrázků nebo „vytvořit“ u každého nástroje s obrázkem, fotka rozložená do N barev
s odstraněním pozadí a vyhlazením, očko tažením po obrysu, 25 tvarů lahví se šroubovací zátkou, 9 tvarů
litofanie s náhledem „podsvícené / povrch“, poleva kreslená sáčkem na sušenku, „moje kusy“ u sad, stavový
řádek s rozměry a počtem dílů, pohledy 3D / shora / ze strany / zespodu, stažení „barevný 3MF“ + STL, uložení
v prohlížeči. **To je laťka, ne cíl.** Lépe u nás znamená, u každého nástroje:

1. **Barvy, které opravdu máme.** Obrázek se rozloží do barev **filamentů farmy** (ne libovolných RGB), náhled
   ukazuje skutečné vzorníky a výtisk na ACE vypadá jako náhled. U nich si člověk stáhne 3MF a doufá.
2. **Konec je výtisk, ne soubor.** Cena vedle náhledu, „Vytisknout u nás“, projekt pro Orcu/Prusu s barvami ve
   slotech, STL po dílech, návod na sestavení. Zdarma, bez kreditů a členství.
3. **Rychlejší náhled** (do 1 s u parametrických nástrojů – změř a napiš do docs) a úpravy přímo ve 3D: úchyty
   rozměrů, očko tažením po obrysu, poleva kreslená na sušence, roviny řezu posuvníky.
4. **Řekne dopředu, co se stane při tisku**: tenké čáry, přesahy, co se nevejde na podložku, kolik odstínů má
   litofanie při vrstvě 0,2, že závit pod M6 nevyjde – srozumitelně, dřív než člověk klikne na tisk.
5. **Tři jazyky, koruny a eura**, SEO stránka s vykreslenými ukázkami u každého nástroje.
6. **Knihovna siluet (session 0) a obrázek z popisu (session 4)** v každém nástroji s obrázkem, výsledek
   jednoho nástroje jde poslat do druhého („použít v…“).
7. **Jen co je ověřené tiskem.** Jejich kuličková dráha spadla hned při první zkoušce („The tool could not
   finish processing“) a nese štítek „Physical testing pending“. U nás je nástroj v katalogu, až když prošel od
   vstupu po výtisk, a mechanické věci nesou štítek „ověřeno tiskem <datum>“ (`config/tools.php` `verified`,
   vidět na kartě i na stránce nástroje).

## Session 1 – Tvar z obrázku a jména (`feature/tools-shapes`, `docs/P.md`)

Katalog, společnou stránku nástroje s viewerem, paletu barev farmy a knihovnu obrázků postavila **session 0**
(`docs/prompts/nastroje-0.md`, `docs/N.md`); odrážky níže jsou její výstup, který tu používáš – nestav je
znovu, jen doplň, co pro nové nástroje chybí:

- Kategorie v `config/tools.php` nově: `images` (Obrázky a loga), `names` (Jména a dárky), `home` (Domácnost
  a úložné), `parts` (Díly a mechanika), `toys` (Hračky a hry), `signs` (Cedule a nápisy), `craft` (Dílna a
  řemeslo), `edit` (Úprava modelu – intent `file`), `sell` (Prodej a plánování). Stávající nástroje přeřaď.
- Stránka `/tools`: filtr podle kategorií zůstává, přibude **hledání podle názvu a klíčových slov** (klíčová
  slova v `lang/<loc>/tools.php`, `<tool>.keywords`, čistě na klientu) a kotva `/tools#<kategorie>`.
  Rozcestník `gifts` dostane odkazy na nové dárky.
- Doplň `SeoTest`/`ToolsCatalogTest` tak, aby **každý** nástroj v katalogu musel mít SEO texty ve třech
  jazycích, tři ukázky a funkční trasu – pak to nové session hlídají samy.
- **Společná stránka nástroje** (`param.blade.php` + `param.ts`) dostane jednou to, co pak užijí všechny kindy
  (předloha: `docs/img/stlbuddy-modular.png`, jejich Modular Storage Builder): klik ve vieweru vybere díl nebo
  buňku a formulář se na ni přepne; **úchyty tažením** pro šířku/výšku/hloubku vybraného dílu přímo ve 3D
  s hodnotou v mm (formulář a úchyt jsou dvě cesty ke stejnému parametru); referenční podložka 250 × 250 jako
  mřížka pod modelem a varování, když se díl nevejde; **rozložený pohled** (posuvník explode) a tlačítko
  „sestavit“ tam, kde je víc dílů; rentgen (průhledné stěny) u dutých věcí; zpět/vpřed a automatické uložení
  rozpracovaného nastavení v prohlížeči (localStorage, jako dnešní znovuotevření z kalkulace); stavový řádek
  „připraveno · N dílů“. Dnešní nástroje tím projdou automaticky, nic se u nich nemá změnit (testy).

**Obrázek v barvách** (`shape2d.colors`, sdílené pro všechny nástroje s obrázkem; předlohy
`docs/img/stlbuddy-coaster.png` a `docs/img/stlbuddy-earrings.png`): fotka/PNG/JPG/WebP/SVG → (1) **pozadí pryč**: alfa kanál,
jinak záplava z rohů s tolerancí (posuvník „síla pozadí“ 0–100, výchozí 30), přepínač vypnuto/zapnuto;
(2) **vyhlazení** v mm (0–1, morfologické otevření/zavření, ať nejsou zubaté hrany); (3) **počet barev** 1–8
(k‑means v Lab), každá barva → plochy → obrysy (`skimage.measure.find_contours` jako v `raster`) → CrossSection,
ostrůvky pod 1 mm² pryč; (4) každá barva přiřazená k **nejbližšímu filamentu farmy** (seznam barev z adminu,
s hex), v kroku „Barvy“ jde přiřazení změnit nebo dvě barvy sloučit, „ponechat pozadí“ jako barvu; před tím
(0) **úpravy fotky**: kontrast, jas, sytost s okamžitým 2D náhledem (předloha `docs/img/stlbuddy-magnet.png`);
silueta = sjednocení všeho. (5) **výšky barev**: každá barva má pořadí a krok reliéfu 0,3–1 mm („zvednout každou
barvu pro 3D efekt“), pořadí se přetahuje v seznamu barev; nebo všechny **zapuštěné** zarovno s vrchem (flag,
výchozí u podtácku). Tvar těla: **podle obrázku** / kruh / zaoblený obdélník / šestiúhelník, rámeček 0–3 mm,
zkosený lem (flag). Výstup: `body` (podkladová barva) + `color_<n>` (každá barva svůj díl) + `rim` (obrys 1–3 mm
kolem siluety ve vlastní barvě, flag – u nich fialový lem duchů). Varování z `printability` pro plochy tenčí než tryska. Stejná funkce slouží filament‑art (session 3)
a sušence.

**Kind `shape` („Tvar z obrázku“)** – jeden builder `engines/python/shape_kinds.py`, vstup obrázek v barvách
(výše) **nebo text** (1–2 řádky, písma jako u cedulky), šířka 20–200 mm, tloušťka 2–8 mm. **Očko / otvor**
kdekoli na obrysu: poloha v % obvodu posuvníkem **a tažením ve vieweru** (očko je prstenec spojený s tělem,
otvor Ø 1,5–6, tloušťka 1,5–3; „vrátit nahoru“). Pole `product` je pevně dané trasou (`->defaults('product', …)`, na stránce
se nenabízí; `WHEN` schovává pole podle produktu), každý produkt má **vlastní kartu v katalogu a SEO stránku**:

| produkt | trasa | co přidá k siluetě |
|---|---|---|
| `charm` přívěsek | `/tools/charm` | očko kdekoli na obrysu (viz výše), otvor Ø 2–6 mm; fotka v barvách, lem ve vlastní barvě |
| `earrings` náušnice | `/tools/earrings` | pár stejných nebo zrcadlových (volba; díly `left`, `right`, rozestup na plátu), šířka 10–60, tl. 1,6–3, očko tažením po obrysu, stavový řádek „32,5 × 38,8 × 3 mm každá · 2 ks“ |
| `ornament` vánoční ozdoba | `/tools/ornament` | očko na stužku, volitelný rámeček kruh/hvězda kolem siluety, tl. 3 |
| `magnet` magnetka | `/tools/magnet` | tělo podle obrázku / kruh / obdélník 40–100 mm, tl. 3, rámeček 1,5, barvy po výškách; magnet: kapsa na lepení / nalisování / skrz, presety disků Ø 6×2, 8×3, 10×2, 12×3, 15×3, 20×3, vůle 0,1–0,3, nebo rovná plocha na samolepicí magnet; ukázky srdce, tlapka, pes, sýr |
| `badge` jmenovka na navíjecí klip | `/tools/badge` | silueta 30–50 mm + vzadu kruhový nákružek Ø 18 mm, 1,5 mm (lepení na klip); text jména |
| `straw` ozdoba na brčko | `/tools/straw-topper` | C‑klip na brčko Ø 8–12 mm (vůle 0,4), otvor klipu 60 % průměru, silueta stojí nad ním |
| `hair_tie` držák na gumičky | `/tools/hair-tie-holder` | silueta jako čelní deska na podstavci + vodorovné rameno Ø 12 mm, délka 40–100 mm |
| `opener` otvírák plechovek | `/tools/can-opener` | silueta s hákem 12 × 6 mm pod úhlem (šetří nehty), tl. 4 |
| `topper` zápich do dortu | `/tools/cake-topper` | skladba vrstev (níže): šablona (číslo, srdce, hvězda, oblouk…) + texty + motivy, každá vrstva svou barvou, 1–2 hroty Ø 3 mm, délka 40–100; preset „2 · Olivia“ |
| `tray` miska ve tvaru | `/tools/shape-tray` | obrys → miska: stěna 1,6, dno 2, výška 8–30, kresba vyrytá do dna (volitelně) |
| `organizer` organizér podle fotky | `/tools/photo-organizer` | dva režimy: (a) silueta vytažená 40–120 mm, dutá, přihrádky mřížkou nebo kulaté otvory Ø 20 (koupelna: kartáčky); (b) **předměty vyfocené na listu A4** (měřítko z listu, segmentace přes „obrázek v barvách“ s pozadím pryč) → tác s kapsami podle jejich obrysů, vůle 1–2 mm, výřez na prst, hloubka podle výšky předmětu (pole na kapsu) – jako pěnová vložka do zásuvky |
| `coaster` podtácek | `/tools/coaster` | kruh / čtverec / šestiúhelník 90–110 mm, tl. 4–6, fotka v barvách **zapuštěná** zarovno (vrch rovný, sklenice se nezachytí), lem 1,5 mm ve vlastní barvě, protiskluzové drážky zespodu (flag); sada 4 ks |
| `cookie` sušenka | viz kind `cookie` níže | tvar z knihovny nebo z obrázku, poleva kreslená |
| `notes` stojánek na lístečky | `/tools/sticky-notes` | silueta stojí vzadu, vpředu miska 78 × 78 × 12 mm (lístečky 76 mm), volitelně drážka na tužku |
| `bag_charm` přívěsek s čepem na tašku | `/tools/bag-charm` | silueta 40–70 mm, tl. 6–8, vzadu čep Ø 9–11 mm × 8 mm + samostatná zátka (díl `cap`), preset „taška Bogg“ |
| `medallion` medaile na řetěz | `/tools/medallion` | kruh 60–120 mm s logem v reliéfu (2 barvy) + očko; díl `links`: články řetězu (0–40 ks, oválné 30 × 18 × 4, vůle, leží na plátu) |
| `candle_stand` stojan na svíčku | `/tools/candle-stand` | silueta z fotky jako stojící ozdoba (podstavec s rovným dnem) + **samostatná plošina s lemem** Ø 80–110 (sklenice svíčky, preset „3 knoty Ø 103“) spojená kuželovým čepem (vůle 0,2); díly `base`, `platform` |
| `papel` prolamovaný panel z portrétu | `/tools/papel-picado` | portrét → stylizovaná vystřihovánka (prahování, můstky jako u šablony – `_bridged_mask`) v panelu 100–250 mm s **květinovým prolamovaným okrajem** (6 vzorů v `engines/shapes/papel/`), tl. 1,2–2, barvy po vrstvách, otvory na šňůru nahoře (girlanda z víc panelů); mexická tradice, pro španělsky mluvící publikum, stránka to vysvětlí |
| `clicker` klikátko | `/tools/clicker` | rám s kruhovým oknem + tenká klenutá destička (0,8 mm, klenba 1,5–2,5 mm, Ø 25–45) s kresbou/písmenem; bistabilní „cvak“. **Nejdřív vytisknout a ověřit, že cvaká** (Roman na farmě) – do té doby `available => false` a poznámka v docs |

**Skladba vrstev (`compose`)** – druhý režim společné stránky pro věci složené z více prvků (předloha
`docs/img/stlbuddy-topper.png`: zápich „2 · Olivia“): vlevo seznam vrstev (šablona, text, motiv, obrázek), každá
má barvu z filamentů farmy, šířku, pořadí (co leží na čem) a viditelnost; ve vieweru se vybraná vrstva
**posouvá, otáčí a zvětšuje gizmem** (three.js TransformControls jen v rovině), klik na prvek ho vybere;
duplikovat, smazat, zpět/vpřed. Builder `engines/python/compose_kind.py`: JSON vrstev → každá vrstva
CrossSection (`shape2d`) → vytažení v pořadí (vrstva n leží o `step` 0,6–1,2 mm výš než n−1, překryvy se
slijí, všechno drží pohromadě jako jeden kus) → díl na barvu; produkt přidá základ a funkci: `topper` (zápich),
`nameplate` (destička z tvaru), `keychain` (otvor), `ornament` (očko), `name_letter` (velké písmeno jako
šablona + jméno jako text). Šablony = tvary z `engines/shapes/` (čísla 0–9 v tučném písmu, srdce, hvězda,
oblouk, obláček, stuha…), motivy z knihovny. Písma **20+ OFL fontů** (předloha jich nabízí 29), výběr
s náhledem názvu v daném písmu. Stavový řádek „97,8 × 143 × 4,4 mm · 2 barvy“. Jednoduché produkty (`shape`
z obrázku) zůstávají ve formulářovém režimu – skladba je pro text a motivy.

**Kind `cookie` `/tools/cookie`** (Cookie Decorator + Image to Cookie; předloha `docs/img/stlbuddy-cookie.png`):
sušenka na hraní nebo ozdoba. Tvar: **40 polotovarů** jako SVG v `engines/shapes/cookies/` (Vánoce: perníček,
Santa, čepice, sob, sáně, dárek, stromek, sněhulák, punčocha, hůlka, baňka, vločka, zvonek, hvězda, rukavice,
věnec, skřítek, tučňák, cesmína, srdce; Halloween: dýně, duch, netopýr, kočka, pavouk, lebka, klobouk, kotlík,
kost, měsíc, rakev, oko, ruka, zub, ... – nakresli je, jednoduché siluety, licence naše), nebo **vlastní obrázek**
(silueta i barvy přes „obrázek v barvách“). Tloušťka 6–10 mm, horní hrana zaoblená 1,5 mm, barva těsta z barev
farmy. **Zdobení** = krok „poleva“: kreslení ve vieweru v pohledu shora (ortograficky; tah = lomená čára, hrot
kulatý / plochý / tečky, šířka 1,5–4 mm, výška 1–1,5 mm, barva z palety filamentů), tah jde vybrat, posunout a
smazat, zpět/vpřed; tahy → kapsle po úsecích → sjednocení (manifold) → díl na každou barvu polevy, oříznuté na
obrys sušenky; náhled hned. Volba „ozdoba“ = očko na stužku; **cukrovinky** z knihovny (gumové bonbony, peprmintky, sypání) jako motivy navíc, každý svou barvou. Volitelný rámeček/tácek na vystavení (díl `tray`).
Stavový řádek „1 sušenka · 3 barvy · 12 tahů“. Ukázky: perníček s polevou, duch, srdce se jménem.

**Knihovna obrázků** – postavila session 0 (okno Nahrát · Knihovna · Moje obrázky); tady přidej tvary sušenek
a cedulek z této session a doplň chybějící kategorie, cíl 100–200 SVG
siluet s jasnou licencí (CC0 / public domain; zdroj a licence v `engines/artwork/SOURCES.md`; kategorie zvířata,
srdce a hvězdy, sport, povolání, svátky, písmena a čísla) + tvary sušenek a cedulek z této session; náhledy,
hledání podle názvu ve třech jazycích; přihlášený má „moje obrázky“ (co nahrál dřív, 30 dní). Záložku
„vytvořit“ (obrázek z popisu) doplní session 4 do téhož okna.

**Jména** (nové kindy v `engines/python/name_kinds.py`, text přes `shape2d.text`):

- `name_letter` `/tools/name-letter` (Name Maker, postavené na skladbě vrstev): velké první písmeno 60–200 mm, tl. 6–15, uvnitř celé jméno
  vyvýšené 1–2 mm (2. barva), písmo pro písmeno i jméno zvlášť; jméno se vejde = automaticky zmenší, když ne,
  varování. Volitelně otvor na zavěšení nebo podstavec (stojí).
- `name_cup` `/tools/name-organizer` (Name Organizer): jméno v spojitém písmu (script), obrys vytažený 60–100 mm,
  stěna 1,6, dno 2; každé písmeno je kapsa (písmena bez vnitřku, např. „l“, se spojí se sousedem); šířka 100–250;
  podstavec deska 3 mm pod celým jménem (volitelně 2. barva).
- `beads` `/tools/letter-beads` (Letter Bead Factory): text → korálek na písmeno: kostka 8–14 mm (zaoblená),
  kulička, srdce nebo hvězda; písmeno vyryté/vyvýšené (2 barvy) na 2 protilehlých stranách; otvor Ø 2–4 skrz;
  díly leží na plátu v pořadí textu; i prázdné (oddělovací) korálky a sada čísel.
- `sign` rozšíření (Nameplate, Keychain, Text Maker, Gingerbread): (a) **tvary destičky** z SVG šablon
  `engines/shapes/*.svg` (obdélník, ovál, kruh, srdce, hvězda, mrak, kost, šestiúhelník, stuha/banner, šipka,
  domek, auto, kočka, pes, kytka, perníček… 15+), text se do tvaru vycentruje a zmenší, aby se vešel;
  (b) **motiv** vedle textu (srdce, hvězda, tlapka, nota, korunka… z `engines/shapes/motifs/*.svg`, nebo
  vlastní obrázek), vlevo/vpravo/nad; (c) otvor klíčenky nahoře/vlevo/vpravo; (d) styl `name` až **3 řádky**,
  60 znaků, a volba **stojící** (podstavec 3 mm pod spojenými písmeny) = Text Maker; (e) tvar `gingerbread`
  s polevou: obrys perníčku, klikatá poleva po obvodu a knoflíky jako 2. barva, jméno uprostřed, očko nahoře.
  Presety a tři ukázky pro každou novou kartu (`/tools/nameplate`, `/tools/keychain`, `/tools/text`,
  `/tools/gingerbread` jsou trasy na `sign` s presetem, karta každá zvlášť).
- `logo` režim `extrude` (SVG to STL): čisté vytažení obrysu bez destičky, výška 1–50 mm, volitelně zkosení;
  karta `/tools/svg-to-stl` je trasa na `logo` s presetem.
- Písma: přidej **20+ fontů pod OFL** (Google Fonts: Lobster, Fredoka, Caveat, Bangers, Playfair Display,
  Pacifico už je, Dancing Script, Great Vibes, Sacramento, Satisfy, Alfa Slab One, Righteous, Luckiest Guy,
  Chewy, Baloo, Comfortaa, Montserrat Black, Oswald, Bebas Neue, Press Start 2P…), licenční soubory vedle
  (jako Pacifico); jen tučnější řezy, které se tisknou (hláška u tenkých); výběr písma dostanou všechny textové
  nástroje jednotně (`typeface` v `shape2d`) s náhledem v daném písmu, názvy v jazycích. Karty `/tools/nameplate`
  a `/tools/keychain` otevírají skladbu vrstev s produktem `nameplate` / `keychain`; `sign` zůstává jako rychlý
  formulář pro jednoduchou cedulku.

Testy: `ShapeToolsTest` (každý produkt: náhled vrátí STL, bbox odpovídá šířce, díly, varování; obrázek z
`tests/fixtures`), `NameToolsTest`, rozšíř `SignToolTest` a `CreativeToolsTest`. Pythonová část: každý builder
vrací manifold bez chyb (`status() == NoError`), objem > 0, žádný díl mimo 240 mm.

## Session 2 – Díly, obaly, hračky (`feature/tools-parts`, `docs/Q.md`)

Nové kindy v `engines/python/part_kinds.py` (užitkové) a `toy_kinds.py` (hračky), všechno manifold3d:

- `sleeve` `/tools/sleeve` („Obal na plechovku, kelímek, svíčku“): vnitřní Ø dole/nahoře 40–120 (kónické =
  různé), výška 20–160, stěna 1,6–4, dno žádné/plné/s odtokovým otvorem, výřezy (žádné / svislé drážky / vzor
  kruhů), úchopová žebra, pásek s textem nebo obrázkem v reliéfu (2. barva, přes `shape2d`). **Presety** s vlastní
  kartou (každá preset = trasa + karta + SEO): `can` plechovka 330 slim (Ø 58), 330 (Ø 66,3), 500 (Ø 66,3 vyšší);
  `cup` kelímek na kávu kónický otevřený (Ø 85→70, v. 60, i papírový 0,3 l) – navíc režim **stěna z propojeného písma**: jména nebo citát dokola, písmena rozvinutá po kuželu a spojená do pásu (horní a dolní lem 3 mm, tučné písmo), jeden kus; `pint` kelímek zmrzliny 473 ml
  (Ø 95→80, v. 100; rozměry ověř na skutečném kelímku, do FAQ napiš, jak si změřit vlastní); `candle` stojan na
  svíčku ve skle (Ø 80–110, v. 25, dno, preset „3 knoty Ø 103“); `jar` sklenice. Vůle 0,5–1 mm je samostatné pole.
- `soap` `/tools/soap-dish` mýdlenka: vnitřní rozměr 60–120 × 40–90, výška 15–30, odtok: drážky / mřížka /
  žebra; tvar kapsy obdélník/ovál/**ze siluety obrázku** (shape2d); v session 3 přibude „podle stopy modelu“ z STL.
- `organizer` preset `bathroom` (kartáčky: otvory Ø 20, pasta: přihrádka 40 × 25) – jen preset a ukázka.
- `drawers` `/tools/modular-drawers` (Modular Storage Builder; předloha je na obrázku `docs/img/stlbuddy-modular.png`,
  prohlédni si ho): **stavebnice modulů**, ne jeden rám. Mřížka sloupce × patra (1–6 × 1–4); každá buňka je
  modul = samostatná skříňka s vlastními rozměry Š × V × H (40–200 × 30–150 × 60–250, výchozí 80 × 60 × 120)
  a obsahem **přihrádka** (otevřená) nebo **šuplík** (díl navíc, vůle 0,4, čelo s otvorem na prst nebo lištou,
  volitelně jméno na čele jako 2. barva). Sousední buňky jdou sloučit v jeden širší nebo vyšší modul. Moduly drží
  pohromadě **zasouvacími rybinami** na bocích a na vršcích (rybina 8 × 3 mm, délka 20, vůle 0,25; flag „bez
  spojů“ pro lepení); výšky v sloupci se zarovnávají (volba „přichytit vrchy sloupce“ / volně). Každý modul se
  tiskne zvlášť, takže se vejde na podložku, i když celek ne. Výstup: díly `module_<c>x<r>` a `drawer_<c>x<r>`,
  souhrn „6 modulů · 10 spojů“, celkový rozměr skříně, **rozložený pohled** a **návod na sestavení** (pořadí
  zasouvání; stránka nebo PDF přes dompdf). Stávající `modular` (miska s miskami) zůstává jako jednodušší
  jednodílná varianta a odkazuje sem.
- `bracket` `/tools/bracket`: typ L / U / Z / konzola (L s výztuhou); ramena 20–200, šířka 10–80, tloušťka 2–8,
  otvory: počet 0–4 na rameno, Ø 3–10, zahloubení pro šroub (flag), rádius v rohu; presety „polička 150“,
  „držák lišty LED“.
- `bolt` `/tools/bolts` (Bolt Factory): metrické M3–M20 (preset: Ø, stoupání) nebo vlastní (Ø 4–40, stoupání
  0,5–6), délka 5–150, hlava šestihran / imbus (šestihranná dutina) / půlkulatá / zápustná / křídlová /
  rýhovaná (ruční), **matice** šestihran / křídlová / čtyřhran / rýhovaná, **podložka**; vůle závitu 0,1–0,4
  (preset „na tisk 0,25“). Závit převezmi z `cap` (`param_tool.cap`, styl `thread`), vytáhni ho do sdílené funkce
  `_thread(M, d, pitch, length, clearance, internal)` a `cap` na ni přepni (test `cap` beze změny výsledku).
  Díly `bolt`, `nut`, `washer`, počet kusů 1–10 (leží na plátu). Varování: závity pod M6 se při 0,4 mm trysce
  tisknou špatně.
- `dice` `/tools/dice`: d6 první; d4, d8, d10, d12, d20 jako mnohostěny (vrcholy spočítej, ne z assetu);
  velikost 12–40, zaoblení hran/rohů, strany: tečky / čísla / vlastní text na každou stranu (d6: 6 polí po 6
  znacích, výchozí 1–6) / obrázek na jedničce; vyryté nebo vyvýšené (2. barva); sada 1–5 kostek v jednom souboru.
  Těžiště: dutina se nedělá (upozorni, že tištěná kostka není férová).
- `ttt` `/tools/tic-tac-toe`: deska 3 × 3 / 5 × 5 / 7 × 7 / 9 × 9 (větší = „pět v řadě“), pole 20–50 mm, mřížka vyvýšená nebo pole zapuštěná (figurka
  nepadá), figurky X a O (5 + 5) nebo dvě vlastní siluety/fotky (shape2d, dvě sady barev), výška figurky
  6–12; díly `board`, `x`, `o`; preset cestovní (pole 25, deska s magnetovými kapsami Ø 6).
- `bell` `/tools/spirit-bell` (zvon fanouška): plášť kužel / komolý jehlan / **míč** (fotbalový, basketbalový, baseballový – švy vyryté) / **helma** (zjednodušená, bez značek) / silueta obrázku vytažená, výška 60–150, stěna 2, nahoře očko
  na popruh, logo/text na boku v reliéfu (2. barva, oboustranně), uvnitř hák + srdce (kulička Ø 10 na pásku,
  díl `clapper`, vůle). Upozornění: plast zvoní málo, zní jako chrastítko – ať to stránka říká.
- `bottle` `/tools/potion-set` (Potion Set Builder; předloha `docs/img/stlbuddy-potion.png`): co vyrobit –
  **láhev / pohár / kotlík**. Láhev má **25 tvarů** jako profily rotačního tělesa v `engines/shapes/bottles.py`
  (koule s hrdlem, kužel, zkumavka, úzká zkumavka, kulička, baňka, kónická lahvička, lékárnická, kapka, vzorková,
  parfém, krystal, korunka, přesýpací hodiny, dvojitá a trojitá baňka, kalich, dlouhé hrdlo, žebrovaná, široká
  ramena; nekulaté – čtvercová, trojúhelník, srdce, štít, diamant, pyramida – jako vytažený průřez se zaoblením
  a kulatým hrdlem; lebku ne, to je modelovaný asset), výška 40–220, hrdlo Ø 12–40, stěna 1,6–3, dutá
  s otevřeným hrdlem; **zátka šroubovací** (závit ze sdílené funkce `_thread`, vůle 0,25, žebrovaný úchop, díl
  `stopper`) nebo zasouvací kužel; **zdobení**: štítek (obdélník / ovál / stuha) s textem nebo obrázkem v barvách,
  pásek kolem těla, pečeť na hrdle – každé svou barvou; pohár: 6 profilů, noha a patka; kotlík: 3 nohy, ucho,
  okraj. **Sada**: víc kusů se jmény („moje kusy“), každý zvlášť, záložka „rozložení na podložce“, souhrn
  „2 díly na kus · podložka 250“. Flag „vázový režim“ (bez dna, stěna 1 obvod) s poznámkou pro slicer.
- `evilbox` `/tools/puzzle-box`: dárková krabička, která se otevře jen správným tahem: vnější plášť + zásuvka
  zajištěná pružnou západkou (vnitřní konzola 0,8 mm, zub 1 mm), uvolní se stiskem skrytého místa na plášti
  (tenká membrána 0,6 mm s tečkou); vnitřek 40–150 × 30–100 × 20–80, stěna 2, vůle 0,4; volitelně nápis na víku
  (2. barva) a „falešný“ přední šuplík, který nejde vytáhnout. Díly `shell`, `drawer`. FAQ: jak se otevírá.
- `marble` `/tools/marble-run` (poslední, až zbyde čas; předloha `docs/img/stlbuddy-marble.png` generuje celou
  trasu náhodně podle seedu do 600 × 330 × 240 mm se zdvihem, tři „zahrady“, „Play“ simulace – a při první
  zkoušce spadla s „The tool could not finish processing“, štítek „Physical testing pending“; my jdeme opačně:
  nejdřív ověřené díly, pak trasa): díly kuličkové dráhy na mřížce 40 mm (kulička Ø 16): rovinka 1–3 pole se
  spádem 5°, zatáčka 90°, zatáčka 180°, trychtýř, schody, start s nálevkou, cíl s miskou; spojení
  kolíky/drážkami 3 mm, vůle 0,3; stránka = „stavebnice“: počty jednotlivých dílů → jeden soubor s díly na
  plátu (po 250 mm, víc plátů = víc dílů `plate_<n>`). Nejdřív 3 druhy dílů, otestovat tisk. Až to drží:
  „náhodná trasa“ (seed, rozměry, charakter) jako automatické skládání z týchž dílů s návodem, a ruční šroubový
  výtah jako samostatný díl.
- Sports Turtles (#51): předloha je hotový model želvy s krunýřem jako míč. Bez assetu nejde; **rozhodnutí**:
  (a) želvu vygenerovat Tripo z textu, očistit a uložit jako náš asset `engines/assets/turtle.stl` (jednou,
  licence naše), pak nástroj mění krunýř (fotbal/basket/tenis – švy vyryté do koule) a přidá jméno a číslo;
  (b) vynechat. Výchozí: (b), dokud Roman neřekne (a).

Testy `PartToolsTest`, `ToyToolsTest` (jako u session 1), `cap` beze změny (snapshot objemu a bboxu před/po
refaktoru závitu). Mechanické věci (šroub + matice M8, kostka, krabička se západkou, klip na brčko) ať Roman
vytiskne na farmě dřív, než session skončí – do docs napiš, co se ověřilo a co ne.

## Session 3 – Úprava modelu (`feature/tools-edit`, `docs/R.md`)

Všechno „mám soubor“: nahrání nebo `?from=<uuid>`, viewer, nastavení, výsledek jako `ModelFile` s díly →
cena / farma / stažení / projekt. Společný základ postav jednou:

- `engines/python/edit_tool.py` s příkazy (`analyse`, `split`, `hollow`, `scale`, `puzzle`, `flexi`, `openings`,
  `potion`, `colors`), vstup STL (po konverzi `ConverterChain`), výstup díly STL + JSON (bbox, objemy, varování).
  Booleany manifold3d (vstup nejdřív opravit jako ve farmě: `farm_tool`/`repair_tool`, jinak manifold odmítne).
- **Dutina** (hollow): podepsaná vzdálenost na mřížce (jako přestavba roztrhaných bust v `mesh_tool.py` –
  použij totéž), vnitřní offset o tloušťku stěny 1,5–6 mm, `skimage.measure.marching_cubes`, odečíst od
  originálu; mřížka ≤ 0,6 mm a ≤ 320³ buněk (nad to zhrubni a řekni to); odtokové otvory Ø 3–8 mm dole (počet,
  poloha automaticky nejníž); hlášení: ušetřený materiál v g a Kč z kalkulace.
- **Dělení** (split): podložka z presetů tiskáren farmy (250) / 220 / 180 / vlastní; automaticky nejmenší počet
  řezů rovinami X/Y/Z, ruční posun rovin posuvníky ve vieweru (ukázat roviny), spoje: žádné / kolíky Ø 6 × 12
  (díry s vůlí 0,2, díl `pins`) / rybina 8 mm; číslo dílu vyryté do řezné plochy; díly položené řeznou plochou
  na plát; mapa sestavení v JSON → stránka ji ukáže (jak díly leží vůči sobě).
- `App\Domain\Tools\ModelEditor` + job `EditModel` ve frontě s fázemi a časy (`timings`, jako M.md), stránka se
  ptá na stav; limit vstupu 2 M trojúhelníků, nad to decimace (trimesh/`fast_simplification` není – použij
  `trimesh.simplify_quadric_decimation` jen když je k dispozici, jinak odmítnout s důvodem).

Nástroje (každý karta + SEO, intent `file`, kategorie `edit`):

- `/tools/split` Model Splitter – dělení výše, jako samostatný nástroj.
- `/tools/life-size` Make It Life Size: cílová výška v cm nebo měřítko v %, pak automaticky dutina (stěna
  2–5, od objemu > 200 cm³) a dělení na podložku s kolíky; výsledek: díly, odhad gramů/hodin přes kalkulaci
  (součet dílů), varování nad 20 dílů.
- `/tools/wearable` Make It Wearable (+ MyFit, + obecná „přilba na míru“): měřítko podle **míry** (obvod hlavy
  → vnitřní šířka přilby + vůle 10 mm; obvod hrudi/paže pro brnění – vzorec v docs), dutina (stěna 2–4),
  **průzory**: uživatel ve vieweru umístí 1–4 obdélníky/elipsy na povrch (promítnutí po ose pohledu → výřez
  skrz stěnu), drážky na popruh 25 mm (flag), dělení na podložku. Mandalorianská přilba se **nedodává** (IP
  Disney/Lucasfilm) – stránka řekne, že si model přilby přinesou (Printables/MakerWorld) a tady ho upravíme.
- `/tools/puzzle` Make It a Puzzle: ploché modely (reliéf, logo, litofanie, cokoli s výškou ≤ 20 mm): mřížka
  n × m (2–8) s puzzle zámky (knoflík 0,35 délky dílu, vůle 0,2) řezaná skrz Z, nebo **skryté kolíky** (rovné řezy, kolíky Ø 3 × 6 v bocích dílů, vrch zůstává čistý); vysoké modely: dělení rovinami
  s puzzle profilem místo rovné roviny (3 osy); díly na plátu, číslování vyryté zespodu; volitelný rámeček
  (díl `frame`).
- `/tools/flexi-cut` Flexi Cutter: segmentace podél osy (automaticky nejdelší osa, nebo osa + počet segmentů
  3–20), do řezů kulové klouby (koule Ø 6–10 s krčkem, dutina s vůlí 0,35–0,5; krček míří ven ze segmentu),
  tiskne se **najednou** v složeném stavu (print‑in‑place) – výsledek jeden STL; varování u segmentů tenčích než
  kloub. `/tools/flexi` Flexi Maker (předloha `docs/img/stlbuddy-flexi.png`: roztomilá kočka z hladkých
  zaoblených tvarů, výběr hlava / tělo / ocas, oči, doplněk, tvářičky, slza): kloubové zvíře z primitiv
  manifold3d (koule, elipsoidy, kapsle, kužely, sjednocené a zaoblené) – **hlava** kočka / pes / medvěd /
  králík / liška / žába / drak (uši, čumák), **oči** veselé / zavřené / tečky / hvězdičky (vyryté nebo 2.
  barva), **doplněk** mašle / čepička / korunka / brýle, tvářičky a slza (2. barva), **tělo** 3–12 článků
  s kulovými klouby, nohy jako krátké kapsle, **ocas** rovný / zatočený / s chomáčem; délka 80–300, jméno
  vyvýšené na břiše; tiskne se vcelku (print‑in‑place), sdílí kód kloubu s flexi‑cut; háčkovaná textura ne.
  Když primitiva nestačí na „roztomilé“, druhá cesta je hlava z Tripo (text → model), očištěná a uložená jako
  náš asset – do docs napiš, co z obou cest vyšlo.
- `/tools/colors` Color Splitter: barvený 3MF → díl na barvu: `basematerials`/`m:colorgroup` + `triangle
  pid/p1`, barvy na objektu (`pid/pindex`), Bambu/Orca `paint_color` na trojúhelníku; Prusa `slic3rpe:mmu_segmentation`
  a dělené trojúhelníky Bambu = většinová barva trojúhelníku, a stránka to řekne. Výstup: díly pojmenované
  barvou, projekt s barvami do slotů ACE; `ThreeMfConverter` dnes barvy zahazuje – doplň čtení, ne přepisuj.
- `/tools/filament-art` Filament Art Factory: obrázek → 3–8 barev filamentů farmy přes „obrázek v barvách“ ze
  session 1. **Dva režimy.** (a) *Jeden tisk*: každá barva vrstva 0,4 mm (2 × 0,2) nad podkladem 1,2 mm na
  desce 50–250 mm s rámečkem; výstup díl na barvu **a** projekt s výměnou barvy ve výškách (`ColorChange.php`)
  – jde i na jednobarevné tiskárně s výměnou filamentu. (b) *Vrstvený obraz* (předloha
  `docs/img/stlbuddy-layered-art.png`: video hotového obrazu – barevné kočky z vrstev PLA v rámu, každá barva
  svá deska, Roman: „skládání obrázku z tištěných vrstev“): každá barva je **samostatná deska 1,5–3 mm** – plocha
  její barvy **plus vše, co leží za ní** (desky se skládají jako vystřihovánka, pořadí zepředu dozadu: černá
  obrysová linka úplně nahoře), vzájemně odsazené distančními sloupky 2–5 mm pro hloubku a stín, nebo naplocho;
  **rám** kulatý / čtvercový (přední prstenec, zadní deska, otvor na zavěšení, díl `frame`), volitelně místo na
  LED pásek vzadu; každá deska se tiskne svou barvou kdekoli (i bez ACE), **návod** = pořadí vrstev s obrázkem.
  Náhled v barvách ve vieweru s rozloženým pohledem. Rozlišení mřížky jako u reliéfu (`points`); linky tenčí
  než 0,8 mm sloučit do sousední barvy a říct to.
- `relief` rozšíření na úroveň předlohy a výš (`docs/img/stlbuddy-lithophane.png`): **co vyrobit** – fotopanel /
  noční lampička / lampa / klíčenka; **tvary**: plochý, oblouk (poloměr 40–120, úhel 60–180°), kruh, ovál,
  srdce, oblouk nahoře (arch), stromek, **vlastní silueta** (SVG/obrázek), válec 360° (lampa: dno, otvor na
  E14/E27 Ø 28/40 nebo LED pásek); šířka × výška s „podle poměru fotky“, rámeček 0–6 mm (v celkovém rozměru);
  **zavěšení a stojánek** (otvor, očko, stojánek už je); **světlo a detail**: nejtenčí 0,4–1,2, nejtlustší 2–5,
  jas, kontrast, střední tóny, invertovat – s okamžitým 2D náhledem upravené fotky; tloušťky zaokrouhlené na
  výšku vrstvy 0,2 (řekni, kolik odstínů tak vznikne); **náhled s podsvícením** ve vieweru (přepínač
  „podsvícené / povrch“: shader převádí tloušťku na propustnost světla, ať člověk vidí výsledek dřív, než
  tiskne). Barevná litofanie = filament‑art s podsvícením (odkaz mezi nástroji).
- `/tools/holder-from-model` Držák z vlastního modelu (Koozie, Ice Cream Pint a Soap Holder jsou u nich „from your
  STL“): nahraný model (figurka, busta, logo…) se zvětší na zadanou výšku, srovná dnem na podložku a **odečte se
  z něj dutina**: plechovka 330 (Ø 66,3), slim 330 (Ø 58), 500 / energy (Ø 66,3 vyšší), kelímek zmrzliny 473 ml
  (kónický), mýdlo (kvádr 90 × 60 × 30 se zaoblením a otvorem na vytlačení zespodu), sklenice svíčky, vlastní válec;
  vůle 0,5–1, poloha dutiny (střed / posun) a hloubka; kontrola, že stěna kolem dutiny nikde není pod 2 mm (jinak
  řekni „zvětšit model na X mm nebo posunout“), dno srovnané. Výstup jeden díl. Varianta bez modelu je `sleeve`
  v session 2.
- `/tools/potion` Make It a Potion: model → duté tělo láhve (dutina výše), seříznuté dno, přidané hrdlo Ø 20–40,
  výška 20–60, zátka (díl `cork`), štítek s textem; tiskne se jako jedna láhev + zátka. Sdílí kód s `bottle`
  ze session 2 (pokud ještě není, hrdlo a zátku postav tady a session 2 je převezme – domluv se přes `main`).
- `soap` „podle stopy modelu“: kapsa mýdlenky z půdorysu nahraného STL (+ vůle) – malé, až nakonec.
- `/tools/slider` Sliding Fidget (volitelné, poslední): do plochého modelu (deska ≥ 6 mm) drážka napříč
  s jezdcem (rybina, vůle 0,3) a 3–5 zarážkami; tiskne se najednou.

Testy `ModelEditTest` (fixture: krychle, koule, dlouhý válec, plochý reliéf; každý nástroj: díly, objemy,
vůle, dutá koule má objem ≈ plášť), test 3MF s barvami (fixture vyrobený lxml v testu). Rychlost: změř (M.md
styl) na modelu 500 k trojúhelníků; dutina + dělení do 60 s na serveru, jinak hrubší mřížka.

## Session 4 – Prodej, plánování, obrázky (`feature/tools-sell`, `docs/S.md`)

Stránky bez geometrie (nový `SellToolsController`, kategorie `sell`, intent `create`; JS v
`resources/js/site/sell.ts`, výpočty na klientu, nic se neukládá bez účtu, přihlášený uživatel může uložit
do `account`):

- `/tools/cost` Náklady tisku na vlastní tiskárně: filament (Kč/kg, g), čas (h), elektřina (W × Kč/kWh),
  odpisy tiskárny (cena / životnost h), zmetky %, práce (Kč/h × min) → náklad a doporučená cena (marže %).
  Předvyplnění z kalkulace `?from=<uuid>` (gramy a hodiny z `calculations`) a z profilu tiskaře, pokud je
  přihlášený (jeho ceny). Vedle: „nechat vytisknout u nás stojí X“ (z kalkulace) – to je náš cíl.
- `/tools/profit` Zisk prodejce: platforma Etsy / Fler / Shopify / vlastní e‑shop / veletrh / farma matplace;
  poplatky v `config/sell.php` **s datem a zdrojem** (Etsy: listing 0,20 USD, provize 6,5 %, platby pro CZ
  4 % + 10 Kč a převod měny 2,5 % – **Roman ověří**, nástroj ukazuje „sazby k <datum>“), měny CZK/EUR/USD
  z `CurrencyController`; vstup: cena, náklad (z `/tools/cost`), doprava účtovaná/skutečná, sleva, DPH ano/ne
  → zisk na kus, marže, break‑even počet kusů; tabulka tří cen vedle sebe.
- `/tools/plan` Plán prodeje (Business Planner): produkty (název, náklad, cena, kusů/měsíc), fixní náklady
  (měsíčně), sezónnost (měsíce) → měsíční tržby/zisk 12 měsíců, break‑even, graf (SVG na klientu); export
  CSV a PDF (dompdf); uloží se do localStorage a s účtem do DB (`sell_plans`, json); k tomu **týdenní plán kroků** (co udělat tento týden:
  nafotit, nacenit, vystavit – odškrtávací seznam, který plánovač vygeneruje z chybějících údajů).
- `/tools/listing` Text inzerátu (Etsy Listing Generator): vstup: co to je (text), materiál, rozměry, fotka
  (volitelně, vision), jazyk cs/en/es, platforma (Etsy: titulek 140 znaků + 13 tagů + popis; Fler; vlastní) →
  `Assistant::ask('listing', …)` se schématem `{title, description, tags[], materials[], keywords[]}`, výstup
  s tlačítky kopírovat, denní limit jako u ostatních AI (`config/ai.php` `daily_limits.listing`), ceny v
  `prices`. Druhý režim: inzerát pro **model z katalogu matplace** (předvyplnění z `catalog_models`).
- `/tools/photo` Foto produktu (Listing Photo Enhancer): nahrání fotky → odstranění pozadí → pozadí bílé /
  přechod / dřevo / mramor / beton (statické textury `public/img/backgrounds`, licence CC0 uvedená) + stín +
  ořez na čtverec 2000 px, export JPG/PNG, dávka až 6 fotek. Odstranění pozadí: **`rembg` (u2net, onnxruntime
  na CPU, ~300 MB) v `/opt/matplace-py`** – instaluje Roman; bez něj nástroj není v katalogu (`available` podle
  `engines.photo`). Fotky se po 24 h mažou (`matplace:prune`).
- `/tools/image` Obrázek z popisu (Image Creator): nový motor `App\Engines\Image\ImageGenerator` (contract:
  `fromText(prompt, style, size)`; `FakeImageGenerator` pro testy a lokál; první provider podle rozhodnutí
  níže), styly „silueta/černobílá kresba“ (to je to, co naše nástroje umějí použít), „linka“, „barevný“; vedle toho záložka
  **„poskládat“**: jednoduchý 2D editor na plátně (tvary, text v našich písmech, grafika z knihovny, vrstvy, barvy)
  s výstupem SVG – bez AI a bez limitu;
  výsledek se dá **rovnou poslat do kteréhokoli nástroje s obrázkem** (`shape`, `logo`, `relief`,
  `filament-art`, `cutter`, `stamp`, `stencil`: tlačítko „použít v…“ → obrázek do session, nástroj ho načte);
  denní limit, cena v `prices`. Vygenerovaný obrázek se uloží do „mých obrázků“ a objeví se jako záložka
  „vytvořit“ v okně knihovny ze session 1 (jedno okno: knihovna / moje obrázky / vytvořit).
- `figure` rozšíření (Image to STL z textu): záložka „popište to“ → `GenerationService` přes `fromText`
  (Tripo, 10 kreditů, už v motoru), stejný limit a tok jako z fotky.
- `/tools/vendors` Kde prodávat (předloha `docs/img/stlbuddy-vendors.png`: „Find your next selling opportunity“
  – město + okruh v mílích + období, mapa s piny a „fit score“, uložené příležitosti, „navrhnout akci“, export
  do kalendáře; jen USA): u nás **trhy a akce v okolí** pro CZ/SK. Tabulka `events` (název, typ – řemeslný trh /
  vánoční trh / farmářský trh / Maker Faire / Comic-Con / veletrh / burza, místo + souřadnice, termíny, web,
  poplatek za stánek, poznámka, stav ověřeno/navrženo), správa v `/admin/events`, veřejný formulář „navrhnout
  akci“ (schvaluje admin); prvních ~40 akcí naplní session z veřejných webů s datem a odkazem (Dyzajn market,
  MINT market, Maker Faire Praha/Brno/Plzeň, Comic-Con Prague, vánoční trhy měst, řemeslné jarmarky…) se
  stavem „ověřit“. Stránka: město (geokódování přes stávající `App\Domain\Geo`), okruh 10/25/50/100 km, období
  (90 dní / rok), mapa Leaflet + OpenStreetMap, seznam s „hodí se pro“ (Assistant podle popisu toho, co člověk
  vyrábí: 3 akce + co na ně vyrobit, s odkazem do nástroje), uložené akce u účtu, export `.ics`. Vedle toho stálý
  seznam kanálů v `config/sell.php` (Fler, Etsy, Amazon Handmade, Vinted, Aukro, komisní prodej) s poplatky a odkazy.

Testy: `SellToolsTest` (výpočty cost/profit/plan jsou **i v PHP** jako čisté funkce `App\Domain\Sell\*`, JS je
zrcadlo jako `rough.ts` – testuj PHP čísly), `ListingToolTest` a `ImageToolTest` s `Fake*`, `PhotoToolTest`
jen když je rembg (skip s důvodem).

## Společné požadavky na každý nový nástroj (checklist, platí pro všechny čtyři session)

1. Trasa (anglická, `/tools/<slug>`), záznam v `config/tools.php` (správná kategorie, tři ukázky), názvy a
   klíčová slova v `lang/{cs,en,es}/tools.php`, popisky polí a varování v `lang/{cs,en,es}/param.php`, SEO
   stránka `tools_seo/<tool>.php` ve třech jazycích (intro 2 odstavce, 4 kroky, 5–6 FAQ včetně materiálu a
   „jak zaplatím/dostanu“ jako u vykrajovátka), ukázky vykreslené `matplace:tool-examples`.
2. Náhled hned (parametrické do ~2 s na serveru – změř `time python …` v docs), milimetry, meze v `FIELDS`,
   nic mimo 240 mm bez varování; díly po barvách, kde to dává smysl (`two_color` vzor), každý díl zvlášť ke
   stažení, projekt 3MF s barvami.
3. Konec nástroje = cena + „Vytisknout u nás“ + stažení; z kalkulace zpět „změnit návrh“ (nástroj se znovu
   otevře se svým nastavením – vzor commit `986aa03`, už v `main`).
4. Varování srozumitelně, ne technicky (`notes.warnings` → text ve třech jazycích): „Písmo je tenčí než tryska,
   zvětšete text“, ne „thin feature“.
5. Testy: feature test nástroje (náhled → STL, bbox, díly, varování; vytvoření → ModelFile → kalkulace), SEO a
   katalog automaticky; Python builder: manifold bez chyb, objem > 0.
6. Docs (`P/Q/R/S.md`): co vzniklo, rozhodnutí a proč, co se **fyzicky** vytisklo a ověřilo a co ne, měření
   rychlosti, nasazení pro Romana (balíčky, `.env`, migrace, `npm run build`, restart).
7. Žádný nástroj v katalogu, který nefunguje od začátku do konce; co nestihneš, nech `available => false`
   s poznámkou v docs, ne poloviční stránku.
8. Nástroj s více díly nebo buňkami (šuplíky, piškvorky, korálky, kuličková dráha, dělení modelu, flexi) používá
   výběr a úchyty ve vieweru, rozložený pohled a návod na sestavení ze společné stránky (session 1); stažení
   nabízí STL po dílech, projekt 3MF s barvami **a** návod. Další screenshoty předloh od Romana jdou do
   `docs/img/stlbuddy-<nástroj>.png` a zadání na ně odkazuje.
9. Společné prvky stránky (ze screenshotů, platí všude): kroky vlevo v pořadí, v jakém člověk přemýšlí
   (Obrázek → Rozměry → Barvy → Tisk; u sad „moje kusy“); u obrázku vždy tři cesty – nahrát, knihovna, vytvořit;
   pohledy 3D / shora / ze strany / zespodu; stavový řádek s rozměry, počtem dílů a barev; stažení nabízí
   nejdřív **projekt s barvami** a pak STL; rozpracované nastavení přežije obnovení stránky.

## Rozhodnutí, která potřebuju od Romana (když neřekne jinak, platí výchozí)

1. **Obrázky z popisu** (#15): poskytovatel a klíč. Doporučení: fal.ai FLUX.1 schnell (~0,003 $/obrázek,
   rychlé, dobré siluety) nebo OpenAI `gpt-image-1` (~0,02 $). Výchozí: motor + Fake se postaví, karta se ukáže
   až s klíčem (`IMAGE_PROVIDER=`, `IMAGE_API_KEY=`).
2. **rembg na serveru** (#60): instalace do `/opt/matplace-py` (~300 MB, CPU). Výchozí: ano, Roman nainstaluje
   podle docs.
3. **Cizí značky a IP**: Mandalorian (#57) se nestaví; Bogg / Bath & Body Works jen jako název presetu. Výchozí: tak.
4. **Želvy** (#51): asset z Tripo (a) nebo vynechat (b). Výchozí: (b).
5. **Tvary sušenek, cedulek a lahví** (40 + 15 SVG, 25 profilů): kreslí session sama jako jednoduché siluety
   a profily, licence naše; Roman může dodat vlastní kresby. Výchozí: tak.
6. **Kuličková dráha** (#55): stavebnice dílů, až po všem ostatním v session 2. Výchozí: tak.
7. **Sazby poplatků** v `config/sell.php` (Etsy, Fler…): session je naplní z veřejných ceníků s datem,
   Roman před nasazením zkontroluje.
8. **Klikátka, šrouby, krabička se západkou, flexi klouby, klip na brčko**: Roman vytiskne zkušební kus na farmě
   během session; dokud neřekne „cvaká / drží / sedí“, zůstává nástroj `available => false`.
9. **Akce a trhy** (#69): session naplní prvních ~40 akcí CZ/SK se stavem „ověřit“, Roman je po nasazení projde
   v `/admin/events`. Výchozí: tak.

## Hotovo, když

Každá ze čtyř větví: všechny nástroje z její části jsou v katalogu `/tools` a fungují od vstupu po cenu a
stažení; SEO stránky ve třech jazycích s vykreslenými ukázkami; `php -d memory_limit=2G vendor/bin/phpunit`
celé zelené; `pint --dirty` čistý; `npm run build` prošel; `docs/<písmeno>.md` s rozhodnutími, měřením a
nasazením; `main` slitý a větev pushnutá. Celkem: z 70 předloh má každá řádek v tabulce buď hotový nástroj,
nebo rozhodnutí Romana, proč ne. Tohle zadání leží i v `docs/prompts/nastroje.md` v repozitáři (zatím
necommitované v `C:\matplace-web-wt`) – první session ho commitne spolu se svou prací.
