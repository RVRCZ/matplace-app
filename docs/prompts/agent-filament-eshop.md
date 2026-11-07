# Agent: filament do tiskárny z odkazu na e-shop (TODO #71)

Pro session 2, 7. 11. 2026 (Roman: „zadej vytvoření agenta pro přidělování barev do tiskáren … pokud se bude přidávat
filament k tiskárně, aby bylo možno přidat odkaz na e-shop, kde byl filament zakoupen, a agent stáhne z této stránky ten
nejlepší obrázek filamentu a odstín, převezme doporučená nastavení filamentu ze stránky, pokud tam budou, jinak mu přidá
obecný profil a uloží ho do seznamu dostupných filamentů“). Worktree `C:\matplace-gcode-wt` (dnes na sloučené větvi
`feature/indx-tools-by-position`): `git fetch origin`, nová větev `feature/filament-from-shop` z `origin/main` (43ddd971
nebo novější). Po pushi napiš řídící. Nic nenasazuj, žádný `migrate*` ve worktree (sdílená dev.sqlite, testy běží v paměti),
klíče jen od Romana (ANTHROPIC_API_KEY na serveru už je, lokálně počítej s `ENGINE_ASSISTANT=fake`). Texty přes
`lang/src/filament_shop.json` + `python scripts/lang_add.py`, i pro administraci (ploché klíče, ne do `lang/cs/farm.php`).
Commit s `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. Git add jen jménem, žádný stash.

## Romanova rozhodnutí (otázky řídící 7. 11., odpovědi Roman)

1. Pole „Odkaz na e-shop“ u pozice tiskárny (hlavní místo) i u nové barvy v Materiálech.
2. Agent připraví kartu, správce ji potvrdí nebo opraví, teprve pak se uloží barva a cívka do pozice.
3. Vlastní filamenty Matplace už v seznamu jsou. Nákupy: https://www.prusa3d.com/category/filament/ ,
   https://www.majkl3d.cz/filamenty , https://store.sunlu.com/collections/new-fdm-filaments , https://www.3djake.cz/filament .
4. Druh přiřadit k existujícímu (kód + povrch), výrobce k barvě; nový druh agent jen navrhne, nezakládá.
5. Doporučená nastavení ze stránky uložit jako vlastní nastavení barvy (`print_overrides`) s poznámkou „z e-shopu“; ladicí
   řádky session F nechat. Bez údajů na stránce zůstává barva bez vlastních nastavení a tiskne podle druhu (= obecný profil).
6. Odstín: HEX nebo RAL ze stránky, když tam je, jinak z obrázku; správce ho v kartě může posunout.
7. Fotku z e-shopu převzít, **jen pokud neobsahuje logo nebo vodoznak e-shopu**. Správce musí dávat odkazy na správné
   (produktové) stránky. Zákazníkům cizí fotku neukazovat. **Fotky výtisků (když je pořídíme) dávat do fronty k odsouhlasení
   v seznamu filamentů**; odsouhlasená se stane fotkou barvy.
8. Čistou hmotnost cívky do zbývajících gramů pozice; cenu za cívku jen do poznámky.
9. Stejný odkaz podruhé = nová cívka k existující barvě, žádný nový řádek.
10. Model: Claude.
11. Zpětně doplnit odkazy a fotky i k barvám, které už v katalogu jsou: ano, jako druhý krok.

## Stav (co už je)

- Druh = `FarmMaterial` (code, finish, manufacturer, filament_profile, filament_overrides, teploty, price_per_gram, enabled;
  `sliceOverrides()`, `maker()`). Barva = `FarmColor` (farm_material_id, code, name, name_en, manufacturer, hex, photo_path,
  drive_folder, print_overrides json, test_notes, enabled, in_stock, sort). Cívka v pozici = `FarmPrinterSlot` (farm_printer_id,
  slot, farm_color_id, label, remaining_g, enabled; `code()` = kód stroje + pozice, `positionLabel()`).
- Administrace (`routes/web.php` 480 až 491, `app/Http/Controllers/Admin/FarmCatalogController.php`): `/admin/farm/printers/load`
  (`loadSlots` 60, `saveSlots` 83: `slots[printer][i][color|remaining_g|enabled]`, výběr barvy `<select>` v
  `resources/views/admin/farm/slots.blade.php` 36, pravidlo `manualOnlyRule`, po uložení `ProfileLibrary::sync()`),
  `/admin/farm/materials` (`materials` 317, druhy rozbalené s barvami, `saveColor` 425: name, name_en, manufacturer, code,
  hex, photo upload → `farm/colors/<uuid>.<ext>` na public disku, remove_photo, print_overrides json, test_notes, enabled,
  in_stock; kód barvy `ColorCatalog::codeFor`), `/admin/farm/colors/fill` (`ColorCatalog::fill`: hex z fotky
  `hexFromPhoto`, `UNSET_HEX` = „ještě nevíme“, `CHECK_NOTE` u duhových a dřevěných, name_en přes překladač).
  Fotky z exportu Drive: `app/Console/Commands/FarmImportPhotos.php` (nejmenší JPEG složky, 800 px, public disk).
- Vrstvy nastavení tisku `app/Domain/Farm/PrintProfile.php`: 1 druh, 2 druh na tiskárně (`FarmPrinterMaterial` bez barvy),
  3 cívka kdekoli (`FarmColor::print_overrides`), 4 cívka na tiskárně. Které klíče mají smysl: `GcodeSlot::tempsOf`,
  `FarmMaterial::sliceOverrides` (nozzle_temperature, nozzle_temperature_initial_layer, hot_plate_temp*, textured_plate_temp*),
  případně `filament_max_volumetric_speed`. Zapisuj jen tyto, nic jiného z e-shopu do Orcy neposílej.
- Claude: `app/Engines/Ai/ClaudeAssistant.php` `ask(kind, system, user, schema, images[], context)` vrací pole podle JSON
  schématu; obrázky jako https adresa nebo lokální soubor do 4,5 MB (jpeg/png/webp/gif); `config/ai.php` `kind_models`
  (druh dotazu může mít vlastní model, `.env` přepis), `assistant_effort`; každé volání účtuje `AiUsage::record`.
  V testech `App\Engines\Ai\FakeAssistant` (`$answers[kind]`, `$calls`, `$fail`). Přidej druh dotazu `filament` s výchozím
  modelem `claude-sonnet-5-5` (`ANTHROPIC_FILAMENT_MODEL`), čtení jedné stránky s obrázky stojí řádově koruny.
- Stahování: vzor `app/Domain/Designer/DesignerImages.php::addFromUrl` (Http, UA, 20 s, limit velikosti), `CatalogImporter`
  112. Na produkci **není** headless Chrome (503), jen HTTP. HTML parser: ve vendoru je `masterminds/html5` a
  `symfony/css-selector` (přes jiné balíky), `symfony/dom-crawler` ne; dej přednost `DOMDocument`/html5, novou závislost jen
  když bez ní nejde, a napiš to do předání.
- Fronty: workery berou `interactive,default,ai` (ai jen první worker). Karta pro správce čeká 10 až 40 s (stažení +
  Claude): úloha na `config('queue.interactive')` a stránka se ptá na stav každé 2 s (vzor `app/Jobs/EditModel.php`,
  `BuildMap.php`), ne synchronní požadavek.
- Fotky výtisků: `app/Domain/Farm/PrintPhotos.php` (fotobox, `FarmOrder::photo_path` = web JPEG v `prints/<token>/`, zápis
  asi 190 `forceFill`), zakázka zná barvy (`farm_color_id`, `colorSlots()`, `farm_printer_slot_id`, `second_slot_id`).
  Zákaznické dlaždice barev (#69, `OrderController::describe`, AN.md §20) a kalkulačka: zjisti grepem, kde se `photo_path`
  barvy ukazuje zákazníkovi.
- E-shopy (sonda řídící 7. 11. curlem s UA prohlížeče):
  - prusa3d.com: produktová stránka serverová, JSON-LD `Product` (1), `og:image`, stovky obrázků (i cizí produkty), teploty
    v textu („Nozzle temperature 215 °C“, „bed temperature 50-60 °C“), hmotnost v názvu/textu.
  - 3djake.cz: produktová stránka serverová, JSON-LD `Product`, `og:image`, 100+ obrázků, teploty asi v tabulce vlastností
    (sonda je v textu nenašla, ověř na skutečné stránce), hmotnost „1000 g“.
  - store.sunlu.com: Shopify, HTML pro roboty odpovídá **429**; `https://store.sunlu.com/products.json` šlo,
    `https://store.sunlu.com/products/<handle>.json` v sondě vrátilo 19 B (ověř, zkus `.js`, `Accept: application/json`,
    rozestupy). Při 429 kartu nevzdávej: řekni správci, co se nepovedlo, a nech ho fotku a odstín doplnit ručně.
  - majkl3d.cz: vlastní platforma (ne Shoptet), kategorie bez JSON-LD, `og:image` ano; produktovou stránku ověř.
  - Kategorie a výpisy agent odmítne („to není stránka produktu“): JSON-LD `ItemList`/`CollectionPage`, chybí `Product`.

