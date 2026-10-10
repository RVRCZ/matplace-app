# U: barva na stránkách návrhu je volná, naše cívky až na dalších stránkách

Větev `feature/color-picker` (z `main` 3b3316c, 10. 10. 2026), zadání `docs/prompts/vlastni-barva.md`. Rozhodnutí
Romana z 10. 10. 2026: **na stránkách nástrojů (`/tools/*`) už neukazujeme naše cívky.** Zákazník si vybere jakoukoli
barvu; které cívky to u nás vytiskneme, se dozví až na kalkulaci a vybere si je na stránce farmy. Žádná migrace, žádný
nový balíček, farma (`app/Domain/Farm` kromě `Palette.php`, `farm.ts`, `PrepareFarmOrder`) beze změny.

## 1. Co se změnilo

**Hodnota barvy je barva sama.** Dřív byla barva kód cívky (`02_PLA+_bily`), teď je to hex malými písmeny
(`#2a7fd5`). Ukládá se stejně jako dřív jako `{code, hex}`, jen u volné barvy je `code` totéž co `hex`:
`part_colors.text = {code: "#2a7fd5", hex: "#2a7fd5"}`, `plate_color = "#fafaf5"` + `plate_color_hex`, `bins[].color`
+ `hex`, `layers[].code`, `strokes[].c` + `h`, `color_changes[].code` + `hex`. Všechno, co čte dál (kalkulace, farma,
3MF, karta návrhu), čte **hex** jako dosud, takže se tam nic neměnilo. Dva starší druhy hodnot zůstávají platné,
protože je nesou uložené návrhy: **kód cívky** z katalogu a **devět vestavěných názvů** (`white`, `black`…).

**Okno „Barva“** (`resources/js/calc/colors.ts`, přepsané; API modulu zůstalo):

- **16 základních barev** jako dlaždice s názvem (bílá, černá, šedá, červená, oranžová, žlutá, zelená, tyrkysová,
  modrá, fialová, růžová, hnědá, béžová, zlatá, stříbrná, měděná). Klik barvu rovnou použije.
- **Vlastní barva**: systémový výběr barvy (`<input type="color">`, na mobilu kolečko prohlížeče), pole **hex** s
  kontrolou při psaní (šest znaků, s `#` i bez; špatná hodnota = červený rámeček, text „Šest znaků 0–9 a a–f“,
  tlačítko vypnuté), náhledové kolečko, název nejbližší základní barvy a **Použít** (i Enter). Pole se při vstupu
  celé označí, takže psaní nebo vložení přepíše, co v něm je.
- **Naposledy použité** (do 8, `localStorage` `mp_colors_recent`, jen hexy; staré kódy cívek v něm se přestanou
  ukazovat samy).
- O cívkách, materiálech, skladu a fotkách tu není nic. Zmizelo hledání, filtr materiálu, „jen skladem“, skupiny podle
  odstínu i hláška „Tahle barva teď není skladem“.

| funkce | dřív | teď |
|---|---|---|
| `pickColor(current)` | vrací kód cívky | vrací hex (`'#2a7fd5'`), nebo `null` |
| `colorOf(value)` | cívka z katalogu | `{code, name, hex}`: `name` je název nejbližší základní barvy („modrá“), `material: ''`, `photo: null`, `in_stock: true`. Platí i pro kód cívky a vestavěný název ze starého návrhu: ukáže se barva, ne cívka |
| `materialLabel(c)` | „PLA+ matný“ | hex. Řádek dílu tak bez úprav volajících říká „modrá · #2a7fd5“ |
| `spoolCode(value)` | vestavěný název → nejbližší cívka | vestavěný název → jeho barva (`white` → `#ede6d6`); hex malými písmeny; kód cívky zůstane (starý návrh se odešle zpět, jak byl) |
| `paintSwatch` | fotka cívky nebo hex | jen barva |
| `recentColors` / `rememberColor` | kódy cívek | hexy |

`param.ts`, `art.ts` a `edit.ts` jsem **nezměnil ani o řádek**: dostanou hex tam, kde dostávaly kód, a jejich řádky se
přes `colorOf` a `materialLabel` vykreslí správně samy. `tool_page.ts` beze změny. Přibyly tři řádky stylů v
`resources/css/app.css` (`.tool-dialog-color`, `.tool-color-input`, vybraný `.tool-swatch`).

**Server – `App\Domain\Farm\Palette`** (jediný soubor farmy, na který jsem sáhl):

