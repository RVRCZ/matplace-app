# W: 3D mapa města nebo krajiny (session B)

Větev `feature/map-maker` (z `main` 68829b5, 10. 10. 2026), zadání `docs/prompts/mapa.md`, předloha printpal „3D Map
Maker“ (AI, 4–6 minut, po přihlášení). Nástroj `/tools/map` (kind `map`): z názvu místa mapa k tisku. Město = budovy
s výškami, silnice, voda, železnice a zeleň z OpenStreetMap na plochém podkladu; krajina = reliéf z výškových dlaždic
s vodou, silnicemi a obcemi na povrchu. Bez AI, z otevřených dat, do minuty, zdarma, bez účtu, s koncem na farmě
(tři barvy nad sebou) nebo ve stažení.

Nic se netisklo a na serveru jsem nebyl. Co je ověřené a čím, je v sekci 6.

## 1. Co vzniklo

### Data (`App\Domain\Tools\MapData`, jen PHP, jen síť)

- `places($q, $lang)`: Nominatim `search?format=jsonv2&limit=6&addressdetails=1&accept-language=`, `User-Agent:
  matplace.com tools (info@matplace.com)`, cache 30 dní (`Cache::remember`, klíč z jazyka a dotazu), 1 požadavek/s
  přes `RateLimiter::attempt('map:nominatim')` s čekáním (`slot()`). Souřadnice „50.0875, 14.4213“ (i s desetinnou
  čárkou) jsou místo samy o sobě, nic se neptá.
- `osm($lat, $lon, $sideM, $kind)`: jeden dotaz Overpass na čtverec, `out body; >; out skel qt;`. Město: budovy
  včetně relací, silnice, železnice, vodní toky a plochy, lesy/louky/parky/zastavěné plochy. Krajina jen to, co leží na
  reliéfu: řeky a kanály, vodní plochy, zastavěné plochy (`landuse=residential`) a silnice – do 6 km všechny mimo
  pěšin/cest/obslužných, nad 6 km jen hlavní (motorway…tertiary); 20 km Prahy by jinak stahovalo statisíce budov,
  které krajina zahodí (Krkonoše 20 km: 3,5 MB). Odpověď na disk `storage/app/maps/osm/<sha1 lat|lon|strana[|landscape]>.json`
  na 30 dní; 1 dotaz za 2 s z celé aplikace; servery po řadě `overpass-api.de`, `overpass.kumi.systems`,
  `overpass.openstreetmap.fr` – při selhání (HTTP chyba, nebo 200 s `remark` a prázdnými `elements` = přetížení)
  další; jinak `MapDataUnavailable('osm_down', 'server: odpověď; …')`, detail jde do logu a do `timings.failed_why`.
- `dem($lat, $lon, $sideM, $zoom)`: dlaždice Terrarium `s3.amazonaws.com/elevation-tiles-prod/terrarium/z/x/y.png` na
  disk `storage/app/maps/dem/z/x/y.png` (rok), nejvýš 36 dlaždic (`area_too_big`), zoom 12 pro krajinu.
- Python nikdy na síť nejde: dostane `{osm: cesta, dem: [{z, x, y, path}], center, side_m, zoom, font, params}`.

### Stroj (`engines/python/map_tool.py`, offline)

`build params.json out.stl` a `preview params.json out.png`; fáze do `<out.stl>.stage` (reading, buildings, roads,
terrain_mesh, writing, done).

- Projekce: Web Mercator relativně ke středu, zpět na metry v zeměpisné šířce středu (× cos φ₀), rovnou do mm
  podložky (`k = okno_mm / strana_m`). Okno = podložka bez rámu.