## Co udělat

### A. Data (migrace)

- `farm_colors`: `source_url` (string 500, nullable, normalizovaná: bez utm a kotvy, malá písmena v hostu), `source_meta`
  (json: host, fetched_at, title, weight_g, price + currency, nastavení jak byla přečtena, zvolený obrázek + verdikt loga,
  confidence, model), `photo_source` (string 16: `drive` pro dnešní fotky s `drive_folder`, `upload`, `eshop`, `print`).
  Index na `source_url`.
- `farm_color_photo_proposals`: farm_color_id, farm_order_id (nullable), path (public disk), status pending/approved/rejected,
  note, created_at, decided_at, decided_by (user id).

### B. Karta z odkazu (administrace)

- Na `/admin/farm/printers/load` u každé pozice pole „Odkaz na e-shop“ + tlačítko „Načíst z e-shopu“ (bez zásahu do
  `saveSlots`: JS pošle `POST /admin/farm/colors/from-url` s `url`, `printer_id`, `slot`; odpověď id úlohy; stav
  `GET /admin/farm/colors/from-url/{id}`). Totéž tlačítko u formuláře nové barvy v Materiálech (bez pozice) a u existující
  barvy („Načíst znovu“, bod E).
- Karta (modal nebo blok nad formulářem): obrázek (zvolený), odstín (hex, editovatelný, vedle vzorník), název cs/en, druh
  (`<select>` druhů farmy předvybraný agentem; když agent nenašel shodu, prázdný + jeho návrh „nový druh … od …“ jen jako text),
  výrobce, nastavení ze stránky (tryska, první vrstva, podložka, objemový průtok) s přepínačem „uložit jako nastavení cívky“
  (zapnuto jen když stránka nastavení uvádí), hmotnost → zbývající gramy pozice, cena do poznámky, varování (logo ve fotce →
  bez fotky; není produktová stránka; e-shop odpověděl 429; nízká důvěra). Tlačítka „Uložit barvu a vložit do pozice“ (jen
  z pozice), „Jen uložit barvu“, „Zrušit“. Uložení = `saveColor` logika (kód přes `codeFor`, `manufacturer` jen když se liší
  od výrobce druhu, `in_stock` true, `enabled` podle přepínače, `photo_source` eshop) + případně cívka do pozice
  (`farm_color_id`, `remaining_g`, `enabled`, pravidlo `manualOnlyRule` a `ProfileLibrary::sync()` jako v `saveSlots`).
