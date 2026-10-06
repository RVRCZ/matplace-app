# Zadání: Session 0 – základ nástrojů (stránka, viewer, barvy, knihovna, vzhled)

Datum: 6. 10. 2026. Pracuj v repozitáři `C:\matplace-app` (Laravel 12) ve **vlastním worktree**
(`git worktree add C:\matplace-base-wt feature/tools-base`, větev z `main`; `main` je nasazený na matplace.com,
ověř `git log origin/main -1`). Nikdy `git add -A`, nikdy `git stash` (stash je společný s jinými session).
Testy `php -d memory_limit=2G vendor/bin/phpunit` (celá sada ~16 min, pouštěj na pozadí a čti log; v testech
`ENGINE_SLICER=fake`); `vendor/bin/pint --dirty`; po změně TS `npm run build` (spouští i `scripts/check_bundle.mjs`).
Nové texty jen do skupinových souborů `lang/{cs,en,es}/<skupina>.php`, ne do sdílených `lang/*.json`; vždy tři
jazyky. Na server se nic nenasazuje, nasazení dělá Roman podle `docs/DEPLOY-BETA.md`. Dokument: `docs/N.md`.

Tohle je **první ze série** k zadání `docs/prompts/nastroje.md` (70 nástrojů ze stlbuddy.com ve čtyřech
session). Přečti si ho celé, hlavně sekci „Lepší než předloha“ a podívej se na všech dvanáct screenshotů
`docs/img/stlbuddy-*.png`. Roman: „Laťka je vysoká, v US to umí dobře, i to prostředí máme dětské.“ Session 0
**nestaví žádný nový nástroj** – staví to, co pak všech 70 nástrojů sdílí, a nasadí to na 22 nástrojů, které
už máme. Po ní vypadá web jinak, ještě než vznikne první nový kind.

## Cíl

Jedna stránka nástroje, jeden viewer, jedna paleta barev, jedna knihovna obrázků, jeden vzhled. Měřítko:
otevřít vedle sebe `docs/img/stlbuddy-earrings.png` a naši cedulku – naše musí působit dospěleji, rychleji
a jasněji říkat, co se stane (cena, tisk, stažení). Nic z geometrie se nemění: stejné kindy, stejné STL,
stejné testy.

## Co dnes je (mapa kódu – přečti si ho, tohle je jen orientace)

- **Stránka parametrického nástroje**: `resources/views/tools/param.blade.php` (245 ř.; formulář vlevo, viewer
  vpravo v gridu, tři kroky `.steps`, řádek emoji symbolů `data-symbol`) + `resources/js/calc/param.ts` (473 ř.;
  náhled `POST /api/tools/param/preview` vrací STL, `create` založí `ModelFile`, artwork upload, presety,
  `?from=`). 15 kindů (`ParametricGenerator::FIELDS`). Vlastní stránky mají reliéf (`relief.blade.php`,
  `relief.ts` 68 ř.), forma (`mold.ts` 211), oprava (`repair.ts` 101), kontrola (`check.ts` 83), figurka
  (`figure.ts` 103); `sign.ts`/`sign.blade.php` jsou starší cesta cedulky (trasa `tools.sign` už vede na
  `param`), `spare` a `gifts` jsou prosté stránky.
- **Viewer**: `resources/js/calc/viewer.ts` (301 ř., třída `Viewer`, `FacePaint`, `Region`,
  `twoColorRegions`, `cutAtHeight`, `FILAMENT` = 9 natvrdo zapsaných barev, `deep()`), **sdílený** s
  kalkulačkou (`calculator.ts` 761 ř.), `check.ts`, `farm.ts`, `mini.ts`, `mold.ts`, `repair.ts`,
  `site/thumbs.ts`. Cokoli v něm změníš, musí dál fungovat na hlavní stránce a ve farmě.
- **Vzhled**: `resources/css/app.css` (77 ř.; Tailwind 4 `@theme` tokeny `page/card/ink/muted/action/ok/line`,
  systémový font stack, komponenty `.card .btn-* .chip .seg .field .steps`), `tests/Unit/DesignTokensTest.php`
  (kontroluje **přesné hodnoty** tokenů a AA kontrast – při změně tokenů upravit test spolu), layout
  `resources/views/layouts/app.blade.php` (hlavička s logem a štítkem „beta“, patička).
