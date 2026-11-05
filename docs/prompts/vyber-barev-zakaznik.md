# Výběr barvy pro zákazníka: jedna dlaždice na barvu, kódy pozic jen pro správce, značka startu (TODO #69)

Pro session 2, 5. 11. 2026. Worktree `C:\matplace-gcode-wt` (stojí na sloučené `feature/colors-per-machine`), nová větev
`feature/color-tiles` z `origin/main`. Po pushi napiš řídící session. Nic nenasazuj, žádný `php artisan migrate*` ve
worktree (sdílený `dev.sqlite` nemá migrace od 28. 10., stránku zakázky vypiš testem jako u #66).

## Proč

Roman 4. a 5. 11. 2026: „v barvách se nevyznám pořád“ u zakázky s jedním modelem ve 4 kusech (ne po dílech; #66 tam nic
neměnilo). Rozhodl: kódy pozic cívek vidí jen správce, a to čitelně („Farm U #1 · ACE 3“, tedy název tiskárny a pozice,
ne „U13“). Cena na dlaždicích ne (Roman 5. 11. 2026), zůstává v kartě ceny jako dnes. Zásada: zákazník vybírá barvu, ne cívku; stroj a
pozici řeší farma. Volba barvy před druhem plastu zůstává (Roman 17. 10.: druh je filtr).

## Stav dnes

- Stránka zakázky `resources/views/farm/order.blade.php` asi 168 až 196: `farm-kinds` (čipy druhů, „Všechny (n)“),
  `farm-color-title`, vysvětlivka `farm.order.codes_hint`, mřížka `farm-colors` (2 sloupce, od sm 3), `farm-by-parts-box`,
  `farm-second` s kartami strojů `farm-machines` (#66) a `farm-second-colors`, `farm-start-note`.
- Kód `resources/js/calc/farm.ts`: `codeTag` (asi 490), `renderColors` (asi 492 až 560: čipy, dlaždice s fotkou, názvem,
  druhem, kódy „U33 · U13“, zašedlé `enough === false` s `low_filament`), karty strojů a díly (asi 538 až 660), druhá
  barva a výměny u desky s nápisem (asi 660 až 700, plochý seznam `c.second` přes všechny stroje), štítky kroku 3
  (asi 696 až 715), řádek čísla zakázky `spoolLine` s kódy v závorce (asi 806 až 808), `total()` / `priceNow()`
  (asi 391 až 398), odhad ceny před řezem z `c.rates` (asi 720 až 740, jen pro vybranou barvu).
- Data `OrderController::describe` (asi 923 až 990): `colors` z `OrderService::availableColors` (asi 719): jedna položka
  na `FarmColor` (barva jednoho druhu; Bílá PLA a Bílá PETG jsou dvě), řazení `colorOrder` (odstín, světlost, název),
  `codes` = kódy všech cívek té barvy, které zakázku utisknou (`FarmPrinterSlot::code()` = `FarmPrinter::shortCode()` +
  slot od 1), `price` a `total` po řezu pro stroj první cívky, `rates` před řezem, `starts_now`, `enough`, `second`,
  `machines`. Pozice v pořadí obsluhy: `FarmPrinterSlot::positionLabel()` a `SpoolPositions` (AL.md §19.3).
- Stránka neví, zda ji čte správce (`User::isAdmin()`); v `describe` žádný příznak není.
- Texty `lang/cs/farm.php` farm.order.kind_all, codes_hint, colors_now, color, material, low_filament, starts_now,
  goes_to_queue (vznikly před `lang/src`; změny i tak přes `lang/src` + `lang_add.py`).

## Co udělat

A. **Jedna dlaždice na barvu, ne na barvu a druh.** Seskupení až v `describe` (nebo v `availableColors` s novým tvarem),
   klíč = název barvy (`displayName` malými) + povrch (`finish`); hex a fotka z první položky. Druhy té barvy uvnitř
   dlaždice jako malý přepínač („PLA · PLA+ · PETG“), předvolený druh zakázky (to, co zákazník zvolil v kalkulačce),
   jinak první s dostatkem materiálu. Přepnutí druhu uvnitř dlaždice nastaví `picked` na slot té barvy a druhu; `picked`
   zůstává id slotu, všechno za ním (přepočet, platba, `recolor`, testy) beze změny. Čipy druhů nahoře zůstanou jako
   filtr: po přepnutí druhu zůstane vybraná stejná barva, pokud v tom druhu je, jinak první barva druhu (jako dnes).
   Když má barva jeden druh, přepínač se nezobrazí, druh je malým písmem jako dnes.
B. **Kódy pozic jen pro správce.** Do `describe` příznak `admin` (`$request->user()?->isAdmin()`); `codes` posílej jen
   správci (zákazníkův JSON je nenese). Správci místo „U33 · U13“ text `název tiskárny · positionLabel()`
   („Farm U #3 · ACE 3“), víc cívek oddělených čárkou, u Kobra X „Kobra X · Stojan 2“. Totéž na kartách strojů
   (`printer_code`), u druhé barvy, u štítků kroku 3 a v řádku čísla zakázky (`spoolLine`: zákazník vidí jen název
   barvy, správce i pozici). Vysvětlivku `codes_hint` z Blade pryč (zákazníkovi nic neříká). Administrace
   (`resources/views/admin/farm/*`) kódy používá dál, té se netýká.
C. **Značka startu a pořadí.** Cena na dlaždici ne (Roman 5. 11. 2026), zůstává v kartě ceny pro vybranou barvu. Na
   dlaždici jen malá značka „začne hned“ nebo „do fronty“ (dnes `farm-start-note` jen pro vybranou; nech ho jako větu
   pod seznamem, značka je navíc). Barvy s `enough === false` na konec seznamu, zašedlé, důvod `low_filament`
   zůstává. Mobil 390 px dvě sloupce: nic se neláme mimo dlaždici.
D. **Jeden vzhled pro všechny tři výběry.** Hlavní barva, druhá barva a výměny u desky s nápisem a díly a skupiny z #66
   používají jednu funkci dlaždice (fotka nebo vzorek, název, druh, zašednutí s důvodem, kód pro
   správce). Karty strojů (`farm-machines`) tam, kde zakázka potřebuje víc barev než jednu: u dílů už jsou, u druhé
   barvy a výměn je doplň stejně (karta jen když vyhovuje víc strojů, jinak bez karty); u jedné barvy stroj volí farma,
   karta se neukáže. Když se ukáže, že druhá barva s kartami je velká změna, udělej jen společnou dlaždici a napiš to
   do odchylek.
E. **Testy.** FarmColorsPerMachineTest, FarmOrderFlowTest, FarmGuestCheckoutTest, CalcToolColorsTest, ThreeMfColorsTest,
   FarmColorsFillTest zelené. Nové: dvě `FarmColor` stejného názvu v PLA a PETG dají jednu dlaždici se dvěma druhy a
   `picked` správný slot; zákazník nedostane `codes` ani kódy v HTML, správce dostane „Farm U #3 · ACE 3“; dlaždice
   s málo materiálu je na konci; filtr druhu drží vybranou barvu.
   `npm run build`, `npx tsc --noEmit -p .`, pint. Stránku vypiš testem (`MP_DUMP_ORDER_PAGE`, vzor
   `test_the_order_page_of_the_seven_colour_design_can_be_written_out_for_a_look`) a prohlédni v headless Chrome
   1280 a 390 px pro tři případy: jedna barva ve 4 kusech, deska s nápisem, díly; screenshoty do scratchpadu, cesty
   do zprávy řídící. Nikdy dvě sady PHPUnit najednou.
F. **Docs.** `docs/AL.md` nový oddíl 20 (co vidí zákazník, co správce, seskupení, karty u druhé
   barvy), řádek TODO 69 (řídící ho založila, doplň stav), odchylky od zadání sem dolů.

## Pravidla

- Jen tvoje větev a worktree. `GcodeSlot.php`, `agent/`, `engines/` a soubory session 4 (`feature/agent-prusalink`,
  nesloučeno) neměň. Ceny nepočítej jinak, jen zobrazuj (`priceFor` a odhad beze změny).
- Texty jen přes `lang/src/<název>.json` + `python scripts/lang_add.py`; validuj každý JSON (načtení + duplicitní
  klíče). Git add jmenovitě, žádný stash. Commit končí `Co-Authored-By: Claude <model session> <noreply@anthropic.com>`.
- Produkční databázi ani server nečti. Žádný `php artisan migrate*` ani `db:*` v žádném worktree.
