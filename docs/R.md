# R: úprava modelu (session 3)

Větev `feature/tools-edit` (ze `fbf9e67` session 1, 7. 10. 2026; `main` 5355fa3 slitý tentýž den), zadání
`docs/prompts/nastroje.md`, část „Session 3 – Úprava modelu“. Roman: začít filament artem (vrstvený obraz v rámu)
a dělením modelu, ty jsou k Vánocům nejužitečnější. Tenhle dokument popisuje **první várku**: oba nástroje od vstupu
po cenu a stažení. Zbytek session 3 je v sekci 7 jako to, co teprve přijde.

Souběžně běžely session 1 (`param.*`, `ParametricGenerator`, `shape_kinds`) a farma (`OrderService`, `OrderFlow`,
`GcodeSlot`, `OrderController`, `farm.ts`); do těch souborů jsem nesahal. Vlastní soubory session 3:
`engines/python/art_tool.py`, `edit_tool.py`, `App\Domain\Tools\ArtGenerator`, `ModelEditor`, job `EditModel`,
`EditToolsController`, `Api\EditApiController`, `calc/art.ts`, `calc/edit.ts`, `tools/filament_art.blade.php`,
`tools/edit.blade.php`, texty `lang/<loc>/edit.php` (ne `param.php`), `tools_seo/{filament_art,split}.php`. Sdílené
soubory jen malými přídavky: `config/tools.php`, `routes/web.php`, `lang/<loc>/tools.php`, `tool_page.ts` (mapa
modulů), `viewer.ts` (dvě nové metody), `ModelFile` (`builtForPrinting`, `printHints`), `UploadController::describe`,
`download.ts` (odkazy na díly), `ToolExamples` (ukázky a karty nových nástrojů).

## 1. Co vzniklo

**Dva nástroje v katalogu**, každý s vlastní stránkou, kartou, SEO texty ve třech jazycích:

| nástroj | adresa | modul | co dělá |
|---|---|---|---|
| Obraz z filamentu | `/tools/filament-art` | `art` (`art_tool.py`, `ArtGenerator`) | obrázek → barvy filamentů → vrstvený obraz z desek v rámu, nebo jeden tisk s barvami nad sebou |
| Rozdělení modelu na díly | `/tools/split` | `edit` (`edit_tool.py split`, `ModelEditor`, job `EditModel`) | model větší než podložka → díly s kolíky / rybinovými klíči, čísla v řezu, položené řezem dolů, mapa dílů |

### Obraz z filamentu (`filament_art`)

Obrázek jde stejnou cestou jako u přívěsků a podtácků session 1 (`shape2d.colors`: pozadí pryč, k‑means v Lab,
cívka ke každé barvě, obrysy `find_contours`); nástroj bere její výstup `stack` (barva a všechno před ní) a dělá z něj
plast dvěma způsoby:

- **Vrstvený obraz z desek** (výchozí, předloha `docs/img/stlbuddy-layered-art.png`): každá barva je deska 1,5–3 mm
  (`plate`) s plochou své barvy **plus všeho, co leží před ní** – zadní deska nese celou siluetu, přední jen svou
  barvu (černá linka úplně vpředu). Desky se skládají jako vystřihovánka. Mezi deskami **distanční sloupky** Ø 6 mm
  o výšce `gap` 2–5 mm (až čtyři, co nejdál od sebe tam, kde má deska před nimi místo; malá deska dostane sloupky
  Ø 4 nebo 3, a když se nevejdou ani ty, lepí se naplocho – varování `small_plate_flat`); flag „naplocho“ sloupky
  vypne. Linky tenčí než 0,8 mm by se jako samostatná deska zlomily: morfologicky se otevřou a připadnou barvě za nimi
  (`thin_merged` s plochou v mm²). **Zadní deska** (`body`): u tvaru „podle obrázku“ je zadní deskou silueta sama
  (oddělené kousky spojí můstky `shape2d.joined`); u kruhu/obdélníku s vyříznutým motivem vznikne navíc plná deska
  v cívce, která je od všech barev motivu nejdál (sněhulák na fialové). **Rám** kulatý / čtvercový: deska 2 mm
  s lemem 1,6 mm vysokým přes celou hloubku obrazu, zezadu drážka 10 × 4 na hřebík, s flagem LED o 10 mm hlubší
  a s výřezem 8 × 5 na kabel v lemu. Tiskne se dnem dolů, desky se vlepí dovnitř. **Návod** (`notes.guide`): desky
  od zadní k přední, každá jako SVG obrys (zjednodušený na 0,15 mm) s tečkami sloupků, cívka, podíl plochy; stránka
  ho kreslí pod cenou a ukládá vedle modelu (`files/<uuid>/guide.json`, `GET /api/tools/edit/<uuid>/guide`).
  Pohled „use“ = složený obraz (rozložený pohled jede po ose Z, `viewer.setSpreadAxis`), uložený STL = **print**:
  všechny díly položené vedle sebe na plátu (řady do 240 mm), každý díl zvlášť jako `files/<uuid>/parts/<díl>.stl`.
