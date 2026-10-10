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

## 6. Díly po barvách (8. 10. 2026)

Druhý způsob, jak dostat víc barev do jedné zakázky: návrh z několika **samostatných dílů** (krabička a víko,
květináč a miska, razítko a držátko, lightbox, modulární box, rozřezaný model, puzzle, desky vrstveného obrazu)
se vytiskne **díl po dílu, každý ze své cívky**. Žádná výměna uprostřed tisku, žádný vliv na kvalitu; jen víc
tisků za sebou na jednom stroji. Výměny ve výšce (§1–5) zůstávají pro barvy *nad sebou* v jednom kuse.

### Co se změnilo

- **`farm_orders.by_parts`** (bool) a **`farm_orders.part_plates`** (JSON, migrace `2026_10_15_100000`):
  `[{part, slot_id, color_id, copies, stl_path, gcode_path, sha256, minutes, grams, meters, dims, piece_dims, supports}]`
  v pořadí tisku. Před nařezáním jen `part, slot_id, color_id` (slot `null` = hlavní cívka, dokud ji zákazník nevybral).
- **`FarmOrder`**: `isByParts()`, `partPlates()`, `plateSpool($plate)` (cívka desky; jiná tiskárna nebo nic → hlavní
  cívka), `absoluteGcodePath($plate)` vrací G‑code desky, `plateLayout()` = kusy na každé desce. `plates` = počet
  dílů; mechanika desek (`plates_done`, `nextPlate()`, `OrderFlow::plateFinished`, fronta) se **nemění**.
- **`OrderService::designParts(ModelFile)`**: díly, které jsou samostatné předměty: generátory z `ASSEMBLED`
  (`box, vase, stamp, logo, qr, lightbox, cutter, modular` přes `ParametricGenerator::partsOf`) a editační nástroje
  kromě `colors` (`ModelEditor::partsOf`, `tool_params.parts`), včetně `filament_art`. Barevné vrstvy obrázků
  (`charm`, `gingerbread`…), `sign` a malovaný 3MF (`colors`) **nejsou** díly – tisknou se v kuse s výměnami.
  `partStl()` postaví díl znovu (`ParametricGenerator::build(kind, params, part)`) nebo vezme `…/parts/<part>.stl`,
  který nástroj uložil. `partLabel()` překládá jméno dílu stejnými klíči jako stránka nástroje
  (`param.part.<díl>.<druh>`, `param.part.<díl>`, `edit.art.part.*`, `edit.<op>.part.*`, `edit.part.*`).
- **Založení** (`create(... ?array $partColorIds)`, parametry `by_parts=1`, `part_color[<díl>]=<id barvy>`):
  `spoolsForParts()` dá každému dílu hlavní cívku nebo jinou cívku téhož stroje (`secondSpools`), nepojmenovaný díl
  = hlavní; víc než 4 různé cívky → `too_many_colors`. `color_changes` se u zakázky po dílech neukládají.
- **`PrepareFarmOrder::prepareParts()`**: pro každý díl STL → `PrintPreparer::prepare` → při `copies > 1`
  `PlateLayout::replicate` (kopie dílu na jedné desce; když se nevejdou, chyba **`copies_fit`** – po dílech se
  deska nedělí) → `ModelValidator::judge` → řez stejnými parametry jako celek (`sliceSetup()`, společné pro oba
  režimy) → `orders/<token>/part-<k>.gcode`. Kontrola, orientace a `print_stl_path` jsou z první desky;
  `est_*` jsou součty; `slice_result.parts` nese minuty a gramy po dílech; cena přes `price()` jako dřív.
- **Tisk**: `Dispatcher::start` bere slot z `plateSpool($plate)`; agent i admin dostanou kopii G‑code s `T<slot>` té
  desky a teplotami její cívky (`PrintProfile::tempsForPlate`); výměny barev se do zakázky po dílech nepíší.
  Admin může stáhnout G‑code konkrétní desky (`…/print.gcode?plate=k`); stránka zakázky vypisuje desky s odkazy.