- **Katalog**: `config/tools.php`, `tools/index.blade.php` (tři vstupy, filtr chipů), `tools/card.blade.php`
  (obrázek 3:2 z `public/img/tools/<key>-800.jpg|webp`, jinak kresba `tools.art`), `tools/picture.blade.php`;
  ukázky nástrojů `php artisan matplace:tool-examples` → `public/img/tool-examples/*.png` (dnes 45).
- **Barvy farmy**: tabulka `farm_colors` (`code`, `name`, `name_en`, `hex`, `photo_path`, `enabled`,
  `in_stock`, `sort`, vazba na `farm_materials`), model `App\Models\FarmColor`, správa v `/admin/farm`.
  Nástroje dnes barvy farmy **neznají** – používají pevný seznam 9 názvů (`$colors` v `param.blade.php`,
  `FILAMENT` ve vieweru, `color.*` v jazycích).
- **Klient dostává** `ConfigController::payload()` (materiály, podložka `bed_mm`, `bed_margin_mm`, měna…).
- **Stažení a projekt**: `calc/download.ts`, `app/Engines/Project/*` (Orca/Prusa 3MF s barvami,
  `ColorChange.php`), díly zvlášť `GET /api/tools/param/{modelFile}/{part}.stl`.
- Python: každý náhled = nový proces (~0,7 s import manifold3d); rychlost řeší `feature/perf` (`docs/M.md`),
  tady ji jen **změř** a nepřepisuj.

## Co udělat

### 1. Vzhled (jednou, pro celý web)

- **Tokeny**: `page`, `card`, `ink`, `muted`, `line` zůstávají; akcent zůstává oranžová `#C94714` (značka), ale
  používá se **jen na jednu hlavní akci na obrazovce**; vedlejší akce šedé/obrysové. Rádius 12 px místo
  `rounded-2xl` u karet a panelů, stíny jen jemné (`shadow-sm`), žádné barevné pozadí sekcí. Nové tokeny
  zapiš do `DesignTokensTest` (test pak hlídá AA kontrast nových dvojic).
- **Písmo**: Inter (OFL), **self-hosted** woff2 v `public/fonts/` (ne Google Fonts CDN – GDPR), `font-display:
  swap`, variabilní řez; nadpisy 600, text 400, číselné hodnoty `tabular-nums`. Měřítko: 14/16/18/24/30.
- **Ikony**: jedna sada čárových ikon (Lucide, ISC) jako inline SVG sprite (`resources/views/partials/icons.blade.php`),
  žádné emoji v rozhraní (řádek symbolů ♥ ★ 🐾 u textových nástrojů zůstává jako **funkce** – vkládá znak do
  textu – ale vykreslený v písmu nástroje, ne jako systémové emoji).
- **Hlavička**: logo, navigace (Modely · Nástroje · Cena ze souboru · Účet), štítek „beta“ pryč. Patička beze změny.
- **Karty nástrojů**: každý z 22 nástrojů dostane **render skutečného výstupu** 3:2 (`public/img/tools/<key>-800.jpg`
  + webp 480/800): rozšiř `matplace:tool-examples` o režim `--card` (studiové osvětlení, neutrální pozadí
  `#F3EEE6`, mírný nadhled, díly v barvách filamentů). Kde Roman dodá fotku výtisku, má přednost (stejná cesta).
- Před/po: screenshoty `docs/img/base-before-*.png` a `base-after-*.png` (katalog, cedulka, forma, kalkulačka;
  headless Chrome `--screenshot --window-size=1440,900`), do `docs/N.md`.

### 2. Jedna stránka nástroje (`tools/page.blade.php` + `calc/tool_page.ts`)

Předloha rozvržení: `docs/img/stlbuddy-coaster.png`, `-earrings.png`, `-potion.png` – ale po našem:

