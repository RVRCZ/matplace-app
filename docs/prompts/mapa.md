# Zadání pro session B: 3D mapa města nebo krajiny (`/tools/map`)

Předloha: printpal „3D Map Maker — AI City & Terrain Map Generator“ (snímek `docs/img/printpal-map-maker.png`): zadáš
název města nebo místa (nebo nahraješ snímek mapy), vybereš styl (Sleek Map / Miniature Style) a typ (City /
Landscape), volitelně „landmarks“, výstup STL, „AI vygeneruje za 4–6 minut“, jen po přihlášení. Cíl stejný jako u všech
našich nástrojů: **udělat to lépe než předloha** – bez AI, z otevřených dat (přesné budovy, cesty, voda, reliéf),
do minuty, zdarma a bez přihlášení, s koncem na farmě (tisk u nás, i vícebarevně) nebo ve stažení.

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-papel-wt`** (tvoje, zůstává), nová větev **`feature/map-maker`** z aktuálního `main`:
  `git fetch origin && git checkout -b feature/map-maker origin/main` (papel je slité a nasazené, nic z něj neztratíš).
  `.env` (sqlite, `APP_URL=http://localhost:8016`, `PYTHON_BIN`), `node_modules` junction do `C:\matplace-app\node_modules`
  (nemaž, nepřepisuj), `vendor`, `public/build` máš. Místní server `php artisan serve --port=8016`.
- Tvůj dokument: **`docs/W.md`** (co jsi postavil, rozhodnutí, co není ověřené, nasazení – i co nainstalovat na server).
- Řídící session (`conceprt19092026-50`, farma) slévá do `main` a nasazuje; ty **nemerguješ a nenasazuješ**. Commity po
  celcích, push na `origin feature/map-maker`, zpráva řídící session s commitem a souhrnem po každém kole. Před pushem
  `git fetch origin main && git merge origin/main`.
