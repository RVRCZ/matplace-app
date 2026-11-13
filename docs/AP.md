# AP. Návštěvnost: nástroje jako vstupní brány, vánoční nástroje, blog (session N1)

Zadání `docs/prompts/navstevnost-nastroje.md`, podklad `docs/prompts/priority-navstevnost-2026-10-27.md` (body 1, 3, 4).
Větev `feature/tools-seo-pages` z main 48fcf13, worktree `C:\matplace-content-wt`. Řídící session nasazuje po dávkách.
Cíl: návštěvnost a návraty, ne prodej. Od 27. 10. 2026.

## 1. Výběr 10 nástrojů (27. 10. 2026)

Search Console zatím není, všechny počty hledání jsou **odhad** ze znalosti trhu a z toho, co se vrací ve výsledcích
hledání (27. 10.). Stupnice: nízký = jednotky až desítky hledání měsíčně, střední = stovky, vysoký = tisíce a víc
(v EN globálně). Konkurence je stav z hledání 27. 10., bez ověření na webech konkurence.

| # | nástroj | hlavní dotaz cs | hlavní dotaz en | odhad cs / en | dávka |
|---|---|---|---|---|---|
| 1 | `svg_to_stl` | svg do stl | svg to stl converter free | nízký / vysoký | 1 |
| 2 | `repair` | oprava stl souboru online | repair stl file online free | nízký / vysoký | 1 |
| 3 | `check` | kontrola stl před tiskem | check stl file for 3d printing online | nízký / střední | 1 |
| 4 | `hollow` | vydutit 3d model | hollow stl online | nízký / střední | 2 |
| 5 | `split` | rozdělit stl na díly | split stl model online | nízký / střední | 2 |
| 6 | `gcode` | gcode do stl | gcode to stl converter | nízký / střední | 2 |
| 7 | `relief` | litofanie z fotky | lithophane maker online | střední / vysoký | 3 |
| 8 | `map` | 3d mapa města k tisku | 3d printed city map generator | nízký / střední | 3 |
| 9 | `qr` | qr kód 3d tisk | 3d printed qr code generator | nízký / střední | 3 |
| 10 | `organizer` | organizér do šuplíku na míru | drawer organizer generator 3d print | střední / střední | 3 |

### 1.1 Dotazy, konkurence, co stránka má a co chybí

**1. SVG do STL (`svg_to_stl`)**
- Vedlejší cs: „svg do 3d“, „logo na 3d tisk“, „png do stl“. En: „svg to 3d model“, „png to stl“, „extrude svg online“.
- Konkurence: svg2stl.com (zdarma, bez registrace, bez rozměrů v mm), převodníky Tripo a Meshy (zdarma, převod
  v prohlížeči; Meshy má i českou stránku „Bezplatný převodník SVG do 3D“), imagetostl.com, Tinkercad (účet).
  Naše výhoda: rozměry v milimetrech, upozornění na čáry pod 0,8 mm, zkosení, obrázek i text místo SVG, tisk.
- Má: title a H1 obsahu už na dotaz, 2 odstavce, 4 kroky, 6 otázek, 3 obrázky příkladů, cs/en/es.
- Chybí: H1 stránky (dnes jen „SVG do STL“), bloky Než objednáte / Pro koho / Kam dál, příklad s cenou, článek
  (`article` není), odkazy na podobné nástroje (logo, razítko, vykrajovátko).

**2. Oprava modelu (`repair`)**
- Vedlejší cs: „oprava 3d modelu zdarma“, „stl díry v síti“, „non manifold oprava“. En: „fix stl file“, „stl repair tool
  free no sign up“, „non-manifold mesh repair online“.
- Konkurence: formware.co (zdarma, bez účtu, limit 50 MB, 4 minuty), Meshy STL repair (účet, kredity), iamrapid.com
  (v prohlížeči, bez účtu), Microsoft 3D Builder a Meshmixer (programy). V češtině jen články (datanumen, Meshy cs).
  Naše výhoda: 120 MB, 3MF a OBJ, zpráva před a po, každé těleso zvlášť, bez účtu.
- Má: title na dotaz, 2 odstavce, 4 kroky, 5 otázek, článek `jak-pripravit-stl-soubor-pro-3d-tisk`.
- Chybí: H1 stránky („Oprava modelu“), bloky, příklad s čísly z nástroje (model před a po opravě), kam dál
  (kontrola, vydutění, rozdělení), odpověď na „slicer hlásí non-manifold“.

**3. Kontrola modelu (`check`)**
- Vedlejší cs: „stl kontrola tisknutelnosti“, „model tenké stěny 3d tisk“, „stl špatné jednotky“. En: „stl printability
  checker“, „is my stl printable“, „stl file validator online“.
- Konkurence: slabá. Netfabb online skončil, MakePrintable chce účet, Meshy jen analýza přes API. Checklisty v článcích
  (All3DP, 3DWithUs). Naše výhoda: jediný bezplatný online checker bez účtu, který řekne, co nález znamená pro tisk.
- Má: title na dotaz, 2 odstavce, 3 kroky, 5 otázek, článek `jak-pripravit-stl-soubor-pro-3d-tisk`.
- Chybí: H1 stránky („Kontrola modelu“), bloky, příklad (kontrola konkrétního souboru s nálezy), kam dál (oprava,
  rozdělení pro model, který se nevejde).

**4. Vydutit model (`hollow`)**
- Vedlejší cs: „dutý model 3d tisk“, „ušetřit filament velký model“, „vydutění pro pryskyřici“. En: „hollow out 3d
  model for printing“, „hollow stl with drain holes“, „make stl hollow free“.
- Konkurence: iamrapid hollowing (jen STL, bez účtu), jinak programy (Meshmixer, PreForm, Lychee, Chitubox).
  Nic v češtině. Naše výhoda: 3MF, OBJ, STEP, počet a průměr otvorů, ušetřené gramy a koruny hned.
- Má: title a H1 obsahu na dotaz, 2 odstavce, 4 kroky, 6 otázek, článek `rozdeleni-a-vyduteni-3d-modelu`.
- Chybí: H1 stránky („Vydutit model“), bloky, příklad s cenou (AK.md §5.7: váleček ⌀ 120 × 200 mm plný 910 Kč,
  dutý 500 Kč), kam dál.

**5. Rozdělit model (`split`)**
- Vedlejší cs: „model se nevejde na tiskárnu“, „rozřezat stl“, „velký výtisk z malé tiskárny“. En: „cut stl into parts
  online“, „model too big for print bed“, „split stl with pins“.
- Konkurence: PartFit 3D (zdarma, v prohlížeči, bez účtu), Model Splitter (beta, lokálně), Meshy splitter (kredity),
  Luban a Meshmixer (programy). Nic v češtině. Naše výhoda: kolíky nebo rybiny, čísla v řezu, mapa dílů, STEP.
- Má: title a H1 obsahu na dotaz, 2 odstavce, 4 kroky, 6 otázek, článek `rozdeleni-a-vyduteni-3d-modelu`.
- Chybí: H1 stránky („Rozdělit model“), bloky, příklad s cenou (AK.md §5.7: ⌀ 120 × 320 mm na 2 díly 1 440 Kč),
  kam dál (vydutění, životní velikost).

**6. Model z G‑kódu (`gcode`)**
- Vedlejší cs: „z gcode zpět stl“, „ztracený model mám jen gcode“, „gcode převod na 3mf“. En: „convert gcode back to
  stl“, „gcode to 3mf“, „recover model from gcode“.
- Konkurence: gcode2l.com (zdarma, malé soubory, hrubý výsledek), Voxelizer (placený), Cura export drátového modelu.
  Nic v češtině. Naše výhoda: 150 MB, vodotěsné těleso z vnějších stěn, díly po barvách, hlavička (slicer, čas, gramy).
- Má: title a H1 obsahu na dotaz, 2 odstavce, 4 kroky, 5 otázek.
- Chybí: článek (`article` není), H1 stránky („Model z G‑kódu“), bloky, příklad s čísly (reálný G‑kód z farmy:
  čas čtení, odchylka objemu), kam dál (kontrola, vydutění, kalkulace).

**7. Reliéf a litofanie (`relief`)**
- Vedlejší cs: „litofanie 3d tisk“, „lampa s fotkou 3d tisk“, „fotka do 3d“. En: „lithophane generator free“,
  „photo to lithophane stl“, „lithophane lamp maker“.
- Konkurence: silná v en: itslitho.com (zdarma pro osobní použití, předplatné), lithophanemaker.com (zdarma, chce
  e‑mail), 3dp.rocks, printpal. V češtině jen návody, žádný nástroj. Naše výhoda: sedm tvarů, lampa E27/E14, stojánek,
  fotku neukládáme, náhled proti světlu, tisk v bílé.
- Má: title na dotaz, 2 odstavce, 4 kroky, 6 otázek, článek `litofanie-z-fotky`.
- Chybí: H1 stránky („Reliéf a litofanie“), bloky, příklad s cenou (AK.md §5.6: panel 100 × 75 mm se stojánkem
  100 Kč, lampa ⌀ 100 × 150 mm 250 Kč), kam dál (busta, filament art, mapa).

**8. 3D mapa (`map`)**
- Vedlejší cs: „3d mapa města model“, „reliéf krajiny 3d tisk“, „mapa rodného města dárek“. En: „3d map stl from
  openstreetmap“, „terrain model stl generator“, „3d printed map of my city“.
- Konkurence: touch-mapper.org (hmatové mapy, zdarma), map2model, printpal map generator (účet, limity),
  cadmapper (placené), TouchTerrain (krajina, zdarma). Nic v češtině. Naše výhoda: město i krajina z názvu místa,
  tři barvy nad sebou, rám s názvem, hotové do minuty bez účtu (3 mapy denně).
- Má: title a H1 obsahu na dotaz, 2 odstavce, 4 kroky, 6 otázek, 3 obrázky, článek `3d-mapa-mesta-z-openstreetmap`.
- Chybí: H1 stránky („3D mapa“), bloky, příklad s cenou (AK.md §5.6: Staré Město 500 m, deska 150 mm, 240 Kč),
  kam dál.

**9. QR cedulka (`qr`)**
- Vedlejší cs: „qr kód wifi cedulka“, „qr kód 3d model“, „stojánek s qr kódem na stůl“. En: „qr code stl generator“,
  „3d printed wifi qr code“, „qr code sign 3d print“.
- Konkurence: printpal QR generator, 3dprintqrcode.com (zdarma, bez účtu), printertools „QR Code 2 STL“,
  parametrické modely na MakerWorld. Nic v češtině. Naše výhoda: kontrolní čtení kódu, Wi‑Fi bez technického zápisu,
  stojánek, dvoubarevný tisk u nás nebo projekt s výměnou.
- Má: landing bloky (AH.md §11), title na dotaz, 6 otázek, 3 obrázky, článek `qr-cedulka-a-wifi-stojanek`, cena 99 Kč.
- Chybí: H1 stránky („QR cedulka“), en/es title na dotaz, příklad v textu, kam dál rozšířit (cedulka, jmenovka).

**10. Organizér do šuplíku (`organizer`)**
- Vedlejší cs: „organizér do zásuvky 3d tisk“, „přihrádky do šuplíku na míru“, „organizér do zásuvky kuchyň“.
  En: „custom drawer organizer stl“, „drawer insert generator“, „drawer divider 3d print custom size“.
- Konkurence: parametrické generátory na MakerWorld (stažení s účtem Bambu), printpal bin generator, Gridfinity
  generátory. V češtině hotové modely na Printables, žádný generátor. Naše výhoda: rozměry zásuvky, hned náhled,
  bez účtu, tisk u nás.
- Má: landing bloky (AH.md §11), title i H1 obsahu na dotaz, 5 otázek, 3 obrázky, článek
  `organizer-do-supliku-na-miru`, cena 250 Kč.
- Chybí: H1 stránky („Organizér do šuplíku“), en/es title na dotaz, příklad v textu, kam dál rozšířit.

### 1.2 Co jsem vynechala a proč

- `figure` (busta z fotky): už je vstupní stránka z AH.md §11 (6 otázek, Pro koho, Kam dál, článek, ceny). V en je
  dotaz „photo to 3d model“ obsazený AI službami (Meshy, Tripo, Hitem) a náš nástroj má denní limit a souhlas s fotkou.
  Dostane jen title a H1 na dotaz, až budu u dárků (vánoční dávka).
- `cookie` (sušenka s polevou): audit psal „cookie (vykrajovátka)“, ale vykrajovátko je nástroj `cutter`. Sušenka je
  hračka bez hledanosti. Vykrajovátko na míru („vykrajovátko na míru“, „custom cookie cutter generator“, konkurence
  cookiecad.com zdarma se základem) patří do vánoční dávky (§3), kde dostane stejnou hloubku jako těchto deset.
- `check` zůstává navzdory nízké hledanosti: jediný bezplatný online checker bez účtu, který jsem v hledání
  nenašla u konkurence, a přirozená dvojice k opravě (sdílejí článek).

## 2. Rozhodnutí (27. 10. 2026)

1. **H1 stránky na dotaz, název nástroje zůstává.** H1 stránky nástroje je dnes `tools.<nástroj>.title`
   (`tools/page.blade.php`), tedy krátký název, který ukazují i karty v /tools (session 3) a odkazy. Název neměním,
   `lang/*/tools.php` nechávám session 3. Přidávám nepovinný klíč `heading` do `tools_seo/<nástroj>.php`: když je,
   stránka ho ukáže jako H1 a krátký název nástroje zůstane v řádku nad ním vedle odkazu „Nástroje“. Bez klíče se nic
   nemění (ostatních 48 nástrojů). Klíč `h1` zůstává nadpisem obsahu pod nástrojem (H2), jak byl.
2. **Texty jen přes `tools_seo`, `landing` a `config/landing.php`**; `config/tools.php` jen `article`.
3. **Příklad s cenou** beru z čísel, která už máme (AK.md §5.5 až §5.7, AH.md §11, kalkulace na produkci 18. 10.).
   Kde nejsou (SVG do STL, G‑kód, oprava, kontrola), udělám model na produkci jako návštěvník (User‑Agent
   `matplace-n1-seo`) nebo spustím nástroj lokálně a zapíšu, co vrátil.
4. **Fotka výtisku**: žádná generovaná. Místo pro fotku od N3 je položka v `config/site.php` `example_items`
   s `photo => null` (ukáže se až s fotkou a `SITE_EXAMPLES=true`, AH.md §11.3). Přidám položky pro nové nástroje
   s kódem kalkulace, aby N3 věděla, co fotit.
5. **Dávky**: 1 = svg_to_stl, repair, check; 2 = hollow, split, gcode; 3 = relief, map, qr, organizer. Po každé push
   a zpráva řídící.
6. Každý nástroj má jinou osnovu otázek podle toho, na co se lidé ptají (viz §1.1), žádný společný vzor.

## 3. Dávka 1: SVG do STL, oprava, kontrola (27. 10. 2026)

### 3.1 Co se změnilo

- `app/Support/ToolSeo.php`: `heading()` (H1 na dotaz, nepovinný klíč). `resources/views/tools/page.blade.php`: H1 je
  `heading`, když je; název nástroje pak stojí v řádku nad ním za odkazem „Nástroje“ (`id="tool-name"`). Ostatní
  stránky beze změny.
- `lang/{cs,en,es}/tools_seo/{svg_to_stl,repair,check}.php`: title a heading na dotaz, description do 155 znaků,
  dva odstavce (co umí, limity, příklad s čísly), kroky, 6 otázek s jinou osnovou pro každý nástroj.
- `lang/{cs,en,es}/landing.php` + `config/landing.php`: bloky Než objednáte (6 otázek, 2 s příznakem `farm`),
  Pro koho (3), Kam dál (podobné nástroje + články) pro všechny tři nástroje.
- `config/site.php`: položka `example_items` pro SVG do STL (kočka, `/c/lfa9iggn3hls`, `photo => null`, místo pro
  fotku od N3); text `site.examples.item.svg_to_stl` přes `lang/src/tools_seo_n1.json` a `lang_add.py`.
- `tests/Feature/LandingPagesTest.php`: tři nové stránky v seznamu `PAGES`.
- `config/tools.php` beze změny: SVG do STL článek nemá, dostane ho v blogu (§5), `article` doplním s ním.

### 3.2 Čísla a odkud jsou

| nástroj | příklad | zdroj |
|---|---|---|
| SVG do STL | kočka z knihovny 80 mm, vytažení 6 mm: 80 × 81 × 6 mm, 2 568 trojúhelníků, 17,7 cm³; 9,6 g PLA, 17 min, 99 Kč | produkce 27. 10. jako návštěvník (`POST /api/tools/param` kind logo, mode cutout; kalkulace PLA standard), User‑Agent `matplace-n1-seo`, `/c/lfa9iggn3hls` |
| oprava | busta 60 × 52 × 78 mm, 4 053 trojúhelníků, uměle rozbitá (3 díry, 25 zdvojených, 40 otočených ploch): 36 otevřených hran, 75 hran sdílených více plochami, 51 těles → 1 těleso, 4 068 trojúhelníků, objem 80,6 cm³, 1,4 s | `tests/fixtures/mold/bust.stl` rozbitá skriptem (scratchpad `repair/break.py`), nahraná na produkci (`/api/uploads`, `/api/files/{uuid}/repair`), stejná čísla dal lokální `repair_tool.py` (2,5 s) |
| kontrola | tatáž busta: rozměr v pořádku, chyba „síť není uzavřená“, doporučení „51 samostatných těles“ | produkce, `check` z `/api/files/{uuid}` |
| cena opravené busty „kolem 110 Kč, asi hodinu“ | odhad z busty 80 mm (99 Kč, 59 min, 14 g, AH.md §11) a objemu 80,6 cm³ (asi 100 g plná) | nepočítáno kalkulací, proto „kolem“ a „asi“ |