- **Úvodní stránka**: u návrhu se ≥ 2 díly blok `#farm-parts` s přepínačem *Tisknout díly zvlášť, každý svou barvou*
  a řádkem cívek pro každý díl (`part_color[<díl>]`, „Hlavní barva“ = bez vlastní cívky). Přepínač je zapnutý
  předem u vrstveného obrazu a u návrhu, který dílům přidělil různé barvy (`tool_params.part_colors`); předvýběr =
  nejbližší cívka stroje k barvě dílu z návrhu (hlavní barva se počítá). Při změně první barvy se řádky přepnou na
  cívky nového stroje (`farm.ts`, `paintParts`). „Tisknout znovu“ nese `by_parts` a `part[<díl>]`.
- **Stránka zakázky**: `state.by_parts`, `state.parts = [{part, plate, label, hex, slot_id, name, copies, minutes,
  grams}]`; blok cívek vypíše řádek na díl (hlavní barva + ostatní cívky stroje), platba posílá `part_slots[<díl>]`.
  `OrderFlow::pay(... ?array $partSlotIds)` ověří cívky (hlavní nebo z nabídky stroje, jinak `color_gone`), limit 4,
  uloží `part_plates`; změna tiskárny spustí přeřez jako dřív (kostra dílů zůstává, nové cívky v ní).
- **Texty**: `start.parts_title/parts_hint/part_main/part_main_hint`, `order.parts_title/parts_hint/part_main/part_line`,
  `error.copies_fit` (cs, en, es).

### Rozhodnutí

1. **Přepínač, ne automatika.** Krabička s víkem se dál tiskne v kuse v jedné barvě, dokud zákazník nezaškrtne tisk
   po dílech – je to dvakrát delší zakázka (dva tisky) a výsledek je jiný. Zapnuto předem je to jen tam, kde to
   návrh sám chce (vrstvený obraz, díly s různými barvami z nástroje).
2. **Jedna deska na díl, kopie dílu na ní.** Víc kusů než se na desku vejde = chyba `copies_fit`, ne další desky –
   `plates` musí zůstat rovno počtu dílů, jinak by `plateSpool()` nevěděl, čí deska to je.
3. **Barevné vrstvy nejsou díly.** `designParts()` je úmyslně užší než `partsOf()`: obrázek v barvách má díly
   `body, color_1…` pro 3MF, ale tiskne se v kuse s výměnami (§1–5). Kdo chce další druh do tisku po dílech, přidá
   ho do `OrderService::ASSEMBLED`.
4. **Rezervace gramů** dál jen na hlavní cívce (jako u výměn); minuty a gramy po dílech jsou v `part_plates`.
5. **`print_stl_path` a `gcode_path`** míří na první desku kvůli všemu, co čte jeden soubor (náhled, stáhnutí, starší
   stránky); desky 2+ znají jen `part_plates` a `absoluteGcodePath($plate)`.

### Co není ověřené

- **Nic se netisklo.** Test `test_a_box_with_a_lid_is_printed_by_parts…` projde celou cestu (úvodní stránka,
  založení, dva řezy, platba, G‑code obou desek, agent tiskne desku 1 ze slotu bílé a desku 2 ze slotu červené,
  „tisknout znovu“), ale na stroji to nikdo neviděl. První ostrý tisk: krabička s víkem ve dvou barvách na S1.
- **Stránky** jen testem a `tsc`; řádky dílů na úvodní stránce a na stránce zakázky jsem v prohlížeči neviděl.
- **Vrstvený obraz** (`filament_art`): `designParts()` ho pouští (díly `body, frame, plate_N`), `partStl()` čte
  `…/parts/<díl>.stl`; test to nepokrývá, protože desky obrazu potřebují obrázek. Vyzkoušet ručně z `/tools/art`.
- **Cena**: tisk po dílech stojí součet dílů (minuty + gramy); žádný příplatek za další desku.

### Nasazeno (8. 10. 2026, 18:02 UTC)