- Pravidla pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php` (všechny tři jazyky; výjimka: klíče
  `param.*`/`tools.*` sdílené v JSON přes `lang/src/*.json` + `python scripts/lang_add.py`); testy
  `php -d memory_limit=2G vendor/bin/phpunit --filter …`; před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`,
  `npm run build`; **nikdy `taskkill /F /IM python.exe`**, nikdy `queue:retry all`; jedna session na worktree; `git add`
  jen jmenovitě; žádný `git stash`.
- Soubory, do kterých jen **přidáváš** (patří jiným): `config/tools.php`, `routes/web.php`, `lang/*/tools.php`,
  `lang/*/tools_seo/`, `resources/js/calc/tool_page.ts` (Stage – jen když je to nutné a napiš proč), `viewer.ts`.
  **Neměň**: farmu (`app/Domain/Farm`, `farm.ts`, `PrepareFarmOrder`), okno barev (`colors.ts`, `Palette.php`),
  `param.ts`, `edit.ts`, `tools/page.blade.php` (po úkolech E #4–#6 je to společná stránka: tlačítko, text pod cenou a
  krok „Materiál a počet kusů“ dostaneš zadarmo).
- Souběh (10. 10. večer): session E opravuje nástroje podle Romana (malé úkoly, hlavně `tools/page.blade.php`,
  `lang/*`); session D, F čekají. Kolize nehrozí, když zůstaneš ve svých nových souborech.
- Síť: Nominatim (geokódování), Overpass (OSM data) a dlaždice výšek (AWS Terrarium) jsou **veřejné služby s limity**
  (bod 2.4). Ze serveru jsou dostupné (ověřeno 10. 10.: Nominatim 200, Terrarium 200; Overpass `/api/status` vrací 406,
  `/api/interpreter` zkus). **Testy nikdy nesmí jít na síť** – `Http::fake()` a fixtury (bod 3.7).

## 1. Co předloha umí (ze snímku)

- Vstup: název města/místa, nebo nahraný snímek mapy (Google Maps, satelit…), „Specific landmarks (optional)“ (text).
- Styl: Sleek Map (čisté hrany) / Miniature Style (hračkovité). Typ: City (budovy) / Landscape (reliéf).
- Výstup STL; generování 4–6 minut; historie generování; „Log in to generate“.
- Co předloha neumí a my ano: přesná data (OSM budovy s výškami, silnice, voda, železnice; reliéf z DEM), měřítko a
  rozměr podle naší podložky, barvy po vrstvách (podklad / silnice / budovy) pro tisk u nás, náhled do minuty,
  bez účtu.

## 2. Co už máme (navaž, nepiš znovu)

### 2.1 Stránka nástroje
`tools/page.blade.php` + `Stage` (`resources/js/calc/tool_page.ts`): kroky jako kotvy, prohlížeč (`viewer.ts`),
`stage.fileResult(file)` (model na scénu, rozměry, orientační cena, stahování, tlačítko „Pokračovat ke kalkulaci“ –
od #5 s materiálem a počtem z posledního kroku), `stage.status()`, `stage.go()`. Vzor stránky s **asynchronní prací
ve frontě a fázemi**: nástroje úprav – `resources/views/tools/edit.blade.php`, `resources/js/calc/edit.ts`,
`App\Jobs\EditModel`, `engines/python/edit_tool.py` (`stage(dst, name)` zapisuje `<dst>.stage`, stránka se ptá
`/api/files/{uuid}` a čte `file.stage`; texty fází `edit.stage.*` v `lang/*/edit.php` – po úkolu E #7 jsou společné).
Druhý vzor pro dlouhou práci: `App\Jobs\GenerateModel` (re-queue každých 5 s, `tries 120`).

### 2.2 Modely z nástrojů
`ModelFile` s `origin = 'tool'`, `origin_ref = '<kind>'`, `tool_params` (parametry stránky → stránka se znovu otevře
přes `?from=uuid`), `tool_params['part_colors'][<díl>] = {code: hex, hex}` (volné barvy – okno barev od session D:
`pickColor()` vrací hex; na stránkách návrhu **žádné naše cívky**, cívky až kalkulace a /farm), `tool_params['color_changes']`
= `[{z: mm, hex: '#rrggbb'}]` (výměny filamentu nad podložkou, viz `ModelFile::colorChanges()`; vzor
`engines/python/art_tool.py` `notes.color_changes` a `creative_kinds.py` ř. 1087–1238). Kalkulace a farma pak samy
nabídnou nejbližší cívky a tisknou s M600 / přes ACE. **Mapa je přesně tenhle případ**: podklad (barva 1) → silnice
na vrchu podkladu (barva 2) → budovy (barva 3): dvě výměny v `color_changes`, žádné samostatné díly.
`App\Support\PreviewMeta` drží hlavičku náhledu pod 3 kB (dlouhé poznámky jdou přes `/api/tools/preview/{key}/meta`).

### 2.3 Python
`engines/python/`: `manifold3d` (CrossSection s dírami, offset = zaoblení rohů, extrude, booleany), `trimesh`, `numpy`,
`scipy`, `Pillow`, `scikit-image`, `requests` – na serveru v `/opt/matplace-py` (Python 3.12), místně tvůj 3.11.
`App\Engines\Repair\PythonTool::runScript` spouští skripty (JSON dovnitř, JSON ven, STL soubor). **Nepřidávej Python
balíčky**, pokud to jde bez nich (shapely/pyproj nepotřebuješ: Web Mercator a lokální metry zvládne numpy, polygony
s dírami CrossSection).

### 2.4 Síťové služby (nové; dodržuj jejich pravidla)
- **Nominatim** `https://nominatim.openstreetmap.org/search?q=…&format=jsonv2&limit=6&addressdetails=1&accept-language=<jazyk>`:
  max 1 požadavek/s, povinný `User-Agent: matplace.com tools (info@matplace.com)`, výsledky cachovat 30 dní
  (`Cache::remember`). Zobrazuj „Data © OpenStreetMap contributors“ (ODbL) na stránce nástroje.
- **Overpass** `https://overpass-api.de/api/interpreter` (záloha `https://overpass.kumi.systems/api/interpreter`):
  jeden dotaz na oblast (`[out:json][timeout:60]; (way["building"](bbox); relation["building"](bbox); way["highway"](bbox);
  way["waterway"](bbox); way["natural"="water"](bbox); relation["natural"="water"](bbox); way["railway"](bbox);
  way["landuse"~"forest|grass|park"](bbox);); out body; >; out skel qt;`), max ~2 km × 2 km ve městě; odpověď
  cachovat na disk (`storage/app/maps/osm/<hash bbox>.json`, 30 dní). Ne víc než 1 dotaz za 2 s z celé aplikace
  (`RateLimiter`).
- **Výšky**: AWS Terrarium `https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png` (bez klíče;
  výška = (R·256 + G + B/256) − 32768 m; z = 12 pro krajinu, 14 pro město), dlaždice cachovat na disk
  (`storage/app/maps/dem/`). Zdroj uvést („Výšky: Mapzen / AWS Open Data“).
