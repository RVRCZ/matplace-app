# Zadání pro session B: Portrét z fotky v papel picado (`/tools/papel-picado`, režim „Portrét“)

Předloha: https://stlbuddy.com/photo-to-papel-picado („Photo to Papel Picado“). Pět snímků obrazovky předlohy je
v `docs/img/stlbuddy-papel-{photo,frame,size,placement,colors}.png`. Cíl je stejný jako u všech nástrojů:
**udělat to lépe než předloha**, zdarma, s koncem na farmě (tisk u nás ve dvou barvách) nebo ve stažení.

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-papel-wt`**, větev **`feature/papel-photo`** (z `main` cee51b1, 10. 10. 2026). Je připravený:
  `.env` (sqlite `database/dev.sqlite`, `APP_URL=http://localhost:8016`, `PYTHON_BIN` na python 3.11), `node_modules`
  je junction do `C:\matplace-app\node_modules` (nikdy ho nemaž ani nepřepisuj), `vendor` a `public/build` hotové.
  Místní server: `php artisan serve --port=8016` (porty 8010, 8013–8015 mají jiné session).
- Tvůj dokument: **`docs/T.md`** (co jsi postavil, rozhodnutí, co není ověřené, poznámky k nasazení). Vzor: `docs/P.md`
  (session 1) a `docs/R.md` (session 3).
- Řídící session (farma, `feature/farm-colors`) slévá a nasazuje. Ty **nenasazuješ** a **nemerguješ do `main`**;
  pošli jí commit (SendMessage na `conceprt19092026-50`), až bude kolo hotové. Commity po jednom celku, pushni na origin.
- Pravidla, která platí pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php` (žádný JSON); testy
  `php -d memory_limit=2G vendor/bin/phpunit --filter …`, před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`
  a `npm run build` (build teď začíná `tsc`, chyba typů ho zastaví); **nikdy `taskkill /F /IM python.exe`** (na tomhle
  počítači běží agent farmy), nikdy `queue:retry all`; jedna session na worktree; `git add` jen jmenovitě, žádný
  `git add -A`, žádný `git stash`.
- Soubory, do kterých jen **přidáváš** (patří jiným): `config/tools.php`, `routes/web.php`, `lang/*/param.php`,
  `lang/*/tools.php`, `resources/js/calc/param.ts`, `resources/js/calc/viewer.ts`, `engines/python/creative_kinds.py`.
  Farmu (`app/Domain/Farm`, `resources/js/calc/farm.ts`, `app/Jobs/PrepareFarmOrder.php`) **neměň vůbec**; co od
  farmy potřebuješ, už umí (bod 2).
- Model: Fable 5.1 (geometrie + viewer); Opus 5.5 stačí, když Fable není po ruce.

## 1. Co předloha umí (z pěti snímků)

Stránka má vlevo svislé záložky **Photo · Frame · Size · Placement · Colors**, uprostřed náhled 3D s plovoucím
panelem **Portrait placement** (Move / Rotate / Scale, pole X a Z v mm, „X and Z move along the frame. Release to
rebuild.“), dole **Download format: 3MF two colors / STL one solid**, stavový řádek `190 × 190 × 2.6 mm`.

1. **Photo** – nahraná fotka (JPG, PNG, WebP; drop kamkoli), dvě miniatury **Original** a **Portrait** (výsledek
   zpracování), výběr „Portrait treatment: **Cut‑paper portrait**“, posuvníky **Light / dark** (50 %), **Portrait detail**
   (45 %, „Use less detail for broader, simpler shapes“), **Trim below shoulders** (22 %, „Trim the lower part of a photo
   to bring the face forward. Flat artwork keeps its full outline“), zaškrtávátka **Isolate the subject** (odstranění
   pozadí) a **Reverse light and dark**. Poznámka: „A clear, well‑lit face works best. The portrait has a solid backing
   so small details stay attached.“
2. **Frame** – **Flower pattern** (Folk flowers …), **Flower density** (0,5), **Border width** (9 mm); „Decorative flower
   and leaf cutouts open up a solid panel with a scalloped edge“ – okraj má po celém obvodu zoubky (půlkruhy)
   s dírkou v každém zoubku, ne jen dole.