`main` = 9d6a49a (farma po dílech ba70ae8 + oprava testu nabídek + session 1 do 9d6a49a), server slil jako 492cb9c, strom
shodný s `main`. Odstávka 16 s, migrace `farm_orders.by_parts/part_plates` proběhla, build s `tsc` prošel, 18 stránek
(úvod, /farm, nástroje, /gifts, /api/config) 200, 11 z 11 tiskáren viděných do tří minut, workery běží, `laravel.log`
bez chyby. Zálohy `/root/matplace_app-20261008-1802.sql` a `/root/matplace-app.env.bak-20261008-1802`. Farma byla
při nasazení vypnutá, 0 běžících úloh. Co zbývá Romanovi: zapnout farmu, první tisk po dílech (krabička s víkem ve
dvou barvách na S1, G‑code druhé desky `?plate=2` s `T<slot červené>`), skryté nástroje session 1 vyzkoušet a odkrýt.

### Doplněk (8. 10. 2026 večer, po prvním klikání Romana)

- **Stránka kalkulace** (`/c/<token>`): návrh s díly v různých barvách (`tool_params.part_colors` ≥ 2 barvy,
  `parts_bbox`) se v náhledu vybarví po dílech – `calculator.ts::partsByColour` rozdělí STL na tělesa (sdílené
  vrcholy), každé těleso přiřadí dílu podle rozměrů (setříděné, takže otočený díl sedí) a přes `Viewer.setPieces`
  + `setPieceColor` ho obarví. Místo tipu druhu („tisknou se najednou, cena za obojí“) se ukáže
  `farm.calc_parts_by_colour` („u nás se každý díl vytiskne zvlášť ze své cívky…“). Ověřeno v headless Chrome na
  místním serveru (krabička zelená, víčko modré).
- **Úvodní stránka farmy**: u návrhu po dílech bez vybrané barvy se první barva volí podle barvy prvního dílu
  (`nearestSet` s hexy ostatních dílů), ostatní díly dostanou nejbližší cívku toho stroje; `?color=` zákazníka má
  přednost. Test `test_a_box_whose_parts_have_colours_starts_by_parts_with_the_nearest_spools`.
- **Výběr barvy na stránce nástroje**: reagovalo jen kolečko; od 3a07fc6 (session 1) otevírá okno celý řádek dílu.

### Nasazeno podruhé (8. 10. 2026, 19:41 UTC)

`main` = d564541 (oprava výběru barvy celým řádkem 3a07fc6, cedulka a klíčenka → skladba vrstev 1f1774c, obarvené díly
na kalkulaci a předvýběr první barvy podle dílu d564541), server slil jako 27fe92d, strom shodný. Odstávka 12 s, bez
migrace, build prošel, 10 stránek 200, log bez chyby. V headless Chrome na produkci: kliknutí na text řádku otevře okno
s 92 cívkami, /tools/nameplate je skladba s odkazem na starý formulář. Záloha `/root/matplace_app-20261008-1941.sql`.

## 7. Katalog barev: ateliérové fotky, hex, nové PLA, rozložení do měničů (9. 10. 2026)

- **Fotky**: 66 barev (druhá zásilka z Číny) dostalo ateliérovou fotku z Romanova Disku („Filamenty“, složky
  pojmenované kódem); `farm:import-photos /root/filamenty --replace`. Druhá složka (e‑shopové fotky, „plaplus…“ =
  PLA+, „silk…“ = Silk, ostatní PLA) dala **15 nových barev** s kódy bez čísla řady: `PLA_White`, `PLA_Beige`,
  `PLA_Yellow`, `PLA_Orange`, `PLA_Brown`, `PLA_Blue`, `PLA_Light_Blue`, `PLA_Sky_Blue`, `PLA_Peacock_Blue`,
  `PLA_Blue_Pink`, `PLA_Dark_Wood`, `PLA_Metallic_Copper`, `PLA+_Coffee`, `PLA+_Peacock`, `PLA_Silk_Sage_Green`.
