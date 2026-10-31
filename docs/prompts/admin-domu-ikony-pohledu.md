# Admin: nástroj na hlavní straně; ikony místo názvů pohledů (session 3)

Zadal Roman 31. 10. 2026 večer: „v adminu u tools přidat zobrazení toolu na hlavní straně“ a „nahradit názvy stran pohledu grafickými, to u všech nástrojů“. Ukázka vzhledu: navigační kostka sliceru (`docs/img/zadani-pohledy-kostka.png`), dnešní lišta pohledů zakroužkovaná na `docs/img/zadani-pohledy-lista.png`.

## A. Nástroj na hlavní straně z adminu

### 1. Co je dnes

- Hlavní strana (`resources/views/calculator/home.blade.php`, řádky kolem 78 až 96) bere dlaždice z `config/home.php`: pole `tools` (13 klíčů v pevném pořadí), `tools_shown` = 8, `popular` (štítek „nejčastější“). Nástroj vypnutý v adminu (`ToolVisibility::isPublic`) nebo bez route vypadne a další se posune.
- `/admin/tools` (`app/Http/Controllers/Admin/ToolController.php`, `resources/views/admin/tools/index.blade.php`, model `App\Models\ToolFlag`, tabulka `tool_flags`: `tool`, `public`, `note`, `updated_by`; popis v `docs/TOOLS-ADMIN.md`) přepíná jen to, zda je nástroj veřejný. Příznaky se čtou z cache (`ToolVisibility::CACHE_KEY`), model ji maže při uložení.
- Co má být na hlavní straně dnes znamená upravit config a nasadit.

### 2. Cíl

1. V `/admin/tools` u každého nástroje přepínač **„Na hlavní straně“** (sloupec vedle „veřejný“), uložení stejným formulářem a AJAXem jako `public`, zápis kdo a kdy (`ToolController::changed`). U nástroje, který není veřejný, je přepínač vidět, ale hlavní strana ho stejně neukáže; řekni to poznámkou u políčka.
2. Uložení: nový sloupec `tool_flags.home` (boolean, nullable: null = podle `config/home.php`, true/false = rozhodl správce). Migrace, `$fillable`, `$casts`, validace v `update()`, odpověď JSON o stav `home`.
3. Hlavní strana: seznam = nástroje s `home = true` plus nástroje z `config('home.tools')`, které správce nevypnul (`home` null), v pořadí `config('home.tools')` a za nimi ostatní zapnuté v pořadí katalogu (`config/tools.php`). `tools_shown` zůstává jako strop; v adminu ukaž počet zapnutých a upozornění, když je jich víc než strop. Štítek `popular` beze změny. Čtení přes `ToolVisibility` (nová metoda, třeba `onHome(): array`, ze stejné cache), ne přímý dotaz v blade.
4. Filtr v adminu: k dnešním filtrům (`show=`) přidej „na hlavní straně“, aby šly vidět jen domácí dlaždice.
5. Texty jen přes `lang/{cs,en,es}/tools.php` (`admin.col.home`, `admin.home.hint`, …), nic natvrdo v blade.

### 3. Testy

- Feature test: uložení přes route `admin.tools.update` zapíše `home`; hlavní strana ukáže zapnutý nástroj, který v `config('home.tools')` není, a neukáže vypnutý, který tam je; nástroj s `public = false` se neukáže ani s `home = true`; cache se po uložení vyprázdní.
- Když sáhneš na existující klíče v lang, před změnou tvaru klíče grep v `tests/` a projet `ToolPageTest|LandingPagesTest|SeoTest`.
- `docs/TOOLS-ADMIN.md` doplnit o přepínač.

## B. Ikony místo názvů pohledů (všechny nástroje)

### 1. Co je dnes

- Lišta pohledů je na jednom místě pro všechny nástroje se sdílenou stránkou: `resources/views/tools/page.blade.php` kolem řádku 140 (`#tool-views`, tlačítka `data-view` iso, top, front, side, bottom; texty `toolpage.view.*` = 3D, Shora, Zepředu, Ze strany, Zespodu), obsluha `resources/js/calc/tool_page.ts` kolem řádku 110 (`viewer.setView`, `aria-pressed`). Na telefonu lištu rozbaluje tlačítko „Pohledy“ s ikonou oka (`#tool-view-toggle`).
- Jiné stránky vlastní lištu pohledů nemají (grep `data-view`; `lang/cs/edit.php o.side.*` jsou strany orientace při úpravě modelu, těch se to netýká). Ověř ještě figurku a domácí kalkulaci; když tam pohledy jsou, sjednoť je stejně.

