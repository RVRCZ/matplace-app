# Oprava: kalkulace posílá objednávku nástrojového návrhu „jednou barvou“, díly po částech se nenabídnou (session 2)

Roman 1. 11. 2026 odpoledne (snímky `docs/img/zadani-kalkulace-jedna-barva-*.png`): „vytvořil jsem návrh barvy zarovno s povrchem, ale v objednávce tisku se nabídla jenom jedna barva“. Přívěsek z obrázku se zarovnanými barvami (5 filamentů: podklad + 4 barvy), na stránce objednávky jedna cívka a zaškrtávátko „Tisknout díly zvlášť, každý svou barvou (5 dílů)“ nezaškrtnuté. Chyba na produkci od nasazení malovaných 3MF (28. až 29. 10., AN.md §19), netýká se jen zarovnaných barev.

## 1. Příčina (ověřeno v kódu)

- `resources/js/calc/calculator.ts` `renderColors()` (řádek 1048, volaná při každé hotové kalkulaci, řádky 119 a 405) nastaví tlačítku farmy `cta-farm` `data-colors = painted ? 'painted' : 'one'`, kde `painted` = soubor má aspoň dvě hlavní malované barvy (`file.colors`, jen malovaná 3MF). **Každý nástrojový návrh tedy dostane `data-colors="one"`.**
- `resources/js/calc/farm.ts` řádek 87 přidá k adresě objednávky `&colors=one` (a totéž v těle POST na řádku 97, ověř).
- `app/Http/Controllers/Farm/OrderController.php` řádek 433: `by_parts` = `colors === 'one' ? false : null` → stránka objednávky začne s díly po částech vypnutými, i když výchozí pravidlo (řádek 196: `onePlateRequired()`, různé barvy dílů, filamentový obraz) by je zapnulo. Výsledek: jedna cívka, žádné barvy dílů, u návrhu s `one_plate_required` ani jedna podložka.
- Dopad: každý návrh z nástroje objednaný přes kalkulaci od 29. 10. (medaile, přívěsky s barvami dílů, krabička s víkem jiné barvy, od 1. 11. zarovnané barvy lícem dolů). Přímé „Vytisknout“ ze stránky nástroje (pokud nejde přes kalkulaci) ověř zvlášť.

## 2. Cíl

1. **Kalkulace**: `renderColors()` dá `data-colors` jen souboru s malovanými barvami (`file.colors` neprázdné: `painted` → `painted`, jinak `one`); u souboru bez malovaných barev atribut odstraní (`delete farm.dataset.colors`), aby o dílech rozhodlo výchozí pravidlo serveru. `farm.ts` řádky 87 a 97 posílají `colors` jen když atribut je. Tlačítko „jednou barvou“ (`cta-farm-one`) zůstává jen u malovaných.
2. **Server**: `OrderController` řádek 433 ponechat (`colors=one` je skutečné přání u malovaného souboru). Zvaž pojistku: u souboru bez malovaných barev (`PaintedColors::painted()` false) `colors=one` ignorovat, aby starý odkaz nebo záložka nevypnuly díly. Napiš do AL.md, co jsi zvolil.
3. **Víc barev než farma tiskne** (Romanův návrh má 5, `FarmOrder::MAX_COLORS` 4): s díly zapnutými dnes `spoolsForParts` vyhodí `too_many_colors` („Jeden tisk může mít nejvýš 4 barvy…“) do karty s cenou. Ověř, že se to u takového návrhu ukáže hned při otevření stránky, že jde přepnout na jeden kus jednou barvou, a doplň do věty odkaz zpět do nástroje („upravte barvy v návrhu“ → stránka nástroje s návrhem, pokud ji objednávka zná), ať zákazník ví, kde barvy slučovat. Nic víc: omezení počtu barev v nástroji je rozhodnutí Romana (session G, jindy).
4. Beze změny: malovaná 3MF (dvě ceny, dvě tlačítka), opakování objednávky, host.

## 3. Testy

- PHPUnit (`FarmOnePlateTest` nebo `CalcToolColorsTest`): otevření objednávky z kalkulace u návrhu s `one_plate_required` bez `colors` → `by_parts` true a barvy dílů předvybrané; s `colors=one` u malovaného souboru → false (dnešní chování); u nástrojového návrhu s `colors=one` podle bodu 2. Návrh s 5 barvami a díly zapnutými → stránka nese důvod `too_many_colors`.
- `npx tsc --noEmit -p tsconfig.json`, `npm run build`, `vendor/bin/pint --test`.
- Prohlížeč (harness session 3 z AN.md, nebo ručně): stránka nástroje přívěsku se zarovnanými barvami a 3 barvami → kalkulace → objednávka: díly zaškrtnuté, barvy dílů vidět, jedna podložka. Snímek `docs/img/order-flush-parts.png`.

## 4. Kde a jak

- Session 2, worktree `C:\matplace-gcode-wt`, větev `fix/calc-colors-wish` z main (f388cc35 nebo novější). Soubory: `resources/js/calc/calculator.ts`, `resources/js/calc/farm.ts`, `app/Http/Controllers/Farm/OrderController.php`, texty přes `lang/src/*.json` + `python scripts/lang_add.py …`. Zápis AL.md §14 jako dodatek (malovaná 3MF a kalkulace) s odkazem z §10.8.
- Texty jen přes lang, `git add` jmenovitě, žádný stash. Nic nenasazuj. Po pushi zpráva řídící: větev, commit, co je ověřené, snímek. Řídící sloučí a nasadí hned, je to oprava produkce.