- **Město** (`city_layers`, `build_city`): budovy z `way` i multipolygonových relací (vnější kusy se skládají po
  koncových bodech, vnitřní jsou díry, even-odd); výška `height` („12“, „12 m“, stopy) nebo `building:levels`×3 m,
  jinak `default_h`, mezi 2 a 150 m; v tisku **nejméně 1 mm** (6 m domek na mapě 2 km je 0,45 mm, jedna vrstva).
  Budovy se seskupí podle výšky (0,1 mm), sjednotí ve 2D (`batch_boolean`), od nejvyšších k nejnižším se odečtou
  vyšší půdorysy (pásy se v půdorysu nepřekrývají, jen dotýkají), pak extruze a jedno sjednocení. Silnice = pás
  (čtyřúhelník na segment + kruh v každém bodě) o šířce podle druhu (dálnice 12 m … pěšina 2 m), nejméně 0,8 mm,
  sjednocené, bez půdorysů budov; vyvýšené 0,6 mm na podkladu nebo zapuštěné. Železnice 3 m zapuštěná 0,4 mm, voda
  (plochy i toky s šířkou řeka 20 m, kanál 10, potok 4) zapuštěná 0,8 mm, zeleň zapuštěná 0,4 mm (volitelná) –
  **nikdy pod silnicí, kolejí ani budovou** (most je plný, nic nevisí). Podklad = okno × `base_h`, rám = prstenec do
  `base_h + 1,2`, název na přední hraně rámu (`shape2d.text`, výška písma 0,6 šířky rámu, zmenšený, ať se vejde,
  vyvýšený 0,6 mm). Vše jedno těleso.
- **Miniatura**: rohy budov zaoblené (otevření + zavření s kulatým spojem, poloměr 0,6 mm·k, min. 0,4 mm), výšky
  ×1,5, silnice ×1,4, rám a podklad s kulatými rohy. **Hladká**: otevření s ostrými rohy odstraní půdorysy užší než
  0,8 mm.
- **Krajina** (`heightmap`, `relief_solid`, `build_landscape`): výšky bilineárně z mozaiky dlaždic (`map_coordinates`)
  na mřížku 160–320 buněk (2 buňky na mm okna), rozsah 0,5.–99,5. percentil, `Z = base_h + (h − min)·k·převýšení`;
  voda, silnice a obce se **vryjí do mřížky** (voda −0,8 mm, silnice +0,6, zastavěné plochy +3 mm, přes vodu most)
  místo booleovských operací na síti o 150 000 trojúhelnících (ty trvaly 11 s; teď 0,2 s). Těleso = povrch mřížky +
  čtyři stěny + dno, orientace ručně (povrch proti směru hodin shora, stěny po obvodu, dno vějířem), `trimesh`
  potvrzuje `is_watertight`. Rám a název stejně jako u města (jedno sjednocení s malým tělesem).
- **Barvy podle výšky**: město `color_changes = [{z: base_h, silnice}, {z: base_h + 0,6, budovy}]` (zapuštěné silnice:
  jen budovy), krajina `[{z: base_h, terén}]`; `regions` pro viewer stejné výšky (podklad do `base_h`, dál). Spodních
  0,6 mm budov je proto v barvě silnic – stejná daň jako u cedulky s vyvýšeným písmem.
- **Náhled** (`preview`): 600 × 600 shora, u města zeleň, voda, koleje, silnice, budovy, rám, měřítko (zaokrouhlená
  pětina strany); u krajiny stínovaný reliéf (světlo od severozápadu, sklony 2×) s vodou, silnicemi a obcemi.
  Hlavička `X-Map-Meta` {buildings, roads_m, relief_m, scale}.

### PHP

- `MapBuilder`: `FIELDS` (size 80–250/150, base_h 3–10/4, frame_mm 3–15/6, default_h 3–30/6 m, exaggeration 1–3/1,5),
  `CHOICES` (type, style, side, roads), `SIDES` (město 500/1000/2000, krajina 2000–20000; `clean` vrátí
  nenabízenou stranu na prostřední), `FLAGS` (frame, water, roads_on, rail, green, towns), `TEXTS` (name 40, place
  120), `PARTS` (město base/roads/buildings, krajina base/terrain), barvy dílů jako `{code: hex, hex}` (volná barva
  od session D; kód cívky nebo vestavěné jméno se převede na hex), `create()` → `ModelFile` (origin tool, origin_ref
  map, `tool_params`) + `BuildMap`, `sources()`/`sourcesOf()`, `build()`/`preview()` nad soubory, `report()` (fáze ze
  `.stage`), `demFixture()` (dlaždice kopce kreslené GD pro ukázky a testy), `fixture()`.