Rozbitá busta na produkci: soubory `ab03c6d8-c3eb-49df-8b12-4c69e37fc86c` (nahraná) a `318a9751-3e97-4845-be09-1791805a91f9` (opravená).

### 3.3 Ověření

- Worktree neměl `public/build/manifest.json`, první běh testů proto dal 500 na každé stránce; po `npm run build`
  spuštěno znovu: `LandingPagesTest|ToolPageTest|SeoTest|ToolsCatalogTest|AuditRestTest` (a CatalogSeoTest, který
  filtr chytil) **49 testů, 10 437 assertions, OK** (358 s). `pint --dirty` čistý. TypeScript se neměnil.
- Délky: title 50 až 59 znaků, description 151 až 157, heading 31 až 40 ve všech třech jazycích.
- JSON‑LD: na všech 9 stránkách (3 nástroje × cs/en/es) dva platné bloky, HowTo a FAQPage s 10 otázkami (6 z
  častých otázek + 4 z „Než objednáte“ bez příznaku farm; s otevřenou farmou 12). Ověřeno curl + json.loads na
  lokálním serveru.
- Sitemapa: `php artisan matplace:sitemap` lokálně, všechny tři stránky v `sitemap-tools-{cs,en,es}.xml`.
- Snímky `docs/img/seo-n1/{svg-to-stl,repair,check}-{390,1280}-cs-1.webp` (`scripts/review_round2.mjs`, lokální server
  na portu 8077; **port 8013 drží server jiné session**, proto první snímky ukázaly cizí worktree se starými texty).
  H1 je na dotaz, název nástroje v řádku nad ním, bez vodorovného posunu, bez chyb skriptu.
- Jen lokálně: SVG do STL na 390 px hlásí „Z těchto rozměrů se tvar nepodařilo vytvořit“ (na 1280 px model vznikne;
  na matplace.com je na 390 px v pořádku, ověřeno stejným skriptem). Lokální engine, ne texty; nic jsem s tím nedělala.

### 3.4 Co zůstalo

- Fotky výtisků (N3): kočka ze SVG (`example_items`), opravená busta není k focení (jen soubor).
- Článek pro SVG do STL (§5 blog) a `article` v `config/tools.php`.
- Nasazení: řídící; po něm Search Console per URL (`/tools/svg-to-stl`, `/tools/repair`, `/tools/check` ×3 jazyky).

## 4. Dávka 2: vydutit, rozdělit, model z G‑kódu (27. 10. 2026)

### 4.1 Co se změnilo

- `lang/{cs,en,es}/tools_seo/{hollow,split,gcode}.php`: title a heading na dotaz („Vydutit 3D model online zdarma“,
  „Rozdělit 3D model na díly online“, „Převod G‑kódu zpět na STL“; en „Hollow out a 3D model online, free“, „Split a 3D
  model into parts online“, „Convert G-code back to STL“), description do 155, dva odstavce s limity a příkladem,
  4 kroky, 6 otázek s vlastní osnovou (vydutit: kdy se vyplatí, stěna, otvory, tvar zvenku; rozdělit: kolíky vs. rybiny,
  lepení, viditelnost spoje; G‑kód: přesnost, formáty včetně .gcode.3mf, barvy, chybějící detaily, vázový režim).
- `lang/{cs,en,es}/landing.php` + `config/landing.php`: bloky pro všechny tři (6 otázek, 2 s `farm`; Pro koho 3; Kam dál
  na životní velikost, opravu, kontrolu a články `rozdeleni-a-vyduteni-3d-modelu`, `cena-3d-tisku-z-modelu`,
  `jak-pripravit-stl-soubor-pro-3d-tisk`).
- `tests/Feature/LandingPagesTest.php`: tři stránky v `PAGES` (`/tools/gcode-model` je adresa G‑kódu).
- `config/tools.php` beze změny: G‑kód článek nemá (blog §5), hollow a split mají `rozdeleni-a-vyduteni-3d-modelu`.
- `example_items` nepřidávám: čísla z AK.md §5.7 jsou bez kódu kalkulace a váleček není k focení.

### 4.2 Čísla a odkud jsou

| nástroj | příklad | zdroj |
|---|---|---|
| vydutit | váleček ⌀ 120 × 200 mm plný 503 g, 8 h 14 min, 910 Kč → stěna 2,5 mm, 3 otvory: 223 g, 6 h 36 min, 500 Kč | AK.md §5.7 (produkce 18. 10., PLA+) |
| vydutit, pryskyřice | plný 2,3 l, dutý asi 0,25 l | spočítáno z objemu (π·60²·200 = 2,26 l; plášť 98 000 mm² × 2,5 mm = 0,245 l), v textu označeno „spočítáno z objemu, ne tištěno“ |
| rozdělit | váleček ⌀ 120 × 320 mm, 1 řez, 2 díly + 4 kolíky: 814 g, 13 h 8 min, 1 440 Kč | AK.md §5.7 (produkce 18. 10., PLA+) |
| G‑kód | 5,6 MB, 191 475 řádků, 165 vrstev 0,2 mm, 36 min, 3,62 g → 91 × 57 × 16,5 mm, 522 384 trojúhelníků, 11,6 s; dvoubarevný 14 MB, 545 188 řádků, 39 vrstev 0,12 mm, 5 h 16 min, 81,5 g → color_1 48,6 g, color_2 32,9 g, 8,9 s | lokálně `engines/python/gcode_tool.py` na `Documents\MODELS\historie\gcode\source_PLA_0.2_35m59s.gcode` a `Nuclear 1_PLA_0.12_5h16m.gcode` (Anycubic Slicer Next, Kobra S1), 27. 10.; odchylka objemu 0,8 % je z AL.md (model z G‑kódu) |

Poznámka: STL v `Documents\MODELS\historie` jsou samy výstupy nástroje z 25. 10. (stejný počet trojúhelníků), původní
modely tam nejsou; proto se odchylka objemu nepřeměřovala a zůstala hodnota z AL.md.

### 4.3 Ověření

- `LandingPagesTest|ToolPageTest|SeoTest|ToolsCatalogTest` (+CatalogSeoTest): **43 testů, 10 537 assertions, OK**
  (290 s). `pint --dirty` čistý.
- Délky: title 52 až 60, description 144 až 155 (es/gcode 153 po zkrácení), heading 25 až 37.
- JSON‑LD na všech 9 stránkách: HowTo + FAQPage s 10 otázkami, platné. H1 na dotaz ve třech jazycích.
- Snímky `docs/img/seo-n1/{hollow,split,gcode-model}-{390,1280}-cs-1.webp`: bez vodorovného posunu, bez chyb skriptu.

### 4.4 Co zůstalo

- Článek pro G‑kód (blog §5) a jeho `article`; fotky nejsou potřeba (soubory, ne výtisky).
- Nasazení: řídící; Search Console per URL (`/tools/hollow`, `/tools/split`, `/tools/gcode-model` ×3 jazyky).

Řídící 27. 10.: dávka 1 sloučena do main 616cde5 a nasazena (server b943da6; konflikt jen v `lang/*.json`, vyřešen
znovu přes `lang_add tools_seo_n1.json`). Fotky výtisků dělá session 2 (`FarmOrder::photoUrl()`,
`PublicStats::latestPhotos()`), do příkladů je vybere Roman; `photo => null` zůstává. Opravuje bod 4 v §2, kde jsem
psala o N3. Main 616cde5 jsem sloučila do své větve (e718bd8, bez konfliktu).

## 5. Dávka 3: litofanie, mapa, QR cedulka, organizér (27. 10. 2026)

### 5.1 Co se změnilo

- `lang/{cs,en,es}/tools_seo/{relief,map,qr,organizer}.php`: title a heading na dotaz („Litofanie z fotky online
  zdarma“, „3D mapa města k tisku z názvu místa“, „QR kód na 3D tisk: cedulka se stojánkem“, „Organizér do šuplíku na
  míru“; en „Lithophane maker online, free“, „3D printed map of any city from its name“, „3D printed QR code: a sign
  with a stand“, „Drawer organizer made to your drawer“), description do 155, dva odstavce s limity a příkladem
  s cenou, 4 kroky, 6 otázek. U QR a organizéru otázky nepřekrývají bloky „Než objednáte“ z AH.md §11 (hlídá kontrola
  duplicit v §5.3); nové otázky: proč nástroj chce kód zvětšit, víko a dělicí stěny, tisk organizéru doma.
- `lang/{cs,en,es}/landing.php` + `config/landing.php`: bloky pro litofanii (světlo do lampy, tisk doma, fotka
  z telefonu) a mapu (rozsah, tisk doma, obec bez výšek budov, rám); QR a organizér bloky z AH.md §11 beze změny.
- `tests/Feature/LandingPagesTest.php`: relief a map v `PAGES` (10 stránek s bloky z 11 landing; `pet` v seznamu je).
- `config/tools.php` beze změny: všechny čtyři mají `article` (litofanie-z-fotky, 3d-mapa-mesta-z-openstreetmap,
  qr-cedulka-a-wifi-stojanek, organizer-do-supliku-na-miru).

### 5.2 Čísla a odkud jsou

| nástroj | příklad | zdroj |
|---|---|---|
| litofanie | panel 100 × 75 mm se stojánkem 27 g, 1 h 9 min, 100 Kč; lampa E27 ⌀ 100 × 150 mm 111 g, 2 h 29 min, 250 Kč | AK.md §5.6 (produkce 18. 10., PLA+, z vygenerovaného obrázku) |
| mapa | Staré Město 500 m, deska 150 mm, Hladká: 85 g, 3 h 42 min, 240 Kč jednou barvou; měřítko asi 1 : 3 300 (500 m / 150 mm) | AK.md §5.6 (produkce 18. 10.) |
| QR | web 70 × 83 × 3 mm 14 g, 25 min, 99 Kč (`/c/lomf0wzlshw4`); Wi‑Fi 90 mm se stojánkem 35 g, 1 h 1 min, 110 Kč | AH.md §11, AK.md §5.5 (produkce 18. 10.) |
| organizér | 200 × 120 × 40 mm 3 × 2: 115 g, 2 h 27 min, 250 Kč (`/c/l1gr0izblzwr`); 100 × 80 × 30 mm 2 × 2: 41 g, 57 min, 110 Kč; 300 × 400 × 50 hlásí přesah podložky | AH.md §11, AK.md §5.5 |

### 5.3 Ověření

- `LandingPagesTest|ToolPageTest|SeoTest|ToolsCatalogTest` (+CatalogSeoTest): první běh 42 OK a 1 chyba (en/organizer
  description 162 znaků), po zkrácení osmi en/es popisků `SeoTest` 12 testů OK; ostatní třídy se od prvního běhu
  neměnily (jen řetězce description). `pint --dirty` čistý.
- Délky: title 54 až 60, description 144 až 155, heading 28 až 50 (es organizér).
- Kontrola duplicit: žádná otázka se neopakuje mezi častými otázkami a blokem Než objednáte (QR a organizér mají
  bloky z AH.md §11).
- JSON‑LD na 12 stránkách: HowTo + FAQPage s 10 otázkami, platné; H1 na dotaz ve třech jazycích.
- Snímky `docs/img/seo-n1/{relief,map,qr,organizer}-{390,1280}-cs-1.webp`: bez vodorovného posunu, bez chyb skriptu.

### 5.4 Co zůstalo

- Tím je hotových všech 10 stránek z §1. Nasazení: řídící; pak Search Console per URL ×3 jazyky.
- Fotky výtisků vybere Roman (session 2), `photo` v `example_items` zůstává null.
- Dál: vánoční nástroje (§6), blog (§7), prolinkování (§8).

Řídící 27. 10.: dávka 2 sloučena (main d5b9a8b) a nasazena (server 94f4db8), dávka 3 sloučena (main 1ea8093) a nasazuje
se; všech 10 stránek je na produkci. Po příštím merge main udělat `npm run build` (kalkulace bere konfiguraci
z `/api/calc-config`).

## 6. Vánoční nástroje: perníček, ozdoba, vykrajovátko, jmenovka, zápich, písmeno (27. 10. 2026)

Zadání bod 3: texty na dotazy „vykrajovátko na míru“, „vánoční ozdoba se jménem“, „jmenovka na dárek 3D tisk“ a bloky
s cenou z kalkulace. Nástroje: `gingerbread` (vánoční ozdoba se jménem), `ornament` (vánoční ozdoba z obrázku),
`cutter` (vykrajovátko na míru; zadání psalo „cookie“, viz §1.2), `nameplate` (jmenovka na dárek; `sign` je rychlý
formulář téhož a zůstává v „Kam dál“), `topper` (zápich do dortu, i na Silvestra), `name_letter` (písmeno se jménem jako
dárek). Dva vánoční články jsou v blogu (§7).

### 6.1 Co se změnilo

- `lang/{cs,en,es}/tools_seo/{gingerbread,ornament,cutter,nameplate,topper,name_letter}.php`: title a heading na
  dotaz (cs „Vánoční ozdoba se jménem: perníček na stromeček“, „Vánoční ozdoba na 3D tisk z obrázku nebo jména“,
  „Vykrajovátko na míru ze jména nebo kresby“, „Jmenovka na dárek z 3D tiskárny“, „Zápich do dortu se jménem a číslem“,
  „Velké písmeno se jménem jako dárek“; en „Christmas ornament with a name: a gingerbread for the tree“, „3D printed
  Christmas ornament from a picture or a name“, „Custom cookie cutter from a name or a drawing“, „3D printed gift tag
  with a name“, „Cake topper with a name and a number“, „A big letter with a name as a gift“), description do 155,
  druhý odstavec s cenou z kalkulace, 6 otázek; otázka o platbě nahrazena užitečnou (délka jména a rok, velikost ozdoby
  na stromeček, velikost vykrajovátka, sada jmenovek, rok na zápichu, datum místo jména).
- `lang/{cs,en,es}/landing.php` + `config/landing.php`: bloky pro všech šest (6 otázek, 2 s `farm`: termín do Vánoc,
  doprava, cena; 3 použití), Kam dál mezi vánočními nástroji a na články `vanocni-ozdoba-z-obrazku`,
  `cedulka-jmenovka-a-klicenka-se-jmenem`, `vykrajovatko-na-cukrovi-podle-vlastniho-tvaru`,
  `klicenka-a-privesek-z-obrazku`.
- `tests/Feature/LandingPagesTest.php`: šest stránek v `PAGES` (celkem 17 z 17 v `config/landing.php`).
- `config/tools.php` beze změny: gingerbread, nameplate, topper a name_letter článek nemají (vánoční články §7).

### 6.2 Čísla a odkud jsou

| nástroj | příklad | zdroj |
|---|---|---|
| perníček | panáček 90 mm „Ela“: 10,8 g, 20 min, 99 Kč (`/c/krswdwunexsg`); 6 kusů 140 Kč (`/c/qkmqrfmbzaln`) | produkce 27. 10., PLA, User‑Agent `matplace-n1-seo` |
| ozdoba | perníček z knihovny 80 mm: 10 g, 18 min, 99 Kč; 6 kusů 160 Kč | AK.md §5.7 (produkce 18. 10., PLA+) |
| vykrajovátko | „Ela“ 82 × 54 × 18 mm: 13 g, 31 min, 99 Kč; „5“ 72 × 93 mm: 12 g, 25 min, 99 Kč | AK.md §5.7 |
| jmenovka | šestiúhelník 120 × 60 mm „Adéla“ s hvězdou: 14,5 g, 25 min, 99 Kč (`/c/v9fh1s1ogsob`); jmenovka na dárek 70 mm s očkem „Pro Elu“: 5,2 g, 10 min, 99 Kč, 10 kusů 120 Kč (`/c/udkkubsktt9d`) | produkce 27. 10., PLA (kind compose, preset plate / vlastní vrstvy) |
| zápich | „2“ 110 mm + „Olivia“, dva hroty 60 mm: 30,1 g, 55 min, 99 Kč (`/c/4jdvyezywc3l`) | produkce 27. 10., PLA |
| písmeno | „E“ 120 mm, 8 mm, „Ela“: 27 g, 40 min, 99 Kč (`/c/vhfifzrzy5in`) | produkce 27. 10., PLA |

Kalkulace na produkci počítá jednu barvu (PLA, profil farmy); druhou barvu vybírá zákazník při objednávce. Rady
o tisku doma (pauza ve výšce polevy, obrubou dolů, matné filamenty) vycházejí z textů nástrojů a z AK.md, ne z nového
tisku; nic z této dávky se netisklo.

### 6.3 Ověření

- `LandingPagesTest|ToolPageTest|SeoTest|ToolsCatalogTest` (+CatalogSeoTest): **43 testů, 11 289 assertions, OK**
  (303 s). `pint --dirty` čistý.
- Délky: title 53 až 60, description 140 až 152, heading 28 až 61; otázky bez duplicit mezi častými otázkami a blokem;
  příznaky `farm` stejné ve třech jazycích; všechny podobné nástroje veřejné.
- JSON‑LD na 18 stránkách (6 nástrojů × 3 jazyky): HowTo + FAQPage s 10 otázkami, platné; H1 na dotaz.
- Snímky `docs/img/seo-n1/{gingerbread,ornament,cookie-cutter,nameplate,cake-topper,name-letter}-{390,1280}-cs-1.webp`:
  bez vodorovného posunu, bez chyb skriptu.

### 6.4 Co zůstalo

