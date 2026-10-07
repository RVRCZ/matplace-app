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
| Vydutit model | `/tools/hollow` | `edit` (`edit_tool.py hollow`) | plný model → dutý se stěnou 1,5–6 mm a odtokovými otvory; ušetřené gramy a koruny |
| V životní velikosti | `/tools/life-size` | `edit` (`edit_tool.py life_size`) | výška v cm → zvětšení, dutina nad 200 cm³ (stěna 2–5 mm), dělení s kolíky; gramy a cena |
| Puzzle z modelu | `/tools/puzzle` | `edit` (`edit_tool.py puzzle`) | plochý model → mřížka 2–8 × 2–8 dílků s puzzle zámky (vůle 0,2) nebo skrytými kolíky Ø 3, čísla zespodu, rámeček |
| Držák z vlastního modelu | `/tools/holder-from-model` | `edit` (`edit_tool.py holder`, kind `holder`, karta `holder_model`) | model → výška, dutina na plechovku 330/slim/500, kelímek 473 ml, mýdlo, svíčku nebo vlastní válec/kužel; stěna měřená v pěti výškách |
| Lektvarová láhev z modelu | `/tools/potion` | `edit` (`edit_tool.py potion`) | model → seříznuté dno, dutina, hrdlo na kuželovém nástavci, kónická zátka (`cork`), štítek s nápisem (`label`) |
| Flexi z modelu | `/tools/flexi-cut` | `edit` (`edit_tool.py flexi_cut`) | podlouhlý model → 3–20 článků napříč osou s kulovými klouby Ø 6–10 (vůle 0,35–0,5), tiskne se najednou složené |

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
  Každý díl jde do souboru přes `mesh_tool.from_manifold` (pinché vrcholy exaktních těles o mikrony od sebe), jinak
  trimesh i slicer hlásí „neuzavřeno“ – ukázalo se u láhve.
- **Job `EditModel`** ve frontě `interactive` s `timings` (`queue_s`, `edit_s`, pak `convert_s`, `analyse_s` z
  `ProcessModelFile`); stránka se ptá na stav po 1,5 s a říká fázi. Lokálně (`QUEUE_CONNECTION=sync`) odpoví hned.

Stránka `/tools/split`: Soubor (nahrání nebo `?from=<uuid>` – u výsledku dělení se otevře jeho zdroj a výsledek)
→ Nastavení (podložka, roviny, spoje, čísla, položení) → Výsledek (věty, mapa, stažení: projekt, celý plát, díly po
jednom přes `GET /api/tools/edit/<uuid>/<díl>.stl`). Kalkulačka ukazuje odkazy na díly (`download.ts` pozná kindy
session 3 a bere `api/tools/edit`).

### Dutina (`hollow`) a životní velikost (`life_size`)

- **Dutina**: `trimesh.voxelized(pitch).fill()` → vyplněná mřížka modelu (pitch 0,6 mm, u modelu přes 192 mm
  `max/320` a varování `coarse_grid`), `distance_transform_edt` × pitch = vzdálenost každé buňky od povrchu,
  gaussovsky vyhlazené pole minus (stěna + 1,1 buňky) → `marching_cubes` na nule = vnitřní plocha → trimesh →
  `simplified` → manifold → `model − dutina`. Posun 1,1 buňky je kalibrovaný na krychli a kouli (stěna vychází
  +0,0 až +0,15 mm, nikdy tenčí). Tělesa dutiny pod 50 mm³ zanikají. **Odtokové otvory** Ø 3–8 mm (výchozí 5):
  dno dutiny = řez dutinou 1 mm nad jejím nejnižším bodem, otvory na nejprostornějších místech (1–3 podle plochy
  dna, nebo zadaný počet 1–4, nebo žádný), válec od z = −1 do 3 mm nad dnem dutiny. Hlášení: objem dutiny,
  ušetřené gramy (PLA 1,24 g/cm³) a rozdíl ceny z hrubého odhadu na stránce (`rough.ts`, spodní mez rozpětí).
  Pro kartu nástroje má `hollow` pohled `cut` (čtvrtina pryč, ať je stěnu vidět). Stránka po vydutění zapne rentgen.
- **Životní velikost**: výška v cm (5–100) → měřítko; nad 200 cm³ dutina se stěnou `2 + 3·(h − 200)/800` mm
  (2 mm při 200 mm, 5 mm při metru) – ale **kolem budoucích rovin řezu zůstává plný prstenec** (6 mm na každou
  stranu roviny, 13 mm hluboko pod povrch; ve voxelovém poli se tam dutina vynuluje), aby kolíky Ø 6 měly v čem
  sedět; bez něj u busty 400 mm nebylo na kolíky místo (stěna 2,8 mm). Pak `split_solid` s kolíky (nebo klíči /
  bez spojů podle volby) a stejná mapa jako u dělení; když se zvětšený model na podložku vejde, je to jeden díl.
  Analýza před stavbou (`analyse` s `height`) říká rozměr, dutý/plný a počet dílů. Nad 1000 mm po zvětšení
  `too_big`.

### Puzzle (`puzzle`)

