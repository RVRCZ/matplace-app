# Zadání pro session 1: bez orientační ceny, „Pokračovat“, jedna volba barev s kódy tiskáren

Od Romana (16. 10. 2026): „odstraníme orientační cenu a text ‚Pokračovat ke kalkulaci‘ nahradíme ‚Pokračovat‘; dále
následují 3 stránky: 1 kalkulace, 2 zadání materiálu a způsobu tisku, 3 objednávka – na obou jsou barvy, tyhle dvě
by stálo za to rozumně spojit; barvy uspořádat podle barev a ke každé přidat kód tiskárny, např. U33 nebo B73
(první farma, druhá tiskárna, třetí slot); barvy nabízet jen ty, kde je tiskárna online a barva založená a aktivní
u tiskárny.“

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-shapes-wt`** (session 1), nová větev **`feature/order-colors`** z aktuálního `main`
  (`git fetch origin && git checkout -b feature/order-colors origin/main`), dokument **`docs/AC.md`**.
- Jiné session teď neběží, takže smíš i do souborů farmy a stránky nástroje: `resources/views/tools/page.blade.php`,
  `resources/js/calc/tool_page.ts`, `resources/views/farm/start.blade.php`, `resources/views/farm/order.blade.php`,
  `resources/js/calc/farm.ts`, `app/Http/Controllers/Farm/OrderController.php`, `app/Domain/Farm/OrderService.php`,
  `app/Domain/Farm/Palette.php`, `app/Models/FarmPrinter.php` (+ migrace), admin `admin/farm/printer_edit.blade.php`
  a `FarmCatalogController`. Nesahat: `param.ts`, enginy, agent, `GcodeSlot`, `OrderFlow` (platby).
- Pravidla: texty jen v `lang/{cs,en,es}/<skupina>.php` nebo přes `lang/src/<soubor>.json` + `python scripts/lang_add.py`;
  `git add` jmenovitě; žádný stash; `PYTHON_BIN` v `.env`; před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`,
  `npm run build`; testy `php -d memory_limit=2G vendor/bin/phpunit --filter …`. Nemerguješ a nenasazuješ – push na
  `origin feature/order-colors` a zpráva řídící session (nasazuje ona; až poběží).

## 1. Stránka nástroje (všechny nástroje, `tools/page.blade.php`, `tool_page.ts`)

- Blok **„Orientační cena“** (odhad z objemu, věta `param.estimate.note`, ≈ g · min · ks) **pryč**. Tlačítko **Stáhnout**
  zůstává. Tlačítko akce: text **„Pokračovat“** / „Continue“ / „Continuar“ (`param.go.farm`, `param.go.download`,
  `param.go` – sjednotit na jeden klíč `param.go`), ikona šipka.
- Krok „Materiál a počet kusů“ zůstává (předává se do kalkulace). Cena se poprvé ukáže v kalkulaci.
- `Stage.price()` a vše, co odhad počítá, odstranit nebo vypnout (ne jen skrýt); `ToolPageTest`/`ToolsFlowTest` upravit
  (hledají `price-estimate`/„Orientační cena“).

## 2. Tok k objednávce: barvy se volí jednou

Dnes: kalkulace → **`/farm`** (start: karty barev, vytvoří zakázku) → **`/farm/orders/{token}`** (kvalita, nastavení
tisku, a v bloku platby **znovu** výběr barvy + druhá barva + doprava + platba). Cíl: **tři stránky, barvy jen na
jedné**:

1. **Kalkulace** (beze změny) → tlačítko „Vytisknout u nás“.
2. **Materiál a způsob tisku** = `/farm/orders/{token}` nahoře: druh materiálu (PLA, PLA+, PETG, ASA…), kvalita,
   nastavení tisku (výplň, podpěry, žehlení…), **barvy** – jeden seznam (viz 3) pro hlavní barvu, u dvoubarevných a
   dílů i druhou barvu / barvy dílů (dnešní `second`/`parts`, jen zobrazené v tomtéž seznamu). Zakázka se vytvoří hned
   po „Vytisknout u nás“ s materiálem z kalkulace (`/farm` start už barvy nenabízí: buď zmizí a odkaz vede rovnou na
   zakázku, nebo zůstane jen jako rozcestník pro hosty, když je farma zavřená – rozhodni a napiš proč).
