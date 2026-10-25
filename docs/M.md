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

## 6. Rozpětí odhadu podle tvaru a velký model bez řezu (TODO #41, větev `feature/calc-estimate-range`, 25. 10. 2026)

Zadání `docs/prompts/kalkulace-odhad.md` (session 3). Data: `docs/data/odhad-vs-rez-2026-10-24.csv` – 400 hotových kalkulací
(250 různých tvarů; drak s 1,8 mil. trojúhelníků je tam 10×, dvě jeho velikosti), odhad proti řezu. Nic z toho není nasazeno.

### 6.1 Co se změnilo

1. **Velký model dostane na první stránce jen odhad.** Práh na jednom místě, `App\Domain\Calculation\BigModel::is()`:
   přes `engines.mesh.decimate_above` trojúhelníků (400 000), **nebo** poslední hotový řez téhož souboru trval přes
   `engines.mesh.big_slice_s` (60 s, `MESH_BIG_SLICE_S`, 0 pravidlo vypne). `UploadController::describe` ho posílá jako
   `big_model`. Prohlížeč pak přesný řez nevolá: `POST /api/calculations` s `rough_only=1` založí kalkulaci ve stavu
   `rough` – odhad, rozpětí, žádný `slicer`, nic ve frontě (`CalculationService::create(..., sliceNow: false)`); hotový
   řez stejného nastavení se vrátí jako dřív (`done`). Místo „Počítám přesně…“ stojí bez kolečka „Velký model (1,8 mil.
   trojúhelníků): přesnou cenu a dobu tisku spočítáme při objednávce, trvá to několik minut.“ Odkaz `/c/<token>` funguje
   (stav `rough` se nepolluje, stránka ukáže odhad), stažení modelu funguje bez řezu, tlačítko „Vytisknout u nás“ dostane
   kalkulaci s `rough`, `params`, `file` a **bez `slicer`** – stránka zakázky (session 2) ji musí přijmout a řezat sama.
   Malý model jako dřív.
2. **Rozpětí ceny podle tvaru.** `App\Domain\Calculation\ShapeRange::factors()` a zrcadlo `rangeFactors()` v
   `resources/js/calc/rough.ts`; konstanty v `config/pricing.php` → `rough.range` (nahradily ploché `range_low/high`
   0,85–1,25). Server ukládá do `calculations.rough` navíc `minutes_min`, `minutes_max`, `range` (faktory) a `shape`
   (výška, g/cm³ kvádru, podíl převisů), takže stránka zakázky může rozpětí přepočítat podle voleb a měření před → po
   jde z databáze.
3. **Doba tisku jako rozpětí** („asi 1 h 20 min až 3 h 30 min“, dolní mez dolů a horní nahoru na 5 min pod hodinu, 10 min
   do tří hodin, půl hodiny nad; `minutesRangeText` v `calculator.ts`). Jedno číslo až po přesném řezu. V režimu farmy
   je rozpětí titulkem karty (o stupeň menším písmem), na tržišti stojí u „Doba tisku“.
4. **Texty** (`lang/src/calc_estimate_range.json`, cs/en/es): `calc.status.big_only`, `calc.status.big_only_n`,
   `calc.price.orient_range` („Orientační cena tisku u nás: ≈ 90 – 210 Kč. Podle podpěr, tvaru, barvy a tiskárny ji
   upřesníme při objednávce.“), `calc.price.shape_hint` (tržiště, pod cenou), `calc.time.range` („asi :lo až :hi“).
   Klíče jsou v seznamu `$i18n` v `calculator/index.blade.php`.
5. **Měření**: `php artisan matplace:estimate-range <csv>` spočítá z CSV v tomhle formátu zásahy a šířky (uložené
   rozpětí i rozpětí z dnešních konstant přepočtené podle tvaru řádku) a vypíše tabulky níže v markdownu;
   `php artisan matplace:estimate-range soubor.csv --export=400` nejdřív takové CSV vypíše z databáze (posledních N
   hotových kalkulací, sloupce řídící session plus `scale`, `rough_minutes_min`, `rough_minutes_max`). Produkční měření
   udělá řídící session: export → příkaz → tabulka sem.