- **Jeden tisk, barvy jako stupně**: podklad `base` 0,8–3 mm (cívka nejdál od barev motivu, nebo nejnižší barva
  u siluety) a na něm každá barva o `step` 0,4–1,2 mm výš (celé vrstvy 0,2). V každé vrstvě tisku je jediný filament
  → `notes.color_changes` {z, part, code, hex} jako u session 1, `ModelFile::colorChanges()` je dá do projektu Orca /
  Prusa (`ColorChange::addAll`) a farma do výměn (s N barvami farmy je to teď objednatelné, nejvýš čtyři).

Pole (`ArtGenerator::FIELDS`): šířka a výška 50–250, okraj 0–20 (jen kruh/obdélník), počet barev 1–8, síla pozadí,
vyhlazení, kontrast/jas/sytost (složené pod „Upravit fotku“), podklad, krok, deska, mezera, šířka rámu 6–20. Volby:
režim, tvar podkladu, rám; flagy odstranit pozadí / naplocho / LED. Stránka: sekce Vstup → Rozměry → Barvy → Tisk;
seznam barev od přední desky k zadní se vzorníkem cívky (okno Barva), barvou v obrázku a podílem, šipky dopředu/dozadu
a sloučení; stavový řádek „79,8 × 150 × 17 mm · 4 díly · 4 barvy“; stažení: projekt Orca / Prusa (uloží návrh,
otevře kalkulaci s výběrem tiskárny), **každá deska zvlášť (ZIP)**, celý plát (STL). Návrh se ukládá jako `ModelFile`
kind `filament_art` s `part_colors`, `color_changes`, `paint`, `guide` (bez kreseb), `parts`, `pieces` (rozsahy
trojúhelníků), `parts_bbox`, `each`; `?from=<uuid>` otevře stránku se stejným nastavením.

### Rozdělení modelu (`split`)

`edit_tool.py` je společný základ „mám soubor“ pro celou session: načtení (`mesh_tool.load`), uzavření, když není
(`mesh_tool.solidify` – pymeshfix, union, přestavba přes mřížku jako ve farmě; a když manifold ani tak nechce,
`rebuild_solid`), decimace nad 2 M trojúhelníků (`fast_simplification` je na tomhle PC; bez něj nástroj odmítne
`too_heavy` s počtem), stavový soubor `<out>.stage` (loading / thinning / repairing / cutting / joints / numbers /
layout / done), který stránka čte přes `GET /api/files/<uuid>` (`edit.stage`), díly do `parts/`.

- **Plán řezů** (`analyse`, bez booleanů, ~1 s): podložka = preset farmy (250 − 2× okraj farmy ze `farm_settings`),
  220 × 220 × 250, 180 × 180 × 180, nebo vlastní (− 2 × 5 mm); zkouší 1–6 dílů v každé ose a každý díl posuzuje tak,
  jak se bude tisknout – **položený největší řeznou plochou dolů**, osa řezu se stává výškou; vybere nejméně řezů,
  pak nejméně dílů, pak největší díly. Roviny rovnoměrně; vlastní roviny (`planes.x/y/z`) se zachovají a doplánují
  se jen ostatní osy. Stránka ukazuje roviny na modelu (`viewer.setPlanes`: oranžové plochy jako děti meshe, takže
  jedou s ním) a posuvník pro každou; „Vrátit automatické roviny“.
- **Řezání**: průnik s kvádrem buňky (manifold3d), buňky uvnitř modelu se dotýkají přesně (objem dílů = objem
  modelu, test); prázdné buňky (< 2 mm³) odpadnou; nad 20 dílů varování.