- Dva vánoční články (§7, termíny 6. a 13. 11.) a `article` pro gingerbread, nameplate, topper, name_letter s nimi.
- Vánoční Shorts dělá session 2 (zadání bod 3 je sdílené).

## 7. Blog: 1 až 2 články týdně (plán 27. 10. 2026)

### 7.1 Co blog umí a co chybí

- **Datum vydání dopředu funguje bez úpravy**: `Post::published()` bere jen `published_at <= now()` a `blog:sync`
  zapíše `published_at` z hlavičky jen novému článku. Článek s budoucím datem v hlavičce se po `blog:sync` objeví sám
  v daný den (výpis, detail, sitemapa, RSS); do té doby je koncept (404 pro návštěvníka, admin ho vidí). Rozložení
  publikace je tedy jen věc dat v hlavičkách.
- **Video a kalkulace do článku nejdou vložit**: Markdown se renderuje s `html_input => strip`, takže iframe ani
  vlastní HTML v těle neprojde (správně). Doplním krátké značky na vlastním řádku, které `Post::html()` nahradí
  šablonou: `[video: <youtube_id>]` nebo `[video: latest]` (zveřejněné `FarmVideo` s tím id, tmavý rámeček s názvem
  a tlačítkem, YouTube nocookie až po kliknutí jako na stránkách nástrojů a na hlavní stránce; bez takového videa se
  nevykreslí nic) a `[calc: <kód>]` (kalkulace `/c/<kód>`, hotová: karta s rozměry, gramy, dobou tisku a cenou
  a odkazem; jinak jen odkaz). Zástupná slova na vlastním řádku projdou Markdownem jako odstavec a ten se vymění.
- Produkce má na hlavní stránce zveřejněné video `2jtLXyMz3Sk` (poslední zveřejněné); seznam videí kanálu se odsud
  přečíst nedá (stránka YouTube je jen skript), další id dodá řídící z `/admin/youtube`.

### 7.2 Šest článků a termíny (`published_at` v hlavičce, UTC)

| # | slug | dotaz / data | vydání |
|---|---|---|---|
| 1 | `svg-do-stl-logo-a-kresba-jako-3d-model` | „svg do stl“ (odhad), nástroj SVG do STL, `/c/lfa9iggn3hls` | út 3. 11. 2026 07:00 |
| 2 | `vanocni-ozdoby-se-jmenem-z-3d-tiskarny` | „vánoční ozdoba se jménem“ (odhad), perníček + ozdoba + písmeno, `/c/krswdwunexsg`, `/c/qkmqrfmbzaln` | pá 6. 11. 2026 07:00 |
| 3 | `jak-presne-odhadneme-cenu-bez-rezu-400-kalkulaci` | data: `docs/data/odhad-vs-rez-2026-10-25-prod-overhang.csv`, M.md §6 | út 10. 11. 2026 07:00 |
| 4 | `jmenovky-na-darky-a-vykrajovatka-vanocni-dilna` | „jmenovka na dárek 3D tisk“, „vykrajovátko na míru“ (odhad), `/c/udkkubsktt9d`, `/c/v9fh1s1ogsob` | pá 13. 11. 2026 07:00 |
| 5 | `gcode-zpet-na-stl-kdyz-se-ztratil-model` | „gcode do stl“ (odhad), nástroj Model z G‑kódu, čísla z §4.2 | út 17. 11. 2026 07:00 |
| 6 | `co-lide-tisknou-nejcasteji-cisla-z-farmy` | data: `PublicStats` + výskyt nástrojů v kalkulacích a zakázkách (export od řídící, viz 7.4) | pá 20. 11. 2026 07:00 |

Každý: místo pro 1 fotku výtisku (`<!-- foto: … -->`, fotky vybere Roman ze session 2), `[video: …]`, `[calc: …]`,
blok „Co dál“ s odkazem na nástroj; nástroj dostane `article` v `config/tools.php` (svg_to_stl → 1, gingerbread
a name_letter → 2, gcode → 5, nameplate a topper → 4).

### 7.3 Čísla pro článek 3 (CSV 400 kalkulací z produkce 26. 9. až 25. 10. 2026, spočítáno 27. 10.)

- gramy: odhad proti řezu medián +0,2 %, medián absolutní odchylky 5,9 %, do 10 % v 71 %, do 20 % v 82 % kalkulací;
- minuty: medián −11,6 % (odhad bývá kratší), medián absolutní odchylky 23,6 %, do 20 % ve 46 %, do 30 % v 62 %;
  podle největšího rozměru: do 50 mm 26 %, 50 až 100 mm 29 %, 100 až 200 mm 13 %, nad 200 mm 21 % (n 31/162/178/29);
- cena: skutečná v rozpětí, které stránka ukázala, 329/400 (82 %), odhad přesně rovný výsledku 131× (33 %); nové
  rozpětí podle tvaru (M.md §6.3) 96,8 %;
- řez: medián 5,2 s, p90 36 s, nejdéle 272 s; trojúhelníky medián 39 656, nejvíc 2,7 milionu; podpěry slicer použil
  u 65 ze 400; kvalita standard 390, fine 10; výplň 15 % u 391;
- výsledná cena: medián 140 Kč, čtvrtiny 120 a 240 Kč, nejvíc 1 750 Kč, 12 % kalkulací na minimu 99 Kč.

### 7.4 Co je hotové (27. 10. 2026)

- **Vložky**: `app/Support/BlogEmbeds.php` (řádek `[video: id|latest]` a `[calc: kód]` → zástupné slovo před
  Markdownem, výměna odstavce za šablonu po něm; `\s*$` na konci vzoru by snědlo prázdný řádek a slepilo slovo
  s dalším odstavcem, proto jen mezery), `Post::html()` ho volá, šablony `blog/_video.blade.php` (tmavý rámeček
  s názvem, nocookie až po kliknutí jako `calculator/_trust`) a `blog/_calc.blade.php` (karta: soubor, rozměry,
  gramy, doba, cena v Kč z uložených cen, odkaz; bez hotové kalkulace jen odkaz). `tests/Feature/BlogEmbedsTest.php`
  (3 testy: video nic → rámeček, kalkulace odkaz → karta, cizí HTML zůstává odstraněné).
- **Články 1 až 5** v `resources/blog/cs/` s `published_at` 3., 6., 10., 13. a 17. 11. 2026 07:00 UTC; každý 650 až
  750 slov, místo pro fotku, `[video: latest]`, `[calc: …]` (1, 2, 4), blok Co dál. `config/tools.php` `article`:
  svg_to_stl → 1, gingerbread a name_letter → 2, nameplate a topper → 4, gcode → 5; `config/landing.php` má nové
  slugy v „Kam dál“ (ukážou se až po vydání). Článek 6 čeká na export (§7.4).
- Ověřeno: `BlogEmbedsTest|BlogSyncTest` 12 testů OK; lokální `blog:sync` článků 1, 3 a 4 vykreslí 694/743/654 slov
  bez zástupných slov, s odkazy na kalkulace (lokální databáze kalkulace nemá, na produkci budou karty), bez iframe.
- Po nasazení: `php artisan blog:sync` na serveru (články vzniknou jako koncepty s budoucím datem a vyjdou samy);
  `[video: latest]` tam ukáže poslední zveřejněné video farmy.

### 7.5 Článek 6 z exportu řídící (28. 10. 2026)

Řídící dodala `docs/data/co-lide-tisknou-2026-10-27.json` (main 60983f3; jen souhrnné počty; `origin_ref`
generovaných modelů je náhodný token a do článku nepatří). Článek `co-lide-tisknou-nejcasteji-cisla-z-farmy`
(20. 11.): 498 kalkulací za 90 dní ve 40 nejčastějších zdrojích (nahrané 191 = 38 %, nástroje 234 = 47 %, generované
73 = 15 %), pořadí nástrojů (cedulka 33, medaile 31, QR 28, stojánek 17, litofanie 14 = `lithophane`, krabička 13…),
105 zaplacených zakázek (nahrané 50, nástroje 43, generované 12; medaile 9, stojánek 7, QR 7, víčko 4…), materiály
PLA 68 + PLA+ 33 = 96 %, PETG 3, ASA 1; `PublicStats`: 67 předaných, 61 za 30 dní, medián 0,5 dne od zaplacení
k předání, 33 videí. Video litofanie `2GqbDiH_mGg`; v článku o jmenovkách video cedulky `upvCotgDNwo` (obě z řídící).
Součty nástrojů a generovaných jsem sečetla z exportu; jiná čísla v článku nejsou.

Videa zveřejněná (řídící 28. 10.): 2GqbDiH_mGg litofanie 22. 10., upvCotgDNwo cedulka 19. 10., Fd72RTnqYDE medaile
15. 10., NDmJZIuyWvM QR 14. 10., auh4D7fI06Q váza 22. 10. Perníček ani ozdoba natočené nejsou.

Poznámka řídící: moje kalkulace na produkci (UA `matplace-n1-seo`) se počítaly jako návštěvník; skript teď posílá
cookie `mp_test=1` (`Track::TEST_COOKIE`).

## 8. Vstupní stránka „3D tisk Praha“ a Google Business Profile (28. 10. 2026)

Roman (přes řídící 28. 10.): město farmy je Praha (sídlo Rybná 716/24, Praha 1, v Organization JSON‑LD od session 3).

- `/3d-tisk-praha` (`PageController::PAGES` `'praha' => '3d-tisk-praha'`, route `pages.praha`, stejná adresa ve
  třech jazycích jako u `/business`), texty `lang/{cs,en,es}/pages.php` `praha`: title do 60, description do 155,
  H1 „3D tisk v Praze na zakázku“, úvod, oddíly Co v Praze tiskneme (karty 8 nástrojů), Kolik stojí (příklady
  z `site.example_items`), Doručení po Praze a Česku (Zásilkovna :cz_point / :cz_home, boxy, **osobní odběr zatím
  nenabízíme**), Jak objednat, Kdo tiskne (firma, IČO, adresa, odkaz na /business), 6 otázek (FAQPage). Jen pravda
  z webu: tiskneme v Praze, PLA/PETG/ASA/TPU, podložka 250 mm, 99 Kč minimum, doklad s DPH; žádná otevírací doba,
  žádný odběr, žádný telefon. Canonical a hreflang dává layout, sitemapa bere `PAGES` sama.
- `tests/Feature/PraguePageTest.php`: tři jazyky, délky, H1, karty nástrojů, FAQPage, hreflang, canonical, bez
  noindex, doplněné ceny dopravy, věta o odběru, ceny v Kč a v eurech, sitemapa.
- `docs/data/google-business.md`: typ profilu (firma s oblastí působení, adresa skrytá, dokud není odběr), název
  jen `matplace` (pravidla Googlu), kategorie, popis 651 znaků, odkazy s utm, otevírací doba a telefon na rozhodnutí
  Romana, služby s cenami z kalkulací, atributy, fotky jen skutečné, příspěvky 1× za 2 týdny.

## 9. Dalších 10 nástrojů (plán 28. 10. 2026)

Řídící 28. 10.: kandidáti phone_stand, box, holder, sign, nameplate (hotový v §6), keychain, vase, stamp, logo,
lightbox, medallion, puzzle. Fotky výtisků dává na stránky session 3 automaticky (`PublicStats::latestPhotos`), v textech
je neřeším. K hledanosti (odhad, Search Console stále není) přibyla dvě vlastní čísla z exportu §7.5: kalkulace za
90 dní a zaplacené zakázky.

| # | nástroj | hlavní dotaz cs | hlavní dotaz en | odhad cs / en | kalkulací / zakázek | dávka |
|---|---|---|---|---|---|---|
| 1 | `phone_stand` | stojánek na telefon 3d tisk | phone stand 3d print custom | střední / vysoký | 17 / 7 | A |
| 2 | `sign` | cedulka se jménem 3d tisk | 3d printed name sign | střední / vysoký | 33 / 3 | A |
| 3 | `keychain` | klíčenka se jménem 3d tisk | custom keychain 3d print | střední / vysoký | (charm 6) / 2 | A |
| 4 | `box` | krabička na míru 3d tisk | custom box generator 3d print | střední / střední | 13 / 1 | B |
| 5 | `medallion` | medaile na míru 3d tisk | custom medal 3d print | střední / střední | 31 / 9 | B |
| 6 | `vase` | váza 3d tisk | 3d printed vase generator | střední / vysoký | 11 / 3 | B |
| 7 | `logo` | logo 3d tisk | 3d printed logo sign | střední / vysoký | 4 / 0 | C |
| 8 | `holder` | držák na zeď na míru 3d tisk | custom wall mount 3d print | nízký / střední | 11 / 1 | C |
| 9 | `stamp` | razítko na míru 3d tisk | custom stamp 3d print | nízký / střední | 0 / 0 | C |
| 10 | `lightbox` | světelný nápis 3d tisk | lightbox sign 3d print | nízký / střední | 0 / 0 | C |

Náhradník: `puzzle` („puzzle z fotky 3d tisk“ nízký, en „3d printed puzzle generator“ střední), `cap` (víčko:
8 kalkulací a 4 zakázky, ale hledanost „víčko na míru“ nízká).

Pořadí: podle hledanosti a podle toho, co lidé opravdu počítají a objednávají (stojánek a medaile jsou nejobjednávanější
nástroje, cedulka nejpočítanější). Dávky po třech až čtyřech jako dosud, po každé push a zpráva. Čísla, která už máme:
stojánek klín 80 mm 120 Kč a A‑rám 170 Kč, váza hladká 190 Kč / spirálová 280 Kč / obal 260 Kč, cedulka klíčenka
99 Kč a „Novákovi 12“ 99 Kč, krabička 99/110 Kč, držák 100/99/99 Kč, klíčenka z obrázku 99 Kč (AK.md §5.5 až §5.7),
medaile zkušební `/c/naze69mzmy0h` (P.md §8d). Logo, razítko a světelný nápis spočítám na produkci (s `mp_test=1`).

### 9.1 Dávka A: stojánek, cedulka, klíčenka (28. 10. 2026)

- `lang/{cs,en,es}/tools_seo/{phone_stand,sign,keychain}.php`: title a heading na dotaz („Stojánek na telefon na míru
  k 3D tisku“, „Cedulka se jménem na 3D tisk“, „Klíčenka se jménem na 3D tisk“; en „Custom phone stand to 3D print“,
  „3D printed name sign with your text“, „Custom keychain with a name to 3D print“), description do 155, druhý
  odstavec s čísly, 6 otázek (nové: jaký sklon, cena pro třídu, velikost klíčenky); stojánek má 8 obrázků příkladů,
  popisky zůstaly a první dva dostaly čísla.
- `lang/{cs,en,es}/landing.php` + `config/landing.php` + `LandingPagesTest::PAGES` (20 stránek).
- Čísla: stojánek klín 80 mm 39 g, 1 h 8 min, 120 Kč a A‑rám 75 mm 65 g, 1 h 45 min, 170 Kč (AK.md §5.6); cedulka
  klíčenka „Jana“ 2 g, 5 min, 99 Kč, 10 ks 99 Kč, dveře „Novákovi 12“ 30 g, 46 min, 99 Kč (AK.md §5.6); klíčenka
  kost 70 mm „Rex“ 3,8 g, 10 min, 99 Kč (`/c/fwgocmbyzfx6`), 10 ks 100 Kč (`/c/teyrxpsrhi4k`), produkce 28. 10.
  s `mp_test=1`. „Nejčastěji počítaný“ (cedulka 33) a „po medaili nejobjednávanější“ (stojánek 7) jsou z §7.5.
- Ověření: `LandingPagesTest|SeoTest|ToolsCatalogTest|BlogSyncTest` (+CatalogSeoTest) **30 testů, 7 800 assertions, OK**;
  `pint` čistý; title 51 až 60, description 138 až 152; JSON‑LD HowTo + FAQPage 10 otázek na 9 stránkách; snímky
  `docs/img/seo-n1/{phone-stand,sign,keychain}-{390,1280}-cs-1.webp` bez posunu a chyb.

### 9.2 Dávka B: krabička, medaile, váza (28. 10. 2026)

- `lang/{cs,en,es}/tools_seo/{box,medallion,vase}.php`: title a heading na dotaz („Krabička na míru k 3D tisku“,
  „Medaile na míru k 3D tisku s řetězem“, „Váza na 3D tisk podle vašich rozměrů“; en „Custom box to 3D print, sized to
  what goes inside“, „Custom medal with a chain to 3D print“, „3D printed vase to your own size“), description do
  155, druhý odstavec s čísly, 6 otázek (nové: krabička na Arduino, proč spirála stojí víc, vázový režim).
- Krabička má bloky z AH.md §11 (6 otázek), proto její časté otázky bloky neopakují; medaile a váza dostaly nové
  bloky v `lang/{cs,en,es}/landing.php` + `config/landing.php`; `LandingPagesTest::PAGES` 22 stránek (landing 25).
- Čísla: krabička 80 × 50 × 30 mm 28 g, 34 min, 99 Kč (`/c/nku29wz4vieb`), s víčkem a výřezem 41 g, 51 min, 110 Kč
  (AK.md §5.5, AH.md §11); medaile 60 mm s řetězem 40 článků 195 × 177 mm, 40,8 g, 1 h 42 min, 130 Kč
  (`/c/naze69mzmy0h`, P.md §8d, přečteno z produkce 28. 10.); váza hladká 160 mm 76 g, 1 h 57 min, 190 Kč, spirálová
  180 mm 80 g, 5 h 7 min, 280 Kč, obal 120 mm 99 g, 3 h 45 min, 260 Kč (AK.md §5.6). „Nejčastěji objednávaný“
  (medaile 9 zakázek, 31 kalkulací) a „13 krabiček za 90 dní“ z §7.5.
