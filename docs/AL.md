# AL – Nástroj „Model z G‑kódu“ (G‑kód → STL/3MF, i po barvách)

Session 2, větev `feature/gcode-model` (z `main` be7a12f), 18. 10. 2026. Zadání: `docs/prompts/gcode-model.md`
(TODO #19). Nemergováno, nenasazeno.

## 1. Co nástroj dělá

`/tools/gcode-model` (karta v kategorii „úpravy souboru“, `config/tools.php` → `gcode`): nahrajete `.gcode` do
150 MB a hned vzniká nový model – operace `gcode` v rámci nástrojů „soubor dovnitř, model ven“ (stejná stránka
`tools/edit.blade.php`, `edit.ts`, úloha `EditModel` ve frontě, výsledek jako každý jiný upravený model: náhled,
Stáhnout model, Spočítat cenu a možnosti tisku, díly, 3MF projekt). Nahrání samo je operace: `POST /api/tools/gcode`
(`EditApiController::gcode` → `ModelEditor::fromGcode`), G‑kód zůstává u modelu jako `source.gcode`, `EditModel`
volá **`engines/python/gcode_tool.py`** místo `edit_tool.py`. Nastavení žádné; tlačítko „Vytvořit model“ je u této operace skryté (skript stránky ho potřebuje v DOM).

## 2. Jak z drah vzniká model (`gcode_tool.py`)

1. **Čtení** (čistý Python, ~1 M řádků za pár sekund): G0/G1 s X/Y/Z/E, G2/G3 (oblouky po 5°, I/J i R), G90/G91,
   M82/M83, G92, G28, `T<n>` (i `M620 S<n>` u Bambu), `;TYPE:`, `;WIDTH:`, `;HEIGHT:`, `;LAYER_CHANGE`/`;LAYER:`,
   hlavičkové řádky `; klíč = hodnota` (Orca, Bambu, Anycubic, Prusa) a Bambu „model printing time: …; total
   estimated time: …“, Cura `;TIME:`/`;Layer height:`. Čte se do `max_lines` (1 500 000) řádků; delší soubor se
   tam usekne a výsledek to říká (`truncated`).
2. **Smyčky vnějších stěn**: souvislé extruzní tahy (bez cestovního pohybu, retrakce, změny Z nebo nástroje)
   tvoří polyline; ta, která obsahuje typ *Outer wall* / *External perimeter* / *WALL-OUTER* (i s úseky *Overhang
   wall*), je obrys. Slicer kreslí právě jednu takovou smyčku kolem každého obrysu řezu – vnějšku i každé díry –
   půl šířky linky uvnitř skutečného povrchu. Smyčka je uzavřená, když se konce sejdou do max(1,5 w; 0,8 mm);
   otevřené kusy se řetězí konci k sobě; co zůstane otevřené, se vynechá a počítá (`open_loops`). Samotný `G1 F…`
   (jen posuv) smyčku nepřerušuje – na tom zprvu padala klíčenka z Anycubicu (2 613 otevřených smyček → 0).
3. **Strom vnoření** v každé vrstvě (bod v mnohoúhelníku, podle plochy od největší): smyčka bez rodiče = materiál;
   uvnitř materiálu **téhož nástroje** = díra; uvnitř díry = materiál (ostrov); uvnitř materiálu **jiného nástroje** =
   vlastní materiál (písmeno vložené do destičky). Oblast nástroje = sjednocení jeho materiálových smyček minus to,
   co leží přímo v nich, zvětšené o **půl šířky linky** (`CrossSection.offset`, Round, 8 segmentů: vnějšek ven,
   díry dovnitř – přesně tam, kde byl povrch). Kde se při vložce překryjí dva nástroje, vyhrává vyšší. Vnitřní
   stěny, výplň, horní/spodní plochy tvar neovlivňují (leží uvnitř obrysů); podpěry, límec, obrys, prime/wipe
   tower, čisticí linky a cestovní pohyby se nečtou vůbec (typ ≠ stěna → polyline končí, a jen obrysové smyčky
   se berou). Nic se nerozšiřuje nad šířku linky: oblast pod `w²` se zahodí.
4. **Vytažení**: oblast vrstvy od `z − h` do `z` (`;HEIGHT:`, jinak rozdíl Z, jinak `layer_height` z hlavičky);
   stejné po sobě jdoucí vrstvy jako jeden hranol; hranoly nástroje sjednoceny po dávkách 32 (`batch_boolean`),
   celek položen na Z = 0 a vystředěn. Váza (spirála): smyčky se shrnou po výšce vrstvy.
5. **Barvy a výstup**: jeden nástroj → díl `body`. Více nástrojů **v jedné vrstvě** (`colors_mode = parts`) → díl na
   nástroj `color_1…n` + `part_colors` (hex z `filament_colour`/`extruder_colour`, jinak paleta) → kalkulace ukáže
   barvy, farma tiskne po dílech; vedle dílů i **`parts/colors.3mf`** (vlastní zápis: objekt na barvu, barva jako
   `basematerials`) ke stažení z menu. Více nástrojů, ale nikdy dva v jedné vrstvě (`height`) → jeden díl `body`
   a `color_changes` (spodek první vrstvy každého dalšího nástroje), jak je farma a 3MF projekt umějí (`ModelFile::
   colorChanges`, `ColorChange::addAll`). Soubor s jedním filamentem jmenuje svou barvu pro jakékoli číslo nástroje
   (zakázka farmy ze slotu T3).
6. **Hlavička** → `slicer`, `printer` (`printer_model`/`printer_settings_id`), `profile` (`print_settings_id`),
   `layer_height`, `line_width` (`outer_wall_line_width` / `external_perimeter_extrusion_width` / `line_width` /
   `extrusion_width`), `time_s`, `filament_g` (`total filament used [g]` nebo součet), `filament_m`, názvy a druhy
   filamentů, gramy po nástrojích (Anycubic dělí čárkou, Orca/Bambu/Prusa středníkem).

## 3. Ověření

| soubor | řádků | vrstev | smyček | výsledek | čas |
|---|---|---|---|---|---|
| syntetická kostka 20 mm (2 stěny, výplň, límec, podpěra) | 11 k | 100 | 100 | 7 999 mm³, 20×20×20, vodotěsná | < 1 s |
| kostka s dírou 8 mm | | 100 | 200 | 6 719 mm³ (8 000 − 1 280 = 6 720) | |
| písmeno 6 mm druhým nástrojem v horní půlce | | 100 | 200 | `parts`: color_1 7 639 + color_2 360 mm³, colors.3mf | |
| horní půlka druhým nástrojem | | 100 | 100 | `height`: 7 999 mm³, color_changes [z = 10] | |
| `matplace-T26-000030.gcode` (OrcaSlicer 2.4, Kobra S1, zakázka farmy, T3) | 130 k | 106 | 384 | 37 802 mm³, 116 × 34,6 × 21,2, jeden díl #F2754E, 28 min, 15,88 g | 3 s |
| `baby samoyed keychain` (AnycubicSlicerNext 1.3.7, Kobra 3 Max, T0 + T2, prime tower) | 518 k | 128 | 1 263 | 22 219 mm³, 29,6 × 100,4 × 25,6, `parts`: #212721 99,8 % + #FFFFFF 0,2 %, 1 h 37 min, 25,91 g | 9 s |
| **známé STL** 30 × 20 × 10 s dírou Ø 8 a výřezem 6 × 6 (objem 5 138,2 mm³), nařezané místním AnycubicSlicerNext 2.0.0.3 (S1, tryska 0,2, vrstva 0,14, linka 0,22) | 39 k | 72 | 144 | 5 177,4 mm³ (**+0,8 %**), 30,0 × 20,0 × 10,08 (72 × 0,14) | 1 s |

Původní STL obou skutečných souborů nemám (zakázka je na produkci, klíčenka je cizí); rozměry a objem sedí s tím,
co hlavičky říkají (max_z_height 21,20 / 25,80, filament 12,8 / 20,9 cm³ při řídké výplni). Proto ta třetí řádka:
známé těleso → slicer → G‑kód → nástroj → objem o 0,8 % větší (zaoblené rohy a půl linky navíc v drobných místech),
půdorys přesný na setiny, výška zaokrouhlená na vrstvy. Postup je ve `scratchpad` session 2 (slice s profily
`engines/orca/profiles/*_kobras1_n02.json`, `--arrange 1 --slice 0`). Testy
`tests/Feature/GcodeModelTest.php` (7): kostka ± 5 % a vodotěsná, díra zůstává, díl na barvu + 3MF se dvěma objekty,
změna barvy po výšce v 10 mm s hexem druhého filamentu, hlavičky Prusa/Bambu/Anycubic (čas, filament, barvy, šířka
linky), nesmyslný soubor → `not_gcode`, soubor bez stěn → `no_walls` (přeložené v cs/en/es), `.stl` na endpoint →
422, stránka cs/en/es (jen `.gcode`, 150 MB, bez surových klíčů, v katalogu). Dál `ModelEditTest` (rámec edit),
`ToolPageTest` (MODULES má `gcode`), `pint`, `tsc`, `build`.

Přesnost: povrch leží tam, kde byl střed vnější stěny + w/2; rohy jsou zaoblené na w/2 (jako u skutečného
výtisku). Schodky vrstev zůstávají (model je vytažený po vrstvách), detaily pod šířkou linky chybí, vnitřní dutiny
bez vlastní vnější stěny (např. dutý model s jednou stěnou kolem dutiny – ta má svůj *Outer wall*, tedy zůstane;
dutina vzniklá jen z výplně ne).

Stránka: `docs/img/gcode-model.png` (místní server, krok 1 soubor jen `.gcode` do 150 MB, krok 2 bez nastavení,
krok 3 výsledek; po nahrání se objeví report, barvy dílů a menu Stáhnout model s 3MF po barvách).

![stránka nástroje](img/gcode-model.png)

## 4. Soubory

`engines/python/gcode_tool.py` (nový engine; importuje `Invalid/finish/out/stage` z `edit_tool.py`),
`app/Domain/Tools/ModelEditor.php` (KINDS `gcode`, `GCODE_MAX_MB`, `GCODE_MAX_LINES`, `fromGcode()`, `colors3mf()`,
`make()` odmítá `gcode`), `app/Jobs/EditModel.php` (zdroj = `source.gcode`, skript `gcode_tool.py`, `part_colors`/
`color_changes`/`multi_material` z notes do `tool_params`), `app/Http/Controllers/Api/EditApiController.php`
(`gcode()`, `colors3mf()`), `routes/web.php` (`tools.gcode`, `api.tools.gcode.store`, `api.tools.edit.colors3mf`),
`config/tools.php`, `resources/views/tools/edit.blade.php` (formáty `gcode`, limit 150 MB, upload na nový endpoint,
blok bez nastavení, skryté tlačítko, klíče textů), `resources/views/tools/art.blade.php` (kresba karty),
`resources/js/calc/edit.ts` (nahrání = operace, report, barvy dílů, 3MF po barvách, „jiný soubor“, otevření
z kalkulace), `lang/src/gcode_model.json` → cs/en/es (36 klíčů), `tests/Support/GcodeFixtures.php`,
`tests/Feature/GcodeModelTest.php`, `tests/Feature/ToolPageTest.php` (MODULES).

## 5. Limity a co dál

- Jen FDM G‑kód se smyčkami vnějších stěn; soubor bez nich (jen podpěry, pryskyřice, CNC) → `no_walls`.
- 1 500 000 řádků; víc = oříznuto s varováním. 150 MB.
- Nejsou čteny: `.gcode.3mf` (Bambu balí G‑kód do 3MF), binární G‑kód Prusa (`.bgcode`), G‑kód bez `;TYPE:`
  (starý Cura/Simplify3D zapisuje jinak: Simplify3D `; outer perimeter` – neověřeno, nepodporováno).
- Vložka (jiný nástroj uvnitř) s vlastní dírou: díra ve vložce se pozná (rodič = materiál téhož nástroje) ✓;
  vložka uvnitř díry jiného nástroje je ostrov ✓.
- Co by stálo za to: kontrola proti původnímu STL na produkci (zakázka T26-000030 má svůj model), nahrání
  `.gcode.3mf`, binární G‑kód.

## 6. Nasazení

Bez migrace a bez balíčku (manifold3d, numpy, trimesh už jsou). `npm run build`, `php artisan view:clear`,
`optimize`. Nástroj je `available => true`; v `/admin/tools` jde skrýt. Limit 150 MB vyžaduje `client_max_body_size`
nginx ≥ 150m a `upload_max_filesize`/`post_max_size` PHP ≥ 150M – zkontrolovat na serveru (dnešní modely mají
120 MB).

---

# Svítící druhy jen z přímo vedené cívky (TODO #2)

Session 2, větev `feature/manual-only-kinds` (z `main` dab9592), 18. 10. 2026. Zadání: `docs/prompts/svitive-rucne.md`
(zadání říká „AL.md §5“, ale §5 a §6 už patří G‑kódu, proto §7). Nemergováno, nenasazeno.

## 7. Co a proč

Svítící PLA se v měniči (ACE) láme – F26‑000065 zrušena (Roman, 14. 10. 2026). Druh materiálu má nový příznak
**`farm_materials.manual_only`** („Jen z přímo vedené cívky (ne do měniče)“): jeho barvy jdou založit jen do slotů
**tiskárny v ručním režimu**, kde cívku vede obsluha přímo, nabízejí se jen odtud, a obsluha vidí u zakázky a na
nástěnce odznak „ručně z přímo vedené cívky“. Zákazník nic nového nevidí (ověřeno testem: stránka zakázky ani
`status` JSON takové slovo nenesou). Agent, slicer, profily, kalkulace, nástroje netknuty.

### 7.1 Jak to drží pohromadě

- **Pravidlo na jednom místě**: `FarmPrinterSlot::kindFits(?FarmPrinter, ?FarmColor)` = barva druhu s příznakem smí
  jen do tiskárny, která **není** přes agenta; instanční `kindFitsPrinter()`. `FarmMaterial::isManualOnly()`,
  příznak jde i do `copy()` („založit podle“).
- **Admin, druh** (`/admin/farm/materials`): zaškrtávátko s vysvětlením pod políčky druhu; v záhlaví druhu
  „· jen z přímo vedené cívky“. `FarmCatalogController::saveMaterial` → `manual_only = $request->boolean(…)`.
- **Admin, cívky** (stránka tiskárny i „Založení cívek do všech tiskáren“): skupiny barev v selectu nesou
  u takového druhu příponu „· jen z přímo vedené cívky“; na hromadné stránce je u tiskárny i její režim. Při uložení
  projde řádky slotů `FarmCatalogController::manualOnlyRule()` (jen když tiskárna je/bude přes agenta):
  - barva s příznakem **nově** daná do slotu agentní tiskárny → `ValidationException` u toho slotu („… se láme
    v měniči; dejte ji do tiskárny v ručním režimu (:printer jede přes agenta)“); na hromadné stránce se pravidlo
    vyhodnotí pro všechny stroje **před** zápisem, takže odmítnutí u jednoho slotu nenechá nic napůl uložené;
    nová barva takového druhu psaná rovnou u slotu („+ nová barva“) se u agentní tiskárny ani nezaloží;
  - barva, která ve slotu **už byla** (tiskárna přepnuta z ručního na agenta, nebo příznak přišel až po založení)
    → zůstane ve slotu, ale **`enabled = false`**; byl‑li slot zaškrtnutý, flash (červený řádek pod navigací) říká
    „Slot :n (:color) je vypnutý: :printer teď jede přes agenta …“. Uložení s nezaškrtnutým slotem už nic neříká;
    přepnutí zpět na ruční a zaškrtnutí slot zase nabízí.
- **Nabídka, pojistka** (`OrderService`): `offeredColors` (úvodní stránka), `availableSlots` (→ `availableColors`,
  `machineFor`, platba), `secondSpoolsAcross` **a** `secondSpools` (druhá barva/díly téhož stroje) vynechají slot,
  kde `! kindFitsPrinter()`. Není‑li taková cívka v žádné ruční tiskárně online, barva se nenabízí – jako dnes bez
  cívky. Netknuto: `printerAndMaterialFor`/`loadedMaterials` (druh prvního řezu podle ceny) – svítící druh není
  nejlevnější a jeho barva by se stejně nenabídla; zadání chtělo jen filtry slotů.
- **Odznak**: `FarmOrder::manualOnly()` = kterákoli barva zakázky (první, druhá, změny, díly) je druhu s příznakem.
  `admin/farm/order.blade.php` u řádku Barva, `dashboard.blade.php` u běžící úlohy i u řádků fronty
  (`data-manual-only`, oranžová pilulka). V seznamu cívek pod tiskárnou na nástěnce je cívka, která ve stroji
  nabízet nesmí (stará data), **přeškrtnutá** s titulkem proč – obsluha ji má přendat.
- **Texty**: `lang/{cs,en,es}/farm.php` → `farm.admin.manual_only`, `_hint`, `_short`, `_slots_hint`, `_refused`,
  `_disabled`, `_badge` (7 klíčů × 3 jazyky).

### 7.2 Testy

`tests/Feature/FarmManualOnlyKindsTest.php` (6 testů, 127 tvrzení): příznak z formuláře druhu (i kopie, i zpět);
uložení do slotu agentní tiskárny odmítnuto u správného pole (stránka tiskárny i hromadná stránka; hromadná
neuloží ani ruční stroj z téhož formuláře), do ruční přijato a hned nabízeno; nová barva druhu u agentní se
nezaloží; přepnutí ruční → agent slot vypne, cívku nechá, hláška jmenuje slot, opakované uložení mlčí, zpět na
ruční nabízí; nabídka: barva jen v agentní (stará data) chybí v `status` JSON, v `offeredColors`, v `secondSpools`
i `secondSpoolsAcross`, zatímco černá téhož stroje tam je; v ruční se nabízí s kódem stroje a jako druhá barva
k její bílé; bez příznaku agentní nabízí jako každou jinou; odznak u zakázky a na nástěnce po zaplacení, nic
u zakázky v jiné barvě, nic u zákazníka; sedm klíčů ve třech jazycích. Dál zelené: `FarmCatalogAdminTest`,
`FarmOrderColorsTest`, `FarmPagesTest`, `FarmOrderFlowTest`, `FarmTuningTest`, `StandPartsFarmTest`,
`PlateLayoutFarmTest`; `pint --dirty`.

### 7.3 Nasazení a produkce

Migrace `2026_10_21_100000_farm_materials_manual_only` (sloupec bool, výchozí false – nic se nezmění, dokud druh
příznak nedostane). Bez balíčku, bez buildu JS (jen Blade), `php artisan view:clear`.

**Příkaz pro produkci** (řídící session po nasazení; totéž udělá Roman zaškrtnutím u druhu PLA svítící
v `/admin/farm/materials`). Uložit jako `/tmp/manual_only.php` a spustit
`php artisan tinker --execute "$(cat /tmp/manual_only.php)"` (jako www-data s `HOME=/tmp/wwwhome`):

```php
$kind = App\Models\FarmMaterial::where('code', 'PLA')->where('finish', 'luminous')->firstOrFail();
$kind->update(['manual_only' => true]);
echo $kind->label().' (id '.$kind->id.'): manual_only = 1'.PHP_EOL;
$slots = App\Models\FarmPrinterSlot::with(['color.material', 'printer'])->whereHas('color', fn ($q) => $q->where('farm_material_id', $kind->id))->get();
foreach ($slots as $s) {
    echo $s->code().' '.$s->printer->name.' ['.$s->printer->mode.'] '.$s->color->fullLabel().' '.round($s->remaining_g).' g, '.($s->enabled ? 'zapnutý' : 'vypnutý')
        .' -> '.($s->kindFitsPrinter() ? 'nabízí se dál' : 'NENABÍZÍ SE (tiskárna přes agenta) – přendat do ruční').PHP_EOL;
}
echo count($slots) ? '' : 'žádná svítící cívka není v žádném slotu'.PHP_EOL;
```

Co se stane se sloty, kde svítící barvy dnes leží: v databázi **nic** – příznak sloty nemění. Od té chvíle:
- slot v tiskárně **v ručním režimu** nabízí dál (zákazník barvu vidí s kódem té tiskárny, zakázka dostane odznak,
  obsluha vede cívku přímo);
- slot v tiskárně **přes agenta** z nabídky zmizí hned (pojistka v `OrderService`), na nástěnce je přeškrtnutý; při
  příštím uložení té tiskárny v adminu (její stránka nebo hromadná) se zaškrtnutý slot vypne s hláškou. Cívku je
  třeba přendat do ruční tiskárny a tam ji založit (nebo tiskárnu přepnout na ruční).
- Není‑li žádná svítící cívka v ruční tiskárně online, svítících 9 barev se nenabízí vůbec (jako dnes bez cívky).
Které tiskárny to dnes jsou, vypíše ten příkaz (řídící slíbila výpis z produkce na vyžádání; výpis je součástí
příkazu, zvláštní dotaz není potřeba).

### 7.4 Soubory

`database/migrations/2026_10_21_100000_farm_materials_manual_only.php`, `app/Models/FarmMaterial.php`
(`manual_only`, `isManualOnly()`, `copy()`), `app/Models/FarmPrinterSlot.php` (`kindFits()`, `kindFitsPrinter()`),
`app/Models/FarmOrder.php` (`manualOnly()`), `app/Domain/Farm/OrderService.php` (čtyři filtry),
`app/Http/Controllers/Admin/FarmCatalogController.php` (`saveMaterial`, `saveSlots`, `savePrinter`,
`manualOnlyRule()`), `resources/views/admin/farm/{materials,printer_edit,slots,order,dashboard}.blade.php`,
`lang/{cs,en,es}/farm.php`, `tests/Feature/FarmManualOnlyKindsTest.php`.

---

# Upozornění obsluze: zaplaceno, tiskne se, dotištěno, pokazilo se (TODO #22)

Session 2, větev `feature/admin-notify` (z `main` 4d27dc6), 18. 10. 2026. Zadání: `docs/prompts/admin-upozorneni.md`.
Nemergováno, nenasazeno. Žádný skutečný e‑mail neodešel (testy `Mail::fake`, místní `.env` bez SMTP).

## 8. Co a proč

Roman (18. 10. 2026): „Potřebuji jako admin dostávat info, že někdo něco tiskne, že se něco dotisklo, nebo se něco
pokazilo, než pustím komerční přijímání zakázek.“ Dosud chodily na `admin_email` jen výjimky. Teď chodí i běžný
průběh, **jeden e‑mail = jedna událost**, v češtině, se čtyřmi přepínači v `/admin/farm/settings`.

### 8.1 Události

| událost | kde vzniká | předmět | tělo (řádky) | druh |
|---|---|---|---|---|
| zaplaceno | `OrderFlow::pay()` na konci (po `move(PAID)` a po rozhodnutí, co dál) | „Nová zakázka F26‑000123: zaplaceno“ / „…zaplaceno, čeká na schválení“ / „…zaplaceno, řeže se znovu pro U #2“ | zákazník (jméno (e‑mail)), model (soubor, u nástroje i jeho klíč), materiál a barva s kódem cívky (`spoolText`), tiskárna + režim, kusy (+ podložky), cena, doprava (druh + místo/adresa + země), poznámka, **fronta** („začne hned, hotovo asi za 1 h 47 min“ / „začne asi za X, před ní N“ / „čeká na vaše schválení“ / „čeká, až obsluha potvrdí volnou podložku“ / „tiskárna je offline“ / „řeže se znovu“ / „ruční režim, začne, až tisk spustíte“), u ruční tiskárny navíc „Tisk spustí obsluha: G‑kód stáhněte v adminu…“ | `paid` |
| tiskne se | `OrderFlow::move(PRINTING)` = první hlášení agenta (`AgentService::jobReport` posune zakázku jen z `queued`); jen **tiskárna přes agenta** a jen **první podložka** (`plates_done = 0`) | „F26‑000123 se tiskne na U #1“ | zákazník, model, cívky, tiskárna; u více podložek „Podložka 1 z 2“; „Odhad dotištění: za 1 h 47 min (asi v 14:32)“ – čas v `farm.operator_timezone` (výchozí Europe/Prague; aplikace běží v UTC) | `printing` |
| dotištěno | `OrderFlow::move(DONE)` – u více podložek/dílů až po poslední (`plateFinished`) | „F26‑000123 dotištěno (U #1, 1 h 47 min)“ | doba tisku (změřená, jinak odhad), filament v g, **další krok** (zabalit a vytvořit zásilku s místem / zákazník si vyzvedne / vyhodnotit testovací tisk), „Poslední snímek z kamery je v příloze.“ + **příloha `snimek.jpg`**: poslední `snapshot_path` úlohy; do 300 kB tak, jak je; větší se přes GD zmenší na 1280 px (kvalita 70) do `orders/<token>/snapshot_mail.jpg`; když ani tak nevejde, mail jde bez přílohy a bez té věty | `done` |
| tisk selhal | `OrderFlow::move(FAILED)` (přes `fail()` z agenta nebo z adminu), jen zaplacené zakázky | „F26‑000123: tisk selhal (print_failed)“ | **Co:** důvod česky pro obsluhu (`farm.admin.mail.reason.*`, jinak `farm.error.*`, jinak kód) + detail z tiskárny; **Kde:** tiskárna, podložka, vytištěno N %; **Co dál:** kredit vrácen, zkontrolujte tiskárnu a podložku, zakázku lze vrátit do fronty | `problem` |
| zrušil zákazník | `OrderFlow::move(CANCELLED)` s aktérem `user`, jen zaplacené | „F26‑000123 zrušil zákazník“ | kdy, vráceno zákazníkovi (cena − účtovaná část), účtováno za vytištěnou část (%), tisklo se ano/ne | `problem` |
| start selhal | `AgentService::commandResult` | „F26‑000123: start na U #1 selhal“ | zákazník/model/cívky/tiskárna, Co: odpověď agenta, Co dál: zakázka zůstává ve frontě, potvrďte podložku znovu | `problem` |
| tiskárna offline | `AgentService::watch` (beze změny četnosti: jednou za 6 h, s běžícím tiskem vždy) | „F26‑000123: tiskárna U #1 je offline“ / bez zakázky „Tiskárna U #1 je offline“ | řádky zakázky, kdy se naposledy ozvala | `problem` |
| chyba čeká na potvrzení | `AgentService::jobReport` (Kobra S1 na chvíli hlásí chybu po startu) | „F26‑000123: tiskárna U #1 hlásí chybu, čekám, zda zůstane“ | řádky zakázky + dosavadní vysvětlení | `problem` |
| ruční spuštění | `OrderFlow::onQueued` na ruční tiskárně | „F26‑000123 čeká na ruční spuštění (U #3)“ | řádky zakázky + pokyn | `printing` |
| cizí měna | `CreditController` | beze změny | | `problem` |

Zrušené: samostatný mail „čeká na schválení“ (je to teď předmět a řádek fronty mailu „zaplaceno“). Mail „ruční
spuštění“ se **neposílá v okamžiku placení** (ta informace je v mailu „zaplaceno“: `OrderFlow::$paying`); jde sám
po schválení, po novém řezu nebo po vrácení do fronty – a také při placení, když je druh `paid` vypnutý. Video ke
schválení (`FarmVideos`) posílá `FarmAdminAlert` napřímo jako dřív, přepínače se ho netýkají.

### 8.2 Přepínače a kanál

- `FarmSettings` **`notify_paid`, `notify_printing`, `notify_done`, `notify_problem`** (výchozí `true`,
  `config/farm.php` `settings`; zadání psalo `notify.paid`, ale `FarmSettings::get` čte výchozí hodnotu přes
  `config('farm.settings.<klíč>')` a tečka v klíči by se četla jako vnoření, proto podtržítko). V `/admin/farm/settings`
  vedle „E‑mail obsluhy“ čtyři zaškrtávátka (`#farm-notify`): zaplaceno, tiskne se, dotištěno (se snímkem), problémy.
- `OrderFlow::alertAdmin($subject, $order, $lines, $kind = 'problem', $attachment = null)`: nic bez `admin_email`,
  nic s vypnutým druhem; `Mail::queue` jako dřív, selhání jen do logu. `FarmAdminAlert` nese `kind` a volitelnou
  přílohu (`attachments()`), šablona beze změny.
- Texty **vždy česky** (`Lang::get(…, 'cs')`, i když platí zákazník na `/en/`); klíče `farm.admin.mail.*` (předměty
  s číslem zakázky na začátku, řádky `line_*`, důvody `reason.*`) ve třech jazycích kvůli souměrnosti souborů.
- **Žádné opakování bez nového sloupce**: stavový automat `move()` spustí každý přechod jednou; hlášení agenta
  každých 5 s zakázku znovu neposune (`AgentService` hlídá `status === queued`), zakázka o více podložkách řekne
  „tiskne se“ jen u první (`plates_done = 0`) a „dotištěno“ až po poslední; opakované hlášení `failed` končí na
  uzavřené úloze. Ověřeno testem (3 hlášení „printing“ = 1 mail, 2 hlášení „failed“ = 1 mail).

### 8.3 Testy

`tests/Feature/FarmAdminNotifyTest.php` (6 testů): zaplacení na ruční tiskárně = právě jeden mail s číslem,
odkazem do adminu, zákazníkem, modelem, cívkou, tiskárnou, cenou, dopravou, frontou a pokynem; agentní tisk = „tiskne
se“ jednou po třech hlášeních (s odhadem dotištění) a „dotištěno“ jednou s dobou 1 h 47 min, filamentem, dalším
krokem a přílohou (soubor existuje, `attachments()` má jednu položku); velký snímek (≈ 500 kB) se zmenší na 1280 px
pod 300 kB do `snapshot_mail.jpg`; dvě podložky = „tiskne se“ u první, „dotištěno“ až po druhé; selhání (2× hlášeno)
= jeden problém s Co/Kde/Co dál, neúspěšný start = předmět s číslem, zrušení zákazníkem = vráceno + „tisklo se: ne“;
vypnutý druh `paid` nic (ruční spuštění pak přijde samo), prázdný e‑mail nic; formulář nastavení ukládá čtyři
přepínače. Dál zelené: `FarmOrderFlowTest`, `FarmOfflineAlertTest`, `FarmPagesTest`, `FarmCardPaymentTest`;
`pint --dirty`.

### 8.4 Nasazení

Bez migrace, bez buildu. `php artisan config:clear` (nové výchozí hodnoty), `view:clear`. Fronta: maily jdou přes
`queue` jako dosud – worker musí běžet (dnes běží, zákazníkům chodí). Po nasazení zkontrolovat v
`/admin/farm/settings`, že je vyplněný e‑mail obsluhy a čtyři přepínače zaškrtnuté; první skutečná zakázka ukáže
mail „zaplaceno“ (a u agentní tiskárny „tiskne se“ a „dotištěno“ se snímkem). Pokud by snímky z kamery byly větší než
300 kB i po zmenšení (dnešní snímky S1 mají ~100–200 kB), mail přijde bez přílohy.

### 8.5 Soubory

`app/Domain/Farm/OrderFlow.php` (`pay()` konec, `move()` → `adminEvent`, `fail()`, `onQueued`, `alertAdmin`,
`adminPaid/Printing/Done/Failed/Cancelled`, `orderLines`, `queueLine`, `deliveryText`, `snapshotFor`, `t`, `clock`,
`duration`), `app/Domain/Farm/AgentService.php` (tři volání s číslem zakázky a řádky), `app/Mail/FarmAdminAlert.php`
(`kind`, `attachment`), `config/farm.php` (čtyři `notify_*`), `app/Http/Controllers/Admin/FarmCatalogController.php`
(`saveSettings`), `resources/views/admin/farm/settings.blade.php`, `lang/{cs,en,es}/farm.php` (`admin.mail`),
`tests/Feature/FarmAdminNotifyTest.php`.
