# Úklid souborů zakázek farmy (TODO #62, 1. 11. 2026, session 2)

Roman 1. 11. 2026 večer, když došlo místo na disku produkce: „soubory zakázek starší 7 dnů také“ smazat.
Nejde o jednorázové `rm`: zda zakázka ještě soubory potřebuje, ví jen databáze (stav zakázky), ne datum souboru. Pravidlo
proto patří do denního `matplace:prune` (routes/console.php, 03:30), první běh po nasazení spustí řídící ručně.

## Stav disku (1. 11. 2026, 18:40)

- `/dev/sda1` 75 GB; po smazání starých záloh DB 17 GB volných. `storage/app` 37 GB: `farm` 20 GB, `models` 15 GB, `slicer` 1,7 GB, `public` 1,1 GB.
- `farm/orders/<token>` 19 GB, 264 složek, 170 z nich má mtime starší 7 dnů. Podle přípon: gcode 7,9 GB (499), stl 7,2 GB (468), jpg 3,2 GB (18 250 snímků časosběru), mp4 1,1 GB (223), 3mf 0,05 GB.
- V jedné složce bývá: `print.gcode` + varianty `print.slot0-215-55.gcode`, `print.slot1-….tl-<hash>.gcode`, `print.hot.slot2-….gcode`, `rest.gcode`, `part-<n>.gcode`; `print.stl`, `plate.stl`, `part-<n>.stl`, `part-<n>-plate.stl`, `plate-part-<n>.stl`, `rest.stl`, `print.stl.stage`; `plate.3mf`, `plate-rest.3mf`; `supports.bin`; `snapshot.jpg`; `timelapse.mp4`, `short.mp4`, `wide.mp4`; `frames/`, `frames_layer/`.
- Snímky časosběru už `matplace:prune` maže po `BuildFarmTimelapse::KEEP_FRAMES_DAYS` (14 dnů), funguje (0 starších složek). Soubory gcode a stl nemaže nikdo.
- Ve složkách starších 7 dnů leží 6,6 GB gcode + stl. To je očekávaný zisk prvního běhu (část z nich ale patří zakázkám, které ještě nejsou hotové, ty zůstanou).

## Pravidlo

1. Zakázka je „uzavřená“, když je ve stavu `handed_over`, `cancelled`, `failed` nebo `done` a poslední změna stavu je starší než `config('farm.files_retention_days')` (nové, výchozí 7; bez přepínače v adminu). Poslední změna = `handed_at`, jinak `finished_at`, jinak `updated_at`; vyber, co je v modelu spolehlivé, a napiš to do docs.
2. Nikdy nesahej na zakázky ve stavech `awaiting_choices`, `uploaded`, `sliced`, `paid`, `queued`, `printing`, ani když jsou staré. Zakázka s tiskovou úlohou (`FarmPrintJob`) ve stavu `sent`, `printing`, `paused`, `unknown` se také nechá být (agent si gcode stahuje přes `route('agent.jobs.gcode')`, AgentService.php:284).
3. U uzavřené zakázky smaž ve `orders/<token>`: všechny `*.gcode`, `print.stl`, `plate.stl`, `part-*.stl`, `part-*-plate.stl`, `plate-part-*.stl`, `rest.stl`, `print.stl.stage`, `*.3mf`, `supports.bin`, složky `frames/` a `frames_layer/`.
4. Nech: `timelapse.mp4`, `short.mp4`, `wide.mp4` (stránka zakázky je servíruje, YouTube a sociální sítě je berou z `timelapse_path`, `timelapse_short_path`, `timelapse_wide_path`), `snapshot.jpg` (`FarmPrintJob::snapshot_path`), `*.json`, `*.txt`. Cizí soubory, které nepoznáš, také nech.
5. Databáze: `OrderController::model()` (řádek 768) bere `absolutePrintStlPath()`, a když chybí, STL původního souboru z `models/files`; teprve pak 404. Po smazání musí náhled na stránce zakázky i v účtu dál fungovat: buď `print_stl_path` (a cesty dílů v `partPlates()`, cestu podpěr, cesty gcode) vynuluj, nebo ať `absolute*Path()` vrací null, když soubor neexistuje. Náhled podpěr se bez souboru prostě neukáže, bez chyby v konzoli. Stránka zakázky v adminu u smazaného gcode ukáže „soubor po 7 dnech smazán“ místo odkazu, tlačítka, která gcode potřebují (poslat znovu na tiskárnu, přeřezat starou zakázku), se u uzavřené zakázky bez souboru schovají nebo řeknou proč. „Vytisknout znovu“ zákazníka (`repeatOf`, řádek 382) staví novou zakázku z původního souboru, soubory staré zakázky nepotřebuje, ověř to testem.
6. `matplace:prune` vypíše `farm order files removed: N orders, X MB`, `--dry-run` vypíše čísla zakázek a velikosti bez mazání. Jednorázový běh na produkci dělá řídící (nejdřív `--dry-run`).
7. `models/files` (15 GB, nahrané soubory; anonymní bez vazby se mažou po 30 dnech) není součást zadání. Napiš do docs jedním odstavcem, co by šlo udělat a co tomu brání (soubory účtů, katalog, zaplacené zakázky).

## Testy

- Feature test s `Storage::fake(config('farm.disk'))`: tři zakázky (uzavřená 8 dnů, uzavřená 3 dny, `printing` 20 dnů) se stejnou sadou souborů; po `matplace:prune` zůstanou jen povolené soubory první z nich, ostatní složky beze změny; sloupce s cestami odpovídají; `--dry-run` nic nemaže.
- Test stránky zakázky po úklidu: 200, `model_url` míří na původní STL, bez `supports`.
- Nikdy dvě sady phpunit najednou (`Storage::fake` sdílí složku, viz paměť řídícího). `php -d memory_limit=2G vendor/bin/phpunit --filter "Prune|FarmOrderFlow|FarmPages"`.

## Pravidla a odevzdání

- Větev `feature/order-files-prune` z main 119d28c0 (má #59 i #61). Texty jen přes `lang/src/*.json` + `python scripts/lang_add.py`. JSON po úpravě zkontroluj (`python -c "import json; json.load(open(...))"`). Git add po jménech, bez stash.
- Nic na produkčním serveru. Žádné mazání souborů mimo test.
- Docs: nový oddíl v docs/AL.md (§16 Úklid souborů zakázek), řádek TODO #62 doplň po pushi stavem. Po pushi napiš řídícímu (`conceprt19092026-b7`): sha, testy, co se smaže při prvním běhu podle `--dry-run` nejde zjistit lokálně, tak odhad z pravidla.