- Ověření: `LandingPagesTest|SeoTest|ToolsCatalogTest` (+CatalogSeoTest) **21 testů, 7 186 assertions, OK**; `pint`
  čistý; title 53 až 60, description 145 až 154; JSON‑LD HowTo + FAQPage 10 otázek na 9 stránkách; snímky
  `docs/img/seo-n1/{box,medallion,vase}-{390,1280}-cs-1.webp` bez posunu a chyb.

Řídící 28. 10.: 7d00604b a ecc8a530 sloučeny (main 61c48774) a nasazeny (server cb0cd4f7); `blog:sync` na produkci
naplánoval 6 článků (3. až 20. 11.), sitemapa obnovena, `/3d-tisk-praha` cs/en 200. Další merge main až po dávce C.

### 9.3 Dávka C: logo, držák, razítko, světelný nápis (28. 10. 2026)

- `lang/{cs,en,es}/tools_seo/{logo,holder,stamp,lightbox}.php`: title a heading na dotaz („Logo na 3D tisk z SVG,
  obrázku nebo textu“, „Držák na zeď na míru k 3D tisku“, „Razítko na míru k 3D tisku do hlíny a modelovací hmoty“,
  „Světelný nápis na 3D tisk s LED páskem“; en „3D printed logo sign from your SVG, picture or text“, „Custom wall
  mount to 3D print“, „Custom stamp to 3D print for clay and polymer clay“, „3D printed lightbox sign with an LED
  strip“), description do 155, druhý odstavec s čísly, 6 otázek (nové: velikost loga na dveře a recepci, držák
  v koupelně a venku, tisk a čištění razítka, zavěšení nápisu).
- `lang/{cs,en,es}/landing.php` + `config/landing.php` (4 nové bloky), `LandingPagesTest::PAGES` 26 stránek
  (landing 29); světelný nápis má adresu `/tools/illuminated-sign`.
- Čísla z produkce 28. 10. (PLA, `mp_test=1`): logo reliéf 100 mm s kočkou 27,6 g, 43 min, 99 Kč (`/c/11vhrvmivr3e`);
  razítko EVA 50 mm s držadlem 6,8 g, 28 min, 99 Kč (`/c/qr5iss66z3ip`); nápis OPEN 180 × 64 × 37 mm, 4 díly, 96,7 g,
  2 h 17 min, 180 Kč v jedné barvě (`/c/haqwdtiseafu`); držák kolébka 27 g, 1 h 12 min, 100 Kč, hák 11 g, 27 min,
  99 Kč, klip 8 g, 21 min, 99 Kč (AK.md §5.7). „11 držáků za 90 dní“ z §7.5.
- Ověření: `LandingPagesTest|SeoTest|ToolsCatalogTest|BlogSyncTest` (+CatalogSeoTest) **30 testů, 8 364 assertions,
  OK**; `pint` čistý; title 54 až 60, description 139 až 153; JSON‑LD HowTo + FAQPage 10 otázek na 12 stránkách;
  snímky `docs/img/seo-n1/{logo,holder,stamp,illuminated-sign}-{390,1280}-cs-1.webp` bez posunu a chyb.
- Po dávce C sloučen `origin/main` 604b8248 do větve (f252e3ba, bez konfliktů; přišly fotky výtisků relace 3,
  `ToolPhotos`), `npm run build`, pak celá sada `LandingPagesTest|SeoTest|ToolsCatalogTest|BlogSyncTest|PraguePageTest|
  BlogEmbedsTest|ToolPageTest` (+CatalogSeoTest) **56 testů, 13 040 assertions, OK** (240 s).
- Věta „Jedna zakázka se u nás tiskne jednou barvou“ (difuzor v bílé jako druhá zakázka) podle řídícího už neplatí:
  farma tiskne díly jedné zakázky v různých barvách (každý díl ze své cívky, nebo jedna podložka na tiskárně
  s měničem, AL.md §10). Opraveno 28. 10. 2026 v úvodu i FAQ `tools_seo/lightbox.php` a v otázce „Kolik stojí díly
  u vás?“ v `landing.php` (cs/en/es): difuzor bílý nebo průsvitný, tělo tmavé, barvu každého dílu vyberete při
  objednávce v jedné zakázce.

## 10. Komunitní příspěvky (28. 10. 2026)

`docs/data/komunita-prispevky.md`: šest odpovědí cs + en (oprava STL, SVG do STL, G‑kód zpět, vydutit, rozdělit,
litofanie) jako odpověď pod dotaz, s hranicemi nástroje a jedním odkazem s `utm_source` (facebook / reddit /
printables), bez ceny tisku u nás. Roman je posílá sám.

## 11. Stav zadání (28. 10. 2026)

| bod zadání | stav |
|---|---|
| 1. výběr 10 nástrojů s dotazy | hotovo, §1 (odhady bez Search Console) |
| 2. stránky 10 nástrojů | hotovo, §3 až §5, vše sloučeno a nasazeno řídící |
| 3. vánoční nástroje do poloviny listopadu | hotovo, §6 (6 nástrojů), nasazeno; vánoční Shorts dělá session 2 |
| 4. blog 1 až 2 týdně, 6 článků, 2 z dat farmy | hotovo, §7: články 3., 6., 10., 13., 17. a 20. 11. naplánované na produkci; vložky video a kalkulace; `blog:sync` datum dopředu umí bez úpravy |
| 5. prolinkování | hotovo: `article` u 29 nástrojů (6 nových článků ↔ svg_to_stl, gingerbread, name_letter, nameplate, topper, gcode), „Kam dál“ v `config/landing.php` u 29 stránek, každý článek v „Co dál“ odkazuje na svůj nástroj (hlídá `BlogSyncTest`) |
| navíc od řídící | `/3d-tisk-praha` + `google-business.md` (§8), dalších 10 nástrojů (§9, dávky A, B, C), `komunita-prispevky.md` (§10), druhá desítka nástrojů (§12, dávky D, E, F), 4 články na 24. 11. až 4. 12. a vložka `[farm:]` (§13) |

Co zůstává na jiných: fotky výtisků (session 2, Roman vybere), Search Console export (Roman) a podle něj druhé kolo
title/description, Google Business Profile založí Roman, vánoční Shorts session 2.

## 12. Dalších 10 nástrojů, druhé kolo (plán 28. 10. 2026)

Řídící po dávce C: dalších 10 stránek v pořadí podle potenciálu, po dávkách 3 až 4. Zbývající nástroje
s `available => true` v `config/tools.php`: charm, earrings, bag_charm, beads, magnet, coaster, cap, cable_holder,
text, notes, tray, puzzle, filament_art, papel, compose, modular, soap_model. Mosaic, insert, straw a opener mají
`available => false`, stránku nemají, vynechány.

Odhady stejnou stupnicí jako v §1. Search Console už od relace 3 sbírá řádky do `search_console_rows` (AN.md §14),
jen na produkci; požádal jsem řídícího o výpis dotazů na stránky nástrojů. Odpověď 28. 10.: za 16 měsíců (4 354
řádků) nemá žádná stránka nástroje ani jedno zobrazení; vyhledávání vidí jen hlavní stránku (394 zobrazení,
84 kliknutí) a stránky katalogu `/model/…` (desítky zobrazení, skoro 0 kliknutí, často `?lang=en`). Pořadí tedy
zůstává podle odhadů; data k nástrojům přijdou až po indexaci nových stránek, řídící je sleduje týdně. Title
a popis stránek katalogu (dnes název modelu z Printables) stojí za úpravu, ale patří relaci 3.

| # | nástroj | hlavní dotaz cs | hlavní dotaz en | odhad cs / en | dávka |
|---|---|---|---|---|---|
| 1 | `text` | 3d nápis na poličku tisk | 3d text generator for 3d printing | střední / vysoký | D |
| 2 | `filament_art` | obraz z filamentu | filament painting generator free | nízký / vysoký | D |
| 3 | `modular` | modulární organizér do zásuvky | modular drawer organizer generator | nízký / střední | D |
| 4 | `cap` | víčko na míru 3d tisk | threaded cap generator 3d print | nízký / střední | E |
| 5 | `coaster` | podtácek s fotkou 3d tisk | custom coaster 3d print generator | nízký / střední | E |
| 6 | `magnet` | magnetka z fotky | custom fridge magnet 3d print | střední / střední | E |
| 7 | `earrings` | náušnice 3d tisk vlastní | 3d printed earrings generator | nízký / střední | E |
| 8 | `puzzle` | puzzle z 3d modelu | 3d printed puzzle generator | nízký / střední | F |
| 9 | `cable_holder` | držák kabelů na stůl | cable holder generator 3d print | nízký / střední | F |
| 10 | `charm` | přívěsek z obrázku | custom charm 3d print | nízký / nízký až střední | F |

Důvody pořadí: `text` a `filament_art` mají globální anglické dotazy s velkým objemem (text to STL; HueForge
zdarma online, kde je náš nástroj jedna z mála bezplatných alternativ v prohlížeči). `modular` navazuje na hotový
organizér (společný článek `organizer-do-supliku-na-miru`). Dávka E jsou dárky k Vánocům a stránky vyjdou
v listopadu, kdy se dárky hledají. Dávka F doplňuje stůl a domov.

Vynecháno a proč: `bag_charm` (dotaz „bag charm“ je módní, ale spojení s 3D tiskem hledá málokdo; překrývá se
s klíčenkou a přívěskem), `beads` (jen „korálky se jménem“, lidé hledají hotové sady), `notes`, `tray`, `compose`,
`soap_model`, `papel` (úzké dotazy bez poptávky). Když Search Console ukáže jinak, vymění se za 9 a 10.

Postup u každé dávky jako v §9: `heading` = H1 na dotaz, 2 úvody, kroky, 6 FAQ, 3 příklady s čísly z produkce
(cookie `mp_test=1`), bloky v `landing.php` (u text, filament_art, modular, coaster, cable_holder a charm bloky
z AH.md §11 už jsou, jen se doplní), `config/landing.php` related + blog, `LandingPagesTest::PAGES`, snímky
390/1280, testy, push, zpráva řídícímu.

### 12.1 Dávka D: stojící nápis, obraz z filamentu, modulární organizér (28. 10. 2026)

- Soubory: `lang/{cs,en,es}/tools_seo/{text,filament_art,modular}.php` přepsány (title, heading, description, h1,
  2 úvody, 4 až 5 kroků, 6 FAQ, 3 příklady), bloky v `lang/{cs,en,es}/landing.php` (6 otázek, farm 101000, 3 pro
  koho), `config/landing.php` (text ↔ sign, name_letter, nameplate; filament_art ↔ relief, svg_to_stl, logo; modular ↔
  organizer, box, holder; blog: cedulka + jmenovky, litofanie + ozdoba z obrázku, organizér + krabička),
  `LandingPagesTest::PAGES` 29 stránek. H1 na dotaz: „3D nápis ze jména nebo slova, který stojí sám“, „Obraz
  z filamentu: obrázek nebo fotka jako vrstvy barevného plastu“, „Modulární organizér do zásuvky na míru z volných
  misek“ (en: 3D text generator for 3D printing, filament painting generator, modular drawer organizer generator).
- Čísla z produkce (28. 10., PLA, cookie `mp_test=1`): nápis HOME Archivo Black 30 mm, hloubka 12 mm, 144 × 34 mm:
  21,1 g, 40 min, 99 Kč (`/c/bkdbbtgjavzn`); dvouřádkový KAVÁRNA u Jany Bebas Neue 30 mm, hloubka 20 mm: 55,7 g,
  1 h 53 min, 130 Kč (`/c/qxcoca4ayqkb`); sněhulák z knihovny jako vrstvený obraz ze 4 desek v kulatém rámu 120 mm:
  105,6 g, 2 h 46 min, 200 Kč (`/c/tmwh0ok9ixpv`); sněhulák 100 mm ze 3 desek bez rámu 25,3 g, 46 min, 99 Kč
  (`/c/iljuoz61xdug`); perníček jako jeden tisk 120 mm, 4 barvy: 15,9 g, 26 min, 99 Kč (`/c/etcdmot38mpy`); čtyři
  misky tří velikostí 300 × 150 × 40 mm (mřížka 6 × 3): 188 g, 3 h 45 min, 310 Kč (`/c/fsgkqtoncl9f`). Obraz
  z filamentu jde přes `POST /api/tools/art` (scratchpad `art_create.py`), ne přes `/api/tools/param`.
- **Nález pro řídícího (ne moje):** vrstvený obraz 180 mm z 5 desek s rámem dá soubor 232 × 551 × 25 mm (desky
  a rám vedle sebe), `POST /api/calculations` ho řeže jako jednu podložku a **selže** („Slice failed (no gcode)…“,
  `/c/9rs4xrseapqu`); zůstane jen hrubý odhad 343 g, 390 až 770 Kč. Tisk po dílech umí až objednávka
  (`OrderService` by_parts), veřejná kalkulace ne. Totéž potká každý vrstvený obraz nad zhruba 120 mm s rámem
  (120 mm: 228 × 268 mm ještě prošlo). Na stránce o tom nepíšu; příklad je 120 mm.
- Rozhodnutí: `heading` i u nástroje `text`, i když je to preset nástroje `sign` (`/tools/text` = sign, as=text,
  preset=stand); texty se berou z `tools_seo/text.php`, nástroj sám se nemění. Organizér z jednoho kusu má rozsah
  30 až 400 mm, ale nad 250 mm se nevejde na podložku, proto FAQ u modulárního říká „zásuvka do 250 mm“. Obraz
  z filamentu nemá vlastní článek (`article` není), „Kam dál“ vede na litofanii a ozdobu z obrázku; článek
  o obrazu z filamentu je kandidát na další dávku blogu. Věta o HueForge je obecný popis (průsvit tenkých vrstev,
  výměny ve sliceru), bez čísel.
- Duplicita FAQ ↔ Než objednáte: u `text` se opakovalo „Jak nápis tisknout doma?“ (chytil to i `LandingPagesTest`),
  v bloku nahrazeno otázkou o přilepení na stěnu (zadní strana je po tisku na zádech rovná).
- Ověření: `SeoTest|ToolsCatalogTest|CatalogSeoTest` 20 testů OK, `LandingPagesTest` po opravě 5 testů, 3 007
  assertions, OK; `pint` čistý; title 54 až 60, description 150 až 154 ve všech třech jazycích; H1 = heading,
  JSON‑LD HowTo + FAQPage 10 otázek na třech stránkách (lokální server 8077); snímky
  `docs/img/seo-n1/{text,filament-art,modular-organizer}-{390,1280}-cs-1.webp` bez posunu a bez chyb („Generátor
  cedulek teď není dostupný“ je jen lokální engine). Vložka `[farm:]` a články: `BlogEmbedsTest` 4 testy,
  `BlogSyncTest` 9 testů OK (commit 15f3099b).

### 12.2 Dávka E: víčko, podtácek, magnetka, náušnice (28. 10. 2026)

- Soubory: `lang/{cs,en,es}/tools_seo/{cap,coaster,magnet,earrings}.php` přepsány (title, heading, description, h1,
  2 úvody, 4 kroky, 6 FAQ, 3 příklady), bloky v `lang/{cs,en,es}/landing.php` (6 otázek, farm 101000, 3 pro koho),
  `config/landing.php` (cap ↔ box, holder, cable_holder; coaster ↔ magnet, nameplate, ornament; magnet ↔ coaster,
  keychain, ornament; earrings ↔ keychain, medallion, nameplate; blog: krabička + materiály, ozdoba z obrázku +
  vánoční dárky, ozdoba + klíčenka, klíčenka + vánoční dárky), `LandingPagesTest::PAGES` 33 stránek. H1 na dotaz:
  „Víčko, zátka nebo krytka na míru pro 3D tisk podle naměřeného rozměru“, „Podtácek s vlastní fotkou nebo kresbou
  pro 3D tisk“, „Magnetka na lednici z fotky nebo obrázku pro 3D tisk“, „Náušnice z vlastního obrázku nebo nápisu
  pro 3D tisk“ (en: threaded cap generator, custom coaster generator, custom fridge magnet, 3D printed earrings
  generator).
- Čísla z produkce (28. 10., PLA, cookie `mp_test=1`), všechno malé kusy na minimu 99 Kč: víčko na PET lahev 28 mm
  s těsnicím kroužkem (předvolba `pet`) 5,7 g, 22 min (`/c/5omcbkke1541`); krytka profilu 36 × 16 mm 4,7 g, 15 min
  (`/c/ldniassche8t`); nasazovací víčko na sklenici 70 mm 17,8 g, 39 min (`/c/hgabcfckkauy`); kulatý podtácek
  100 mm se sněhulákem, 4 mm, drážky: 20,4 g, 40 min, 99 Kč, 4 kusy 170 Kč (`/c/xqbu3qsmyqm0`, `/c/57k7ndo2ibxo`);
  tlapka v kruhu 60 mm s kapsou Ø 10 × 2: 7 g, 14 min (`/c/gob4imtsfmor`); pár srdcí 28 mm: 2,2 g, 6 min
  (`/c/5xbjn5tfg5gn`), tedy zhruba gram na náušnici, jak FAQ říká.
- Rozhodnutí: věta „na farmě vytiskneme až čtyři barvy najednou: podklad a tři další“ z původních textů zůstala,
  odpovídá AL.md §10 (2 až 4 filamenty v jedné podložce). Odkazy „Kam dál“ u podtácku a náušnic vedou i na článek
  `vanocni-darky-z-3d-tisku-do-24-12-terminy` (vyjde 24. 11.); do té doby ho `LandingPage::blog()` nenabídne, nic
  se nerozbije. Otázky bloků jsem volil mimo FAQ (měření závitu, kde koupit magnety, háčky z galanterie, čištění,
  firemní dárky), duplicitu hlídá test.