### 6.2 Jak rozpětí vzniká

Odhad (`RoughEstimator`, beze změny) počítá gramy z objemu a plochy a minuty z gramů. Řez se od něj liší hlavně tam, kde
gramy nevidí práci tiskárny; na datech byla nejsilnější **hustota materiálu v obalovém kvádru** (korelace s poměrem
řez/odhad −0,55 na logaritmech: tenké stěny, mřížky, dlouhé tenké díly = málo materiálu ve velké krabici = dlouhé dráhy)
a **výška** (+0,51: hodně vrstev), pak podpěry. Faktory horní a dolní meze:

```
hi = high × (1 + height × H) × (1 + thin × F) × S        H = rampa výšky 15 → 100 mm (0 … 1)
lo = low  × (1 − small × T)                               F = 1 − rampa hustoty 0,04 → 0,2 g/cm³ (tenký = 1)
                                                          T = 1 − rampa odhadnutých minut 15 → 30 (malý tisk = 1)
S: podpěry zapnuté → supports; vypnuté → 1; nechat slicer → auto + (supports − auto) × rampa podílu převisů 5 → 30 %
```

| | high | height | thin | supports | auto | low | small |
|---|---|---|---|---|---|---|---|
| doba tisku | 1,25 | 0,2 | 1,2 | 1,2 | 1,2 | 0,78 | 0,5 |
| cena | 1,05 | 0,1 | 0,2 | 1,4 | 1,15 | 0,7 | 0,3 |

Cena se mění méně než čas, protože materiál (který odhad trefuje: medián 1,00, p90 1,13) je její velká část. Podíl
převisů měří prohlížeč z normál trojúhelníků při čtení souboru (`geometry.ts stats()`: plocha stěn skloněných dolů přes
45°, bez dna; síť obrácená naruby se pozná podle záporného objemu) a posílá ho s kalkulací (`geometry.overhang_share`,
`geometry.bbox`); server bez něj počítá „nechat slicer“ jako `auto`. Neznámá výška nebo hustota (soubor ještě nezměřený
a prohlížeč ho neumí přečíst) se počítá jako vysoký a tenký, tedy na širokou stranu.

Kalibrace: hledání po mřížce (souřadnicový sestup) nad 400 řádky CSV s cílem ≥ 95 % zásahů a nejmenší střední šířkou
(log), zvlášť pro horní a dolní mez, zvlášť cena a čas; pak zaokrouhlení konstant a kontrola. Na odloženém vzorku
(kalibrace na lichých id, kontrola na sudých a naopak) vychází 93–96 %, tedy čísla níže jsou trochu optimistická.
Konstanty jsou v `config/pricing.php`; referenční vektory drží PHP a TypeScript při sobě
(`tests/Unit/ShapeRangeTest`, `scripts/check_rough_range.mjs` v `npm run build`).

### 6.3 Čísla z CSV, před → po (`php artisan matplace:estimate-range docs/data/odhad-vs-rez-2026-10-24.csv`)

| co | uvnitř | nad / pod | medián hi/lo | p90 hi/lo | tvary celé uvnitř |
|---|---|---|---|---|---|
| cena, rozpětí, které stránka ukázala (0,85–1,25) | 329/400 (82,2 %) | 37 / 34 | 1,53 | 3,17 | 80,0 % |
| cena, rozpětí podle tvaru | 387/400 (96,8 %) | 5 / 8 | 2,14 | 2,75 | 95,6 % |
| doba tisku, kdyby měla totéž 0,85–1,25 (dnes jedno číslo) | 172/400 (43,0 %) | 162 / 66 | 1,47 | 1,47 | 38,0 % |
| doba tisku, rozpětí podle tvaru | 385/400 (96,2 %) | 8 / 7 | 3,39 | 4,38 | 95,2 % |

