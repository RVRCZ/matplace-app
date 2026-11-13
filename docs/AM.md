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

**Vrstvové snímky F26‑000089 (23, `Desktop\kx-frames\F26-000089\frames_layer`, korelace `scratchpad/kx_frames_corr.py`):**
15 v parku, 2 od věže po výměnách (1792441878.242, 1792442039.493 – vrstva 0 → stejná vrstva, viz výše), **6 s hlavou
mimo park** (1792442256.337, 1792442515.631, 1792443016.056, 1792444365.522, 1792445135.557, 1792446599.391). Soubor
zakázky **neměl `sync=m400`** (řídící: volba C1 se cestou timelapseFor → fileFor ztratila; opravuje server), ovladač
tedy jel 2,2/4,0 a okno 4,0–5,2 s. Čas snímku vůči prvnímu `print/report` s novou vrstvou v odposlechu: všechny v parku
**2,0–3,2 s**, všechny mimo park **4,4–6,1 s** – dvě těsné skupiny s odstupem ~3,5 s (hodiny PC farmy jsou zřejmě ~2 s
pozadu za tímto PC, absolutní čísla neberu; rozdíl skupin ano). Rozptyl předstihu plánovače by dal spojité rozložení,
ne dvě skupiny; ~3,5–4 s je přesně otevření zavřeného streamu kamery (první klíčový snímek, §7c'). Ovladač stream
zavíral po 20 s bez požadavku a živé snímky agenta ho zjevně neudržely; snímek vrstvy pak přišel o 4 s později, kdy
hlava už byla zpátky u dílu. **0.2.7:** během tisku `status()` (každých 5 s) stream „chce“ (`Camera.keep()`), zavře se
až 20 s po tisku; `_note_layer` ignoruje vrstvu 0 (0.2.6). Ověření M400 + −1,0/1,5 s zůstává na další zakázce.

### 8c. Drak F26‑000097 (22.–23. 10. 2026, 15 h, 306 vrstev, první zakázka s dwell 4000): časosběr uzavřen

Korelace s mediánem celého tisku tu selhává (116 „mimo park“ v souvislém bloku 0,63): vysoký díl mění scénu, medián z
celku není reference. S **lokální** referencí (medián 10 sousedních snímků, `scratchpad/f26-000097-local.txt`):
**303 z 306 v parku ostře**, 3 v parku s rozmazanou podložkou (dojíždí/odjíždí: 1792702544.194, 1792740448.601,
1792740889.817), **0 s hlavou mimo park**. Ověřeno: M400 + předstih −1,0/1,5 s + dwell 4000 + stream kamery držený
při tisku (0.2.7). Případné doladění bez kódu: `park_lead: 2.0` v configu C1 (okno 2,0–3,0 s) by odstranilo i ty tři
rozmazané; nechávám na řídící. Časosběr Kobry X tím považuji za hotový.

### 8d. Výměny podle výšky na Kobře X: řádek mapování i pro ně (řídící, 8. 11. 2026, F26‑000142)

Zakázka F26‑000142 (PLA Silk Rainbow Fire Red v pozici 2, výměny 3,0 mm → oranžová pozice 3, 3,8 mm → modrá pozice 1,
4,6 mm → zlatá pozice 6, 5,4 mm → černá pozice 4): soubor měl `T1` na startu a `T2`, `T0`, `T5`, `T3` ve vrstvách správně
podle pozic, ale tiskárna u `T0` vzala zase pozici 2 a skončila chybou 11850 (ACE: kanál nenavedl filament). Příčina: soubor
s výměnami podle výšky (`GcodeSlot::fileFor` → `colorChanges`) řádek `; matplace filaments` neměl, ovladač Kobry X ho tedy
bral jako jednofilamentový (`facts.paints` prázdné): `ams_box_mapping` s jedinou barvou 0 → hlavní cívka, `prepared_gcode`
přečísluje jen první `T` na `T0` a firmware čte `T0` v souboru jako barvu 0 = hlavní cívku; ostatní `T` zůstaly nenamapované.
Dvoubarevná medaile 19. 10. (§8b) šla cestou jedné podložky (`retargetMany`), která řádek píše, proto fungovala.
**Oprava na serveru** (`fix/kx-height-changes-map`): `GcodeSlot::withFilamentsLine` napíše do kopie s výměnami řádek
`; matplace filaments T0=<start> T1=<další>…` ze všech `T<slot>` souboru v pořadí prvního výskytu (barva, která se vrací, je
jedna položka); kopie bez výměn řádek nemá (cesta jedné barvy agenta beze změny); značka souboru `.map.then…`, ať se staré
kopie nepoužijí. Ověřeno místně na skutečném souboru zakázky funkcemi ovladače (`facts_of`, `tools_of`, `prepared_gcode`,
`package`): barvy `{0: 1, 1: 2, 2: 0, 3: 5, 4: 3}`, `T` přečíslované na `T0`…`T4`, balíček s pěti filamenty. Rinkhals
a Moonraker řádek ignorují. Agent sám by měl příště soubor s více `T<slot>` bez řádku odmítnout před nahráním (dnes `tools_of`
vrací `[1, 2, 0, 5, 3]` a nic s tím nedělá): úkol pro session 4 do příští verze ovladače.

**Totéž odpoledne, U1 (Kobra S1) a jedna podložka s více filamenty, F26‑000144, úloha 213 (10:54):** první skutečný tisk tohoto druhu na S1; firmware odmítl soubor kódem 10116 „abnormal slice file“. Kopie `retargetMany` měla řádek `; matplace filaments` vložený za `; HEADER_BLOCK_START`, tedy uvnitř hlavičkového bloku, který stock firmware S1 kontroluje; řádek časosběru před blokem S1 bere u každého tisku. Oprava `fix/s1-filaments-line`: řádek jako první řádek souboru (agent Kobry X ho čte kdekoli v prvních 16 kB), značka kopie `.hot.map.multi…`. Ověření až tiskem na U1; pokud 10116 zůstane, další podezřelí jsou `; filament: 2,3,4,1` v hlavičce, seznamy `filament_*` v CONFIG a věž.

## 9. Konec dlouhého tisku s nulami podruhé: úloha 154 (agent 0.2.8, větev `feature/agent-stats-long`, 23. 10. 2026)

Drak F26‑000097 (15 h přes noc, agent 0.2.7) skončil na serveru s `print_duration_s 0`, `filament_used_mm 0` – stejný
příznak jako úloha 143 před opravou 0.2.4, ale cesta musí být jiná (holé hlášení před `finished` už se slučuje). Log
agenta z PC farmy nemám (vyžádán); bez něj jsem uzavřela všechny cesty, které v ovladači k nulám vedou:

1. **`last_project` tiskárny jmenuje dotištěný soubor** (u krátkých tisků 18. 10. se to nestávalo; dlouhý tisk dokončený
   „normálně“ tam může být) **bez `print_time`/`supplies_usage`**: `_map` dával přednost `last_project` před
   zapamatovaným koncem (`_ended`), jakmile oba jmenovaly stejný soubor. Teď každý skončený projekt projde
   `_with_numbers`: chybějící minuty a milimetry doplní z `_ended`, z posledního plného hlášení, nebo z disku.
2. **Projekt v `info` zůstane jako `finished` bez čísel** – totéž doplnění.
3. **Pozdní hlášení s výslovnými nulami** (`print_time 0, supplies_usage 0`): čísla za běhu tisku jen rostou, hlášení,
   které říká míň nebo nic, je nevezme zpět (`_heard`).
4. **Restart agenta během tisku** (Windows v noci): ovladač si poslední čísla ukládá na disk (`<work_dir>/<key>.progress.json`,
   při novém souboru hned, pak nejvýš jednou za minutu; hlášení bez čísel paměť nepřepíše); po restartu je konec
   téhož souboru vezme z disku. Agent předává `_work_dir` všem ovladačům (ostatní ho ignorují).

Testy: pozdní nuly, `last_project`/`info` bez čísel, restart přes work_dir (68). Co přesně se stalo úloze 154, řekne až
log (restart agenta / spojení v noci; řádek konce úlohy). Pojistku na serveru (done s 0 → rozdíl started/finished,
spotřeba z řezu) dělá session 2.

## 10. Hlášky z tiskáren, pauza, uklizená podložka, soubory na disku (agent 0.2.9, větev `feature/agent-messages`, 30. 10. 2026)

Zadání `docs/prompts/agent-hlasky-soubory.md` (F26‑000108 na B3: pauza při `T3`, důvod jen na displeji, „Pokračovat“ z adminu
hlášeno jako selhání). Farma byla nedostupná; čteno jen z Romanových domácích U1 (192.168.8.21) a U2 (192.168.8.23) přes
Moonraker HTTP, read only, obě ve stavu `complete` po posledních zakázkách. Kobra X (192.168.8.44) na port 18910
neodpovídala (vypnutá), vše pro ni je ze zdrojů a ze zkoušek z §2 až §8.

### 10a. Co Rinkhals skutečně vystaví (čtení U1/U2 30. 10. 2026)

- **`display_status.message`** (M117): prázdné na obou. **`print_stats.message`**: prázdné. `kobra.py` Rinkhalsu
  `print_stats.message` nikdy nenastavuje (jen přepisuje stavy `heating/leveling/resonance → printing`, `onpause → paused`);
  pauzu a pokračování na MQTT nepřekládá, dělá je GoKlipper sám.
- **`mmu`** (objekt z `mmu_ace.py`): má pole `reason_for_pause`, ale je v kódu natvrdo `""`; `print_state` `unknown`, `action`
  `Idle`. Nic z toho důvod pauzy nedá.
- **`filament_hub`** (Rinkhals, ACE Pro): `filament_hubs[0].status` (`ready`), `slots[].status` (`ready`; v kódu `mmu_ace.py`
  dále `empty`, `runout`), `current_filament` (`""` nebo `"<box>-<index>"`), `temp`, `dryer_status`, `auto_refill`,
  `filament_present`. Ukázka U1:
  ```
  {"filament_hubs":[{"id":0,"status":"ready","temp":37,"slots":[{"index":0,"status":"ready","type":"PLA Silk","color":[137,168,79],"rfid":1,"source":2},
   {"index":1,"status":"ready","type":"PLA"},{"index":2,"status":"ready","type":"PLA Silk"},{"index":3,"status":"ready","type":"PLA"}]}],"current_filament":"","filament_present":0}
  ```
- **`/server/gcode_store`** (konzole Moonrakeru, 1000 posledních řádků): jediné místo, kde GoKlipper říká, proč stojí. U2
  (zakázka s ASA, říjen 2026):
  ```
  {"message":"FEED_FILAMENT ID=0 INDEX=2 LENGTH=80 SPEED=25","type":"command"}
  {"message":"!! error: typ = WebRequestError, code = 10011715, message = Extruder must heat up first","type":"response"}
  {"message":"!! error: typ = WebRequestError, code = 10011710, message = filament hub feed filament timeout","type":"response"}
  {"message":"!! error: typ = WebRequestError, code = 10011711, message = filament hub unwind filament timeout","type":"response"}
  {"message":"!! error: typ = WebRequestError, code = 10011719, message = Filament is tangled","type":"response"}
  ```
  Přesně `10011710` (filament do hlavy nedorazil) je nejpravděpodobnější důvod pauzy B3; `FEED_FILAMENT ID=0 INDEX=3` před ním
  říká, ze kterého slotu. Ostatní řádky konzole: `leviQ3 auto_zoffset_on_off`, `cancel_request:stopping`,
  `!! Macro CANCEL_PRINT called recursively`, `SDCARD_RESET_FILE`.
- **`webhooks.state_message`** při shutdownu Klipperu (jako dřív). **`pause_resume.is_paused`**, `idle_timeout.state`.
- **Soubory:** `/server/files/directory?path=gcodes` dává `files[] {filename, size, modified}` a **`disk_usage {total, used, free}`**
  (U1: 358 souborů, 1,9 GB z 6,2 GB; U2: 261 souborů). `/server/files/roots` ukazuje `gcodes` =
  `/userdata/app/gk/printer_data/gcodes`. Stažení `GET /server/files/gcodes/<jméno>`, smazání `DELETE` tamtéž.
  `/server/history/list` nese dokončené úlohy s `print_duration`, `filament_used`, `status completed`.
- **Po dotištění:** `print_stats.state = complete` s názvem souboru zůstává **dny** (U1 od 25. 10., U2 podobně), i když obsluha
  výtisk dávno sundala a dialog na displeji potvrdila. Dialog „tisk dokončen“ tedy do Moonrakeru nic nezapíše; totéž
  `idle_timeout.state complete`. Databáze Moonrakeru má jen `gcode_metadata`, `moonraker`, `announcements`.

### 10b. Ovladač `moonraker`

- `telemetry.message` v každém hlášení (i mimo tisk): M117, `print_stats.message`, `webhooks.state_message` při shutdownu,
  `ACE <status>` když hub není `ready`, `ACE slot N <status>` pro sloty mimo `ready`, `ACE <state> N%` při zavádění, a při
  **pauze nebo chybě** chybové řádky konzole od začátku tisku (`now − print_stats.total_duration`), s příkazem
  `FEED_FILAMENT`/`T<n>`/`M600` před nimi: „last command FEED_FILAMENT ID=0 INDEX=3 LENGTH=80 SPEED=25 · error: typ =
  WebRequestError, code = 10011710, message = filament hub feed filament timeout“. Konzole se čte nejvýš jednou za 20 s,
  do 300 znaků, stejné věty jednou (hláška s kódem nahradí tu bez kódu). `job.message` = celá hláška u pauzy a chyby,
  u běžícího tisku jen `print_stats.message` jako dřív.
- `slot_status(slot)`: `filament_hub.slots[slot].status`, jinak `mmu.gate_status` (1 ready, 0 empty), jinak `None`.
- `files()`, `fetch()`, `delete()` přes `/server/files/directory`, `/server/files/gcodes/<jméno>`; jména jen holá
  (`safe_name`: bez lomítek, bez `..`).
- `bed_cleared_at`: přechod `complete|cancelled|error` (se souborem) → `standby` bez nového tisku = obsluha dotištěnou
  úlohu resetovala; čas se posílá 60 s v každém hlášení.

### 10c. Ovladač `anycubic_lan` (Kobra X)

- `telemetry.message`: `err_msg`/`error` z `info`, `project.err_msg`, slovo příznaku `pause` (1 paused, 2 pausing, 3 resuming,
  4 stopping), stav se slovem `filament` nebo `cutting` během výměny, a `msg (code N)` posledního `print/report` k souboru
  (odmítnutý start 10301, selhání; `kobra.py` zná 10107 „Filament broken. Please load new filament.“). Protokol **nenese
  text dialogů displeje** ani důvod pauzy; pauza bez důvodu zůstane „paused“.
- `slot_status(slot)`: `multiColorBox` status 5 → `loaded`, 4 → `ready`, jiné číslo → `status N` (agent odmítá jen
  `empty`/`runout`, takže Kobra X start nikdy neodmítne na základě odhadu). Neověřeno, co firmware hlásí u prázdné pozice.
- `files()`: `file listLocal {"path": ""}` (tvar kobra-connect `command_list_files`); Kobra X odmítla 10112 (18. 10.),
  ovladač vrátí `DriverError` s kódem a admin to vidí jako selhaný příkaz. `fetch`/`delete` protokol nemá. Potvrzení
  dotištění na displeji: v LAN hlášeních nic známého, ovladač `bed_cleared_at` neposílá.

### 10d. Agent

- `pause`/`resume`/`cancel`: po odmítnutí nebo vypršení volání čte stav až 60 s po 2 s (`CONFIRM_WAIT_SECONDS`); správný
  stav = `done` s textem „confirmed by the printer's state after N s; the call itself said: …“, jinak `failed` s původní
  chybou a „the printer's state did not change in 60 s“.
- Start: pro každý slot z `payload.filaments` (nebo `slot`) `slot_status`; `empty`/`runout` = `DriverError` před stažením
  G‑kódu (server pošle mail „start selhal“, zakázka ve frontě); `None` = poznámka „slot N not checked, the printer does
  not report its slots“ ve výsledku příkazu.
- Příkazy `files` (seznam → `POST /api/agent/printers/{key}/files`), `fetch {name}` (kontrola velikosti podle seznamu,
  300 MB, stažení do `work/<key>/fetched/`, `POST …/files/upload`, smazání místní kopie), `delete {name}` (nikdy soubor
  sledované úlohy ani soubor, který tiskárna právě tiskne; `.gcode.3mf` = `.gcode`), `cleanup {keep: […]}` (jen
  `^mp\d+-matplace-.*\.gcode(\.3mf)?$`, mimo `keep` a běžící; po úklidu pošle čerstvý seznam s `deleted[]` a `freed`).
- Testy: 93 (`python -m pytest agent/tests`; nové `test_messages_files.py`, `test_moonraker_messages.py`,
  `test_anycubic_lan_messages.py`; falešný Moonraker má `gcode_store`, `filament_hub`, adresář souborů, tichý resume).

### 10e. Server

- Migrace `2026_10_30_100000_farm_printer_messages_and_files`: `farm_print_jobs.message_at`, `messages` (JSON, posledních
  20 `{t, m}`, stejná slova za sebou jednou); `farm_printers.message`, `message_at`, `files` (JSON seznam + volné místo +
  poslední úklid), `files_at`. JSON na úloze místo tabulky: historie je omezená (20), čte se jen s úlohou, nic přes ni
  nehledá. Šířky stavů beze změny (`files`, `fetch`, `delete`, `cleanup` se vejdou do `type` 10).
- `AgentService`: `telemetry.message` → `printer.message/message_at` při změně (prázdné hlášení poslední slova nemaže,
  čas říká stáří; `slim()` pouští `message` do 300 znaků); `job.noteMessage()` při každém hlášení; **mail `paused`**
  (`notify_paused`, výchozí zapnuto, přepínač v `/admin/farm/settings`) jednou na pauzu (`paused_at` null → nastaveno),
  řádky Kde + „Hláška tiskárny: …“ nebo „tiskárna důvod neposlala“ + Co dál; druhá pauza po pokračování = nový mail.
  `bed_cleared_at` z telemetrie → `bed_clear = true` (jen novější než `bed_cleared_at`, ne starší než 6 h, bez aktivní
  úlohy) a `Dispatcher::kick` v témže syncu spustí frontu.
- API agenta: `POST /api/agent/printers/{key}/files` (seznam, `deleted`, `freed`), `POST …/files/upload` (multipart `file`
  + `name`, 300 MB = 307200 kB ve validaci, jméno holé, uloží do `storage/app/farm/printers/{id}/files/<jméno>`).
- Admin: `/admin/farm` u tiskárny „Hláška tiskárny <čas>: …“ (zvýrazněná, když ji agent hlásí právě teď), poslední příkaz
  dne s výsledkem, u běžící úlohy poslední 3 hlášky s časem; u zakázky celá historie úloh; stránka tiskárny
  (`/admin/farm/printers/{id}#files`, `partials/files.blade.php`, `FarmFileController`, `PrinterFiles`): Načíst seznam,
  Uklidit disk (N), tabulka jméno/velikost/datum s odznaky „náš“ a „běžící úloha“, Stáhnout na server / stáhnout do PC,
  Smazat z tiskárny (u běžící úlohy tlačítko není a server i agent to odmítnou), souhrn posledního úklidu, výsledky
  souborových příkazů za 24 h, kopie na serveru s odkazem ke stažení a „smazat kopii ze serveru“.
- Texty: `lang/{cs,en,es}/farm.php` `admin.message`, `admin.last_command`, `admin.command_status.*`, `admin.command_type.*`,
  `admin.files.*`, `admin.mail.paused`, `line_pause_reason`, `line_pause_none`, `line_next_paused`.
- Testy: `tests/Feature/FarmAgentMessagesTest.php` (7): pauza s hláškou = jeden mail + historie + obě stránky, druhá pauza
  = druhý mail; pauza bez hlášky = „tiskárna důvod neposlala“, přepínač vypne; historie 20; `bed_cleared_at` (staré
  ignorováno, start v témže syncu, opakování ignorováno, při tisku ignorováno, po dotištění znovu); seznam souborů +
  příkazy adminu (běžící soubor nikdy do delete, `keep` v cleanup, souhrn úklidu na stránce, ruční tiskárna odmítne);
  upload (velikost, jméno, token, stažení do PC, smazání kopie); odmítnutý start jako poslední příkaz na nástěnce.

### 10f. Neověřeno (bez tiskárny farmy a bez pauzy k vidění)

- Že při pauze B3 konzole skutečně nese `!! error … 10011710` (odvozeno z U2, kde ty řádky jsou po ručním zavádění).
- Jak dlouho Rinkhals drží volání `print/resume` a že stav `paused → printing` přijde do 60 s (z F26‑000108 jen „minutu tiskne“).
- Slova `empty`/`runout` ve `filament_hub.slots[].status` u skutečně prázdné pozice (z kódu `mmu_ace.py`, na U1/U2 vše `ready`).
- Co displej S1 ukáže po `T3` z prázdného slotu a zda dialog „resume/konec“ něco zapíše do `gcode_store`.
- Potvrzení dotištění na displeji: ověřeno jen, že `complete` zůstává dny; náhrada (reset dotištěné úlohy v Mainsailu/Fluiddu
  na telefonu, `http://<IP>:4409`, tlačítko Reset = `SDCARD_RESET_FILE`) nevyzkoušena naživo.
- Kobra X: `listLocal` jiným tvarem, hlášení při pauze a po potvrzení dialogu, status prázdné pozice v `multiColorBox`.
- Produkce: `upload_max_filesize`/`post_max_size` PHP a `client_max_body_size` nginx musí pustit 300 MB na
  `/api/agent/printers/*/files/upload`, jinak `fetch` skončí 413 (dnešní limit webu je 120 MB podle `max_upload_mb`).

### 10g. Instalace 0.2.9 (řídící)

Zip `C:\Users\roman\Desktop\matplace-farm-agent-0.2.9.zip` (18 souborů, bez config.yaml, stavěno `build_agent_zip.py`
jako 0.2.8). Na PC farmy: zastavit úlohu `matplace-farm-agent`, přehrát složku `farm_agent/` (žádné nové knihovny,
`requirements.txt` beze změny), spustit úlohu. Server: migrace (`php artisan migrate --force`), `config:clear`
(`notify_paused`), `view:clear`; bez buildu (žádné TS). Po nasazení v `/admin/farm/settings` zkontrolovat zaškrtnutou
„pauza tiskárny“ a na stránce tiskárny B3 kliknout „Načíst seznam“.

## 12. Ovladač `prusalink`: Prusa CORE One+ s INDX, 8 nástrojů (agent 0.2.11, větev `feature/agent-prusalink`, 4. 11. 2026)

Zadání `docs/prompts/prusalink-core-one-indx.md` (TODO #67). Bez tiskárny: Core One ten den tiskla a klíč API má jen Roman. Vše je
postavené podle specifikace PrusaLinku a zdrojů firmwaru Buddy a ověřené proti falešnému PrusaLinku v testech. Řídící přečetla
z Romanovy tiskárny čtyři odpovědi (firmware 8.0.0+17068, PrusaLink api 2.0.0, server 2.1.2) a jsou zapracované. Oddíl 11 patří
nesloučené větvi `fix/s1-start-heat`.

### 12a. API PrusaLinku (specifikace a firmware)

- Zdroje: `github.com/prusa3d/Prusa-Link-Web` `spec/openapi.yaml` (1.0.0-draft) a firmware `prusa3d/Prusa-Firmware-Buddy`
  (`lib/WUI/link_content/prusa_link_api_v1.cpp`, `basic_gets.cpp`, `prusa_api_helpers.cpp`, `lib/WUI/wui_api.cpp`,
  `lib/WUI/nhttp/status_renderer.cpp`, `gcode_upload.cpp`, `file_info.cpp`, `req_parser.cpp`, `status_page.cpp`,
  `src/state/printer_state.cpp`, `src/common/marlin_server.cpp`, `marlin_print_preview.cpp`, `tools_mapping.cpp`,
  `src/feature/compatibility_checks/gcode_compatibility.cpp`, `src/common/gcode/gcode_info.cpp`, `src/common/filename_type.cpp`,
  `include/buddy/filename_defs.hpp`), čteno 4. 11. 2026 z větve master.
- Ověření: hlavička `X-Api-Key: <heslo PrusaLinku>` (displej: Nastavení → Síť → PrusaLink → Heslo). Bez ní nebo se špatným
  heslem 401 a `WWW-Authenticate: ApiKey realm="Printer API"`. Totéž heslo platí pro HTTP digest s uživatelem `maker`
  (výzva `Digest realm="Printer API", nonce=..., stale=...`, bez `qop`). Na Romanově Core One klíč v hlavičce funguje
  (řídící 4. 11. 2026). Fóra píší o 403 „Bad X-Api-Key“ na některých firmwarech; pro ten případ je v ovladači `auth: digest`.
- `GET /api/v1/status`: `printer{state, temp_nozzle, target_nozzle, temp_bed, target_bed, axis_z, flow, speed, fan_hotend,
  fan_print}`, `job{id, progress, time_remaining, time_printing}` jen při tisku, `storage{path /usb/, name usb, read_only}`.
  `axis_x`, `axis_y` jen když hlava stojí. Slova stavu (`printer_state::to_str`): `IDLE, BUSY, PRINTING, PAUSED, FINISHED,
  STOPPED, ERROR, ATTENTION, READY`.
- `GET /api/v1/job`: 204 bez úlohy (`has_job()` platí jen pro PRINTING, PAUSED a ATTENTION; po dotištění tedy `FINISHED`
  ani `STOPPED` soubor nejmenují), jinak `{id, state, progress, time_printing, time_remaining, file{name = krátké jméno 8.3
  (`GREEK-~3.BGC`), display_name = pravé jméno, path, size, m_timestamp, refs.download}}`.
- `GET /api/v1/info`: `nozzle_diameter, mmu (false i s INDX), serial, hostname, min_extrusion_temp`. O nástrojích nic.
  `GET /api/version`: `api 2.0.0, server 2.1.2, firmware 8.0.0+17068, printer 7.10.0, capabilities.upload-by-put true`.
- Nahrání: `PUT /api/v1/files/usb/<jméno>`, tělo = soubor, hlavičky `Content-Length`, `Overwrite: ?1`, `Print-After-Upload:
  ?0|?1`. Odpovědi: 201; 409 `File is busy`, `Another transfer in progress`, `File already exists`, `Cannot print right now`
  (firmware píše „Can“ s apostrofem); 415 `Not a GCODE`; 431 jméno přes 167 znaků (`max_filename_length`); 507 plný disk
  nebo chyba zápisu; 411 bez délky. Firmware tiskne `.gcode .gco .g .gc` i `.bgcode .bgc` (`filename_is_printable`). Farmě
  tedy stačí obyčejný G-code, binární není potřeba (Romanovy soubory z PrusaSliceru jsou `.bgcode`, to je jeho výchozí export).
- Start: `POST /api/v1/files/usb/<jméno>` → 204, nebo 409 když tiskárna nemůže. `remote_print_ready` bere start ve stavech
  IDLE, READY, FINISHED i STOPPED, takže obrazovka „tisk dokončen“ start neblokuje.
- Řízení: `PUT /api/v1/job/<id>/pause`, `/resume`, `DELETE /api/v1/job/<id>` (stop) → 204; 409 ve špatném stavu; 404 cizí id.
- Soubory: `GET /api/v1/files/usb/` s `Accept: application/json` → `{type FOLDER, children[{name 8.3, display_name, type
  PRINT_FILE|FOLDER, m_timestamp, ro, refs{icon, thumbnail, download "/usb/<8.3>"}}]}`. Velikosti ani volné místo seznam
  nenese (velikost dá jen `GET` jednoho souboru). Právě tištěný soubor je `ro: true` a bez `download`. Stažení
  `GET /usb/<8.3>`, smazání `DELETE /api/v1/files/usb/<8.3>` (409 při tisku).
- Kamera: firmware žádné `/api/v1/cameras` nemá (to má jen PrusaLink na Raspberry Pi). Chyby: API nenese text chyby ani
  důvod pauzy, jen slovo stavu.

### 12b. Ovladač `prusalink` (`agent/farm_agent/drivers/prusalink.py`, agent 0.2.11)

- Volby `url`, `api_key` (jen v config.yaml), `auth` (`key` výchozí, `digest`), `username` (`maker`), `timeout` 15,
  `upload_timeout` 1800, `start_wait` 180, `start_poll` 3, `snapshot_url`. Žádný websocket, jen dotazy po `poll_seconds`.
  Digest dělá `aiohttp.DigestAuthMiddleware` (aiohttp 3.12+, `requirements.txt` povýšen; se starší knihovnou se pošle klíč).
- Stavy: IDLE a READY → `idle`; PRINTING a BUSY → `printing`; PAUSED → `paused`; ATTENTION → `paused` s hláškou „the printer
  waits for a touch on its screen (tools mapping, a filament question or an error)“, takže server pošle mail o pauze
  (§10e); FINISHED → `idle` a úloha `done`; STOPPED → `idle` a `cancelled`; ERROR → `error` a `failed` s hláškou, že text je
  jen na displeji. Telemetrie: `extruder`, `extruder_target`, `bed`, `bed_target`, `printer_state`, `z`, `speed`, `flow`,
  `fan_hotend`, `fan_print`, `remain_min`, `message`, `error`, `bed_cleared_at`.
- Úloha: jméno z `display_name` (ne 8.3), průběh a `time_printing` z `/api/v1/job`. Po konci firmware soubor nejmenuje,
  proto si ovladač poslední úlohu pamatuje (`<work_dir>/<key>.prusalink.json`, přežije restart agenta) a hlásí ji jako
  `done`, `cancelled` nebo `failed` podle slova stavu, dokud nezačne nová (jako Moonraker hlásí `complete`). Když tiskárna
  skočí rovnou do IDLE (obsluha klepla mezi dvěma dotazy), rozhoduje průběh: od 99 % `done`, jinak `cancelled` s poznámkou
  „ended at N % without a finished state“.
- Start: stav musí být `idle`; jméno musí mít tiskovou příponu a nejvýš 167 znaků; `PUT` s `Overwrite ?1` a
  `Print-After-Upload ?0`; `POST`. Pak čekání až `start_wait`: firmware po POST soubor nejdřív čte (hlásí IDLE), potom
  PRINTING (nahřívání, homing i MBL jsou už součást tisku), nebo ATTENTION (dialog na displeji; start se bere jako
  úspěšný a úloha je `paused` s důvodem, soubor je na tiskárně), ERROR = chyba startu. `slot` se jen zaloguje: nástroj si
  soubor volí sám (12d).
- `plate_confirmed`: přechod FINISHED/STOPPED → IDLE/READY (obsluha opustila obrazovku dotištění, nebo „Set ready“ na
  displeji či v Connectu) = `bed_cleared_at`, stejně jako u Rinkhalsu `complete → standby` (§10). Náš vlastní start tento
  přechod nespustí.
- `files`, `fetch`, `delete`: pravé jméno ↔ 8.3 ze seznamu (velikost 0 = neznámá, volné místo None). `slot_status` vždy None
  (API o nástrojích mlčí, filamenty hlídá firmware při startu). `snapshot` jen přes `snapshot_url` (třeba webkamera u PC
  farmy přes mjpg-streamer; do budoucna i obrázek z Prusa Connectu, ten ale vede přes cloud). `reset` odmítne: chybu
  potvrzuje displej. `head` None: polohy při pohybu API nedává, časosběr po vrstvách tedy na Core One nejde a u tiskárny
  má být vypnutý. `light` a `dry` nic.
- Testy `agent/tests/test_prusalink.py` (15) s falešným PrusaLinkem: stav a úloha s pravým jménem, špatný klíč (offline s
  `link: unauthorized`), nedostupná tiskárna, start s hlavičkami a čekáním, pomalé čtení souboru, tiskárna, která nezačne,
  ATTENTION, start odmítnutý s textem, špatná jména před odesláním, pauza a pokračování a zrušení podle id úlohy, dotištění
  a úklid podložky i po restartu agenta, konec rovnou v IDLE, chyba, soubory a stažení a mazání podle 8.3, digest. Celkem
  108 testů agenta (`python -m pytest tests` v `agent/`).

### 12c. Mapování nástrojů při startu: podmínka provozu bez obsluhy

Zjištěno ze zdrojů firmwaru a z dokumentace, na tiskárně nezkoušeno:

- PrusaLink startuje tisk voláním `print_start(soubor, PreviewSkipIfAble::all)` (`wui_api.cpp`, `wui_start_print`), stejně
  `POST` i `Print-After-Upload`.
- V náhledu tisku (`marlin_print_preview.cpp`, stav `checks_done`): na stroji s měničem nástrojů (`is_tool_mapping_possible`:
  víc než jeden nástroj povolen a soubor nějaký používá, tedy na INDX i u jednobarevného souboru) firmware spočítá zprávu
  kompatibility pro výchozí mapování 1:1. Když je `fully_compatible`, obrazovku mapování přeskočí a tiskne. Jinak čeká ve
  stavu `tools_mapping_wait_user`, PrusaLink hlásí `ATTENTION` a bez dotyku na displeji se nic neděje.
- Co se kontroluje (`gcode_compatibility.cpp`, `generate_toolmapping_only_noclear`), pro každý nástroj, který soubor
  používá: nástroj přiřazen a povolen (jinak Abort); dok zkalibrován; **typ filamentu v souboru (`; filament_type =
  PLA;PETG`, položka toho nástroje) se rovná názvu typu zavedeného u nástroje na tiskárně** (jinak Warning, pevně); průměr
  trysky z `; nozzle_diameter` (přísnost podle Nastavení → Hardware → Kontroly → Tryska); kalená tryska u abrazivního
  filamentu a HF tryska (`M862.1 T<n> P0.4 A<abraziv> F1`); **filament zavedený** podle čidla (jinak Warning); spool join.
  Barva se nekontroluje. Jakékoli Warning = obrazovka mapování. Prázdný typ v souboru kontrolu typu vynechá.
- Podmínka provozu: u každého nástroje, ze kterého farma tiskne, je na tiskárně zavedený filament se **stejným názvem typu**
  jako ve slicu (PLA, PETG, ASA, PC...; Prusa pojmenovává pružné FLEX, Orca píše TPU, proto u pružných pozor), filament
  je fyzicky zavedený a trysky jsou 0,4 HF (Romanovy jsou všechny 0,4 HF kalené). Pak tisk přes PrusaLink začne bez
  dotyku. Při neshodě ovladač start nezahodí: úloha je `paused` s hláškou o displeji, obsluha na tiskárně mapování potvrdí
  nebo tisk zruší.
- Dokumentace Prusy: článek „Tools Mapping (XL, INDX)“ (help.prusa3d.com/article/tools-mapping-xl_668277) říká, že
  obrazovka se otevírá při tisku z USB po „Continue/Print“ a že z Prusa Connectu „this feature is not available“; článek
  o stavu 31814 říká „Select Print to start the print with defaults“. Issue Buddy #4013 (MMU3 přes PrusaLink, zavřeno
  „not planned“ 22. 12. 2025): obrazovka mizí, když je filament zavedený přes „load filament advanced“, tedy se správným
  typem. To sedí se zdrojem.

### 12d. Co to znamená pro server (GcodeSlot, jedna podložka, věž)

Ověřeno na G-kódu z Orcy 2.5 (12e):

- Orca pro INDX píše výměnu jako odsazený řádek `  T1 S1 L0 D0` uvnitř skriptu výměny a start jako `T0 S1 L2 D0` a
  `T0 S1 L0 D0`. Žádný holý řádek `T<n>`, žádné `G9111` ani `M117`. `GcodeSlot::retarget` ani `retargetMany` proto **žádný
  nástroj nepřemapují** (0 řádků „matplace slot“); `retargetMany` jen vloží hlavičku `; matplace filaments`.
- S teplotami cívky obě **přepíšou 6 řádků `M104/M109`** ve startu Prusy (`M104 S120` před homingem Z, `M109 S170` sonda
  MBL, `M104 S175`, `S225`, `S220` v čištění) na jednu hodnotu a `M140/M190` na podložku cívky. To by rozbilo Prusovu
  choreografii teplot. Bez teplot `retargetMany` nemění nic kromě hlavičky.
- Proto u měniče nástrojů server soubor pro agenta nepřemapovává: `AgentController::gcode` a stažení v administraci
  (`FarmOrderController::gcode`) dají pro `isToolChanger()` originál řezu (pojistka v této větvi, `GcodeSlot.php` beze
  změny). Volbu nástroje musí udělat řez: díl na extruder `slot + 1` v 3MF a profil filamentu pro ten nástroj. Dnes řeže
  jednobarevný tisk vždy T0 (nástroj 1) a jedna podložka filamenty v pořadí dílů (T0, T1...), proto první zkoušky jen
  s cívkami v nástrojích 1 a 2 ve stejném pořadí. Přemapovat tokeny `T<n>` v `T<n> S1`, `M104 T<n>`, `M862.1 T<n>`,
  `M574 S<n>` by šlo, ale `G427` a bloky `is_extruder_used` jsou vyhodnocené při řezu. Doporučení pro session 2 a řídící:
  tool changer = řez s pravými čísly nástrojů, bez GcodeSlot.
- Firmware mapuje T<n> souboru na fyzický nástroj n (1:1); PrusaLink jiné mapování poslat neumí (Connect ano). Pozice
  farmy = číslo nástroje, slot 0 = Nástroj 1.
- Věž: `PrepareFarmOrder::ONE_PLATE_PROCESS` (věž 25 mm, `flush_into_infill`) je na INDX zbytečná, čistí se na stanici
  (`G12 S90`, `G12 S30`, blok `FLUSH_START`, 10 mm³ na výměnu, `filament_minimal_purge_on_wipe_tower`). Dvě kostky 15 mm:
  bez věže 2,24 g a 18 min, s přepisy farmy 4,49 g a 22,5 min. Cena jde z hlavičky, zákazník by věž zaplatil. Druh stroje
  „měnič nástrojů“ má tedy v kalkulaci smysl jen tady: u `isToolChanger()` nevnucovat věž a `flush_into_infill`. Ceny ani
  `engines.colors.change_minutes/flush_mm3/tower_g` (odhad kalkulačky u malovaných 3MF) neměním, rozhodne Roman.
- Buddy čte z hlavičky `; filament_type` (oddělovač `;`), `; filament used [g]` (`,`), `; extruder_colour`, `; nozzle_diameter`
  a řádky `M862.x`. Orca 2.5 je píše ve tvaru, který firmware čeká.

### 12e. Profily Orca 2.5 pro INDX 8T

- Zdroj: OrcaSlicer 2.5.0-alpha (16. 10. 2026; PR #15903 „CoreOne INDX 8T and 4T“ sloučen 4. 10., #16209 výměny podle
  PrusaSliceru 3.0 sloučen 6. 10.; Prusa bundle 02.04.00.17 podle PrusaResearch 2.5.10). Portable 2.5 balí dodavatele do
  `.opc` (magic `ZCRO`, není to zip), proto jsou vstupem soubory tagu `v2.5.0-alpha`
  (`resources/profiles/Prusa/{machine,process,filament}`), portable sloužil k řezu.
- Řetězec `inherits` a `include` rozbaluje `scripts/orca_flatten_profile.py` (rodiče, pak vlastní klíče každého `include`
  v pořadí, pak vlastní klíče presetu, jak to dělá `PresetBundle.cpp` 2.5). Chybějící klíče doplněny výchozími hodnotami
  Orcy 2.5 z `--export-settings` jednoho řezu: Orca 2.5 i 2.4 odmítají filament bez úplných vektorových klíčů
  („filament_is_support count 1 not equal to filament_colour size 2“). Soubory v `engines/orca/profiles`:
  `machine_coreone_indx8t.json` (name „Prusa CORE One INDX 8T HF 0.4 nozzle“, `printer_model` „Prusa CORE One INDX 8T“,
  248×205×270, 8 trysek 0,4 HF, `gcode_flavor marlin2`, `single_extruder_multi_material 0`, `purge_in_prime_tower 0`),
  `process_draft_` (0,25 mm Draft), `process_standard_` (0,20 Balanced), `process_detail_` (0,15 Detail), `process_fine_`
  (0,10 Fine) `coreone_indx8t.json`, filamenty `filament_pla_` a `filament_petg_coreone_indx8t.json` (Generic @Prusa CORE One
  INDX; `machineVariant` je k `machine_coreone_indx8t.json` najde sama), `filament_prusament_pla_` a
  `filament_prusament_petg_coreone_indx8t.json` (k ručnímu přiřazení). ASA ani TPU pro INDX Orca 2.5 nemá; Roman řekne,
  co v INDX má, a odvodí se stejným skriptem z profilu CORE One HF s `include fdm_filament_template_indx`.
- Zkouška řezu (dvě kostky 15×15×6 mm, PLA na nástroji 1, PETG na 2, 3MF z `ThreeMfWriter`, CLI jako farma:
  `--load-settings machine;process --load-filaments f1;f2 --arrange 0 --slice 0`, podložka Textured PEI): Orca 2.5 **řeže**,
  30 vrstev, `; total filament change = 30`, 31 řádků `T<n> S1 L0 D0`, 31 čištění na stanici (`G12 S90`, `G12 S30`,
  `;FLUSH_START`), `enable_prime_tower 0`, bez `G9111`, bez `M117`, `M862.3 P "COREONEINDX"`, `M862.1 T0 P0.4 A0 F1`,
  `; filament_type = PLA;PETG`, `; filament used [g] = 1.12, 1.13`, 18 min. `GcodeStats::fromFile` čte gramy po filamentu
  i počet výměn. S přepisy `ONE_PLATE_PROCESS`: věž 61 vrstev `;TYPE:Prime tower`, 4,49 g, 22,5 min. Jednobarevný řez:
  1,84 g, 10 min, také bez věže.
- Orca 2.4.0-beta (portable session 2, `59282dac…/scratchpad/orca24`) s těmito profily **padá** (segfault po načtení
  druhého filamentu, před řezem; log končí „skip scalar option inherits from filament 2“). Bez povýšení serveru na 2.5
  INDX neřeže. Dál: Orca 2.5 odmítá farmě profily filamentů s neprázdným `inherits` (`filament_pla.json`,
  `filament_petg.json`, `filament_tpu.json` a jejich `_kobra3max`, `_kobras1_n02`: `inherits: "Anycubic PLA @acbase"` →
  „can not resolve preset: Preset was not found in the loaded bundle“); před povýšením jim dát `inherits: ""`. Ověřil jsem
  jen to odmítnutí, ne řez S1 v 2.5. To je vstup pro řídící k povýšení serveru.
- Orca 2.5 ukládá do `; thumbnails` formát QOI; farma náhledy nepotřebuje.
- **Dva slicery vedle sebe (řídící, 5. 11. 2026, větev `feature/orca-bin-toolchanger`):** `config/engines.php` `orca.bin_toolchanger`
  (`ORCA_BIN_TOOLCHANGER`, prázdné = jako dřív). `OrcaSlicer::binFor(machineProfile)` vrátí tuhle binárku pro profil stroje se
  jménem `indx` (`OrcaSlicer::isToolChangerProfile`, totéž používá `FarmPrinter::isToolChanger()`), pro všechno ostatní `bin`;
  `assertAvailable` kontroluje tu, která poběží. `CachedSlicer::key` bere do klíče binárku, se kterou řez opravdu běží, takže
  klíče S1 a Kobra X se nemění a INDX má své. Server: Orca 2.5.0-alpha (AppImage Ubuntu 24.04 x86_64, 218 MB) rozbalit do
  `/opt/orca/squashfs-root-250a` vedle 2.4 (`/opt/orca/squashfs-root`), do `.env` `ORCA_BIN_TOOLCHANGER=/opt/orca/squashfs-root-250a/AppRun`;
  zbytek farmy dál řeže 2.4. Až INDX pár týdnů poběží, přepnout vše jedním `ORCA_BIN` (12i říká, že farmě profily v 2.5 projdou).
  Test `tests/Feature/OrcaBinToolchangerTest.php`.

### 12f. Web

- `FarmPrinter::isToolChanger()` (soubor profilu stroje se jménem `indx`), `positionRows()` 8 (stránka tiskárny nabídne
  u INDX rovnou 8 řádků, jinak 4, víc přes „+ přidat pozici“ session 2). Názvy pozic dává po sloučení s TODO #66
  `App\Domain\Farm\SpoolPositions::names()`: větev pro měnič nástrojů „Nástroj 1 až 8“ (`farm.admin.position_tool`),
  Kobra X „Stojan/ACE“, Kobra „ACE“, jinak „Pozice“; název, který admin napsal do `label` slotu, vítězí
  (`FarmPrinterSlot::positionLabel()`). Stránka založení cívek ukazuje totéž. U klíče je nápověda s názvy ovladačů
  (`farm.admin.driver_hint`). Texty `lang/src/prusalink.json`. Test `tests/Feature/FarmPrinterPositionsTest.php`;
  `FarmPagesTest` a `FarmColorsPerMachineTest` zelené.
- Druh stroje „měnič nástrojů“ bez nového sloupce; co by v kalkulaci měl měnit je v 12d.

### 12g. Živá zkouška (jen s Romanem, až tiskárna netiskne)

1. V routeru rezervovat IP tiskárny (192.168.8.60). Tiskárna je na Wi‑Fi; nahrání 50 MB trvá minuty, kabel je jistější.
2. Na displeji Nastavení → Síť → PrusaLink: zapnuto, opsat heslo. Heslo patří jen do `config.yaml` agenta (`api_key`).
3. `config.yaml` (PC farmy nebo zkušební agent na Romanově PC): blok `key: core-one-01`, `driver: prusalink`,
   `url: http://192.168.8.60`, `api_key: <heslo>`. Pak `venv\Scripts\python.exe -m pip install -r requirements.txt`
   (aiohttp 3.12) a `venv\Scripts\python.exe -m farm_agent --config config.yaml --check`: musí vypsat `idle` a teploty.
   `offline` s `link: unauthorized` = špatné heslo; když 401 trvá i se správným, `auth: digest`.
4. Administrace, tiskárna s klíčem `core-one-01`: režim agent, agent farmy, profil stroje `machine_coreone_indx8t.json`,
   procesy `{"draft":"process_draft_coreone_indx8t.json","standard":"process_standard_coreone_indx8t.json","fine":"process_fine_coreone_indx8t.json"}`,
   podložka texturovaná PEI, 248 × 205 × 270, tryska 0,4, časosběr vypnuto. Sloty: Nástroj 1 = cívka, kterou má tiskárna
   v nástroji 1 zavedenou se stejným typem (PLA), Nástroj 2 = cívka nástroje 2.
5. Server musí řezat Orcou 2.5 (12e): `ORCA_BIN_TOOLCHANGER` míří na rozbalenou 2.5.0-alpha, ostatní stroje zůstávají na 2.4. Bez toho řez pro tuhle tiskárnu selže (2.4 padá).
6. Malý jednobarevný objekt z farmy ze slotu 1. Sledovat: v logu agenta `uploading`, `starting`, `the printer took`;
   na displeji žádný dialog (jinak 12c); nástěnka `printing`, průběh a zbývající minuty; po dotištění `done` a po odklepnutí
   obrazovky na displeji `bed_cleared_at` na nástěnce. Pak pauza, pokračování a zrušení z adminu.
7. Dvoubarevný objekt (jedna podložka, cívky v nástrojích 1 a 2 ve stejném pořadí jako filamenty zakázky). Sledovat totéž
   a výměny na stanici. Bez úpravy `ONE_PLATE_PROCESS` (12d) tiskárna postaví věž navíc.
8. Když přijde `paused` s hláškou o displeji: na tiskárně je obrazovka mapování. Zkontrolovat typy filamentů u nástrojů,
   potvrdit, příště typy srovnat.

### 12i. Farmě profily filamentů bez `inherits` (pro povýšení serveru na Orcu 2.5)

- Devět profilů mělo `inherits` na preset, který v žádném bundlu Orcy není: `filament_pla.json`, `filament_petg.json`,
  `filament_tpu.json` a jejich varianty `_kobra3max` a `_kobras1_n02` („Anycubic PLA/PETG/TPU @acbase“). Orca 2.4 to jen
  nese s sebou (CLI 2.4 bere u filamentů z `--load-filaments` všechny hodnoty ze souboru, větev „update to newest“ platí jen
  pro filamenty z projektu 3MF), Orca 2.5 rodiče hledá a bez něj soubor odmítne. Změna: `"inherits": ""` v těch devíti
  souborech, nic jiného (jeden řádek na soubor). Všech 32 profilů prošlo `json.load` i kontrolou duplicitních klíčů.
- Ověření (`scratchpad/inherits_check.py`, kostka 10 mm `cube10.stl` z pokusu k TODO #65, CLI jako farma, podložka
  Textured PEI, `enable_support 0`): každý z devíti filamentů na svém stroji (S1 `machine.json` + `process_standard`,
  S1 0,2 `machine_kobras1_n02` + `process_standard_n02`, Kobra 3 Max `machine_kobra3max` + `process_standard`) a navíc
  dvoufilamentová podložka S1 (dvě kostky, dvakrát PLA, přepisy `ONE_PLATE_PROCESS`). Tři řezy na kombinaci: Orca
  2.4.0-beta před změnou, 2.4.0-beta po změně, 2.5.0-alpha po změně.
- Výsledek: **2.4 po změně = 2.4 před změnou** ve všech deseti kombinacích (normalizovaný G-code bez časových řádků a
  náhledů stejný bajt po bajtu, stejné vrstvy, gramy i posloupnost `M104/M109/M140/M190/G9111`). **2.5 proti 2.4:** stejný
  počet vrstev (50, 100 u trysky 0,2, 30 u podložky), gramy 0,00 % rozdíl u všech devíti kostek a 0,68 % u dvoufilamentové
  podložky (1,47/1,55 → 1,48/1,56 g), stejná množina i posloupnost teplotních příkazů, čas do 2 s u kostek, 1 h 10 min →
  1 h 13 min u podložky s věží. Tělo G-kódu se mezi 2.4 a 2.5 liší (jiná verze sliceru), ne kvůli profilům.
- PLA a PETG na jedné podložce odmítají 2.4 i 2.5 shodně („nozzle temperature must fall within the recommended range of
  the other filaments“, předvolba „Remove mixed temperature restriction“), to je stav i dnes a profily na tom nic nemění.
- Pro povýšení serveru tedy k profilům nezbývá nic; zůstává `machine_kobra3max.json` bez klíče `inherits` vůbec (2.5 bere
  jako prázdné, řez v 2.5 prošel).

### 12h. Soubory

`agent/farm_agent/drivers/prusalink.py`, `agent/farm_agent/drivers/__init__.py`, `agent/farm_agent/__init__.py` (0.2.11),
`agent/farm_agent/config.py`, `agent/requirements.txt`, `agent/config.example.yaml`, `agent/README.md`,
`agent/tests/test_prusalink.py`; `scripts/orca_flatten_profile.py`; `engines/orca/profiles/*coreone_indx8t.json` (9);
`app/Models/FarmPrinter.php`, `app/Http/Controllers/Farm/AgentController.php`, `app/Http/Controllers/Admin/FarmOrderController.php`,
`resources/views/admin/farm/printer_edit.blade.php`, `slots.blade.php`, `lang/src/prusalink.json`, `lang/{cs,en,es}.json`,
`tests/Feature/FarmPrinterPositionsTest.php`; `docs/TODO.md` řádek 67, `docs/prompts/prusalink-core-one-indx.md` odchylky.

### 12j. Nástroje podle pozic, věž pryč, výměny ve výšce, kontrola tisku s 8 barvami (TODO #70, větev `feature/indx-tools-by-position`, 6. 11. 2026)

Zadání `docs/prompts/indx-nastroje-podle-pozic.md` (session 2, noc na 6. 11.). Do rána A, B, C; D, E a F také hotové. Všechno
místně (falešný slicer, falešná binárka Orcy a skutečná Orca 2.5 portable), tiskárna ani server netknuté.

**A. Nástroje podle pozic (`PrepareFarmOrder::prepareOnePlate`).** U měniče nástrojů (`FarmPrinter::isToolChanger()`) je seznam
filamentů řezu v pořadí nástrojů: **filament n = pozice cívky n** (slot 0 = Nástroj 1 = `T0`), díl nebo skupina barev dostane
v 3MF `extruder` = pozice své cívky + 1, ať jsou díly v jakémkoli pořadí; díl bez cívky jde na nástroj hlavní barvy. Pozice mezi
použitými nástroji dostanou **zástupný filament** (`withPlaceholders`: profil a barva hlavní cívky, `placeholder: true`,
`slot_id` null, objem 0), protože Orca osloví extruder n jen mezi n načtenými filamenty; Orca je v G‑kódu nenahřívá, nekalibruje
ani nečistí (start stroje jde přes `is_extruder_used`, ověřeno: `M862.1 T<n>`, `M574 S<n>` a `M104 T<n> S0` na konci jen u
použitých, `G427 R2 P3` kalibruje jen použité). Gramy: Orca 2.5 píše `; filament used [g]` **pro každý načtený filament, mezery
0.00**, index = pozice, takže `part_plates[*].grams` se berou přímo podle pozice; `slice_params.filaments` nese všech n položek
(zástupné označené), `FarmOrder::filamentSlots()` dává `{pozice: pozice}` jen pro použité (např. `{0:0, 1:1, 4:4}`), start pro
agenta (`Dispatcher`) totéž, agent i správce dostanou **originál řezu** (12d). `OrcaSlicer::slice` počítá hlavičku proti počtu
skutečně použitých filamentů (zástupné nepočítá), `FakeSlicer` dělá výměny jen mezi filamenty s objemem a `T<n>` jen pro ně.
Stroje s ACE beze změny (`GcodeSlot::fileForMany` dál přemapovává; FarmOnePlate zelený). Od 8. 11. 2026 (TODO #72, AL.md
§10.13) nese projekt pro měnič u více kusů každý díl jako objekt s build item na kus (instance, `ThreeMfWriter` čtvrtý
parametr), bez pásu pro věž; `plate_copies`, `copies_grid` a zbytková podložka jako u malovaného souboru.

**B. Věž u měniče pryč.** `PrepareFarmOrder::TOOL_CHANGER_PROCESS = ['enable_prime_tower' => '0']` místo `ONE_PLATE_PROCESS`,
žádné `wipe_tower_x/y`, `slice_params.tower` null, `copiesPlan` a `PlateLayout::towerGrid(..., tower: false)` pás pro věž
nerezervují (kostka 75 mm na 248 × 205: 6 kusů místo 4). Čištění dělá stanice (`G12 S90`, `G12 S30`, `FLUSH_START`, blok
`Purge Station` v `change_filament_gcode`, protože `enable_prime_tower == false`). Rozdíl z 12e platí dál: dvě kostky
2,24 g a 18 min bez věže proti 4,49 g a 22,5 min s ní; dnešní řezy níž mají 0 řádků `;TYPE:Prime tower`.

**D. Výměny ve výšce na měniči = projekt s výměnou nástroje ve vrstvě (varianta 2).** Orca 2.5 čte ze 3MF soubor
`Metadata/custom_gcode_per_layer.xml` (`<layer top_z=".." type="2" extruder="n" .../>`, `<mode value="MultiAsSingle"/>`;
`ThreeMfWriter::write(..., $toolChanges)` / `toolChangesXml`) a udělá výměnu nástroje svým vlastním blokem (`T<n> S1 L0 D0`,
čištění na stanici), start nahřeje a zkalibruje oba nástroje. Ověřeno místně: kostka 10 mm, výměna v 5 mm → `total filament
change = 1`, `T0` 3× a `T1` 1×, výměna před vrstvou `;Z:5` („Change Tool0 -> Tool1 (layer 24)“), 0,61 + 0,74 g, 13 min.
Proto na měniči **každý tisk jednoho kusu i každá podložka dílu po dílech jde jako projekt** (`toolChangerParams`): síť
položená na střed podložky (`StlFile::place`; slicer centruje jen sítě, ne projekty), jeden objekt na nástroji hlavní cívky,
výměny z `FarmOrder::colorChanges()` jako výměny nástroje, filamenty podle pozic se zástupnými mezerami, `TOOL_CHANGER_PROCESS`.
Výška: `top_z = z + výška vrstvy / 2`, tedy od první vrstvy, jejíž střed je nad změnou, stejně jako `GcodeSlot::colorChanges`
u ACE (deska 3 mm, vrstvy 0,2: od vrstvy končící v 3,2). `slice_params.tool = {position, changes[{z, position}]}`
(`PrepareFarmOrder::toolPlan`) a `slice_params.filaments`; **platba řeže znovu** („reslice for the tools“), když se nástroj
hlavní barvy nebo výměny liší od toho, s čím byl soubor řezaný (`OrderFlow::pay`, `sameToolPlan`; porovnání hodnotou, JSON
píše 3.0 jako 3; zakázka řezaná před touto větví bez `tool` se řeže znovu jednou). Jednobarevný tisk z nástroje 1 bez výměn
jde touž cestou (projekt s jedním filamentem), aby záznam i porovnání byly stejné. Stránka zakázky kartu Core One u výměn
nabízí dál, nic se neschovává. Varianta 1 (vkládat blok výměny při předání) zamítnuta: start stroje nahřívá a kalibruje jen
nástroje, které řez použil (`G427`), dodatečně vložený nástroj by byl studený a nezkalibrovaný.

**C. Předletová kontrola tisku s 8 barvami.** Prošlo: `ColorLimit::of(Core One s 8 pozicemi)` = 8, `max_colors` 8 na stránce,
karta „Nástroj 1 až 8“, návrh 8 dílů → jedna podložka s 8 filamenty bez zástupných, `T0` až `T7`, 8 položek gramů, devátá
barva `too_many_colors` s číslem 8; jediný strop 4 zůstává jako výchozí `FarmOrder::MAX_COLORS` pro stroj bez pozic
a `?? 4` v `farm.ts`, strop `max_colors_ceiling` 16 nechán. Malované 3MF: `ColorLimit::farm()` = 8, jakmile je Core One
zapnutá a přes agenta (slučuje se až nad 8). Skutečný řez 8 kostek na 8 nástrojích Orcou 2.5 níže.

**Kontrolní seznam pro Romana (ráno 6. 11.):**
1. Tiskárna: ve všech osmi nástrojích cívka **stejného typu podle displeje** (PLA; firmware porovnává `filament_type` souboru
   s typem zavedeným u nástroje, 12c), filament zavedený (čidlo), trysky 0,4 HF, podložka texturovaná PEI, dok všech nástrojů
   zkalibrovaný. Displej bez otevřeného dialogu.
2. Administrace, tiskárna `core-one-01`: **pozice Nástroj 1 až 8 v pořadí nástrojů na tiskárně**, u každé správná barva a druh
   (PLA stejné rodiny; pozice s jiným druhem se u dílů nabídne jen zašedlá), dost gramů, zapnuto; profil stroje
   `machine_coreone_indx8t.json`, procesy `*_coreone_indx8t.json`, podložka texturovaná, časosběr vypnutý. Podložka prázdná
   a potvrzená (`bed_clear`).
3. Server: `ORCA_BIN_TOOLCHANGER` míří na Orcu 2.5 (12e); tato větev nasazená (bez ní jde filament i na nástroj i).
4. Zakázka: návrh po dílech nebo malované 3MF s 8 barvami, na stránce karta Core One „8 z 8 barev“, u dílů pozice Nástroj 1–8;
   po řezu v administraci odznak „jedna podložka · výměny n× · T0→slot 1 … T7→slot 8“ (T podle pozice, ne podle pořadí dílů),
   `filament used [g]` v souboru 8 položek.
5. Sledovat: start bez dialogu mapování (jinak `paused` s hláškou o displeji → na displeji potvrdit a srovnat typy), první
   výměnu na stanici (`G12`), že barvy sedí na díly (pozice ↔ nástroj), čas: každá výměna asi 20 s (8 kostek 15 mm = 210 výměn
   = 1 h 22 min, z toho tisk sám pár minut), gramy po nástrojích v hlavičce proti cívkám. Nástěnka: ventilátory v otáčkách
   (`fan_*_rpm`), u startu věta o nekontrolovaných pozicích (API je nehlásí) je v pořádku.

**E. Nástěnka.** Ovladač `prusalink` 0.2.12 posílá ventilátory jako `fan_hotend_rpm` a `fan_print_rpm` (PrusaLink dává
otáčky, Kobry procenta); `FarmCommand::resultText()` překládá známé poznámky agenta u „Poslední příkaz“ („slot n not
checked…“ → „Pozice 1, 2, 5 agent nekontroloval, tiskárna své pozice nehlásí…“, `lang/src/indx_tools.json`), ostatní nechává;
„Poslední příkaz“ sám byl česky už dřív. `agent.py` netknutý (jen ovladač), pytest 108.

**F. Profily INDX pro další druhy (neověřené na tiskárně, označené ve `filament_notes`).** `filament_asa_coreone_indx8t.json`
(ASA, 265/265 °C, texturovaná 105 °C místo 45 z vendor souboru Prusy, 26 mm³/s) a `filament_tpu_coreone_indx8t.json` (TPU,
230 °C, 50 °C, 2,5 mm³/s; na displeji Prusa říká FLEX, soubor TPU) jsou **syntetické** presety „Generic ASA/TPU @Prusa CORE One
INDX“ (inherits `Generic ASA @Prusa CORE One HF 0.4` / `Generic TPU @Prusa CORE One`, include `fdm_filament_template_indx`,
varianta „Direct Drive High Flow“) zploštělé `scripts/orca_flatten_profile.py`; `filament_abs_coreone_indx8t.json` a
`filament_pla_silk_coreone_indx8t.json` jsou Prusiny vlastní INDX presety (ABS 260/110 °C; Z‑smrštění 0,22 % Prusy Orca
odmítá, dáno 100 %). Všechny položené na klíčovou sadu `filament_pla_coreone_indx8t.json` (12e), JSON bez duplicit, Orca 2.5
s každým řeže (kostka ASA 10 min, TPU 11 min, ABS + PLA Silk na jedné podložce 23 min: měnič nástrojů míchá druhy, žádné
„mixed temperature“). `machineVariant` bere `filament_asa_*` pro ABS, ABS+, ASA i PC a `filament_tpu_*` pro TPU sám; silk
a abs se přiřadí ručně. **Řídící 6. 11. 2026 (Roman: profily ano):** aby se silk a ABS vybraly samy, přibyly základní kopie `filament_pla_silk.json` (= `filament_pla.json`, jiné jen jméno presetu) a `filament_abs.json` (= `filament_asa.json`, jiné jen jméno; typ zůstává ASA, na Anycubicích se tím nic nemění) a druhy PLA silk → `filament_pla_silk.json`, ABS a ABS+ → `filament_abs.json` (seeder + migrace `2026_11_06_100000_farm_materials_silk_abs_profiles`, jen řádky, které ještě ukazují na původní profil). PC zůstává na ASA. Na INDX pak `machineVariant` najde `filament_pla_silk_coreone_indx8t.json` a `filament_abs_coreone_indx8t.json`. Podklady: `@System` presety z `OrcaFilamentLibrary` tagu v2.5.0-alpha. Roman ráno řekne, co do INDX dá.

**Testy.** `FarmToolChangerPlateTest` (4): 3 díly na nástrojích 1, 2, 5 (extrudery 1, 2, 5; 5 filamentů, zástupné 3 a 4;
`filamentSlots` `{0:0,1:1,4:4}`; věž 0; start `filaments {0:0,1:1,4:4}`; agent dostane originál, `T4` ano, `T2` ne; odznak
„T4→slot 5“), 8 dílů na 8 nástrojích + devátá barva odmítnuta s 8, jednobarevný tisk z nástroje 2 (projekt na extruderu 2,
`tool {position 1}`, platba bez nového řezu) a deska s nápisem (výměna v 3 mm na nástroj 5 → `top_z 3.1 extruder 5`, po platbě
s černou z nástroje 2 „reslice for the tools“ a `extruder 2`), a **řez skutečnou třídou `OrcaSlicer` přes falešnou binárku**
`tests/Support/fake_orca.php` (obal .cmd/.sh, čte 3MF a píše hlavičku jako Orca 2.5; 5 souborů filamentů s barvami, proces
`enable_prime_tower 0`, projekt s extrudery 1, 2, 5, gramy `[1, 1, 0, 0, 1]`, 60 výměn; a jeden kus z nástroje 2 se 2 soubory).
`ToolChangerPlateTest` (4): zástupné filamenty, mřížka bez pásu, soubor výměn, hlavičky Orcy 2.5 s mezerami a s 8 položkami.
`FarmCommandResultTextTest` (2). Zelené dál: FarmOnePlate, FarmColorsPerMachine, FarmColorTiles, FarmDecimateCopies,
OrcaBinToolchanger, GcodeStats, GcodeSlot, OnePlateTower, FarmOrderFlow, ThreeMfColors, FarmPrinterPositions, FarmOrderColors,
FarmManualOnlyKinds, CalcToolColors = 110 testů, 1676 assertions, 4 přeskočené (Orca, výpisy stránek). pint, `npm run build`
(Blade), pytest agenta 108.

**Skutečné řezy Orcou 2.5 (portable, CLI jako farma `--load-settings machine;process --load-filaments f1;…;fn --arrange 0
--slice 0`, podložka texturovaná, `enable_prime_tower 0`, 3MF z `ThreeMfWriter`, kostky 15 × 15 × 6 mm, 0,2 mm):**
- 3 díly na nástrojích 1, 2, 5: `; filament used [g] = 1.29, 1.11, 0.00, 0.00, 1.10`, `; filament_colour =
  #F4F4F0;#101010;#F5F5F1;#F6F6F2;#C01818`, `; total filament change = 60`, 27 min 54 s, `T0` 32×, `T1` 16×, `T4` 15×, žádné
  `T2`/`T3`, `M862.1`/`M574`/`M104 T` jen T0, T1, T4, 61× `G12 S90`, 0 řádků věže.
- 8 dílů na 8 nástrojích: `; filament used [g] = 1.12, 1.29, 1.29, 1.29, 1.29, 1.29, 1.11, 1.27` (9,93 g), `T0` až `T7`,
  `total filament change = 210`, **1 h 21 min 48 s** (výměna asi 20 s), 211× čištění, `GcodeStats` čte 8 položek.
- výměna ve výšce (D): viz výše. Skripty a výstupy ve scratchpadu session (`indx70/slice.php`, `a3/`, `a8/`, `d/`).

**Soubory.** `app/Jobs/PrepareFarmOrder.php` (TOOL_CHANGER_PROCESS, withPlaceholders, toolPlan, sameToolPlan,
toolChangerParams, filamentRecord), `app/Engines/Mesh/ThreeMfWriter.php`, `app/Domain/Farm/PlateLayout.php`,
`app/Engines/Slicer/OrcaSlicer.php`, `app/Engines/Slicer/FakeSlicer.php`, `app/Domain/Farm/OrderFlow.php`,
`app/Models/FarmCommand.php`, `resources/views/admin/farm/dashboard.blade.php`, `lang/src/indx_tools.json`,
`agent/farm_agent/drivers/prusalink.py`, `agent/farm_agent/__init__.py` (0.2.12), `agent/README.md`, `agent/tests/test_prusalink.py`,
`engines/orca/profiles/filament_{asa,tpu,abs,pla_silk}_coreone_indx8t.json`, `tests/Support/fake_orca.php`,
`tests/Feature/FarmToolChangerPlateTest.php`, `tests/Unit/ToolChangerPlateTest.php`, `tests/Feature/FarmCommandResultTextTest.php`.

**Neověřeno / otevřené.** Skutečný tisk (zítra): mapování nástrojů bez dialogu, barvy na dílech podle pozic, čas výměn. Malované
3MF s mezerami mezi nástroji (repaint `colors_tool.py` s `map` na extruder 5 při chybějících 3 a 4) jen podle kódu, ne řezem.
Kalkulačka počítá podložky malovaného 3MF dál s pásem věže (`CalculationController`, stroj nezná) – konzervativní. Zástupný
profil u `machineVariant` je vždy INDX varianta hlavní cívky; dva druhy na jedné podložce měnič zvládne, nabídka dílů je ale dál
jedna rodina plastu. Agent 0.2.12 zip pro PC farmy nedělán (jen ovladač; verze navazuje na 0.2.11).

## 13. Zrušení a pauza potvrzené stavem tiskárny (agent 0.2.13, větev `fix/agent-cancel-confirm`, 12. 11. 2026)

Zadání `docs/prompts/agent-zruseni-pauza.md` (F26‑000158 na U2, 11. 11. 2026: zakázka na webu zrušená, tiskárna 15 minut
v pauze se souborem zrušené zakázky, nikde to nebylo vidět). Bez tiskáren: na 192.168.8.x se nesahalo ani čtením, log
agenta leží na PC domácí farmy. Podklady: tabulka událostí v zadání, uložené výpisy U2 z 3.–4. 11. v `C:\tmp\u2`
a ověřený řádek z `agent/README.md` (start S1: `progress` 0, `print_duration` se nuluje se začátkem první vrstvy).

### 13a. Zrušení (agent)

- `agent.py` `_control` → `_cancel`: zrušení je hotové, až když to řekne stav tiskárny. Po volání ovladače (ať odpověděl
  `ok`, nebo chybou) agent čte stav hned a pak po 2 s až 60 s (`CONFIRM_WAIT_SECONDS`). Hotovo = stav není `printing`
  ani `paused`, nebo úloha hlásí `cancelled` (stejná podmínka jako dřív).
- Tisk, který zůstal: druhý krok ovladače `cancel_fallback()` a dalších 20 s čtení (`FALLBACK_WAIT_SECONDS`).
  Moonraker: `POST /printer/gcode/script?script=SDCARD_RESET_FILE` (na U2 11. 11. převedl `paused` na `standby`;
  GoKlipper ho má i ve výpisu `/printer/gcode/help`, `C:\tmp\u2\u2-gcode-help.json`). Výsledek příkazu pak říká, co
  pomohlo: „cancel was answered ok, but the printer stayed paused for 60 s; SDCARD_RESET_FILE ended the print“.
- Nepomohlo ani to (stav se nezměnil, tiskárna krok odmítla, ovladač druhý krok nemá, tiskárna 60 s neodpovídá): příkaz
  končí `failed` s větou „cancel not confirmed: …; stop the print on the printer's screen“ a server pošle e‑mail (13c).
- Soubor, který není z farmy (`mp<id>-matplace-…` ani soubor sledované úlohy), druhý krok nikdy nedostane: tisk spuštěný
  na displeji se odsud silou neukončuje, příkaz selže s názvem souboru.
- Potvrzené zrušení agent hlásí jako `cancelled` hned v dalším hlášení (`Tracked.cancelled`, přežije restart agenta),
  i když tiskárna po `SDCARD_RESET_FILE` žádný soubor nejmenuje. Dřív by taková úloha po osmi hlášeních skončila jako
  `failed` („the printer no longer reports this print“), což u zrušení z administrace znamenalo zakázku „nepodařilo se“.
- Topení po zrušení (`_cool`): byl‑li před zrušením tisk nebo pauza, mají cíle trysky a podložky do 10 s spadnout na
  nulu. Jinak `heaters_off()` ovladače (Moonraker: `TURN_OFF_HEATERS`, při odmítnutí `M104 S0` a `M140 S0`) a poznámka
  „the heaters were left on (nozzle 220, bed 55), switched off with TURN_OFF_HEATERS“. Ovladač, který to neumí, nebo
  topení, které zůstalo: „the heaters are still on (…); switch them off at the printer“ a e‑mail provozovateli.
  `SDCARD_RESET_FILE` žádné makro zrušení nespouští, proto ta kontrola.
- Ostatní ovladače: `anycubic_lan` (Kobra X), `prusalink` i `mock` jdou stejným ověřením stavu. Druhý krok ani vypnutí
  topení `anycubic_lan` a `prusalink` nemají (nic bezpečného a ověřeného v protokolu není), nepotvrzené zrušení u nich
  selže s větou „has no second way to end a print“. `mock`: `cancel_stuck` (cancel odpoví ok, stav zůstane `paused`),
  `cancel_fallback` `works|fails|refused|none`, `heaters_stay`, `heaters_off` `works|none`.
- Výsledek příkazu se před odesláním krátí na 300 znaků (server delší odmítne 422 a příkaz by zůstal „u agenta“); z chyby
  samotného volání se do věty o zrušení bere nejvýš 110 znaků.

### 13b. Pauza a zrušení během přípravy tisku

**Zvoleno: pauzu při přípravě odmítnout hned, neodkládat.** Důvody: odložená pauza by držela zámek tiskárny (jeden
příkaz najednou), takže by za ní čekalo i zrušení, tedy přesně to, co má v té chvíli projít; musela by přežít restart
agenta a obsluha by neměla jak ji odvolat; a přišla by nečekaně o minuty později, kdy ji už nikdo nechce. Odmítnutí je
jedna podmínka před voláním: agent tiskárně nic neposílá a příkaz do jednoho hlášení selže s větou „the printer is
getting ready (heating, levelling); a pause will work once the first layer has started“, kterou administrace ukáže česky
(13c). Obsluha klikne znovu, až tisk poběží.

**Jak se příprava pozná.** `JobStatus.starting` nastavuje ovladač. Moonraker: stav `printing` a zároveň
`virtual_sdcard.progress` 0, `print_stats.info.current_layer` 0 nebo chybí, `print_stats.filament_used` 0. Musí platit
všechny tři, takže běžící tisk se za přípravu nevydává (nejpozději čisticí linka dá `filament_used` > 0).
`print_duration == 0` znak není: podle ověřeného řádku README během přípravy běží a nuluje se až s první vrstvou. Teploty
pod cílem znak také nejsou (při LeviQ je tryska na mezicíli 170 °C). Když příprava rozpoznána není, pauza jde jako dřív:
volání, 60 s čtení stavu, `failed`, jen s českou větou místo technické. Kobra X a PrusaLink `starting` neposílají (nikdo
nezměřil, zda pauzu při přípravě berou). Agent při každé pauze zapíše do `agent.log` řádek `pause asked: state, progress,
print_duration, filament_used, nozzle, bed, layer, starting`: z první zkoušky na U2 půjde ty tři znaky potvrdit nebo
opravit. **Tohle rozpoznání je odhad z dokumentace, na tiskárně neměřený** (zadání chtělo čtení z U2/U1 a z logu agenta;
obojí bylo této session zakázané, respektive nedostupné).

**Zrušení při přípravě** projde stejnou cestou jako každé jiné: volání, stav, případně `SDCARD_RESET_FILE`, potom kontrola
topení (tisk zrušený při nahřívání je přesně případ, kdy cíle zůstanou) a hlášení `cancelled`.

### 13c. Server (bez migrace)

- Stav „nepotvrzeno“ se nikam neukládá, čte se z příkazu `cancel` a ze stavu tiskárny (`FarmCommand::cancelState()`):
  `waiting` = příkaz čeká na agenta nebo je u agenta; `refused` = příkaz selhal a tiskárna je `printing`, `paused` nebo
  `unknown`. Konec: příkaz splněn, tiskárna hlásí jiný stav (obsluha zastavila na displeji), pozdější start nebo zrušení
  na téže tiskárně, nebo stáří 7 dní. Žádný nový stav ve sloupcích, `FarmStatusColumnsTest` se netýká.
- Administrace: na kartě tiskárny (`/admin/farm`) žlutý rámeček „Zrušeno, čeká na potvrzení tiskárny“ nebo červený
  „Tiskárna tisk nezrušila“ s číslem zakázky, českou větou a tlačítkem **Zkusit zrušit znovu** (příkaz `cancel` pro tutéž
  úlohu, i když je zakázka už zrušená); totéž pod stavem na stránce zakázky a v seznamu zakázek. Fronta na takovou
  tiskárnu dál nic nepošle (stav není `idle`), teď je ale vidět proč.
- E‑mail provozovateli (`alertAdmin`, druh `problem`, přepínač `notify_problem`): selhaný `cancel` → „F26‑…: <tiskárna>
  tisk nezrušila“ s řádky zakázky, českou větou, větou agenta a „Co dál“; splněný `cancel` s „the heaters are still on“ →
  „… má po zrušení zapnuté topení“. Obojí i do `Log::warning` (produkce loguje od warning).
- `FarmCommand::resultText()` překládá věty agenta o pauze, pokračování a zrušení (cs, en, es, `lang/src/agent_cancel.json`):
  pauza při přípravě, pauza nebo pokračování, které tiskárna nevzala (bez `TimeoutError`), nezrušený tisk, druhý krok,
  topení. Neznámé věty zůstávají, jak je agent napsal.
- Starý agent (do 0.2.12) hlásí `cancel` jako hotový hned jako dosud; server s tím počítá, jen nic neověří.
- Co server nedělá: příkaz `cancel`, na který agent nikdy neodpoví (agent spadl uprostřed), zůstane „čeká na potvrzení“
  bez e‑mailu; o tiskárně bez spojení píše dál jen `farm:watch`.

### 13d. Testy

- Agent: 126 testů (`python -m pytest tests` v `agent/`; venv s `requirements.txt`, `pytest` a `Pillow`); nový
  `tests/test_cancel_confirm.py` (18): cancel ok + stav paused → druhý krok → potvrzeno a úloha `cancelled`; druhý krok
  nic nezmění, je odmítnut, neexistuje → `failed` s důvodem; cizí soubor; restart po potvrzeném zrušení; pauza při
  přípravě odmítnuta do čtvrt vteřiny a po startu projde; zrušení při přípravě s vypnutím topení; skutečný ovladač
  `moonraker` proti falešnému Moonrakeru včetně přehrání incidentu (stop na displeji → `paused`, cancel `ok`,
  `SDCARD_RESET_FILE` → `standby`, `TURN_OFF_HEATERS`). Falešný Moonraker umí `cancel_ends`, `reset_ends`,
  `knows_heaters_off` a cíle teplot.
- Server: `tests/Feature/FarmCancelConfirmTest.php` (6 testů, 134 asercí): čekání na potvrzení na kartě, zakázce i v seznamu;
  nezrušený tisk (jeden e‑mail, červený rámeček, další zakázka nestartuje, zrušení znovu); konec varování; topení;
  věty o pauze česky a anglicky; věty o zrušení.

### 13e. Neověřeno na skutečných tiskárnách

- Že `SDCARD_RESET_FILE` pomůže i ze stavu `printing` a z pauzy, která nevznikla stopem na displeji (ověřen jediný případ
  z 11. 11.: `paused` po stopu na displeji).
- Že `TURN_OFF_HEATERS` GoKlipper provede (je ve výpisu příkazů U2, nikdy poslán) a v jakém stavu topení
  `SDCARD_RESET_FILE` nechá; kde po něm zůstane hlava a jestli se filament vrátí do ACE (makro zrušení se nespouští).
- Tři znaky přípravy na S1 (`progress` 0 po celou přípravu je z kalibračního tisku 22. 9.; `current_layer` a
  `filament_used` během přípravy nikdo nečetl, mimo jiné zda zavádění z ACE nepočítá do `filament_used`).
- Jak dlouho S1 po běžném zrušení drží stav `printing`/`paused` (makro zrušení, odjezd, vytažení filamentu): je‑li to
  déle než 60 s, přijde druhý krok zbytečně brzy.
- Kobra X a Core One: zda zrušení vždy skončí stavem mimo `printing`/`paused` do 60 s; pauza při přípravě.
- Farma B (jiná síť): nic z toho.

### 13f. Zkouška na U2 (Roman, po instalaci 0.2.13 a nasazení serveru)

1. Malý tisk na U2 z farmy (zkušební zakázka), potvrdit podložku, počkat na start.
2. **Pauza při přípravě:** do půl minuty po startu (nahřívání, vyrovnání) v `/admin/farm` kliknout Pauza. Čekáme: do pár
   vteřin u „Poslední příkaz“ věta „Tiskárna se připravuje…, pauza půjde za chvíli“, tisk běží dál. Kdyby místo ní po
   minutě přišlo „Tiskárna pauzu nepřijala…“, příprava se nepoznala: pošlete řádek `pause asked:` z `agent.log`.
3. Počkat na první vrstvu, **zastavit tisk na displeji tiskárny** (stop). Tiskárna zůstane v pauze.
4. **Zrušit zakázku na webu** jako zákazník. Čekáme: na kartě U2 žlutě „Zrušeno, čeká na potvrzení tiskárny“, asi po
   minutě rámeček zmizí, „Poslední příkaz: zrušení tisku · hotovo · Tiskárna na zrušení odpověděla, ale tisk 60 s držela
   dál… Ukončil ho až druhý krok (SDCARD_RESET_FILE)“, případně věta o vypnutém topení. Tiskárna „volná“, na displeji bez
   souboru, tryska i podložka chladnou. Žádný e‑mail.
5. Kdyby se objevil červený rámeček „Tiskárna tisk nezrušila“ a přišel e‑mail: druhý krok nepomohl. Zastavit na displeji
   a poslat `agent.log`.
6. Na konec: podívat se, kde stojí hlava a jestli je filament vytažený (13e), sundat výtisk, potvrdit podložku.

### 13g. Instalace 0.2.13 (řídící)

Zip `matplace-farm-agent-0.2.13.zip` (19 souborů, bez `config.yaml`, stavěno `build_agent_zip.py` jako dřív) leží ve
scratchpadu session (cesta v závěrečné zprávě); na plochu a na D: ho dá řídící. Na PC obou farem podle §5 a §10g: zastavit
úlohu `matplace-farm-agent`, přehrát složku `farm_agent/` (žádné nové knihovny, `requirements.txt` beze změny, `config.yaml`
beze změny), spustit úlohu. Server: jen kód, pohledy a texty (`lang/*.json`); **žádná migrace**, žádný build (bez TS), po
nasazení `view:clear` a `optimize` jako vždy. Pořadí je volné: nový agent se starým serverem funguje (věty anglicky), starý
agent s novým serverem také (zrušení se neověřuje).

**Soubory.** `agent/farm_agent/agent.py`, `drivers/base.py`, `drivers/moonraker.py`, `drivers/mock.py`, `__init__.py`
(0.2.13), `agent/README.md`, `agent/config.example.yaml`, `agent/tests/test_cancel_confirm.py`, `agent/tests/test_moonraker.py`;
`app/Models/FarmCommand.php`, `FarmPrinter.php`, `FarmOrder.php`, `app/Domain/Farm/AgentService.php`,
`app/Http/Controllers/Admin/FarmOrderController.php`, `resources/views/admin/farm/{dashboard,order,orders}.blade.php`,
`lang/src/agent_cancel.json` (+ `lang/{cs,en,es}.json`), `tests/Feature/FarmCancelConfirmTest.php`.

---

## 14. Tiskárna bez měniče: start bez kontroly slotů, hlášení počtu ACE (agent 0.2.14, větev `feature/printer-no-changer`, 13. 11. 2026) – bez tiskárny

Serverová část a důvody jsou v `docs/AL.md` §24 (přepínač „Měnič (ACE) připojen“ u tiskárny). Agent se změnil ve dvou
místech; **žádná tiskárna při tom nebyla oslovena a nic se netisklo.**

- **`_start` (agent.py):** příkaz `start` s `payload.direct = true` (server ho posílá u tiskárny s vypnutým přepínačem)
  **před startem nekontroluje žádný slot** (`slot_status`), ani `filaments`. Důvod: kontrola z 0.2.9 (F26-000108) se ptá
  ACE na pozici; bez ACE by se ptala na pozici, která není, a slovo `empty` by start odmítlo. Bez klíče (nebo
  s `direct: false`) je vše jako v 0.2.13. Ovladači se dál předává `slot` (u `moonraker` jen do logu; soubor pro takovou
  tiskárnu žádný `T` řádek nemá, `docs/AL.md` §24.4).
- **`moonraker.status()`:** `telemetry.ace_units` = počet jednotek v `filament_hub.filament_hubs`; 0, když objekt
  `filament_hub` existuje a jednotka žádná; klíč se neposílá, když firmware objekt nemá (holý Klipper). Server z toho
  ukazuje „Tiskárna hlásí: ACE připojené / žádné ACE“ a rozpor s přepínačem; nic sám nepřepíná.
- `anycubic_lan` (Kobra X) a `prusalink` beze změny: tyhle stroje přepínač nemají.

**Kompatibilita.** Nový agent + starý server: `direct` nepřijde, `ace_units` server zahodí jako neznámý klíč telemetrie
(jen ho vypíše mezi hodnotami na kartě tiskárny). Starý agent (≤ 0.2.13) + nový server: `direct` ignoruje a slot 1
zkontroluje; start projde, když tiskárna o slotu mlčí, a je odmítnut („slot 1 is empty…“), když firmware bez ACE řekne
`empty`. Proto na PC farmy s tiskárnou bez měniče **nejdřív 0.2.14, pak přepnout přepínač**.

**Testy.** `agent/tests/test_direct_spool.py` (7): bez značky prázdná pozice start odmítne; s `direct` start projde bez
poznámky, i když zkušební tiskárna o pozici říká `empty`, a i se seznamem `filaments`; `direct: false` kontroluje jako
dřív; `ace_units` 1, 0 (prázdný seznam, chybějící klíč, `null`), bez objektu klíč chybí. Celá sada: 150 testů OK
(`python -m unittest discover -s tests`).

**Neověřeno.** Zda GoKlipper/Rinkhals na S1 bez ACE objekt `filament_hub` vrací a s prázdným seznamem; co řekne
`slot_status(0)` (proto se neptáme); zda `print/start` bez ACE a se souborem bez `T` projde stejně jako s ACE (včetně
známého dlouhého mlčení volání, které ovladač už umí přečkat). Viz `docs/AL.md` §24.6.

**Instalace 0.2.14.** Jako §13g: zastavit úlohu `matplace-farm-agent`, přehrát složku `farm_agent/`, spustit úlohu;
žádné nové knihovny, `config.yaml` beze změny. Zip staví řídící (`build_agent_zip.py`) nebo je cesta v závěrečné zprávě.

**Soubory.** `agent/farm_agent/__init__.py` (0.2.14), `agent.py`, `drivers/moonraker.py`, `agent/README.md`,
`agent/tests/test_direct_spool.py`.