- `Palette::BASIC` – 16 základních barev (klíč → hex), jedno místo pravdy. Do prohlížeče jdou s názvy v jazyce
  návštěvníka: `GET /api/config` → `colors.basic = [{key, hex, name}]`. Přibylo i `colors.named` (jak vypadá devět
  vestavěných názvů). `colors.items` (katalog cívek) zůstává: čte ho farma a stránka nástroje podle něj ukáže barvu
  návrhu uloženého s cívkou.
- `isCustom($v)` (`/^#[0-9a-f]{6}$/i`), `canonical($v)` (hex malými písmeny, ostatní beze změny), `has()` je pro hex
  `true`, `hex()` vrátí hex sám, `inStock()` je pro hex `true`.
- **`Palette::rule()` – jedno pravidlo pro každé barevné pole každého nástroje**: hex, kód cívky z katalogu, nebo
  vestavěný název. Projde i tvar `{code, hex}`, kterým se vrací uložený návrh, a to i když jeho cívka mezitím z
  katalogu zmizela (návrh si barvu drží v `hex`). Používá ho `ParametricGenerator::rules()` (barevné volby,
  `part_colors.*`, `strokes.*.c`, `bins.*.color`, `layers.*.code`) a `ArtGenerator::rules()` (`part_colors.*`).
  `'#12'`, `'red;drop'`, `'pink'` → 422.
- `free($picked)` – z čeho si nástroj bere barvu, když ji má zvolit sám (podklad pod motivem, těsto perníčku, světlá
  deska pod tmavým písmem): 16 základních barev + to, co vybral návštěvník, vše jako `[hodnota, hex]`. Žádná cívka.
- `nearestSpool($hex)` – nejbližší cívka katalogu, jak ji ukáže stránka (`code, name, hex, material, finish, photo,
  in_stock`); `null`, když katalog farmy není.

`ParametricGenerator::clean()` ukládá hodnoty přes `canonical()`. Skladač vrstev (`compose`) už **nepřevádí** vestavěné
názvy na cívky (dřív `legacy()`): vrstva `white` zůstane `white`. Výchozí hodnoty barevných polí na stránce (QR:
podklad a kód) jsou barvy (`#ede6d6`), ne cívky.

**Obrázek v barvách bez mapování na cívky** (krok 2.2). `shape2d.colors` dostal volbu `free`: barvy obrázku zůstanou
barvami obrázku (hex z kvantizace = `code` = `hex` = `rgb`), žádná se nehledá v paletě. Paleta se tomu nástroji dál
posílá (`ParametricGenerator::freeColors()`: `palette` z `Palette::free()` + `free_colors: true`), ale slouží jen
k tomu, aby věděl, jak vypadají barvy vybrané návštěvníkem, a z čeho vzít barvu, kterou volí sám. Stejně vrstvený
obraz (`ArtGenerator::build`, `art_tool.py`). Změny v Pythonu jsou čtyři řádky ve třech souborech:

- `shape2d.py` `colors()`: `free`, a ve vrstvách nové `near` (nejbližší barva palety; bez `free` je to `code`).
- `shape_kinds.py`: `options["free"]`; „co je stejně těsto, vynech“ se u perníčku rozhoduje podle `near` místo podle
  shody kódu cívky (jinak by hnědá z obrázku nikdy nebyla „těsto“).
- `art_tool.py`: `options["free"]`.

Malovaný 3MF (`edit.ts`, `ModelEditor`, `colors_tool.py`) barvy na cívky nemapoval nikdy: ukazuje hexy ze souboru a
okno barev nevolá. Beze změny.

**Kalkulace: „U nás vytiskneme“** (krok 2.4). `UploadController::describe()` vrací nové pole
`nearest = [{hex, spool: {code, name, hex, material, finish, photo, in_stock}}]`: pro každou barvu návrhu
(`ModelFile::designColors()`: `part_colors`, `color_changes`, u QR podklad a kód; nejvýš osm, každá jednou) nejbližší
cívka podle `Palette::nearest()`. Je tedy v odpovědi `POST/GET /api/calculations` (`calculation.file.nearest`) i
`GET /api/files/{uuid}`. Na webu bez katalogu farmy je pole prázdné a řádek se neukáže. `calculator.ts`
(`showNearest`) ho vypíše pod tlačítko „Vytisknout u nás“ (`farm/cta.blade.php`, `#cta-farm-nearest`):
**„U nás vytiskneme: ● modrá PLA+ ● zelená PLA+“** – fotka výtisku z cívky (nebo kolečko v její barvě), název,
materiál, u cívky mimo sklad „(není skladem)“. Stejná cívka pro dvě barvy návrhu je v řádku jednou. **Nic se tím
nerozhoduje**: výběr dělá `/farm` jako dosud.

