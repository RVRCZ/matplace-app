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