Půdorys modelu (`Manifold.project`) na mřížku `rows` × `cols` (buňky ≥ 12 mm, jinak `pieces_too_small`). **Zámky**:
každá vnitřní hrana mřížky dostane zámek s náhodným směrem (seed pevný, stejný model = stejné puzzle); zámek
(`knob2d`) = krček 0,55 šířky hlavy a kulatá hlava o průměru `knob` % strany dílku (výchozí 35), celkový dosah
0,375 strany, takže zámky z protilehlých stran nechají uprostřed dílku 23 % materiálu. Dílek = buňka + vlastní
zámky − zámky sousedů zvětšené o vůli 0,2 (jen dutina má vůli, rovné části řezu se dotýkají). Dílek = model ∩
vytažená buňka; dílek rozpadlý na víc těles (otvor v modelu) si nechá největší a řekne to. **Skryté kolíky**: rovné
řezy, díry Ø 3,2 ve stěnách dílků v polovině výšky (1–2 na hranu podle délky), díl `pins` Ø 3 × 6; model nad 25 mm
dostane kolíky i když chtěl zámky (`tall_gets_pins`), nad 80 mm `too_tall`, pod 5 mm `too_thin_for_pins` (dílky se
slepí). **Čísla** zespodu (řez ve výšce 0,3 mm, nejprostornější místo, 3–7 mm). **Rámeček** (flag `frame`, díl
`frame`): tácek z půdorysu + vůle, lem 6 mm, dno 1,5 mm, výška min(H, 10) + 1,5. Mapa dílků jako u dělení (jedna
úroveň), stránka v puzzle vynechá seznam dílků (mřížka s čísly stačí). Zadání chtělo pro vysoké modely „roviny
s puzzle profilem ve třech osách“ – to tu není, vysoké modely dostanou kolíky (rozhodnutí 10).

### Držák z vlastního modelu (`holder`)

Model (volitelně zvětšený na `height`) stojí dnem na podložce; shora se z něj odečte dutina: válec, kužel
(`cav_d` dole, `cav_d2` nahoře, kelímek zmrzliny 80→95) nebo zaoblený kvádr (mýdlo 90 × 60, r 12), vždy + vůle
0,3–1,5 mm, hloubka z předvolby nebo vlastní, střed posunutelný o `cav_x`/`cav_y`. Předvolby `CAVITIES`: plechovka
330 Ø 66,3 / 90 hluboko, slim Ø 58 / 110, 500 ml Ø 66,3 / 130, kelímek Ø 80→95 / 100, mýdlo 90 × 60 / 30, svíčka Ø 80 /
25. **Stěna**: v pěti výškách dutiny řez modelu (`slice`) a binární hledání největšího offsetu průřezu dutiny, který
je ještě uvnitř řezu (0–8 mm, 10 kroků) → `walls`, `min_wall`; pod 2 mm varování `wall_thin` s odhadem `grow_to`
(hrubý: výška × (1 + potřebný přírůstek / průměr dutiny)). Dno pod dutinou < 2 mm → `too_short` s potřebnou
výškou. Mýdlenka má zespodu otvor Ø 20 (vytlačení, odtok). Jeden díl `body`; stránka po výrobě zapne rentgen.
Katalogový klíč je `holder_model` (klíč `holder` má generátor držáku ze session 0), kind souboru `holder`.

### Lektvarová láhev (`potion`)

Model zvětšený na `height` (0 = ponechat), dno seříznuté o `cut` % výšky (`trim_by_plane`), hrdlo na nejvyšším
místě: střed řezu modelu 3 mm pod vrcholem, válec s vnitřním Ø `neck_d` (12–60) a stěnou `wall`, výška `neck_h`
(10–80), pod ním **kuželový nástavec** (Ø hrdla + 3 mm → Ø hrdla, 12 mm nebo 12 % výšky do modelu), aby hrdlo
drželo i na špičaté hlavě. Pak `hollow_solid` na sjednocení (bez odtoků) a vrt Ø `neck_d` od hrdla přes stěnu do
dutiny. **Zátka** `cork`: kužel Ø (hrdlo − 0,5) nahoře → (hrdlo − 1,8) dole, výška 0,7 hrdla, hlavička Ø hrdlo + 6
× 6 mm. **Štítek** `label` (flag, text do 20 znaků, DejaVu Sans Bold 7 mm): zaoblená destička 1,2 mm + písmo
0,8 mm vyvýšené, šířka nejvýš 70 % láhve (text se zmenší). Varování: `small_foot` (dno pod 100 mm²), `solid_bottle`
(dutina se nevešla – láhev plná, hrdlo průchozí), `label_failed`. Zadání chtělo sdílet hrdlo a zátku s kindem
`bottle` session 2 – session 2 ještě neběží, hrdlo a zátka jsou tu zatím vlastní (prostý válec a kužel, bez závitu);
až `bottle` vznikne, převezme je nebo naopak.

### Flexi z modelu (`flexi_cut`)

