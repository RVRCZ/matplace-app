# Zadání pro session 3: zákaznický audit, etapa 1, zbytek (hlavní stránka, mobil, akce, cena a doručení, slovník)

Podle `docs/AUDIT.md` (body 3, 4, 5, 6, 11) a `docs/Z.md`; dosud vedeno jako „řídící“, od 18. 10. 2026 tvoje. Platby
(bod 7, `OrderFlow`, `Wallet`) zůstávají řídící session, nesahej na ně. Kód plateb a trychtýř měření neměň.

## 0. Kde pracuješ a pravidla

- Větev **`feature/audit-rest`** z aktuálního `main`, dokument **`docs/AH.md` §9** (pokračuj v AH.md).
- Soubory: `resources/views/calculator/index.blade.php` (hlavní stránka = kalkulace), `layouts/app.blade.php`
  (hlavička, navigace, lišta), `farm/order.blade.php` a `resources/js/calc/farm.ts` (zakázka: texty a souhrn, ne
  logika cen), `resources/css/app.css`, `lang/src/*.json` + `python scripts/lang_add.py`, `scripts/review_round2.mjs`
  (tvůj skript průchodu), testy `tests/Feature/WebRound2Test.php`, `CalculationFlowTest`, `ToolPageTest`.
- Pravidla jako dosud: `git add` jmenovitě, žádný stash, `pint --dirty`, `tsc`, `build`, testy; nemergovat,
  nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c). Snímky do `docs/img/audit/`.

## 1. Úkoly (pořadí podle přínosu)

1. **Mobil (bod 4)**: na 360 × 800 a 390 × 844 výška úvodu tak, aby bylo pole pro soubor a cena vidět bez rolování;
   navigace pro palec (dnes drobná: Nástroje, Cena ze souboru, Přihlásit, cs/en/es, Kč/€): dotykové plochy
   ≥ 44 × 44 px, jazyk a měna tišší; žádný vodorovný posun na 360, 390, 768, 1280; text 200 %; formuláře
   s otevřenou klávesnicí. Měřit skriptem (výška prvků, `scrollWidth`, velikost tlačítek) a čísla do AH.md.
2. **Hlavní stránka (bod 3)**: sdělení podle auditu („Váš nápad vytiskneme ve 3D…“, „Máte vlastní tiskárnu? Model si
   můžete stáhnout.“) **už na produkci je** (řídící, 17. 10.); jen zkontroluj en/es a že na 360/390 nezabere celou
   výšku (bod 1). Jeden sloupec zůstává (Roman 16. 10.).
3. **Akce a stavy v kalkulaci a zakázce (bod 5)**: „Vytisknout u nás“ jasně jako začátek objednávky (zakázka vzniká
   nezaplacená, nic se neplatí); každý přechod má stav načítání / úspěch / chyba; zpět a obnovení stránky nic neztratí
   (zakázka má token; kalkulace: ověřit); neúspěšný přesný výpočet zachová zadání a odhad (dnes ano, ověřit).
   Tlačítko „Pokračovat“ vs. „Spočítat cenu a možnosti tisku“: Roman 17. 10. rozhodl „Pokračovat“, neměnit.
4. **Cena a doručení (bod 6)**: v zakázce oddělit „tisk X h“ / „expedice: v den dotištění v pracovní dny“ /
   „doručení Z“ s větou, že jde o odhad; věta **proč** se cena změnila po přepočtu (jiný stroj, jiný druh); souhrn
   před platbou zkontrolovat proti skutečnému účtování (jen čtení, čísla do AH.md). Příklady výrobků s cenou čekají
   na fotky od Romana: připrav blok se zástupnými obrázky a čísly z produkce (busta 80 mm 99 Kč, 120 mm 130 Kč,
   150 mm 200 Kč), skrytý za `config('site.examples')`.
5. **Slovník a stavy (bod 11)**: „3D model“ / „soubor“ pro digitální výsledek, „výtisk“ / „výrobek“ pro fyzickou
   objednávku; akce vytvořit / stáhnout / objednat tisk / zaplatit; „zdarma“ vždy s limitem na místě rozhodnutí;
   projít kalkulaci, zakázku, účet, hlavní stránku (nástroje máš z AH.md); test, že žádný použitý klíč nechybí
   v cs, en, es (rozšířit stávající test z nástrojů na tyto stránky).
6. **Úkol A**: po tom všem projet všechny cesty skriptem (390, 360, 768, 1280; cs, en) a do AH.md tabulku
   „cesta → kroky → co se stane“ se snímky; nálezy třídit chyba / překážka / neověřený návrh.

## 2. Hotovo

Push `feature/audit-rest`, AH.md §9 (co se změnilo, čísla z měření, tabulka cest), testy, zpráva řídící session.
