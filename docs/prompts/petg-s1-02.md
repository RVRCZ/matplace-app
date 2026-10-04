# Zadání: ladění PETG na tiskárně Kobra S1 #2 (`kobra-s1-02`)

Datum: 4. 10. 2026. Pracuj v repozitáři `C:\matplace-app` (Laravel 12) ve **vlastním worktree**
(`git worktree add C:\matplace-petg-wt feature/petg-tuning`, větev z `main`; `main` je nasazený na matplace.com).
Nikdy `git add -A`, nikdy `git stash`. Většina práce je **provozní** (administrace farmy + tiskárna), kód se mění jen
tam, kde ladění ukáže, že poradce nebo profily PETG neumějí (viz část C). Na server se připojuj jen pro čtení
(`ssh -i ~/.ssh/hetzner_matplace root@178.104.162.164`, `/var/www/matplace-app`); nasazuje Roman po schválení
(postup `docs/DEPLOY-BETA.md`).

## Cíl

Tiskárna **S1 #2** (id 3, klíč `kobra-s1-02`, Anycubic Kobra S1 Combo s Rinkhals, tryska 0,4 mm, podložka v profilu
„High Temp Plate“, v ACE slot 0 = PETG červená) má mít **vyladěný profil PETG** (řádek ladění **#27**, dnes
`testing` v3) tak, aby zákaznické tisky z PETG vycházely bez stringingu, s čistými převisy do ~50°, pevnými mosty,
rozměrově v toleranci a bez odlepení – a aby to bylo **zapsané** (řádek „vyladěno“, knihovna profilů, pravidla
poradce), ne jen jednou vytištěné.

## Stav teď (4. 10. 2026 odpoledne)

- Na S1 #2 **právě běží** zkušební tisk **T26-000028** (objekt `quick`, PETG červená, kandidát = hodnoty řádku v3:
  `outer_wall_speed 60`, `bridge_speed 30`, ventilátor 30/60 %, převisy 80 %, `slow_down_min_speed 15`,
  `filament_max_volumetric_speed 10`, retrakce 1,2 mm, z‑hop 0,4). Začni jeho vyhodnocením.
- Řádek #27 vznikl z knihovny (`database/data/farm_profile_library.json`, „kobra s1“ → PETG: tryska 240/245 °C,
  podložka 75 °C + výše uvedené). Základní profil `engines/orca/profiles/filament_petg.json` (Anycubic PETG
  @ Kobra S1: 230 °C, podložka 75, fan 30–90, flow 0,96, PA 0,04, 12 mm³/s). Historie verzí je v `history`
  řádku a na stránce `/admin/farm/tuning/27`.
- Dřívější testy na S1 #2: T26-000021, 22, 25 (PLA, hodnocené), T26-000026 selhal. Testy PETG před T26-000028 nebyly.
- Poradce `App\Domain\Farm\TuningAdvisor` má pravidla psaná pro PLA (základ `fan 100 %`, retrakce 0,8…) – u PETG
  může navrhnout nesmysl (ventilátor na 100 %, příliš dlouhá retrakce). Tohle je část C.

## Jak ladění ve farmě funguje (přečti si kód, tady je mapa)

- `/admin/farm/tuning` – mřížka tiskárna × materiál; `/admin/farm/tuning/{row}` – řádek: **účinný profil**
  (knihovna → řádek → přepisy tiskárny), formulář přepisů (uložení = nová verze), **Vytisknout test** (slot,
  objekt `quick` 35 min / `detailed` 90 min / `ironing` / `temp_tower` s žebříkem `floors/start/step`, volitelně
  teploty a JSON přepisy `t_process`/`t_filament` jen pro ten test), seznam testů s fotkami a hodnocením.
- Test = `FarmOrder` kind `test` (`T26-…`), bez ceny, `TestPrintService::create` → `calib_tool.py` (quick: kostka
  15 mm, převisy 30–70°, most 20 mm, 3 pilíře na stringing, stěna 0,8, díra Ø8; tower: patra po 10 mm s mostem
  a 45° převisem, teploty přes `TowerGcode`), slicuje se s podpěrami vypnutými, jde rovnou do fronty; spouští se
  po „Podložka je volná“ v `/admin/farm`.
- Po dotisknutí: **foto-box** (`/admin/farm/photobox`, 3 kamery, nebo nahrání fotek z mobilu u testu na stránce
  řádku) → **Vyhodnotit z fotek (AI)** (`TestPhotoJudge`, claude-opus-5, ~1–1,5 USD, 2–3 min) předvyplní formulář
  hodnocení (stringing 0–3, overhang_ok 30–70, bridge, elephant, corners, top, wall, bond, warp, rozměry kostky
  a díry, u toweru best_floor) → **1 Uložit hodnocení** (uloží `test_params.result` a návrh poradce `advice`) →
  **2 Zkusit navržené úpravy** (nová verze řádku, stav `testing`) nebo **3 Test je dobrý – označit jako vyladěné**
  (`adopt`: řádek převezme hodnoty testu, stav `tuned`, z `overhang_ok`/`bridge` vzniknou
  `support_threshold_angle`/`max_bridge_length`). Poradce: `TuningAdvisor::advise()` – pravidla v docblocku.
