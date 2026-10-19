# AM – ovladač `anycubic_lan` pro agenta farmy 0.2.0 (Kobra X v LAN režimu)

Session 4, 18. 10. 2026, větev `feature/agent-kobra-x` (z `main` 7d46a2d), zadání `docs/prompts/kobra-x-agent.md`.
Nic se nemerguje ani nenasazuje. Server se nemění.

## 1. Co je hotové

- `agent/farm_agent/drivers/anycubic_lan.py` (ovladač, registrovaný jako `anycubic_lan`) a
  `agent/farm_agent/drivers/anycubic_pkg.py` (soubor pro tiskárnu: řádky, které firmware čte, a balíček `.gcode.3mf`).
- Testy: `agent/tests/test_anycubic_lan.py` (15, falešné spojení a falešná adresa pro nahrání, chová se jako skutečná
  Kobra X 18. 10.) a `agent/tests/test_anycubic_pkg.py` (4). Celá sada agenta: 51 testů, všechny projdou
  (`python -m pytest agent/tests`).
- Verze agenta `0.2.0`; `requirements.txt`, `config.example.yaml` (blok Kobra X), `README.md` (oddíl „Kobra X…“,
  tabulka voleb) a `config.py` (komentář).
- **Na skutečné Kobra X ověřeno 18. 10. 2026 (§4):** nahrání, start s naším produkčním G-code (zahřátí podle
  `G9111`, cívka podle `T1` = Rack pozice 2), průběh, zrušení, stav po zrušení, kamera.

## 2. Protokol: co Kobra X dělá

Tiskárna 192.168.8.44 („Anycubic Kobra X“, `modelId` 20030, firmware 2.0.2.2, `ctrlType: lan`).

| Co | Jak |
|---|---|
| handshake | `GET :18910/info` → podepsaný `POST /ctrl` → AES → MQTT/TLS `:9883` (`kobra_connect`, commit 3edba24) |
| stav | `info` query (téma `web/.../info`): `state` `free`/`busy`, `temp`, `project` (běžící úloha) / `last_project`, `features`, `urls` |
| `project.state` při startu | `init` → `checking` → `printing` → `hotbed_heating` / `nozzle_heating` → `extfilbox_filament_feeding` (Rack; z ACE `ace_filament_feeding`) → `nozzle_purging` → `printing` → `auto_leveling` → `nozzle_cooling` → `nozzle_wiping` → tisk; zrušení: `stopping` (pár s, tiskárna `busy`) → `stoped` |
| `last_project` | `filename` bez `.3mf`, `print_time` v minutách, `supplies_usage` v mm, `progress` 1 po dokončení (ovladač dává 100 %) |
| nahrání | `POST urls.fileUploadurl` (`:18910/gcode_upload?s=<token>`), hlavička `X-File-Length`, multipart: pole `filename` (text), pak pole `gcode` (soubor); cokoli jiného → `{"code":19005,"message":"Parsing form fail"}`; odpověď `{"code":200,"data":{"gcode":"<jméno bez .3mf>"}}` |
| start | MQTT `slicer/.../print`, `action start` – **celý tvar, jaký posílá Slicer Next** (§4c): `taskid "-1"`, `url` (zástupná adresa Sliceru), `filename` (bez `.3mf`), `md5`, `filesize`, `filepath null`, `filetype 1`, `project_type 1`, `ams_settings {use_ams, padding1-3, ams_box_mapping [{paint_index, ams_index, paint_color, ams_color, material_type}]}`, `task_settings {auto_leveling, vibration_compensation, flow_calibration, dry_mode, ai_settings, timelapse, drying_settings, model_objects_skip_parts}` |
| odpověď na start | `print/report` `action start`: `state checking` (vzala) nebo `state failed` + `code` (např. 10301 „设备忙,情况未知“ = zařízení zaneprázdněné, situace neznámá); hned za tím `action update`/`init` |
| pauza, pokračování, zrušení | `print` `pause` / `resume` / `stop` (`taskid "-1"`); `stop` ukončí i úlohu jen založenou (`init`) |
| cívky | `multiColorBox` `getInfo` (téma web): box `id −1` = Rack (4 pozice), `id 0` = ACE; `slots[].index` 0–3, `type`, `color`, `status` 5 = zavedeno; `ams_index` = index v Racku, na displeji index + 1 |
| kamera | `urls.rtspUrl` = HTTP-FLV H.264 na `:18088/live/<id>`, bez `video/startCapture`; snímek přes PyAV |
| soubory | `listLocal` Kobra X odmítá (10112); `fileDetails {root: "local", filename}` vrací, co tiskárna ze souboru přečetla (`paint_infos`, `model_dimensions`, `png_image`, čas, vrstvy) |

## 3. Jak ovladač pracuje

- Spojení ve vlastním vlákně (paho) přes `KobraLink`; hlášení z tiskárny si pamatuje i s historií, protože na start
  přijdou dvě hlášení během milisekundy (odmítnutí, pak `update`/`init`). Znovupřipojení každých 30 s a při dotazu
  bez spojení, pod zámkem.
- **Cloudový režim** (`ctrlType: cloud`): `status()` = `offline` s `telemetry.link = "cloud_mode"`, příkazy hlásí
  „the printer is not in LAN mode“.
- **Stav:** `project.state` podle tabulky výše; `pause` 1/2 = pauza; `finished` = hotovo, `stoped` = zrušeno,
  `failed`/`error` = chyba s `err_msg`; neznámý stav = tiskne. Úloha `init` na volné tiskárně (založená, nikdy
  nespuštěná) je idle. Skončenou úlohu (dotištěnou, zrušenou, selhanou) si ovladač pamatuje z hlášení tiskárny
  (§6d), protože v `last_project` se neobjeví. Telemetrie `layer`, `remain_min` jako u Kobra S1, při tisku `spool`
  a `slot` (zavedená pozice). Změna stavu z MQTT probudí agenta hned.
