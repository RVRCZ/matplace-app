# Zadání: rychlost výpočtu a slicování na serveru

Datum: 4. 10. 2026. Pracuj v repozitáři `C:\matplace-app` (Laravel 12) ve **vlastním worktree**
(`git worktree add C:\matplace-perf-wt feature/perf`, větev z `main`; `main` je nasazený na matplace.com). Nikdy
`git add -A`, nikdy `git stash` (stash je společný s jinými sessions). Na server (`ssh -i ~/.ssh/hetzner_matplace
root@178.104.162.164`, aplikace `/var/www/matplace-app`) se **jen dívej** – měření, logy, `ps`, `top`; nic tam
neměň a nenasazuj, nasazení dělá Roman po schválení (postup v `docs/DEPLOY-BETA.md`: merge `origin/main`
identitou `deploy`, `migrate --force`, `npm run build`, `optimize`, restart `php8.2-fpm matplace-worker`).

## Cíl

Zákazník nahraje model a čeká na „Přesný výpočet“ (kalkulace) nebo na přípravu zakázky farmy (kontrola, oprava,
natočení, slicování, cena). Chci, aby to bylo **znatelně rychlejší a hlavně stabilní pod zátěží** (víc lidí
najednou, dlouhé tisky), aniž by se změnil výsledek: stejný G‑code, stejná cena, stejné testy. Nejdřív **měř**,
pak optimalizuj, pak dokaž čísly.

## Jak to dnes funguje (přečti si kód, tohle je jen mapa)

- **Server**: Hetzner, 4 vCPU (AMD EPYC Rome), 7,7 GB RAM, disk 75 GB (46 % plný), průměrná zátěž ~1.
  Fronty: `jobs` v MySQL; **3 workery** (`matplace-worker` s `--queue=default,ai`, `matplace-worker@2` a `@3` jen
  `default`; všechny `--memory=512 --max-time=3600`; unit `deploy/matplace-worker.service`). Fronta `ai` nese
  dávky katalogu (`TranslateCatalogModel`, `ClassifyModel`), aby neblokovaly zákazníky.
- **Kalkulace** (`/api/calculations`, `App\Domain\Calculation\CalculationService`): hrubý odhad hned
  (`RoughEstimator` z objemu/plochy), pak job `SliceCalculation` (default queue, čeká re‑queue na zpracování
  souboru, `timeout 400`) → `OrcaSlicer::slice` → `applySlice` (cena přes `PriceEngine`). Za posledních 7 dní
  157 hotových, **průměr 18 s, max 96 s**, 5 selhání.
- **Soubor** (`/api/uploads`, `ModelFile`): konverze (3MF/STEP… → STL přes `engines.converters`), analýza
  (objem, plocha, bbox) Python nástroji (`engines/python/mesh_tool.py`, trimesh, přes `App\Engines\Repair\PythonTool`
  – **nový proces Pythonu na každé volání**, import trimesh/cadquery pokaždé), náhled (`render_tool.py`), kontrola
  (`ModelCheck`), oprava (`repair_tool.py`).
- **Farma** (`App\Jobs\PrepareFarmOrder`): stage `checking` → `PrintPreparer` (`PythonPrintPreparer` = `farm_tool.py`:
  oprava, natočení, umístění; obalené `CachedPrintPreparer` – klíč sha1(md5 STL, měřítko, podložka, verze
  nástrojů), cache `storage/app/prepared`, 14 dní) → stage `slicing` → `OrcaSlicer::slice` (víc kusů = i druhý
  slice zbytku plátu) → `GcodeSlot` (sloty ACE, teploty), `TimelapseGcode` → cena. Zakázka z nahrání do „sliced“
  dnes **15–130 s**, reslice (jiná kvalita/barva) 16–45 s. Oprava poškozeného modelu s 611k trojúhelníky trvala
  2 minuty (proto cache).
- **OrcaSlicer 2.4.0‑beta** jako AppImage (`/opt/orca/squashfs-root/AppRun`) pod `xvfb-run -a`, každé slicování =
  nový pracovní adresář (`storage/app/slicer/job-…`), zapsané/patchované profily (`engines/orca/profiles/*.json`,
  per‑tiskárna `machine_*.json`, `process_*`, `filament_*`), STL přepsaný kvůli měřítku a umístění
  (`StlFile::scale/place` v PHP), CLI `--arrange 0 --slice 0`, pak `GcodeStats` parsuje **celý G‑code načtený do
  řetězce** (`File::get`) – u 100 h tisku jsou to desítky MB. Když model bez podpěr neprojde, slicuje se **podruhé
  s podpěrami** (dvojnásobný čas). G‑code se ukládá do `storage/app/slicer/gcode/` (dnes 1,9 GB) a maže ho
  `matplace:prune`. `ORCA_TIMEOUT=600`.
- Testy: `php -d memory_limit=2G vendor/bin/phpunit` (celá sada ~16 min, 371 testů; v testech
  `ENGINE_SLICER=fake`), `tests/Feature/Farm*.php`, `tests/Feature/CalculationTest.php`, `SlicerTest`… Python:
  `engines/python/tests`. Pint: `vendor/bin/pint --dirty`.

## Co udělat