3. **Size** – Width 190, Height 190, **Base thickness 2 mm**, **Portrait relief 0,6 mm**.
4. **Placement** – Portrait size (1×), Left/right (mm), Up/down (mm), Rotation (°), tlačítka **Edit in 3D** a **Reset
   placement**; „Keep the portrait inside the frame. Placement is carried into your download.“
5. **Colors** – **Frame & details** (tmavá) a **Portrait backing** (světlá). „3MF keeps both colors as aligned parts.
   STL is a single solid for one‑color printing.“

Výsledek předlohy: plochý panel v tmavé barvě s prolamovaným okrajem; v okně světlá podkladová deska a na ní
tmavé plochy portrétu (dvoutónový, „vystřihovaný“ obraz tváře) vyvýšené o 0,6 mm. Portrét není proříznutý skrz,
drží ho podklad, proto se nic neodlomí.

## 2. Co už máme (nepiš znovu, navaž)

- **`/tools/papel-picado`, druh `papel`** (session 1, `engines/python/creative_kinds.py` od řádku „papel picado“,
  `ParametricGenerator` FIELDS/FLAGS/OPTIONS pro `papel`, texty `lang/*/param.php` klíče `papel.*`, SEO
  `lang/*/tools_seo/papel.php`, karta a ukázky). Dnes dělá **vyříznutou siluetu**: tmavé části obrázku zůstávají jako
  papír, světlé jsou díry skrz; fotka jde cestou `_papel_photo` (výřez na poměr okna, autokontrast, rozostření
  `soften`, práh Otsu + posuvník `darkness`, úklid drobností), `_papel_ties` dělá spojky visících kusů, `_papel_unit`
  kreslí šest vzorů okraje, `scallop` zoubky dole, `string_holes` otvory na šňůru. Panel je jeden obrys vytažený na
  `thickness`. **Tenhle režim zůstává** (girlanda z několika jednobarevných panelů); přidáváš druhý.
- **Gizmo v náhledu**: `Viewer.setFrame({box, z}, cb)` z vrstveného skladače (`resources/js/calc/viewer.ts`,
  `FrameChange {dx, dy, scale, turn}`, fáze `move`/`end`); jak ho používá stránka, viz `param.ts` u `compose`
  (`viewer.setFrame(...)`, blok `#compose-edit`). Předloha má plovoucí panel s přepínačem Move/Rotate/Scale –
  u nás rámeček umí tažení za střed (posun), za roh (měřítko) a za úchyt (otočení) najednou; panel s čísly X/Y,
  velikost, otočení a tlačítko „Výchozí umístění“ přidej vedle, jako má skladač.
- **Barvy dílů**: okno palety (`resources/js/calc/colors.ts`, `pickColor`), řádky dílů ve `param.ts`
  (`renderParts`, třída `tool-swatch-row` – celý řádek otevírá okno), uložení do `tool_params.part_colors[<díl>] =
  {code, hex}`. Díly deklaruje `ParametricGenerator::partsOf()` a `PARTS`.
- **Dvě barvy nad sebou na farmě i ve 3MF**: `tool_params.color_changes = [{z, part, code, hex}]` (jedna položka
  = výměna ve výšce `z`); farma z toho předvybere cívky a tiskárna si vymění filament sama
  (`docs/FARM-COLORS.md` §1–5); 3MF se dvěma barvami dělá `ColorChange::addAll` (Orca i PrusaSlicer, ověřeno
  u cedulky). `multi_material = false`. Přesně takhle to dělají obrázkové nástroje session 1 (`charm`, `magnet`…):
  `ParametricGenerator::create()` ukládá `color_changes` z `meta.notes.color_changes` – podívej se, jak.
