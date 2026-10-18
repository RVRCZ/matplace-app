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
  změny vrstvy sleduje z `print/report` i z `info` (od vrstvy 2, první značku farma přeskakuje) a po dobu
  `layer_window` (výchozí dwell + 1 s) vrací `head()` = park jako *plánovanou polohu* (rychlost `None`). Agent pak,
  jako u Kobra S1 bez `motion_report`, nafotí sérii snímků (okno 2,5 s + dwell + 0,8 s, po 0,3 s) a pošle nejklidnější.
  Agent se nemění. Návrh: C1 `dwell` 3000 ms v adminu (parametr tiskárny), až to měření potvrdí (§7d).

### 7d. Ověření na tiskárně – čeká na Romanovo svolení v okně session 4

1. Světlo: `light(True)`/`light(False)` a `light query` mezi tím.
2. Sušení: `dry(True, 45, 10)`, `getInfo` po minutě (teplota, zbývající čas, jednotka), `dry(False)`.
3. Parkovací kostka `kx-cube10-park.gcode` (od řídící; dwell 1000) a varianta s dwell 3000 (`G4 P3000`, hlavička
   `dwell=3000`): start přes ovladač, při každé ohlášené změně vrstvy série snímků po 0,3 s s časem od hlášení,
   výběr nejklidnějšího (`pick_still` z agenta); kolik z 49 parků má vybraný snímek hlavu v parku.