Osa = nejdelší rozměr (nebo volba), `segments` 3–20 (0 = délka / 2,5 Ø koule), řezy rovnoměrně; články = model ∩
pás mezi řezy zúžený o mezeru 0,45 mm z každé strany řezu (dvě vrstvy). **Kloub** v každém řezu: střed koule
0,7 r za rovinou uvnitř dalšího článku (na nejprostornějším místě řezu, potřeba r + vůle + 1,2 mm stěny); z dalšího
článku se odečte koule r + vůle (dutina), do předchozího se přidá koule r a krček Ø r od jeho řezné plochy do středu
koule. Otvor dutiny v řezné ploše má poloměr √((r+c)² − (0,7r)²) ≈ 0,76 r < r, takže koule nevypadne, a krček
0,5 r projde s rezervou. Řez bez místa → kloub vynechán (`joint_no_room`), články se jen dotýkají. Výstup: jeden STL
se články na místě (print‑in‑place), díly `segment_<n>` jen pro barvení v náhledu (stahují se jako celek). Článek
kratší než Ø koule + 2 → `segments_too_short` s potřebnou délkou.

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
8. **Dutina měří vzdálenost na mřížce, ne přesným offsetem**: přesný offset trojúhelníkové sítě (manifold
   `minkowski`) by u busty s 200 tis. trojúhelníků trval minuty; mřížka 320³ to dá za sekundy a stěna je přesná na
   desetinu milimetru, což tisk stejně nerozliší.
10. **Puzzle vysokých modelů = kolíky, ne puzzle profil ve třech osách.** Zámek s hlavou je 2D tvar tažený skrz
    celou výšku; v 40 mm plastu by šel zasunout jen shora a drží stejně jako kolík. Skutečný 3D puzzle profil
    (hlava i v ose Z) by vyžadoval kulové „knoflíky“ a vůle v tisku s převisy – ponecháno jako otevřené.
11. **Zámky mají vůli jen v dutině** (0,2 mm kolem zámku souseda), rovné části řezu se dotýkají přesně: tak to dělá
    každá tištěná skládačka a díly se k sobě dají přitlačit; tisk sám přidá setinu až desetinu.
9. **Límec u řezů jen jako prstenec** (ne celý průřez): celý průřez 12 mm silný přidal bustě 400 mm 0,8 kg;
   prstenec 13 mm hluboký 0,15 kg a kolíky sedí stejně.

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

Dutina a životní velikost (tentýž den):

| krok | čas |
|---|---|
| `hollow` koule Ø 60 (5 tis. trojúhelníků), stěna 2 | 2,3 s |
| `hollow` busta 85 mm (4 tis. trojúhelníků), stěna 2,5 | 6,8 s |
| `life_size` busta → 400 mm: dutina (mřížka 1,25 mm), 2 roviny, 4 díly, 12 kolíků | 27 s |
| `life_size` koule → 150 mm, vejde se vcelku, jen dutina | 9,7 s |
| `puzzle` deska 120 × 90 × 6 → 3 × 4 dílků se zámky (+ rámeček) | 1,2–1,5 s |
| `puzzle` disk Ø 100 → 3 × 3 | 1,3 s |
| `holder` trubka Ø 80 × 100 → plechovka 330 | 1,1 s |
| `holder` busta 85 → 120 mm, svíčka Ø 80 (stěna 0, varování) | 1,1 s |
| `potion` koule Ø 70 → 90 mm, hrdlo 24, štítek | 6,8 s |
| `potion` busta 85 → 110 mm (dutina na mřížce 0,6 mm) | 16,5 s |
| `flexi_cut` kapsle Ø 18 × 118 → 6 článků, 5 kloubů | 1,2 s |
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

- **Nic se netisklo.** Flexi je nejcitlivější na vůli (0,4 mm v kloubu, mezera 0,45 mm mezi články): před zařazením
  mezi „ověřené“ vytisknout kapsli nebo hranol 120 × 20 × 14 s pěti články. K vyzkoušení na farmě dál: (a) vrstvený obraz 150 mm bez rámu, 4 desky 2 mm se sloupky 3 mm –
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
čísla, položení, mapa), `/tools/hollow`, `/tools/life-size`, `/tools/puzzle` (zámky, kolíky, čísla, rámeček; bez
3D profilu pro vysoké modely), `/tools/holder-from-model`, `/tools/potion`, `/tools/flexi-cut`, společný základ `edit_tool.py` + `ModelEditor` +
`EditModel` + stránka `edit`.
Zbývá (v pořadí, jak dává smysl): `/tools/wearable` (míra, průzory ve vieweru, drážky na popruh); `/tools/flexi` (zvíře z primitiv);
`/tools/colors` (barvený 3MF, `ThreeMfConverter` čtení barev); rozšíření `relief` (9 tvarů, lampa, náhled
s podsvícením); `soap` podle stopy; `/tools/slider`.

## 8. Stav

7. 10. 2026 večer: filament art a dělení v katalogu (commit 61d99ef). Později téhož večera: dutina, životní velikost,
puzzle, držák z modelu a lektvarová láhev (všechny na stránce `edit`), `ModelEditTest` má 10 testů; build, pint a testy
stránek, katalogu, SEO a karet zelené. Celá sada se pouští na konci session (sekce se doplní).