- **Start:** (1) tiskárna musí být idle; (2) založenou nespuštěnou úlohu ukončí `print/stop`; (3) přečte G-code:
  `T<n>` od GcodeSlot říká pozici cívky, barvu a materiál zjistí z Racku (`multiColorBox`), jinak z konfigurace Orcy;
  (4) soubor nahraje jako `.gcode.3mf` (`package: true`, výchozí) nebo jako holý G-code (`package: false`), v obou
  případech s `T0` = barva 0 a řádky `paint_info`/`statistics` (§4c); (5) pošle start v plném tvaru Sliceru
  s přiřazením barva 0 → `ams_index` = pozice; (6) čte odpověď: při 10301 start zopakuje (3×, po 5 s), jiný kód ohlásí;
  (7) čeká, až tiskárna skutečně tiskne (`busy` a ne `init`), nejvýš `start_wait` (120 s).
- `head()` = `None` (poloha hlavy v LAN protokolu není, časosběr po minutách). `dry()` výchozí chyba. `reset()`
  nic nevyčistí, chybu potvrdí obsluha na displeji. Světlo jen s `light: true` (neověřeno). Soubory v tiskárně
  ovladač nikdy nemaže.

## 4. Zkoušky na skutečné tiskárně 18. 10. 2026

### 4a. Čtení (dopoledne)

Handshake, stav, teploty, kamera (73 kB), `multiColorBox`, `listLocal` (10112). Nic se neposílalo.

### 4b. Nahrání: tvar formuláře (12:27)

Roman připojil Kobra X ve Sliceru Next (záložka „LAN Printer“; síť byla ve Windows „veřejná“, proto Slicer tiskárnu
neviděl) a poslal kostku; `pktmon` (`scratchpad/kx_capture.ps1`, dekódování `kx_decode.py`) zachytil
`POST /gcode_upload` s hlavičkou `X-File-Length`, polem `filename` a polem `gcode` se souborem `.gcode.3mf`
(tabulka v §2). Do té doby tiskárna všechny moje tvary odmítala (19005).

### 4c. Start: co firmware potřebuje (12:48–13:49)

Odposlech na brokeru tiskárny (`scratchpad/kx_sniff.py`, jen odběr `anycubic/#`) zachytil start ze Sliceru i hlášení
tiskárny. Pokusy, všechny na kostce 10 mm (Romanovo svolení přímo v okně session), po každém `print/stop`
a kontrola, že tiskárna je `free`:

