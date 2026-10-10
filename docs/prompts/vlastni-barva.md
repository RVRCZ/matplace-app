# Zadání pro session D: barvy na stránkách návrhu = volný výběr barvy; naše cívky až na dalších stránkách

Rozhodnutí Romana (10. 10. 2026): **na stránkách návrhu (nástroje `/tools/*`) už neukazujeme naše cívky.** Zákazník
si tam vybere jakoukoli barvu obyčejným výběrem barvy, jako má předloha stlbuddy (systémový dialog
`<input type="color">`, snímek `docs/img/stlbuddy-color-picker.png`). **Naše cívky (fotky, materiál, skladem) se ukážou
až na dalších stránkách**: na stránce kalkulace u tlačítka „Vytisknout u nás“ a na úvodní stránce farmy `/farm`, kde
se k barvám návrhu sama předvybere nejbližší cívka (to už funguje, farma pracuje s hexy).

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-colorpick-wt`**, větev **`feature/color-picker`** (z `main` 3b3316c, 10. 10. 2026). Připravené:
  `.env` (sqlite `database/dev.sqlite`, `APP_URL=http://localhost:8017`, `PYTHON_BIN`), `node_modules` je junction do
  `C:\matplace-app\node_modules` (nemaž, nepřepisuj), `vendor`, `public/build`. Místní server `php artisan serve --port=8017`.
- Dokument: **`docs/U.md`** (co, rozhodnutí, co není ověřené, poznámky k nasazení). Vzor `docs/N.md` (session 0).
- Řídící session (`conceprt19092026-50`) slévá a nasazuje; ty **nemerguješ do `main`** a nenasazuješ. Commity po celcích,
  push na origin, pak zpráva řídící session (SendMessage).
- Pravidla pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php`; `php -d memory_limit=2G vendor/bin/phpunit
  --filter …`; před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`, `npm run build`; **nikdy
  `taskkill /F /IM python.exe`**, nikdy `queue:retry all`; jedna session na worktree; `git add` jen jmenovitě; žádný `git stash`.
- **Souběh:** v `resources/js/calc/param.ts` právě pracuje session 1 (`feature/tools-shapes`) a session B
  (`feature/papel-photo`). Drž změny v **`resources/js/calc/colors.ts`** (okno a `pickColor`, `colorOf`, `paintSwatch`,
  `materialLabel` – jejich API zůstává, mění se obsah), v `app/Domain/Farm/Palette.php` a ve validaci; v `param.ts`,
  `art.ts`, `edit.ts` jen malé, oddělené diffy (popisky řádků), žádné přesuny kódu. Co by chtělo víc, napiš do U.md
  a řídící session – ta rozhodne, kdy to která session udělá.
- Model: Opus 5.5 (TypeScript + PHP, žádná geometrie).

## 1. Co máme dnes

- **Okno barev** `resources/js/calc/colors.ts`: `initPalette(payload)` z `/api/config` (`colors.items`: cívky farmy s
  `code, name, hex, material, finish, photo, in_stock, hue, light, search`), `pickColor(current)` otevře `<dialog>` se
  skupinami cívek podle odstínu, hledáním, filtrem materiálu, „jen skladem“ a „naposledy použité“ (`localStorage`
  `mp_colors_recent`), vrací **kód cívky**. `colorOf(code)`, `spoolCode(code)`, `paintSwatch(el, code, hex?)`,
  `materialLabel(c)`, `recentColors()`, `rememberColor(code)`. Volá se z `param.ts` (řádky dílů `renderParts`, barvy
  obrázku v barvách, kreslení polevy, skladač vrstev), `art.ts` (vrstvený obraz), `edit.ts` (malovaný 3MF).
- **Uložení**: `tool_params.part_colors[<díl>] = {code, hex}` (`ParametricGenerator::clean()` ~ř. 416:
  `['code' => $code, 'hex' => $palette->hex($code)]`; `ArtGenerator::clean()` ř. 97–107 bere hex z požadavku, když je
  platný). Výměny barev `tool_params.color_changes[] = {z, part, code, hex}`. Dál (kalkulace, farma, 3MF, karta
  návrhu) se čte **hex**; kód je jen odkaz na cívku.
- **Validace**: `ParametricGenerator::rules()` ř. 514–523 – barevná pole a `params.part_colors.*` jsou
  `Rule::in($palette->codes())`; `ArtGenerator::rules()` ř. 70–72 (`code` string do 40 + `hex` regex); `ModelEditor` má
  vlastní (barvy 3MF).
