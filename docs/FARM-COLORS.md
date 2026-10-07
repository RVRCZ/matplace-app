# Farma: víc barev na jeden tisk (výměny filamentu jako seznam)

Větev `feature/farm-colors` (ze `feature/tools-shapes` b070caf, 7. 10. 2026), krok mezi session 1 (vánoční sada,
`docs/P.md`) a jejím pokračováním. Dosud farma znala **jednu výměnu barvy ve výšce** (`second_slot_id`,
`GcodeSlot::secondColor`): destička a nad ní text nebo kód. Nástroje session 1 (přívěsek, magnetka, podtácek, ozdoba…)
skládají barvy nad sebe po 0,4 mm a návrh nese celý seznam (`tool_params.color_changes`: výška, hex, kód cívky).
Tahle větev učí farmu ten seznam objednat a vytisknout. Roman 7. 10.: **nejvýš 4 barvy na zakázku** (jeden ACE má
čtyři sloty; Kobra se dvěma ACE umí osm, ale nebudeme to nabízet).

## 1. Co se změnilo

- **`farm_orders.color_changes`** (JSON, migrace `2026_10_14_100000`): `[{z, slot_id, color_id}, …]` odspodu nahoru –
  cívka pro každou výměnu návrhu, kterou zákazník vybral. `second_slot_id` / `second_color_id` zůstávají a nesou
  **první** výměnu (starší stránky, exporty a admin čtou dál, co četly). `FarmOrder::MAX_COLORS = 4`.
- **`FarmOrder`**: `wantedChanges()` = co návrh chce (z `ModelFile::colorChanges()`, přepočtené měřítkem zakázky);
  `colorSlots()` = co zákazník vybral (nový sloupec, nebo starý druhý slot); `colorChanges()` = `T<n>` pro
  tiskárnu: jen sloty téhle tiskárny, výměna na slot, který právě tiskne, se nepíše (A, B, A dává dvě výměny,
  „stejná jako předchozí“ žádnou). `colorChange()` vrací první z nich (kompatibilita).
- **`GcodeSlot::colorChanges($gcode, $changes)`**: pro každou výšku první vrstva, jejíž střed leží nad ní, za blokem
  `; AFTER_LAYER_CHANGE`; dvě výměny v jedné vrstvě → platí horní; výměna na běžící slot se vynechá (počáteční slot
  se čte z řádku `T<n>`, který napsal `retarget`). `secondColor()` je případ s jednou položkou, `fileFor()` bere
  jednu výměnu i seznam a pojmenuje kopii podle všech (`.then2at3_0_1at3_4`). Agent i admin (stažení G‑code)
  posílají `colorChanges()`.
- **Založení zakázky** (`OrderService::create`, parametr `change_color[]` = id barvy pro každou výměnu, starší
  `second_color` = první výměna): `spoolsForChanges()` vybere pro každou výměnu cívku téhož stroje (hlavní cívka
  sama, nebo `secondSpools`); barva jiného stroje = bez výměny; víc než 4 různé cívky → `FarmRefusal('too_many_colors')`.
- **Platba** (`OrderFlow::pay`, parametr `change_slots[]` = id slotů; starší `second_slot` = první): totéž
  s kontrolou, že slot je hlavní nebo z nabídky stroje (`color_gone`), a limit 4.
- **Úvodní stránka** (`/farm`): blok cívek **pro každou výměnu** („Barva 3 (od 3,4 mm)“; u jedné výměny stejné
  texty jako dřív). Předvýběr: hlavní barva + cívky stroje, které jsou dohromady nejblíž barvám návrhu
  (`nearestSet`: podklad = `part_colors.body` nebo destička QR, výměny = hex z `color_changes`; stroj s jedinou
  cívkou dostane penalizaci, když návrh chce víc barev). Od druhé výměny je v nabídce i **první barva** (návrat na ni).
  „Tisknout znovu“ nese `change[]`. Náhled kreslí všechny barvy podle výšek (`multiTone`, řez modelu v každé výšce).
- **Stránka objednávky**: řádek cívek pro každou výměnu z nabídky vybraného stroje (`state.changes` = výšky, hex,
  zvolený slot; `state.max_colors`), nejbližší cívka předvybraná, „stejná jako předchozí“ = bez výměny; platba
  posílá `change_slots`. `second_slot` / `second_color` ve stavu zůstávají.
- **Texty** `lang/{cs,en,es}/farm.php`: `start.change_title`, `start.changes_hint`, `order.change_title`,
  `order.changes_title`, `order.change_same(_hint)`, `order.change_first`, `order.change_line`, `refuse.too_many_colors`.

