# Zadání pro session 3: první stránka = rychlá kalkulace, přesný řez jen u malých modelů, rozpětí ceny podle tvaru (TODO #41)

Od Romana (24. 10. 2026): „První stránka je rychlá kalkulace pro ty, co mají tiskárnu a chtějí stáhnout model.“ Cena
se má ukázat co nejrychleji; přesně se počítá až u objednávky (session 2, `docs/prompts/flow-objednavky.md`).

## Co dnes stránka dělá

Po výběru souboru prohlížeč do 5 s ukáže hrubý odhad (objem a povrch, `resources/js/calc/rough.ts`): dobu tisku,
rozpětí ceny „od–do“ a větu „Přesná cena podle barvy a tiskárny až při objednávce“. Potom běží „Počítám přesně…“: řez
Orcou na serveru; u malých modelů 15 až 20 s, u velkých minuty (drak 1,8 mil. trojúhelníků 3 min 50 s, model 2,7 mil.
95 s). Upload začíná hned při výběru, soubory nad 20 MB se čtou ve workeru (řídící 24. 10., main 59591fc).

## Jak se odhad trefuje

`docs/data/odhad-vs-rez-2026-10-24.csv`: 400 posledních hotových kalkulací, odhad proti řezu (gramy, minuty, cena,
rozpětí, trojúhelníky, rozměry, podpěry). Z posledních 300: cena u poloviny přesně, u 10 % výsledek o víc než 29 %
vyšší; do ukázaného rozpětí se vešlo 246 z 300 (82 %); doba tisku u poloviny přesně, u 10 % skutečnost víc než 2,3×
delší, nejhůř 3,4×. Příklad: kalkulace 554 (150 × 90 × 100 mm) odhad 1 h 36 min a 130 Kč (110 až 170), řez 3 h 03 min
a 150 Kč; gramy 62,7 proti 61,1.

## Úkol

1. **Přesný řez na první stránce jen u malých modelů.** Práh je společný se session 2 (přes `decimate_above`
   trojúhelníků, dnes 400 000, nebo poslední řez stejného souboru přes 60 s): velký model dostane jen odhad
   s rozpětím, doba tisku jako rozpětí, a místo „Počítám přesně…“ větu „Velký model: přesnou cenu a dobu tisku
   spočítáme při objednávce, trvá to několik minut.“ Tlačítko k objednávce funguje bez přesného řezu (zakázka
   z kalkulace bez řezu; dohodnout se session 2, co kalkulace nese: `rough`, parametry, bez `slicer`). Malý model jako
   dnes. Údaj „Velký model (n mil. trojúhelníků)“ už přichází z `UploadController::describe` (`big_model`).
2. **Rozpětí ceny podle tvaru**, aby se výsledná cena vešla do rozpětí u **≥ 95 %** kalkulací z CSV (dnes 82 %),
   a přitom zůstalo co nejužší. Horní mez zvednout tam, kde odhad nevidí práci sliceru: převisy a potřeba podpěr
   (odhad z normál trojúhelníků v prohlížeči: podíl plochy skloněné pod 45° směrem dolů), poměr povrchu k objemu (tenké
   stěny, mřížky, dlouhé tenké díly = dlouhé dráhy), výška (hodně vrstev), počet těles. Dolní mez podle dat stejně
   (p10 je 0,8). Ověřit na CSV: podíl zásahů, medián šířky rozpětí před a po; zapsat do `docs/O.md` nebo `docs/M.md`.
3. **Doba tisku jako rozpětí** („asi 1 h 40 min až 3 h“) stejnou metodou; jedno číslo jen po přesném řezu.
4. **Text rozpětí**: „od–do“ zůstává, přibývá „podle podpěr a tvaru upřesníme při objednávce“; en/es stejně.
5. Měření před → po na produkci udělá řídící session (čtení produkční DB má jen ona): připrav příkaz nebo skript, který
   z CSV spočítá zásahy a šířky, a tabulku do dokumentace.

Větev **`feature/calc-estimate-range`** z aktuálního `main`. Soubory: `resources/js/calc/rough.ts`, `calculator.ts`
(jen část kolem `requestPrecise`/`renderRough`, část s uploadem a workerem netknout), `config/calc*.php` nebo
`config/pricing.php` pro konstanty, texty přes `lang/src/<soubor>.json` + `scripts/lang_add.py` (klíče do seznamu
`$i18n` v `resources/views/calculator/index.blade.php`, jinak se v prohlížeči ukáže surový klíč). Netknout: farmu,
stránku zakázky, agenta, nástroje. Testy (odhad na fixture, rozpětí), pint, `npm run build`, push + zpráva řídící
session s čísly z CSV.
