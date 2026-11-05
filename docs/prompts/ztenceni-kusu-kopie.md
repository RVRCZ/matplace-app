# Ztenčení kusu před rozkopírováním a velký model podle kusu × kopie (TODO #68)

Pro session 2, 5. 11. 2026 (Roman „zadej“). Worktree `C:\matplace-plate-wt` (stojí na sloučené `feature/plate-layout`),
nová větev `fix/decimate-copies` z `origin/main`. Po pushi napiš řídící session. Nic nenasazuj, žádný `php artisan
migrate*` ani `db:*` ve worktree (sdílený `dev.sqlite`). Souběžně běží #69 v `C:\matplace-gcode-wt` nad `farm.ts`,
`OrderController`, `OrderService::availableColors` a dál, Blade zakázky a `lang`; těch míst se nedotýkej (výjimka:
jeden řádek `OrderService.php` asi 111, volání `BigModel::judge`).

## Proč

Roman 4. 11. 2026 ráno, zakázka j9vAL9… (jeden model, 388 162 trojúhelníků, 4 kusy): „trvá to hodně dlouho a to jsem
ještě nedal barvu“. Ze serveru: kus sám je pod prahem 400 000, ale podložka `plate.stl` se 4 kopiemi má 1 552 648
trojúhelníků (77 MB). `MeshDecimator` ztenčoval celou podložku, po 240 s (`decimate_timeout`) to vzdal, pak Orca celou
podložku nařezala za 50 s (G-code 18,6 MB, hotovo 07:40:12). Čtyři z pěti minut bylo ztenčování zbytečně. Dvě chyby:
ztenčuje se podložka s kopiemi místo kusu před rozkopírováním, a verdikt „velký model“ (`BigModel::judge`, odloží řez až
po volbách zákazníka) počítá jen jeden kus, ne kus × kopie, proto řez jel hned, před volbou barvy.

## Stav dnes

- `app/Jobs/PrepareFarmOrder.php` asi 184 až 210: `PlateLayout::capacityOf($piece, $usable)`, u kopií
  `PlateLayout::replicate($piece, plate.stl, $perPlate, $usable)` a podložka jde do `OrcaSlicer::slice`; zbytková podložka
  asi 251 až 262; zakázka po dílech asi 431 až 459 (`part-n-plate.stl`) stejně.
- `app/Engines/Slicer/OrcaSlicer.php` asi 205 až 220: `MeshDecimator::wants($meshPath)` podle počtu trojúhelníků v hlavičce
  STL (nad `engines.mesh.decimate_above` 400 000), `decimate(mesh, thin, layer)` s cílem `decimate_target` 350 000 a
  limity odchylky podle vrstvy (`limits()`), `decimate_timeout` 240 s; výsledek do `timings` (`decimate_s`,
  `triangles_in`, `triangles_sliced`, `decimate_deviation_mm`, `decimate_peak_mm`, `decimate_skipped`).
- `app/Engines/Mesh/MeshDecimator.php`: mezipaměť `cacheKey` = otisk bajtů souboru + cíl + limity (složka
  `decimate_cache`). Podložka s kopiemi má pokaždé jiné bajty, mezipaměť nikdy netrefí; kus by trefil při každém přeřezu
  (změna barvy, zbytková podložka, díly).
- `app/Domain/Farm/BigModel::judge(file, calculation)` nad `App\Domain\Calculation\BigModel` (`Threshold::reason`:
  trojúhelníky souboru nad `decimate_above`, nebo `slice_s` kalkulace nad `big_slice_s` 60 s) a `estimateSeconds`
  (`slice_s` × 1,2 + 15, bez kalkulace `farm.big_model.seconds_per_million` 150 × miliony, nejméně `min_seconds` 120).
  Volá `OrderService.php` asi 111 při vzniku zakázky, verdikt se zapíše na `farm_orders.big_model` a už se nemění
  (AL.md §11.1). Kopie (`$order->copies`) do verdiktu ani do odhadu nevstupují.
- Python `engines/python/decimate_tool.py` (`in out target max_dev max_peak`), `farm_tool.py prepare`,
  `plate_shadow.py`. Docs: AL.md §10.11 (ztenčení, měření), §11 (velký model, odhad na stránce `slice_estimate_s`).