**1. Měření (první commit, nasadit jako první).** Bez čísel nic neoptimalizuj.
- U každé kalkulace a zakázky farmy ukládej **časy fází** (čekání ve frontě, konverze, analýza, oprava+natočení,
  slice 1 / slice 2, parsování G‑code, cena) – do JSON sloupce (`calculations.timings`, `farm_orders.timings`) nebo
  do `events`, ať se to dá číst zpětně. Zapisuj i velikost STL (trojúhelníky), minuty tisku, zda byl druhý slice.
- Příkaz `php artisan matplace:perf-report [--days=7]`: percentily (p50/p90/max) po fázích, čekání ve frontě,
  počet dvojitých slicování, podíl cache hitů přípravy, špičky (kolik jobů současně). Krátce i do `/admin/stats`
  nebo `/admin/farm` (tabulka „rychlost“).
- Na serveru si (jen čtením) ověř, kde se čas tratí: `top` během slicování (Orca je vícevláknová – tři workery
  na čtyřech jádrech se perou), čas startu Orcy naprázdno (`xvfb-run AppRun --help`), čas importu trimesh
  (`python -c "import time,trimesh"`), I/O (`storage/app/slicer`).

**2. Optimalizace podle naměřeného.** Kandidáti, které očekávám (ověř, že je to pravda, než je uděláš):
- **Čekání ve frontě**: oddělit interaktivní práci (kalkulace, příprava zakázky) od dávek – fronty
  `interactive` / `default` / `ai`, worker, který bere nejdřív interaktivní; `SliceCalculation` nemá čekat
  40× po 3 s na soubor, ale být spuštěn, až je soubor hotový (řetězení jobů / dispatch z konce zpracování souboru).
- **Souběh Orcy**: omezit na 1–2 současné slice (semafor přes cache lock / `WithoutOverlapping` per‑Orca),
  případně `--threads`/`OMP_NUM_THREADS`, aby tři slice najednou nebyly pomalejší než po sobě.
- **Dvojitý slice kvůli podpěrám**: rozhodnout o podpěrách předem z geometrie (farm_tool už měří převisy)
  nebo zkusit podpěry rovnou tam, kde je převis > X; druhý běh jen výjimečně.
- **Cache slicování**: stejný STL (hash) + stejné parametry (profil, kvalita, výplň, měřítko, podpěry, kopie)
  = stejný G‑code; vrátit uložený výsledek (kalkulace opakovaná z odkazu, reslice se stejnými parametry,
  „tisknout znovu“). Klíč ať zahrnuje verzi profilů a Orcy.
- **GcodeStats** proudově (řádek po řádku, bez `File::get` celého souboru), hlavička Orcy nese čas i filament –
  většinu statistik lze číst jen z hlavičky/patky.
- **Python**: jeden perzistentní proces na worker (stdin/stdout JSON‑RPC) nebo alespoň lehčí import pro jednoduché
  dotazy (`probe`, bbox) – start interpretu s trimesh je sekundy. Měř, kolik volání na jeden upload a zakázku
  se dnes dělá; sloučit, co jde do jednoho volání.
- **STL přepisy** (scale/place v PHP po bajtech): dělat jednou, ne v každém pokusu; velké STL (nad ~50 MB) hlídat
  pamětí workeru (512 MB!) – raději streamovat.
- **Disk**: `storage/app/slicer` a `prepared` – mazat průběžně, ne až v `prune`; G‑code zakázky se po dokončení
  tisku nemusí držet (nebo komprimovat).
- Co **nedělat**: neměnit profily slicování ani ceny; nekupovat silnější server bez čísel (až když měření ukáže,
  že CPU je limit, navrhni a spočítej).

**3. Důkaz.** Před/po: stejný vzorek 10–20 reálných modelů ze serveru (různé velikosti, i ten 611k; jen číst,
nekopírovat zákaznická data mimo server – měř přímo tam, nebo na vlastních testovacích modelech), porovnat
fáze p50/p90 a ověřit, že G‑code / cena pro stejné vstupy **vyšly stejně** (hash G‑code, stejné gramy/minuty).
Nasazuje Roman; připrav mu krátký postup a čísla.

## Pravidla

- Výsledky slicování a ceny se **nesmí změnit** (test, který to hlídá: stejný vstup → stejné gramy, minuty, cena).
- Žádné tajné hodnoty do chatu ani do commitů; `.env` serveru jen číst.
- Každá změna s testem; celou sadu spouštěj na pozadí a nespouštěj dvě najednou.
- Dokumentace do `docs/M.md` (rozhodnutí, čísla před/po, co se nasazuje, co nastavit v `.env`/systemd).
- Když zjistíš, že úzké hrdlo je někde jinde, než čekám (např. databáze, síť, ffmpeg časosběru), napiš to a
  řeš to; mapa výše je stav 4. 10. 2026, ne příkaz.

## Hotovo, když

`matplace:perf-report` ukazuje p90 kalkulace a přípravy zakázky výrazně pod dneškem (cíl: kalkulace p90 pod 15 s,
zakázka do „sliced“ p90 pod 45 s, bez čekání ve frontě při 3 současných zákaznících), dvojité slicování je
výjimka, testy zelené, `docs/M.md` má čísla před/po a postup nasazení.