- Opakovaný nákup: stejná normalizovaná `source_url` už v katalogu → karta řekne „už máme: <barva>“ a nabídne jen vložení
  cívky do pozice s gramy (žádná nová barva, žádné volání Claude).

### C. Agent (`app/Domain/Farm/FilamentFromShop.php` + `app/Jobs/ReadFilamentPage.php`)

1. Ochrana: jen `https?`, host veřejný (rozlišit a odmítnout soukromé a loopback rozsahy, i po přesměrování), HTML do 3 MB,
   obrázek do 6 MB, 20 s na požadavek, max 8 kandidátních obrázků, zmenšené kopie do 1200 px (GD/Imagick jako u fotek).
2. Čtení stránky: JSON-LD `Product` (name, brand, image, offers, weight), `og:*`, Shopify JSON (title, body_html, images,
   variants = barvy a hmotnosti), viditelný text bez skriptů a menu (zkrácený na asi 20 000 znaků), kandidátní obrázky
   z `Product.image`, `og:image` a galerie produktu (největší ze `srcset`), ne z doporučených produktů.
3. Claude (druh `filament`, schéma): is_product_page, name_cs, name_en, manufacturer, material {code, finish} ze seznamu
   druhů farmy v promptu (nebo null + návrh), colour_name, hex_declared (jen když stránka HEX/RAL uvádí; RAL převést
   tabulkou nebo nechat modelu s poznámkou), best_image (index) + pro každý obrázek {shows_this_spool, has_shop_logo_or_watermark},
   settings {nozzle, nozzle_first, bed, max_volumetric_speed, drying} s `declared` příznaky, net_weight_g, price {amount,
   currency}, confidence, notes. Obrázky posílej jako lokální zmenšené kopie, ne cizí URL (e-shop by viděl Anthropic).
4. Odstín: hex_declared → jinak `ColorCatalog::hexFromPhoto` na zvoleném obrázku (ověř, že si poradí s bílým pozadím
   produktové fotky; když ne, nech model odhadnout hex a připiš `CHECK_NOTE`), speciální povrchy (duha, dřevo) jako ve `fill`.
