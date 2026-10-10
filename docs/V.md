# V: ladění tiskových parametrů farmy (session F)

Větev `feature/print-tuning` (z `main` 95ea9d6, 10. 10. 2026), zadání `docs/prompts/ladeni-tisku.md`. Roman:
začít částí 5 – šev (scarf joint). Tenhle dokument je průběžný: každý řádek ladění, každé kolo a výsledek sem.

Vlastní soubory session: `TuningAdvisor.php`, `ProfileLibrary.php`, `TestPrintService.php`,
`database/data/farm_profile_library.json`, `engines/orca/profiles/*`, `engines/python/calib_tool.py`,
`tuning*.blade.php`, `FarmTuningController`, testy `FarmTuningTest`, `FarmNozzleTest`, `TuningAdvisorTest`.

## 1. Šev (část 5 zadání)

### 1.1 Proč ne objekt `quick`

Zadání chtělo vytisknout `quick` dvakrát a porovnat „válcovou část kostky / pilířů“. Na `quick` ale šev není na čem
posoudit:

- kostka je hranatá, šev `aligned` jde do ostrého rohu a **podmíněný scarf** (`seam_slope_conditional = 1`,
  `scarf_angle_threshold = 155°`) se na obvodu s ostrým rohem nepoužije vůbec,
- pilíře na stringing mají Ø 4 mm, tedy obvod 12,6 mm – kratší než samotný scarf (20 mm).

Ověřeno řezem (Anycubic Slicer Next 2.0.0.3 z příkazové řádky, naše profily `machine.json` + `process_standard.json`
+ `filament_pla.json`, podložka 250 mm, výplň 15 %): `quick` se scarfem má šikmé pohyby (G1 se Z uvnitř vrstvy)
**jen na třech pilířích**, na kostce, převisech, mostu ani stěně žádný.

### 1.2 Nový zkušební objekt `seam`

![objekt seam](img/tuning-seam-object.png)

`calib_tool.py seam` – jedna destička 116 × 35 × 21 mm, zleva:

| prvek | rozměr | k čemu |
|---|---|---|
| válec | Ø 30 × 20 mm | šev je čára po stěně, nemá se kam schovat |
| hranol se zaoblenými rohy | 20 × 20 × 20, R 6 | rovné plochy, ale žádný ostrý roh – scarf se použije |
| kostka | 15 mm | kontrola: šev zůstává v rohu, rozměr a rohy se scarfem nesmí změnit |
| kužel rozšířený nahoru | Ø 16 → 34,65, sklon 25° | scarf na převisu (0,09 mm na vrstvu ≈ 22 % šířky stěny, pod prahem 40 %) |

V administraci: *Vytisknout test* → objekt **Šev**; zaškrtávátko **Šikmý šev (scarf joint)** přidá k nastavení řádku
`TestPrintService::SCARF` (hodnoty ze zadání); pole *Proces navíc (JSON)* je přebije. Bez zaškrtnutí se tiskne šev
podle řádku. U testu je napsáno, s čím se tiskl („šev: scarf external, délka 20 mm, mezera 15%“ / „bez scarfu“).
Hodnocení testu `seam` se ptá na **šev** (není vidět / slabá linka / zřetelná linka / hrubý), **vadu na švu**
(žádná / boule / díry), rozměry kostky a rohy; otvor, převisy, stringing, most a ostatní pole u něj nejsou.
`TestPhotoJudge` (soubor řídící session) pole švu nezná – hodnocení z fotek AI u testu `seam` nic nepřinese.

Řez téhož objektu dvakrát (místní Anycubic Slicer Next 2.0.0.3, ne serverová Orca – čísla jsou orientační):

| | čas | filament | šikmé pohyby na stěnách (vrstvy 5–15 mm) |
|---|---|---|---|
| dnešní nastavení (`seam_slope_type = none`, `seam_gap = 10%`) | 28 min 9 s | 15,81 g | žádné |
| scarf (`external`, podmíněný, délka 20, 10 kroků, rychlost 100 %, `seam_gap = 15%`, `staggered_inner_seams = 1`) | 30 min 17 s | 15,69 g | válec, zaoblený hranol, kužel: vnější i vnitřní stěna; **kostka žádné** |

Scarf tedy prodlouží tisk oblých dílů o jednotky procent (tady +7,5 %) a hranaté díly nechá být.

### 1.3 Stav tisku

| test | stroj, cívka | nastavení | výsledek | fotky |
|---|---|---|---|---|
| – | – | dnešní (bez scarfu) | čeká na nasazení objektu `seam` | – |
| – | – | scarf | čeká | – |

Co se nedalo: stav řádků, cívek a fronty na matplace.com jsem nečetl (čtení z produkce mi tahle session nepovolila) –
číslo řádku PLA+ na S1 a volný stroj doplní Roman.

### 1.4 Co dál podle výsledku

1. Scarf lepší → *3 · Test je dobrý* u scarf testu: řádek PLA+ na tom stroji dostane hodnoty testu (stav Vyladěno);
   pak do knihovny `kobra s1` → PLA+ (`process`), ať je mají i ostatní S1.
2. Silk (šev je na lesku nejvíc vidět) stejný pár testů; PETG s nižší `scarf_joint_speed`; ASA až po základním ladění.
3. Globálně do `process_standard.json` / `process_fine.json` až po bodu 1 a 2.
4. Když bude výsledek nejasný: scarf mění tři věci najednou (šikmý přechod, mezeru 10 → 15 %, střídání vnitřních
   švů). Třetí tisk se scarfem a `{"seam_gap":"10%","staggered_inner_seams":"0"}` v poli procesu je oddělí.

## 2. Oprava formuláře hodnocení

U testu, který ještě nikdo nehodnotil, měl formulář předvybrané odpovědi **stringing: žádný**, **sloní noha: žádná**
a **převis čistý do: žádný** (`null == 0` je v PHP pravda). Odeslání bez doteku tak zapsalo tři odpovědi, které nikdo
nedal, a z „žádný převis čistý“ poradce navrhne víc chlazení a podpěry pod vším plošším než 60°. Opraveno
(`isset(...)`), test `test_a_test_nobody_has_judged_yet_shows_no_answer_as_chosen`. Starší hodnocení, která mají
`overhang_ok = 0` bez důvodu, stojí za kontrolu.

## 3. Testy

`FarmTuningTest` (+2: test švu od tisku po převzetí do řádku; nevyhodnocený formulář), `FarmNozzleTest` (objekt
`seam` vodotěsný pro trysku 0,4 i 0,2), `TuningAdvisorTest` beze změny. Vzhled stránky v prohlížeči jsem neověřoval,
jen obsah HTML v testech.
