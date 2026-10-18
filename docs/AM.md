# AM – ovladač `anycubic_lan` pro agenta farmy 0.2.0 (Kobra X v LAN režimu)

Session 4, 18. 10. 2026, větev `feature/agent-kobra-x` (z `main` 7d46a2d), zadání `docs/prompts/kobra-x-agent.md`.
Nic se nemerguje ani nenasazuje. Server se nemění.

## 1. Co je hotové

- `agent/farm_agent/drivers/anycubic_lan.py`, registrovaný jako `anycubic_lan` v `drivers/__init__.py`.
- `agent/tests/test_anycubic_lan.py`: 11 testů s falešným spojením a falešnou adresou pro nahrání. Celá sada agenta
  má 44 testů a všechny projdou (`python -m pytest agent/tests`).
- Verze agenta je `0.2.0`. Aktualizované jsou `requirements.txt`, `config.example.yaml` (blok Kobra X), `README.md`
  (oddíl „Kobra X…“ a konfigurace) a `config.py` (komentář).

## 2. Protokol: co jsem na Kobra X ověřila

Tiskárna 192.168.8.44 („Anycubic Kobra X“, `modelId` 20030, firmware 2.0.2.2, `ctrlType: lan`). Všechno v tomto
oddílu jsou jen čtení: handshake, dotazy a stream kamery.

| Co | Výsledek |
|---|---|
| handshake `GET :18910/info` → `/ctrl` → MQTT/TLS `:9883` | funguje přes `kobra_connect` (commit 3edba24) |
| `info` (téma `web/.../info`, akce `query`) | `state` (`free`), `temp`, `project` (při tisku) / `last_project`, `features`, `urls.fileUploadurl` = `http://IP:18910/gcode_upload?s=<token>`, `urls.rtspUrl` = `http://IP:18088/live/<id>` |
| `last_project` | `filename` (`….gcode` i u tisku ze `.gcode.3mf`), `state` `finished`, `print_time` v minutách (364 u tisku 5 h 51 min), `supplies_usage` v mm, `progress` 1 po dokončení (nepoužitelné, ovladač dává 100 %), `curr_layer`/`total_layers` |
| `print` query | žádná odpověď (prázdný report) |
| `tempature`, `fan` | odpovídají, ale `info` už to obsahuje |
| `file` `listLocal` (cesty `/`, `""`, `/local`, slicer i web) | odmítnuto: `code 10112`, „参数设定失败“ (nastavení parametru selhalo). Na Kobra X má jiný tvar a nikde není popsaný. Ovladač seznam souborů nepotřebuje. |
| `multiColorBox` `getInfo` | box −1 (vestavěný, 4 sloty) a box 0, každý s `slots[]` (`type`, `color`, `status` 5 = zavedeno, `consumables_percent`), `loaded_slot` −1 |
| kamera `rtspUrl` | HTTP-FLV, H.264, odpovídá i bez `video/startCapture`. Snímek přes PyAV (`av` 18.1) má 73 kB a 1280×720 (kamera tiskárny, nízký pohled). `:18910/camera/snapshot` vrací 404. |

**Nahrání souboru (bod 2 zadání).** Jak tiskárna chce soubor na `/gcode_upload` přijmout, není popsané nikde:
v `kobra-connect` (jeho most do Moonrakeru nahrávání výslovně nepodporuje), v Rinkhals, v `stribor/anycubic_kobrax`
ani v `chrisfore/anycubic_ha_local`. Řetězce v knihovnách Slicer Next to neprozradí a jeho logy jsou z doby
cloudového režimu. Ovladač proto používá nejpravděpodobnější tvar: `POST <fileUploadurl>` s multipart polem `file`,
pak MQTT `print/start` s `filename`, `filepath "/"`, `filetype 1`, `task_mode 1`, `taskid "-1"`. Potom 5 minut
čeká, než tiskárna začne tisknout, a když nezačne, ohlásí chybu. **Ověření na tiskárně čeká** (§4).

## 3. Jak ovladač pracuje

- Spojení drží ve vlastním vlákně (paho) přes `KobraLink`. Opakovaný pokus o spojení je každých 30 s (`retry_seconds`)
  a při každém dotazu, který spojení nenajde. Souběžné pokusy hlídá zámek: dřív se při startu otevřela dvě spojení,
  na skutečné tiskárně to byla vidět jako dvojí „connected“, teď je jedno.
