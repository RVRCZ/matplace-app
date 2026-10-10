# Zadání pro session D: vlastní barva (color picker) v barvách dílů na stránkách nástrojů

Předloha: stlbuddy u každého dílu („Frame & details“, „Portrait backing“) otevírá obyčejný systémový výběr barvy
(`<input type="color">`, ve Windows dialog „Color“ s paletou, odstínem, sytostí a RGB; snímek
`docs/img/stlbuddy-color-picker.png`). Naše stránky nabízejí **jen cívky farmy** (okno „Barva filamentu“).
To je naše výhoda (barva = cívka, kterou opravdu máme, tisk u nás jedním klikem) a zůstává výchozí. Doplň k ní
**vlastní barvu**: zákazník, který tiskne doma na vlastním filamentu, si zvolí jakýkoli odstín; náhled, 3MF i karta
návrhu ho použijí, a farma si k němu sama najde nejbližší cívku.

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-colorpick-wt`**, větev **`feature/color-picker`** (z `main`, 10. 10. 2026). Připravené: `.env`
  (sqlite `database/dev.sqlite`, `APP_URL=http://localhost:8017`, `PYTHON_BIN`), `node_modules` je junction do
  `C:\matplace-app\node_modules` (nemaž, nepřepisuj), `vendor`, `public/build`. Místní server `php artisan serve --port=8017`.
- Dokument: **`docs/U.md`** (co, rozhodnutí, co není ověřené, poznámky k nasazení). Vzor `docs/N.md` (session 0).
- Řídící session (`conceprt19092026-50`) slévá a nasazuje; ty **nemerguješ do `main`** a nenasazuješ. Commity po celcích,
  push na origin, pak zpráva řídící session (SendMessage).
- Pravidla pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php`; `php -d memory_limit=2G vendor/bin/phpunit
  --filter …`; před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`, `npm run build`; **nikdy
  `taskkill /F /IM python.exe`**, nikdy `queue:retry all`; jedna session na worktree; `git add` jen jmenovitě; žádný `git stash`.
- **Souběh:** v `resources/js/calc/param.ts` právě pracuje session 1 (`feature/tools-shapes`) a session B
  (`feature/papel-photo`, díly `body`/`details`). Proto: **všechno nové dej do `resources/js/calc/colors.ts`** (okno,
  vlastní barva, `colorOf` pro hex) a do `app/Domain/Farm/Palette.php`; v `param.ts`, `art.ts`, `edit.ts` měň jen to
  nejnutnější (zobrazení názvu „vlastní“ u řádku dílu), po jednom malém diffu, bez přesouvání kódu. Co by vyžadovalo víc,
  napiš do U.md a řídící session.
- Model: Opus 5.5 stačí (TypeScript + PHP, žádná geometrie).

## 1. Co máme dnes (navaž, nepřepisuj)

- **Okno barev**: `resources/js/calc/colors.ts` – `initPalette(payload)` z `/api/config` (`colors.items`: `code, name, hex,
  material, finish, photo, in_stock, hue, light, search`; `legacy` = staré názvy → kód), `pickColor(current)` otevře
  `<dialog>` se skupinami podle odstínu, hledáním, filtrem materiálu, „jen skladem“ a „naposledy použité“
  (`localStorage` `mp_colors_recent`); vrací **kód cívky** (`string`). `colorOf(code)` dá `PaletteColor`,
  `paintSwatch(el, code, hex?)` obarví kolečko, `materialLabel(c)`.
- **Uložení barvy dílu**: `tool_params.part_colors[<díl>] = {code, hex}` (`ParametricGenerator::clean()` kolem ř. 416:
  `['code' => $code, 'hex' => $palette->hex($code)]`; `ArtGenerator::clean()` ř. 97–107 bere hex z požadavku, když je
  platný, jinak z palety). Výměny barev `tool_params.color_changes[] = {z, part, code, hex}`. Všude dál (náhled na
  kalkulaci, farma, 3MF) se čte **hex**, kód je jen odkaz na cívku.