- Ověření: `SeoTest|LandingPagesTest|ToolsCatalogTest|CatalogSeoTest` **21 testů, 8 220 assertions, OK**; `pint`
  čistý; title 52 až 58, description 141 až 155 ve všech třech jazycích; H1 = heading, JSON‑LD HowTo + FAQPage
  10 otázek na čtyřech stránkách (lokální server 8077); snímky
  `docs/img/seo-n1/{cap,coaster,magnet,earrings}-{390,1280}-cs-1.webp` bez posunu a bez chyb.

### 12.3 Dávka F: puzzle, držák kabelů, přívěsek (28. 10. 2026)

- Soubory: `lang/{cs,en,es}/tools_seo/{puzzle,cable_holder,charm}.php` přepsány (title, heading, description, h1,
  2 úvody, 4 kroky, 6 FAQ, 3 příklady; puzzle měl dosud `examples` prázdné), bloky v `lang/{cs,en,es}/landing.php`
  (6 otázek, farm 101000, 3 pro koho), `config/landing.php` (puzzle ↔ relief, coaster, logo; cable_holder ↔ holder,
  phone_stand, box; charm ↔ keychain, earrings, magnet; blog: litofanie + rozdělení, držák na zeď + stojánek,
  klíčenka + ozdoba z obrázku), `LandingPagesTest::PAGES` 36 stránek. H1 na dotaz: „Puzzle z 3D modelu nebo z fotky:
  skládačka pro 3D tisk zdarma“, „Držák kabelů na stůl na míru pro 3D tisk: kabely zacvaknou a nespadnou“, „Přívěsek
  z vlastního obrázku nebo jména pro 3D tisk: na klíče, tašku i batoh“ (en: 3D printed puzzle generator, cable holder
  generator, custom charm).
- Čísla z produkce (28. 10., PLA, cookie `mp_test=1`), vše na minimu 99 Kč: puzzle z podtácku 100 mm se sněhulákem
  (soubor kalkulace `/c/xqbu3qsmyqm0` přes `POST /api/files/{uuid}/edit` op=puzzle, 3 × 4, zámky, čísla; scratchpad
  `edit_create.py`): 12 dílků, 234 × 128 × 5,6 mm, 22,6 g, 1 h 3 min (`/c/wnr4wkkungpz`); držák na 4 kabely 6 mm
  17,1 g, 42 min (`/c/mjyodedmxzye`); držák na 6 kabelů 4 mm s oušky 24,6 g, 56 min (`/c/kop5zm2dhbtw`); veselý duch
  45 mm 3 g, 7 min (`/c/mca5ddgfzctt`); jméno Ela psacím písmem 60 mm 3,7 g, 12 min (`/c/gmfsgcpwmjl8`).
- Rozhodnutí: u puzzle jsem poslal `frame: true`, ale soubor má jen dílky `piece_1` až `piece_12` a žádný tácek
  (výška 5,6 mm), proto příklad tácek nejmenuje; tácek jako volba nástroje v textu zůstal podle původního popisu.
  Jestli vlajka `frame` na produkci opravdu tácek přidává, nevím; zmíním řídícímu. Puzzle z fotky vede přes dva
  nástroje (reliéf nebo podtácek → puzzle), FAQ to říká. U držáku kabelů tabulka průměrů běžných kabelů (sluchátka
  3 až 4, USB‑C 4 až 5, HDMI 6 až 7, síťový kabel monitoru 7 až 8 mm) je z běžné praxe, ne z měření.
- `SeoTest` chce u každého nástroje tolik popisků příkladů, kolik má `config/tools.php` v `seo.examples`; puzzle
  tam má prázdné pole (nástroj „mám soubor“ nemá obrázky příkladů), proto jsou `examples` u puzzle prázdné ve všech
  třech jazycích a čísla jsou jen v úvodu a FAQ.
- Ověření: `LandingPagesTest|ToolsCatalogTest|CatalogSeoTest` 20 testů OK, `SeoTest` po vyprázdnění příkladů
  **12 testů, 4 322 assertions, OK**; `pint` čistý; title 54 až 59, description 140 až 154 ve všech třech
  jazycích; H1 = heading, JSON‑LD HowTo + FAQPage 10 otázek na třech stránkách (lokální server 8077); snímky
  `docs/img/seo-n1/{puzzle,cable-holder,charm}-{390,1280}-cs-1.webp` bez posunu a bez chyb.
- Druhá desítka (§12) je tím hotová: D 8bc986fd, E 426d1b29, F tento commit.

## 13. Další 4 články po 20. 11. (plán 28. 10. 2026)

Řídící: čtyři články na listopad a prosinec, stejná pravidla (video, kalkulace, místo pro fotku). Termíny
navazují na rytmus úterý a pátek z §7.2:

| datum | slug (návrh) | téma | data |
|---|---|---|---|
| út 24. 11. | `vanocni-darky-z-3d-tisku-do-24-12-terminy` | vánoční dárky z 3D tisku do 24. 12. s termíny výroby a dopravy | medián předání z `PublicStats`, doprava Packeta, nástroje z §6 a §12 E |
| pá 27. 11. | `jak-zjistit-cenu-3d-tisku` | jak zjistit cenu 3D tisku: z čeho se skládá, odhad bez řezu, kalkulace | M.md §6 (rozsah odhadu), `[calc:]` vložky, 400 kalkulací z §7.3 |
| út 1. 12. | `3d-tisk-praha-jak-to-u-nas-chodi` | 3D tisk Praha jako článek ke vstupní stránce `/3d-tisk-praha` | §8, `PublicStats`, bez osobního odběru (zatím není) |
| pá 4. 12. | `co-noveho-na-farme-v-listopadu` | co nového na farmě v listopadu podle `PublicStats` | čísla za listopad nejsou dřív než 1. 12.; řešení v §13.1 |

Napsáno 28. 10. 2026, všechny čtyři v `resources/blog/cs` s datem dopředu (`Post::published()` je pustí samo, na
produkci po nasazení `php artisan blog:sync`), titulky 61 až 71 znaků, výtah 189 až 230 znaků (meta popis bere
prvních 155), místo na fotku jako komentář, „Co dál“ nejvýše 3 odkazy (hlídá `BlogSyncTest`). Vložky: dárky
`[calc:]` písmeno, jmenovky, perníček, zápich, světelný nápis, misky + `[video: Fd72RTnqYDE]`; cena `[calc:]` HOME
(99 Kč: materiál 19 + čas 6 + příprava 30, pod minimem) a misky (310 Kč: 226 + 49 + 30) + `[video: latest]`; Praha
`[calc:]` logo a světelný nápis + `[video: 2GqbDiH_mGg]`; listopad `[farm: 2026-11]` + `[video: latest]`.

K rozhodnutí řídícího (napsal jsem, co z dosavadních čísel plyne, nic víc): termíny v článku o dárcích (drobnosti
do pá 18. 12., tisky na hodiny do st 16. 12., busty a víc kusů do pá 11. 12.) vycházejí z mediánu předání do dne,
odeslání v den dotisknutí a Zásilkovny 1 až 2 pracovní dny; vánoční rozvoz dopravců vyhlášený v prosinci může
termíny posunout, článek to říká a slibuje doplnění. Článek o listopadu jmenuje, co bude v listopadu nové na webu
podle stavu z 28. 10. (stránky nástrojů včetně dávky D, vánoční nástroje, `/3d-tisk-praha`, fotky výtisků, blog
dvakrát týdně) a na prosinec slibuje dávku E (víčko, podtácek, magnetka, náušnice); kdyby se plán změnil, upravit
před 4. 12. Vlastní fotky ke článkům nemám, místo je v komentáři.

### 13.1 Čísla za měsíc při zobrazení: vložka `[farm: 2026-11]` (28. 10. 2026)

Listopadová čísla nejsou dřív než 1. 12. a článek vyjde 4. 12., proto je nepíšu do textu. `BlogEmbeds` dostal
třetí vložku `[farm: <RRRR-MM>]` (řídící souhlasil 28. 10.): `App\Support\FarmMonthStats::month()` spočítá za
kalendářní měsíc stejně jako `PublicStats` celou farmu (jen čtení, paměť hodinu): dokončené výtisky (zaplacené,
`done` nebo `handed_over`, `finished_at` v měsíci), medián dnů od zaplacení k předání (události `handed_over`
v měsíci), videa zveřejněná na YouTube v měsíci, nejčastější tři nástroje podle `ModelFile::kind()` (generovaná
figurka jako `figure`, jako v `PublicStats::latestPhotos`) a počet vlastních souborů zákazníků. Pohled
`blog/_farm_month.blade.php` je karta jako u kalkulace, texty `site.blog.farm.*` (`lang/src/blog_n1.json`).
Text článku čísla nejmenuje, mluví o kartě; kdo článek otevře v prosinci, vidí listopad spočítaný ze zakázek
ve chvíli zobrazení. Špatný měsíc (třeba `2026-13`) vloží nic. Test v `BlogEmbedsTest`.

## 14. Úklid pro vyhledávače a cesta nových stránek do indexu (větev `feature/seo-cleanup-index`, 12. 11. 2026)

Zadání `docs/prompts/uklid-indexace.md`, podklad studie návštěvnosti z 12. 11. 2026 (mimo repo): Google nehlásí
postih, ale ze 40 zkoušených stránek nástrojů, vstupních stránek a článků nemá v indexu žádnou, v mapě stránek
„odesláno 603, zaindexováno 0“, a prochází skoro jen převzatý katalog `/model/…` a zbytky starého webu. Tato
kapitola popisuje, co se změnilo, aby pozornost Googlu šla na stránky, které přivádějí zákazníky. Žádné triky:
pořádek, pravdivé signály, nic nového se nevymýšlí. Nic z toho neběželo na produkci a Search Console jsem neviděl;
co je potřeba ověřit tam, je v 14.10.

### 14.1 Katalog: v indexu jen stránka s naším textem a naší hodnotou

Přepínač `CATALOG_INDEX_REQUIRES` (`config/catalog.php` `index_requires`, §9 v `docs/AL.md`) říká, co musí stránka
modelu mít, aby směla do indexu; musí mít všechno, co je vyjmenováno. Do 12. 11. byl výchozí stav
`enriched,price,file` (náš text, jakákoli cena včetně odhadu převzatého ze starého webu, soubor).

**Nové výchozí pravidlo `enriched,value`:**

| podmínka | co znamená | jak se pozná |
|---|---|---|
| `enriched` | text napsaný u nás | `enriched_at` (`catalog:enrich` nebo admin) |
| `value` | něco, co o modelu umíme říct jen my, aspoň jedno z: | |
| | cena tisku z řezu vlastního souboru modelu | `sliced_at`, `slice_minutes > 0`, `slice_grams > 0` (`catalog:slice`) |
| | fotka výtisku z naší farmy | zaplacená dokončená zakázka s fotkou a souhlasem se zveřejněním, `farm_orders.catalog_model_id` |
| | karta autora, kterou farma tiskne | `designerModels` `printable()` |

Starší podmínky `price` a `file` zůstávají pro přepínač (`CATALOG_INDEX_REQUIRES=enriched` = každý model s naším
textem; `value` samotné = i bez našeho textu). Odhad gramů a minut převzatý ze starého webu už sám nestačí: není náš.
Pravidlo je jedno místo: `CatalogModel::scopeIndexable()` (mapa stránek, admin, `catalog:enrich`, `catalog:slice`),
`isIndexable()` a `indexGaps()` (admin ukazuje „chybí: vlastní hodnota“).

Stránka mimo index: `noindex, follow`, bez kanonické adresy, není v mapě stránek a **nenese `Product`**. Návštěvník
vidí všechno jako dřív (i tabulku cen z řezu, když ji model má). `Product` s `AggregateOffer` se vypisuje jen na
stránce, která je v indexu, má řez vlastního souboru, licence dovoluje prodej a existuje kalkulace k objednání, tedy
jen tam, kde je to pravda: cena tisku u nás. `CreativeWork` (dílo jiného autora, s odkazem na zdroj) zůstává všude.
Fotka výtisku je hodnota, ne cena: stránka jen s fotkou `Product` nenese.

Nový blok „Takhle jsme to vytiskli“ na stránce modelu (`App\Domain\Catalog\CatalogPhotos`, až 4 fotky, materiál
a datum, nikdy zákazník ani cena): stejné zakázky, které dělají stránku indexovatelnou, jsou na ní vidět. Druhá a další
strana seznamu `/model?page=N` má nově `noindex, follow`.

**Kolik stránek zůstane: podle pravidla, ne z dat.** Místní databáze je od 1. 11. prázdná (jen seedery), čísla
produkce neznám. Podle `docs/AN.md` §13 bylo 27. 10. indexovatelných 375 modelů (tehdejší nastavení produkce neznám)
a soubor drží jen modely nahrané ručně (seznam „Top 30“ v `/admin/catalog/files`). Nové pravidlo nechá v indexu
jen modely, které mají náš text a zároveň řez, fotku nebo kartu autora: nejvýš tolik, kolik souborů Roman nahrál
a rozřezal, plus karty autorů a vyfocené zakázky z katalogu. Čekám desítky, ne stovky; je to odhad, ne měření.
Přesná čísla pro všechny varianty vypíše na produkci (jen čte):

    php artisan matplace:sitemap
    … Inspiration catalogue: N indexable of M shown pages, K noindex (not listed); the index needs: enriched, value.
    … By rule: enriched,value = a; enriched = b; value = c; enriched,price,file = d.

**Pozor na `.env` produkce:** pokud tam je `CATALOG_INDEX_REQUIRES=…`, výchozí hodnota se nepoužije. Buď řádek
smazat, nebo nastavit `enriched,value`.

### 14.2 Zbytky starého webu

- **410 „zrušeno“** (`config/legacy.php` `gone`, `LegacyRedirects::gone`): vzory nad cestou, kontrolované před
  směrováním; adresa, na kterou odpovídá naše routa, nikdy zrušená není (ověří se proti routeru). Výchozí vzory:
  `/assets/thumbs/…` (náhledy starého katalogu), `/<číslo>` (`/14763`), cokoli s `filament` v jediném úseku cesty
  (`/pla-luminous-filament-…`), `/klicenka-…`. Soubor dostane dvě slova textu, stránka šablonu `errors/410`
  („Tato stránka už neexistuje“, odkazy na hlavní stranu a nástroje, `noindex`). Další vzory se doplní podle
  seznamu „Adresy, které neexistují“ v `/admin/stats` a logu; seznam starých adres e‑shopu jsem neměl.
- **`/assets/thumbs/…`:** dosud 302 na `legacy.matplace.com`. Dělá to aplikace (`to_legacy`), ne nginx: šablona
  `deploy/nginx/matplace-app-common.conf` posílá neexistující soubory do `index.php`. Kdo je ještě používá: nejspíš naše vlastní
  stránky katalogu. Import katalogu běžel před přepnutím domén s `LEGACY_ASSETS_URL=https://matplace.com`
  (`docs/DEPLOY-BETA.md`), takže model bez zkopírovaného náhledu má v `preview_url` adresu
  `https://matplace.com/assets/thumbs/…` a každé zobrazení jde přes naše 302. To sedí s logem (Googlebot-Image,
  tisíce náhledů). Z produkčních dat jsem to ověřit nemohl. Proto `CatalogModel::directPreview()`: taková adresa se při čtení přepíše rovnou na
  hostitele starého webu (`legacy.host`), stránka tedy nikoho přes naše přesměrování neposílá a obrázky fungují dál.
  Model s kopií (`thumbnail_path`) bere obrázek z `/storage` jako dřív. Vypínač `LEGACY_THUMBS_GONE=false` vrátí 302.
- **Obrázek indexované stránky z vlastní adresy:** `php artisan catalog:own-images [--dry-run] [--limit=200] [--all]`
  stáhne náhled indexovatelných modelů bez vlastní kopie, uloží jako WebP do `storage/app/public/catalog/own/…`
  a nastaví `thumbnail_path`.
- Ostatní `/assets/*`, `/blog-img/*`, `/thumbnails/*`, `/stl/*` a stránky účtů starého webu zůstávají 302 na starý
  web: mohou je používat staré články a lidé se starými zakázkami. Nesahal jsem na ně.
- **Přihlášení a účet:** `/login`, `/register`, `/forgot-password`, účet a zakázky mají `noindex` už dřív
  (`config/seo.php` `noindex`). Nově má odkaz „Přihlásit“ v hlavičce a tlačítka přihlášení přes Google/Facebook
  `rel="nofollow"`, a `robots.txt` zavírá `/auth/` (jen přesměrování ke Googlu). `/login` v `robots.txt` zavřený
  není, aby Google `noindex` viděl a stránku z indexu vyřadil.

### 14.3 Přesměrování a jazyky

- `?lang=xx` (starý přepínač): jedno 301 na konečnou adresu. Model katalogu bez textu v daném jazyce šel dřív
  `/model/x?lang=en` → `/en/model/x` → `/model/x`; teď `SetLocale` pošle rovnou na `/model/x`.
- Vnitřní odkazy, kanonické adresy, hreflang ani mapa stránek `?lang=` nenesou (test nad 13 stránkami a všemi
  soubory mapy).
