# Barvy podle stroje: limit cívek z počtu pozic tiskárny a výběr barev seskupený podle tiskárny (TODO #66)

Pro session 2, 4. 11. 2026 (Roman: „špatně se vyznám v barvách, když volím barvu pro vícebarevný tisk, a Kobra X umí
7 barev, my dovolíme jen 4“). Worktree `C:\matplace-gcode-wt`, větev `feature/colors-per-machine` z `origin/main`.
Po pushi napiš řídící session. Nic nenasazuj, žádné `php artisan migrate*` v worktree (sdílené `dev.sqlite`).

## Stav dnes

- `app/Models/FarmOrder.php:289` `MAX_COLORS = 4` („jeden ACE má čtyři“). Hlídá se v `OrderFlow.php:124`,
  `OrderService.php:413, 546, 587`, `OrderController.php:223, 258, 302` a jde na stránku jako `max_colors`
  (`OrderController.php:958`, `resources/js/calc/farm.ts` nápověda `farm.order.parts_hint {max}`).
- Tiskárny mají pozice v `FarmPrinter::slots()` (`FarmPrinterSlot`, řazené `slot`). Kobra X má 3 ve stojanu + 4 v ACE = 7
  (Roman 4. 11.: v administraci nastaveno 3 + 4), Kobra S1 4, chystaná Prusa Core One + INDX 8 nástrojů (TODO #67).
  Ovladač agenta pozice Kobra X číslovat umí (stojan 0 až 3, ACE 4 až 7, `agent/farm_agent/drivers/anycubic_lan.py`
  `Position`), takže věc je jen na straně webu.
- Výběr barev u dílů (`farm.ts` asi 500 až 600, data `OrderController.php:900` `machines` = `firstsAcross` + `secondSpools`):
  pod každým dílem řada dlaždic se všemi cívkami všech strojů, nabídka `offerFor(part)` se po každém kliknutí přepočítá
  podle toho, které stroje udrží už vybranou sadu. Barvy tiše mizí a objevují se, stroj ani jeho pozice nejsou vidět,
  u pěti dílů a deseti cívek je to velká mřížka. Nápověda říká abstraktní „nejvýš 4 barvy“.
- Malované 3MF: při nahrání se barvy nad 4 slučují do tělesa (`colors_tool.py upload … max_colors`, AL.md §14,
  „4 největší, zbytek na barvu tělesa“), `preselect` omezuje výběr za zákazníka na 4 (AL.md §14.10).

## Co udělat

A. **Limit podle stroje.** Konstanta pryč z rozhodování: počet barev jedné zakázky smí být nejvýš počet pozic
   tiskárny, která ji tiskne (`slots()->count()`), při předběžné kontrole bez stroje nejvýš maximum přes tiskárny řízené
   agentem. Bezpečnostní strop do `config/farm.php` (`max_colors_ceiling`, 16), `FarmOrder::MAX_COLORS` nech jen jako
   výchozí hodnotu pro testy a stroje bez pozic. Hláška `too_many_colors` jmenuje číslo stroje. Jedna podložka
   (`FarmOnePlate`, slučování dílů stejné barvy) beze změny.
B. **Nahrání malovaného 3MF a předvolba:** slučování barev nad limit a `preselect` používají maximum přes stroje farmy
   (dnes 7, po INDX 8), ne 4. Skupiny barev nad limit se dál slučují do tělesa, ale až nad limit stroje.
C. **Výběr barev podle tiskárny** (`farm.ts`, Blade stránky zakázky, texty přes `lang/src/*.json` + `lang_add.py`):
   1. Nahoře karta stroje: název, jeho pozice v pořadí, jak je vidí obsluha („Stojan 1 až 3, ACE 1 až 4“ u Kobra X,
      „ACE 1 až 4“ u S1), u každé vzorek, název, druh; stav cívky, pokud ho web zná. Když vyhovuje víc strojů, výběr
      mezi nimi (karty), předvolený ten, kde se návrh potká s nejvíc barvami. Počitadlo „3 ze 7 barev“.
   2. Pod tím u každého dílu (nebo skupiny barev u malovaného 3MF) jen cívky vybraného stroje. Nevyhovující cívky
      viditelné, zašedlé, s důvodem (jiný druh materiálu, pozice obsazená jinou barvou dílu, stroj by měl víc barev než
      pozic). Nic se neschovává bez vysvětlení.
   3. Automatická předvolba nejbližší barvy zůstává a je označená jako předvolená; přepnutí stroje zachová ruční volby,
      které na něm jdou, ostatní znovu předvolí a řekne to.
   4. Náhled modelu se obarví hned, jako dnes. Mobil 390 px: karty pod sebou, dlaždice ve dvou sloupcích.
D. **Administrace:** ověř, že u tiskárny jde nastavit 7 a 8 pozic a že se pojmenování pozic (stojan, ACE) dá zadat nebo
   odvodit z ovladače; kdyby ne, doplň pole `label` u `FarmPrinterSlot` (migrace, SQLite i MariaDB).
E. **Testy:** FarmOrderFlow, FarmOnePlate, CalcToolColors, ThreeMfColors, FarmGuestCheckout zelené; nové: stroj se 7
   pozicemi přijme 7 barev a odmítne 8 s číslem 7 v hlášce; S1 dál 4; předvolba při nahrání 3MF s 6 barvami na farmě
   s Kobra X nechá 6 skupin. `npm run build`, tsc, pint. Projdi stránku zakázky lokálně (postup z AL.md §14.10).
F. **Docs:** `docs/AL.md` nový oddíl (limit, výběr, co kde platí), řádek TODO 66 (řídící ho založila, doplň stav),
   odchylky od zadání sem dolů.

## Pravidla

- Jen tvoje větev a worktree; `GcodeSlot.php` a ovladače agenta neměň (TODO #67 je o ně opřené), když je to nutné, napiš řídící.
- Texty jen přes `lang/src/<název>.json` + `python scripts/lang_add.py`; validuj každý JSON. Git add jmenovitě, žádný stash.
- Commit končí `Co-Authored-By: Claude <model session> <noreply@anthropic.com>`. Produkční databázi ani server nečti.

## Odchylky session 2 (4. 11. 2026, feature/colors-per-machine, docs/AL.md §19)

- Hláška `too_many_colors` neskloňuje: „nejvýš tolik barev, kolik má tiskárna pozic na cívky, tady :n“ (platí pro 4 i 7).
  Stejně `parts_hint` a `by_parts_hint` bez „nejvýš 4 barvy“.
- `spoolsForParts` počítá **vyžádané** barvy (s hlavní), ne jen výsledné: výsledných nemůže být víc než pozic stroje,
  takže by limit stroje nikdy nepadl. Stránka zakázky nikdy nepošle víc, nabízí jen cívky jednoho stroje; týká se jen
  `part_color` při vzniku (API, testy, dřív start). Předvolba za zákazníka je proto po strojích (`nearestSet` hodnotí
  každý stroj zvlášť) a „tisknout znovu“ napasuje staré cívky na jeden stroj (`cap`), aby odmítnutí nikdy nepřišlo samo.
- Pojmenování pozic se odvozuje z profilu stroje (`SpoolPositions`), ne z ovladače agenta (web ovladač nezná). Prusa Core
  One + INDX dostane zatím „Pozice n“, dokud session 4 neřekne, jak INDX pozice číslovat; admin je může pojmenovat sám
  (`label`). Změna v `agent/` ani `GcodeSlot.php` nebyla potřeba.
- „Stav cívky, pokud ho web zná“: web zná jen zbytek gramů a zapnutí; karta a dlaždice říkají důvod (prázdná pozice,
  málo materiálu, cívka se nenabízí, jiný druh, jen ruční podavač), ne stav z tiskárny.
- Počet pozic v administraci: řádky slotů + odkaz „+ přidat pozici“ (`?slots=n`, do 16); žádné samostatné pole počtu.
- Místní průchod podle AL.md §14.10 neprošel: sdílený `dev.sqlite` nemá migrace od 28. 10. (`farm_orders.anonymous_session_id`),
  zakázka se na něm nezaloží; migrace soukromé kopie auto režim odmítl. Stránka zakázky proto vypsaná testem
  (`FarmColorsPerMachineTest::test_the_order_page…`, `MP_DUMP_ORDER_PAGE=<složka>`) a prohlédnutá v headless Chrome
  1280 a 390 px vedle kopie `public/build` (karty, počitadlo, dlaždice, přepnutí stroje, ruční volba). Živé „Přepočítat“
  a platba v prohlížeči neprošly, kryje je PHPUnit.
- `engines.colors.max` z configu odstraněn (nahrazen `ColorLimit::farm()`); `PaintedColors` ukládá do `colors.max` číslo,
  se kterým soubor sloučil, a díly řeže stejným.