- **Hex**: `ColorCatalog::hexFromPhoto` (medián středu fotky) dává u ateliérových fotek šeď – filament je vlevo,
  uprostřed je příruba cívky. Hex 66 + 15 barev se proto spočítal zvlášť: pozadí = barva rohů, „živé“ pixely
  (sytost > 40) → medián nejsytější poloviny; duhy a duály = nejčastější hrubý odstín + poznámka „hex z fotky,
  zkontrolovat“; bílá/černá/šedá/stříbrná/transparentní podle jména. Pět ručních oprav (svítící modrá, rose gold,
  PETG hnědá, ABS hnědá, šalvějová). Kdo bude příště plnit hex z ateliérových fotek, ať tenhle postup přenese do
  `hexFromPhoto` (parametr „kompozice“), zatím je jen ve skriptu řídící session.
- **Nabídka farmy**: Roman 9. 10.: jen PETG, všechny druhy PLA a ASA (materiál ASA zapnut), nejvýš 2 typy
  materiálu na stroj. ABS, ABS+, PC, TPU, TPE zůstávají vypnuté.

### Návrh rozložení cívek (11 tiskáren × 4 sloty ACE)

Zásady: bílá a černá na většině strojů (nejčastější barvy, dvoubarevné cedulky, QR = nefritová bílá + černá);
nejprodávanější barvy (světle modrá, svítící zelená, silk měděná a zlatá) na dvou strojích nebo na stroji, kde se
hodí do kombinace; silk, svítící a matné pohromadě (jiné teploty, jiný vzhled); PETG + ASA na uzavřené S1; na
Kobře 3 Max (otevřená, 420 mm) jen PLA+ pro velké díly. Výměna barvy v jednom tisku jde jen v rodině PLA.

| Stroj | Role | Slot 1 | Slot 2 | Slot 3 | Slot 4 |
|---|---|---|---|---|---|
| Farm B #1 | PLA+ základ A | 02 bílá | 01 černá | 05 červená | 14 světle modrá |
| Farm B #2 | PLA+ cedulky a QR | 03 nefritová bílá | 01 černá | 11 modrá | 12 šedá |
| Farm B #3 | PLA+ základ B | 02 bílá | 01 černá | 04 růžová | 08 oranžová |
| Farm B #4 | PLA+ vánoční | 02 bílá | 05 červená | 06 zelená | 07 zlatá |
| Farm B #5 | PLA+ perníčky a doplňky | 01 černá | 09 hnědá | 10 žlutá | 25 světle fialová |
| Farm B #6 | Silk A (prodejní) | 39 silk zlatá | 40 silk měděná | 57 silk dual zlatá/červená | 44 silk červená |
| Farm B #7 | Silk B | 24 silk bílá | 23 silk černá | 25 silk růžová | 54 silk nebesky modrá |
| Farm B #8 | Svítící | 17 Luminous Green | 17_a Luminous Red | 34 svítící modrá | 33 svítící žlutá |
| Farm U #1 (S1) | Matné a třpyt | 44 matte PLA+ bílá | 44_c matte PLA+ černá | 47_a twinkling červená | 44_a matte PLA+ sky blue |
| Farm U #2 (S1) | PETG + ASA | 48 PETG černá | 51_b PETG oranžová | 72 ASA černá | 72 ASA bílá |
| Farm U #3 (Max) | Velké díly PLA+ | 02 bílá | 01 černá | 12 šedá | 14 světle modrá |

Tam, kde je stejná barva ve dvou dávkách (např. červená `05_PLA+_cerveny` a `9_PLA+_2.0_Red`), založit tu, které
je víc, a druhou v adminu vypnout. Duhy, duály, PETG transparentní/fialová/hnědá, dřevo, mramor a nová řada PLA
zůstávají „na objednávku“: při zakázce se na chvíli vymění slot. Po založení nastavit v adminu u každého slotu
barvu a gramy; nabídka na webu se řídí sloty sama.

### Ověřeno tiskem (10. 10. 2026)

První ostrý tisk po dílech: zakázka F26‑000054, krabička s víkem na Farm U #1 (`kobra-s1-01`), tělo ze slotu 1
(Fire copper – Spectrum), víko ze slotu 2 (měděná), dvě desky za sebou, obě hotové, zakázka `done`. Agent farmy U
mezitím běží na novém počítači (Windows 10 Pro, Wi‑Fi farmy 5 GHz, Python 3.12, úloha `matplace-farm-agent`);
zdejší agent je vypnutý včetně samospouštění.