### 2. Cíl

1. Každé tlačítko pohledu má místo slova **ikonu**: malá krychle s obarvenou stěnou, na kterou se dívám (iso = krychle šikmo, shora = horní stěna, zepředu = přední, ze strany = boční, zespodu = spodní). Inline SVG přes `x-icon` (`resources/views/components/icon.blade.php`, nové názvy `view-iso`, `view-top`, `view-front`, `view-side`, `view-bottom`), `currentColor`, 20 px, stejný styl jako dnešní ikony lišty (oko, vrstvy). Vzor: kostka sliceru ze snímku (šedá krychle, zvýrazněná stěna; osy a popisky netřeba).
2. Slovo nezmizí: zůstane v `title` a `aria-label` (stávající klíče `toolpage.view.*`), takže čtečka a najetí myší řeknou, co tlačítko dělá. Aktivní pohled (`aria-pressed="true"`) musí být poznat i bez textu (plná stěna nebo tmavé pozadí tlačítka, jako dnes).
3. Lišta se zúží; zkontroluj 390 px a 1280 px, ať se nic nepřeskládá (sdílené `stick-*` a lišta, docs/AN.md §18) a tlačítka drží dotykovou plochu aspoň 44 px.
4. Tlačítko „Pohledy“ na telefonu ať ukazuje ikonu právě zvoleného pohledu místo oka, když to vyjde levně.

### 3. Testy

- `ToolPageTest` (stránka se staví, každé tlačítko pohledu má `aria-label`), snímky 390 a 1280 px do `docs/img/views-icons-390.png` a `docs/img/views-icons-1280.png`, `npx tsc --noEmit -p tsconfig.json`, `npm run build`, `vendor/bin/pint --test`.

## Kde a jak

- Session 3, worktree `C:\matplace-edit-wt`, větev `feature/admin-home-view-icons` z main (01392c2e nebo novější). Soubory viz výše. Zápis: `docs/AN.md` nová kapitola §21 („Nástroj na hlavní straně z adminu, ikony pohledů“).
- Texty jen přes lang soubory, `git add` jmenovitě, žádný stash. Nic nenasazuj.
- Po pushi zpráva řídící session: větev, commit, co je ověřené, snímky. Řídící sloučí a nasadí na Romanovo „nasaď“.

## C. Stahování: jedna položka projektu na stránce nástroje, popisky dílů v kalkulaci

Doplnil Roman 31. 10. 2026 večer u přívěsku: „proč máme stažení modelu na první straně návrhu s výběrem Bambu a Prusa, když to potom přejde na další stránku a tam je další výběr; a název krabička se sem nehodí, to zřejmě zůstalo po prvním toolu“. Snímky `docs/img/zadani-stahovani-nastroj.png` (nabídka na stránce nástroje) a `docs/img/zadani-stahovani-kalkulace.png` (panel v kalkulaci s „Jen krabička · color_1 · color_2 · color_3“).

### 1. Co je dnes

- Nabídka „Stáhnout model“ na stránce nástroje: `resources/js/calc/tool_page.ts` kolem řádku 301 (`toolpage.download.orca`, `toolpage.download.prusa`, pak STL, ZIP, díly). Položky projektu nic nestahují: vedou na `?open=<uuid>&download=1&slicer=orca|prusaslicer`, tedy uloží návrh a otevřou kalkulaci s panelem stahování; `slicer` jen předřadí výrobce v seznamu (`resources/js/calc/download.ts` kolem řádku 90). Totéž mají `resources/js/calc/edit.ts` kolem řádku 406, `art.ts` kolem řádku 310 a `param.ts` kolem řádku 576 (tam přes `save(…, 'prusaslicer')`).
- Panel v kalkulaci (`download.ts`, blade `resources/views/calculator/index.blade.php`, texty `download.*` v `lang/src/param.json`): výrobce, model, „Stáhnout projekt (.3mf)“, „Jen samotný model (.stl)“, „Jednotlivé díly zvlášť“. Díly popisuje jen obecným klíčem `param.part.<díl>` (`download.ts` kolem řádku 123): `param.part.body` = „Jen krabička“ (zbytek po prvním nástroji, krabičce) a díly bez klíče surově (`color_1`). Stránka nástroje má vlastní popisky (`param.ts` `partLabel`, řádky 209 až 218: `shape.part.*` podle druhu = Podklad, Barva 1, Lem; `param.part.<díl>.<druh>`, pak obecný klíč).

