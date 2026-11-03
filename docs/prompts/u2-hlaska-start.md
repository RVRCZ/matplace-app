# U2 při každém startu hlásí „Extruder must heat up first“ (TODO #65)

Pro session 4 (agent a G-code farmy), 3. 11. 2026. Worktree `C:\matplace-sell-wt` (stojí na sloučené `feature/agent-messages`),
větev `fix/s1-start-heat` z `origin/main`. Po pushi napiš řídící session (sha, výsledky pokusů, co je neověřené).
Nic nenasazuj, žádné `php artisan migrate*` v worktree (sdílené `dev.sqlite`), na tiskárnách nikdy nic nemaž.

## Co se děje

- U2 (Anycubic Kobra S1 + ACE, Rinkhals, 192.168.8.23) při každém startu zakázky z farmy nejméně od 1. 11. 2026. Konzole
  Moonrakeru (`GET /server/gcode_store`) drží 3 starty a 3× totéž. Dnešní F26-000122 (ASA, 270/100 °C, slot 3):
  21:07:31 start (`leviQ3 auto_zoffset_on_off`), 21:09:56 `FEED_FILAMENT ID=0 INDEX=3 LENGTH=80 SPEED=25`,
  21:14:20 `!! error: typ = WebRequestError, code = 10011715, message = Extruder must heat up first` (displej: 10539
  „nozzle must be heated first. Please check it.“), 21:15:01 druhý `FEED_FILAMENT … INDEX=3`, od té chvíle tisk běží
  normálně. Totéž 3. 11. 20:51 se slotem 1 (PETG) a 1. 11. 13:52 se slotem 1.
- U1 (192.168.8.21) 1. 11. 13:40 `FEED_FILAMENT ID=0 INDEX=0` (PLA 230/55), bez chyby, F26-000116 dotištěna. Obě tiskárny
  mají komponentu `mmu_ace`, oba soubory hlavičku `; processed by MmuAcePatcher` a stejné řádky od nás.
- Náš soubor (`app/Domain/Farm/GcodeSlot.php::retarget`, nahřátí před T tam je od 12. 10. kvůli téže hlášce):
  `G9111 bedTemp=100 extruderTemp=270` (startovní makro Anycubic), `M117`, `M104 S270 ; matplace slot: heat before the
  spool change`, `M109 S270 ; matplace slot: wait for it`, `T3 ; slot chosen by matplace farm`, `G90`, `G21`, `M83`, první
  extruze na řádku 45. Prvních 200 kB souboru: `C:\tmp\u2\F26-000122.head.gcode`.
- První FEED přijde 2,5 min po startu, tedy ještě uvnitř G9111, náš T3 je až za M109. Kdo ten FEED posílá a kdo pak hlásí
  „heat up first“ (MmuAcePatcher, který si z prvního `T` vezme výchozí nástroj? stock makro G9111? `mmu_ace` při našem
  T3?), nevíme. Mezi chybou a druhým FEED je 41 s: buď to firmware zkusil sám, nebo Roman ťukl na displej. Roman to řekne.
- Dnes navíc 20:32 a 20:39 `10011711 filament hub unwind filament timeout` (ACE nevtáhl filament zpět), před startem se
  slotem 1. Fyzická věc, Roman kontroluje dráhu slotu 1; sem jen pro kontext.
- Hlavní důvod zadání: když je po hlášce potřeba ťuknout na displej, U2 bez obsluhy neodstartuje.

Uložená data (3. 11. 2026 ~21:30): `C:\tmp\u2\u2-gcode_store-2026-11-03.json`, `u1-gcode_store-2026-11-03.json`,
`u2-state-2026-11-03.json` (print_stats, extruder, heater_bed, mmu, filament_hub, pause_resume, toolhead, virtual_sdcard),
`u2-objects.json`, `u2-gcode-help.json`. `docs/AM.md` §10a má stejný řádek konzole z 30. 10., §10 popisuje, co Rinkhals
vystavuje; `agent/farm_agent/drivers/moonraker.py` je ovladač (upload `/server/files/upload`, start
`/printer/print/start?filename=`, konzole přes `CONSOLE_COMMAND`).

## Co udělat

