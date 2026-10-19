# Zadání pro session 2 (+ session 4): jedna podložka, dva až čtyři filamenty (vložený motiv, vícebarevné díly)

Nález 19. 10. 2026 (session 1 u medaile pro vítěze): farma umí buď **změnu barvy po výšce** (`GcodeSlot` vloží
`T<slot>` za značku vrstvy, měnič vymění cívku jednou), nebo **tisk po dílech** (`PrepareFarmOrder`: každý díl na
vlastní podložce, `part-<n>.stl` + `part-<n>.gcode`, jedna cívka na díl). Neumí **dva filamenty v jedné podložce**
(motiv vložený do desky ve stejných vrstvách, vícebarevná klíčenka, logo v jiné barvě na stejné výšce). Přitom
tiskárny to umí: Kobra S1 + ACE (Rinkhals, `T0`–`T3` = pozice ACE, výměna za běhu) i Kobra X (Rack + ACE, `ams_box_mapping`
pro každou barvu projektu). Medaile lícem dolů s vloženou jedničkou se proto odkládá (`inlay` vypnutý), dokud tohle
nebude.

## 0. Kde pracuješ a pravidla

- Session 2: větev **`feature/multi-filament-plate`** z aktuálního `main`, dokument **`docs/AL.md` §10**.
  Soubory: `app/Jobs/PrepareFarmOrder.php`, `app/Domain/Farm/{OrderService,GcodeSlot,PrintProfile,Dispatcher}.php`,
  `app/Engines/DTO/SliceParams.php` + slicer (`app/Engines/Orca/*`), `app/Domain/Tools/ModelEditor.php` jen pro
  zápis 3MF s díly (už umí `colors.3mf`), `resources/js/calc/farm.ts` (výběr cívky na díl už je, jen stejná
  tiskárna), `tests/Feature/Farm*Test.php`, `tests/Unit/GcodeSlotTest.php`. Nesahat: agent (session 4), nástroje
  (session 1), kalkulace mimo cenu výměn.
- Session 4: větev **`feature/agent-multi-mapping`** z `main`, `docs/AM.md` §8: ovladač `anycubic_lan` pošle
  `ams_box_mapping` pro **všechny** barvy projektu (`paint_index` 0..n‑1 → pozice z hlavičky, kterou napíše GcodeSlot,
  viz bod 3), `paint_info`/`statistics` pro více filamentů v balíčku `.gcode.3mf`; Moonraker nic (T<n> = pozice).
  Testy s falešnou tiskárnou; skutečný tisk až po Romanově svolení v okně session 4 (malý dvoubarevný díl).
- Pravidla jako dosud: `git add` jmenovitě, žádný stash, `pint --dirty`, testy, `tsc`/`build` při změně JS;
  nemergovat, nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Jak to má fungovat (session 2)

1. **Kdy**: návrh nástroje s `multi_material` (díly ve stejných vrstvách: `part_colors` + příznak z enginu, nebo
   3MF s více objekty, např. z nástroje G‑kód → model), nebo zakázka s díly, které se vejdou na jednu podložku a
   zákazník zvolil cívky z **jedné tiskárny**. Jinak dnešní cesty (po dílech / po výšce) beze změny.
2. **Řez**: jeden běh Orcy s jedním 3MF, kde každý díl je objekt s `extruder = n` (1..4), `--load-filaments` s
   profilem filamentu na každý díl (druh podle zvolené cívky, teploty z `PrintProfile`), čisticí věž zapnutá
   (`enable_prime_tower`, umístit tak, aby se vešla; `flush_volumes` z profilu; proplach do výplně `flush_into_infill`
   když Orca umí), jedna podložka → jeden `print.gcode`. `SliceParams`: pole filamentů místo jednoho; výsledek nese
   gramy na filament (hlavička Orca `filament used [g]` po extruderech) a počet výměn.
3. **Pozice cívek**: `GcodeSlot` dnes přepisuje jeden `T<slot>`; nově mapa `T<n>` → slot pro každý extruder
   (1 → slot dílu 1 …), a do hlavičky souboru řádek `; matplace filaments T0=<slot> T1=<slot> …` (agent Kobry X
   z něj udělá `ams_box_mapping`; Rinkhals bere `T<slot>` přímo). Teploty po filamentu (`M104`/`M109` pro každý).
4. **Zakázka**: výběr cívky na díl jen ze slotů **téže tiskárny** (dnes může být jiná tiskárna), dispečer vybere
   stroj, který má všechny zvolené cívky (Kobra X 7 pozic, S1 4); není‑li, nabídnout tisk po dílech. Cena: filament
   po dílech + proplach (gramy z hlavičky) + čas; řádek „výměny barvy: n×“ v rozpisu.
5. **Nástěnka a mail**: zakázka ukazuje cívky všech dílů (odznaky), mail obsluze „tiskne se“ jmenuje všechny cívky.
6. **Pojistky**: víc než 4 filamenty = po dílech; tiskárna bez měniče (ruční) = po dílech; model, kde se díly
   nevejdou na podložku = po dílech (jako dnes).

## 2. Testy a hotovo

Falešný slicer: 3MF se dvěma objekty, G‑kód s `T0`/`T1` → GcodeSlot přemapuje na sloty 2/5 a napíše hlavičku; zakázka
s cívkami ze dvou tiskáren → odmítnuto s hláškou; cena s proplachem; dispečer vybere stroj se všemi cívkami; mail
jmenuje obě cívky. Skutečný řez lokálně (AnycubicSlicerNext/Orca CLI s profily farmy) s medailí `inlay` od session 1
→ čas, gramy, počet výměn do AL.md. **Hotovo =** push obou větví, AL.md §10 + AM.md §8, zpráva řídící session; pak
session 1 zapne `inlay` u medaile.