- `BuildMap` (fronta `interactive`, tries 1, timeout 600): fáze `osm` → `terrain` (PHP) → fáze Pythonu → `done`;
  do `tool_params` `notes` (měřítko, datum dat, počty), `regions`, `color_changes`, `multi_material = false`;
  `ProcessModelFile::dispatchSync`; selhání = `status failed`, `error` = kód (`osm_down`, `dem_down`, …).
- `MapApiController`: `POST /api/tools/map/places`, `/preview` (PNG), `/` (create; limit 3/den bez účtu, 20 s účtem,
  admin bez limitu, 429 s `retry_in` a textem „další půjde za :h h“). `MapToolController` stránka.
- Sdílená místa, kam jsem jen **přidal**: `routes/web.php` (4 cesty), `ToolGate` (jedna větev `api.tools.map.*` →
  `canOpen('map')`), `ModelFile::builtForPrinting` (`MapBuilder::KIND`, aby farma a 3MF četly `color_changes`),
  `UploadController::describe` (`'map' => MapBuilder::report`), `config/tools.php` (`map`, tři ukázky z fixtury),
  `ToolExamples` (ukázky a karta mapy z fixtury, barvy podle `regions`), `tool_page.ts` (modul `map`), `api.ts`
  (`FileInfo.map`), `lang/*/tools.php` (název, popis, slova). Farma, kalkulace, okno barev, `page.blade.php`,
  `param.ts`, `edit.ts` beze změny.

### Stránka (`tools/map.blade.php`, `resources/js/calc/map.ts`, `lang/*/map.php`)

Kroky **Místo · Nastavení · Barvy · Materiál a počet kusů** (poslední je společný). Místo: pole + Hledat (Enter),
seznam míst (název, druh, země, celá adresa), jedno místo rovnou; Město/Krajina; rozsah (nabízí se jen strany daného
typu); **2D náhled oblasti** po výběru místa a po každé změně (zpoždění 400 ms), pod ním počet budov, metry silnic a
měřítko. Nastavení: styl, velikost (s živým měřítkem „1 : 6 700“), výška podkladu, rám + šířka + název (název se
předvyplní z místa, dokud ho návštěvník nepřepíše), u města výška budov bez údaje a vyvýšené/zapuštěné silnice, u
krajiny převýšení, zaškrtávátka podle typu. Barvy: řádek na díl s oknem barev. Tlačítko **Vytvořit mapu** → fronta,
fáze se ukazují (`map.stage.*`), výsledek na scénu (`regions` → barvy podle výšky), rozměry, cena, stažení (projekt
3MF s výměnami, STL), „Pokračovat ke kalkulaci“; fakta pod cenou (budovy, silnice, datum dat / převýšení); varování
`no_buildings`, `no_roads`, `flat`. `?from=uuid` otevře návrh se stejným místem a nastavením a ukáže model.
Atribuce „Data © přispěvatelé OpenStreetMap (ODbL) · Výšky: Mapzen / AWS Open Data“ je pod cenou.

### Texty a SEO

`lang/{cs,en,es}/map.php` (vše na stránce, fáze, chyby, varování, atribuce), `lang/*/tools.php` (`map.title/hint/
action`, hledací slova), `lang/*/tools_seo/map.php` (titulek, popis, dva odstavce, čtyři kroky, šest otázek: data a
přesnost, velikost, tři barvy, snímek mapy ne, licence ODbL s prodejem, limit map). Karta a tři ukázky
(`matplace:tool-examples map --force` a `--card`) jdou **offline** z `tests/fixtures/maps/mesto.json` a z dlaždic
kreslených `demFixture()`.

## 2. Rozhodnutí a proč

