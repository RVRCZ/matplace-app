# Import 3MF s více barvami (barvy namalované ve sliceru): kalkulace, zakázka, farma. Session 2 a session 3

Roman 28. 10. 2026 večer: načetl `turtlescutieone2.3mf` (uložený v Anycubic Slicer Next) do tří slicerů. Slicer Next
a PrusaSlicer ukážou želvu ve dvou barvách, u nás je celá modrá. „My neumíme importovat .3mf s více barvami.“
Piš česky, krátce, bez pomlček. Deníky: session 2 AL.md §14, session 3 AN.md §19.

## Co v souboru je (řídící ověřila 28. 10.)

- 1 objekt, 2 komponenty, 317 686 trojúhelníků, každý s atributem `paint_color` (malování po trojúhelnících, formát
  Bambu Studio / OrcaSlicer / Anycubic Slicer Next). Žádné `basematerials`, `model_settings.config` má extruder 1
  u obou dílů. PrusaSlicer ukládá totéž jako `slic3rpe:mmu_segmentation`.
- `engines/python/colors_tool.py analyse` to už čte: 4 barvy, extrudery 2/4/3/1, zelená 77,7 %, žlutá 21,4 %,
  bílá 0,8 %, černá 0,1 % (nenamalovaný zbytek), 5,7 s. Celý `edit_tool.py colors` 7,5 s, 3 díly `color_1..3`,
  ale žlutá (21 %) zmizela v `color_1` („mixed“, 9 těles). To je chyba: dvě tělesa s různou základní barvou se slila.
- Dnes: `ThreeMfConverter` při nahrání barvy zahodí, kalkulace i zakázka vidí jeden kus v jedné barvě. Nástroj
  `/tools/colors` to umí, zákazník o něm neví. Výsledek nástroje barev má v `OrderService::designParts` (řádek 434)
  výslovně `[]` dílů, zakázka ho tiskne jako jeden kus v jedné barvě.

## Cíl

Zákazník nahraje namalovaný 3MF kamkoli (kalkulace, zakázka, nástroj) a web ho od začátku bere jako vícebarevný:
náhled v barvách, počet barev v kalkulaci, na zakázce cívka ke každé barvě (předvyplněná nejbližší skladová),
farma tiskne na stroji s měničem na jedné podložce podle AL.md §10, jinak po dílech. Želva je přejímací test.

## Rozdělení

- **Session 2** (`conceprt19092026-6f`, `C:\matplace-gcode-wt`, větev `feature/import-3mf-colors` z main): rozbor při
  nahrání, data na souboru, zakázka, farma, cena, oprava sloučení.
- **Session 3** (`conceprt19092026-01`, `C:\matplace-edit-wt`, větev `feature/calc-3mf-colors` z main): náhled
  v barvách, řádek barev a cena v kalkulaci, stránka `/tools/colors`.
- Pořadí: session 2 nejdřív navrhne tvar dat (JSON barev na `ModelFile`, skupiny trojúhelníků pro náhled, pole
  v `GET /api/files/{uuid}` a v odpovědi kalkulace) a pošle ho session 3 zprávou, než začne kódovat. Session 3 do té
  doby připraví viewer na skupiny podle hex. `farm/order.blade.php` a `farm.ts` upravuje session 2; session G
  (`conceprt19092026-cf`) tam nemá nic rozpracovaného, před zásahem jí to session 2 oznámí zprávou.

## Session 2

1. **Rozbor při nahrání.** U každého nahraného `.3mf` (UploadController, zpracování ve frontě, ne v požadavku: 5,7 s)
   spustit `colors_tool.py analyse` a výsledek uložit na soubor (např. `colors`: seznam `{hex, extruder, share,
   source}`, `has_colors`). Barvy pod 0,5 % plochy sloučit do barvy tělesa a říct to (`merged`). Barvy filamentů brát
   z projektu (`Metadata/project_settings.config`: `filament_colour`, `extruder_colour`), bez projektu paleta jako dnes.
2. **Náhled.** STL pro náhled s trojúhelníky seřazenými po barvách a `groups: [{hex, from, to}]` (stejný tvar jako
   `parts[].tris` z nástroje barev), ať viewer obarví přes `addGroup` (viewer.ts) bez druhého souboru. Bez barev beze změny.