- **Odstranění pozadí**: na serveru je `rembg` (u2net) v `/opt/matplace-py`, model v `/opt/matplace-py/u2net`
  (proměnná prostředí `U2NET_HOME`). Session C k němu napsala `app/Engines/Photo/BackgroundRemover` +
  `engines/python/photo_cut.py` (`--probe`, `U2NET_HOME` přes `Process::env`), ale zatím jen na větvi
  `feature/tools-sell` (není v `main`). Nečekej na ni: zavolej `rembg` přímo z `_papel_photo` (import uvnitř funkce,
  `rembg.remove(img, session=rembg.new_session("u2net"))`), při `ImportError` vrať do meta `notes.rembg = false` a stránka
  zaškrtávátko „Oddělit postavu od pozadí“ ukáže šedé s vysvětlením. Lokálně `rembg` nemáš; otestuj s falešnou
  maskou (viz testy). Až se `tools-sell` slije, přepojení na `BackgroundRemover` je pět řádků – napiš to do T.md.
- **Fotky v nástrojích**: nahrání přes Artwork okno (záložky Nahrát / Knihovna / Moje), `Artwork::REF`,
  `artwork_path` v parametrech; `shape2d.raster` fotky odmítá úmyslně, proto má `papel` vlastní cestu.

## 3. Co postavit

### 3.1 Režim „Portrét“ (nový) vedle „Silueta“ (stávající)

Nový parametr `treatment` (`cutout` = dnešní chování, `portrait` = nové; výchozí `portrait`, když je nahraná fotka,
`cutout` u SVG a u motivů z knihovny). V režimu `portrait`:

- **Geometrie**: celý panel (okraj se vzorem, zoubky, okno) je **podkladová deska** tloušťky `base` (1–3 mm, výchozí 2)
  v barvě „Podklad portrétu“; **na ní** leží v barvě „Rám a detaily“ vrstva `relief` (0,3–1,2 mm, výchozí 0,6):
  tmavé plochy portrétu v okně **a celý okraj kolem okna** (pás `border` včetně vzoru, zoubky). Díry okraje (květy,
  dírky zoubků, otvory na šňůru) jdou skrz obě vrstvy. Celková výška = `base + relief` (2,6 mm jako předloha).
  Shora to vypadá jako předloha: tmavý rám, světlé okno, tmavý portrét; z boku je vidět světlá hrana podkladu – to je
  cena za tisk jednou tryskou s výměnou ve výšce `base`, stejně jako u cedulky s vystouplým písmem. Napiš to do tipu.
- **Dva díly**: `body` (podklad) a `details` (rám + portrét); `partsOf('papel')` je vrací jen v režimu `portrait`;
  `meta.notes.color_changes = [{z: base, part: 'details', code, hex}]`, `part_colors.body` a `part_colors.details`
  (výchozí: podklad = nejsvětlejší, rám = nejtmavší cívka palety, jako u cedulky). Soubor STL = jedno těleso;
  **3MF se dvěma barvami** = stávající exportér s jednou výměnou (nepiš nový).
- **Portrét nemusí mít spojky**: drží ho podklad. `_papel_ties` se v režimu `portrait` nevolá na portrét; okraj
  spojky nepotřebuje nikdy (vzor je v souvislém pásu).
- **Fotka → dvoutónový portrét** (`_papel_photo` rozšířit, ne duplikovat): 
  - `darkness` (Světlo / stín, 10–90, výchozí 50) = stávající posun prahu Otsu;
  - `detail` (Kresba portrétu, 0–100 %, výchozí 45) nahradí `soften` v režimu `portrait`: nízká hodnota = silnější
    rozostření **a** větší nejmenší zachovaný prvek (úklid drobností škáluje s `detail`), vysoká = jemná kresba.
    `soften` zůstane pro režim `cutout` (kompatibilita uložených návrhů);
  - `trim` (Oříznout pod rameny, 0–60 %, výchozí 22): spodní část fotky se ořízne o `trim` % výšky **před** vsazením
    do okna, aby tvář byla větší; u kresby (SVG) a u motivu z knihovny se neořezává nic;
  - `isolate` (Oddělit postavu od pozadí, výchozí zapnuto, jen když je `rembg`): pozadí se nahradí bílou, takže se
    nevyřízne; **a** obrys postavy se použije pro „Oříznout pod rameny“ (ořez jde od spodního okraje masky, ne od
    okraje fotky);
  - `invert` (Obrátit světlo a stín) = stávající.
  - Kvalita kresby: předloha dává souvislé plochy (vlasy, brýle, stíny) bez roztřepení. Kroky: autokontrast →
    mírné rozostření → práh → otevření/zavření → **vyhlazení obrysů** (`offset` ± jako dnes) → odstranění ostrůvků
    pod ~1,6 mm. Vyzkoušej na třech fotkách obličeje (vlastní, nebo volná z Unsplash/Pexels – licenci zapiš do T.md,
    nebo si fotku vygeneruj; do repozitáře ukázkovou fotku cizího člověka nedávej). Zorničky a odlesky brýlí: zůstat
    mají; jsou na podkladu, nic je nedrží ve vzduchu.
  - **Dvě miniatury** v panelu Fotka: **Původní** a **Portrét** (výsledek). Portrét vrací server jako malé PNG
    (do 300 px) v `meta.notes.preview` (base64) při každé stavbě, stránka ho ukáže; nepočítej ho v prohlížeči zvlášť,
    ať sedí s tím, co se tiskne.