3. **Objednávka** = spodní část téže stránky (nebo samostatný krok po „Pokračovat“): shrnutí – zvolené barvy jako
   štítky s kódy (jen zobrazené, odkaz „Změnit“ skočí nahoru), cena, doprava, platba kreditem/kartou. V bloku platby
   **žádný druhý výběr barev**.
- Přepočet (`quote`) při změně barvy/kvality/nastavení jako dnes; „starts_now“ / fronta hláška zůstává.

## 3. Seznam barev

- **Jen dostupné**: cívka v povoleném slotu, barva povolená, tiskárna povolená a **online** (agent viděn za poslední
  2 min, stav ne offline; ruční tiskárna = online, když je povolená – `FarmPrinter` to už říká), `enough` (≥ 50 g).
  Použij `OrderService::offeredColors()` / `availableColors()` – ověř, že filtr odpovídá, a doplň, co chybí (dnes
  je řazení podle připravenosti stroje, ne podle barvy).
- **Pořadí podle barvy**: odstín (`Palette::hue()` – už dává třídu odstínu), uvnitř odstínu světlost; bílá/šedá/černá
  na konci; stejná barva na více strojích = **jedna karta** s více kódy (zákazník vybírá barvu, stroj určí farma jako
  dnes přes `machineFor`), nebo karta na cívku – rozhodni podle toho, co je přehlednější při 20 cívkách, a napiš proč.
- **Kód cívky `<farma><tiskárna><slot>`**, např. `U33` = farma U, tiskárna 3, slot 3; `B73`. Zdroj: nové pole
  `farm_printers.code` (migrace, nullable, např. `U1`, `B7`, `X1` pro Cobru X, `K1` pro K3M), v admin úpravě tiskárny
  s nápovědou; výchozí hodnota z názvu `Farm <písmeno> #<číslo>` → `U1`, jinak první písmeno názvu + pořadí; test
  jedinečnosti. Kód cívky = `code . (slot + 1)`. Ukázat na kartě barvy (`U33 · PLA+, modrá/blue, Matplace`) i ve
  shrnutí objednávky, v e-mailech zakázky a na nástěnce farmy u úlohy (obsluha pozná stroj a slot). `fullLabel()`
  neměnit, kód je zvlášť.
- Karta barvy: vzorek (hex/fotka), název, druh, kód(y); u nedostatku g šedě (jako dnes).

## 4. Testy a hotovo

- `FarmOrderFlowTest`, `FarmPagesTest`, `FarmCardPaymentTest`: tok kalkulace → zakázka → platba bez start stránky
  (nebo s rozcestníkem), barvy jen jednou, kód cívky v odpovědi `status`/`describe`, řazení podle odstínu, offline
  tiskárna své barvy nenabízí, ruční povolená ano; `ToolPageTest`/`ToolsFlowTest` bez odhadu ceny a s „Pokračovat“;
  `FarmCatalogAdminTest`: pole `code`. Migrace jen pro `code`.
- V prohlížeči (headless, 390 i 1366): nástroj → Pokračovat → kalkulace → Vytisknout u nás → materiál/nastavení/barvy
  (kódy, pořadí) → objednávka (štítky, doprava, platba); snímky do `docs/img/order-colors/`.
- **Hotovo =** push `feature/order-colors`, AC.md s rozhodnutími (start stránka, karta na barvu vs. na cívku), snímky,
  testy, `pint`, `tsc`, `build`; zpráva řídící session.

## 5. Co není v zadání

Ceny a platby (`OrderFlow`, `Wallet`), agent, G-code, farma po dílech (jen zobrazení), nová slova v nabídce materiálů.
