# Kopie na jedné podložce u návrhů se zarovnanými barvami (TODO #72)

Pro session 2, 8. 11. 2026. Roman: „proč máme pravidlo jedna podložka = jeden kus u sloučení do roviny, bez sloučení to
funguje, zadej opravu“. Co viděl: na stránce nástroje „Barvy zarovna s povrchem“ + 4 ks, na stránce zakázky náhled jen
jednoho kusu (minuty a cena za čtyři), po zaplacení „Nepodařilo se … dvě barvy v jedné vrstvě … a jeden kus … Kredit jsme
vám vrátili“ (Prusa Core One INDX). S 1 ks projde.

Worktree `C:\matplace-gcode-wt`, nová větev `fix/one-plate-copies` z `origin/main` (až po sloučení #71, řídící řekne;
na startu `git fetch origin`). Po pushi napiš řídící. Nic nenasazuj, žádný `migrate*` ve worktree (sdílená dev.sqlite,
testy běží v paměti), git add jen jménem, žádný stash, commit s `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
Texty: nové JSON klíče přes `lang/src/one_plate_copies.json` + `python scripts/lang_add.py`; klíče `farm.*` v
`lang/{cs,en,es}/farm.php` jsou starý formát, ty uprav přímo ve všech třech souborech. Každý JSON, kterého se dotkneš,
projdi `json.load` s kontrolou duplicitních klíčů. PHPUnit po jedné sadě (`php -d memory_limit=2G vendor/bin/phpunit
--filter …`), nikdy dvě naráz.

## Proč to dnes padá

- `ModelFile::onePlateRequired()` (druh + `tool_params.flush` + `multi_material`, docs/P.md §11) říká „jen jedna podložka“.
  `OrderFlow::pay` (řádek 97) takový návrh s `copies !== 1` odmítne (`FarmRefusal('one_plate_needed')`, kredit zpět) a
  `PrepareFarmOrder::prepareOnePlate` (řádek ~728) kopie u dílů nástroje přeskočí (`skip('copies')`); jen malovaný soubor
  kopie má (docs/AL.md §14.8: přebarvený objekt N× jako build item, Orca řeže objekt jednou a tiskne instance, výměny po
  vrstvách, počet výměn se kusy nemění). V AL.md §10 je to zapsané jako odložený limit („rozložení více kusů z více dílů
  na jednu podložku zatím není“); zarovnané barvy z #58 ho zdědily, protože jdou vždy jednou podložkou.
- Stránka zakázky jen varuje drobným textem (`farm.order.one_plate_copies`, `farm.ts` 687 a 926), platbu nechá; náhled
  načítá díly jednoho kusu (`loadParts(partUrls, one_plate)`), minuty a cena se násobí kusy. Proto to vypadá dobře až do
  zaplacení.
- Vedle toho `resources/js/calc/param.ts` 400–408 má čtyřku natvrdo („nejvýš čtyři v jednom tisku“,
  `shape.print.plate.many.farm`); Core One má 8 pozic (#66, #70, `ColorLimit::farm()`).

## Co udělat

### A. Kopie v `prepareOnePlate` i pro díly nástroje

- Zrušit `skip('copies')`. Rozměr kusu = společná obálka všech dílů tak, jak leží (`$lo`/`$hi` tam už jsou);
  `copiesPlan($size, $bed, $margin / 2, $nFilaments, copies, ! $toolChanger)` se na řádku ~787 už počítá, použij ho:
  `per` kusů na plnou podložku, zbytek na poslední, víc podložek za sebou jako u malovaného souboru (řádky ~997 dál,
  „more copies than a plate takes“). U měniče nástrojů bez pruhu pro věž, u ACE s pruhem (`tower: true`).
- `ThreeMfWriter::write`: nový nepovinný parametr s posuny kopií (`list<array{float, float}>`); každý objekt dostane tolik
  `<item objectid=".." transform="1 0 0 0 1 0 0 0 1 dx dy 0"/>`, kolik je posunů (dnes jeden s jednotkovou maticí). Všechny
  díly jednoho kusu mají stejný posun, ať motiv zůstane zapadlý do podkladu. Ověř proti skládání dílů na střed
  (`StlFile::place`: slicer centruje jen sítě, ne projekty), celý rastr musí ležet uvnitř použitelné plochy s okrajem.
- Záznam jako u malovaného: `part_plates[*].copies = per`, `plates`, `plate_copies`, `rest_copies`, `rest_gcode_path`,
  `copies_grid`, `check.max_copies`; gramy po nástrojích z `; filament used [g]` instance už sčítají,
  `FarmOrder::filamentSlots()` beze změny; čas ze řezu. Zúžení před kopiemi (#68, `decimate`) nech, jak je.
- Řez ověř falešným slicerem i skutečnou Orcou: měnič Orcou 2.5 (`tests/Support/fake_orca.php` a FarmToolChangerPlateTest
  z #70, AM.md §12j), ACE Orcou 2.4 portable ve scratchpadu `orca24/` (AL.md §10). Zapiš počet výměn a gramy u 1 ks a 4 ks.

### B. Platba a důvody

- `OrderFlow::pay`: z podmínky vypadne `copies !== 1`; zůstává odmítnutí ručního stroje. Cívky ze dvou strojů dál řeší
  `prepareOnePlate` (`two_machines`).
- `PrepareFarmOrder` 151 (`fail … one_plate_needed`): důvod `copies` z výčtu `one_plate_skipped` zmizí; text důvodů
  `farm.php` 479 (cs/en/es) uprav.

### C. Stránka zakázky

- `OrderController::describe` (1037–1046) a `farm.ts` (684–687, 886–892, 922–930): u `one_plate_required` místo „po jednom
  kuse“ ukázat kusy na podložku a podložky (`farm.copies.plates` i pro jednu podložku s více kusy, `farm.copies.max` +
  `more_plates` jako u malovaného). Náhled dílů jednoho kusu může zůstat, řádek pod ním řekne „4 kusy na 1 podložce
  (2 × 2)“ z `plate_layout`/`copies_grid`.
- Texty `one_plate_required`, `one_plate_copies`, `one_plate_single`, `one_plate_needed` (3 místa) v
  `lang/{cs,en,es}/farm.php`: „po jednom kuse“ a „a jeden kus“ pryč, zůstává „tiskne se vcelku na tiskárně s měničem“.

### D. Stránka nástroje

- `param.ts` 400–408: limit z farmy místo 4. Ověř, co stránka nástroje už dostává (`/api/config`, `max_colors` ve
  `farm.ts` 56); když nic, dej `ColorLimit::farm()` do konfigurace stránky. Texty `shape.print.plate.many` a
  `shape.print.plate.many.farm` s `:max` místo „čtyři“ (nové znění přes `lang/src/one_plate_copies.json`).

### E. Odhad před řezem

- `OrderService::create/reslice` a `BigModel::judge`: odhad minut, gramů a ceny u zarovnaného návrhu s kusy násobit stejně
  jako u ostatních (zdá se, že už násobí, ověř); po řezu platí řez. Porovnej orientačně 4 ks proti řezu 1 ks × 4.

### F. Testy a kontrola

- Najdi sady grepem na `one_plate_skipped`, `one_plate_needed`, `FlushDown`, `FarmOnePlate`, `FarmToolChangerPlate`.
  Případy: 4 ks zarovnaného návrhu na INDX → 1 podložka, 3MF se 4 build items na každý díl se společnými posuny,
  `plate_copies 4`, gramy podle pozic, bez věže; 9 ks při 4 na podložku → 3 podložky (4 + 4 + 1), `rest_gcode_path`;
  ACE → pruh věže zůstává; ruční stroj → `one_plate_needed` s důvodem `manual`; platba 4 ks projde (dřív 422);
  `describe` nese `plates`, `plate_copies`, `max_copies` a text bez „po jednom kuse“; stránka nástroje s limitem 8 ukáže
  `.many` až nad 8. Dnešní očekávání `copies 2 → failed/copies` (AL.md §10) otoč.
- `vendor/bin/pint --test`, `npx tsc --noEmit -p .`, `npm run build`, PHPUnit po jedné sadě (celou sadu před pushem
  v pozadí), lang JSON s kontrolou duplicit; pytest se netýká (jen PHP a TS).

### G. Dokumentace a předání

- `docs/AL.md` §10: nový pododdíl „10.x Kopie na jedné podložce (8. 11. 2026)“ a oprava věty o limitu; AM.md §12j
  jedna věta, že projekt pro měnič má instance.
- `docs/TODO.md` řádek 72: připisuj na konec své buňky přes `rsplit(" | ", 2)`, nikdy nekotvi na text, jiné řádky neměň.
- Paměť: jen připiš odstavec do
  `C:\Users\roman\.claude\projects\c--Users-roman-Documents-MatPlace-conceprt19092026\memory\project_one_plate_copies.md`,
  `MEMORY.md` nech řídící.
- Nechat být: malovaná 3MF (kopie už mají), jednobarevný zarovnaný návrh (`multi_material: false`) jde dál jako jeden kus.
  Odchylky od zadání napiš do předání.