**Texty** (`lang/{cs,en,es}`): `toolpage.php` – `color.window` je nově „Barva“; nové `color.basic`, `color.custom`,
`color.custom_hex`, `color.custom_bad`, `color.use` a 16× `color.basic.<klíč>`. `farm.php` – `calc_nearest`
(„U nás vytiskneme: :list“), `calc_nearest_out` („(není skladem)“).

**Dvě drobné opravy, na které jsem cestou narazil** (bez nich by vlastní barva nedošla až do konce):

- `viewer.ts`: `twoColorRegions()` maluje QR cedulku podle uloženého `plate_color_hex` / `code_color_hex` a
  `paintByRegion()` umí barvu zadanou hexem. Bez toho se cedulka ve volných barvách na kalkulaci namalovala celá
  výchozí krémovou a kód na ní nebyl vidět (viděl jsem to v prohlížeči před opravou). Stejnou cestou musela dopadat
  i cedulka uložená s **kódem cívky**: kalkulačka paletu nenese, takže kód cívky ve `FILAMENT` nenajde. To jsem na
  produkci neověřoval.
- `ModelFile::colorChanges()`: cedulka s vyvýšeným písmem má jednu výměnu filamentu a její barva byla vždy
  náhradní oranžová `#D97706`. Teď je to barva dílu `text`, když ji návrh má. Projekt 3MF ke stažení tak nese barvu,
  kterou zákazník písmu dal. Na to, co farma zaškrtne, to vliv nemá (u cedulky se nic nepředvybírá, viz 3).

## 2. Rozhodnutí

1. **Řádek dílu říká „modrá · #2a7fd5“ i u starého návrhu s cívkou.** Zadání chtělo, aby se starý návrh zobrazil;
   nechtělo na stránce nic o cívkách. Proto se z cívky ukáže jen její barva a název nejbližší základní barvy, ne
   název cívky ani materiál. Kód cívky se při otevření **nepřepisuje**: návrh se odešle zpět, jak byl, a na hex se
   změní až ve chvíli, kdy zákazník v okně vybere jinou barvu.
2. **Vestavěné názvy drží svou starou barvu.** `white` je dál `#EDE6D6` (to, co mají uložené návrhy a co maluje
   náhled), základní „bílá“ v okně je `#ffffff`. `Palette::BASIC` a `Palette::BUILT_IN` jsou dvě různé věci:
   BASIC je nabídka okna, BUILT_IN je čtení starých návrhů.
3. **Barvu, kterou nástroj volí sám, bere ze základních šestnácti.** Podklad pod vyříznutým motivem je ta ze
   základních barev, která je motivu nejdál; těsto perníčku je základní barva nejbližší upečenému perníku
   (`#b0703c` → „měděná“ `#b5683a`); deska pod písmem je bílá `#ffffff`, písmo černé `#1a1a1a`.
4. **Pravidlo pouští `{code, hex}` i s neznámým kódem**, když je `hex` platný a kód vypadá jako kód (písmena, číslice,
   mezera a `+_.,()/-`, do 40 znaků). Důvod: `ArtGenerator` takový tvar bral vždy (návrh, jehož cívka zmizela) a
   nechtěl jsem, aby otevření starého vrstveného obrazu začalo končit chybou 422. `ParametricGenerator::clean()`
   takový díl jako dosud zahodí (barva spadne na výchozí), `ArtGenerator::clean()` ho jako dosud vezme i s hexem.
5. **Název barvy je nejbližší základní barva v prostoru Lab** – stejný výpočet v `colors.ts` jako `Palette::lab()` na
   serveru. Pojmenovávání vlastních barev, palety výrobců ani oblíbené jsem nepřidával (zadání bod 3).
6. **Dva commity kódu místo tří.** Kroky 1 a 2 sdílejí `ParametricGenerator.php` a jeden bez druhého neprojde testy
   (server by Pythonu poslal `free_colors`, kterému by nerozuměl), tak jsou v jednom commitu; krok 3 je druhý.

## 3. Co není ověřené a co jsem neudělal

- **Fotka cívky v řádku „U nás vytiskneme“ jsem v prohlížeči neviděl.** Místní databáze nemá u žádné z 92 cívek
  fotku, takže se ukázala kolečka v barvě cívky. Větev s `<img>` je napsaná, ne vyzkoušená na oko. Na produkci (117
  cívek s fotkou) se to ukáže hned: zkontrolovat velikost a ořez (20 px kolečko, `object-cover`).
- **Systémový výběr barvy na mobilu a v Safari** jsem nezkoušel (jen Chrome na Windows bez okna). Je to nativní prvek
  prohlížeče; pole hex vedle něj funguje samostatně.
