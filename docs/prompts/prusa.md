# Zadání: připojení tiskárny Prusa do tiskové farmy matplace

Datum: 4. 10. 2026. Pracuj v repozitáři `C:\matplace-app` (Laravel 12), větev `feature/prusa` založ z `main` (`main` je
od 3. 10. 2026 nasazený na matplace.com). Pracuj ve vlastním worktree (`git worktree add C:\matplace-prusa-wt feature/prusa`),
ne přímo v `C:\matplace-app` — v něm pracují jiné sessions. Nikdy `git add -A`, nikdy `git stash`.

## Cíl

Do farmy přibude tiskárna Prusa. Zákazník nic nepozná: objedná, zaplatí, farma naslicuje pro Prusu, agent tisk spustí,
hlásí průběh, dokončení a spotřebu, admin vidí stav a snímky jako u Kobry. Nic z toho, co dnes funguje pro Anycubic
Kobra S1 / Kobra 3 Max, se nesmí rozbít.

Před začátkem si ode mě vyžádej (jedna zpráva, všechno najednou):
1. **model tiskárny** (MK4S / MK3.9 / MK3S+ / XL / MINI+ / Core One) a verzi firmwaru,
2. **jak je připojená**: PrusaLink v LAN (IP adresa, API key z menu tiskárny) — preferovaná cesta, agent mluví lokálně;
   PrusaConnect (cloud) jen jako záloha, pokud PrusaLink nepůjde,
3. zda má **kameru** (Prusa Camera / USB webkamera / žádná) a **MMU** (ne = jedna cívka = jeden slot),
4. **velikost trysky** a jaké materiály na ní poběží.

## Jak farma funguje dnes (přečti si to, než začneš)

- **Agent** (`agent/` v repu, nasazený na Romanově PC v `C:\farm-agent`, Python 3.11, běží jako skrytý proces): polluje
  server (`routes/agent.php`, `Api\AgentController`), stahuje G‑code, posílá příkazy tiskárně přes ovladač
  `agent/farm_agent/drivers/<driver>.py` (kontrakt v `base.py`: `status`, `start`, `pause`, `resume`, `cancel`,
  `snapshot`, `head`, `light`, `dry`). Dnes `moonraker` (Rinkhals na Kobře) a `mock`. **Prusa = nový ovladač
  `prusalink.py`**, konfigurace `driver: prusalink`, `url`, `api_key`, `snapshot_url` v `config.yaml`.
- **Server**: `FarmPrinter` (klíč, režim `agent`, `machine_profile`, `process_profiles[quality]`, `machine_overrides`,
  `process_overrides`, `nozzle_mm`, `bed_*`), `PrintProfile` (materiál × tiskárna × cívka), `PrepareFarmOrder`
  (slicování OrcaSlicerem, `engines/orca/profiles/*.json`), `GcodeSlot` (Kobra: `T<n>` pro ACE sloty, teploty do
  G‑code), `TimelapseGcode` (parkování hlavy po vrstvě pro časosběr), `Dispatcher` (výběr tiskárny podle podložky
  a materiálu), `OrderFlow`. Poznámky o farmě jsou v paměti projektu (`project_matplace_rebuild.md`), přehled
  kroků v `docs/`.
- Seeder `database/seeders/FarmSeeder.php` zakládá tiskárny a profily (Kobra S1 slot/ACE, Kobra 3 Max).
- Testy: `tests/Feature/Farm*.php`, agent má vlastní `agent/tests` (pytest). Lokálně `ENGINE_SLICER=fake`.

## Co udělat

**A. Ovladač PrusaLink v agentu** (`agent/farm_agent/drivers/prusalink.py`)
- PrusaLink HTTP API (hlavička `X-Api-Key`): stav `GET /api/v1/status` a `/api/v1/job`, nahrání souboru
  `PUT /api/v1/files/usb/<název>.gcode` (tělo = soubor; hlavička `Print-After-Upload: ?1` pro okamžitý start, nebo
  zvlášť `POST /api/v1/files/usb/<název>` jako start), pauza/obnovení/zrušení `PUT|DELETE /api/v1/job/<id>` s
  `pause`/`resume`. U MK3S+ (starší PrusaLink 0.7) jsou cesty `/api/job`, `/api/files/local` (OctoPrint‑like) —
  ověř podle firmwaru, který ti řeknu; pokud se liší, napiš obě varianty a vyber podle `api_version` při `connect()`.
- `status()` nikdy nehází: nedostupná tiskárna = `OFFLINE`; mapuj stavy IDLE/PRINTING/PAUSED/ERROR/FINISHED→IDLE
  s `JobStatus` (progress, `print_duration`, `filament_used` když API dá; jinak 0).