- **Když se portrét nepovede** (prázdný, nebo přes 90 % tmavé): chyba `image_blank` jako dnes, s textem, co zkusit
  (jiné „Světlo / stín“, vypnout oddělení pozadí).

### 3.2 Rám

- `border` (vzor): stávajících šest + `none`; **přidej** `folk` („lidové květy“: květ s pěti okvětními lístky
  a lístky mezi květy, jako předloha) – jedna další větev v `_papel_unit`/skládání pásu.
- `density` (Hustota vzoru, 0,2–1,0, výchozí 0,5): rozestup jednotek vzoru v pásu (dnes pevný).
- `border_mm` (Šířka okraje, 6–30 mm, výchozí 12): dnes se počítá jako 13 % kratší strany – ponech ten výpočet jako
  výchozí hodnotu, když parametr chybí (staré návrhy), jinak číslo z pole.
- `scallop` rozšiř: `none` / `bottom` (dnešní) / `all` (zoubky po celém obvodu s dírkou v každém zoubku – předloha;
  výchozí `all` v režimu `portrait`, `bottom` v `cutout`). Otvory na šňůru nahoře zůstávají volitelné.

### 3.3 Velikost

`width`, `height` jako dnes (80–250; Kobra S1 má podložku 250, Max 420 – `SIZE_FIELDS`/kontrola podložky ve vieweru
to hlídá), `base` (1–3) a `relief` (0,3–1,2) nové; `thickness` zůstává pro `cutout`.

### 3.4 Umístění portrétu

Parametry `portrait_scale` (0,5–1,5×), `portrait_x`, `portrait_y` (mm, ±polovina okna), `portrait_turn` (°, ±45).
Portrét se vsadí do okna v daném měřítku, posunu a otočení; co přesahuje okno, se ořízne (portrét je jen uvnitř
okna). V náhledu: rámeček `Viewer.setFrame` kolem portrétu (jako vrstva ve skladači) – tažení = posun, roh = měřítko,
úchyt = otočení; po puštění se přepočítá (`end`). Vedle toho panel s čísly (X, Y, velikost, otočení) a tlačítko
**Výchozí umístění**. Umístění se ukládá do `tool_params`, jde do STL/3MF i na farmu. Předloha má zvláštní tlačítko
„Edit in 3D“ – u nás je rámeček vidět vždy, když je nahraná fotka a pohled „celý model“.

### 3.5 Barvy a stažení

Dva řádky dílů v kroku Barvy: **Rám a detaily** (`details`) a **Podklad portrétu** (`body`), výběr barvy přes
`pickColor()` jako jinde. **Pozor (10. 10.):** session D (`docs/prompts/vlastni-barva.md`) mění okno barev na volný výběr
(systémový color picker + hex; naše cívky se ukážou až na kalkulaci a farmě) – API `pickColor`/`colorOf`/`paintSwatch`
zůstává, jen vrací hex místo kódu cívky. Piš proti tomu API, nic o cívkách na stránce neukazuj; výchozí barvy
portrétu nastav hexem (podklad světlý `#f3efe4`, rám tmavý `#1f2a44`).
Stažení: **3MF (dvě barvy)** výchozí, **STL (jedno těleso)**; názvy souborů jako u ostatních nástrojů. Tlačítko
„Vytisknout u nás“ vede na farmu, která z `color_changes` předvybere cívky (nic na farmě neměníš; otestuj, že
`/farm?file=<uuid>` ukáže blok „Druhá barva“ s předvybranou tmavou cívkou – vzor testu: `FarmOrderFlowTest`,
`test_a_qr_sign_starts…` a testy session 1 pro `charm`).