- Lokální Orca 2.4 portable má session 2 ve svém scratchpadu
  (`C:\Users\roman\AppData\Local\Temp\claude\c--Users-roman-Documents-MatPlace-conceprt19092026\59282dac-fa71-408c-9292-79f5e4187435\scratchpad\orca24`),
  jen spouštět, nic tam nepsat; postup lokálního řezu a enginu v AL.md §10.11 a §14.10. Produkce má Orcu 2.4.0-beta.

## Co udělat

A. **Práh i ztenčení na kus, ne na podložku.** V `PrepareFarmOrder` před `replicate`: když je kus sám nad
   `decimate_above`, ztenči kus (`MeshDecimator::decimate` s cílem `decimate_target` a limity podle vrstvy zakázky) a
   podložku, zbytkovou podložku i podložky dílů sestav z ztenčeného kusu; čísla do `timings` jako dnes. Podložka z kusu
   pod prahem se neztenčuje vůbec, i když součet trojúhelníků práh přesáhne (`OrcaSlicer::slice` dostane kus a počet
   kopií, nebo příznak „už ztenčeno / neztenčovat“; vyber čistší cestu, řez jednoho souboru bez kopií se chová jako dnes).
   Mezipaměť kusu tím platí pro přeřez po změně barvy, zbytkovou podložku i díly.
B. **Ztenčení nikdy déle než řez, který šetří.** Časový limit ztenčení = nejvýš očekávaná doba řezu toho, co se ztenčuje
   (`BigModel::estimateSeconds` nebo lepší odhad z měření), a nejvýš `decimate_timeout`; po vypršení řez plné sítě jako
   dnes. Zjisti místně, kolik Orca 2.4 potřebuje na 1 kus 390 k, na podložku 4 × 390 k a na 4 × 350 k (po ztenčení), a
   kolik stojí ztenčení kusu; z toho rozhodni, zda má u podložky s kopiemi smysl snižovat cíl na kus (např. strop
   trojúhelníků na podložku / kopie, nikdy pod limity odchylky). Měřený výchozí poměr z produkce: 1 552 648
   trojúhelníků za 50 s (asi 32 s na milion), `seconds_per_million` 150 je proti tomu 5× pesimistický; nový výchozí
   údaj navrhni s čísly, neměň ho sám bez řídící.
C. **Velký model podle kusu × kopie.** `BigModel::judge` (farma) dostane počet kopií zakázky: odhad `estimateSeconds`
   počítá trojúhelníky × kopie a `slice_s` kalkulace × kopie (řez roste zhruba lineárně, ověř v B); verdikt „velký“
   podle odhadu nad `big_slice_s` nebo kusu nad `decimate_above` jako dnes (součet trojúhelníků sám o sobě důvod není,
   Orca ho zvládá). Cíl: zakázka ze 4. 11. by vznikla jako `awaiting_choices`, zákazník by nejdřív zvolil barvu a řez
   by běžel jednou; jeden malý kus se chová jako dnes. Odhad minut na stránce (`slice_estimate_s`, AL.md §11.1) počítá
   s kopiemi; změna kopií po vzniku zakázky verdikt nepřeklápí (jako dnes), napiš to do docs.
D. **Měření místně** (kus kolem 390 k trojúhelníků: vygenerovaná koule nebo torus, nebo skutečný model z
   `Documents\MODELS`): dnes vs. po opravě, časy `layout`, `decimate_s`, `orca_s`, `prepare_s` pro 1 kus, 4 kusy,
   přeřez po změně barvy (trefa mezipaměti), zbytková podložka. Tabulka do AL.md.
E. **Testy.** MeshDecimatorTest, FarmOrderFlowTest, FarmOnePlateTest, FarmBigModelFlowTest, PlateLayoutFarmTest,
   PlateLayoutTest, OnePlateTowerTest zelené. Nové: podložka 4 × kus pod prahem se neztenčuje (žádné `decimate_s`, nebo
   `decimate_skipped` s důvodem); kus nad prahem se ztenčí jednou a podložka i zbytková podložka vzniknou z ztenčeného
   souboru (druhé volání `cached`); `judge` se 4 kopiemi dá odhad × 4 a verdikt velký, s 1 kopií jako dnes. `pint`.
   `farm.ts` neměň (odhad minut přichází ze serveru); kdyby to bylo nutné, napiš řídící. Nikdy dvě sady PHPUnit najednou.