- `snapshot()`: když má kameru s HTTP snímkem (`snapshot_url`), stáhni JPEG; jinak `None`. `head()` vrať `None`,
  pokud PrusaLink pozici hlavy nedává — ověř, co dělá časosběr (`BuildFarmTimelapse`, agent), když `head()` je `None`,
  a ať to neskončí chybou (snímky bez synchronizace vrstev).
- `light()` tiše nic, `dry()` vyhodí `DriverError` (kontrakt to dovoluje) — Prusa nemá světlo ani sušičku.
- Soubor na USB tiskárny: unikátní název (`mp-<číslo zakázky>.gcode`), po dokončení/zrušení ho smaž
  (`DELETE /api/v1/files/usb/<název>`), ať se USB neplní. Při startu, když tiskárna není IDLE, vyhoď `DriverError`
  s hláškou pro obsluhu.
- Testy v `agent/tests` s falešným HTTP serverem (jako má moonraker), bez skutečné tiskárny.

**B. Server: profil tiskárny a slicování**
- `engines/orca/profiles/machine_prusa_<model>.json` + `filament_<pla|petg|asa|tpu>_prusa_<model>.json` odvozené
  z oficiálních Orca/PrusaSlicer profilů pro daný model (gcode flavor `marlin2`, start/end G‑code Prusy včetně
  `M862.3 P "<MODEL>"` kontroly modelu, podložka podle modelu, náhledy `thumbnails` v G‑code — Prusa je ukazuje na
  displeji). Procesní profily (`process_draft/standard/fine`) jsou společné; zkontroluj, že klíče specifické pro Kobru
  Prusu nerozhodí.
- `GcodeSlot::retarget` dnes **vždy** vkládá `T<n>` pro ACE; pro Prusu bez MMU se `T` vkládat nesmí — rozhoduj podle
  tiskárny (nový příznak, nebo podle `machine_profile`); teploty do G‑code pro zvolenou cívku zachovej.
- `TimelapseGcode` (parkování hlavy po `; AFTER_LAYER_CHANGE`): ověř, že značka vrstvy v Orca výstupu pro Prusu je
  stejná; parkovací souřadnice ber z podložky tiskárny, ne z konstant pro Kobru.
- `Dispatcher`/`PrintProfile`: Prusa bez MMU má jeden slot — jedna cívka na tiskárnu v `/admin/farm/printers`; druhá
  barva (QR kód, cedulka) se na ní nenabízí. Rozměry podložky podle modelu.
- `FarmSeeder`: přidat Prusu jako vzor (vypnutou); skutečnou založím v adminu. Kalibrační testovací výtisky
  (`FarmTuningTest`, `calib_tool.py`) musí jít spustit i na Pruse.
- Ceník: `hourly_rate`/`time_factor`/`weight_factor` tiskárny výchozí jako u Kobry S1; doladím v adminu.

**C. Admin a dokumentace**
- `/admin/farm/printers`: v nápovědě u režimu „agent“ doplnit PrusaLink (IP, API key z menu *Settings → Network →
  PrusaLink*). V `agent/README.md` a `agent/config.example.yaml` nový blok `prusalink` a sekce „Prusa“ (síť, API key,
  kamera, co dělat při chybě). `docs/PRUSA.md`: co vzniklo, rozhodnutí, co zbývá, jak nasadit (nový agent se kopíruje
  do `C:\farm-agent` a restartuje přes `run-agent.cmd`; server podle `docs/DEPLOY-BETA.md`).

## Pravidla

- Testy zelené: `vendor/bin/pint --dirty`, `php -d memory_limit=2G vendor/bin/phpunit` (celá sada ~15 min, na pozadí),
  `pytest` v `agent/tests`. Žádné volání ven v testech; PrusaLink v testech jen falešný.
- Nic nenasazuj a nepushuj bez mého pokynu. Nasazení uděláš po schválení krok za krokem (server `/var/www/matplace-app`,
  merge `origin/main` s identitou `deploy`, `php artisan optimize`, restart `php8.2-fpm matplace-worker`).
- První skutečný start na Pruse uděláme spolu s testovacím výtiskem (`/admin/farm/tuning`), ne se zakázkou zákazníka.
- Když je zadání v rozporu s kódem nebo chybí údaj, který nejde zjistit z tiskárny ani z repa, zeptej se v jedné
  zprávě najednou; jinak rozhodni sám a zapiš rozhodnutí do `docs/PRUSA.md`.