1. **Všechno síťové v PHP, Python offline.** Testy jedou s `Http::fake()` nad fixturou, stroj jde zkoušet z příkazové
   řádky, a limity služeb hlídá jedno místo (`RateLimiter`).
2. **Barvy podle výšky, ne díly.** Mapa je jedno těleso; farma i 3MF už umí výměny ve výšce (`color_changes`), viewer
   maluje `regions`. Žádné díly po barvách (`OrderService::ASSEMBLED` mapu neobsahuje).
3. **Krajina má dvě barvy** (podklad, terén), ne tři: voda je zapuštěná do povrchu v různých výškách, výměnou ve
   výšce ji obarvit nejde; v náhledu modrá je. Zadání psalo `[{z: …}]` pro vodu – nešlo by to.
4. **Budova nejméně 1 mm, Miniatura zaobluje, Hladká odstraňuje úzké půdorysy, nic se nevyřezává pod silnicemi a
   budovami** – schváleno řídící session 10. 10.
5. **Krajina vrytá do mřížky.** Booleovské operace vody a silnic na síti reliéfu trvaly 11 s (a `trimesh.fix_normals`
   15 s); ruční orientace stěn a úprava `Z` přímo v mřížce dávají 0,2 s a stejný výsledek.
6. **Overpass 200 s `remark`** (přetížení) je selhání → záloha (řídící session).
7. **Limit 3/20 map za den** vlastním klíčem `map:<ip|user>` (`RateLimiter::hit` na 86 400 s), náhled oblasti se
   nepočítá; 429 nese `retry_in`.
8. **Snímek mapy jako vstup ne** (zadání): stránka a SEO to říkají.
9. Nominatim i Overpass dostávají `accept-language`/dotaz v jazyce stránky; názvy míst v seznamu jsou tedy česky na
   české stránce.

## 3. Rychlost (lokálně, Windows, 10. 10. 2026)

| vstup | stažení (Overpass + dlaždice) | stavba (bez startu interpretu) | trojúhelníků |
|---|---|---|---|
| fixtura město 500 m, 150 mm, rám s názvem | – | 0,3 s | 5 300 |
| fixtura krajina 5 km, 150 mm, mřížka 276, rám | – | 0,2 s | 154 000 |
| Praha, Staroměstské náměstí, město 500 m, 200 mm (373 budov) | 2,5 s | 0,5 s Sleek · 0,8 s Miniatura | – |
| totéž, město 1 km | – | 1,3 s Sleek · 1,9 s Miniatura | – |
| totéž, město 2 km (2 788 budov, 227 km silnic, odpověď 9,7 MB) | 6,6 s | 4,9 s Sleek · 5,8 s Miniatura · 250 mm 5,7 s | 291 000 · 384 000 |
| náhled města 2 km | – | 2,7 s | – |
| Krkonoše, krajina 20 km, 200 mm, 2× (odpověď 3,5 MB, 16 dlaždic, reliéf 1 087 m) | 14,7 s | 1,5 s · náhled 1,5 s | 208 000 |
| Sněžka, krajina 5 km, 200 mm, 2× (4 dlaždice, reliéf 747 m, výška 63 mm) | 14,8 s | 1,1 s · náhled 0,9 s | 207 000 |

Start interpretu a knihoven ~0,6 s navíc. Vše hluboko pod cílem 30 s na 1 km; o čas rozhoduje Overpass, ne stroj.
Overpass kolísá: 2 km Prahy jednou (11 s) skončilo 503 `osm_down` a napodruhé prošlo za 6,6 s. Hlavní server dává
2 sloty na adresu (429, když jsou vyčerpané; `/api/status` říká kdy se uvolní), `overpass.kumi.systems` vracel
10. 10. večer 500 na cokoli, proto je třetí server `overpass.openstreetmap.fr` (odpověď 0,4 s). Chybová zpráva v logu
(`BuildMap failed`) jmenuje odpověď každého serveru.

## 4. Testy (`tests/Feature/MapToolTest.php`, bez sítě: `Http::preventStrayRequests()`)

