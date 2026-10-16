# AC – Bez orientační ceny, „Pokračovat“, barvy jednou a s kódy tiskáren

Session 1, větev `feature/order-colors` (z `main` f6b6e02), 16. 10. 2026. Zadání: `docs/prompts/barvy-tok.md`.

## 1. Co se změnilo

**Stránky nástrojů** (všechny, `tools/page.blade.php`, `tool_page.ts`)
- Blok „Orientační cena“ zmizel. `Stage.price()` nic nepočítá; zůstal jako prázdná metoda kvůli `param.ts`,
  do kterého nesmím. Volání odhadu v `art.ts`, `edit.ts` a `map.ts` jsou pryč.
- Tlačítko má ve všech režimech text „Pokračovat“ / „Continue“ / „Continuar“ (jeden klíč `param.go`) a šipku.
  Varianty `param.go.farm` a `param.go.download` jsou smazané. Pod tlačítkem zůstala věta o tom, co ukáže
  kalkulace.
- Texty, které cenu u náhledu slibovaly, jsou přepsané ve všech třech jazycích. Jde o SEO texty 45 nástrojů,
  úvod katalogu, úvody organizéru, dárků, cedulky a nástroje „v životní velikosti“, tlačítko reliéfu („Vytvořit
  model“) a 3. krok stránky dárků.

**Tok k objednávce:** tři stránky, barvy se volí jen na jedné.
1. **Kalkulace → „Vytisknout u nás“.** Přihlášený zákazník má zakázku založenou hned (POST s tokenem kalkulace).
   Z kalkulace se přebírá kvalita, počet kusů, velikost, „bez podpěr“, výplň jako nejbližší pevnost a druh
   plastu (zakázka začne na něm, když je založený). Host se nejdřív přihlásí a pokračuje přes `/farm`.
2. **Materiál a způsob tisku** (horní část `/farm/orders/{token}`) obsahuje:
   - druhy plastu jako filtr (PLA+, PETG… a „Všechny“),
   - jeden seznam barev,
   - druhou barvu, barvy výměn a barvy dílů (stejné cívky jako dřív, jen v téže části stránky),
   - volbu „Tisknout díly zvlášť“,
   - kvalitu, pevnost, podpěry, pokročilé nastavení, velikost, počet kusů a jednotky.
   
   Barva z jiné tiskárny nebo jiného druhu ukáže „Přepočítat pro zvolenou barvu“ hned pod barvami. V platbě je
   místo tlačítka jen věta, že je potřeba nejdřív přepočítat.
3. **Objednávka** (spodní část téže stránky): zvolené barvy jako štítky s kódy, „Změnit“ skočí nahoru. Pak
   doprava, poznámka, podmínky a platba. Druhý výběr barev tu už není.

**Seznam barev** (`OrderService::availableColors`, `offeredColors`)
- Nabízí se jen to, co teď může tisknout:
  - slot, barva i druh jsou povolené,
  - tiskárna je povolená a online (agent viděn za posledních 120 s; ruční tiskárna, když je zapnutá),
  - na cívce zbývá aspoň 50 g po odečtení rezervací. Méně = barva se vůbec nenabídne. Cívka, které stačí na
    jiný tisk, ale ne na tento, zůstane šedá s poznámkou, jako dřív.
- Pořadí (`OrderService::colorOrder`): podle odstínu (`Palette::hue`) červené → oranžové a žluté → hnědé
  a béžové → zelené → modré a fialové → zvláštní (glitter, svítící…) → bílé a šedé → černé. Uvnitř odstínu od
  světlé k tmavé. Stejně jsou seřazené i cívky pro druhou barvu a díly.
- Karta ukazuje vzorek, název, druh a kódy cívek (`U12`, u barvy ve dvou strojích `U11 · U21`). Pod nadpisem je
  vysvětlivka „U33 = farma U, tiskárna 3, slot 3“.

**Kódy**
- Nové pole `farm_printers.code` (migrace `2026_10_19_100000_farm_printers_code`, unikátní) se nastavuje
  v adminu na stránce tiskárny, s nápovědou.
- Prázdné pole = kód podle názvu: „Farm U #3“ → `U3`, jinak první písmeno a další volné číslo („Kobra S1 #1“ →
  `K1`). Stávající tiskárny dostanou kód v migraci, nové a kopie při vytvoření.
- Kód cívky je `code` + slot počítaný od 1 (`FarmPrinterSlot::code()`). `fullLabel()` se nezměnil.
- Kódy se ukazují:
  - na kartě barvy a ve štítcích shrnutí,
  - v řádku pod číslem zakázky,
  - v e-mailech zakázky (řádek „Barvy: PLA+ modrá (U14), druhá barva: bílá (U11)“),
  - na nástěnce farmy: u tiskárny, u slotů, u úloh ve frontě a u běžící úlohy,
  - na admin stránce zakázky.

## 2. Rozhodnutí

**Startovní stránka `/farm` zůstala jako rozcestník, ale bez barev a bez nastavení.** Nedá se zrušit, protože na
ni vede víc cest než z kalkulace:
- vlastní nahrání STL,
- karta návrháře (`designer_model`) a inspirace (`source`),
- „Vytisknout znovu“,
- host, který se musel přihlásit,
- odmítnutá zakázka („model je příliš velký“, denní limit) se vrací s tím, co zákazník poslal.

Ukazuje model a jedno tlačítko. Co adresa přinesla, jde dál ve skrytých polích: kalkulace, nastavení a barvy
opakovaného tisku. Z kalkulace se přihlášený zákazník na `/farm` vůbec nedostane, kalkulace zakázku založí
sama. Proč tlačítko a ne automatické odeslání: tlačítko Zpět ze stránky zakázky by jinak založilo další zakázku.