- **Spoje**: styčná plocha dvou buněk = řez modelu rovinou (`Manifold.slice` po otočení osy do Z) ořezaný na buňku.
  *Kolíky* Ø 6 × 12: díry Ø 6,2 (vůle 0,2) do obou dílů, 1–4 podle plochy, co nejdál od sebe se stěnou 2,5 mm;
  samostatný díl `pins` (válečky stojí na plátu). *Rybinové klíče*: oboustranný rybinový klíč (8 mm na koncích,
  5 v pase, 3 mm do každé plochy) v drážce vyříznuté do obou řezných ploch podél 60 % délky, zasune se ze strany;
  díl `keys`; kde na něj není místo (řezná plocha užší než 12 mm), jsou místo něj kolíky (`key_as_pins`).
  Drážka se tiskne řezem dolů bez podpěr (stěny 15° od svislice). *Bez spojů*: hladké řezy.
- **Čísla** 0,6 mm hluboko do řezné plochy (DejaVu Sans Bold, 3–8 mm podle místa), na nejprostornějším místě mimo
  kolíky a drážku.
- **Položení**: každý díl otočený tak, že jeho největší řezná plocha leží na plátu (`lay_down`); díly v řadách do
  240 mm, kolíky a klíče vedle. `notes.map`: buňka [i, j, k], rozměr v celku, kam leží; stránka kreslí mapu po
  patrech (mřížka buněk s čísly v barvách dílů) a seznam dílů; díly v náhledu každý svou barvou (`edit.pieces_tris`).
  Výsledek je `ModelFile` kind `split` (`tool_params.source` = původní soubor, `report`, `parts`, `pieces`, `each`,
  `parts_bbox` → `ModelCheck` hlídá, že se každý díl vejde), `builtForPrinting()` = farma ho neotáčí.
- **Job `EditModel`** ve frontě `interactive` s `timings` (`queue_s`, `edit_s`, pak `convert_s`, `analyse_s` z
  `ProcessModelFile`); stránka se ptá na stav po 1,5 s a říká fázi. Lokálně (`QUEUE_CONNECTION=sync`) odpoví hned.

Stránka `/tools/split`: Soubor (nahrání nebo `?from=<uuid>` – u výsledku dělení se otevře jeho zdroj a výsledek)
→ Nastavení (podložka, roviny, spoje, čísla, položení) → Výsledek (věty, mapa, stažení: projekt, celý plát, díly po
jednom přes `GET /api/tools/edit/<uuid>/<díl>.stl`). Kalkulačka ukazuje odkazy na díly (`download.ts` pozná kindy
session 3 a bere `api/tools/edit`).

## 2. Rozhodnutí a proč

1. **Filament art není kind `ParametricGenerator`**, ale vlastní generátor a modul `art`. Zadání ho tam chtělo;
   `ParametricGenerator.php` a `param.*` ale patří session 1, která v nich souběžně pracuje. `ArtGenerator` má
   stejné rozhraní (`FIELDS`, `rules`, `clean`, `build`, `create`, `zip`) a stejný tvar odpovědi (`X-Model-Meta`
   s `parts`), takže převod na kind je později mechanický. Díly se ukládají jako soubory, ne přestavují z parametrů
   (`/api/tools/edit/<uuid>/<díl>.stl`) – to je totéž, co dělení modelu potřebuje tak jako tak.
2. **Zadní deska pod vyříznutým motivem** (kruh, obdélník s pozadím pryč) je samostatný díl v kontrastní cívce.
   Bez ní bílý sněhulák na bílé zadní desce zmizel; předloha (kočky) má fotku přes celý kruh, kde zadní deska není
   potřeba – ta cesta zůstává („odstranit pozadí“ vypnuto nebo fotka bez průhlednosti = `cover`).
3. **Plocha desky = barva + vše před ní**, ne „plus vše za ní“, jak to píše zadání. Deska vpředu musí mít na čem
   ležet; zadní deska nese celek. Je to totéž, co `stack` z `shape2d.colors`, takže oba režimy sdílí geometrii.