- **Validace na serveru**: `ParametricGenerator::rules()` ř. 514–523 – `params.part_colors.*` a barevná pole
  (`isColor($key)`) musí být `Rule::in($palette->codes())`; `ArtGenerator::rules()` ř. 70–72 – `part_colors.*.code` string
  do 40 znaků + `hex` regex. `ModelEditor` (barvy 3MF, `edit.ts`) má vlastní pravidla – podívej se, jak ukládá `colors`.
- **Paleta na serveru**: `app/Domain/Farm/Palette.php` – `all()`, `codes()`, `has()`, `hex($code)` (BUILT_IN názvy,
  katalog cívek, zaniklé cívky), **`nearest($hex)`** (Lab vzdálenost, penalizace neskladem a speciálních) – to je přesně
  „nejbližší cívka k vlastní barvě“, nepiš další.
- **Farma**: úvodní stránka `/farm` předvybírá cívky z **hexů** (`OrderController::start`: `nearestSet`, `part_colors.*.hex`,
  `color_changes.*.hex`), kód nepotřebuje. 3MF: `app/Engines/Converter/ThreeMfConverter.php` a `ColorChange::addAll`
  pracují s hexy, které dostanou.
- Kde se `pickColor` volá: `param.ts` (řádky dílů `renderParts`, barvy obrázku v barvách, kreslení polevy, skladač),
  `art.ts` (vrstvený obraz), `edit.ts` (malovaný 3MF – barvy dílů). Řádek dílu má třídu `tool-swatch-row` (celý řádek
  otevírá okno, listener v `tool_page.ts`).

## 2. Co postavit

### 2.1 Vlastní barva v okně „Barva filamentu“

- V hlavičce okna vedle filtru materiálu tlačítko **„Vlastní barva…“**. Po kliknutí se v okně otevře řádek: nativní
  `<input type="color">` (systémový dialog – na Windows ten z předlohy, na mobilu vlastní kolečko prohlížeče),
  textové pole **hex** (`#rrggbb`, s okamžitou kontrolou), náhledové kolečko, tlačítko **Použít**. Žádná vlastní
  barevná paleta v JS – prohlížeč to umí lépe.
- Vrácená hodnota z `pickColor()` je **hex jako kód**: `'#2a7fd5'` (malá písmena). Tím zůstane všechno, co je klíčované
  kódem, beze změny: `part_colors[díl] = {code: '#2a7fd5', hex: '#2a7fd5'}`.
- `colorOf('#2a7fd5')` vrátí syntetický `PaletteColor`: `{code: '#2a7fd5', name: t('toolpage.color.custom') ('vlastní barva'),
  hex, material: '', finish: 'solid', photo: null, in_stock: false, hue: hue z hexu, light, search: ''}` –
  `paintSwatch` a řádek dílu tak fungují bez úprav; `materialLabel` vrátí `'#2a7fd5'` (ať zákazník vidí kód).
- „Naposledy použité“ si pamatuje i hexy (max 8 vlastních), zobrazí je jako dlaždice bez fotky s popiskem hexu.
- Když je zvolená vlastní barva, pod řádkem dílu malý text: **„vlastní barva · na farmě vytiskneme nejbližší cívku:
  <název> (<materiál>)“** – název z `Palette::nearest` posílá server v odpovědi na stavbu (`meta.notes.nearest[<díl>] =
  {code, name, hex}`), nebo spočítej v prohlížeči stejnou Lab vzdáleností z `palette.items` (zvol jedno; server je
  pravda pro farmu, prohlížeč je rychlejší – doporučuji prohlížeč pro text a server pro skutečný výběr, který už dělá
  `/farm` sám).
- Okno má přepínač, co je výchozí: **cívky**. Vlastní barva je vždy až druhá volba (tlačítko), ne záložka na úrovni cívek.

### 2.2 Server