F. **Docs.** AL.md nový pododdíl §11.3 (kus × kopie, časový limit, měření) a poznámka v §10.11, řádek TODO 68 (řídící ho
   založila, doplň stav), odchylky od zadání sem dolů.

## Pravidla

- Jen tvoje větev a worktree. `farm.ts`, Blade zakázky, `lang`, `OrderController`, `OrderService` mimo řádek `judge`,
  `GcodeSlot.php`, `agent/` neměň. Ceny ani profily řezu neměň.
- Texty (kdyby nějaké byly) jen přes `lang/src/<název>.json` + `python scripts/lang_add.py`; validuj každý JSON. Git add
  jmenovitě, žádný stash. Commit končí `Co-Authored-By: Claude <model session> <noreply@anthropic.com>`.
- Produkční databázi ani server nečti. Žádný `php artisan migrate*` v žádném worktree.

## Odchylky session 2 (5. 11. 2026, fix/decimate-copies, docs/AL.md §11.9)

1. Pododdíl je **§11.9**, ne §11.3: §11.3 („Nový stav všude, kde na stavu záleží“) v AL.md už existuje, §11 má 11.1–11.8.
2. Ztenčení kusu dělá `PrepareFarmOrder` **i u 1 kusu** (ne jen před `replicate`): slicer dostává z farmy vždy
   `withoutThinning()`, aby mezipaměť a `timings` byly jedny; navenek stejné chování jako dosud (stejný klíč mezipaměti –
   bajty `print.stl`, slicer dřív ztenčoval tentýž soubor). `print.thin.stl` se po běhu maže, `print.stl` zůstává plný.
3. Vypršení rozpočtu ztenčení (`timeout`) se **pamatuje** v `decimate_cache` jako `deviation`. Zadání: „po vypršení řez
   plné sítě jako dnes“ – to platí; navíc se stejný soubor podruhé nezkouší, protože na produkci by jinak každý přeřez
   znovu čekal celý rozpočet. Pád skriptu (`error`) se dál nepamatuje.
4. Rozpočet používá `BigModel::estimateSeconds` včetně spodní hranice 120 s (zadání připouštělo „lepší odhad z měření“):
   z měření plyne, že poměr s/M závisí na tvaru, podpěrách a délce G‑kódu víc než na trojúhelnících, proto jsem výchozí
   čísla neměnil a jen navrhl (`seconds_per_million` 60, `decimate_above` kandidát 1 M).
5. Poznámka v historii zakázky u důvodu `copies` neříká počet kusů (skládá ji řádek `OrderService` 147, který nebyl ve
   výjimce); `BigModel::judge` vrací `copies` i `reason = copies`, text může doplnit řídící nebo #69.
6. Cíl na kus u podložky s kopiemi (strop trojúhelníků na podložku / kopie) **nezaveden**: Orca čas trojúhelníky skoro
   neurčují, ztenčení podložky je čistá ztráta (tabulka v §11.9).
7. Modely k měření: generovaná polokoule Ø 80 mm bez převisů (aby čas Orcy neurčovaly podpěry), 387 200 a 441 800
   trojúhelníků; v `Documents\MODELS` žádný STL kolem 390 k není (3MF a STEP). Měření místně bez `CachedSlicer` (každý
   řádek opravdu řeže) a bez souběhu s jinou prací. Přeřez jinou barvou u velké zakázky měřicí skript napoprvé nezapsal
   (seed farmy má v S1 jedinou barvu, „jiná cívka“ neexistovala); doměřeno zvlášť s druhou cívkou PLA v S1: 388 k
   16,6 s (Orca 7,8, nic ke ztenčení), 442 k 15,2 s (ztenčení kusu z paměti 0,02 s, Orca 7,3). Řádek „dnes“ u 388 k
   byl proto přeřez touž cívkou (trefa podložky 0,05 s) – v tabulce §11.9 označeno.
8. Harness měření a modely nejsou v repozitáři (scratchpad session 2); postup: PHPUnit test mimo `tests/` s navázaným
   `OrcaSlicer` na portable `orca-slicer.exe`, `CachedPrintPreparer(PythonPrintPreparer)`, výsledky `results.jsonl`.
