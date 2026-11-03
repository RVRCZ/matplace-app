# Příprava zakázky se zasekne v opravě sítě (TODO #64)

Pro session G, 3. 11. 2026. Větev `fix/prepare-repair-hang` z `origin/main` (0653368b nebo novější), worktree
`C:\matplace-guest-wt`. Po pushi napiš řídící session (sha, měření před a po, co je neověřené). Nic nenasazuj,
žádné `php artisan migrate*` v worktree (sdílené `dev.sqlite`), testy Feature běží v `:memory:`.

## Co se stalo

- 1. 11. 2026 ve 22:43 a 22:47 Roman založil dvě zakázky (id 277 a 278, tokeny `PCTZZeKIHf54RDXVzw3X6fb6PzGVQZEu`
  a `IiSQU2oyPOeLbbHYvhSH5MVWr8Sg8795`) z téhož návrhu nástroje o čtyřech dílech (`plate-part-1..4.stl`, pozice
  `keep`). Obě skončily ve stavu `failed`.
- Log serveru: `PrepareFarmOrder failed order 277: farm_tool.py prepare …/plate-part-1.stl …/plate-part-1.stl.prepared
  1 250 250 250 40 keep exceeded the timeout of 300 seconds` (limit v `app/Engines/Farm/PythonPrintPreparer.php:26`).
  Zakázka 278 o 5 s později: `The process has been signaled with signal "15"`. V adresáři obou zakázek zůstal jen
  `plate-part-1.stl.prepared.stage` s textem `repairing`; díly 2 až 4 se nespustily.
- Reprodukce u řídící session (Store Python 3.11 s trimesh 4.11.5, `python -P -E engines/python/farm_tool.py prepare
  <díl 1> <výstup> 1 250 250 250 40 keep`): po 4 minutách zabito, stage `repairing`.
- Síť dílu 1: 62 114 trojúhelníků, po `merge_vertices` 30 116 vrcholů, 1 těleso, **0 otevřených hran, ale není
  vodotěsná**: má hrany sdílené třemi a více plochami (`open_edges()` vrátí `holes == 0`, `joints > 0`).
  `trimesh.repair.fix_normals` i `fill_holes` proběhnou za 0 s a nic nezmění. Podezřelý je proto
  `pymeshfix.MeshFix.repair()` v `repair_tool.fix_body()`, který `farm_tool.repair()` volá přes `repair_tool.mend()`
  (sdílené s nástrojem `/tools/repair`). Neověřeno, změř.
- Kopie dílů: `C:\tmp\order277\plate-part-1.stl` (3 105 784 B), `plate-part-2.stl` (807 284), `plate-part-3.stl`
  (674 684), `plate-part-4.stl` (344 684). Díl 1 je ten zaseknutý.

## Co udělat

A. **Kde to visí.** Změř s dílem 1 krok po kroku (`mend`: degenerované, duplicitní, `split`, `fix_body`: normály,
   díry, pymeshfix). pymeshfix vlastní limit nemá; měř v podprocesu, ať ti nevisí měření. Výsledek do docs.
B. **Oprava v `farm_tool.py prepare`:**
   1. Síť bez otevřených hran (`holes == 0`), jen s nemanifoldními hranami, se pro řez opravovat nemusí, Orca ji
      řeže. `repair()` v tom případě přeskoč a do výsledného JSON zapiš, že síť zůstala neopravená a proč (počet
      `joints`). Ověř, co dnes `.prepared` JSON nese a co z něj čte stránka zakázky, a drž tvar.
   2. Nezávisle na bodu 1 dej opravě časový strop (návrh 60 s, konstanta nahoře v souboru vedle `BED_GAP_MM`):
      opravu pusť v podprocesu (`multiprocessing`, nebo druhý proces téhož skriptu s volbou `repair`) a po stropu
      pokračuj s neopravenou sítí a tímtéž záznamem v JSON. Limit 300 s v `PythonPrintPreparer` zůstává jako
      poslední záchrana.
   3. Tatáž `mend` běží v nástroji `/tools/repair` (`RepairToolTest`). Ověř s dílem 1, jestli nástroj také visí.
      Jestli ano, dej mu týž strop a zákazníkovi hlášku (texty jen přes `lang/src/*.json` + `python scripts/lang_add.py`).