| čas | soubor | příkaz | výsledek |
|---|---|---|---|
| 12:48, 12:58, 13:06 | náš holý `.gcode` (`T1`) | strohý (bez `url`/`md5`/`filesize`, bez/s přiřazením) | 10301, úloha zůstala v `init` |
| 13:22 | náš `.gcode` + řádek `; paint_info` | strohý | 10301; hlášení `update` mělo v `code` smetí |
| 13:35 (E1) | balíček ze Sliceru (jeho kostka) jako `mptest-slicer.gcode.3mf` | **plný tvar Sliceru** | **vzala**: 220/60 °C, zavedení z Racku pozice 2 (`channel_no 2`, `loaded_slot 1`) |
| 13:38 (E1m) | tentýž balíček | strohý | **10301** → rozhoduje tvar příkazu |
| 13:43 (E2') | náš holý `.gcode` s `T0`, `paint_info` a `statistics` (`anycubic_pkg.prepared_gcode`) | plný | **vzala**: podložka 55, tryska 225 (naše `G9111`), Rack pozice 2, `nozzle_purging`, `printing`, `auto_leveling`, `nozzle_cooling` 180, `nozzle_wiping`; `stop` → `stoped` → `free` |
| 13:47 (E3) | náš balíček `.gcode.3mf` (`anycubic_pkg.package`) | plný | **vzala**, stejný průběh |

Závěry:
- **Příkaz:** bez `url`, `md5` a `filesize` (a zbytku tvaru Sliceru) firmware start odmítne 10301, i u souboru ze
  Sliceru. `md5`/`filesize` tiskárna nekontroluje (Slicer posílá hodnoty ze vzoru dokumentace), `url` nestahuje (tiskne
  nahraný soubor). Ovladač posílá skutečné `md5` a `filesize` nahraného souboru a `url` Sliceru jako zástupný text.
- **Soubor:** firmware čte z G-code řádek `; paint_info = [{"material_type","paint_color","paint_index"}]` (v hlavičce a
  v bloku `ams_info` na konci) a blok `statistics` (`model_size` = rozměry na displeji). Orca 2.4 je nepíše, a bez
  nich hlášení `update`/`init` nese smetí a start neprojde. `anycubic_pkg` je doplní; holý `.gcode` s nimi stačí,
  balíček `.gcode.3mf` navíc dá tiskárně náhled a je to formát Sliceru (výchozí).
- **`T<n>` a GcodeSlot:** pro tento firmware je `T<n>` „barva n“ projektu a cívku určuje `ams_box_mapping`. Ovladač
  proto v souboru nahradí `T<n>` za `T0` (barva 0) a pozici `n` pošle jako `ams_index`. `GcodeSlot::retarget` se
  měnit nemusí; `M104/M109` před `T` nevadí. Vícebarevný tisk (víc `T`) tímhle zatím neřeším.
- **`G9111 bedTemp=55 extruderTemp=225`** firmware vzal (nahřál přesně na to).
- `auto_leveling 1` jako Slicer: tiskárna vyrovnává před každým tiskem (volba `auto_leveling`).
- Úloha, kterou tiskárna jen založila (`init`), neblokuje start ze Sliceru, ale je lepší ji ukončit; `print/stop` to
  umí (ovladač to dělá před startem). Restart tiskárny nebyl nikdy potřeba.

### 4d. Celý průchod ovladačem (E4, 13:50–13:52)

`scratchpad/kx_drive.py start` → `watch` → `snapshot` → `cancel` s ovladačem z této větve, výchozí volby
(`package: true`): balíček `mptest-cube10.gcode.3mf` (24 210 B) nahrán, log „starting mptest-cube10.gcode
(T1 -> spool position 1, PLA Silk #FF7F32)“, za 6 s „the printer took“; stav `printing`, tryska 113 → 228 °C
(cíl 225), podložka 53 → 56 °C (cíl 55), název souboru sedí; snímek z kamery 81 kB; `cancel()` → do 5 s `idle`,
tiskárna `free`, bez úlohy. Nic na tiskárně nezůstalo rozdělané.

Nález při zrušení: tiskárna zrušenou úlohu **nezapíše do `last_project`** (zůstává tam poslední dokončený tisk),
takže po zrušení hlásí jen „volná“ a starou dokončenou úlohu. Ovladač si proto zapamatuje hlášení
`print/report stop` → `stoped` (s názvem souboru) a po něm hlásí úlohu jako zrušenou, dokud nezačne další.

## 5. Instalace agenta na PC farmy (pro řídící)

- `requirements.txt` nově: `kobra-connect @ https://github.com/rvanderp3/kobra-connect/archive/3edba24….zip` (zip,
  git netřeba; stáhne `paho-mqtt`, `cryptography`, `flatbuffers`, `websocket-client`, `requests`) a `av>=12` (kamera;
  wheel pro Windows má ffmpeg v sobě). `anycubic_pkg` je čistá knihovna, nic dalšího.
- Stávající instalace: `venv\Scripts\python.exe -m pip install -r requirements.txt`, restart služby agenta.
- `config.yaml` na PC farmy: blok `key: kobra-x-01`, `driver: anycubic_lan`, `ip: 192.168.8.44` (Roman tiskárnu
  v `/admin/farm/printers` už přepnul na „Automaticky přes agenta“, agent Agent1 S1; dokud na PC farmy neběží 0.2.0
  s tímhle blokem, je v adminu offline a žádná zakázka na ni nejde).
- PC farmy musí vidět tiskárnu v síti (stejná LAN; firewall Windows pro Python povolit, nebo síť jako soukromou).
  Tiskárna musí zůstat v LAN režimu (odpojená od cloudu Anycubic).
- Server: beze změny. `telemetry.link = "cloud_mode"` nástěnka zatím ukáže jako neznámý klíč.

## 6. Měnič ACE: 3 + 4 pozice (agent 0.2.1, větev `feature/agent-kobra-x-ace`, 18. 10. 2026)

Zadání `docs/prompts/kobra-x-menic.md`: Roman má na Kobře X připojený měnič, Rack 3 + ACE 4 = 7 cívek; ovladač 0.2.0
posílal cívku jen do Racku.

### 6a. Co tiskárna hlásí (`multiColorBox getInfo`, čtení 18. 10. ~14:10)

```
head_tools_model 1
BOX id=-1 model_id=40002 status=1 loaded_slot=-1 temp=0 humidity=0          (Rack tiskárny)
    index 0  PLA Silk  [0,43,73]      status 5  edit_status 1  100 %
    index 1  PLA Silk  [255,127,50]   status 5  edit_status 1  100 %
    index 2  PLA Silk  [33,39,33]     status 5  edit_status 1  100 %
    index 3  PLA+      [255,255,255]  status 5  edit_status 3    0 %         (vstup měniče; na displeji dlaždice „ACE“)
BOX id=0  model_id=40002 status=1 loaded_slot=-1 temp=28 humidity=38       (ACE)
    index 0  PLA Silk  [255,127,50]   status 5
    index 1  PLA Metal [0,53,148]     status 5
    index 2  PLA Metal [101,137,70]   status 4
    index 3  PLA Metal [242,169,0]    status 5
```
- Rack hlásí 4 sloty i s měničem; index 3 je obsazený vstupem měniče (`edit_status 3`, 0 %), displej ukazuje Rack 1–3
  a dlaždici ACE. ACE je `id 0`, sloty 0–3, na displeji ACE 1–4. `status` 5 = zavedeno, 4 = připraveno.
- Během zavádění z Racku (§4) hlásil box `−1` `loaded_slot 1` a report `extfilbox` `current_filament "0-1"`,
  `print/report` `channel_no 2`. Dotazy `extfilbox` tiskárna neodpovídá, hlásí sama.
- Žádný veřejný kód nestartuje tisk z ACE na Kobře X. Jediný známý vzorec je z cloudového API Anycubicu
  (hass-anycubic_cloud, `printer_properties.py`): `ams_index = box × 4 + slot`, bez pole pro box, kde box je **pořadí
  v seznamu** `multi_color_box`. Pro Kobru X je seznam [Rack (`id −1`), ACE (`id 0`)] → Rack 0–3, ACE 4–7; start ze
  Sliceru s `ams_index 1` zavedl z Racku, což s tím souhlasí.

### 6b. Ovladač

- `spools()`: pozice živě z `getInfo`, Rack první, pak ACE podle `id`; Rack dává 3 pozice s měničem (jinak 4), ACE 4.
  Slot matplace `n` = n‑tá pozice v tomhle pořadí: Rack 1–3 = sloty 0–2, ACE 1–4 = sloty 3–6; bez měniče Rack 1–4 =
  sloty 0–3. `Position.ams_index = pořadí boxu × 4 + index` (Rack 0–3, ACE 4–7).
- `spool_at(slot)`: pozice s barvou a materiálem pro `paint_info` a `ams_box_mapping`; slot mimo rozsah →
  `DriverError("slot 5: the printer offers 4 spool positions (Rack 1, …)")`, bez nahrání. Když tiskárna boxy
  nehlásí, slot se bere jako index Racku a barva z G-code.
- `status()` při tisku přidá `telemetry.spool` („ACE 1“) a `telemetry.slot` (slot matplace) podle `loaded_slot`.
- Stavy `extfilbox_filament_feeding` i `ace_filament_feeding` = zavádí (tiskne).
- Testy: dva boxy 3 + 4 i jeden (4); start ze slotů 2, 3, 6 (ams_index 2, 4, 7 s barvou té pozice); slot 4 bez
  měniče = chyba bez nahrání; zavedená cívka ACE 1 v telemetrii. Celkem 53 testů.

### 6c. Ověřeno na tiskárně (18. 10. 2026, 15:38; Romanovo „proveď zkoušku“ v okně session 4, C1 v ručním režimu)

Kostka `kx-cube10.gcode` s `T3` (jak by ji napsal GcodeSlot pro slot 3), `kx_drive.py start … 3` s ovladačem
0.2.1: log „starting mptest-cube10-t3.gcode (T3 -> slot 3 = ACE 1, ams_index 4, PLA Silk #FF7F32)“, tiskárna start
vzala za 6 s, nahřála 225/55 °C a pak:

```
15:39:48 print/report start  state=ace_filament_feeding  data={"channel_no": 1, "unit_no": 1, "taskid": "-1"}
15:39:49 extfilbox/report    current_filament "1-0"
```

tedy **zavedla z ACE, pozice 1** (`unit_no 1` = druhý box v pořadí = ACE, `channel_no 1` = index 0 + 1;
`current_filament` = „<pořadí boxu>-<index>“, u Racku dříve „0-1“). Vzorec `ams_index = pořadí boxu × 4 + index`
platí: Rack 0–3, ACE 4–7. Roman nechal kostku dotisknout. Zkouška ze slotu 0 (Rack 1) se nedělala: Rack byl
ověřený už v §4 (slot 1 = Rack 2) a liší se jen indexem.

Průběh (čtení `info` + `getInfo` po 10 s): `ace_filament_feeding` 15:39:48–15:40:29 (box 0 `loaded_slot 0`),
`nozzle_purging`, `nozzle_wiping`, `nozzle_cooling` (180 → 140), vyrovnání, od 15:47 `printing` s `progress`,
`curr_layer`/50 a `remain_time` v minutách, 15:52:03 vrstva 50/50, pak `hotbed_cooling_off`, `nozzle_cooling_off`,
15:53:09 `print/report` **`state finished`** (`filename`, `progress 100`) a `status/report workReport free`. Průběžná
`print/report` nesou `print_time` (min) a `supplies_usage` (mm; na konci 403 mm = 0,63 g odpovídá Orce). Telemetrie
ovladače během tisku: `spool "ACE 1"`, `slot 3`.

### 6d. Nález: dokončený tisk v `last_project` není

Po dokončení tiskárna hlásí `free`, `project null` a v `last_project` **pořád starý tisk** (Spring-Lock z 17. 10.,
z cloudové úlohy), ne naši kostku; stejně jako po zrušení (§4d). Ovladač 0.2.0 by tedy po dotištění hlásil starou
dokončenou úlohu a agent by zakázku po několika dotazech uzavřel jako „selhala“ („the printer no longer reports this
print“). Oprava v 0.2.1: ovladač si z hlášení `print/report` pamatuje poslední průběh úlohy (`print_time`,
`supplies_usage`) a její konec (`finished` → hotovo, `stoped` → zrušeno, `failed` → chyba s `msg`) a po něm hlásí
úlohu s těmito čísly, dokud nezačne další start. Test s falešnou tiskárnou končí stejně jako skutečná. Omezení: je
to paměť procesu; když agent spadne mezi dokončením a dalším dotazem, konec se ztratí a platí dosavadní chování
agenta (úloha „ztracena“). Tiskárna sama konec jinak neuchovává (aspoň u úloh s `taskid "-1"`).

## 7. Časosběr po vrstvách, světlo, sušení (agent 0.2.2, větev `feature/agent-kobra-x-timelapse`, 18. 10. 2026)

Zadání `docs/prompts/kobra-x-casosber.md`. Zjištěno čtením z tiskárny a z kódu integrací (chrisfore/anycubic_ha_local,
stribor/anycubic_kobrax, Rinkhals, hass-anycubic_cloud); ověření příkazů na tiskárně v §7d.

### 7a. Světlo

- Kobra X má světlo kamery (`type 3`); firmware ho sám zapíná při zavádění a vyrovnávání a hlásí
  `light/report {action control, data {type 3, status 0|1, brightness}}` (zachyceno během tisků v §4 a §6).
- Příkaz: téma `web/.../light`, `{type: "light", action: "control", data: {type: 3, status: 1|0, brightness: 100|0}}`
  (stejný tvar používá stribor na Kobře X). Stav: `light` `query` → `{"lights": [{"type": 3, "status", "brightness"}]}`
  (ověřeno čtením 18. 10.). `type 1` = světlo hlavy, `type 2` = komora (jen S1); Kobra X hlásí jen `type 3`.
- Ovladač: `light(on)` posílá `control` (výchozí `light: true`), `status()` dává `telemetry.light` = `on`/`off`.

### 7b. Sušení

- Příkaz (chrisfore, zachyceno na S1 Max): `multiColorBox` `setDry`,
  `{"multi_color_box": [{"id": 0, "drying_status": {"status": 1, "target_temp": 45, "duration": <minuty>}}]}`;
  stop = `drying_status {"status": 0}`. Odpověď `multiColorBox/report` `action setDry`, `state success`.
- Stav v `getInfo`: box ACE `temp`, `humidity`, `drying_status {status, target_temp, duration, remain_time}`.
  Kobra X 18. 10.: ACE `temp 28`, `humidity 38`, `drying_status` vše 0. Vestavěný Rack (`id −1`) sušičku nemá.
- Ovladač: `dry(on, temp, minutes)` na první ACE (`id 0`); bez ACE `DriverError("this printer has no ACE unit to dry
  in")`. Telemetrie s klíči nástěnky: `dryer_state` (`off`/`drying`), `dryer_temp`, `dryer_target`, `dryer_remain_min`,
  `dryer_rh`. Jednotka `remain_time` (minuty?) a rozsahy teplot z displeje: doplnit po zkoušce.

### 7c. Časosběr

- **Vlastní časosběr tiskárny:** tiskárna si videa ukládá – `file` `listVideo` vrací
  `{"records": [{"filename": "Dragon Textured_PLA_0.2_9h20m-20260524170046_3.mp4", "fileSize": 78779, "createTime":
  1779615629, "fileKey": "", "main_file_name": …}]}` (video z května z cloudového tisku). V LAN ale není cesta, jak ho
  stáhnout: HTTP na 18910/18088 vrací 404 pro všechny vyzkoušené cesty (`/video/<jméno>`, `/timelapse/…`, …), akce
  `file` `videoDetails`/`getVideoUrl`/`downloadVideo` a témata `video`/`timelapse` neodpovídají, `fileKey` je prázdný
  (plní ho cloud při nahrání). Žádná integrace to nemá. `task_settings.timelapse {status, count, type, mode}` ze
  startu Sliceru nikde popsaný není (v cloudu je `camera_timelapse: 0|1`). → Nepoužitelné bez cloudu; nezapínám.
- **Vrstva z hlášení:** `print/report` během tisku nese `curr_layer`, `progress`, `print_time`, `supplies_usage`,
  `remain_time`, ale **žádné souřadnice** (ani v jiné integraci). Hlášení chodí periodicky zhruba každé 2 s (změřeno
  z tisku v §6c: 15:47:00–15:49:12 intervaly 1–5 s, nejčastěji 2 s), vrstva v nich mění hodnotu podle postupu tisku.
  Změna vrstvy tedy dorazí se zpožděním 0 až ~2 s po skutečné změně. Farma parkuje hned za `; AFTER_LAYER_CHANGE`:
  zdvih, přejezd na 250/250 (~1 s), `G4 P<dwell>`, návrat. S `dwell 1000` je hlava v parku asi 1–2 s po změně, a to
  hlášení často nestihne; s `dwell 3000` (1–4 s po změně) ano.
- Ovladač: z první řádky G-kódu (`; matplace timelapse park_x=250 park_y=250 dwell=1000 …`) si vezme park a dwell;
  změny vrstvy sleduje z `print/report` i z `info` (od vrstvy 2, první značku farma přeskakuje). Odhad „hlášení
  přijde 0–2 s po změně“ se měřením (§7d) ukázal mylný: hlášení přichází 2,2–4,0 s **před** parkem, protože firmware
  hlásí vrstvu při načtení G‑kódu. `head()` proto hlásí park podle změřeného předstihu (§7d). Agent se nemění.

### 7c'. Kamera: stream musí číst vlastní socket, ne HTTP vrstva ffmpeg

První měření parkování (16:24) dalo z každé série jen **jeden** snímek, ~10 s po hlášení vrstvy: `snapshot()` 0.2.0
otvíral `av.open(http://…:18088/live/<id>)` pro každý snímek a první snímek trval ~10 s. Příčina (16:32–16:40,
`scratchpad/kx_stream_raw.py`, `kx_stream_fileobj.py`): tiskárna streamuje **živě a hned** (HTTP 206, `video/x-flv`,
souvisle ~43 kB/s, jedna hlavička FLV na začátku, 10 snímků/s H.264 1280×720), ale ffmpeg přes svůj HTTP čtenář
stream ~10 s sonduje, pak vydá dávku ~100 nasbíraných snímků a na dalších paketech padá („Invalid data found“ /
„Immediate exit requested“) – i po `video/startCapture` (tiskárna odpoví `initSuccess`, `joinSuccess`,
`pushStarted`; `stopCapture` → `pushStopped`; na obsah streamu v LAN to vliv nemá, stream jede bez něj).
Řešení v ovladači (`_StreamBody`, `Camera`): tělo HTTP odpovědi čte vlastní socket a PyAV dostane souborový objekt
s `format="flv"`, `probesize 65536`, `analyzeduration 0`, `fflags nobuffer`; vadný paket se přeskočí. Stream drží
vlákno, dokud někdo chce snímky (zavře se po 20 s bez požadavku). Změřeno (`kx_cam_timing.py`): první snímek
**3,6–4,1 s** po otevření (čeká na klíčový snímek), další **okamžitě** (0,00–0,02 s) a vždy čerstvé; po 25 s nečinnosti
znovu 4,1 s. Při tisku agent chce snímky každých 15 s, takže stream zůstává otevřený a série po 0,3 s jsou skutečné.

### 7d. Ověření na tiskárně (18. 10. 2026 od 16:22, Romanovo „proveď zkoušky“ v okně session 4, C1 ruční)

1. **Světlo** (`scratchpad/kx_light_dry_test.py`): `light(True)` → za 2 s `light query` `status 1` → telemetrie
   `light: on`; `light(False)` → `off`. Funguje; výchozí `light: true`.
2. **Sušení**: `dry(True, 45, 10)` → tiskárna odpověděla `success`; `getInfo` po 10–60 s: `drying_status {status 1,
   target_temp 45, duration 600, remain_time 9}`, box `temp 29`, `humidity 36–37` → telemetrie `dryer_state drying`,
   `dryer_target 45`, `dryer_remain_min 9`, `dryer_temp 29`, `dryer_rh 37`. Jednotky: v příkazu `duration` = **minuty**,
   v hlášení `duration` = sekundy a `remain_time` = **minuty**. Za minutu se box neohřál (29 °C), ACE hřeje pomalu.
   `dry(False)` → `drying_status` vše 0, telemetrie `off`. Rozsahy z displeje (PLA 45 °C, PETG 55 °C, max. minuty)
   jsem nečetla; ovladač posílá, co mu nástěnka dá.
3. **Parkovací kostka** (`kx-cube10-park.gcode`, dwell 1000, a varianta dwell 3000; park přepsán na X0 Y130 podle
   Romana – kamera je vpravo, hlava v parku vlevo je od ní dál; `scratchpad/kx_timelapse_test.py` = ovladač + emulace
   série snímků agenta, bez vyrovnání podložky, 12 parků a zrušení; poloha hlavy ve snímcích změřena jako střed a
   plocha bílé hlavy, `kx_park_analyse2.py`):

   **Dwell 1000 (16:42–16:49):** v každé sérii 14 snímků po 0,3 s od hlášení vrstvy. Hlava je v parku (malá, vlevo)
   **2,2–4,0 s PO hlášení vrstvy**, pokaždé asi 1 s (dwell): vrstva 3: 2,3–3,0 s; 4: 2,3–2,9; 5: 3,0–3,7; 6: 2,7–3,4;
   7: 3,0–3,6; 8: 4,0–4,3+; 9: 3,0–3,7; 10: 2,2–2,8; 11: 3,2–3,5; 12: 4,0–4,3+; 13: 3,7–4,0+. Tiskárna tedy hlásí
   vrstvu, když G‑kód **načte**, a hlava do parku dojede až o 2,2–4,0 s později (plánovač jede napřed; rozptyl podle
   délky drah před parkem). Výběr nejklidnějšího snímku (`pick_still`) vybral u 10mm kostky v 11 z 11 vrstev snímek
   s hlavou **u kostky**, ne v parku: při tisku malé kostky se hlava mezi snímky po 0,3 s skoro nehýbe, takže
   „nejklidnější“ není „zaparkovaná“. S dwell 1 s žádné jisté okno není (začátek parku 2,2–4,0 s, konec 3,2–5,0 s).

   **Dwell 3000 (16:49–16:55):** série 20 snímků po 0,3 s. Park začíná 2,1–3,8 s po hlášení (vrstvy 3–13: 2,2; 2,1;
   3,8; 3,6; 2,8; 2,9; 2,2; 2,2; 3,0; 2,9; 2,7) a končí 5,5–6,6 s (trvá ~3,3 s). Průnik přes všechny vrstvy:
   **4,0–5,2 s po hlášení je hlava v parku vždy** (11 z 11; i s nejpozdějšími starty 4,0 s z běhu s dwell 1000 vychází
   konec 7,0 s). Žádný tisk nezůstal rozdělaný; obě kostky zrušeny po 12 parcích, tiskárna `free`.

   **Závěr a ovladač:** `head()` počítá s předstihem hlášení `park_lead_min` 2,2 s až `park_lead` 4,0 s (volby). Když
   dwell dá jisté okno `[hlášení + 4,0 s, hlášení + 2,2 s + dwell]` aspoň 0,5 s dlouhé (dwell ≥ 2,3 s), hlásí v něm
   park jako **skutečnou stojící polohu** (rychlost 0) a agent udělá **jeden snímek** hned při prvním dotazu v okně
   (dotazuje se 4× za s). S kratším dwell hlásí park jako plánovanou polohu od 2,2 s po dobu dwell + 1,8 s a agent
   dělá sérii s výběrem nejklidnějšího – u malých dílů nespolehlivě. **Doporučení pro C1: `dwell` 3000 ms v adminu**
   (parametr tiskárny; 49 vrstev = +2,5 min, server to do odhadu připočítá), park X0 Y130. Agent ani server se nemění.
   Rozptyl předstihu (1,8 s) závisí na tom, kolik G‑kódu má plánovač před parkem načteno; u dílů s dlouhými rovnými
   drahami může být předstih delší – když by se snímky míjely, zvětšit `park_lead` (volba v configu) nebo dwell.

## 7e. První zakázka F26‑000086: hlava v parku 340 z 349 snímků; „kamera jako čidlo“ (0.2.3) stažena; předstih se `sync=m400`; nuly ve statistice (agent 0.2.4, větev `feature/agent-kobra-x-stats`, 18. 10. 2026)

**Snímky** (349 vrstvových v `Desktop\kx-frames\F26-000086rames_layer`, 15:39–17:23 UTC, agent 0.2.2, dwell 3000, park
X0 Y130, bez M400): kamera je na mostě hlavy a dívá se podél osy X; hlava v parku X0 stojí na konci mostu **uprostřed
obrazu** (střední velikost, díl pod ní v ose pohledu) – ne „malá vpravo“, jak jsem řídící napsala z prvních 17 snímků
(to byl jas lišty mostu v rohu). Měřeno korelací oblasti hlavy s mediánem všech snímků (nezávislé na expozici,
`scratchpad/f26-000086-corr.txt`): 340 snímků ≥ 0,96 = hlava v parku, **8 snímků 0,32–0,46 = hlava jinde** (velká,
blízko kamery, rozmazaná pohybem): 1792337995.472, 1792338052.829, 1792338122.600, 1792338304.691 (+50, +107, +177,
+359 s – spodní vrstvy s dlouhými drahami výplně), 1792338488.614 (+543 s), 1792343649.097, 1792344015.011,
1792344137.114 (+5704, +6069, +6192 s – horní vrstvy); 1792343830.897 (0,84) je v parku, ale rozmazaná (přijíždí nebo
odjíždí). Časování 0.2.2 (okno 4,0–5,2 s po hlášení) tedy sedělo v 97 % vrstev; vedle jsou vrstvy, kde plánovač načte
značku víc než 4 s před parkem (dlouhé rovné dráhy) – přesně to řeší M400.

**Proč „kamera jako čidlo“ z 0.2.3 nejde a 0.2.4 ji vynechává:** počet jasných bodů (> 200) ve středním pásu rostl během
tisku z 0 na 1 300 **u zaparkované hlavy** – kamera mění expozici podle scény (tmavý díl roste, světlo se odráží) a
bílá hlava se dostane nad práh. V prvních 17 snímcích prahování náhodou sedělo (tisknoucí hlava blízko lampy jasná,
parkovaná ještě šedá); na celé zakázce by `head()` od ~26. minuty park nehlásil vůbec (hlava „pořád vidět“ → žádný
snímek vrstvy). Jas oblasti parku dopadl stejně. Spolehlivá je až korelace s mediánem (šablona z vlastních snímků), tu
ale ovladač v reálném čase bez reference nemá. Proto 0.2.4: **jen časování**; volby `camera_check`, `head_region`,
`head_away` zrušeny (0.2.3 nebyl nikde nainstalován).

**Výchozí předstih se `sync=m400`** (server main 4030397 vkládá `M400` před značku vrstvy, hlavička ` sync=m400`): M400
nechá parser čekat, až dojedou pohyby vrstvy, značka se tedy čte se **stojící** hlavou (t = 0); přejezd do parku trvá
T ≈ 1–1,5 s, hlášení `print/report` přijde 0–2 s po čtení (chodí periodicky po ~2 s). Vůči hlášení park začíná
T − L ∈ [−1,0; 1,5] s a končí o dwell později → jisté okno `[park_lead, park_lead_min + dwell]` = **[1,5; dwell − 1,0]**,
s dwell 3000 **1,5–2,0 s po hlášení**. Výchozí `park_lead_min` −1,0 / `park_lead` 1,5 (0.2.3 mělo 0 / 2,0 – při hlášení
zpožděném o 2 s by snímek padl na odjezd hlavy). Bez značky zůstává 2,2 / 4,0. Ověří se na další zakázce z
`frames_layer` stejnou korelací; při míjení zvětšit dwell (3500 → okno 1,5–2,5 s), ne předstih.

**Statistika úlohy 143 = 0 min, 0 mm:** z odposlechu (`kx_sniff_ace.log`, 15:53:08) tiskárna sekundu před `finished`
pošle `print/report` `state printing` s daty jen `{filename, progress 0, taskid}`; ovladač 0.2.1–0.2.3 si ukládal celé
poslední hlášení s `progress`, takže tahle řádka **přepsala** `print_time`/`supplies_usage` prázdnem a konec měl nuly
(v §6c jsem čísla četla z odposlechu, ne z ovladače). 0.2.4: hlášení téhož souboru se slučují (nové klíče přepisují,
chybějící zůstávají); falešná tiskárna v testech teď posílá i chladicí a holé hlášení, test konce by bez opravy padl.
Instalace: soubory `agent/`, restart agenta; nic na serveru.

## 8. Jedna podložka, více filamentů (agent 0.2.5, větev `feature/agent-multi-mapping`, 19. 10. 2026) – bez tiskárny

Zadání `docs/prompts/vice-filamentu-podlozka.md`: session 2 řeže podložku s díly v několika filamentech (Orca, extruder
na díl), `GcodeSlot` přemapuje `T<n>` na sloty a do hlavičky napíše `; matplace filaments T0=<slot> T1=<slot> …`.
Ovladač Kobra X k tomu:

- **Čtení** (`anycubic_pkg.filaments_of`, `tools_of`, `config_values`): řádek `; matplace filaments` kdekoli v prvních
  16 kB (i za řádkem časosběru) → barva `T<n>` → slot matplace; soubor sám vybírá cívky jako `T<slot>` (jediný tvar farmy,
  Rinkhals ho bere přímo; `tools_of` = všechna `T<slot>` v souboru, změny uprostřed tisku); z bloku CONFIG seznamy `filament_colour`, `filament_type`, `filament_ids` (oddělovač `;`) a z řádků
  `filament used [mm]`/`[g]` spotřeba po extruderech (oddělovač `,`). Bez toho řádku vše jako dosud (jeden filament).
- **Pozice** (`_paint_positions`): pro každou barvu `spool_at(slot)` – barva a materiál z cívky tiskárny (bez informací
  o boxech slot = index Racku jako dosud). Odmítne **před nahráním**: mezera v barvách (`T0 T2`), dvě barvy z jednoho
  slotu, `T<slot>` v souboru, který řádek nemapuje, slot mimo pozice (`T1: slot 10: the printer offers 7 spool positions (…)`).
- **Soubor** (`prepared_gcode(filaments=…, renumber=…)`, `package(…)`): každý řádek `T<slot>` se přečísluje na
  `T<barva>` (firmware čte `T<n>` jako barvu n, ověřeno 18. 10.; u jednoho filamentu dál `T<n>` → `T0`); `paint_info` v hlavičce i v `ams_info` má položku na každou barvu (`paint_index` = n, barva projektu,
  materiál cívky; řádku, kterou už soubor má – Slicer Next ji píše sám – nechá); `slice_info.config` má `<filament id="n+1" … used_m used_g>` z řádků po extruderech, `plate_1.json`
  `filament_colors`/`filament_ids` pro všechny, `weight` = `total filament used [g]`, `statistics.used_filament` =
  součet metrů; náhledy barvou první cívky.
- **Start**: `ams_box_mapping` = položka na barvu `{paint_index n, ams_index pozice, paint_color = barva projektu
  (řádek `filament_colour` souboru; Slicer Next ji má i ve své řádce `paint_info`, která v souboru zůstává),
  ams_color = barva cívky, material_type}` – přesně tak Slicer Next mapuje barvy projektu na cívky (zachyceno 18. 10.:
  `paint_index 1 → ams_index 1`, `paint_color` projektu černá, `ams_color` cívky oranžová). U jednoho filamentu dál
  barva cívky v obou (ověřeno). Log startu vypíše všechna `T<n> → slot = pozice`.
- **Za běhu**: `multiColorBox` `loaded_slot` se při výměně mění → telemetrie `spool`/`slot` ukazuje cívku v hlavě jako
  dosud; `project.state` obsahující `filament` (`extfilbox_filament_feeding`, `ace_filament_feeding`; při výměně
  nejspíš i odvádění) jde do telemetrie `spool_change`. Neznámé stavy = tisk (jako dosud), výměna tisk „neukončí“.
- **Neověřeno na tiskárně**: `paint_info` s více položkami je rekonstrukce podle Sliceru (jedna položka ověřená
  18. 10.); co tiskárna hlásí při výměně uprostřed tisku (stavy, pořadí `loaded_slot`), zda `T<n>` z Orcy stačí
  (Slicer Next možná vkládá vlastní makro výměny – G‑kód session 2 ukáže) a jak dlouho výměna trvá. **Plán zkoušky**
  (až bude G‑kód session 2 a Romanovo svolení v okně session 4, C1 v ručním režimu): malý dvoubarevný díl,
  `scratchpad/kx_sniff.py` běží vedle, zaznamenat `print/report` při každé změně, `multiColorBox/getInfo` `loaded_slot`,
  čas výměny → §8a.
- Testy: `test_anycubic_pkg` (fixture TWO: fakta, G‑kód, balíček), `test_anycubic_lan` (start se dvěma barvami →
  mapování, balíček, `spool_change`; tři odmítnutí před nahráním). Moonraker beze změny (`T<n>` = pozice ACE).

### 8a. Zkouška nasucho s prvním G‑kódem session 2 (19. 10. 2026, `scratchpad/kx_dry_run_multi.py`, falešná tiskárna s boxy Kobry X z 18. 10.)

Soubor `Desktop\matplace-two-filaments-T0slot2-T1slot5.gcode` (535 kB, 20 248 řádků, **AnycubicSlicerNext 2.0.0.3**,
15 vrstev, 55 min, 8,57 + 3,86 g, čisticí věž): řádek 2 `; matplace filaments T0=2 T1=5`; soubor vybírá cívky jako
**`T2`/`T5`** (16 řádků, první na ř. 53 za `M104/M109` od GcodeSlot) – tedy sloty, tvar pro Rinkhals. Firmware Kobry X
čte `T<n>` jako barvu n (ověřeno 18. 10.), proto ovladač každý `T<slot>` **přečísluje** na `T<barva>` (`renumber`
slot → barva z řádku); session 2 nic nemění, jeden tvar souboru slouží oběma tiskárnám. Dřívější verze (4a4c0bd)
by soubor odmítla („selects T2 T5, which the filament line does not name“) – bezpečně, ale k ničemu.

Co by tiskárna dostala: balíček `.gcode.3mf` 111 kB (16 souborů); G‑kód s **vlastní** řádkou `paint_info` Sliceru
Next (barvy projektu 255,215,0 a 32,32,32) v hlavičce i v `ams_info`, s jeho `statistics` (`used_filament 4.17`,
`model_size 60.00,60.00,3.00`) – ovladač nic nepřidává, jen přečísluje; 16 řádků `T0`/`T1`; `slice_info.config`
`weight 12.43`, `<filament id=1 … color="#FFD700" used_m 2.87 used_g 8.57>`, `<filament id=2 … "#202020" 1.29 3.86>`;
`plate_1.json` `bbox_all [95,95,155,155]` (Slicer Next nepíše `first_layer_print_min/max`, ovladač bere obrysy
z `EXCLUDE_OBJECT_DEFINE`; shoduje se s `model_size` 60×60). Start: `ams_box_mapping` `[{paint 0, ams_index 2 (Rack 3),
paint_color 255,215,0, ams_color barva cívky}, {paint 1, ams_index 6 (ACE 3), paint_color 32,32,32, ams_color cívky}]`,
`md5`/`filesize` balíčku, `auto_leveling 1`. Makro výměny: Slicer Next ho zapsal jen jako zakomentované řádky
(`;;; G1 …`, `;;; M400 …`) kolem holého `T<n>` – změnu dělá firmware sám; `M600` je v souboru jen v komentáři
`machine_pause_gcode`. Skutečný tisk: až po Romanově svolení v okně session 4, C1 v ručním režimu, odposlech vedle.

### 8b. První dvoubarevná zakázka na Kobře X: F26‑000089 (19. 10. 2026 22:13–23:52, agent 0.2.5, odposlech `scratchpad/kx_sniff_medal.py` → `kx_medal.log`)

Zkušební medaile (řídící: `print.hot.multi0to4_1to1-….gcode`, `; matplace filaments T0=4 T1=1`, tělo = slot 4 = ACE 2
azuritová, vložka = slot 1 = Rack 2 červená, 22 vrstev, odhad 1 h 42 min, tiskárna hlásí `channel_no` = index pozice + 1,
extfilbox `current_filament` = `<box>-<index>`: `0-1` Rack 2, `1-1` ACE 2). Start přes farmu prošel napoprvé (balíček,
žádné odmítnutí), celý tisk bez zásahu; já jen poslouchala (Romanovo „odposlech ok“ v okně session 4).

**Sekvence před tiskem** (delší než u kostky 18. 10.): 22:13:56 `extfilbox_filament_feeding` ch 2 (tiskárna napřed zavede
cívku, která byla v hlavě z minula – Rack 2, ne barvu 0 z mapování) → `nozzle_purging` → 22:14:40 `nozzle_cooling` 180,
`nozzle_wiping` → `nozzle_cooling` 140 → 22:15:16 `auto_leveling` → 22:17:29 **`z_offset_calibrating`** → `hotbed_cooling_off`
→ `auto_leveling` → `hotbed_heating` 60 → **`frequency_sweeping`** → 22:17:57 `nozzle_heating` 230 → 22:18:33 druhé
zavedení Rack 2 + `nozzle_purging` → 22:19:51 hlášena vrstva 1 (čtení napřed) → **22:20:24 `filament_cutting`** = první
`T0` souboru (ř. 53, před první značkou vrstvy a čisticí čárou) → `extfilbox_filament_unfeeding` ch 2 → 22:20:38
`ace_filament_feeding` unit 1 ch 2 → 22:21:22 `nozzle_purging` → 22:21:57 tisk vrstvy 1 azuritem. Nic se netisklo
špatnou barvou (první extruze je čisticí čára za značkou vrstvy, po výměně). Dva nové stavy (`z_offset_calibrating`,
`frequency_sweeping`) + `filament_cutting`, `extfilbox_filament_unfeeding`, `ace_filament_unfeeding`: ovladač je bere
jako tisk (neznámý stav = tisk), ty s „filament“ jdou do telemetrie `spool_change`.

**Výměny za běhu** (vše samo, firmware na holé `T<n>`; cut → odvedení → zavedení → proplach):
| # | kdy | z → na | odvedení | zavedení | celkem |
|---|-----|--------|----------|----------|--------|
| 1 | 22:20:24 | Rack 2 → ACE 2 | rack 6 s | ACE 44 s | ~66 s |
| 2 | 22:29:28 | ACE 2 → Rack 2 | **ACE 51 s** | rack 7 s | ~72 s |
| 3 | 22:32:31 | Rack 2 → ACE 2 | rack 9 s | ACE 44 s | ~65 s |
Odvedení z ACE trvá 51 s, z Racku 6–9 s; zavedení z ACE 44 s, z Racku 7 s. Před výměnou tiskárna nastaví teplotu
nové cívky (`nozzle_heating 230` / `nozzle_cooling 225`). `multiColorBox` `loaded_slot`: Rack index 1 → −1 → ACE
index 1 (telemetrie `spool`/`slot` ukazuje správnou cívku). Během výměny hlásí `curr_layer 0` a po ní zase
skutečnou vrstvu (L2 → L0 → L2): ovladač 0.2.5 to bere jako novou vrstvu → agent udělá jeden snímek časosběru
navíc s hlavou u věže (tady 2×); 0.2.6 hlášení vrstvy 0 po již viděné vrstvě ignoruje.

**Konec:** 23:50:35 poslední plné hlášení `print_time 92`, `supplies_usage 13595` (mm, ≈ 40 g; Slicer 12,4 g u
zkušebního souboru, u medaile jiný objem), 23:51:11 `hotbed_cooling_off`/`nozzle_cooling_off`, 23:52:15 holé
`{filename, progress 0}`, 23:52:17 `finished` – přesně tvar, na který míří oprava 0.2.4: úloha má mít na serveru
`print_duration_s 5520` a `filament_used_mm 13595` (řídící ověří). Celkem 98 min od startu (tiskárna 92 min od
první vrstvy), odhad Sliceru 102 min.