4. **Rybina jako volný oboustranný klíč**, ne jako pero na jednom dílu: pero vyčnívající z řezné plochy by díl
   položený řezem dolů zvedlo z plátu (podpěry); drážka v obou dílech se tiskne čistě a klíč zvlášť naplocho.
5. **Roviny jen rovnoběžné s osami** a jen rovné; položení dílů řezem dolů podle buňky, ne podle skutečné styčné
   plochy (u organických tvarů může být největší plocha jiná – díl si člověk ve sliceru otočí; FAQ to říká).
6. **Podložka farmy bere okraj ze `farm_settings`** (`bed_margin_mm`), ostatní presety 5 mm.
7. Texty do `lang/<loc>/edit.php` (ne `param.php`, který je session 1); klíče voleb `o.<volba>.<hodnota>`.

## 3. Rychlost (změřeno 7. 10. 2026, lokálně, Windows, 4 jádra)

`POST /api/tools/art/preview` (medián z pěti, PHP vestavěný server, pohled „use“, díly):

| ukázka | požadavek | STL |
|---|---|---|
| sněhulák, kulatý rám 160, 5 barev | 1,58 s | 719 kB |
| stromek bez rámu 150, 5 barev | 1,35 s | 358 kB |
| perníček jeden tisk 120 × 140, 4 barvy | 1,56 s | 142 kB |

Samotný `art_tool.py` 1,0–1,4 s (z toho ~0,45 s importy numpy/scipy/Pillow jako u session 1). Kulatý rám má
14 tis. trojúhelníků (lem 240 segmentů) – to je ten větší soubor. Cíl „do 2 s“ platí, „do 1 s“ ne (stejně jako
u session 1 – produkce je ~1,6× rychlejší, odhad 0,9–1,0 s).

`edit_tool.py` na **koule 996 tis. trojúhelníků, 308 mm** (sinusové hrboly, uzavřená; `split` na podložku 240
= 8 dílů, 3 roviny):

| krok | čas |
|---|---|
| `analyse` (načtení, plán) | 4,2 s |
| `split` s 48 kolíky | 6,6 s |
| `split` s rybinovými klíči (10 klíčů + 4 kolíky) | 7,4 s |
| `split` bez spojů na 180 | 8,5 s |

Kvádr 300 × 60 × 40 (12 trojúhelníků): analýza 1,1 s, dělení 1,2–1,4 s (většina je start Pythonu a importy).
Limit 60 s ze zadání je daleko; decimaci nad 2 M jsem na skutečném modelu neměřil.

## 4. Testy

`FilamentArtTest` (7): stránka ve třech jazycích a katalog; vrstvený obraz = rám, zadní deska, deska na barvu,
návod s hloubkami po 5 mm, rozsahy trojúhelníků pokrývají soubor, rozložení na plát; jeden tisk = podklad + barvy
po 0,4 mm, výměny na celých vrstvách; vlastní cívka a sloučení barev; odmítnutí špatného vstupu větou; návrh jako
`ModelFile` s díly ke stažení, projektem s výměnami, znovuotevřením a ZIPem. `ModelEditTest` (5): stránka; plán
řezů (240 → 1 řez, vlastní podložka 100 → 2 řezy, vlastní roviny, „vejde se“, cache analýzy); dělení s kolíky
(díly řezem dolů 150 mm vysoko, čísla, mapa, objem = objem kvádru, uzavřenost, `timings.edit_s`, díly po jednom,
cizí díl 404, znovuotevření); rybinové klíče, hladké řezy s vlastními rovinami, odmítnutí dělení toho, co se vejde,
špatné volby 422; busta s vruby se před řezáním uzavře. Celkem 12 testů, ~2 min (Python). Související existující
testy po změnách (`ToolPageTest`, `ToolsCatalogTest`, `SeoTest`, `ToolCardsTest`, `ProjectExportTest`,
`ColorsPayloadTest`) procházejí; celá sada viz sekce 8.

## 5. Co není ověřené

- **Nic se netisklo.** K vyzkoušení na farmě: (a) vrstvený obraz 150 mm bez rámu, 4 desky 2 mm se sloupky 3 mm –
  drží sloupky Ø 6 na desce 2 mm, sedí na sebe bez vůle (sloupky jsou součástí zadní desky, přední deska na nich
  jen leží, bez otvoru – lepí se)? (b) kulatý rám 180 mm: vejdou se desky do okna s vůlí 0,6 mm, drží drážka na
  hřebík? (c) dělení: kolík Ø 6 v díře 6,2 (vůle 0,2) – nejde tuho ani volně? Rybinový klíč 8/5/3 s vůlí 0,2 ve
  drážce tištěné vzhůru nohama (převisy 15° od svislice) – jde zasunout? Čísla 0,6 mm hluboko čitelná? Štítek
  „ověřeno tiskem“ nemá ani jeden nástroj.