- Všechno síťové dělá **PHP** (`Illuminate\Support\Facades\Http`, timeout 60 s, retry 1×, cache) v
  `App\Domain\Tools\MapData` – Python dostane jen lokální soubory (JSON z Overpassu, PNG dlaždice) a je **čistě
  offline**. Díky tomu jsou testy bez sítě (`Http::fake()`) a Python lze zkoušet z příkazové řádky nad fixturou.

## 3. Co postavit

### 3.1 Stránka `/tools/map` (kind `map`, modul `map`: `tools/map.blade.php`, `resources/js/calc/map.ts`, texty `lang/*/map.php`)

Kroky (kotvy jako u ostatních):
1. **Místo** – pole „Město, obec nebo místo“ s tlačítkem Hledat → `POST /api/tools/map/places` (Nominatim přes
   `MapData`) → seznam do 6 výsledků (název, typ, země) k výběru; nebo rovnou souřadnice (`50.0875, 14.4213`).
   **Rozsah**: pro město 500 m / 1 km / 2 km (strana čtverce), pro krajinu 2 / 5 / 10 / 20 km. **Typ**: Město (budovy,
   silnice, voda, železnice na plochém nebo mírně vyvýšeném podkladu) / Krajina (reliéf z výšek, volitelně voda a
   silnice, obce jako nízké bloky). Náhled oblasti: po výběru místa se do kroku vykreslí **2D náhled** (`POST
   /api/tools/map/preview` → PNG 600×600 vykreslené Pythonem z načtených dat: budovy tmavě, silnice, voda modře,
   rámeček a měřítko) – ať je vidět, co se vytiskne, dřív než se staví 3D. Snímek mapy jako vstup (předloha) **ne**.
2. **Nastavení** – Styl: **Hladká** (přesné hrany, ploché střechy, skutečné výšky) / **Miniatura** (zaoblené rohy
   budov přes `CrossSection.offset`, výšky ×1,5, tlustší silnice, široký rám). Velikost podkladu 80–250 mm (výchozí 150;
   měřítko se dopočítá a ukáže „1 : 6 700“). Výška podkladu 3–10 mm. Rám ano/ne + šířka. **Název** na přední hraně
   rámu (text vyvýšený 0,6 mm; výchozí název místa, lze přepsat, prázdné = bez textu; písmo jako u cedulky –
   `creative_kinds`/`name_kinds` mají text → použij stejnou cestu). Krajina: **převýšení** 1×–3× (výchozí 1,5×),
   voda ano/ne, silnice ano/ne, obce ano/ne. Město: výška neznámých budov (výchozí 6 m = 2 podlaží; `height` nebo
   `building:levels`×3 m z OSM má přednost, strop 150 m), silnice vyvýšené o 0,6 mm (= 2 vrstvy, výměna barvy) nebo
   zapuštěné, voda zapuštěná 0,8 mm, zeleň zapuštěná 0,4 mm s jemnou texturou (volitelné, výchozí vypnuto).
   Značky (předloha „landmarks“): volitelné pole „zvýraznit místa“ (až 4 názvy; každý přes Nominatim v rámci oblasti) →
   tenký kolík se zploštělou kuličkou (Ø 4 mm) na místě; **druhé kolo**, ne v prvním.
3. **Barvy** – díly `base` (podklad + rám + text), `roads`, `buildings` (město) / `base`, `water`, `terrain` (krajina):
   okno barev od D (volná barva; výchozí podklad `#e8e4d8`, silnice `#2b2b2b`, budovy `#c9a96b`; krajina podklad
   `#2d6a4f`, voda `#3b82c4`). Z barev vznikne `color_changes` (bod 3.3). Prohlížeč maluje díly jejich barvami
   (jako vrstvený obraz: `viewer.setPieces`/regions – viz jak to dělá `art.ts`).
4. **Materiál a počet kusů** – společný krok, nic nepíšeš.

Tlačítko **„Vytvořit mapu“** → `POST /api/tools/map` → `ModelFile` (origin `tool`, `origin_ref` `map`, `tool_params`)
+ fronta (job `App\Jobs\BuildMap`, `tries 1`, `timeout 600`): fáze `places` → `osm` → `terrain` → `building` → `done`
(texty `map.stage.*`, stránka se ptá `/api/files/{uuid}` jako `edit.ts`, ukazuje fázi a průběh); po `ready`
`stage.fileResult(file)`. Opakované otevření `?from=uuid` vrátí stránku se stejnými parametry (vzor ostatní nástroje).
Limity: nepřihlášený 3 mapy/den, přihlášený 20/den (`FarmSettings` `daily_slices_per_user` je jiný limit – udělej
vlastní `RateLimiter` klíč `map:<ip|user>`), oblast ve městě max 2 km, krajina max 20 km. Čas: město 1 km do 30 s,
krajina 10 km do 20 s na serveru (změř a zapiš do W.md).