- Jak se profil dostane k zákazníkovi: `PrepareFarmOrder` skládá `layer_height + řádek->process + tiskárna->process_overrides`
  a filament z řádku; `GcodeSlot` zapíše teploty a `T<slot>`; `Dispatcher` vybírá stroj podle podložky a materiálu.
  Sloty: `/admin/farm/printers` → cívky (slot 0 PETG červená, ostatní prázdné).
- Sušení v ACE: tlačítka *Sušit* / *Zastavit sušení* u tiskárny v adminu (`MMU_DRYER_START`), stav `dryer` v telemetrii.
- Testy kódu: `tests/Feature/FarmTuningTest.php`, `tests/Unit/TuningAdvisorTest.php`, `FarmNozzleTest`; celá sada
  `php -d memory_limit=2G vendor/bin/phpunit` (~16 min, na pozadí, nikdy dvě najednou); `vendor/bin/pint --dirty`.

## Co udělat

**A. Vyhodnotit T26-000028 a vést ladicí smyčku.** Před každým krokem napiš Romanovi, co má fyzicky udělat
(vyfotit v foto-boxu, vyměnit/usušit cívku, potvrdit podložku, změřit kostku posuvkou – AI měření nenahradí),
a čekej na něj. Pořadí, které dává u PETG smysl:
1. **Sušení**: PETG je hygroskopický; než se cokoli hodnotí, cívka 4–6 h / 65 °C v ACE (pokud test T26-000028
   stringuje a cívka nebyla sušená, je to první podezřelý, ne teplota).
2. **Teplotní věž** (`temp_tower`, start 250, krok −5, 6 pater → 250…225): nejlepší patro = tryska; PETG na S1
   obvykle 235–245 °C. První vrstva o 5 °C víc.
3. **quick** s novou teplotou: stringing (cíl 0–1) → retrakce 1,0–1,5 mm, z‑hop 0,2–0,4, `slow_down_min_speed`;
   převisy (cíl čisté do 50°) → ventilátor **max 60–70 %** (víc u PETG láme vrstvy a kazí lesk), `overhang_fan_speed`
   do 80; mosty → `bridge_speed` 20–30; rohy/boule → `outer_wall_speed` 40–60, `pressure_advance` 0,03–0,06;
   rozměry → `xy_contour_compensation` / `xy_hole_compensation`; sloní noha → `elefant_foot_compensation`,
   podložka 70–80 °C (High Temp Plate; na texturované PEI PETG drží až moc – separátor/lepidlo, jinak trhá povrch).
4. Jedna změna na test, kde to jde; každý test hodnotit stejnou metodou (AI + člověk), zapsat.
5. **detailed** jako potvrzení (90 min), pak **3 – označit jako vyladěné**. Vyladěný řádek má mít i
   `support_threshold_angle`/`max_bridge_length` z hodnocení.
6. Ověřit, že `time_factor` 1,07 S1 pro PETG sedí (skutečný čas vs. odhad u testů; PETG tiskne pomaleji) a
   případně navrhnout hodnotu per materiál (dnes je per tiskárna).

**B. Zapsat výsledek, aby platil i jinde.** Vyladěné hodnoty PETG pro Kobra S1 do
`database/data/farm_profile_library.json` („kobra s1“ → PETG), aby je S1 #1 (a budoucí S1) dostala přes
`ProfileLibrary::startingValues` – jen hodnoty, které se liší od profilu; kind‑teploty na řádek nepatří (viz
`ProfileLibrary`). `docs/N.md`: co se měřilo, verze řádku, konečné hodnoty, fotky testů (čísla T26‑…).

**C. Poradce a profily pro PETG (kód).** Podle toho, co A ukáže:
- `TuningAdvisor`: meze per materiál (PETG: ventilátor nejvýš 70 %, retrakce 0,8–1,5 na direct drive, objemová
  rychlost ≤ 12, teploty 225–255; PLA beze změny). Základ `BASE` ať bere skutečný filament profil materiálu, ne
  PLA konstanty. Test `TuningAdvisorTest`: PETG test se stringingem 2 → návrh nejde pod 225 °C ani nad 70 % fanu.
- `filament_petg.json` / `filament_petg_kobra3max.json`: jen když se ukáže, že základ je špatně pro všechny S1
  (ne kvůli jedné cívce); změnu profilu doložit slicem (stejný model před/po: gramy, minuty).
- Nic z PLA/PLA+ a z Kobra 3 Max se nesmí změnit (porovnej `sliceFingerprint` řádků ostatních materiálů před/po).

## Pravidla

- Tisk startuje jen Roman („Podložka je volná“); ty připravuješ testy a hodnocení. Nenech frontu plnou testů –
  vždy jeden rozdělaný na S1 #2; zákaznické zakázky mají přednost (sleduj `/admin/farm`).
- Hodnocení AI je předvyplnění, ne rozhodnutí: rozměry a stringing potvrzuje člověk; co nejde posoudit, nech prázdné.
- Žádné tajné hodnoty do chatu; `.env` serveru jen číst.
- Každá změna kódu s testem, Pint, dokumentace do `docs/N.md`; nasazení přes Romana.

## Hotovo, když

Řádek #27 je `tuned` s hodnotami potvrzenými `detailed` testem (stringing ≤ 1, převisy čisté ≥ 50°, most 20 mm
ok, kostka ±0,15 mm, díra ±0,2 mm, bez odlepení), knihovna profilů má PETG pro Kobra S1 z těchto hodnot, poradce
nenavrhuje PETG nic mimo meze, testy zelené, `docs/N.md` má průběh a čísla.