Stránka ve třech jazycích (kroky, atribuce, háčky skriptu, `?from`); místa z Nominatimu jednou a pak z cache
(1 HTTP), souřadnice bez sítě, geokodér mimo provoz = 503 s textem; město z fixtury přes frontu (sync): `ready`,
rozměry, `notes` (5 budov, metry silnic, měřítko, datum), `color_changes` dvě výměny, `colorChanges()`,
`designColors()` tři barvy, `regions`, `tool.url`, projekt 3MF s `M600` nad podkladem, druhá mapa téhož čtverce bez
dalšího požadavku; Miniatura 1,5× vyšší, zapuštěné silnice jedna výměna; krajina z dlaždic kreslených v testu:
`relief_m` ≈ 400, výška ≈ `base_h + 400·2·k`, bez `not_watertight`, dvě barvy; náhled PNG 600 × 600 s barvou budovy
na místě budovy; Overpass mimo provoz = `failed` s `osm_down` (záloha zkoušena), texty fází a chyb ve třech jazycích,
limit tři mapy, nenabízená strana vrácena, špatné souřadnice 422; ukázky offline (`Http::assertNothingSent()`),
`ToolSeo::tools()` zná mapu.

## 5. Nasazení (řídící session)

- Žádná migrace, žádný nový balíček PHP, npm ani Pythonu (scipy, scikit-image, trimesh, manifold3d, Pillow,
  fontTools jsou na serveru). `npm run build`.
- Složky `storage/app/maps/osm`, `storage/app/maps/dem`, `storage/app/tmp/map` si PHP založí samo; musí být
  zapisovatelné pro www-data (jako `storage/app` jinde).
- Server musí ven na `nominatim.openstreetmap.org`, `overpass-api.de`, `overpass.kumi.systems`,
  `overpass.openstreetmap.fr`, `s3.amazonaws.com` (ověřeno 10. 10. řídící session: Nominatim a Terrarium 200;
  Overpass `/api/interpreter` POST ze serveru nezkoušen – z vývojového PC ano, sekce 3).
- Po nasazení nástroj **skrýt** v `/admin/tools` (zadání); Roman ho zapne po zkoušce. Zkouška: `/tools/map`, hledat
  „Staroměstské náměstí“, náhled, Vytvořit mapu (město 500 m), výsledek, kalkulace, `/farm?file=` s dvěma výměnami.
- Fronta: `BuildMap` běží na frontě `interactive` jako úpravy modelů; worker ji musí poslouchat (už poslouchá).

## 6. Co není ověřené

- **Skutečná data jen z jednoho kraje.** Lokálně prošla Praha (500 m, 1 km, 2 km; relace budov, `building:part`,
  `height`) a Krkonoše (20 km, 5 km), čísla v sekci 3, náhledy vypadaly správně (řeky, zástavba, silnice, stín reliéfu).
  Jiná města mohou mít kraje, které Praha nemá (budovy bez `height` jsou na `default_h`; relace s více vnějšími
  kusy se kreslí po kusech).
- **Nic se netisklo.** Silnice 0,6 mm na podkladu, budovy od 1 mm, zoubky zapuštěné 0,4 mm: z náhledu. V krajině 20 km
  je zástavba (`landuse=residential`) hodně drobných skvrn zvednutých 3 mm – po prvním tisku posoudit, zda je nespojit
  (offset) nebo nechat jen v barvě.
- **Stránka proklikána jen v headless Chromu** proti živým službám (10. 10.): hledat „Staroměstské náměstí“, náhled,
  Vytvořit mapu (město 500 m, 373 budov), stav `ready`, odkaz na Pokračovat. V běžném prohlížeči ještě ne.
- **Limit `fastcgi` hlaviček**: `X-Map-Meta` je krátká (< 200 B); `X-Model-Meta` tu není (model jde přes soubor).
- **Druhé kolo** (zadání 3.8.5): značky míst, popisky ulic v náhledu, export jen reliéfu – ne teď.