### 3.2 Data (`App\Domain\Tools\MapData`)
- `places(string $q, string $lang): array` – Nominatim, cache 30 dní, 1 req/s (RateLimiter + `usleep`), vrací
  `[{name, kind, country, lat, lon, bbox}]`.
- `osm(float $lat, float $lon, int $sideM): string` – cesta k cachovanému JSON z Overpassu pro čtverec; při selhání
  primárního serveru záloha; výjimka `MapDataUnavailable` → chyba `map.error.osm_down` (stránka řekne „zkuste za
  chvíli“ a nabídne menší oblast).
- `dem(float $lat, float $lon, int $sideM, int $zoom): array` – seznam cest k PNG dlaždicím pokrývajícím čtverec.
- Nic z toho nevolá Python; Python dostane `{osm: path, dem: [paths], center, side_m, zoom, params}`.

### 3.3 Geometrie (`engines/python/map_tool.py`, offline)
- Projekce: Web Mercator → lokální metry kolem středu (y nahoru), ořez čtvercem `side_m`, měřítko = `base_mm / side_m`.
- **Město**: budovy = `CrossSection` z `way`/`relation` (multipolygon s dírami: outer/inner), unie přes
  `CrossSection.batch_boolean`, `extrude(h_mm)`; výška z `height`/`building:levels`, jinak výchozí; Miniatura: `offset`
  (zaoblení 1 mm v měřítku tisku, min. 0,4 mm), výšky ×1,5. Silnice = `highway` (šířka podle typu: motorway 12 m,
  primary 9, secondary 8, residential 6, service 4, path/footway 2; ostatní 3) jako `CrossSection` offsetem linie →
  vrstva 0,6 mm na podkladu (nebo zapuštěná). Železnice 3 m, zapuštěná 0,4 mm s příčnými pražci (Miniatura).
  Voda (`natural=water`, `waterway` s šířkou podle typu: river 20 m, stream 4) zapuštěná. Podklad = čtverec
  `base_mm` × `base_mm` výšky `base_h`, rám (`frame_mm`, výška +1,2 mm), text názvu na přední hraně rámu (vyvýšený
  0,6 mm, výška písma = `frame_mm`·0,6, zkrácený, ať se vejde). Vše sjednoceno booleany do jednoho tělesa; nic nesmí
  viset ve vzduchu (silnice i budovy stojí na podkladu). Minimální tisknutelná tloušťka: budovy s půdorysem užším
  než 0,8 mm se vynechají nebo rozšíří (Miniatura), silnice min. 0,8 mm.
- **Krajina**: výšková mapa z dlaždic (bilineárně na mřížku 200×200 až 400×400 podle `base_mm`), převýšení, reliéf =
  povrch mřížky + stěny + dno (uzavřené těleso, `trimesh` → kontrola `is_watertight`), nejnižší bod na `base_h`;
  voda jako ploché hladiny na úrovni z DEM (`natural=water` polygony promítnuté na reliéf, zapuštěné 0,8 mm);
  silnice jako tenký pás 0,6 mm na povrchu (promítnutý: pro každý vrchol pásu výška z mapy); obce jako bloky 3 mm.
- Výstup: STL + `meta` (`bbox`, `parts` s objemy, `notes`: `scale` („1 : 6 700“), `osm_date`, `buildings` (počet),
  `roads_m`, `color_changes` = `[{z: base_h, hex: roads}, {z: base_h + 0.6, hex: buildings}]` pro město (Hladká
  i Miniatura), `[{z: …}]` pro vodu v krajině, `attribution` text). Náhled 2D (`--preview out.png`) ze stejných dat.
- Z příkazové řádky: `python engines/python/map_tool.py build params.json out.stl` a `… preview params.json out.png`;
  `params.json` ukazuje na soubory z fixtury – ať to jde zkoušet bez PHP.