### Nasazeno potřetí (10. 10. 2026, 11:27 UTC)

`main` = 86dedea: session D (volný výběr barvy na stránkách návrhu, cívky až na kalkulaci „U nás vytiskneme“ a na farmě),
session B (portrét z fotky v papel picado), farma: jednobarevný návrh se na `/farm` předvybere na nejbližší cívku,
`__pycache__` ze sledování pryč. Server 278506c, strom shodný, odstávka 12 s, bez migrace. Záloha
`/root/matplace_app-20261010-1127.sql`. Farma byla při nasazení zapnutá (Roman ji zapnul kvůli testu švu), 0 běžících úloh.

### Nasazeno počtvrté a popáté (10. 10. 2026, 11:39 a 12:20 UTC)

11:39 `main` 1c617e0: session F – zkušební objekt `seam`, volba šikmého švu, šev v hodnocení i v AI posouzení fotek (bez migrace).
12:20 `main` 7819fe5: session B druhé kolo (oddělení postavy přes onnxruntime, nahrání fotky na stránce), session E
úkol #1 (přepínač viditelnosti nástrojů v adminu `/admin/tools`, migrace `tool_flags`, skrytý nástroj pro hosta 404,
správce otevře s lištou) a #2 (odkazy podle viditelnosti). Server ebaa875, výpadek 13 s, záloha
`/root/matplace_app-20261010-1219.sql`. Při nasazení netiskl žádný stroj. Známá závada: náhled vrstveného obrazu
vrací 502 (hlavička X‑Model‑Meta větší než buffer nginx) – oprava v kódu u session D, nebo zvětšení `fastcgi_buffer_size`
v nginx (Roman, viz zpráva 10. 10.).

## 8. Výrobce, nová barva u slotu, mazání z katalogu (10. 10. 2026)

Zadání Romana po prvním zakládání cívek do S1 slot 4: „při přiřazení materiálu k slotu přidej možnost přidat nový
materiál, pokud si z katalogu nevyberu; k barvě ještě druh a výrobce (bílá/white, PLA+, Matplace); do katalogu
materiálů výrobce a možnost materiál smazat; prvotní vlastnosti tisku podle globálního nastavení druhu, pokud se
nenastaví individuálně.“ Upřesnění: všechny dosavadní materiály jsou Matplace, výrobců bude mnoho a přibývají
postupně; nový druh se zakládá jen v katalogu.

### Co se změnilo

- **Migrace `2026_10_16_100000_farm_catalogue_manufacturer`**: `farm_materials.manufacturer` (80 znaků, výchozí
  „Matplace“), unikátní klíč druhu je nově `code + finish + manufacturer` (PETG od dvou výrobců = dva druhy s vlastními
  teplotami a cenou); `farm_colors.manufacturer` nullable (null = výrobce druhu). Nic se nepřepisuje: všechny
  dosavadní druhy dostanou výchozí „Matplace“, barvy zůstanou s null → `FarmColor::maker()` vrací výrobce druhu.
- **`FarmMaterial`**: `DEFAULT_MAKER`, `maker()`, `label()` přidá výrobce jen když není náš („PLA+ Sunlu“; „PLA+“
  zůstává „PLA+“), `usage()` (barvy, zakázky), `copy()` (nový druh podle stávajícího). **`FarmColor`**: `maker()`,
  `usage()` (sloty, zakázky vč. druhé barvy, řádky ladění).
- **`ColorCatalog::codeFor(druh, název, výrobce)`**: kód nové barvy `PLA+_White`, `PLA_Silk_Sage_Green`,
  `PETG_Salmon_Pink_Prusament` (bez diakritiky, slova s velkým písmenem, náš výrobce se nepíše), obsazený kód dostane
  `_2`, `_3`…
