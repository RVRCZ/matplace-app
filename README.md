# matplace – „Vyfoť to a tady jsou ceny“

Nová aplikace matplace.com. Jedna obrazovka: nahraj soubor (později fotku nebo popis) → otočný 3D model, cena, doba výroby, materiál → „chci to vyrobit“ / „mám tiskárnu, stáhnout“. Bez registrace, výsledek jde sdílet odkazem.

Stack: Laravel 12 (PHP 8.2), MariaDB, fronta v DB + systemd worker, Blade + Vite + TypeScript + three.js, Tailwind 4.
Plán a audit starého webu: `../matplace/PLAN.md`, `../matplace/AUDIT.md`.

## Lokální vývoj

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# .env: DB_* (lokální MariaDB), ENGINE_SLICER=fake (bez OrcaSlicer), PYTHON_BIN=python (trimesh volitelně)
php artisan migrate
npm run build            # nebo npm run dev
php artisan serve        # http://localhost:8000
php artisan queue:work   # druhý terminál – zpracování souborů a slicing
php artisan test
```

Bez OrcaSlicer nastav `ENGINE_SLICER=fake`: deterministický slicer z geometrie (stejný, který používají testy).
Python + trimesh (`pip install trimesh`) přidá kontrolu sítě a převod OBJ/PLY; bez něj běží čistě PHP varianta (STL, 3MF).

## Struktura

```
app/Engines/Contracts/    rozhraní vyměnitelných motorů: Slicer, FormatConverter, MeshRepair, ModelGenerator, ModelSearch, RoyaltySettlement
app/Engines/*             implementace: OrcaSlicer, FakeSlicer, ThreeMfConverter, FreeCadConverter, PythonMeshConverter, TrimeshRepair, PhpStlRepair, NullGenerator
app/Engines/Mesh/StlFile  čistě PHP čtení/zápis STL (objem, plocha, bbox, škálování)
app/Domain/Calculation/   RoughEstimator (odhad < 1 s), PriceEngine (rozpad ceny), CalculationService, MaterialCatalog
app/Jobs/                 ProcessModelFile (konverze → STL → kontrola), SliceCalculation (přesný výpočet)
app/Http/                 CalculatorController (/, /k/{token}), Api/* (uploads, calculations, files, config)
resources/js/calc/        rough.ts (zrcadlo PHP vzorce), geometry.ts, loaders.ts, viewer.ts (three.js), api.ts, calculator.ts
engines/orca/profiles/    OrcaSlicer profily (Anycubic Kobra S1 0.4, kalibrováno na produkci)
engines/python/           mesh_tool.py (trimesh)  ·  engines/freecad/convert.py (STEP/IGES → STL)
config/engines.php        volba motorů  ·  config/materials.php  ·  config/pricing.php (konstanty odhadu, orientační profily)
deploy/                   nginx vhost, systemd worker, server-setup.sh, deploy.sh
```

## Tisková farma („Pronajmout tiskárnu“)

Třetí tlačítko v kalkulaci: přihlášený zákazník nechá model vytisknout na naší tiskárně a platí předem nabitým kreditem.

```
app/Domain/Farm/          OrderService (založení, barvy, cena), OrderFlow (jediné místo změny stavu: historie, kredit, e-maily),
                          Dispatcher (fronta → tiskárna, odhad pořadí), AgentService (hlášení agenta), Wallet (kredit = součet
                          append-only záznamů), PriceCalculator, ModelValidator, GcodeSlot, FarmSettings
app/Jobs/PrepareFarmOrder kontrola + oprava + orientace (engines/python/farm_tool.py) → slice → G-code + parametry → cena
app/Engines/Farm/         PrintPreparer: Python (trimesh, pymeshfix) | PHP záloha bez opravy a orientace
app/Engines/Payment/      PaymentGateway: Stripe Checkout | fake (testy, lokál)
routes/agent.php          bezstavové /api/agent/* (token agenta) a /webhooks/payments/{brána}
agent/                    farm-agent (Python): Moonraker + simulovaná tiskárna, README, systemd
```

- Tiskárny, sloty, materiály, barvy, ceny a pravidla jsou **data** (`/admin/farm`, výchozí hodnoty `config/farm.php`,
  první naplnění `php artisan db:seed --class=FarmSeeder`). Slicer je stávající OrcaSlicer; řádek tiskárny nese
  soubory profilů a přepisy (skutečná plocha 250 mm, stromové podpěry na auto, bez čistící věže).
- Tisk se sám spustí jen tehdy, když obsluha v `/admin/farm` potvrdí **volnou podložku**; start potvrzení spotřebuje.
- Tiskárna v režimu *ruční*: obsluha stáhne G-code a stavy přepíná sama. V režimu *agent*: viz `agent/README.md`.
- První kroky na serveru: `php artisan migrate`, `php artisan db:seed --class=FarmSeeder`,
  `php artisan farm:setup --admin=<e-mail>`; plánovač (`schedule:run`) hlídá `farm:watch` každou minutu.

## Motory

Výměna motoru = změna `config/engines.php` (nebo `.env` `ENGINE_*`). Kód aplikace vidí jen rozhraní.
Slicer dostává vždy STL; ostatní formáty projdou `ConverterChain` (3MF čistě v PHP, OBJ/PLY přes trimesh, STEP/IGES přes FreeCAD).

## Nasazení

Poprvé: `sudo bash deploy/server-setup.sh` na serveru (balíčky, python venv, DB, worker, nginx). Pak z lokálu `bash deploy/deploy.sh` (git push → pull → composer → build → migrate → restart). Stará aplikace v `/var/www/matplace` zůstává nedotčená.