A. **Zjistit, kdo hlášku vyvolá.** Zdroje Rinkhalsu (GitHub jbatonnet/Rinkhals: aplikace `mmu`, `mmu_ace.py`,
   `MmuAcePatcher`; jak zpracuje první `T<n>` a co dělá při startu tisku) a logy na U2 (`/tmp/gklib.log`, logy Rinkhalsu
   v `/useremain/rinkhals/…`). SSH na U2 jen po Romanově výslovném souhlasu a s jeho heslem. Bez SSH zkus
   `GET /server/files/list?root=logs` a `GET /server/files/logs/<soubor>` přes Moonraker.
B. **Pokusy na U2**, jen když U2 netiskne a každý tisk nejdřív ohlas Romanovi. Malý objekt (kostka 10 mm, jako
   `kx-cube10.gcode` z AM.md §8). U každé varianty zapiš čas prvního FEED, čas chyby, zda `pause_resume.is_paused` nebo
   `print_stats.state` ukáže pauzu, zda tisk pokračoval sám a po kolika sekundách, co ukázal displej (Roman vyfotí):
   1. dnešní tvar (M104/M109 + `T<n>` za G9111),
   2. `T<n>` před `G9111`,
   3. bez M104/M109, jen `T<n>`,
   4. bez řádku T (slot zvolený na displeji, nebo `FEED_FILAMENT`/mmu API z ovladače před `print/start`),
   5. stejný objekt z Anycubic Slicer Next se zvoleným slotem (referenční chování stock souboru),
   6. teplota: ASA 270 proti PLA 220 (U1 bez chyby tiskla PLA).
C. **Oprava** podle výsledku: nejspíš pořadí řádků v `GcodeSlot::retarget` (a `fileForMany` pro víc filamentů), nebo
   ovladač moonraker (zavedení slotu voláním API před `print/start`, případně automatické `resume`, když tiskárna stojí a
   poslední řádek konzole je právě 10011715). Když jde o chování firmware U2 (verze proti U1), napiš to a navrhni ruční
   krok pro obsluhu.
D. **Testy:** testy GcodeSlot na nové pořadí, pytest ovladače s mockem Moonrakeru na nové volání, `agent/tests` zelené
   (venv: `pip install -r agent/requirements.txt pytest pillow`). PHPUnit sada farmy zelená.
E. **Docs:** `docs/AM.md` §11 (časová osa, kdo hlášku posílá, varianty a výsledky, oprava), řádek TODO 65 (řídící ho
   založila, doplň stav), odchylky od zadání připiš sem dolů.

## Pravidla

- Jen tvoje větev a worktree; soubory sessions 2, 3 a G neměň, když je to nutné, napiš řídící.
- Texty jen přes `lang/src/*.json` + `python scripts/lang_add.py`; validuj každý JSON, který upravíš.
- Git add jen jmenovitě, žádný stash. Commit končí `Co-Authored-By: Claude <model session> <noreply@anthropic.com>`.
- Produkční databázi ani server nečti. Tiskárny: čtení přes Moonraker kdykoli, tisky a SSH jen s Romanem.

## Doplnění 3. 11. 2026 večer (Roman)

Hláška tisk **zastaví** a pokračuje až po potvrzení na displeji; těch 41 s mezi chybou a druhým FEED je Romanovo ťuknutí.
Průběh dnes: na podložce zůstal neúspěšný tisk z PETG (start 20:51 se slotem 1, hláška, tisk stál, ve 21:06 zrušen), Roman
v adminu „uvolnil podložku“, farma poslala ASA (F26-000122, slot 3), hláška znovu, po potvrzení tiskne dál. Varianta B.4
(bez řádku T) a automatické pokračování z ovladače v bodě C mají proto přednost: cílem je start bez člověka u tiskárny.
Zjisti také, jak se tiskárna v té chvíli jeví přes Moonraker (`print_stats.state`, `pause_resume.is_paused`,
`idle_timeout.state`, objekt `mmu`), aby agent 0.2.9 uměl rozpoznat „stojí na hlášce 10011715“ a případně poslat
`RESUME` nebo totéž, co dělá ťuknutí na displeji (co posílá K3SysUi, je vidět v logu Rinkhalsu po Romanově ťuknutí).