- **Vnitřní odkazy přes přesměrování:** `SeoQualityTest::test_no_link_on_the_pages_search_engines_read_leads_through_a_redirect`
  projde všechny stránky mapy stránek, seznam a detail katalogu a přihlášení, posbírá všechny vnitřní odkazy a každý
  otevře. Výsledek je v 14.9.

### 14.4 Mapa stránek a robots.txt

- V mapě je jen to, co má být v indexu: nástroje ve třech jazycích, `/tools`, `/gifts`, `/3d-tisk-praha`, `/business`,
  statické stránky, blog, indexovatelné modely katalogu. Test otevře každou adresu z map nástrojů a blogu: 200, bez
  `noindex`, kanonická adresa na sebe, jeden H1.
- **Skutečné `lastmod`:** nástroj = den, kdy se změnil jeho text (`lang/<jazyk>/tools_seo/<nástroj>.php`, čas souboru;
  nasazení přepisuje jen změněné soubory), článek = `updated_at`, model katalogu = den našeho textu nebo řezu (ne
  `updated_at`, to posouvá každé zobrazení stránky), stránky bez známého data žádné `lastmod` nemají. Index map už
  neříká u každého souboru dnešek, ale nejnovější datum v souboru (nebo nic).
- **`robots.txt`** nově zavírá: `/farm?` (vstup do zakázky s parametry, i `/en/`, `/es/`), `/auth/`, `/c/` (soukromé
  kalkulace), hledání v katalogu `?q=` a řazení `?sort=`. Nezavírá nic, co má být v indexu nebo co stránka potřebuje
  k vykreslení (`/build`, `/storage`, `/img`). `/assets/thumbs` se nezavírá schválně: Google musí 410 vidět.

### 14.5 Vnitřní odkazy

- **Patička na každé stránce** (`partials/footer_make.blade.php`, v layoutu jeden řádek `@include`): Všechny nástroje,
  Dárky na míru, šest nástrojů z `config/seo.php` `footer_tools` (busta, mazlíček, přívěsek, medaile, klíčenka,
  organizér; jen ty, které jsou veřejné), 3D tisk Praha, 3D tisk pro firmy. V jazyce stránky.
- **Stránka modelu:** blok „Chcete něco takového vyrobit?“ se třemi obyčejnými odkazy (cena z vlastního souboru,
  nástroje, dárky). Tlačítko „Nahrát a vytisknout“ (`/farm?source=…`) zůstává `nofollow`.
- Úvodní stranu jsem neupravoval (jiná session).

### 14.6 Kvalita stránek, které mají být v indexu

`SeoQualityTest` čte každou adresu z map nástrojů a blogu ve třech jazycích jako vyhledávač. Tvrdá pravidla (test
na nich padá): 200, žádný `noindex`, kanonická adresa na sebe, právě jeden H1, jazyk stránky, titulek, popis aspoň
50 znaků, obrázek pro sdílení, každý obrázek s `alt`, strukturovaná data, titulek i popis v rámci jazyka jedinečné,
hreflang: stránka jmenuje sebe, každé dvojče je v mapě a jmenuje ji zpět, `x-default` je česká verze. Co je jen
slabé, vypíše s `SEO_AUDIT_OUT=<soubor>` jako tabulku. Nálezy a opravy jsou v 14.9.

Doplněná strukturovaná data: `Service` (3D tisk na zakázku, poskytovatel matplace, oblast Česko, u pražské stránky
i Praha; bez vymyšlené ceny) na `/3d-tisk-praha`, `/business` a `/gifts`; `ItemList` nástrojů na `/tools`;
`Organization` na stránkách O nás a Kontakt umí `location` pro místa farem z `config/seo.php` `farms` (prázdné,
dokud Roman adresy nezveřejní).

### 14.7 Měření: roboti a `order_started`

Pravidla jsou v `App\Support\Bots`, nastavení v `config/bots.php` (jde měnit v `.env` bez nasazení):

- **Jména:** nové rodiny `seranking`, `seo` (Serpstat, Sistrix, Screaming Frog, BLEXBot, Seobility…), `semrush`
  pozná i audit bez slova „bot“. Jejich stránka je `crawl`, nikdy návštěva.
- **Datová centra:** `BOT_NETWORKS="203.0.113.0/24,2001:db8::/32"`: požadavek z uvedeného bloku je robot
  `datacenter`. Blok z logu 12. 11. (1 128 požadavků „Firefox 159“) studie nejmenuje, doplní řídící.
- **Hlavička, kterou žádný prohlížeč neposílá:** `BOT_AGENTS="řetězec|řetězec"` → robot `listed`. „Firefox 159“
  sem nepatří: podle čísla verze robota poznat nejde, stejnou hlavičku pošle i člověk.
- **Opakovaná hlavička z jednoho bloku adres (`burst`):** stejný User-Agent ze stejné sítě (IPv4 /24, IPv6 /48),
  který si nedrží naši cookie, smí za hodinu otevřít `BOT_BURST_PER_HOUR` stránek (výchozí 40) jako „nový
  návštěvník“; další jsou `crawl`. Prohlížeč si cookie drží od první stránky, člověk se tedy započítá jednou, ať čte
  cokoli; `0` pravidlo vypne. Stránku i cookie dostane každý, mění se jen to, co se zapíše.
- Zpětně: `matplace:events-bots` označí i nepotvrzené návštěvy podle `BOT_NETWORKS` a `BOT_AGENTS`. Potvrzené vlny
  dál hlídá `matplace:stats-wave` (docs/O.md).
- Přehled a týdenní e‑mail počítají lidi přes `Event::people()` jako dřív; roboti výše se do návštěv vůbec nezapíšou.

**`order_started`:** zapisuje ho jediné místo, `Farm\OrderController::start` (`GET /farm`), při každém otevření
stránky, bez ohledu na to, kdo ji otevřel. Od objednávky bez registrace (28. 10.) je stránka otevřená i hostům a na
každém modelu katalogu na ni vede odkaz `/farm?source=<id>`. Robot, který prošel katalog a odkaz následoval, tak
zapsal jednu „zahájenou objednávku“ za každý model: to odpovídá 942 a 962 zápisům 9. a 10. 11. Příčinu jsem
odvodil z kódu, produkční události jsem neviděl; ověření je v 14.10. Ochrana: robot (`Bots::is`) nezapíše nic;
člověk jednou za 30 minut na relaci a věc (obnovení stránky nebo návrat z přihlášení není další zahájení);
`robots.txt` zavírá `/farm?`.

### 14.8 Stav indexu jedním příkazem

    php artisan searchconsole:inspect                # důležité adresy, pak mapa stránek, do stropu
    php artisan searchconsole:inspect --important    # jen důležité (config/seo.php inspect)
    php artisan searchconsole:inspect --cap=40 | --dry-run | --summary

Nad stávajícím `SearchConsoleClient` (stačí oprávnění jen pro čtení): `inspect()` = `POST
searchconsole.googleapis.com/v1/urlInspection/index:inspect` (`inspectionUrl`, `siteUrl`, anglicky kvůli strojovému
čtení), `sitemaps()` = `GET webmasters/v3/sites/<site>/sitemaps`. `App\Domain\Stats\SearchConsoleIndex` sestaví
frontu (důležité adresy z `config/seo.php` `inspect.routes` a `paths`, pak adresy z mapy, nejdřív ty nikdy nebo
nejdéle nezkoušené; na co se ptal dnes, na to se neptá znovu), uloží odpovědi do `search_console_inspections`
(řádek na adresu a den: stav `indexed / discovered / crawled / unknown / excluded / error`, slova Googlu, kanonická
adresa podle Googlu, poslední procházení) a mapy do `search_console_sitemaps` (odesláno, zaindexováno, chyby).
Stropy: jeden běh `SEARCH_CONSOLE_INSPECT_CAP` (150), den 1 800 (Google dovoluje 2 000); při 429 nebo ztraceném
oprávnění běh skončí a řekne proč. Souhrn tiskne příkaz i týdenní e‑mail (oddíl „Google: co má v indexu“): kolik
adres je v jakém stavu, které důležité stránky v indexu nejsou, mapy stránek a u cizích map poznámka „stará mapa,
smazat v Search Console“. Rozvrh: pondělí 6:30 před týdenním e‑mailem. Testy jen s `Http::fake`; Google jsem nevolal.

### 14.9 Co ukázala kontrola stránek (13. 11. 2026, místně v testech)

`SeoQualityTest` přečetl 249 adres z map nástrojů a blogu (99 česky, 75 anglicky, 75 španělsky; články jsou jen
česky) a otevřel 345 různých vnitřních odkazů, které na nich a na stránkách katalogu jsou.

- **Odkazy přes přesměrování: žádný.** Všech 345 odkazů odpovídá 200 (soukromé stránky `/farm`, `/account` posílají
  hosta na přihlášení a roboti na ně nesmějí). 301, které Googlebot dostává, tedy nepocházejí z našich odkazů, ale
  z adres, které zná z dřívějška (`?lang=`, starý web); ty teď končí jedním krokem nebo 410.
- **Jen na produkci, neposuzováno:** sedm odkazů na ukázkové kalkulace `/c/<token>` z článků a ze stránky pro firmy
  (tokeny jsou produkční) a odkaz na článek `/blog/jak-vznika-busta-z-fotky` z článku o figurce (článek ze starého
  webu, v repozitáři není). Na produkci stojí za kontrolu, že všech osm odpovídá 200.
- **Tvrdá pravidla:** po opravách projde všech 249 stránek. Opraveno: `/privacy` a `/farm/terms` měly ve všech
  jazycích stejný popis (obecný slogan webu), teď má každá svůj (`seo.privacy.description`,
  `seo.farm_terms.description`); doplněna strukturovaná data z 14.6.
- **Slabé, neopravováno (chce to texty nebo rozhodnutí):**
  - Titulek delší než 60 znaků má 176 stránek, delší než 70 má 29 (hlavně články: název článku + „ · matplace“,
    73 až 91 znaků). Google zobrazí zhruba 60 znaků; u článků by pomohl kratší titulek pro vyhledávače vedle
    nadpisu.
  - `/blog` má popis 70 znaků.
  - `/contact` má 100 slov (je to kontakt), `/model` je seznam bez vlastního textu.
  - Stránky nástrojů mají 700 až 1 900 slov vlastního textu, medián všech stránek 1 136 slov; HTML má medián
    114 kB, největší 351 kB (nástroj s velkou konfigurací ve stránce).
- **Rychlost načtení jsem neměřil** (místní databáze je prázdná a produkci nesmím zatěžovat); platí měření
  z `docs/AH.md` §11. Pro Search Console: Zkušenosti → Core Web Vitals.
- Úvodní strana: titulek a obsah si odporují (studie, příčina 3); sjednocuje ji session `feature/home-what-to-make`.

**Testy (13. 11. 2026, jak opravdu běžely):** celá sada má 1 109 testů. Jeden běh celé sady jsem po 895 testech
zastavil (trval přes hodinu, stroj sdílely další session). Potom po částech: rozsah tříd L až Se (234 testů)
a všechno ostatní včetně Unit a znovu Se (917 testů), pak 71 a 104 testů stránek, katalogu a statistik po poslední
úpravě textů (obojí OK). Po sloučení `origin/main` z 13. 11. do větve 172 testů stránek, katalogu, statistik a Search
Console: selhal jeden můj test (čekal datum souboru, který v mapě není), opraven, `SeoCleanupTest` 12 testů OK.
**Celá sada po sloučení s `main` neběžela.** Selhávají čtyři testy z důvodů mimo tuto větev (jejich soubory ani kód,
který zkoušejí, jsem neměnil; na `main` jsem je nespouštěl): `FarmPagesTest` (formulář nastavení farmy žádá
`shorts_publish_hour`), `PrintPhotosTest` (fotka `photo-short.jpg` ve střihu Shortu), `PapelPortraitTest` (šest
kroků: `id="param-artwork-file" type="file"`), `YouTubeChannelTest` (název videa vlastního souboru). Pint bez
nálezů. Pozor při testech: neupravovat `app/` a šablony, dokud běží PHPUnit. Běžící proces drží starou třídu
a vykreslí novou šablonu; čtyři „selhání“ zastaveného běhu byla jen tohle a v novém procesu prošla.

### 14.10 Nasazení a co ověřit na produkci

**Nasazení nese:**

- **Migraci** `2026_11_12_100000_search_console_inspections` (dvě nové tabulky `search_console_inspections`
  a `search_console_sitemaps`; nic stávajícího nemění). Stav `state` má nejvýš 10 znaků ve sloupci `varchar(20)`.
- **`.env` (nic není povinné):** zkontrolovat `CATALOG_INDEX_REQUIRES` (smazat nebo `enriched,value`, viz 14.1);
  volitelně `BOT_NETWORKS`, `BOT_AGENTS`, `BOT_BURST_PER_HOUR` (40), `LEGACY_THUMBS_GONE` (true),
  `SEARCH_CONSOLE_INSPECT_CAP` (150). Po změně `.env` `php artisan optimize` a restart php-fpm.
- **`npm run build`** (nové třídy v patičce a na stránce modelu) a po nasazení `php artisan matplace:sitemap`
  (jinak se mapa přepíše až ve 4:40).
- **Nový rozvrh:** `searchconsole:inspect` v pondělí 6:30 (až 150 dotazů na Google přes připojený účet).
- **nginx: není potřeba nic**, pokud server odpovídá šabloně v `deploy/nginx` (neexistující soubory jdou do
  `index.php`, 410 vrací aplikace). Návrh, jen pokud by se chtělo ušetřit PHP nebo pokud má server vlastní pravidlo,
  které `/assets/` přesměrovává dřív než aplikace (pak 410 z aplikace nikdy nepřijde a pravidlo je potřeba odstranit):

      # snippets/matplace-app-common.conf, před location ~* \.(css|js|…)$
      location ^~ /assets/thumbs/ { return 410; }

**Co ověřit na produkci (z této session nešlo):**

1. `php artisan matplace:sitemap`: řádek „By rule“ a počet indexovatelných modelů; rozhodnout, jestli nechat
   `enriched,value`, nebo dočasně `enriched`.
2. `curl -sI https://matplace.com/assets/thumbs/<cokoli>.jpg` → 410; `/14763` → 410; `/assets/img/<soubor>` → 302;
   `/model/<slug>?lang=en` → jedno 301.
3. Stránka modelu bez zkopírovaného náhledu: obrázek se načte z `legacy.matplace.com` (ne přes
   `matplace.com/assets/thumbs`). Kolik modelů to je: `php artisan catalog:own-images --dry-run` (indexované)
   a `--all --dry-run` (všechny). Pak `catalog:own-images` pro indexované. Pozor: `legacy.matplace.com` posílá
   `X-Robots-Tag: noindex` i u obrázků, takže indexovaná stránka má mít obrázek u nás.
4. `https://matplace.com/robots.txt` a `sitemap.xml` (data `lastmod` nejsou všechna dnešní).
5. `order_started`: `SELECT DATE(created_at), COUNT(*), COUNT(DISTINCT session_id) FROM events WHERE type =
   'order_started' AND created_at >= '2026-11-08' GROUP BY 1` a u 9. a 10. 11. `meta->kind` (čekám `inspiration`)
   a User-Agent relací; po nasazení má být denní počet zpět v jednotkách.
6. Blok adres „Firefox 159“ z logu do `BOT_NETWORKS`; `php artisan matplace:events-bots --since=2026-11-01 --dry-run`.
7. Seznam „Adresy, které neexistují“ v `/admin/stats` a log: další staré adresy e‑shopu do `config/legacy.php` `gone`.
8. `php artisan searchconsole:inspect --important` (první běh, 16 dotazů) a `--summary`.
9. Rich Results Test na jednu indexovanou stránku modelu (`Product`), `/3d-tisk-praha` (`Service`, `FAQPage`)
   a `/tools` (`ItemList`).

**Pro Romana v Search Console (ne z kódu):**

Požádat o indexování (Kontrola adresy URL → Požádat o indexování; kolem deseti denně), v tomto pořadí:

1. `https://matplace.com/gifts`
2. `https://matplace.com/tools/figure`
3. `https://matplace.com/tools/pet-figurine`
4. `https://matplace.com/tools`
5. `https://matplace.com/3d-tisk-praha`
6. `https://matplace.com/tools/charm`
7. `https://matplace.com/tools/medallion`
8. `https://matplace.com/tools/organizer`
9. `https://matplace.com/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026`
10. `https://matplace.com/blog/vanocni-ozdoby-se-jmenem-z-3d-tiskarny`

Až 24. 11. vyjde článek `vanocni-darky-z-3d-tisku-do-24-12-terminy`, požádat i o něj. Další den anglické verze
(`/en/gifts`, `/en/tools/figure`, `/en/tools`), pokud má smysl cizina.

Postup:

1. Soubory Sitemap: smazat tři staré mapy z let 2013 a 2018 (u každé tři tečky → Odebrat soubor Sitemap). Nechat jen
   `https://matplace.com/sitemap.xml`; po nasazení ji odeslat znovu (stejná adresa, „Odeslat“), aby si ji Google stáhl.
2. Po nasazení deset adres výše.
3. **Za týden:** Stránky → „Proč stránky nejsou indexovány“: má ubývat „Stránka s přesměrováním“ a přibývat
   „Vyloučeno značkou noindex“ u `/model/…` (to je správně); Soubory Sitemap: „zaindexováno“ u naší mapy už ne 0;
   pondělní e‑mail, oddíl „Google: co má v indexu“ (kolik z důležitých stránek je v indexu).