- **Stránky jsem viděl jen v headless Chrome** (snímky obou, náhled sněhuláka se načetl, stavový řádek, cena, návod);
  nahrání souboru, posun rovin, tlačítko „Rozdělit“, zpět/vpřed a stažení jsem proklikal jen přes API (testy),
  ne myší. Mapa dílů a barvy dílů ve vieweru jsou ověřené typovou kontrolou a daty z testu, ne okem.
- **Projekt 3MF** s výměnami pro jeden tisk jde stejnou cestou jako u session 1 (`ColorChange::addAll`); ve
  skutečné Orce jsem ho neotvíral. Projekt vrstveného obrazu a dělení je jeden plát se všemi díly v jedné barvě –
  barvy desek do slotů projekt zatím nenese (exportér objektové barvy neumí); desky se tisknou po jedné.
- **Farma a vrstvený obraz**: objednávka „Vytisknout u nás“ pošle celý plát jako jeden výtisk v jedné barvě.
  Každá deska svou barvou = N samostatných výtisků; to farma neumí a stránka to neslibuje (text říká „každá deska
  se tiskne zvlášť ve své barvě na kterékoli tiskárně“). Jeden tisk s barvami nad sebou na farmě funguje (výměny).
  → **Pro řídící session**: objednávka sady dílů po barvách (každý díl `part_colors` vlastní cívka, N výtisků v jedné
  zakázce) by vrstvený obraz i vícebarevné sady zpřístupnila farmě.
- Fotky lidí: postup je pro kresby; z fotky vybere hlavní barvy (plakát).

## 6. Nasazení (Roman)

Podle `docs/DEPLOY-BETA.md` (merge `origin/main` identitou `deploy`, stop na konfliktu). Nic nového v `.env`, žádná
migrace této větve (migrace farmy `2026_10_14_100000_farm_orders_color_changes` je z `main`). Python: žádný nový
balíček povinný; **`fast_simplification`** (`pip install fast_simplification` do `/opt/matplace-py`) zapne decimaci
modelů nad 2 M trojúhelníků, bez něj je nástroj odmítne s důvodem. `npm run build`, `php artisan view:clear`,
restart `php8.2-fpm` a `matplace-worker` (job `EditModel` běží ve frontě `interactive`). Karty a ukázky
(`public/img/tools/{filament_art,split}-*`, `public/img/tool-examples/filament-art-{1,2,3}.png`) jsou v repozitáři,
kreslené z lokálního katalogu cívek (37 PLA+); na produkci se nepřekreslují.

## 7. Co ze zadání session 3 teprve přijde

Hotové: `/tools/filament-art` (oba režimy, rám, LED, návod), `/tools/split` (podložky, roviny, kolíky, rybiny,
čísla, položení, mapa), společný základ `edit_tool.py` + `ModelEditor` + `EditModel` + stránka `edit`.
Zbývá (v pořadí, jak dává smysl): **dutina** (`hollow`, podepsaná vzdálenost na mřížce + marching cubes, odtokové
otvory, ušetřený materiál) a **`/tools/life-size`** (měřítko + dutina + dělení s kolíky, varování nad 20 dílů);
`/tools/wearable` (míra, průzory ve vieweru, drážky na popruh); `/tools/puzzle`; `/tools/flexi-cut` a `/tools/flexi`;
`/tools/colors` (barvený 3MF, `ThreeMfConverter` čtení barev); rozšíření `relief` (9 tvarů, lampa, náhled
s podsvícením); `/tools/holder-from-model`; `/tools/potion`; `soap` podle stopy; `/tools/slider`.

## 8. Stav

7. 10. 2026 večer: oba nástroje v katalogu, build prošel (`check_bundle` OK), `pint --dirty` čistý, 12 nových testů
zelených, související existující testy zelené; celá sada se pouští na konci session (sekce se doplní).