Pro srovnání ploché kvantily bez tvaru by na 95 % potřebovaly cenu 0,66–1,50 (medián šířky 2,29) a čas 0,54–2,78 (5,16).

| třída | n | cena uvnitř | cena medián hi/lo | čas uvnitř | čas medián hi/lo |
|---|---|---|---|---|---|
| plochý, pod 20 mm | 123 | 99,2 % | 1,89 | 95,1 % | 2,17 |
| vysoký, 80 mm a víc | 127 | 95,3 % | 2,27 | 96,9 % | 3,95 |
| tenký, pod 0,1 g/cm³ kvádru | 133 | 94,0 % | 2,25 | 100,0 % | 4,08 |
| kompaktní, 0,3 g/cm³ a víc | 95 | 98,9 % | 1,89 | 97,9 % | 1,92 |
| podpěry zapnuté | 63 | 100,0 % | 2,75 | 98,4 % | 3,85 |
| podpěry nechat slicer | 204 | 95,1 % | 2,17 | 94,6 % | 3,68 |
| podpěry vypnuté | 133 | 97,7 % | 1,88 | 97,7 % | 2,40 |
| malý, odhad pod 30 min | 77 | 100,0 % | 2,29 | 98,7 % | 3,20 |
| přes milion trojúhelníků | 18 | 83,3 % | 2,25 | 100,0 % | 3,80 |

Příklady (odhad → nové rozpětí, řez): kalkulace 554 (150 × 90 × 100 mm, 0,046 g/cm³) odhad 1 h 43 min a 130 Kč → čas
1 h 20 min až 6 h 40 min (řez 3 h 03 min), cena 90–210 Kč (řez 150; dřív 110–170). Drak 555 (146 × 163 × 42 mm) odhad
2 h 15 min a 170 Kč → 1 h 45 min až 6 h 40 min (řez 6 h 37 min), 110–250 Kč (řez 220; dřív 140–220). Kalkulace 461
(60 × 60 × 100 mm) odhad 1 h 56 min a 180 Kč → 1 h 30 min až 3 h 31 min, 120–240 Kč, řez 5 h 29 min a 280 Kč –
**mimo**: dutý kompaktní tvar s hustotou 0,2 vypadá pro vzorec snadno. Z 13 minutí ceny je 10 drak (týž soubor 10×, cena
1,29–1,62×): u sítí přes milion trojúhelníků je rozpětí stále úzké, 83 %.


**Produkce 25. 10. 2026 (řídící session, `php artisan matplace:estimate-range /tmp/odhad-prod-20261025.csv --export=400`, kopie dat `docs/data/odhad-vs-rez-2026-10-25-prod.csv`):**

400 calculations, 210 distinct shapes (odhad-prod-20261025.csv).

| what | inside | above / below | median hi/lo | p90 hi/lo | shapes fully inside |
|---|---|---|---|---|---|
| price, the range the page showed | 329/400 (82.2 %) | 37 / 34 | 1.53 | 3.17 | 77.6 % |
| price, the range by shape of today's constants | 387/400 (96.8 %) | 5 / 8 | 2.14 | 2.75 | 95.2 % |
| time, the range by shape of today's constants | 385/400 (96.2 %) | 8 / 7 | 3.39 | 4.38 | 94.8 % |

| class | n | price inside | price median hi/lo | time inside | time median hi/lo |
|---|---|---|---|---|---|
| flat, under 20 mm | 123 | 99.2 % | 1.89 | 95.1 % | 2.17 |
| tall, 80 mm and more | 127 | 95.3 % | 2.27 | 96.9 % | 3.95 |
| thin, under 0.1 g/cm³ of its box | 133 | 94.0 % | 2.25 | 100.0 % | 4.08 |
| compact, 0.3 g/cm³ and more | 95 | 98.9 % | 1.89 | 97.9 % | 1.92 |
| supports on | 63 | 100.0 % | 2.75 | 98.4 % | 3.85 |
| supports left to the slicer | 204 | 95.1 % | 2.17 | 94.6 % | 3.68 |
| supports off | 133 | 97.7 % | 1.88 | 97.7 % | 2.40 |
| small, estimate under 30 min | 77 | 100.0 % | 2.29 | 98.7 % | 3.20 |
| over a million triangles | 21 | 85.7 % | 2.25 | 100.0 % | 3.80 |