- **Vlevo panel 340 px** (na mobilu pod viewerem, viewer 55 vh nahoře): název nástroje a jedna věta; sekce
  **Vstup → Rozměry → Barvy → Tisk** jako kotvy s čísly (všechny viditelné, aktuální se zvýrazní podle
  scrollu – ne průvodce, který schovává); presety jako chipy nahoře; pole s jednotkou vpravo v poli, posuvník
  + číslo u hlavních (`MAIN`) rozměrů; podmíněná pole (`WHEN`) se sbalují plynule.
- **Vpravo viewer** na celou výšku: lišta pohledů (3D · Shora · Zepředu · Ze strany · Zespodu), „Přizpůsobit“,
  rentgen, posuvník „rozložit“ (jen když je dílů víc), přepínač podložky. **Stavový řádek** dole: „97,8 × 143 ×
  4,4 mm · 2 díly · 2 barvy · vejde se na 250 mm“ (nebo červeně „nevejde se: 262 mm“).
- **Pod viewerem karta ceny**: hrubý odhad hned (jako kalkulačka, `rough.ts`), přesný po slicu; tlačítka
  **Vytisknout u nás** (hlavní) a **Stáhnout** (menu: projekt Orca 3MF · projekt Prusa 3MF · STL všech dílů
  ZIP · jednotlivé díly) – vše přes `download.ts` a stávající API, nic nového v PHP kromě ZIP všech dílů.
- **Barvy**: sekce ukazuje díly (jména z `param.part.*`) a u každého vzorník z palety farmy (bod 4).
- **Varování** (`notes.warnings`) jako žluté řádky v sekci, které se jich týkají, ne jedna hromada dole.
- **Autosave a zpět/vpřed**: parametry do `localStorage` pod klíčem nástroje, chip „obnovit poslední
  nastavení“; zásobník historie parametrů (Ctrl+Z / tlačítka).
- **Přenést**: 15 kindů `param` + reliéf + forma + oprava + kontrola + figurka na novou stránku (jejich skripty
  se stanou moduly `tool_page.ts`: vstupní část je u každého jiná – soubor / fotka / text – zbytek sdílený).
  `sign.blade.php`/`sign.ts` smazat, pokud na ně nic nevede (ověř trasy a testy). `spare` a `gifts` zůstávají.
- Testy stránek (`ParametricToolsTest`, `CreativeToolsTest`, `ToolsFlowTest`, `ToolFixesTest`,
  `ReliefToolTest`, `MoldToolTest`, `RepairToolTest`, `FigureToolTest`, `SignToolTest`) musí zůstat zelené;
  kde testují HTML stránky, uprav selektory, ne chování.

### 3. Viewer, který se dá chytit (`viewer.ts`, rozšíření – nic stávajícího neměnit)

- **Výběr dílu** klikem (raycast po dílech; dnes jsou díly barvené regiony – přidej mapu trojúhelník → díl
  z náhledu: Python `param_tool.py` ať vedle STL vrátí `parts: [{name, tris: [od, do], bbox}]`, malá změna
  v `main()`), zvýraznění, panel skočí na pole dílu.
- **Úchyty rozměrů**: u `MAIN` rozměrů šipky na stěnách bboxu (vlastní gizmo: tři osy, tažení v rovině
  obrazovky → změna hodnoty → debounce 150 ms → náhled); hodnota se při tažení ukazuje u úchytu v mm.
  Formulář a úchyt jsou dvě cesty k témuž parametru.
- **Podložka** 250 × 250 s okrajem `bed_margin_mm` jako mřížka pod modelem; co přečnívá, zčervená.
- **Rozložený pohled** (díly posunuté od těžiště celku, posuvník 0–100 %), **rentgen** (průhlednost 35 %),
  pevné **pohledy** s animací 300 ms.
- Kalkulačka, farma, `mini.ts`, `thumbs.ts` používají `Viewer` dál beze změny (jen přidané metody); ověř ručně
  hlavní stránku a `/farm` objednávku.

### 4. Paleta barev farmy (jediný zdroj pravdy, ~250 cívek)

Roman doplňuje katalog cívek profesionálními fotkami, hexem a anglickým názvem; je jich asi **250 druhů a barev**
a potrvá to týden. Paleta proto není řada devíti vzorníků, ale výběr z katalogu, a rutinu doplňování bere na sebe
kód, ne Roman.

