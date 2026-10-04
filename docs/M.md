# M: rychlost výpočtu a slicování

Větev `feature/perf` (z `main` ab2bcb2, 4. 10. 2026), zadání `docs/prompts/perf.md`. Výsledky slicování a ceny se
nemění: co jde do Orcy, je bajtově stejné jako dřív (testy proti staré implementaci), čísla z G-code jsou stejná
(test proti řetězcovým funkcím).

## 1. Co jsem naměřil (4. 10. 2026)

Na serveru jsem mohl jen systémová měření; čtení produkční DB a `.env` mi oprávnění této session nepustilo, proto
čísla „před“ pro kalkulace a zakázky beru ze zadání (7 dní: kalkulace průměr 18 s, max 96 s; zakázka do „sliced“
15–130 s, reslice 16–45 s) a „po“ dá `matplace:perf-report` po nasazení.

| Měření | Výsledek | Závěr |
|---|---|---|
| Start Orcy naprázdno (`xvfb-run -a AppRun --help`), server | 0,30–0,39 s | start procesu není problém |
| `python -c "import trimesh"`, server | 0,65 s (holý Python 0,01 s) | perzistentní Python by ušetřil < 1 s na volání |
| Nástroje `mesh_tool.py`, `farm_tool.py` | importy jsou už líné (uvnitř funkcí) | nic k řešení |
| Server v klidu | 4 vCPU, load 0,04, 6,3 GB RAM volné | |
| PHP `StlFile::place` (umístění na podložku, každý slice), lokálně | 330k: 2,62 s, **1,3M: 10,5 s** | **úzké hrdlo** |
| PHP `StlFile::scale` (měřítko ≠ 1 a normalizace při nahrání) | 330k: 1,91 s, **1,3M: 7,66 s** | **úzké hrdlo** |
| PHP `StlFile::stats` (rozměry po slicu) | 330k: 0,45 s, 1,3M: 1,76 s | |
| `GcodeStats` nad 15 MB G-code (načíst + 6 regexů) | 0,01 s, 23 MB paměti | čas zanedbatelný, jen paměť |

Model s 1,3M trojúhelníky tedy v PHP strávil kolem slicování **~20 s** (place + scale + stats) a znovu ~8 s při
nahrání (normalizace přes `scale(1.0)`). Kopie na plátu (`PlateLayout::replicate`) stejným kódem: 10 kopií = 10× place.

## 2. Co se změnilo (commity)

1. **Měření** (2033fe6). Sloupec `timings` (JSON) u `model_files`, `calculations`, `farm_orders` (migrace
   `2026_10_12_100000_perf_timings`). Klíče v sekundách (`*_s`): `queue` (fronta), `wait` (od nahrání/kliku do startu),
   `convert`, `analyse`, `prepare` (+ `prepare_cached`), `layout`, `mesh` (přepis STL), `slice1`, `slice2` (+ `double`,
   `slice1_error` = co Orca řekla běhu bez podpěr), `parse`, `rest_slice`, `post` (věž, podpěry pro náhled, parkování
   časosběru, hash), `price`, `total`; dál `triangles`, `print_minutes`, `gcode_mb`, `reslice`, `started`/`finished`.
   `php artisan matplace:perf-report [--days=7]` a `/admin/stats/speed` (Statistiky → Rychlost): p50/p90/max po fázích,
   dvojí slicování, zásahy cache, čekání ve frontě > 5 s, nejvíc úloh najednou, posledních 5 hlášek Orcy bez podpěr.
2. **STL po blocích** (6d5d5dd). `StlFile::records()` čte 20 000 trojúhelníků najednou, `transform()` zapisuje jedním
   `pack` na trojúhelník; `stats`, `place`, `scale`, `bounds` a `PlateLayout::replicate` na tom stojí.
   Stejná aritmetika → stejné bajty (`tests/Unit/StlFileBlocksTest.php` porovnává se starým kódem: binární i ASCII,
   tři měřítka, rovná i otočená mřížka). 1,3M: place 10,5 → 1,9 s, scale 7,7 → 0,8 s; 330k: 2,6 → 0,5 s, 1,9 → 0,2 s.
3. **Fronta `interactive`** (ec34bbe). `ProcessModelFile`, `SliceCalculation`, `PrepareFarmOrder` jdou do fronty
   `interactive` (`QUEUE_INTERACTIVE`), kterou berou všechny workery jako první – generování, časosběry, importy ani AI
   před zákazníkem nestojí. Soubory designérských karet (i ZIP portfolia) zůstávají v `default`.
   `SliceCalculation` už se nevrací do fronty každé 3 s (40×; soubor zpracovaný déle než ~2 min kalkulaci shodil):
   konec `ProcessModelFile` spustí čekající kalkulace a zakázky (selhaný soubor shodí čekající zakázku hned).
   Ze dvou kopií jobu slicuje ta, která kalkulaci atomicky převezme (`queued → slicing`); slicování zabitého workeru se
   převezme znovu po `timeout − 60 s`.
4. **Cache slicování** (f0161ce). `CachedSlicer` kolem Orcy: klíč = bajty STL + všechny parametry a overrides + obsah
   všech profilů (`engines/orca/profiles` i nahrané farmou) + binárka Orcy + náš kód kolem (`OrcaSlicer`, `GcodeStats`,
   `StlFile`). Zásah vrací vlastní kopii G-code a stejná čísla. `storage/app/slicer/cache`, maže se po 7 dnech bez
   použití a nad `ORCA_CACHE_MB` (3000). Kalkulace, která se liší jen počtem kusů, vezme hotový slice (dřív slicovala
   znovu, protože `params_hash` počet kusů obsahoval).
