# Zadání pro session 2: svítící filamenty jen z přímo vedené cívky (TODO #2)

Od Romana (TODO #2, 14. 10. 2026): „Svítivé filamenty na farmě: vypnout v nabídce, nebo jen pro ruční tisk z přímo
vedené cívky.“ Lámou se v měniči (ACE; F26‑000065 zrušena). Na produkci je druh `PLA/luminous` s 9 barvami
(`farm_materials.finish = 'luminous'`). Dnes jde barva do nabídky, když leží v zapnutém slotu online tiskárny
(`app/Domain/Farm/OrderService.php`, filtry přes `FarmPrinterSlot` ~ř. 243, 475, 528), bez ohledu na to, jak je cívka
vedená. Tiskárny v ručním režimu (`farm_printers.mode = manual`) obsluhuje člověk: ten cívku může vést přímo.

## 0. Kde pracuješ a pravidla

- Větev **`feature/manual-only-kinds`** z aktuálního `main`, dokument **`docs/AL.md` §5** (pokračuj v AL.md).
- Soubory: migrace, `app/Models/FarmMaterial.php`, `app/Http/Controllers/Admin/FarmCatalogController.php` +
  `resources/views/admin/farm/materials.blade.php` (druh), `app/Http/Controllers/Admin/FarmPrinterController.php`
  + `resources/views/admin/farm/slots.blade.php` a `printer_edit.blade.php` (cívky v tiskárnách), `OrderService.php`
  (jen filtry slotů), nástěnka/zakázka v adminu (odznak), `lang/*/farm.php` nebo `lang/src/*.json` + `scripts/lang_add.py`,
  testy `tests/Feature/FarmCatalogAdminTest.php`, `FarmOrder*`/`OrderService*`. Nesahat: agent, slicer, profily,
  kalkulace, nástroje.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, testy (`php -d memory_limit=2G vendor/bin/phpunit
  --filter …`); nemergovat, nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Co udělat

1. **Příznak druhu** `farm_materials.manual_only` (bool, výchozí false), v adminu u druhu zaškrtávátko „Jen z přímo
   vedené cívky (ne do měniče)“ s vysvětlením; `FarmMaterial::isManualOnly()`.
2. **Cívky v tiskárnách**: barvu druhu s příznakem jde dát jen do slotu tiskárny v ručním režimu; u tiskárny přes
   agenta uložení odmítne s hláškou („… se láme v měniči; dejte ji do tiskárny v ručním režimu“). Když tiskárnu
   někdo přepne z ručního na agenta a má takový slot, slot se vypne a admin to řekne (hláška při uložení tiskárny).
3. **Nabídka**: `OrderService` ve všech třech filtrech vynechá sloty, kde je barva `manual_only` a tiskárna není
   ruční (pojistka pro případ starých dat). Není‑li taková cívka nikde v ruční tiskárně, barva se nenabízí (jako dnes
   bez cívky).
4. **Zakázka a nástěnka**: u zakázky s takovou barvou odznak „ručně z přímo vedené cívky“ (admin zakázka, nástěnka
   farmy u řádku tiskárny), aby obsluha věděla, proč jde tisk ručně. Zákazník nic nového nevidí.
5. **Produkce**: po nasazení nastaví řídící `PLA/luminous` → `manual_only = true` (nebo Roman v adminu); napiš do
   AL.md přesný příkaz (tinker) a co se stane se sloty, kde svítící barvy dnes leží (které tiskárny: zjistím ti
   na vyžádání výpisem z produkce).

## 2. Testy a hotovo

Druh s příznakem: uložení do slotu agentní tiskárny odmítnuto, do ruční přijato; nabídka barev bez ní, když leží
jen v agentní; s ní, když leží v ruční; přepnutí tiskárny na agenta slot vypne; odznak u zakázky. Tři jazyky.
**Hotovo =** push `feature/manual-only-kinds`, AL.md §5, testy, zpráva řídící session.