**Data a náhled**

- `ConfigController::payload()` dostane `colors: [{code, name, hex, material, finish, photo, in_stock}]` z
  `farm_colors` (`enabled`, řazení `sort`), když farma běží. Ukazuje se, **co v `farm_colors` je** (i 30 barev
  stačí) – devět vestavěných je záloha jen pro vývoj bez farmy a pro testy, ne pro web s rozpracovaným katalogem.
- `FILAMENT` ve vieweru se naplní z payloadu (hex → lineární), 9 vestavěných zůstává jako záloha.
- Parametry nástrojů nesou **kód barvy z `farm_colors`** místo názvu z pevného seznamu a k dílu se ukládá **i hex**,
  aby náhled seděl, i když cívka zmizí ze skladu (pak hláška „tahle barva teď není skladem, vyberte jinou“).
  Stávající názvy (`white`, `black`…) se mapují na kódy při načtení starých nastavení (`?from=`), takže nic nepadá.
  Projekt 3MF dostane barvy do slotů podle kódu (`ColorChange.php`, exportéry) – ověř na dvoubarevném QR kódu
  a cedulce.

**Výběr barvy (unese 250 položek)**

- Na stránce nástroje je u dílu jen **jeden vzorník + jméno** (klik otevře okno) a vedle „naposledy použité“
  (localStorage, max 8).
- **Okno „Barva“**: nahoře hledání (česky i anglicky, podle kódu), filtr materiálu (PLA / PLA+ / PETG / matná /
  hedvábná…) a „jen skladem“; vzorníky seskupené podle **odstínu** (bílá a šedá, černá, červená, oranžová a žlutá,
  zelená, modrá a fialová, hnědá a béžová, speciální – duhová, dřevo, svítící) a seřazené podle světlosti; u
  každého název v jazyce, materiál a fotka výtisku, když `photo_path` je.

**Doplňování katalogu (místo ruční práce)**

- `php artisan farm:colors-fill [--dry-run] [--only=hex,name_en]`: **hex z fotky** (střed fotky, medián barvy
  v oblasti výtisku po odstranění pozadí záplavou z rohů, převod na sRGB hex; u duhových a dřevěných dominantní
  barva a do `test_notes` „hex z fotky, zkontrolovat“), **`name_en` přes `App\Engines\Translate`**
  (`ClaudeTranslator`, dávka všech chybějících v jednom volání, styl faithful, kontext „názvy barev filamentů
  pro 3D tisk“); výpis, co se doplnilo a co ne.
- `php artisan farm:import-colors <csv>` (sloupce `code, material, name, name_en?, hex?, in_stock?, sort?`;
  oddělovač `;`, UTF‑8, hlavička povinná): založí nebo aktualizuje podle `code`, `--dry-run` ukáže rozdíly. Roman
  vyplní tabulku v Google Sheets a uloží jako CSV. Fotky dál přes stávající `farm:import-photos` (složky
  pojmenované kódem).
- V `/admin/farm` tlačítko „Doplnit hex a anglické názvy“ a odkaz na import, ať to jde i bez terminálu; `hex`
  a `name_en` ve formuláři **přestanou být povinné** (doplní se), chybějící se v seznamu barev zvýrazní.
- Roman pak jen fotí (foto-box) a píše české názvy a materiál do tabulky; hexy a angličtina se dopočítají.

### 5. Knihovna obrázků

- `engines/artwork/<kategorie>/<slug>.svg`, 100+ siluet **jen CC0 / public domain** (openclipart, svgrepo
  s filtrem CC0, publicdomainvectors – každý soubor má řádek v `engines/artwork/SOURCES.md` s URL a licencí;
  bez řádku nesmí do repa). Kategorie: zvířata, srdce a hvězdy, sport, povolání, svátky, doprava, příroda,
  písmena a čísla. Test, že každý SVG má záznam a projde `shape2d.svg` (jednoduché cesty, žádné rastry).
