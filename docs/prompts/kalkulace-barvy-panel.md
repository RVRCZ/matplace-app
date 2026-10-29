# Kalkulace: barvy z nástroje a panel rolující v rámečku (session 3)

Zadal Roman 29. 10. 2026 večer u krabičky (`/tools/box` → „Připravuji model…“ → `/c/{token}`). Řídící session: `conceprt19092026-91`. Pravidla v `docs/prompts/koordinator.md`, rozložení bez skoků v `docs/AN.md §18`.

## 1. Barvy z předchozí stránky

Na stránce nástroje má krabička i víčko měděnou (`part_colors` body a lid, `40_PLA_Silk_medena_Silk_Copper`). Na stránce výpočtu se model ukáže v jedné výchozí barvě (tyrkysová), i když řádek „U nás vytiskneme: měděná PLA“ barvu zná.

Cíl: stránka výpočtu drží barvy z nástroje. Soubor z nástroje má v `tool_params` části (`parts`, `pieces` s rozsahy trojúhelníků) a `part_colors` (kód cívky na díl). Náhled obarví každý díl barvou jeho cívky (hex z palety), model bez dílů jednou zvolenou barvou. Stejný mechanismus, jakým stránka zakázky barví díly (TODO #30) a jakým kalkulace barví namalovaný 3MF (`painted.ts`, AN.md §19). Co není z nástroje, zůstává jak je.

Ověř u krabičky (dva díly, jedna barva), medaile (dva díly, dvě barvy), podtácku s golfistou (zarovnané barvy, lem) a u modelu bez dílů (STL z disku).

## 2. Panel parametrů roluje v okně s rámečkem

Dnes na výpočtu je sticky jen karta ceny, zbytek pravého sloupce jede se stránkou. Roman: „opět udělat jezdící parametry v okně a tam, kde je jezdící okno, doplnit okolo okna slabý rámeček; teď není vidět nic a pak se to rozjede“.

Cíl od šířky lg: pravý sloupec (cena, velikost, kusy, materiál, kvalita, výplň, nastavení, tlačítka) je jedno okno, které vyplní výšku pod hlavičkou a roluje uvnitř (`stick-fit` + `stick-scroll` jako `#tool-panel` na stránce nástroje, AN.md §18.2), náhled vlevo stojí. Okno má slabý rámeček (`rounded-2xl border border-slate-200`), aby bylo vidět, že roluje, a stejný rámeček dej i panelu na stránce nástroje, pokud ho nemá. Na telefonu zůstává dnešní pořadí a chování (lišta `action-bar`). Bez skoků při načtení a přepočtu: měř polohou na obrazovce jako v §18.4.

## 3. Kde a jak

- Větev `feature/calc-colors-panel` z main, worktree session 3 (`C:\matplace-edit-wt`). Soubory: `resources/js/calc/calculator.ts`, `painted.ts`, `resources/views/calculator/index.blade.php`, `resources/js/site/sticky.ts`, `app.css`. Stránku zakázky neměň.
- Texty jen přes `lang/src/*.json` a `python scripts/lang_add.py`. Zápis do `docs/AN.md` jako §20.
- Testy: `CalculationFlow`, `PaintedColorsPage`, `StickyLayout`, `ToolPageTest`; `npx tsc --noEmit -p tsconfig.json`, `npm run build`, `pint --test`. Snímky 1280 a 390 px do `docs/img/`.
- Po pushi zpráva řídící session: větev, commit, co je ověřené. Nic nenasazuj.
