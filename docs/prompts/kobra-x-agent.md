# Zadání pro session 4: ovladač `anycubic_lan` pro agenta farmy (Kobra X v LAN režimu), agent 0.2.0

Výsledek zkoušky 18. 10. 2026 (řídící, z Romanova PC, stejná síť): Kobra X na **192.168.8.44** odpovídá protokolem K3
LAN přes `kobra-connect` (Apache‑2.0, https://github.com/rvanderp3/kobra-connect): porty 18910 (HTTP handshake
`GET /info` → podepsaný `POST /ctrl` → AES) a 9883 (MQTT/TLS) otevřené; `query_info()` vrátil `name='Anycubic Kobra X'`,
`firmware='2.0.2.2'`, `state='free'`, teploty, ventilátory, `features` mimo jiné `gcode_3mf_support`,
`camera_timelapse_support`, `pre_cancel_support`, `preheating_support`. Skript a klient: `C:\Users\roman\Desktop\kobra-x-test\`
(klient je nainstalovaný do uživatelského Pythonu 3.11 na Romanově PC: `kobra_connect` s moduly `client`, `handshake`,
`models`). Dnes je Kobra X na farmě v ručním režimu (`mode=manual`, kód C1, profil `machine_kobrax.json`).

## 0. Kde pracuješ a pravidla

- Tvůj worktree, větev **`feature/agent-kobra-x`** z aktuálního `main`, dokument **`docs/AM.md`**.
- Soubory: `agent/` (nový `agent/farm_agent/drivers/anycubic_lan.py`, registrace v `drivers/__init__.py`,
  `config.example.yaml`, `README.md`, `requirements.txt`, verze `__version__` → `0.2.0`, testy `agent/tests/`),
  server jen pokud ovladač potřebuje něco nového v protokolu agenta (`app/Http/Controllers/Farm/AgentController.php`,
  `app/Domain/Farm/…`), a to po domluvě s řídící session. Nesahat: slicer a profily, `GcodeSlot` (jen hlásit),
  nástroje, kalkulace.
- Pravidla: `git add` jmenovitě, žádný stash, testy agenta `python -m pytest agent/tests`, PHP testy jen při změně
  serveru; nemergovat, nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c). Zip agenta pro PC farmy
  staví řídící (`scratchpad/build_agent_zip.py`), ty jen řekneš, co se v instalaci mění (balíček `kobra-connect`).
- **Skutečná tiskárna**: z tohoto PC ji vidíš (192.168.8.44), ale je Romanova a tiskne zákazníkům: čtení stavu kdykoli;
  každý příkaz (start, teploty, zrušení) jen na malém zkušebním souboru a jen když Roman řekne, že je volná (zeptej se
  ho přes řídící session nebo přímo v okně). Nikdy nemazat soubory v úložišti tiskárny.

## 1. Co ovladač umí (rozhraní `PrinterDriver` v `drivers/base.py`)

- `connect`/`close`: handshake + MQTT (`KobraClient`), automatické znovupřipojení, chyba `CloudModeError` → stav
  „tiskárna není v LAN režimu“ srozumitelně do nástěnky (přes `PrinterStatus`).
- `status()`: stav (`free`/`printing`/`paused`/`error`), teploty, průběh (%), vrstva, zbývající minuty, název souboru,
  chybový text; mapování na `JobStatus`/`PrinterStatus` stejně jako u Moonrakeru (viz `moonraker.py`), nic nového ve
  tvarech dat pro server.
- `start(gcode_path, filename, slot)`: **nahrání souboru a start** – to je hlavní otázka (bod 2). `slot` (ACE) u Kobry X:
  má ACE? Zjisti z `features`/`/info`; když ne, slot ignoruj, cívku hlídá obsluha.
- `pause`, `resume`, `cancel` (`command_pause/resume/cancel`), `reset` (co K3 nabízí při chybě; jinak jen hlásit).
- `head()`: souřadnice hlavy, pokud je LAN hlásí (jinak `None`, časosběr po vrstvách pak nejde, zůstane po minutách).
- `snapshot()`: obrázek z kamery tiskárny, pokud K3 LAN něco dává (RTSP/MJPEG adresa v `/info` nebo MQTT `video`
  témata); jinak `None` a časosběr se nedělá (nástěnka to ukáže jako „bez kamery“). `camera_timelapse_support` znamená,
  že si tiskárna dělá vlastní časosběr do úložiště: zjisti, jak soubor vzniká a jde-li stáhnout (bod 2).
- `light`, `dry`: pokud protokol umí, jinak no‑op s logem.

## 2. Nahrání souboru: zjisti, co protokol dovolí, v tomhle pořadí

1. **Příkaz `print/start` s adresou souboru**: v cloudovém protokolu Anycubicu nese start tisku `url` + `md5` + `filesize`
   a tiskárna si soubor stáhne sama. Zkus v LAN režimu totéž s `url` na malý HTTP server v agentovi (na PC farmy, port
   z configu), `filetype` podle klienta (1 = místní soubor, zjisti hodnotu pro URL). Když to tiskárna vezme, je to
   nejjednodušší cesta a nic se nekopíruje ručně.
2. **MQTT téma `file`**: klient umí `listLocal`; prověř další akce (`download`, `upload`, `delete`, `rename`), jaké
   tiskárna přijme (odpověď v `file/report`). Zdroje: kód `kobra_connect`, projekty „kobra-unleashed“ a protokol
   Anycubic LAN mode na GitHubu, provoz Anycubic Slicer Next v LAN režimu (na Romanově PC jde zachytit, jak slicer
   soubor posílá: Wireshark nebo log sliceru; jen pozorovat).
3. **Když nic z toho**: poloautomat: obsluha dá soubor na USB nebo ho pošle slicerem, agent ho najde v úložišti podle
   jména (`command_list_files`), spustí, hlídá stav, zruší, hlásí; nástěnka řekne „soubor nahraj ručně: <název>“.
   Napiš do AM.md, kterou cestu jsi ověřil a jak.

## 3. Konfigurace a nástěnka

- `config.example.yaml`: blok `driver: anycubic_lan`, `ip: 192.168.8.44`, volitelně `serve_port` pro HTTP výdej souborů,
  `snapshot_url`, poznámka „tiskárna musí být v LAN režimu, odpojí se od cloudu Anycubic“.
- `README.md` agenta: jak zapnout LAN režim, jaký balíček přidat (`pip install kobra-connect` z GitHubu, verze/commit
  přišpendlit v `requirements.txt`), co ovladač umí a neumí.
- Server: tiskárna přepnutá z `manual` na `agent` v `/admin/farm/printers` (řídící); zkontroluj, že `GcodeSlot::retarget`
  (T‑řádek za `M117`, `G9111`) sedí s tím, co Kobra X přijme: Anycubic Slicer Next pro Kobra X píše stejný začátek jako
  S1 (docs/V.md §6), ověř na skutečném startu malého souboru, jestli K4 vezme náš `T<n>` a zahřátí.

## 4. Testy a ověření

- `agent/tests/test_anycubic_lan.py`: falešný klient (bez sítě) → mapování stavů, průběhu, chyb, start přes zvolenou
  cestu, výpadek spojení a znovupřipojení, cloud mode hláška.
- Skutečná tiskárna (po Romanově „volná“): handshake, stav, malý zkušební soubor (kostka 10 mm, 5 minut) nahraný
  zvolenou cestou, start, průběh na nástěnce, zrušení, případně snímek z kamery. Výpis a snímky do AM.md.
- **Hotovo =** push `feature/agent-kobra-x`, AM.md, testy, zpráva řídící session s tím, co se mění v instalaci agenta
  na PC farmy (balíčky, config) a zda je potřeba změna na serveru.