### 3.6 Stránka a texty

- Kroky v levém panelu: **Fotka · Rám · Velikost · Umístění · Barvy · Stažení** (stránka nástroje `tools/page.blade.php`,
  `tool_page.ts` `Stage`; kroky a ovládání jako u skladače). Režim Silueta / Portrét je první volba v kroku Fotka.
- Texty cs/en/es: `lang/*/param.php` klíče `papel.*` (lead, tip pro oba režimy, názvy a nápovědy polí, chyby);
  `lang/*/tools_seo/papel.php` dopsat o portrét (H1/lead/FAQ zmiňují fotku tváře, dvě barvy, tisk u nás).
- Karta a tři ukázky (`php artisan matplace:tool-examples papel --force` a `--card`, viz `ToolExamples`): jedna ukázka portrét
  z **kreslené** tváře (ne fotka cizího člověka), jedna silueta z knihovny (cukrová lebka), jedna lidový rám bez obrázku.
- „Lepší než předloha“ (ať je v T.md, co z toho je hotové): náhled pod 1 s při 190 mm (mřížka `PAPEL_PX` zůstává),
  barvy z cívek farmy, tisk u nás jedním klikem, varování, když je portrét příliš jemný na trysku 0,4 (plochy pod
  0,8 mm), ukázky v cs/en/es, žádné kredity.

### 3.7 Testy

`tests/Feature/PapelPortraitTest.php` (nebo doplň `ShapeToolsTest`), s nakreslenou tváří jako fotkou (PIL: ovál,
oči, ústa – viz jak session 1 testuje `_papel_photo`):
- stavba v režimu `portrait`: výška bbox = `base + relief` ± 0,01, díly `body` a `details`, objem `details` > 0 a
  < objem `body`, `color_changes` = `[{z: base, part: 'details', …}]`, `part_colors` oba díly;
- `trim` zmenší výšku masky, `invert` prohodí plochy, `isolate` s `rembg` nedostupným = zaškrtávátko vypnuté
  a `notes.rembg === false` (testuj s `ImportError`, nezaváděj závislost do `requirements`);
- umístění: posun `portrait_x` posune bbox dílu `details` v okně, otočení nepustí portrét ven z okna;
- rám `folk`, `density`, `scallop: all` dávají platné těleso (watertight, objem > 0) při 80 × 80 i 250 × 250;
- `cutout` zůstává beze změny: stávající testy session 1 musí projít beze změny.
- Stránka: `ToolPageTest`/`ToolsFlowTest` pro `/tools/papel-picado` (200, kroky, texty ve třech jazycích), start
  farmy předvybere druhou barvu.

### 3.8 Pořadí práce

1. Přečti `docs/P.md` (část Papel picado), `creative_kinds.py` (papel), `param.ts` (compose + renderParts),
   `docs/FARM-COLORS.md` §1 a jak `charm` ukládá `color_changes`.
2. Geometrie portrétu (3.1) + testy stavby. Commit.
3. Rám (3.2), velikost (3.3). Commit.
4. Umístění s rámečkem ve vieweru (3.4). Commit.
5. Stránka, barvy, miniatury, texty, SEO, ukázky (3.5–3.6). Commit.
6. `docs/T.md`, celá sada testů, pint, tsc, build; push; zpráva řídící session.

## 4. Co není v zadání

Žádná migrace (vše v `tool_params`), žádný nový PHP ani npm balíček, žádná změna farmy ani exportéru 3MF. Když
narazíš na něco, co by změnu farmy chtělo (např. třetí barva), napiš to do T.md a řídící session, nedělej to sám.