- `GET /api/artwork/library?q=&cat=` (názvy ve třech jazycích v `lang/<loc>/artwork.php`), náhledy jako
  samotné SVG; **modální okno „Obrázek“** se záložkami *Nahrát · Knihovna · Moje obrázky* (moje = dřívější
  nahrání této session / účtu, 30 dní; endpoint `GET /api/artwork/mine`), použité u loga, razítka, šablony,
  světelného nápisu a vykrajovátka. Záložku „Vytvořit“ (AI) doplní session 4.

### 6. Katalog `/tools`

- Kategorie podle `docs/prompts/nastroje.md` (`images`, `names`, `home`, `parts`, `toys`, `signs`, `craft`,
  `edit`, `sell`), stávajících 22 přeřadit; **hledání** podle názvu a klíčových slov (`lang/<loc>/tools.php`
  `<tool>.keywords`, na klientu); karty s rendery; štítek **„ověřeno tiskem <datum>“** z `config/tools.php`
  (`'verified' => '2026-10-01'`) – u 22 stávajících dopíše Roman data podle toho, co už tiskl.
- `ToolsCatalogTest`/`SeoTest`: každý nástroj v katalogu má render karty, texty ve třech jazycích a funkční
  trasu – ať to po tobě hlídají testy.

### 7. Rychlost (jen změřit)

`docs/N.md`: čas od změny pole po nový náhled u pěti nástrojů (cedulka, krabička, váza, logo s SVG, modulární
organizér) před a po, na lokálu i na serveru (`curl` proti API, jen čtení). Když je něco nad 2 s a je to na
klientu (parsování STL, překreslení), oprav; serverovou část nech `feature/perf`.

## Testy

Celá sada zelená; nové: `ToolPageTest` (každá trasa nástroje vykreslí novou stránku: sekce, stavový řádek, menu
stažení, karta ceny; `?from=` obnoví nastavení), `ColorsPayloadTest` (paleta z `farm_colors`, záloha bez farmy,
mapování starých názvů na kódy, barva mimo sklad), `FarmColorsFillTest` (hex z fotky, `name_en` s `FakeTranslator`,
import CSV s `--dry-run`), `ArtworkLibraryTest` (hledání, licence u každého SVG, `mine` jen vlastní),
`ToolCardsTest` (render pro každý nástroj existuje), upravený `DesignTokensTest`. Pythonová změna `param_tool.py`
(`parts` v odpovědi) má test, že součet rozsahů trojúhelníků sedí s STL.

## Rozhodnutí, která potřebuju od Romana (když neřekne jinak, platí výchozí)

1. **Akcent**: oranžová `#C94714` zůstává jako jediná hlavní akce. Výchozí: ano.
2. **Písmo**: Inter self-hosted. Výchozí: ano.
3. **Obrázky karet**: rendery hned, fotky výtisků z farmy je nahradí, jak budou (Roman fotí ve foto-boxu).
4. **Knihovna**: jen CC0 / public domain. Výchozí: ano.
5. **Barvy cívek** (~250): Roman fotí a píše české názvy a materiál do tabulky (CSV), hex a anglický název
   dopočítá `farm:colors-fill`; do té doby paleta ukazuje, co v `farm_colors` je.

## Hotovo, když

Všech 22 nástrojů běží na jedné nové stránce s viewerem, který se dá chytit; paleta je z `farm_colors` s oknem pro výběr z 250 barev, `farm:colors-fill` a `farm:import-colors` běží; knihovna
má 100+ SVG s licencemi; karty mají rendery; `/tools` má kategorie, hledání a štítek „ověřeno tiskem“; štítek
„beta“ a emoji z rozhraní zmizely; `php -d memory_limit=2G vendor/bin/phpunit` celé zelené; `pint --dirty`
čistý; `npm run build` prošel; `docs/N.md` má před/po screenshoty, měření rychlosti, rozhodnutí a sekci
„Nasazení (Roman)“ (`npm ci && npm run build`, `php artisan optimize`, restart `php8.2-fpm`; bez migrace; nové
soubory v `public/fonts`, `public/img/tools`, `engines/artwork`); `main` slitý a větev pushnutá. Teprve pak
startují session 1–4 ze zadání `nastroje.md`.