### 6.4 Co není ověřené a limity

- Podíl převisů v CSV není (sbírá se až od téhle větve), takže jeho vliv na „nechat slicer“ je odhad: zvedá horní mez
  ceny nejvýš k faktoru podpěr (1,4), čas nemění (na datech měly tisky s podpěrami stejný horní faktor jako „auto“).
  Nikdy nezužuje pod to, co CSV ověřilo.
- CSV nemá měřítko: `bbox` je soubor, gramy jsou po zvětšení; řádky se zvětšením ≠ 1 mají hustotu posunutou. Export
  z příkazu už `scale` nese.
- Kalibrace je na stejných datech jako kontrola (odložený vzorek 93–96 %); 250 tvarů, drak 10×. Produkční měření za
  pár týdnů ukáže, jestli 95 % drží – příkaz je připravený.
- Počet těles (zadání) jsem nepřidal: v datech není a v prohlížeči by u 1,8 mil. trojúhelníků stál sekundy (union–find).
- V prohlížeči zkoušeno jen lokálně s tenkou deskou 120 × 2 × 150 mm (kalkulace přes API s `rough_only`, pak `/c/<token>`
  v headless Chrome přes `scripts/review_round2.mjs`, šířky 390 a 1280): status bez kolečka, titulek „asi 45 min až
  1 h 50 min“, věta o rozpětí ceny, bez vodorovného posuvu a bez chyb JS; přesný výsledek téhož souboru ukazuje jedno
  číslo jako dřív. Drak lokálně není, cesta s `big_model` z nahrání běžela jen v testech.
- Stránka zakázky (session 2) zatím kalkulaci bez `slicer` neumí; do té doby tlačítko farmy u velkého modelu založí
  zakázku, která si řeže sama (jako dnes – farma řeže vždy znovu), jen bez čísla z první stránky.

### 6.5 Soubory

`app/Domain/Calculation/{BigModel,ShapeRange}.php`, `CalculationService` (`sliceNow`, `roughFor(..., $shape)`),
`PriceEngine::range($factors)`, `Api\CalculationController` (`rough_only`, `geometry.bbox`, `geometry.overhang_share`),
`Api\UploadController::describe` (`big_model` přes `BigModel`), `AppServiceProvider` (singleton `ShapeRange`),
`Console\Commands\EstimateRange`, `config/pricing.php` (`rough.range`), `config/engines.php` (`mesh.big_slice_s`),
`resources/js/calc/{rough,geometry,calculator,api}.ts`, `resources/views/calculator/index.blade.php` (klíče),
`lang/src/calc_estimate_range.json` → `lang/{cs,en,es}.json`, `scripts/check_rough_range.mjs` (+ `package.json`
build), testy `tests/Unit/ShapeRangeTest`, `tests/Feature/CalculationEstimateRangeTest`, `tests/Unit/PriceEngineTest`
(rozpětí s faktory).

### 6.6 Drak po nasazení: dolní mez doby tisku stoupá s počtem trojúhelníků (větev `feature/calc-range-floor`, 25. 10. 2026)

Romanův průchod drakem (kalkulace jokfcgmvlury, 146 × 163 × 42 mm, 1,8 mil. trojúhelníků) ukázal „asi 1 h 30 min až
6 h 30 min“; řezy téhož draka dávají 6 h 36 min bez podpěr a 7 h 08 min se stromovými podpěrami. Horní mez tedy sedí,
dolní mez byla nesmysl: odhad minut je u takového tvaru 2 až 3× pod skutečností a symetrické rozpětí ho jen roztáhlo.