### 3.4 Texty a SEO
`lang/{cs,en,es}/map.php` (stránka, kroky, fáze, chyby, atribuce), `lang/*/tools.php` (název, popis, hledací slova:
„3D mapa“, „mapa města k tisku“, „reliéf krajiny“, „diorama města“), `lang/*/tools_seo/map.php` (odstavce, FAQ:
odkud jsou data, jak přesné, jak velké, barvy, licence ODbL – „model si můžete vytisknout i prodávat, uveďte
© OpenStreetMap contributors“). Karta a ukázky `php artisan matplace:tool-examples map --force` (a `--card`): ukázky
musí jít **offline** – `config/tools.php` `seo.examples` s `params` ukazujícími na vestavěnou fixturu (bod 3.7), ne na
síť. `config/tools.php`: `'map' => ['route' => 'tools.map', 'intent' => 'create', 'categories' => ['home', 'craft'],
'available' => true, …]` – řídící session nástroj po nasazení **skryje přepínačem** v `/admin/tools`, Roman ho zapne
po vyzkoušení.

### 3.5 Stažení a farma
Stahování ze `stage.fileDownloads` (STL, 3MF s M600 podle `color_changes` – to už umí `ModelFileController::project`).
Na farmě se nic nemění: `color_changes` + `part_colors` → kalkulace ukáže nejbližší cívky, /farm je zaškrtne. Ověř
ručně jednu mapu do kalkulace a na /farm (místně `FarmSeeder` má cívky).

### 3.6 Co neumí předloha a my ano (napiš na stránku a do SEO)
Přesná data místo AI, do minuty, měřítko a rozměr podle podložky, tři barvy po vrstvách, název na rámu, bez účtu,
zdarma; a poctivě: „snímek mapy nahrát nejde, zadejte místo“.

### 3.7 Testy (bez sítě)
- Fixtura `tests/fixtures/maps/mesto.json` (ručně napsaný malý Overpass JSON: 5 budov vč. jedné s dírou a jedné
  s `building:levels=4`, 3 silnice různých typů, jezírko, kousek železnice; ~200 řádků) + `tests/fixtures/maps/dem/`
  (2×2 Terrarium PNG vyrobené v testu z numpy: kopec uprostřed) + `tests/fixtures/maps/nominatim.json`.
- `tests/Feature/MapToolTest.php`: stránka ve třech jazycích (kroky, atribuce); `places` přes `Http::fake()` vrátí
  seznam a druhé volání jde z cache (jen 1 HTTP); `POST /api/tools/map` s fixturou (přes `Http::fake()` na Overpass
  a dlaždice) → job proběhne (sync fronta), soubor `ready`, `tool_params` nese parametry i `color_changes` se dvěma
  výměnami, `designColors()` = tři barvy, `meta.notes.scale`; krajina s DEM fixturou: `is_watertight`, výška reliéfu
  v rozsahu převýšení; limit map/den; chyba Overpassu → `map.error.osm_down`; `?from=uuid` vrátí parametry;
  `matplace:tool-examples map` projde offline; SEO test (`SeoTest`) ho vidí; `ToolPageTest` (má cyklus přes
  `config('tools')`) projde.
- Python: `tests/python/test_map_tool.py` (pokud repo má pytest; jinak PHP test spustí `map_tool.py build` nad
  fixturou a zkontroluje STL: 5 budov = objem v rozsahu, bbox = base_mm, výška max = base_h + nejvyšší budova).

### 3.8 Pořadí práce
1. `MapData` + fixtury + `map_tool.py build` pro město (Hladká) – STL, které si vytiskneš v hlavě: podklad, 5 budov,
   silnice. Zpráva řídící session s prvním STL a časem.
2. Stránka: krok Místo (hledání, výběr, 2D náhled), Nastavení, Barvy, fronta s fázemi, výsledek na scénu, stažení.
3. Krajina (DEM), Miniatura, rám s názvem, voda/železnice/zeleň.
4. Texty cs/en/es, SEO, karta a ukázky offline, testy, `docs/W.md`, zpráva s commitem a **seznamem, co nainstalovat
   na server** (ideálně nic; jinak přesně).
5. Druhé kolo (až po Romanově zkoušce): značky míst, 2D náhled s popisky ulic, export jen reliéfu pro frézování –
   **ne teď**.

## 4. Co není v zadání
- Nahrání snímku mapy jako vstup (předloha) – ne; Nominatim + Overpass stačí a je to přesné.
- Interaktivní mapa (Leaflet) k výběru oblasti – ne v prvním kole; výběr místa + rozsah + 2D náhled.
- Satelitní textury, 3D stromy, auta – ne.
- Měnit farmu, kalkulaci, okno barev, společnou stránku nástroje – ne (pošli, co by bylo potřeba).
