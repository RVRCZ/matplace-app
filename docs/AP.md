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
- Neověřeno a ponecháno z původního textu: u světelného nápisu věta „Jedna zakázka se u nás tiskne jednou barvou“
  (difuzor v bílé jako druhá zakázka). Farma dnes umí víc filamentů v jedné podložce (AL.md §10), ale jestli to platí
  i pro díly světelného nápisu, nevím; řídící může větu zrušit, pokud ano.

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
| navíc od řídící | `/3d-tisk-praha` + `google-business.md` (§8), dalších 10 nástrojů (§9, dávky A, B, C), `komunita-prispevky.md` (§10) |

Co zůstává na jiných: fotky výtisků (session 2, Roman vybere), Search Console export (Roman) a podle něj druhé kolo
title/description, Google Business Profile založí Roman, vánoční Shorts session 2.