**Co data říkají.** Návrh byl zvednout dolní mez u tvarů „málo gramů na cm³ kvádru a velký podíl převisů“. Na CSV to
neprošlo: podíl převisů u draka je jen **5 %** (`engines/python/overhang_tool.py`, stejná míra jako v prohlížeči – drak
leží naplocho, povrch míří nahoru a do stran), a hustota sama o sobě dolní mez zvednout nedovolí: mezi tenkými tvary
(pod 0,12 g/cm³) jsou tisky s řezem pod odhadem (p05 poměru řez/odhad 0,91 u vypnutých podpěr, 0,99 u „nechat slicer“).
Spolehlivý signál je **počet trojúhelníků**: skenovaný nebo sochařský povrch (stovky tisíc a víc) nikdy netiskne rychleji
než odhad (přes 400 000: p05 1,32, medián 2,38) – každá vrstva je samé krátké perimetry. Export z 24. 10. měl u poloviny
řádků trojúhelníky prázdné, proto kalibrace běžela na produkčním exportu z 25. 10. (úplné trojúhelníky a měřítko) a
export z 24. 10. slouží jako kontrola.

**Změna.** Dolní mez dostala člen `low_triangles` (rampa `triangles` 200 000 → 1 000 000): `lo = low × (1 + low_triangles × R)
× (1 − small × T)`, pro čas i cenu 0,6 (`config/pricing.php`). Horní mez se nemění. Výběr konstant: aspoň 97 % řezů
zůstane nad dolní mezí na obou CSV a nejmenší medián šířky; silnější varianty (0,7 od 100 000) srážely třídu přes
400 000 trojúhelníků na 91 % (modely 489, 320, 249 s řezem kolem odhadu). Trojúhelníky nese `rough.shape.triangles`;
server je bere z měření souboru nebo z hlavičky binárního STL (`BigModel::triangles`), prohlížeč z vlastního čtení.
Stránka zakázky volá `shapeOf(dims, 1, grams, overhang)` bez trojúhelníků → její dolní mez zůstává jako dřív (širší);
stačí předat trojúhelníky souboru jako pátý parametr.

| co (produkční export 25. 10., 400 kalkulací) | uvnitř | nad / pod | medián hi/lo | p90 hi/lo |
|---|---|---|---|---|
| doba tisku, nasazené rozpětí | 385/400 (96,2 %) | 8 / 7 | 3,39 | 4,38 |
| doba tisku, s členem trojúhelníků | 383/400 (95,8 %) | 8 / 9 | 3,10 | 4,15 |
| cena, nasazené rozpětí | 387/400 (96,8 %) | 5 / 8 | 2,14 | 2,75 |
| cena, s členem trojúhelníků | 385/400 (96,2 %) | 5 / 10 | 2,00 | 2,67 |

Export z 24. 10. (kontrola): čas 384/400 (96,0 %), medián 3,21; cena 387/400 (96,8 %), medián 2,11. Třída přes milion
trojúhelníků: čas 100 % uvnitř, medián šířky 3,80 → 2,38; 200 000 až milion: 94,4 %, 3,40. Drak (kalkulace 555, odhad
2 h 15 min): dolní mez 1 h 45 min → **2 h 50 min**, horní 6 h 40 min zůstává (řez 6 h 37 min); cena 110–250 Kč →
200–250 Kč (řez 220). Rozptyl je pořád velký, protože horní mez je správně a odhad sám je u takového tvaru 3× nízko;
užší by to bylo jen s lepším odhadem času (dráha stěn z plochy místo minut z gramů), na to chybí v datech plocha a objem.

**Pro příští kalibraci.** `matplace:estimate-range --export` od teď vypisuje i `volume_mm3`, `area_mm2`, `shell_mm3`,
`material_mm3` (z `rough`) a `overhang_share` (z `rough.shape`, u starých kalkulací bez něj `--overhang` ho změří
z uloženého STL nástrojem `overhang_tool.py`, soubor jednou, i když byl počítán desetkrát). S tím půjde zkusit odhad času
ze stěn a výplně zvlášť a ověřit člen převisů na skutečných řezech. Kontrola příkazu ve `read()` bere `overhang_share`
a `triangles` z CSV, když tam jsou.