- **Paleta na serveru** `app/Domain/Farm/Palette.php`: `all()`, `codes()`, `has()`, `hex($code)` (BUILT_IN jména, katalog,
  zaniklé cívky), `nearest($hex)` (Lab vzdálenost, penalizace neskladem a speciálních) – nejbližší cívka k barvě.
- **Obrázek v barvách** (session 1, `shape2d.colors`, `colors_tool.py`, stránka `param.ts` blok „barvy obrázku“): z
  obrázku se vyberou hlavní barvy a dnes se **mapují na cívky farmy** (kódy); řádky ukazují cívku, podíl plochy,
  přesun nahoru/dolů, sloučení.
- **Farma**: `/farm` (`OrderController::start`) předvybírá cívky z **hexů** (`nearestSet`, `part_colors.*.hex`,
  `color_changes.*.hex`, `parts`); stránka objednávky nabízí cívky stroje; kalkulace (`calculator.ts`) maluje díly hexem.
  3MF: `app/Engines/Converter/ThreeMfConverter.php`, `ColorChange::addAll` – hexy.

## 2. Co postavit

### 2.1 Výběr barvy na stránkách návrhu (`colors.ts`)

- `pickColor(current)` otevře **jednoduché okno „Barva“** místo katalogu cívek:
  - **základní barvy**: 16 pojmenovaných dlaždic (bílá, černá, šedá, červená, oranžová, žlutá, zelená, tyrkysová, modrá,
    fialová, růžová, hnědá, béžová, zlatá, stříbrná, měděná – hexy zvol rozumně a napiš je do `Palette::BASIC` na serveru
    i do `colors.ts`, jedno místo pravdy přes `/api/config` `colors.basic`),
  - **vlastní barva**: nativní `<input type="color">` (systémový dialog předlohy; na mobilu kolečko prohlížeče) + pole
    **hex** `#rrggbb` s okamžitou kontrolou + náhledové kolečko + **Použít**,
  - **naposledy použité** (do 8, `localStorage`, hexy).
  - Vrací **hex** (`'#2a7fd5'`, malá písmena). Všechny volající (`param.ts`, `art.ts`, `edit.ts`) dostanou hex tam, kde
    dřív dostaly kód; `colorOf(hex)` vrátí syntetický `PaletteColor` (`name` = český název nejbližší základní barvy, např.
    „modrá“, `material: ''`, `photo: null`), `paintSwatch` a `materialLabel` (vrátí hex) tak fungují bez úprav. Staré
    uložené návrhy s kódem cívky dál `colorOf(code)` zobrazí (cívka z `/api/config` zůstává k dispozici pro čtení), ale
    po otevření okna se už nabízí jen volné barvy.
  - **Nic o cívkách, materiálech, skladu ani fotkách** se na stránce návrhu neukazuje. Pryč jde i text „na farmě
    vytiskneme nejbližší cívku“ – to řekne až kalkulace (2.4).