- **Nejbližší cívka se hledá v celém katalogu, ne v tom, co je právě v tiskárnách.** `Palette::nearest()` dává
  přednost cívkám skladem a obyčejným povrchům, ale neví, co je založené. Řádek na kalkulaci tak může jmenovat cívku,
  kterou `/farm` zrovna nenabízí; `/farm` pak zaškrtne nejbližší z těch, které v tiskárnách jsou. Navíc každá strana
  měří jinak (kalkulace Lab, `OrderController::nearestSet` vzdálenost v RGB). Sjednotit to znamená sáhnout do farmy:
  **pro řídící session.**
- **Jednobarevný návrh se na `/farm` nepředvybere.** `OrderController::start` zaškrtává nejbližší cívky jen u návrhu,
  který své barvy jmenuje (QR, obrázek v barvách) nebo se tiskne po dílech. Krabička bez víčka v modré tak na farmě
  začíná první cívkou v nabídce, ne modrou. Bylo to tak i dřív, ale teď, když je barva volná, to bude víc vidět.
  Totéž cedulka s druhou barvou písma („začíná jednobarevně, jako vždy“ – test to hlídá). **Pro řídící session.**
- **Ukázky a karty nástrojů jsem nepřekresloval** (`matplace:tool-examples`). Hotové obrázky zůstávají. Kdyby je
  někdo překreslil s `--force`, obrázkové nástroje a vrstvený obraz vyjdou v barvách samotného obrázku a s podkladem
  ze základních šestnácti, ne v devíti vestavěných barvách jako dnes. Jednodílné nástroje (pevné `CARD_COLORS`) se
  nezmění.
- **Španělské a anglické texty** jsem psal sám, rodilý mluvčí je nečetl (21 krátkých řetězců na jazyk).
- **Skutečná objednávka na farmě s volnou barvou** neproběhla; hlídá to jen test (QR s volnou červenou zaškrtne
  červenou cívku stroje, krabička se zeleným tělem a modrým víkem jde po dílech ze zelené a modré).

## 4. Co má kdo dodělat

**Session 1 (`feature/tools-shapes`, `param.ts`, `lang/*/param.php`):**

- Texty slibují cívky tam, kde už žádné nejsou. V `lang/{cs,en,es}/param.php` úvodní věty `charm.lead`,
  `keychain.lead_form`, `earrings.lead`, `ornament.lead`, `magnet.lead`, `coaster.lead`, `bag_charm.lead`,
  `medallion.lead`, `badge.lead`, `compose.lead` („v barvách našich filamentů“, „Barvy jsou filamenty, které máme na
  farmě“, „barvy vyberete z našich filamentů“) a `shape.print.swap.farm` („předvybrané jsou ty z návrhu“ je dál
  pravda, „cívku potvrdíte při objednávce“ také). V `lang/*/edit.php` `art.lead` („převedeme do barev filamentů“).
- Nápověda u barev QR cedulky `param.c.qr.code_color.hint.farm` (`lang/cs.json`, `lang/en.json`, zdroj
  `lang/src/qr_two_color.json`) říká „Při objednání tisku předvybereme dvojici cívek, která je těmto barvám nejblíž“.
  Je to pravda a o konkrétních cívkách nemluví, tak jsem ji nechal; kdyby měla ze stránky návrhu zmizet i tahle
  zmínka, je to jedna věta ve dvou jazycích.
- `param.ts` ř. 241 a `art.ts` ř. 174: varování `toolpage.color.out` se už nikdy nespustí (`in_stock` je vždy
  `true`). Mrtvá větev, dá se smazat.
- `param.ts`: `spoolCode` už nevrací cívku, jméno je historické. Kdo bude soubor stejně přepisovat, může přejmenovat
  (export v `colors.ts` nechat, dokud ho volá i `art.ts`).
- Řádek barvy obrázku ukazuje vedle kolečka dílu i malé kolečko „barva v obrázku“ (`shape.colors.picture`). Dokud
  barvu nikdo nezmění, jsou obě stejné. Zvážit, jestli malé kolečko ukazovat jen po změně.
- `ShapeToolsTest`: upravil jsem jen tvrzení o kódech barev (obrázek už nevrací `white`/`black`/`red`, ale své hexy).
  Přibyl pomocník `named()` (hex → klíč nejbližší základní barvy) a těsto je `copper` místo `brown`. Při slévání
  s vaší větví pozor na konflikt v těch řádcích.

**Session B (`feature/papel-photo`):** piš proti `pickColor()` → hex a `colorOf(hex)`; barvy portrétu z
`shape2d.colors` přijdou s `free` jako hexy obrázku. Do `part_colors` posílej hex jako řetězec.

