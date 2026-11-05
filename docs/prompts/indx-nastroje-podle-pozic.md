# INDX: nástroje podle pozic, věž pryč, výměny ve výšce na měniči, kontrola tisku s 8 barvami (TODO #70)

Pro session 2, 5. 11. 2026 večer (Roman: „zadej, zítra budeme zkoušet barevný tisk s 8 barvami“). Worktree
`C:\matplace-gcode-wt`, nová větev `feature/indx-tools-by-position` z `origin/main` (22b0f271 nebo novější; main už má
`OrcaSlicer::binFor`, dnešní opravy, #68 i #69). Po pushi napiš řídící. Nic nenasazuj, žádný `migrate*` ve worktree,
klíče a config agenta nech být. **Priorita: body A, B a C do rána 6. 11.**, zbytek až po nich.

## Stav

- Core One U41 (`core-one-01`, ovladač `prusalink`, `machine_coreone_indx8t.json`, 8 pozic „Nástroj 1 až 8“, dnes PLA+ bílá
  v nástroji 1 a černá v nástroji 2, texturovaná PEI). Orca 2.5 jen pro měnič (`OrcaSlicer::binFor`, `ORCA_BIN_TOOLCHANGER`),
  ostatní stroje 2.4. Agent dostává u měniče originál řezu (`AgentController::gcode`, `isToolChanger()` → `$source`).
  Dnes ověřeno živě: jednobarevný tisk (zkušební objekt) a dvoubarevný přívěsek po dílech na jedné podložce (plate.3mf se
  dvěma filamenty, T0/T1, 3 výměny, čisticí věž navíc, 22 min). AM.md §12 (hlavně 12d, 12e, 12g).
- Jedna podložka s více filamenty: `app/Jobs/PrepareFarmOrder.php` `prepareOnePlate` (asi 688 až 1015): seznam `$filaments`
  (slot_id, color_id, profile, colour) v pořadí dílů nebo skupin barev, `$filamentOf[$i]` = index filamentu dílu,
  `ThreeMfWriter::write(..., 'extruder' => $filamentOf[$i] + 1)` (`app/Engines/Mesh/ThreeMfWriter.php`), `OrcaSlicer`
  `--load-filaments` ve stejném pořadí, Orca píše `T(index − 1)`. **Filament i jde tedy vždy na nástroj i bez ohledu na to,
  ve které pozici cívka opravdu je.** Dnes to vyšlo jen proto, že bílá je v nástroji 1 a černá v nástroji 2. Gramy po
  filamentech: `GcodeStats::fromFile` `filament_grams` (seznam v pořadí filamentů) → díly (asi 993), `slice_params.filaments`,
  `FarmOrder::filamentSlots()` (520), předání správci `FarmOrderController::gcode`, `spoolLines`.
- `PrepareFarmOrder::ONE_PLATE_PROCESS` (635): `enable_prime_tower 1`, šířka 25, proplach do výplně; `TOWER` (641) rezervuje
  místo věže v rozložení podložky. Psáno pro měniče cívek (ACE); INDX čistí na stanici, věž je zbytečná (§12e: dvě kostky
  18 min bez věže, 22,5 min s věží).
- Výměny ve výšce (deska s nápisem, vystouplý text): `GcodeSlot::fileFor($source, slot, temps, changes)` vkládá výměny až při
  předání agentovi u strojů s ACE; měnič dostane originál → nápis by se vytiskl jednou barvou. Stránka zakázky přitom druhou
  barvu pro Core One nabízí (karty z #69).
- Nástěnka: `fan_hotend: 15151` (PrusaLink dává otáčky, karta čeká procenta), hláška startu „slot 1 not checked, the printer
  does not report its slots“ (ovladač `agent/farm_agent/drivers/prusalink.py`, agent 0.2.11; session 4 neběží, smíš do něj,
  verze 0.2.12), „Poslední příkaz“ anglicky.
- Profily INDX: `filament_pla_`, `filament_petg_`, `filament_prusament_pla_`, `filament_prusament_petg_coreone_indx8t.json`
  (odvozeno `scripts/orca_flatten_profile.py`, §12e); ASA, TPU a další druhy farmy pro INDX chybí (`machineVariant` pak vezme
  sdílený profil S1, což Orca 2.5 vezme, ale teploty a chlazení nejsou pro Prusu).
- Limity: `ColorLimit::of(Core One)` = 8 pozic, nahrání malovaného 3MF slučuje až nad `ColorLimit::farm()` = 8 (#66).
- Lokální Orca 2.5 portable (jen spouštět, nic tam nepsat):
  `C:\Users\roman\AppData\Local\Temp\claude\c--Users-roman-Documents-MatPlace-conceprt19092026\ac77c450-d812-4639-8c0c-b3457b294a63\scratchpad\orca25\orca-slicer.exe`;
  Orca 2.4 portable ve scratchpadu session 2 (`59282dac…\scratchpad\orca24`). CLI jako farma: `--load-settings
  machine;process --load-filaments f1;f2;… --arrange 0 --slice 0 --outputdir out plate.3mf`.

## Co udělat

A. **Nástroje podle pozic (do rána).** U měniče nástrojů (`FarmPrinter::isToolChanger()`) musí být seznam filamentů řezu
   v pořadí nástrojů: index filamentu = pozice cívky (slot 0 → filament 1 → T0 … slot 7 → T7), díl nebo skupina barev dostane
   `extruder` = pozice zvolené cívky + 1. Nástroje bez cívky zakázky dostanou zástupný profil (profil hlavní cívky s její barvou),
   protože Orca potřebuje N filamentů, aby šel oslovit extruder N. Ověř v G-kódu 2.5, že nepoužité nástroje nic nenahřívají ani
   nečistí (`M104/M109` jen u použitých, start Prusy pracuje s použitými extrudery); když ano, najdi nastavení. Gramy z
   `filament_grams` mapuj zpět podle pozic (mezery přeskoč). `slice_params.filaments`, `filamentSlots()`, předání správci,
   maily a `spoolLines` musí sedět. Stroje s ACE beze změny (`GcodeSlot::fileForMany` tam přemapovává slot).
   Zkouška lokálně Orcou 2.5: podložka se 3 díly na nástrojích 1, 2 a 5 (mezera) → v G-kódu jen `T0`, `T1`, `T4`, `; filament used
   [g]` má 5 položek, mezery nula; a 8 dílů na 8 nástrojích → `T0` až `T7`, `GcodeStats` přečte 8 položek.
B. **Věž u měniče pryč.** Pro měnič přepisy procesu jedné podložky `enable_prime_tower 0` (proplach do výplně nevadí), rozložení
   podložky pro měnič místo věže nerezervuje (`TOWER`, kapacita kopií). Ověř lokálním řezem (žádný `;TYPE:Prime tower`), zapiš
   rozdíl času a gramů.
C. **Předletová kontrola tisku s 8 barvami.** Projdi lokálně, kam až to jde: návrh nebo malovaný 3MF s 8 barvami → nahrání
   nechá 8 skupin (farma s Core One), stránka zakázky nabídne karty a 8 pozic, jedna podložka s 8 filamenty projde Orcou 2.5
   (`T0` až `T7`, 8 položek gramů), nikde nezbyl strop 4 nebo 7 (`MAX_COLORS`, `max_colors_ceiling` 16 nech). Sepiš kontrolní
   seznam pro Romana: co má být na tiskárně (8 cívek PLA stejného typu i na displeji, pozice v administraci v pořadí nástrojů,
   podložka prázdná a potvrzená) a co sledovat (první výměna, dialog mapování, čas).
D. **Výměny ve výšce na měniči.** Najdi nejjednodušší správnou cestu: (1) vložit výměnu do G-kódu 2.5 při předání jako u ACE,
   ale s blokem výměny nástroje stroje (čištění na stanici), ne holé `T1`; (2) nabídnout výměnu ve výšce jako projekt s více
   filamenty (Orca umí změnu filamentu ve vrstvě v procesu); (3) když nic z toho nejde v rámci session, stránka zakázky nesmí
   u měniče druhou barvu ve výšce nabízet (karta Core One se u výměn schová) a docs to řeknou. Rozhodni, udělej, zapiš.
E. **Kosmetika nástěnky**: ventilátor v procentech (nebo otáčky s jednotkou), hláška startu a „Poslední příkaz“ česky přes
   `lang/src`, případně úprava ovladače (agent 0.2.12, pytest). Jen když to nestojí víc než hodinu.
F. **Profily pro INDX**: odvoď `orca_flatten_profile.py` z Orcy 2.5 (Prusa CORE One HF + šablona INDX) druhy, které farma má
   (ASA, TPU, PLA Silk, PLA matný, PLA třpytivý…: podle `filament_*.json` a druhů v `FarmSeeder`), označ jako neověřené; Roman
   ráno řekne, co do INDX dá. Všechny JSON validuj (načtení + duplicitní klíče).
G. **Testy a docs.** FarmOnePlate (pořadí u měniče, mezery), GcodeStats s 8 položkami, FarmColorsPerMachine, FarmColorTiles,
   FarmDecimateCopies zelené; **každá změna v `OrcaSlicer::slice` nebo `prepareOnePlate` musí mít test, který řez opravdu spustí
   s falešnou binárkou** (vzor `tests/Feature/OrcaBinToolchangerTest.php::test_the_slice_itself_runs_the_binary_it_chose`;
   dnešní chyba „Undefined variable $bin“ prošla testy s falešným slicerem a položila produkci) **a skutečný lokální řez Orcou
   2.5** (příkaz a hlavičku G-kódu do zprávy). pint, tsc a build jen při změně TS/Blade. `docs/AM.md` nový oddíl §12j, řádek
   TODO 70 (řídící ho založila), odchylky dolů do tohoto zadání.

## Pravidla

- Jen tvoje větev a worktree. `agent/` smíš jen v ovladači prusalink a verzi; `config.yaml`, klíče, `.env` nikdy. Ceny neměň.
- Texty jen přes `lang/src/<název>.json` + `python scripts/lang_add.py`; validuj JSON. Git add jmenovitě, žádný stash. Commit končí
  `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. Push jen `git push -u origin feature/indx-tools-by-position`.
- Produkční databázi ani server nečti, nic nenasazuj, na tiskárnu nic neposílej. Nikdy dvě sady PHPUnit najednou. Python s českým
  textem do souboru, ne heredoc. Nečekej na nikoho; co nejde, zapiš do odchylek a pokračuj. Zpráva česky: SHA, mechanismus,
  výsledky testů, příkazy a hlavičky lokálních řezů, kontrolní seznam pro Romana, odchylky, co potřebuje řídící.

## Odchylky session 2 (6. 11. 2026, feature/indx-tools-by-position, docs/AM.md §12j)

- A platí i pro tisk jednoho kusu a pro díly po dílech, ne jen pro jednu podložku: na měniči jde každý řez jako projekt
  (`toolChangerParams`), aby cívka v nástroji 3 tiskla z nástroje 3 (dosud vždy `T0`). Jednobarevný tisk z nástroje 1 jde touž
  cestou (projekt s jedním filamentem), aby záznam `slice_params.tool` a porovnání při platbě byly jednotné.
- D: zvolena varianta 2 (Orcina výměna nástroje ve vrstvě přes `Metadata/custom_gcode_per_layer.xml`), ověřená skutečným řezem;
  varianta 1 zamítnuta (start stroje nahřívá a kalibruje jen nástroje použité v řezu); varianta 3 nebyla potřeba, karta Core One
  u výměn zůstává. Výška výměny od první vrstvy se středem nad změnou (jako `GcodeSlot::colorChanges`), tedy `top_z = z + vrstva/2`.
- `OrderFlow::pay` nově řeže znovu i podle nástrojů (`sameToolPlan`); zakázka na měniči řezaná před touto větví se při platbě
  rozřeže jednou navíc (nemá `slice_params.tool`).
- Gramy: Orca 2.5 píše položku pro každý načtený filament (mezery 0.00), mapování zpět je tedy přímo indexem; žádné „zhuštěné“
  mapování nebylo potřeba. `OrcaSlicer::slice` kontroluje hlavičku proti počtu použitých filamentů.
- E: „Poslední příkaz“ byl česky už dřív; anglicky byla poznámka agenta – překládá se na nástěnce (`FarmCommand::resultText`), v
  databázi zůstává anglicky. Ventilátory v otáčkách s jednotkou v klíči (`fan_hotend_rpm`), ne v procentech (PrusaLink
  maximum neudává). `agent.py` netknutý, zip 0.2.12 pro PC farmy nedělán.
- F: ASA a TPU pro INDX Prusa nemá, odvozeno syntetickým presetem (HF 0.4 / CORE One + šablona INDX); ABS a PLA Silk zploštěny
  z Prusiných INDX presetů. Texturovaná podložka u ASA 105 °C (vendor soubor Prusy říká 45), Z‑smrštění ABS 100 % (Orca
  odmítá 0,22 %). Vše neověřené na tiskárně, označené v `filament_notes`. `@System` presety staženy z GitHubu (tag v2.5.0-alpha).
- Kalkulačka (malované 3MF, `CalculationController`) stroj nezná a počítá podložky dál s pásem věže; neměněno.
- Testy jsem pouštěl v jedné sadě po skupinách (nikdy dvě najednou); `npm run build` kvůli změně Blade, TS netknut.