## 2. Rozhodnutí

1. **Seznam v JSON na zakázce, ne tabulka.** Čte se celý najednou, nikdy po částech; starý druhý slot zůstává
   jako kopie první položky, aby nic z dřívějška nepřestalo fungovat.
2. **Nevybraná výměna = žádná výměna**, ne „první barva“. Zákazník tím říká „pokračuj, v čem tiskneš“; to je
   totéž, co dřív „stejná jako model“ u jedné výměny.
3. **Návrat na první barvu je povolený** (A, B, A): u druhé a další výměny je hlavní cívka v nabídce. G‑code
   dostane `T<hlavní>`; `colorChanges()` to nevynechá, protože běžící slot je v tu chvíli jiný.
4. **Limit 4 barvy** počítá různé cívky včetně hlavní; UI ho nemůže překročit (ACE má 4 sloty), chrání API.
5. **Rezervace gramů** se počítá dál jen na hlavní slot (jako dřív u druhé barvy): barvy nad sebou jsou pár
   gramů, nestojí to za složitější účetnictví. Kdyby se to ukázalo jako problém, `reservedGrams` dostane podíl.
6. **Radio pole na úvodní stránce se jmenují `change_color[i]`** i pro jedinou výměnu; `second_color` zůstalo jako
   parametr API (první výměna) pro odkazy z dřívějška. Test QR kódu čte nové jméno.

## 3. Co není ověřené

- **Nic se netisklo.** `T<n>` ve více vrstvách je stejný řádek, jaký farma píše pro druhou barvu od 22. 9.
  (ověřeno na S1 + ACE), jen vícekrát; ale tři výměny v jednom tisku na S1 nikdo nezkoušel. První ostrý tisk:
  perníček nebo přívěsek ve třech barvách z produkce, s obsluhou u tiskárny.
- **Stránky** jsem proklikal jen testy (úvodní stránka: bloky, předvýběr; objednávka: stav, platba) a sestavením
  (`tsc` bez chyb). Výběr cívek na stránce objednávky myší a kreslení barev v náhledu jsem v prohlížeči neviděl.
- **Admin stránka zakázky** vypisuje řádek „Barvy nad sebou: 3,0 mm → červená, 3,4 mm → černá“ (`colorSlots()`);
  dřív nevypisovala ani druhou barvu. Vyzkoušeno testem stránky, ne okem.
- **Předvýběr cívek na úvodní stránce** běží jen u návrhu, který své barvy výslovně nese (`tool_params.color_changes`
  od nástrojů session 1, nebo QR kód). Obyčejná cedulka s vystouplým textem začíná dál jednobarevně, jak byla
  zvyklá (test `test_a_qr_sign_starts…` to hlídá). To odpovídá i na otázku z `docs/P.md` §3: dvoubarevné návrhy
  z nových nástrojů mají cívky předvybrané podle návrhu.
- **Cena**: výměna barvy nic nestojí (ani dřív nestála). Jestli má mít příplatek, je to řádek v `PriceCalculator`.

## 4. Nasazení (Roman)

```
git pull                      # main po slití feature/tools-shapes a feature/farm-colors
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force   # 1 migrace: farm_orders.color_changes
php artisan optimize
systemctl restart php8.2-fpm matplace-worker 'matplace-worker@*'
```

Po nasazení: `/tools/charm` s obrázkem ve 3 barvách → „Pokračovat k ceně“ → `/farm` (tři bloky barev, cívky stroje
Farm U #1 předvybrané) → objednávka → zaplatit → stáhnout G‑code v adminu a najít `T<n>` ve třech výškách
(`grep -n "colour change" soubor.gcode`). Potom teprve pustit tisk s obsluhou u stroje.

## 5. Nasazeno (7. 10. 2026, 18:27 UTC)

`main` = 5772959 (feature/tools-shapes a764dcf + feature/farm-colors), server slil jako e0a4afa, strom shodný
s `main`. Odstávka 11 s, migrace `farm_orders.color_changes` proběhla, build prošel, stránky nástrojů i `/gifts`
200, `laravel.log` bez chyby, oba agenti s heartbeatem do minuty. Zálohy `/root/matplace_app-20261007-1827.sql`
a `/root/matplace-app.env.bak-20261007-1827`. Testy sloučeného stavu před nasazením: 99 + 34 zelených.
Co zbývá Romanovi: zapnout farmu v adminu, první tisk ve třech barvách z produkce s obsluhou u stroje, G‑code
z adminu před tiskem zkontrolovat (`grep -n "colour change" …`).