4. **Za tři týdny:** deset adres má být „Stránka je indexována“; Výkon → Česko: zobrazení na dotazy mimo název
   („dárek z fotky“, „3D tisk na zakázku“, „3D tisk Praha“); Statistiky procházení: méně požadavků na obrázky
   a `/model/…`, více na `/tools/…` a `/blog/…`; karta Nákupy: počet „produktů“ má klesat k počtu skutečně
   indexovaných modelů s cenou. Když se deset adres do tří týdnů neobjeví, příčina není technická, ale váha webu:
   odkazy odjinud a firemní profil (studie, bod 4 a 5).

## 15. Každá indexovaná stránka zvlášť: měření, opravy, před a po (větev `feature/page-optimization`, 13. 11. 2026)

Zadání `zadani-optimalizace-stranek.md` (mimo repo, Roman: „všechny stránky nech perfektně optimalizovat“). Větev
z `origin/main` 7dc7f357 (to, co běží na produkci). Na produkci jsem jen četl veřejné stránky jako návštěvník
(nejvýš 2 dotazy za vteřinu, cookie `mp_test=1` a hlavička `X-Matplace-Test`, `docs/AJ.md`); nic jsem nenasazoval,
žádná migrace, žádné placené volání AI, texty jsem psal sám. Místně běžel server nad **kopií** sdílené `dev.sqlite`
(`DB_DATABASE` v proměnné prostředí, kopie ve scratchpadu, do ní `blog:sync`), sdílená databáze zůstala netknutá.

### 15.1 Měření: `scripts/seo_audit.mjs`

Skript bez balíčků (Node 22, Chrome přes DevTools protokol jako `scripts/review_round2.mjs`). Přečte index mapy
stránek a všechny dílčí mapy, přidá vstupní stránky (`/`, `/tools`, `/gifts`, `/3d-tisk-praha`, `/business`, `/farm`,
`/models`, `/blog` ve třech jazycích) a pro každou adresu zapíše: stav a řetěz přesměrování, canonical, robots (meta
i `X-Robots-Tag`), hreflang (cs/en/es + x-default, vzájemnost), titulek a popis (délka, jedinečnost v jazyce), H1
a osnovu nadpisů, počet slov vlastního textu (`<main>` bez skriptů, menu a seznamů formuláře), podíl textu, který stojí
na šesti a více stránkách téhož jazyka (šablonový text), obrázky (alt, rozměry, formát, lazy, váha; u `<picture>` váží
zdroj WebP, který si prohlížeč vezme), vnitřní odkazy ven a dovnitř (osiřelé stránky, kroky z úvodní strany, odkazy
na přesměrování a na stránky, které nejsou), strukturovaná data (druhy, platnost JSON-LD, otázky a odpovědi FAQPage,
kroky HowTo a ceny Product ve viditelném textu, nadpis Article proti H1), Open Graph, váhu HTML, CSS a JS, a s
`--browser` v Chromu 390 × 844: LCP (i prvek), FCP, CLS, počet dotazů, přenesené kB, vodorovný posun.

    node scripts/seo_audit.mjs --base=https://matplace.com --out=storage/app/seo-audit/prod --browser=important
    node scripts/seo_audit.mjs --base=http://127.0.0.1:8097 --out=storage/app/seo-audit/local --rate=30 --browser=important
    node scripts/seo_audit.mjs --compare=<složka před>,<složka po>      # tabulka před/po (cesty ve tvaru C:/…)

Výstup ve složce `--out`: `pages.csv` (řádek na stránku, středník, UTF-8 s BOM, otevře ho LibreOffice), `pages.json`
(všechno), `summary.json` a `summary.md` (čísla pro tabulku), `findings.md` (nálezy, stránky kampaně a vstupní stránky
první). `storage/app` je mimo git. Jeden dotaz po druhém, `--rate` za vteřinu (výchozí 2); po stránce otevřené
v Chromu čeká tak dlouho, jak dlouho by její soubory trvaly při `--rate`. `--browser=important` měří vstupní
stránky a stránky kampaně (39 stránek), `--browser=all` všechny, `--only=<regex>` jen vybrané cesty, `--links=0`
a `--images=0` vynechají otevírání odkazů a vážení obrázků. Celý běh proti produkci bez Chromu trvá asi 10 minut
(236 stránek, 290 dalších odkazů, obrázky), s Chromem asi 25 minut.

Co skript neumí: nevidí Search Console (stav indexu), neměří na pomalé síti (LCP je z rychlého připojení, pro
srovnání před/po stačí; skóre Lighthouse dává `scripts/lighthouse_landing.mjs`) a slova počítá jednoduše (mezery).

### 15.2 Co měření našlo (produkce 13. 11. 2026 ráno: 230 adres v mapách, 236 přečtených, všechny 200)

Seřazeno podle dopadu; stránky kampaně a důležité stránky, které Google nezná, první.

| # | nález | kde | stav |
|---|---|---|---|
| 1 | Odkaz na stránku, která odpovídá 404 | 4 články (`stojanek-na-telefon-na-miru`, `generovani-3d-modelu-pomoci-ai`, `kolik-stoji-3d-tisk-na-zakazku-v-roce-2026`, `jak-pripravit-stl-soubor-pro-3d-tisk`) → `/tools/phone-stand` a `/tools/colors`, vypnuté v `/admin/tools` | opraveno |
| 2 | Titulek delší než 60 znaků, výsledek hledání ho usekne | 152 stránek, 28 přes 70; z kampaně klíčenka 71, ozdoba 71, mazlíček 68, Praha 68, perníček 67, organizér 67, přívěsek 66, medaile 64 | opraveno, 0 stránek |
| 3 | Titulek a H1 neříkají slova dotazu | mazlíček („mazlíčka“, lidé hledají „figurka psa z fotky“), busta bez „na zakázku“ a ceny, `/tools` s H1 „Nástroje“ | opraveno |
| 4 | Stránka kampaně skoro bez odkazů | `/tools/ornament` 9 odkazů a `/tools/gingerbread` 11 (ostatní 95); `/3d-tisk-praha` bez jediného odkazu z obsahu, jen z patičky | opraveno |
| 5 | Chybí `BreadcrumbList` | 207 stránek (všechno kromě článků a modelů) | opraveno; bez něj zůstává úvodní strana (je vrchol), seznam katalogu, `/farm`, `/models` |
| 6 | Popis článku je začátek perexu useknutý „...“ | 26 stránek článků; `/blog` má popis 70 znaků | opraveno u 31 článků z repozitáře a u `/blog` |
| 7 | `Article` bez obrázku | 19 článků | opraveno |
| 8 | `/materials` není v mapě stránek | tři jazyky | opraveno |
| 9 | Nástroj bez bloku „Kam dál“ | 23 nástrojů (na produkci 17 z veřejných) | opraveno |
| 10 | Odkaz přes přesměrování | `/models` → `/account` (302 na přihlášení) ve třech jazycích | opraveno (`nofollow`) |
| 11 | Obrázek bez rozměrů | 170: 141 na seznamu katalogu `/model`, 29 v blogu (fotky článků a obrázky v textu) | blog opraven; katalog nechán (15.4) |
| 12 | HTML přes 200 kB | 34 stránek nástrojů (medián všech stránek 173 kB, nejvíc 302 kB) | návrh 15.8/1 |
| 13 | `/tools/medallion` přenese 3,9 MB | ostatní stránky 414 až 558 kB | návrh 15.8/2 |
| 14 | Osnova nadpisů H1 → H3 | seznam katalogu `/model` (karty), článek `jak-vznika-busta-z-fotky` v en/es (text v databázi) | nechán |
| 15 | PNG nebo JPG přes 30 kB | 24 stránek (vykreslené příklady nástrojů, fotky v článcích) | návrh 15.8/7 |

**V pořádku bylo a zůstává:** žádné přesměrování ani chybový stav, canonical každé stránky na sebe, hreflang vzájemný
a s x-default (0 chyb), titulky a popisy v rámci jazyka jedinečné, právě jedno H1 a žádné dvakrát, žádný obrázek bez
`alt`, JSON-LD platné, každá otázka `FAQPage` a každý krok `HowTo` ve viditelném textu, Open Graph úplný, žádná
osiřelá indexovaná stránka, všechno nejvýš dva kroky od úvodní strany (nedosažitelné jsou jen `/farm` a `/models`,
obě `noindex`). Šablonový text: medián 7 % slov stránky, nejvýš 27 % (ozdoba na jojo, ozdoba na tašku), žádná stránka
přes 40 %. Vlastní text stránek nástrojů 597 až 1 534 slov. Tenké jsou jen `/contact` (102 slov), `/en/blog`
a `/es/blog` (jeden článek) a `noindex` stránky. Rychlost viz 15.4.

### 15.3 Co je opraveno

**Titulky.** `App\Support\PageTitle`: značka „ · matplace“ zůstává v titulku, jen dokud se celý vejde do 60 znaků;
delší titulek si nechá vlastní slova (název webu stojí ve výsledku hledání nad každým odkazem zvlášť). Dělá to layout
pro všechny stránky; katalog Inspirace si drží celý titulek (`titleFull`, zkracuje se v něm název modelu,
`docs/AN.md` §16). Vlastní titulky všech nástrojů ve třech jazycích mají nejvýš 60 znaků (test). Na dotaz nově:

| stránka | bylo | je |
|---|---|---|
| `/tools/pet-figurine` cs | Figurka mazlíčka z fotky: 3D model psa nebo kočky k tisku | Figurka psa nebo kočky z fotky: 3D tisk na zakázku; H1 „Figurka psa nebo kočky z fotky“ (název nástroje zůstává v řádku nad ním) |
| en / es | Pet figurine from a photo: your dog or cat in 3D / Figura de mascota a partir de una foto en 3D | Dog or cat figurine from a photo: custom 3D print / Figura de su perro o gato a partir de una foto, en 3D (+ H1) |
| `/tools/figure` cs / en / es | Busta nebo figurka z fotky: 3D model a tisk | Busta nebo figurka z fotky: 3D tisk na zakázku od 99 Kč / Bust or figurine from a photo: custom 3D print / Busto o figura a partir de una foto: impresión 3D a medida |
| `/tools/keychain` cs | Klíčenka se jménem na 3D tisk: tvar, jméno a obrázek, zdarma | Klíčenka se jménem z 3D tiskárny: návrh zdarma, od 99 Kč |
| `/tools/ornament` cs | Vánoční ozdoba na 3D tisk z obrázku nebo jména: model zdarma | Vánoční ozdoba z obrázku nebo jména: 3D tisk od 99 Kč |
| `/tools/charm` cs | Přívěsek z obrázku nebo jména pro 3D tisk: návrh zdarma | Přívěsek z obrázku nebo jména: 3D tisk od 99 Kč |
| `/tools` H1 | Nástroje | Nástroje pro 3D tisk: model z fotky, jména nebo rozměrů (`tools.seo.h1`; menu a odkazy dál „Nástroje“) |
| `/blog` | Blog o 3D tisku · popis 70 znaků | Blog o 3D tisku: návody, ceny a nápady na dárky · popis 144 znaků (`site.blog.seo_title`, `seo_description`) |

„Od 99 Kč“ je nejnižší cena zakázky (`config/farm.php` `min_price`) a text každé z těch stránek ji dokládá kalkulací.
Titulky, popisy ani H1 se nemění podle stavu farmy (jsou to texty souborů, žádná podmínka).

**Cena v úvodu.** Busta: věta s cenami z kalkulací 18. 10. 2026 (`docs/AG.md`, `config/site.php` `example_items`):
80 mm 14 g, necelá hodina, 99 Kč; 120 mm 130 Kč; 150 mm 200 Kč (cs/en/es, v en/es „CZK“ jako u ostatních nástrojů).
Mazlíček: změřenou cenu nemáme (model stojí kredity), proto jen pravdivá věta „cenu vidíte v kalkulaci před
objednáním; nejnižší cena zakázky je 99 Kč“.

**Drobečková navigace.** Layout bere `crumbs` (`[[název, adresa], …]` bez úvodní strany) a tiskne `BreadcrumbList`;
komponenta `<x-crumbs>` je tatáž cesta jako řádek na stránce („matplace › 3D tisk Praha“). Stránka nástroje ji má
sama (Nástroje › nástroj; viditelný je stávající řádek „← Nástroje · název“), řádek + data nově mají `/tools`,
statické stránky (`/3d-tisk-praha`, `/business`, O nás, Kontakt, Časté otázky, podmínky), ochrana osobních údajů,
podmínky farmy, `/blog`, `/materials`;
`/gifts` jen data (řádek „← Nástroje“ už má). Článek, model a kolekce měly `BreadcrumbList` dřív. Jedna na stránku.

**Blog.** `App\Support\BlogSeo`: soubor článku (`resources/blog/cs/<slug>.md`) může v hlavičce nést `seo_title`
a `seo_description`. Do článku se nezapisují (`blog:sync` je nečte do databáze, nadpis, text ani `updated_at` se
nemění, takže `dateModified` a `lastmod` zůstávají dnem skutečné změny textu); čte je jen stránka pro `<title>`,
`og:title` a popis. Všech 31 článků v repozitáři má popis 110 až 155 znaků psaný jako celá věta (dřív prvních 155
znaků perexu s „...“) a 22 článků s nadpisem přes 60 znaků má kratší titulek pro hledání. H1 i `Article.headline`
zůstávají nadpisem článku. Článek bez souboru (z adminu, ze starého webu) se chová jako dřív. `Article.image` má
náhradu: článek bez fotky ukazuje svou kartu pro sdílení (`/og/article/<slug>.png`), dřív pole chybělo u 19 článků.
Fotka článku má `alt` (nadpis), rozměry a `fetchpriority="high"`; obrázky v textu dostávají rozměry (soubor
u nás: `public/img`, veřejné úložiště) a od druhého `loading="lazy"` (`App\Support\BlogLinks::pictures`).

**Odkazy článků na vypnuté nástroje.** Na produkci 13. 11. vedly čtyři články na `/tools/phone-stand` a
`/tools/colors`, které odpovídají 404 (nástroje vypnuté v `/admin/tools`). `App\Support\BlogLinks::live` při
vykreslení nechá z odkazu na nástroj, který není veřejný, jen jeho slova; odkaz se vrátí sám, až bude nástroj
zapnutý. Text článku v databázi se nemění.

**Prolinkování.** Každý nástroj má blok „Kam dál“ (podobné nástroje + články): `config/landing.php` `next` pro
23 nástrojů, které neměly nic (bez textů „Než objednáte“ a „Pro koho“, ty se u nich neukazují); `figure` a `pet`
odkazují na článek o figurce, `qr` na svůj článek. Článek má nový blok „Nástroje z článku“: nástroje, jejichž stránka
ho jmenuje (jen veřejné). Pod seznamem `/tools` jsou odkazy na cenu ze souboru, Dárky, 3D tisk Praha, 3D tisk pro
firmy a Materiály (`/3d-tisk-praha` dosud neměla žádný odkaz z obsahu, jen z patičky). Patička „Co vyrobíme“ má
navíc vánoční ozdobu a perníček se jménem (`config/seo.php` `footer_tools`; stránky kampaně měly 9 a 11 odkazů,
ostatní přes 100). Odkaz na
účet na `/models` má `rel="nofollow"` (vedl robota přes 302 na přihlášení).

**Mapa stránek.** `/materials` v ní chyběla (není ve `PageController::PAGES`); je tam ve třech jazycích.

**Náhled odkazu.** Naše karty `/og/…` říkají rozměr (`og:image:width` 1200, `height` 630), první sdílení na
Facebooku pak ukáže obrázek hned.

### 15.4 Co jsem neměnil a proč

- **Rychlost.** Produkce na telefonu 390 px z rychlého připojení: LCP 208 až 496 ms (medián 320), CLS nejvýš
  0,003, 11 až 26 dotazů, medián 467 kB, žádný vodorovný posun, HTML s gzip. Písmo se přednačítá, skripty jsou
  moduly (odložené samy), obrázky karet mají rozměry, WebP a lazy, první `fetchpriority`. Poslední Lighthouse na
  pomalé síti (`docs/AH.md` §11.x) dal 83 až 91. Nenašel jsem levnou opravu, která by čísla zlepšila; větší jsou v 15.8.
- **Texty 23 nástrojů bez bloků „Než objednáte“ a „Pro koho“.** Mají místně 500 až 870 slov vlastního textu (úvod,
  postup, 6 otázek, příklady) a šablonového textu 9 až 18 % (na produkci nejvýš 27 %). Nové otázky jsem k nim
  nepsal: otázky bez podkladu z provozu by byly vycpávka. Doplnil jsem jim jen „Kam dál“. Zadání chtělo vlastní
  obsah ke každému nástroji; u těchto 23 tedy zůstává, jak byl.
- **`Product` na stránkách nástrojů.** Cena nástroje závisí na tom, co si člověk nastaví, a tisk se nabízí jen
  s otevřenou farmou; pevná cena ve strukturovaných datech by nebyla pravda v každém stavu. Návrh v 15.8.
- **Úvodní strana.** Titulek a popis sjednotila řídící 12. 11. (`docs/AN.md` §23.6), obsah je rozhodnutí Romana.
  H1 „Co chcete vyrobit?“ neobsahuje, co lidé hledají; návrh v 15.8.
- **`alt` karet nástrojů** zůstává prázdný: název nástroje stojí hned pod obrázkem (čtečka by ho četla dvakrát).
- **Katalog, `/farm`, `/models`:** `/farm` (vstup do zakázky) a `/models` (dokud nemá dost modelů) mají `noindex`
  záměrně (`config/seo.php`, `docs/AP.md` §14); katalog je mimo index rozhodnutím Romana.

### 15.5 Před a po