**Jedna karta na barvu, ne na cívku.**
- Při 20 cívkách bývá stejná bílá nebo černá ve třech strojích. Karta na cívku by ukázala tři stejné karty, které
  se liší jen kódem, a zákazník by vybíral stroj.
- Zákazník vybírá barvu, stroj určuje farma jako dosud: `machineFor` podle druhé cívky nebo cívky dílu, jinak stroj,
  na kterém je zakázka spočítaná, pak ten, co může začít hned.
- Všechny kódy dané barvy jsou na kartě vidět. První z nich je ten, se kterým se počítá.

**Kdy zakázka začíná s barvou:** jen když ji někdo chtěl, nebo ji určuje návrh.
- **Chtěl ji někdo:** „Vytisknout znovu“, nebo odmítnutá zakázka.
- **Určuje ji návrh:**
  - QR kód: světlá deska a tmavý kód,
  - obrázek v barvách filamentu,
  - krabička s barvami dílů,
  - návrh ve volné barvě: nejbližší cívka.

Logika je stejná jako na staré startovní stránce, jen přesunutá do `OrderController::preselect`. Ostatní modely
začínají na nejlevnějším založeném druhu, případně na druhu z kalkulace. Stránka zakázky pak označí první barvu
toho stroje. Protože seznam běží podle odstínu, je to u obyčejného modelu první barva od červené (viz §4).

**„Tisknout díly zvlášť“ se přesunulo na stránku zakázky.** Dřív bylo jen na startovní stránce a bez ní by nešlo
zapnout ani vypnout. Teď je to předvolba jako kvalita: po změně se zakázka znovu nařeže (`reslice` s `by_parts`).
Výchozí stav určuje návrh jako dřív (zapnuto, když návrh pojmenuje barvy dílů).

## 3. Snímky (`docs/img/order-colors/`)

Lokálně, `dev.sqlite`, tiskárny přejmenované na U1–U3 (U3 je agent bez spojení, jeho oranžová se nenabízí).

| soubor | co ukazuje |
|---|---|
| `1-tool-1366.png`, `1-tool-390.png` | organizér: bez orientační ceny, „Stáhnout“ a „Pokračovat →“ |
| `2-calc-1366.png` | kalkulace s tlačítkem „Vytisknout u nás“ (přihlášený: `data-store`) |
| `3-order-1366.png`, `3-order-390.png` | zakázka po kliknutí: druhy PLA+ (6) / PETG (1) / Všechny (7), barvy podle odstínu s kódy (bílá `U11 · U21`), barvy výměn vykrajovátka, níž objednávka se štítky a „Změnit“ |
| `4-start-390.png` | `/farm` z kalkulace: model a „Spočítat →“, bez barev |

Klik v prohlížeči (kalkulace → zakázka) proběhl na 1366 px. Zakázka se nařezala, štítky a kódy odpovídají
nabídce.

## 4. Co zbývá, co jsem neověřil

- **Výchozí barva obyčejného modelu** je první barva stroje v pořadí odstínů, tedy obvykle červená. Dřív to byla
  první barva podle abecedy, takže taky náhoda. Kdyby Roman chtěl jako výchozí třeba bílou nebo barvu s nejvíc
  filamentu, je to jeden řádek v `renderColors` (`farm.ts`).
- **Úspora u nástroje „dutý model“** („ušetří ~X Kč“ v hlášení nástroje `edit.ts`, `priceOf`) zůstala. Je to
  hlášení nástroje, ne blok ceny. SEO text dutého modelu ji proto pořád zmiňuje. Zavolá-li to Roman pryč, jde
  o jeden řádek.
- **Klik z kalkulace u neověřeného e-mailu** ukáže pod tlačítkem hlášku „ověřte e-mail“. Odzkoušené jen testem
  middleware, ne v prohlížeči.
- **Data na produkci:** migrace nastaví kódy podle názvů tiskáren. Pokud se nejmenují „Farm X #n“, dostanou
  písmeno + pořadí (K1, K2…). Roman je přepíše v adminu na U1, B7… a tam taky uvidí, co dostaly.
- Snímky nástěnky farmy a e-mailu nemám. Obojí kryje test `FarmOrderColorsTest` (kód `K14` v e-mailu,
  na nástěnce i na admin stránce zakázky).

## 5. Testy

- Nové: `FarmOrderColorsTest` (pořadí podle odstínu a kódy, přejmenování stroje, offline / ruční tiskárna, cívka
  pod 50 g, vypnutá barva a slot, stránka bez druhého výběru barev, kód v e-mailu, na nástěnce a v adminu).
  Dál `FarmCatalogAdminTest::test_every_printer_has_a_code_of_its_own_and_a_spool_adds_its_slot`.
- Upravené tak, aby předvolené barvy četly ze založené zakázky (`Tests\Support\StartsOrders`), ne z přepínačů
  startovní stránky:
  - `FarmOrderFlowTest`, `FarmPagesTest`, `CustomColorTest`, `PapelPortraitTest`, `StandPartsFarmTest`,
  - v `FarmOrderFlowTest` navíc přepínání dílů zvlášť / spolu přes `reslice`,
  - `ToolPageTest`, `ToolsFlowTest`: bez odhadu, „Pokračovat“.
- `pint`, `tsc --noEmit` a `npm run build` prošly. Výsledek celé sady je v kap. 6.

## 6. Celá sada

Na commitu 229faae (před tímto dokumentem): 637 testů, 41 345 tvrzení, bez chyby, 2 přeskočené. Větev je pushnutá
na `origin feature/order-colors`. Nemerguji ani nenasazuji, to dělá řídící session.