5. Fotka: jen `best_image` bez loga/vodoznaku a `shows_this_spool`; uložit 800 px JPEG do `farm/colors/`, `photo_source`
   eshop. Jinak bez fotky a karta to řekne.
6. Nastavení: jen klíče z vrstvy 3 (`print_overrides`), teploty jako řetězce v poli jako `sliceOverrides`; do `test_notes`
   řádek „Z e-shopu <host> <datum>: tryska 215/220, podložka 60“. Bez údajů nic nezapisovat.
7. Výsledek úlohy do cache/tabulky se stavem (`queued`, `reading`, `asking`, `done`, `failed` + zpráva), správce čte stavem.

### D. Fronta fotek výtisků

- Když zakázka dostane fotku z fotoboxu (`PrintPhotos`, zápis `photo_path`), založ návrh pro každou barvu zakázky
  (hlavní + barvy dílů), která ještě nemá fotku `print` a nemá čekající návrh ze stejné zakázky. Cesta = web JPEG zakázky
  (kopie do `farm/colors/proposals/`, ať ji mazání souborů zakázky po 7 dnech nevezme; viz `matplace:prune`).
- V Materiálech u barvy proužek „Fotky k odsouhlasení (n)“ s náhledy, „Použít“ (stane se `photo_path`, `photo_source`
  print, předchozí e-shopová fotka zůstane v `source_meta.eshop_photo`) a „Zamítnout“. Počet čekajících i v hlavičce stránky.
- Zákazník: fotku barvy ukazovat jen s `photo_source` drive, upload nebo print; `eshop` nikdy (rozhodnutí 7). Projdi
  všechna místa, kde zákazník `photo_path` barvy vidí (#69 dlaždice, kalkulačka, katalog), a dej tam jednu podmínku
  (`FarmColor::customerPhoto()`).

### E. Zpětně (až po A až D)

- `source_url` ve formuláři existující barvy + „Načíst znovu“: stejný agent, doplní jen prázdné (fotka, když barva nemá
  vlastní; hex, když `UNSET_HEX`; name_en; nastavení jen s přepínačem „přepsat“).
- Příkaz `farm:colors-from-shop {--missing}` pro barvy se `source_url` bez fotky nebo bez hex, s rozestupem mezi požadavky
  na stejný host (2 s) a souhrnem jako `ColorCatalog::fill`.

### F. Testy a kontrola

- `tests/Feature/FilamentFromShopTest.php`: `Http::fake` se čtyřmi tvary stránek jako fixtures v `tests/fixtures/shops/`
  (zkrácené skutečné stránky bez obrázků: Prusa s JSON-LD, 3DJake s JSON-LD, Sunlu Shopify JSON, majkl3d bez JSON-LD),
  `FakeAssistant::$answers['filament']`; případy: karta z pozice → barva + cívka + gramy; logo → bez fotky; není produkt
  → zpráva; soukromý host a `http` → odmítnuto; stejný odkaz podruhé → jen cívka; nastavení jen klíče vrstvy 3; návrh druhu
  bez založení druhu; fronta fotek (návrh po fotce zakázky, použít/zamítnout, zákazník e-shopovou fotku nevidí);
  `AiUsage` zapsán. K tomu `FarmCatalogAdminTest`, `FarmColorTilesTest`, `ColorsPayloadTest`, `CalcColorsTest` a vše,
  co grep na `photo_path` najde.
- `vendor/bin/pint --test`, `npx tsc --noEmit -p .`, `npm run build`, PHPUnit po jedné sadě (nikdy dvě naráz), JSON
  fixtures a lang projdi `json.load` s kontrolou duplicit.
- Lokálně skutečné stránky stáhni curlem a z nich udělej fixtures; skutečné volání Claude bez klíče neuděláš, napiš to.

### G. Dokumentace a předání

- `docs/AL.md` nová sekce §21: tok, schéma odpovědi, co které e-shopy dávají, limity, co zbývá.
- `docs/TODO.md` řádek 71: připisuj na konec své buňky přes `rsplit(" | ", 2)`, nikdy nekotvi na text, jiné řádky neměň.
- Paměť: jen připiš odstavec do `C:\Users\roman\.claude\projects\c--Users-roman-Documents-MatPlace-conceprt19092026\memory\project_filament_from_shop.md`,
  `MEMORY.md` nech řídící.
- Pořadí: A, B, C (karta z odkazu funguje) → push a zpráva řídící → D → E. Odchylky od zadání napiš do předání.