- Řádek dílu (`tool-swatch-row`): kolečko v barvě + název (např. „modrá · #2a7fd5“). Popisky dílů zůstávají.

### 2.2 Obrázek v barvách (session 1) bez mapování na cívky

Hlavní barvy obrázku se na stránce ukážou **jako barvy obrázku** (hex z kvantizace), ne jako cívky: řádky „Barva 1
(34 %)“ s kolečkem v té barvě a stejnou nabídkou úprav (přesun, sloučení, ruční změna přes výběr barvy). Podívej se, co
`shape2d.colors` / `colors_tool.py` vrací a jak se v Pythonu mapuje na paletu (`palette` v parametrech volání): nejčistší
je mapování na cívky **vynechat** (paleta = prázdná nebo neposílat) a nechat kvantizované hexy; když to pipeline bez
palety neumí, pošli jako „paletu“ samotné nalezené barvy. Uložené `part_colors` a `color_changes` dostanou `code = hex`.
Totéž pro vrstvený obraz (`art.ts`, `ArtGenerator`) a malovaný 3MF (`edit.ts`, `ModelEditor`): barvy z 3MF se ukážou
jako hexy ze souboru. Session 1 pracuje na `param.ts` souběžně – její řádky barev obrázku **neměň strukturálně**, jen
zdroj dat a popisek; napiš do U.md, co by sama měla dodělat (např. texty „nalezené cívky“ → „nalezené barvy“).

### 2.3 Server

- `Palette::isCustom($code)` (`/^#[0-9a-f]{6}$/i`), `Palette::hex()` vrátí hex pro vlastní kód, `Palette::has()` true,
  `Palette::BASIC` (název cs/en/es + hex, do `/api/config` jako `colors.basic`).
- Validace (`ParametricGenerator::rules()`, `ArtGenerator::rules()`, `ModelEditor`): barva = **hex nebo kód cívky**
  (jedno pravidlo, jedno místo); `clean()` ukládá `{code: hex, hex}`. Kódy cívek zůstávají povolené kvůli starým
  návrhům a kvůli farmě.
- `/api/config` `colors.items` zůstává (farma, čtení starých návrhů), přibude `colors.basic`.
- Ukázky a karty nástrojů (`matplace:tool-examples`): barvy ukázek jsou pevné hexy v `ParametricGenerator::SAMPLE`/
  `COLOR_HEX` – zkontroluj, že se nic nezmění; když ano, nepřekresluj, jen napiš do U.md.

### 2.4 Naše cívky na dalších stránkách

- **Kalkulace** (`/c/<token>`, `resources/views/calculator/*.blade.php`, `calculator.ts`): u bloku „Vytisknout u nás“
  (`farm/cta.blade.php`) ukaž řádek **„U nás vytiskneme: ● bílá PLA+, ● červená PLA+“** – pro každou barvu návrhu
  (`part_colors`, `color_changes`) nejbližší cívku s fotkou, názvem a materiálem; server ji spočítá `Palette::nearest()`
  (nové pole `nearest` v odpovědi kalkulace, `app/Http/Controllers/Api/CalculationController::describe` nebo
  `UploadController::describe`/`toolOf` – kde je `tool.params`). Když cívka není skladem, řekni to. Nic se tím
  nerozhoduje – výběr dělá `/farm` jako dnes.
- **Farma `/farm`**: beze změny (předvýběr z hexů). Ověř testem, že návrh s vlastní barvou písma zaškrtne nejbližší
  cívku (vzor `FarmOrderFlowTest::test_a_qr_sign_starts…`, `test_a_picture_in_three_colours…`).

### 2.5 Texty

`lang/{cs,en,es}/toolpage.php` (klíče `color.*`): `color.custom` („Vlastní barva“), `color.custom_hex` („Hex, např.
#2a7fd5“), `color.use` („Použít“), `color.basic` („Základní barvy“), názvy 16 základních barev (`color.basic.<klíč>`),
`color.recent` zůstává; `farm.calc_nearest` („U nás vytiskneme: :list“) a `farm.calc_nearest_out` („(není skladem)“)
v `lang/*/farm.php`. Staré klíče `color.in_stock`, `color.search`, `color.out.badge` ponech (stránky farmy je nepoužívají,
ale ať nic nespadne), v U.md je označ k úklidu.

### 2.6 Testy

- `tests/Feature/CustomColorTest.php`: cedulka s `part_colors.text = '#2a7fd5'` se uloží s hexem a vrátí v
  `file.tool.params`; `'#12'`, `'red;drop'` → 422; kód cívky dál projde; kalkulace vrátí `nearest` s cívkou pro
  `#2a7fd5`; `/farm?file=` zaškrtne nejbližší cívku; 3MF obsahuje hex (`ColorChangeTest` ukazuje jak); vrstvený obraz
  s vlastní barvou desky; obrázek v barvách vrátí hexy (ne kódy cívek).
- Stávající sady beze změny: `ToolPageTest`, `ParametricToolsTest`, `ShapeToolsTest`, `ColorChangeTest`, `ArtTest`
  (nebo jak se jmenují), `FarmOrderFlowTest` (jen dotčené testy).
- Headless Chrome (ručně nebo `puppeteer-core`, který má řídící session ve scratchpadu): okno otevřít, zvolit základní
  barvu, pak vlastní přes hex, řádek dílu ukáže barvu; na kalkulaci řádek „U nás vytiskneme“ s fotkou cívky.

### 2.7 Pořadí

1. `colors.ts` nové okno + `Palette` + validace + `colors.basic` (2.1, 2.3), testy serveru. Commit.
2. Obrázek v barvách, vrstvený obraz, malovaný 3MF bez mapování na cívky (2.2). Commit.
3. Kalkulace „U nás vytiskneme“ (2.4), texty (2.5). Commit.
4. `docs/U.md`, pint, tsc, build, dotčené sady, push, zpráva řídící session.

## 3. Co není v zadání

Žádná migrace, žádný balíček, žádná změna farmy (`app/Domain/Farm` kromě `Palette.php`, `farm.ts`, `PrepareFarmOrder`)
ani exportérů 3MF; katalog cívek a admin se nemění. Nerozšiřuj výběr barvy o palety výrobců, oblíbené ani pojmenování
vlastních barev.