- `Palette::isCustom($code)` (regex `/^#[0-9a-f]{6}$/i`), `Palette::hex()` vrátí hex sám pro vlastní kód,
  `Palette::has()` true pro vlastní.
- Validace: `ParametricGenerator::rules()` – `Rule::in(codes)` nahradit pravidlem „kód cívky **nebo** hex“ (vlastní
  `Rule`/closure, jedno místo pro všechna barevná pole a `part_colors.*`); `ArtGenerator::rules()` – `code` může být hex;
  `ModelEditor` totéž, pokud ukládá kódy. `clean()` všude uloží `{code: hex, hex: hex}`.
- `Palette::nearest()` pro vlastní barvu: nic nového; jen doplň do `meta.notes.nearest` (viz 2.1), pokud zvolíš server.
- `/api/config` beze změny.

### 2.3 Kde všude se to projeví (ověř, nic nepřepisuj)

- Náhled ve vieweru (barvy dílů = hex), kalkulace (`partsByColour` čte `part_colors.*.hex`), 3MF se dvěma barvami
  a barevné díly (`ThreeMfConverter`, `ColorChange::addAll` – hex), karta návrhu v „moje návrhy“, **farma**: `/farm`
  předvybere nejbližší cívku z hexu (ověř testem na cedulce s vlastní barvou písma: `change_color[0]` je nejbližší cívka),
  obrázky v barvách (`shape2d.colors` mapuje barvy obrázku na cívky – tam vlastní barvu nenabízej, barvy obrázku mají
  cívky sedět; ale **ruční přebarvení** vrstvy obrázku na vlastní barvu ano).
- Ukázky a karty nástrojů (`matplace:tool-examples`) se nemění.

### 2.4 Texty

`lang/{cs,en,es}/toolpage.php` (nebo kde jsou klíče `toolpage.color.*`): `color.custom` („Vlastní barva“), `color.custom_hint`
(„Jakýkoli odstín pro tisk doma. U nás se vytiskne nejbližší cívkou.“), `color.custom_hex` („Hex, např. #2a7fd5“),
`color.use` („Použít“), `color.nearest` („na farmě: :name (:material)“). Všechny tři jazyky, žádný JSON.

### 2.5 Testy

- `tests/Feature/CustomColorTest.php`: návrh cedulky (`sign`) s `part_colors.text = '#2a7fd5'` se uloží s hexem a vrátí
  v `file.tool.params`; nekorektní hodnota (`'#12'`, `'red;drop'`) je 422; `Palette::nearest('#2a7fd5')` vrátí modrou
  cívku; `/farm?file=<uuid>` zaškrtne nejbližší cívku pro písmo (vzor `FarmOrderFlowTest::test_a_qr_sign_starts…`);
  3MF stažení obsahuje hex (podívej se, jak testuje `ColorChangeTest`); `ArtGenerator` s vlastní barvou desky.
- Stávající testy palety a nástrojů beze změny (`ToolPageTest`, `ParametricToolsTest`, `ShapeToolsTest`, `ColorChangeTest`).
- Headless Chrome: okno otevřít, kliknout „Vlastní barva…“, zapsat hex, „Použít“, řádek dílu ukáže kolečko v té barvě
  a text o nejbližší cívce (`puppeteer-core` je v tomhle počítači ve scratchpadu řídící session; nebo totéž ručně
  v prohlížeči a napiš to do U.md).

### 2.6 Pořadí

1. `colors.ts` + `Palette.php` + validace (2.1–2.2), testy serveru. Commit.
2. Texty, drobné úpravy řádků dílů (`param.ts`, `art.ts`, `edit.ts` – jen název a poznámka), ověření farmy a 3MF
   testem. Commit.
3. `docs/U.md`, pint, tsc, build, celá sada, push, zpráva řídící session.

## 3. Co není v zadání

Žádná migrace, žádný balíček, žádná změna farmy ani exportérů; vlastní barva se nepřidává do katalogu cívek ani do
admina. Nerozšiřuj okno barev o další funkce (třídění, oblíbené) – jen vlastní barva.