- **/admin/farm/printers/{id}** (`printer_edit.blade.php`): v selectu slotu první volba **„+ nová barva (není v
  katalogu)“** rozbalí řádek: název česky, anglicky, druh materiálu (select všech druhů), výrobce (text s našeptávačem
  `<datalist>` všech dosud zapsaných výrobců), hex (volitelně), fotka (volitelně; formulář je teď `multipart`).
  Uložení tiskárny barvu založí (`FarmCatalogController::newColor`: zapnutá, skladem, kód podle `codeFor`, výrobce
  jen když se liší od výrobce druhu) a přiřadí do slotu. Bez názvu nebo druhu → chyba u pole, nic se nezaloží.
  Tiskne s nastavením druhu, dokud nedostane vlastní (Ladění materiálů nebo `print_overrides` barvy) – to platilo už
  dřív (`FarmColor::temps()` / `PrintProfile`), nic nového.
- **/admin/farm/materials**: pole **Výrobce** u druhu (vedle názvu) i u barvy (v rozbalené části vedle přeřazení
  druhu; placeholder = výrobce druhu); v shrnutí barvy se výrobce ukáže, jen když je její vlastní. **Smazat** u barvy
  (pod jejím řádkem) a u druhu (pod jeho formulářem) – tlačítko je jen u nepoužité položky, jinak je místo něj text
  „nelze smazat, používá se (…) – vypněte“. Barva je použitá, když je v některém slotu, na některé zakázce (hlavní
  nebo druhá barva) nebo má řádek ladění; druh, když má barvy nebo zakázky. Mazání druhu smaže i jeho řádky ladění
  (cascade, vznikají automaticky pro každý druh × tiskárnu). Smazání barvy smaže i její fotku.
  **„založit podle“** u druhu = odkaz `?copy={id}#kind-new`: formulář nového druhu je předvyplněný profilem,
  teplotami, cenou a přepisy podle vybraného druhu (vypnutý, stejný kód – admin kód/název/výrobce upraví a uloží).
- Validace kódu druhu je unikátní v rámci `finish + manufacturer`; kód se před kontrolou převede na velká písmena
  (dřív prošel „pla+“ validací a padl na DB).
- Routy `POST /admin/farm/materials/{material}/delete`, `POST /admin/farm/colors/{color}/delete`. Texty v
  `lang/*/farm.php` `admin.maker*`, `admin.new_color*`, `admin.copy_kind*`, `admin.delete*`, `admin.*_in_use`,
  `admin.usage.*` (cs/en/es).
- Test `tests/Feature/FarmCatalogAdminTest.php` (5 testů): výchozí výrobce a popisky, nová barva u slotu (kód,
  výrobce, fotka, přiřazení, druhá se stejným jménem → `_2`, bez názvu odmítnuta), kódy, mazání (založená ve slotu /
  na zakázce zůstane, omyl zmizí i s fotkou; druh s barvami zůstane, prázdný zmizí i s řádky ladění), „založit podle“.

### Rozhodnutí

- Výrobce barvy se **neopakuje**, když je stejný jako výrobce druhu (ukládá se null) – katalog 250 cívek nemá
  250× „Matplace“. Zadá-li admin u barvy jiného výrobce než má druh, zůstane na barvě (cívka Prusament pod naším
  druhem PLA+ tiskne s našimi teplotami, dokud nedostane vlastní).
- Druh jiného výrobce se stejným kódem je **samostatný řádek** (vlastní teploty, profil, cena); v popisku pro
  zákazníka i obsluhu se jmenuje „PLA+ Sunlu“. `OrderService::family()` (písmena kódu) ho řadí do stejné rodiny.
- Mazání jen nepoužitého: zakázka drží barvu, kterou se tiskla (FK `nullOnDelete` by historii smazal), slot drží
  cívku, řádek ladění drží výsledky testů. Všechno ostatní se **vypíná** (`enabled`), jak to bylo.
- Nový druh jen v katalogu (ne u slotu): druh nese profil a teploty, to u zakládání cívky nikdo nevyplní správně.

### Co není ověřené

- Nahrání fotky z řádku nové barvy v reálném prohlížeči (test posílá fake soubor; `enctype` je na formuláři).
- CSV import (`ColorCatalog::import`) výrobce zatím nezná – sloupec `manufacturer` dodat, až bude potřeba.
- Checklisty cívek (Desktop, `civky-farma-*.pdf`) výrobce neukazují.