- **Cloudový režim** (`ctrlType: cloud`): `status()` vrátí `offline` s `telemetry.link = "cloud_mode"`, příkazy
  hlásí „the printer is not in LAN mode“.
- **Mapování stavů:**
  - `project.state` `printing`/`preheating`/`busy`/`leveling`… → tiskne, `pause` 1 nebo 2 → pauza.
  - `finished` → hotovo, `stoped` (tak to píše firmware) → zrušeno, `failed`/`error` → chyba s `err_msg`.
  - Bez `project` je tiskárna `free`, tedy idle, a poslední úloha se bere z `last_project`.
  - Tvary dat pro server jsou stejné jako u Moonrakeru. Telemetrie `layer` a `remain_min` se počítá jako u Kobra S1.
- **Změna stavu** slyšená z MQTT reportu agenta hned probudí (`on_change`).
- `head()` vrací `None`: poloha hlavy v LAN protokolu není, časosběr jde po minutách. `dry()` je výchozí chyba
  (Kobra X sušičku nemá). `reset()` nic nevyčistí: chybu potvrdí obsluha na displeji, ovladač ji jen ohlásí.
- **Světlo** (`light` téma, typ 3) jen s `light: true`, na Kobra X neověřené.
- **Soubory v úložišti tiskárny ovladač nikdy nemaže.**

## 4. Zkouška startu na skutečné tiskárně – čeká

Řídící session předala 18. 10. Romanovo „CX je volná“ a produkční G-code kostky 10 mm
(`C:\Users\roman\Downloads\kx-cube10.gcode`, 106 138 B, ~5 min, `G9111 bedTemp=55 extruderTemp=225`,
`M104/M109 S225`, `T1`). Spuštění tisku z mé session ale zastavila kontrola oprávnění („Real-World Transactions“).
Souhlas předaný jinou session tam neplatí, musí přijít od Romana v tomto okně. Připravený postup (skript
`scratchpad/kx_drive.py` s ovladačem z této větve):

1. `start mptest-cube10.gcode`: nahrání, `print/start`, čekání na tisk. Zapíše odpověď `/gcode_upload`.
2. `watch`: průběh, vrstvy, zbývající minuty, snímek kamery.
3. `cancel`: `print/stop`, kontrola, že tiskárna je zase `free` a úloha `cancelled`.
4. Výsledek do tohoto oddílu: jestli Kobra X vzala `T1` (vestavěný box, slot 1 = oranžové PLA Silk, zavedeno) a
   `G9111` se zahřátím, a jestli se `GcodeSlot::retarget` pro Kobra X musí změnit.

Když `/gcode_upload` multipart pole `file` nevezme, jsou další na řadě: `print/start` s `url` na malý HTTP server
agenta, a nakonec poloautomat (obsluha pošle soubor ze Sliceru Next a agent ho spustí podle jména).

## 5. Instalace agenta na PC farmy (pro řídící)

- `requirements.txt` nově obsahuje:
  - `kobra-connect @ https://github.com/rvanderp3/kobra-connect/archive/3edba24….zip`, zip místo git, takže na PC
    farmy není potřeba git. Stáhne závislosti `paho-mqtt`, `cryptography`, `flatbuffers`, `websocket-client` a
    `requests` (`aiohttp` už je).
  - `av>=12`: kamera Kobra X. Wheel pro Windows obsahuje ffmpeg, samostatný ffmpeg netřeba. Zip agenta tím naroste
    jen o `requirements.txt`, balíčky se stáhnou při instalaci.
- Stávající instalace: `venv\Scripts\python.exe -m pip install -r requirements.txt`, pak restart služby agenta.
- `config.yaml` na PC farmy: blok `key: <klíč C1 v adminu>`, `driver: anycubic_lan`, `ip: 192.168.8.44`. Tiskárnu
  v `/admin/farm/printers` přepnout z `manual` na `agent` až po zkoušce v §4.
- Server: beze změny. `telemetry.link = "cloud_mode"` nástěnka ukáže jako neznámý klíč. Jestli ho má převést do
  věty, je to drobná změna nástěnky pro řídící.