Produkce „před“ je měření z 13. 11. ráno (HTML a odkazy bez Chromu 08:40, telefon 06:50). Místně „před“ je commit
7dc7f357 a „po“ tato větev na stejném serveru a stejné kopii databáze (`php artisan serve`, bez komprese, blog jen
česky, všechny nástroje zapnuté, proto má víc adres než produkce). **Produkce „po“ neexistuje**, větev není nasazená.

| ukazatel | produkce před | místně před | místně po |
|---|---|---|---|
| adres v mapách stránek | 230 | 249 | 252 (+ `/materials` ×3) |
| přečteno / odpovídá 200 | 236 / 236 | 257 / 255 | 260 / 258 (`/en/blog`, `/es/blog` místně nejsou) |
| titulek přes 60 znaků | 152 | 175 | **0** |
| titulek přes 70 znaků | 28 | 29 | **0** |
| medián délky titulku | 65 | 65 | 55 |
| stejný titulek nebo popis dvakrát | 0 | 0 | 0 |
| stránek s `BreadcrumbList` | 26 | 23 | **246** |
| stránek bez strukturovaných dat | 27 | 27 | 9 (`/model`, `/farm`, `/models` ×3 jazyky) |
| strukturovaná data s nálezem (Article bez obrázku) | 19 | 19 | **0** |
| vnitřní odkazy na přesměrování | 3 | 3 | **0** |
| vnitřní odkazy na 404 | 2 adresy ze 4 článků | 0 (nástroje místně zapnuté) | 0; s vypnutým nástrojem ověřeno testem |
| stránky s odkazy jen z menu a patičky | 33 | 33 | 30 (zbývají právní texty, O nás, Kontakt, Časté otázky) |
| hreflang s chybou, canonical jinam, bez H1 nebo s více H1 | 0 | 0 | 0 |
| obrázky bez `alt` | 0 | 0 | 0 |
| šablonový text, medián | 7 % | 6 % | 6 % |
| HTML, medián | 173 kB | 133 kB | 135 kB |
| stránek s aspoň jedním nálezem | 233 | 254 | 73 |
| nálezů celkem | 557 | 578 | 144 |
| telefon 390 px, LCP medián (39 stránek) | 320 ms | 1 772 ms | 1 832 ms |
| telefon, CLS nejvýš | 0,003 | 0 | 0 |
| telefon, dotazů na stránku (medián) | 13 | 12 | 12 |
| telefon, stránek širších než okno | 0 | 0 | 0 |

Místní LCP se nezměnilo (60 ms je šum místního serveru; měření, které běželo souběžně s PHPUnit, dalo 2 060 ms).
Ze 144 zbylých nálezů místně: 30× „jen z menu a patičky“, 18× PNG nad 30 kB, 17× odkaz na `/c/<token>` produkce
nebo na článek, který je jen v produkční databázi, 15× málo slov (`/model` a `/materials` bez dat místní databáze,
`/contact`, `noindex` stránky), 9× bez strukturovaných dat, zbytek `noindex` stránky a krátké titulky právních textů.

Stránky kampaně a vstupní stránky česky (titulek ve znacích i se značkou, pokud ji má; odkazy = kolik
stránek na ni vede celkem / z obsahu):

| stránka | titulek před → po | odkazy před → po | strukturovaná data po |
|---|---|---|---|
| `/tools/figure` | 54 → 55 (nově „na zakázku od 99 Kč“) | 102 / 10 → 103 / 11 | BreadcrumbList, FAQPage, HowTo |
| `/tools/pet-figurine` | 68 → 50 | 102 / 6 → 103 / 6 | BreadcrumbList, FAQPage, HowTo |
| `/tools/keychain` | 71 → 56 | 102 / 11 → 103 / 16 | BreadcrumbList, FAQPage, HowTo |
| `/tools/medallion` | 64 → 53 | 102 / 4 → 103 / 5 | BreadcrumbList, FAQPage, HowTo |
| `/tools/ornament` | 71 → 53 | **9 / 7 → 103 / 8** | BreadcrumbList, FAQPage, HowTo |
| `/tools/gingerbread` | 67 → 56 | **11 / 9 → 103 / 11** | BreadcrumbList, FAQPage, HowTo |
| `/tools/charm` | 66 → 58 | 102 / 6 → 103 / 10 | BreadcrumbList, FAQPage, HowTo |
| `/tools/organizer` | 67 → 56 | 102 / 8 → 103 / 11 | BreadcrumbList, FAQPage, HowTo |
| `/gifts` | 60 → 60 | 102 / 4 → 103 / 6 | BreadcrumbList, FAQPage, HowTo, Service |
| `/tools` | 53 → 53, H1 na dotaz | 104 / 89 → 105 / 89 | BreadcrumbList, ItemList |
| `/3d-tisk-praha` | 68 → 57 | **102 / 0 → 103 / 1** | BreadcrumbList, FAQPage, Service |
| `/business` | 55 → 55 | 104 / 5 → 105 / 5 | BreadcrumbList, FAQPage, Service |
| `/` | 58 → 58 | beze změny | FAQPage, HowTo, Organization, WebSite |

**Testy (13. 11. 2026, jak opravdu běžely).** `tests/Feature/PageOptimizationTest.php`, 7 testů: pravidlo titulku
a délka titulků všech nástrojů ve třech jazycích; drobečky jako řádek i jako data; titulek a popis článku z hlavičky
souboru, nadpis, `dateModified` a obrázek článku; „Kam dál“ u každého nástroje a nástroje v článku; odkaz na vypnutý
nástroj; rozměry obrázků a rozměr karty náhledu; `/materials` v mapě, patička a odkazy pod seznamem nástrojů.
`SeoTest` upraven na třech místech (titulek přes `PageTitle`, `BreadcrumbList` mezi druhy dat stránky). Celá sada
jednou: **1 200 testů, 101 871 assertions, 3 selhání, 11 přeskočených, 1 h 55 min**; selhávají jen tři testy, které
padají i na `main` (`PapelPortraitTest`, `PrintPhotosTest`, `YouTubeChannelTest`, stejné důvody jako v 14.9).
Po celé sadě přišly ještě dvě změny v `config/seo.php` (perníček do patičky; klíčenka, ozdoba a perníček mezi adresy,
na které se `searchconsole:inspect` ptá první) a jeden řádek testu; po nich znovu
`SeoCleanupTest|PageOptimizationTest|SeoQualityTest|WebRound2Test|SearchConsole`: 32 testů OK. Celá sada po těchto
dvou řádcích znovu neběžela. `vendor/bin/pint --dirty` bez nálezů, `npx tsc --noEmit -p .` čisté, `npm run build`
prošel i s kontrolami bundlu.

**Sloučení `main`.** `main` se během práce posunul (41f02d51: nástroj Čelenka, přímá cívka bez měniče). Sloučil jsem
ho do větve: konflikt jen ve třech `lang/*.json` (vzal jsem soubory z `main` a znovu pustil `lang_add.py` nad
`lang/src/page_optimization.json`, všechny tři platné), čelenka dostala „Kam dál“ (`config/landing.php` `next`; její
titulky mají 43 až 45 znaků). Po sloučení `npm run build` a 104 testů stránek, vyhledávačů a čelenky
(`Headband|PageOptimizationTest|SeoTest|SeoQualityTest|SeoCleanupTest|LandingPagesTest|ToolPageTest|PraguePageTest|
PetFigurineTest|HomeWhatToMakeTest|ToolsCatalogTest|BlogTest|CatalogValueTest`): OK, 22 908 assertions.
**Celá sada po sloučení neběžela** (běžela nad větví před ním).

### 15.6 Nasazení

- **Migrace: žádná vlastní. `.env`: nic.** (Větev má sloučený `main` 41f02d51 a s ním jeho migraci
  `2026_11_13_100000_farm_printers_changer_attached`; ta patří `main`, ne této práci.) Nové třídy `App\Support\PageTitle`, `BlogSeo`, `BlogLinks`, komponenta
  `components/crumbs.blade.php`, `config/landing.php` `next`, `config/seo.php` `footer_tools` a `inspect.routes`
  (pondělní `searchconsole:inspect` se nově ptá i na klíčenku, ozdobu a perníček: o tři dotazy víc).
- **`npm run build`** (nové třídy v řádku drobečků a pod seznamem nástrojů).
- **Po nasazení `php artisan matplace:sitemap`** (přibude `/materials`; změněné soubory `tools_seo` posunou `lastmod`
  pěti nástrojů: figure, pet, keychain, ornament, charm; jinak se mapa přepíše až ve 4:40).
- `blog:sync` není potřeba: `seo_title` a `seo_description` čte stránka ze souboru, databáze se nemění.
  (Když se `blog:sync` po nasazení pouští, nahlásí „unchanged“.)
- Stránky se po nasazení vykreslí znovu samy; `php artisan optimize` jako u každého nasazení (nová konfigurace).
- Po nasazení: `node scripts/seo_audit.mjs --base=https://matplace.com --out=storage/app/seo-audit/prod-after
  --browser=important` a porovnat s tabulkou v 15.5.

### 15.7 Co není ověřeno

- **Na produkci neběželo nic z větve.** Sloupec „po“ je z místního serveru; produkce má jiná data (8 nástrojů
  vypnutých, články ze starého webu, katalog, víc barev filamentů v konfiguraci stránky nástroje).
- **Odkazy článků na vypnuté nástroje** jsem ověřil testem s vypnutým nástrojem, ne na produkčních datech; po
  nasazení má audit hlásit `linksBroken: 0`.
- **Články, které jsou jen v produkční databázi** (`jak-vznika-busta-z-fotky` cs/en/es a případné další ze starého
  webu), nemají soubor v repozitáři: zůstává jim nadpis jako titulek (zkrátí se jen o značku) a začátek perexu jako
  popis. Článek `jak-vznika-busta-z-fotky` má v en/es osnovu H1 → H3 (text v databázi, neměnil jsem).
- **Rozměry obrázků v článcích** se doplní jen souborům, které na serveru jsou (`storage/app/public/blog`, `public/img`);
  na produkčních souborech neověřeno.
- **Stav indexu** nevidím (Search Console je jen z produkce): jestli Google nové titulky převezme a kdy, ukáže až
  `searchconsole:inspect` a Výkon za dva až tři týdny.
- **Rich Results Test** jsem nepouštěl (cizí služba, stránky větve nejsou veřejné). JSON-LD je platný JSON a audit
  ověřuje shodu s viditelným textem; po nasazení stojí za ruční zkoušku `/tools/keychain` (HowTo, FAQPage,
  BreadcrumbList), `/3d-tisk-praha` a jeden článek.
- **Ceny v textech** („od 99 Kč“, busta 99/130/200 Kč) jsou z kalkulací z 18. 10. a z `config/farm.php`; znovu jsem
  je na produkci nepočítal.
- Angličtinu a španělštinu jsem psal sám, rodilý mluvčí je nečetl.

### 15.8 Návrhy větších zásahů (neprovedeno)

1. **Konfigurace stránky nástroje ven z HTML.** HTML stránky nástroje má na produkci 170 až 300 kB (s gzip
   zhruba pětinu); 82 kB je `window.MP_TOOL` (texty `toolpage` a paleta barev, stejné na každém nástroji) a 30 kB
   `MP_PARAM`. Texty rozhraní jako soubor `/build/…` na jazyk (stáhne se jednou a drží v mezipaměti) by ubraly
   třetinu HTML každé stránky nástroje. Zásah do `tools/page.blade.php` a `tool_page.ts`.
2. **`/tools/medallion` stahuje 3,9 MB** (ostatní stránky kolem 460 kB): výchozí model s řetězem ze 40 článků.
   Na mobilních datech je to znát. Lehčí výchozí náhled (bez řetězu, řetěz po zapnutí) je zásah do nástroje.
3. **Úvodní strana:** H1 „Co chcete vyrobit?“ neříká, co web je. Pod H1 nebo místo něj věta s tím, co lidé
   hledají („3D tisk na zakázku: dárek z fotky, díl podle popisu“), případně H1 = titulek a otázka jako popisek
   pole. Je to rozhodnutí o vzhledu první obrazovky (Roman).
4. **`Product` s cenou „od“ u nástrojů kampaně** (busta, klíčenka, ozdoba, medaile, přívěsek): šlo by poctivě jen
   jako `Product` s `AggregateOffer.lowPrice` = nejnižší cena zakázky a jen když farma tiskne (data by se pak měnila
   se stavem farmy; titulky ne). Chce rozhodnutí, jestli to za to stojí: Google pro takové stránky bohaté výsledky
   s cenou ukazuje nerad, když na stránce není jedna cena a tlačítko koupit.
5. **Fotky skutečných výtisků** místo vykreslených příkladů u nástrojů kampaně (`config/site.php` `example_items`
   má pořád `photo => null`). Pro hledání obrázků i pro důvěru je to nejsilnější chybějící obsah; fotit musí Roman.
6. **`max-image-preview:large`** v `robots` meta u indexovaných stránek (větší náhled obrázku ve výsledcích
   a v Discover). Dnes test `SeoQualityTest` žádá, aby indexovaná stránka `robots` meta neměla; změna pravidla.
7. **Příklady nástrojů jako WebP** (`public/img/tool-examples`, 137 PNG, 3 MB, 10 z nich přes 30 kB): úspora
   malá (načítají se líně), zásah do `matplace:tool-examples` a šablony.
8. **Blog v angličtině a španělštině** má jeden článek (`jak-vznika-busta-z-fotky`). Doporučení: nechat, jak je,
   dokud anglické a španělské stránky nástrojů nemají zobrazení; pak přeložit čtyři až šest článků, které vedou
   k prodeji (cena 3D tisku, figurka z fotky, klíčenka a přívěsek, vánoční dárky, litofanie), ne všech 25. Stránka
   blogu s jedním článkem ničemu neškodí (je v mapě, má hreflang), ale sama nic nepřivede.
9. **Stránka `/3d-tisk-plzen`** podle stejné šablony jako Praha, pokud farmy tisknou na Plzeňsku (viz otázka 1).

### 15.9 Search Console: o které adresy požádat (Roman, po nasazení)

Kontrola adresy URL → Požádat o indexování, kolem deseti denně. První den:

1. `https://matplace.com/gifts`
2. `https://matplace.com/tools/figure`
3. `https://matplace.com/tools/pet-figurine`
4. `https://matplace.com/tools/keychain`
5. `https://matplace.com/tools/medallion`
6. `https://matplace.com/tools/gingerbread`
7. `https://matplace.com/tools/ornament`
8. `https://matplace.com/tools`
9. `https://matplace.com/3d-tisk-praha`
10. `https://matplace.com/business`

Druhý den:

11. `https://matplace.com/tools/charm`
12. `https://matplace.com/tools/organizer`
13. `https://matplace.com/blog/vanocni-ozdoby-se-jmenem-z-3d-tiskarny`
14. `https://matplace.com/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026`
15. `https://matplace.com/blog/figurka-z-fotky-busta-a-mazlicek`
16. `https://matplace.com/blog/klicenka-a-privesek-z-obrazku`
17. `https://matplace.com/tools/nameplate`
18. `https://matplace.com/tools/relief`
19. `https://matplace.com/tools/qr`
20. `https://matplace.com/materials`

24. 11. vyjde `vanocni-darky-z-3d-tisku-do-24-12-terminy`: požádat ten den. Proti seznamu v 14.10 jsou výš
klíčenka, medaile a obě vánoční ozdoby (vede na ně kampaň) a přibyly `/business` a `/materials`. Úvodní stranu
není třeba (je v indexu); po nasazení ji ale stojí za to nechat znovu načíst (Kontrola adresy URL → Otestovat
zveřejněnou adresu → Požádat o indexování), protože z ní vedou odkazy na všechno výše.

### 15.10 Otázky pro Romana

1. **„Tiskneme na vlastní farmě v Praze“.** Stránka `/3d-tisk-praha` (cs/en/es), její `Service` a článek
   `3d-tisk-praha-jak-to-u-nas-chodi` (vyjde 1. 12., říká „na vlastní farmě v Praze 1“) stojí na větě z 28. 10.
   („město farmy je Praha“, §8). Zadání z 12. 11. ale mluví o farmách Útušice, Bory a Stod. Pokud se v Praze
   netiskne, je to nepravdivé tvrzení na stránce, kterou chceme dostat do indexu: buď text upravit („sídlo v Praze,
   tiskneme na farmách na Plzeňsku, do Prahy posíláme do druhého dne“), nebo potvrdit, že platí. Text jsem neměnil.
2. **Článek o stojánku na telefon** (`stojanek-na-telefon-na-miru`) je zveřejněný, ale nástroj je na produkci
   vypnutý. Odkazy z něj teď zmizí samy; nechat článek zveřejněný, nebo ho stáhnout, dokud se nástroj nevrátí?
3. **„Od 99 Kč“ v titulcích** busty, klíčenky, ozdoby a přívěsku (jen česky): je to nejnižší cena zakázky a každá
   z těch stránek ji dokládá kalkulací. Vyhovuje, že cena stojí už ve výsledku hledání?
4. **`/farm` má `noindex`.** Zadání ji jmenuje mezi vstupními stránkami; dnes je to vstup do zakázky (94 slov,
   zavřený robotům `/farm?`). Nechat mimo index (doporučuji), nebo z ní udělat stránku „3D tisk na zakázku“ s textem?
5. **Fotky skutečných výtisků** k nástrojům kampaně (busta, figurka psa, klíčenka, medaile, ozdoba): bez nich
   stránky ukazují vykreslené příklady. Fotí se v sobotu 14. 11. i tyhle kusy?
6. **`Product` s cenou „od“ a H1 úvodní strany** (15.8/3 a 15.8/4): udělat, nebo nechat?