**Řídící session:** dvě věci u farmy z bodu 3 (nejbližší cívka z toho, co je v tiskárnách; předvýběr u jednobarevného
návrhu), slití a nasazení.

## 5. K úklidu (nechal jsem, ať nic nespadne)

- Klíče `toolpage.color.search`, `color.material`, `color.all`, `color.in_stock`, `color.none`, `color.out`,
  `color.out.badge`, `color.count`, `color.builtin` a `toolpage.hue.*`: stránky nástrojů je už nepoužívají
  (`color.out` jen v mrtvé větvi výše a v seznamu textů `filament_art.blade.php`). Stránky farmy mají své texty ve
  `farm.php`.
- `Palette::legacy()` a `colors.legacy` v `/api/config`: čte je už jen `ColorsPayloadTest`.
- `Palette::HUES`, `hue()`, pole `hue`, `light`, `search` v `colors.items`: sloužily staré skupině a hledání v okně.
  `hue()` dál potřebuje `nearest()` (penalizace speciálních barev) a `ColorCatalog`.
- `ParametricGenerator::spools()`: nikdo ho nevolá.
- `colors.ts`: `isFarmPalette()` nikdo nevolá; `initPalette()` má třetí parametr (názvy povrchů), který už nepoužívá.
- `engines/python/__pycache__/*.pyc` jsou v repozitáři a mění se při každém běhu Pythonu. Necommitoval jsem je.

## 6. Testy

- Nový `tests/Feature/CustomColorTest.php` (7 testů): paleta zná volnou barvu a 16 základních se jmény ve třech
  jazycích; jedno pravidlo bere hex, cívku i starý název a odmítne `#12`, `red;drop`, `pink`; cedulka s
  `part_colors.text = '#2A7FD5'` se uloží jako `{code: '#2a7fd5', hex: '#2a7fd5'}`, kalkulace vrátí `nearest` s modrou
  cívkou, kód cívky dál projde, projekt 3MF nese `color="#2a7fd5"`; nejbližší cívka řekne, že není skladem, a web bez
  farmy nejmenuje žádnou; obrázek v barvách vrací hexy obrázku a žádný kód cívky; vrstvený obraz bere volnou barvu
  desky; `/farm?file=` zaškrtne nejbližší cívky (QR s volnou červenou, krabička po dílech).
- Upravené: `ColorsPayloadTest` (pole QR začíná barvou `#ede6d6`, ne cívkou), `ShapeToolsTest` (viz 4).
- Prohlížeč (puppeteer-core + Chrome, místní server 8017, 10. 10. 2026): `/tools/box` – klik na **řádek** dílu
  otevře okno „Barva“ s 16 dlaždicemi a systémovým výběrem; základní modrá jedním klikem → řádek „modrá · #1f6fd6“;
  u víčka hex `#12` = chyba a vypnuté „Použít“, `#30A040` → název „zelená“, kolečko i systémový výběr se srovnají,
  po „Použít“ řádek „zelená · #30a040“; znovu otevřené okno nabízí obě barvy v „Naposledy použité“; ve formuláři není
  slovo o cívce, skladu ani PLA+. Kalkulace téhož návrhu: „U nás vytiskneme: modrá PLA+ zelená PLA+“. Žádná chyba
  v konzoli, žádná odpověď 4xx/5xx.

## 7. Nasazení (řídící session)

Bez migrace a bez nových balíčků. Běžný postup z `docs/DEPLOY-BETA.md`: sloučit, `npm run build` (začíná `tsc`),
`php artisan view:clear`, `php artisan config:cache`. Po nasazení zkontrolovat:

1. `GET /api/config` → `colors.basic` má 16 položek, `colors.items` dál celý katalog.
2. `/tools/box` → okno „Barva“ bez cívek; `/tools/magnet` s obrázkem z knihovny → řádky barev jsou barvy obrázku.
3. Kalkulace návrhu s barvou → řádek „U nás vytiskneme“ **s fotkou cívky** (to jediné jsem místně neviděl).
4. Starý návrh uložený s cívkou (`/tools/<nástroj>?from=<uuid>`) se otevře a ukáže svou barvu.

Místní poznámka k vývoji na Windows: vestavěný server PHP nepředá Pythonu `APPDATA`, takže Python z Microsoft Storu
nenajde své balíčky a stránka nástroje hlásí „generátor není dostupný“. Pomohlo dát do místního `.env` `PYTHON_BIN`
(plná cesta) a `PYTHONPATH` (uživatelské `site-packages`) a server pustit přímo: `php -S 127.0.0.1:8017` ve složce
`public` s `vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`.