### 2. Cíl

1. Na stránce nástroje (a v editaci, filament artu i `param.ts`) **jedna položka** místo dvou: „Projekt pro můj slicer (3MF)“ s dovětkem „Tiskárnu vyberete na další stránce (OrcaSlicer, Bambu Studio, PrusaSlicer).“ Odkaz bez `slicer=`; předřazení výrobce podle zapamatované tiskárny v `download.ts` zůstává. Klíče `toolpage.download.orca` a `toolpage.download.prusa` nahraď jedním (`toolpage.download.project`); před změnou grep v `tests/` a projet `ToolPageTest|LandingPagesTest|SeoTest` (pravidlo z paměti o změně klíčů).
2. Kalkulace popisuje díly **stejně jako stránka nástroje**: vyjmi `partLabel` z `param.ts` do sdíleného modulu (třeba `resources/js/calc/parts.ts`) a použij ho v `download.ts` s `file.kind` a s tím, zda jde o tvarový druh (`shape`), aby přívěsek ukázal „Podklad · Barva 1 · Barva 2 · Barva 3“. Krabička dál „Jen krabička“ (klíč `param.part.body.box` už je v `lang/src/order_part_labels.json`); obecný `param.part.body` přepiš na „Jen tělo“ (en „Body only“, es „Solo el cuerpo“) v `lang/src/param.json` a projeď `python scripts/lang_add.py param`. Ověř, že stránka objednávky s díly (`order_part_labels`) ukazuje totéž.
3. Zkontroluj, že „Celý model (STL)“ na stránce nástroje a „Jen samotný model (.stl)“ v kalkulaci říkají stejnou věc stejnými slovy; sjednoť na „Celý model (STL)“.

### 3. Testy

- `ToolPageTest` (nabídka má jednu položku projektu), test kalkulace na popisky dílů přívěsku a krabičky (`CalculatorPageTest` nebo kam to patří), `npx tsc --noEmit -p tsconfig.json`, `npm run build`, `vendor/bin/pint --test`. Snímky nabídky nástroje a panelu kalkulace po změně do `docs/img/download-one-project-*.png`.
- Zápis do téže kapitoly `docs/AN.md` §21 (část C).

## D. Dodatek po odevzdání (řídící, 1. 11. 2026 v noci, po sloučení 5c2f99b7 do main b71a3585)

1. **Ručně zapnuté nástroje jdou na hlavní straně první.** Config dnes jmenuje 13 nástrojů a strop je 8, takže nově zapnutý nástroj je čtrnáctý a neukáže se. Nové pořadí: nástroje s `home = true` v pořadí katalogu (nebo `config('home.tools')`, když tam jsou), za nimi seznam z `config('home.tools')` bez vypnutých, do stropu `tools_shown`. Štítek `popular` beze změny. Řádek v adminu ať říká, že zapnutý nástroj je vždy první. Test v `ToolHomeSwitchTest`: zapnutý nástroj mimo config je na stránce i při plném configu.
2. **Jeden název dílu všude.** Stránka objednávky má u medaile „Placka a řetěz“ a „Číslo a lem“ (`lang/src/order_part_labels.json`, `param.part.<díl>.medallion`), nástroj a kalkulace „Medaile a řetěz“ a „Barva 1“. Sjednoť na popisnější texty objednávky: sdílený `partLabel` (parts.ts) i PHP strana objednávky ať dají přednost klíči podle druhu `param.part.<díl>.<druh>` před obecným `shape.part.color`; texty medaile vyber tak, aby seděly i na stránce nástroje („Medaile a řetěz“ nebo „Placka a řetěz“, jedno z toho všude; doporučuji „Medaile a řetěz“ a „Číslo a lem“). Puzzle na objednávce ukazuje syrové `piece_2`: doplň `param.part.piece` s `:n` („Díl :n“) i pro objednávku. Soubory objednávky (`OrderService`, `order_part_labels.json`) jsou session 2, pro tento dodatek je můžeš změnit; napiš session 2 přes řídící, co ses změnil.
3. Větev `feature/home-first-part-names` z main (po sloučení #57, tedy 7c27156d nebo novější), zápis do AN.md §21 jako část D. Testy jako v §3 částí A a C. Nic nenasazuj, po pushi zpráva řídící.