3. **Zakázka.** Namalovaný 3MF se 2 a více barvami jde do zakázky `by_parts` s díly po barvách (vnitřně nástroj barev
   spuštěný automaticky jako puzzle `auto_apply`, nebo díly rovnou z rozboru). Cívka ke každé barvě předvyplněná
   nejbližší skladovou (vzdálenost v Lab, `colors.ts` to pro paletu má), zákazník ji může změnit. Stroj vybere
   `machineForColors`, jedna podložka přes `prepareOnePlate` (AL.md §10), jinak po dílech. Opravit `designParts` pro
   `colors` (dnes `[]`; zjistit proč přes `git log -S`), ať i výsledek `/tools/colors` jde po dílech.
4. **Chyba sloučení žluté.** Na želvě musí vyjít dvě hlavní barvy (zelená, žlutá) a dvě drobné. Malý namalovaný 3MF
   do `tests/fixtures` (dvě tělesa s různou základní barvou, na jednom nálepka) a test na počet a podíl barev.
5. **Jak farma řeže.** (a) naše díly po barvách (vložka 1,2 mm, funguje všude, tiskne i po dílech), (b) původní
   namalovaný 3MF rovnou Orce s mapou extruder na cívku (přesné malování bez zahloubení, jen stroj s měničem, Prusa
   `mmu_segmentation` Orca nečte). Základ zůstává (a), (b) jen když to Orca 2.4 na produkci vezme: ověřit na místní Orce
   ve scratchpadu (`orca24/`, AL.md §10.8b) a zapsat čas řezu a hmotnost věže. Bez čísla nerozhodovat.
6. **Cena.** Hrubý odhad (`rough`) u vícebarevného souboru počítá věž a výměny podle počtu barev (čísla `flush`
   v AL.md §10.2). Kalkulace dostane dvě ceny: jednobarevně a „barevně od“.
7. **Omezení.** Víc než `FarmOrder::MAX_COLORS` (4) barev: nabídnout hlavní 4 se sloučením zbytku, nebo jednobarevně.
   Žádná bílá stránka ani 500 na soubor bez barev, s jednou barvou, s poškozeným `paint_color`.

## Session 3

1. **Náhled v barvách** v kalkulaci a na zakázce: má li soubor `groups`, obarvit po skupinách (viewer.ts `addGroup`
   a vertex colours jako u dílů nástroje). Bez skupin jako dnes.
2. **Kalkulace.** Řádek „Soubor má 2 barvy (a 2 drobné, sloučené)“ s barevnými tečkami, cena jednobarevně a „barevně
   od X Kč“ (podle API session 2), tlačítko „Vytisknout barevně“ vede na zakázku s předvyplněnými barvami; volba
   jednobarevného tisku zůstává. Nic neskáče (platí `rozlozeni-bez-skoku.md`).
3. **Stránka `/tools/colors`.** Horní odstavec: nahrajte 3MF namalovaný v Bambu Studio, OrcaSlicer, Anycubic Slicer Next
   nebo PrusaSlicer. Pojem „namalované barvy“ do slovníku. Texty přes `lang/src` a `python scripts/lang_add.py`.

## Ověření

- Želva `C:\Users\roman\Downloads\turtlescutieone2.3mf` (do scratchpadu, do repa jen malý fixture): nahrání
  v kalkulaci dá zeleno žlutý náhled, „2 barvy“, dvě ceny; zakázka má dvě cívky předvyplněné; dry run
  `prepareOnePlate` na místní Orce 2.4 dá 3MF se dvěma filamenty a hlavičku `; matplace filaments`. Nic netisknout
  bez Romana.
- Testy: nový `ThreeMfColorsTest` (fixture), `FarmOrderColors`, `FarmOrderFlow`, `CalculationFlowTest`,
  `ToolsFlowTest`, `ToolPageTest`; pint, tsc, build. Každé upravené JSON zkontrolovat `python -m json.tool`.
- Zpráva řídící (`conceprt19092026-91`) po pushi: čas rozboru, díly želvy, čas řezu, co zůstalo otevřené.

## Pravidla

Větev jen z main, žádný stash, `git add` jménem, texty jen přes lang. Soubory, které právě drží jiná session,
nesahat (zeptat se zprávou). Klíče a `.env` jen od Romana. Nic nenasazovat, nasazuje řídící.