5. **G-code proudově + limit souběhu** (cf1a5d2). `GcodeStats::fromFile` čte bloky celých řádků (test: stejná čísla
   jako z řetězce, ať se bloky lámou kdekoli). `ORCA_PARALLEL` (výchozí 0 = bez limitu, jako dřív): nejvýš N Orc
   najednou přes zámky souborů; čekání se zapisuje (`orca_wait_s`).
6. **Disk** (ee4d8e0). G-code odhadu (kalkulace, souhrn karty designéra) nikdo nečetl, jen plnil `slicer/gcode`
   (1,9 GB): teď odchází s pracovním adresářem. G-code zakázky farmy zůstává.
7. **`matplace:perf-bench`** (75697fc): slicuje vybrané soubory samotnou Orcou (bez cache) po jednom a po N
   najednou; vypíše sekundy po fázích, gramy, minuty a hash G-code bez řádku s datem.

## 3. Co jsem záměrně neudělal

- **Perzistentní Python**: import trimesh 0,65 s, holý start 0,01 s, importy v nástrojích líné. Úspora < 1 s na
  volání neodpovídá riziku dlouho žijícího procesu (paměť, chyby trimesh). Vrátit se k tomu, jen když `analyse_s`
  nebo `prepare_s` v reportu ukáže, že malé modely tráví sekundy startem.
- **Podpěry předem z geometrie**: špatný odhad by změnil G-code a cenu. Farma slicuje rovnou s podpěrami „auto“
  (jeden běh); dvojí běh se týká jen kalkulací s podpěrami „auto“. Nejdřív data: `double` a `slice1_error` v timings
  ukážou, jak často a proč Orca bez podpěr selže; pak lze bezpečně přeskočit první běh tam, kde je selhání jisté.
- **`ORCA_PARALLEL` / vlákna**: Orca používá TBB, které proměnnou počtu vláken nečte; limit souběhu nechávám vypnutý,
  dokud `perf-bench --parallel=1,3` neukáže, že 3 najednou jsou pomalejší než po sobě.
- **Mazání G-code hotových zakázek**: „tisknout znovu“, časosběr a reklamace s ním počítají; navrhuji až po dohodě
  (např. gzip po předání + smazání po 90 dnech).
- Profily slicování a ceny beze změny.

## 4. Nasazení (Roman)

Postup podle `docs/DEPLOY-BETA.md` (merge `origin/main` identitou `deploy`, zastavit se na konfliktu!). Navíc:

```
php artisan migrate --force          # 2026_10_12_100000_perf_timings: sloupec timings ve třech tabulkách
cp deploy/matplace-worker.service  /etc/systemd/system/matplace-worker.service
cp deploy/matplace-worker@.service /etc/systemd/system/matplace-worker@.service
systemctl daemon-reload
systemctl restart php8.2-fpm matplace-worker      # @2 a @3 jsou PartOf, restartují se s ním
systemctl cat matplace-worker@2 | grep queue      # --queue=interactive,default
```

**Jednotky workerů zkopírovat ve stejném kroku jako kód**: nový kód posílá zákaznické joby do fronty `interactive`
a starý worker (`--queue=default,ai`, šablona `@` bez `--queue`) by je nebral. Kontrola, že nic nevisí:
`php artisan tinker --execute='echo DB::table("jobs")->where("queue","interactive")->count();'` → po chvíli 0.
Nouzově jde vrátit vše do `default` řádkem `QUEUE_INTERACTIVE=default` v `.env` + `php artisan optimize`.

`.env` (volitelné, výchozí hodnoty stačí): `ORCA_CACHE=true`, `ORCA_CACHE_MB=3000`, `ORCA_PARALLEL=0`.

## 5. Důkaz po nasazení

1. Vybrat 10–20 souborů různých velikostí včetně toho s 611k trojúhelníky (uuid z `/admin`). **Před merge** pustit
   bench na starém kódu z dočasného worktree (nic nemění, jen čte soubory a DB):
   ```
   cd /var/www/matplace-app && git worktree add /tmp/perf-old HEAD
   cp .env /tmp/perf-old/.env && ln -s /var/www/matplace-app/vendor /tmp/perf-old/vendor
   rm -rf /tmp/perf-old/storage && ln -s /var/www/matplace-app/storage /tmp/perf-old/storage
   git -C /var/www/matplace-app show origin/main:app/Console/Commands/PerfBench.php > /tmp/perf-old/app/Console/Commands/PerfBench.php
   cd /tmp/perf-old && sudo -u www-data php artisan matplace:perf-bench <uuid…> --parallel=1,3 | tee /root/perf-before.txt
   ```
   Po nasazení totéž v `/var/www/matplace-app` (`… | tee /root/perf-after.txt`), pak `git worktree remove /tmp/perf-old`.
   Sloupec „G-code (no date)“, gramy a minuty musí být po řádcích stejné; „total s“ je zrychlení.
2. Za 1–7 dní `php artisan matplace:perf-report --days=7` a zapsat sem tabulku „po“. Cíl ze zadání: kalkulace p90
   < 15 s, zakázka do „sliced“ p90 < 45 s, `queue_s` ~0 při třech zákaznících najednou, `double` výjimka.
3. Podle `perf-bench --parallel=1,3`: když jsou 3 najednou celkově pomalejší než 3 po sobě, nastavit
   `ORCA_PARALLEL=2` a porovnat `orca_wait_s` + `slice1_s` v reportu.

## Čísla po nasazení

_(doplnit z `matplace:perf-report`)_