C. **Signál 15 u zakázky 278.** Zjisti, proč python druhé zakázky dostal SIGTERM 5 s po timeoutu první: `$timeout`
   jobu je 900 s a fronta se tehdy nerestartovala (journal). Podezření: Symfony Process při `stop()` po timeoutu
   zabíjí skupinu procesů, nebo `WithoutOverlapping`/`release`. Když je to skupina, oprav (každý python ve vlastní
   skupině, nebo `setsid`); jinak jen zapiš, co jsi zjistil.
D. **Měření po opravě:** díl 1 projde (čas, výška, podpěry, `repaired`), celá zakázka ze čtyř dílů se připraví
   lokálně (postup z AL.md §17, `PYTHON_BIN` z venv). Zkus i skutečný řez neopravené sítě Orcou lokálně (portable
   `orca24` ze scratchpadu session 2 nebo AnycubicSlicerNext), ať víme, že ji slicer přijme a kolik podpěr dá.
E. **Testy:** syntetická síť s nemanifoldní hranou (dvě krychle sdílející plochu, nebo zdvojená plocha) do
   `FarmOrientationTest` nebo nového `FarmPrepareRepairTest`: `prepare` doběhne do pár sekund a vrátí síť
   neopravenou se zdůvodněním. Test stropu s uměle pomalou opravou (proměnná prostředí nebo skrytá volba skriptu,
   ne čekání 60 s v testu). Stávající FarmOrientation, FarmPreparedCache, RepairTool, FarmOrderFlow musí zůstat zelené.
F. **Docs:** `docs/AL.md` §18 (co visí, strop, měření před a po), řádek TODO 64 (řídící ho založila, doplň stav),
   odchylky od zadání připiš sem dolů.
G. Když oprava potřebuje změnu PHP (pole v JSON, stránka zakázky), drž ji malou, texty přes lang.

## Pravidla

- Jen tvoje větev a worktree. Soubory session 2 (`gcode_tool.py`, `decimate_tool.py`) neměň; když je to nutné,
  napiš řídící.
- Validuj každý JSON, který upravíš (`json.load` + kontrola duplicitních klíčů).
- Git add jen jmenovitě, žádný stash. Commit končí `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Produkční databázi ani server nečti, data máš v `C:\tmp\order277`. Po opravě a nasazení Roman zakázky založí
  znovu („Uložit a naslicovat znovu“).

## Odchylky (session G, 3. 11. 2026)

- B1: při `holes == 0` se nepřeskakuje celá oprava, jen pymeshfix. Rychlé kroky (zdvojené plochy, smítka) uzavírají Baby Turtle
  (`RepairToolTest`), která má taky 0 otevřených hran. Neuzavřenou síť bez děr musel pustit i `ModelValidator` (varování
  `non_manifold`); jinak by zakázka místo zaseknutí skončila chybou `not_watertight`.
- B2: strop hlídá jen pymeshfix (ve vlastním procesu), ne celou opravu. Ostatní kroky trvají na dílu 1 dohromady 0,5 s a
  `/tools/repair` si tak nechá, co stihly. Konstanta je `REPAIR_SECONDS` v `farm_tool.py` a `MESHFIX_SECONDS` v `repair_tool.py`;
  pro testy je přepíše `MP_MESHFIX_SECONDS`.
- C: nejde o skupinu procesů, ale nejspíš o cgroup při restartu přes `PartOf=`. Jednotky systemd jsem neměnil, návrh je v `docs/AL.md` §18.
- D: celou zakázku přes `PrepareFarmOrder` jsem lokálně nespouštěl (sdílené `dev.sqlite`, žádný migrate). Engine, slicer a validátor
  jsou ověřené každý zvlášť.
- Commit nese `Co-Authored-By: Claude Opus 5.5`: v této session běží Opus 5.5, ne Fable 5.1.
