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
  nespuštěná) je idle. Telemetrie `layer`, `remain_min` jako u Kobra S1. Změna stavu z MQTT probudí agenta hned.
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
