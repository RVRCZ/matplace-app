# Krok C: katalog modelů pro farmu a inspirační katalog

Větev `feature/c-catalog` (z `feature/b-designers`). Dvě veřejné části: `/models` = modely designérů, které
farma tiskne, s cenou a odměnou autorovi; `/model/{slug}` = starý katalog na starých adresách, s odkazem ke
zdroji a s výzvou k tisku tam, kde to licence dovolí.

## Co vzniklo

**Data** (`2026_10_04_100000_catalogue_for_the_farm_and_inspiration`): `catalog_categories`; `catalog_models`
přejmenováno `author → author_name`, `active → visible`, přidáno `slug` (unique), `source_locale`,
`thumbnail_path`, `images`, `license_restricted`, `category_id`, `tags`, `view_count`, `ai_confidence`,
`ai_mismatch`, `ai_reason`, `description` převedeno z textu na JSON podle jazyka; `designer_models` +
`catalog_category_id`, `max_mm`; `collections`, `collection_items`; `farm_orders.catalog_model_id`.

**Katalog farmy `/models`** (`ModelCatalogController`): jen karty, které farma umí vytisknout (viditelná karta
viditelného profilu, soubor prošel kontrolou a má `slice_summary`). Řazení nové / nejvíc tištěné, filtry
kategorie, velikost S/M/L (nejdelší strana do 80 / do 160 / nad 160 mm, `config/catalog.php`), „lze stáhnout“,
stránkování 24, hreflang.

**Stránka modelu `/models/{slug}`**: galerie, popis v jazyce adresy, designér s odkazem na portfolio, licence
a původ (odkaz na zdroj `nofollow`), rozměry, gramy, čas. Cena se přepočítává při změně materiálu a počtu kusů
(`GET /api/models/{slug}/quote`), odměna autorovi je zvlášť („z toho autorovi 25 Kč“). Objednat →
`/farm?file=<uuid>&designer_model=<id>`. Se zapnutým stažením: výběr tiskárny → export 3MF a odkaz na STL,
obojí zapíše `events` typ `download`. Strukturovaná data `Product` (cena od) a `Person`, OG obrázek = cover.

**Objednávka z katalogu**: `farm_orders.designer_model_id` + `royalty_czk` (částka za kus). Souhrn objednávky
i platba ukazují tisk, odměnu autorovi a celkem; `OrderService::priceFor()` vrací `royalty_unit`, `royalty`,
`total`.

**Odměna** (`Wallet::creditRoyalty` / `reverseRoyalty`, volá `OrderFlow::onDone` a `giveBack`): při `done`
`credit_transactions` typ `royalty` na účet designéra, `royalty_czk × copies` v měně jeho účtu, s
`farm_order_id` a `designer_model_id`, `order_count++`. Vrácení peněz po `done` vytvoří `royalty_reversal`
ve stejné výši (jen pokud odměna byla připsaná). Vlastní model = bez odměny.

**Ochrana souborů** (middleware `file`, `GuardModelFile`): geometrii karty (STL, 3MF, podstavec, forma, oprava,
díly) dostane jen designér, admin, nebo kdokoliv, pokud designér povolil stažení. Zákazník, který model
objednal, vidí v objednávce cover místo 3D náhledu a soubor si nestáhne.

**Inspirační katalog**:
- `matplace:import-catalog {--dry-run} {--since=} {--thumbs=} {--assets=}`: kategorie, modely se stejnými slugy,
  náhledy a obrázky zkopírované na disk `public` (`catalog/…`), licence + `license_restricted`, kolekce.
  Přeskočí `status != active` a `nsfw = 1`. Opakované spuštění aktualizuje, nezdvojí a zachová naše překlady;
  plný běh skryje modely, které ve staré DB mezitím přestaly být aktivní. Lokálně ověřeno na kopii staré DB:
  2 498 modelů.
- `/model`, `/model/kategorie/{slug}`: seznam s kategoriemi a hledáním (název + tagy), stránkování 48;
  `/katalog` → 301 na `/model`.
- `/model/{slug}`: galerie, popis, kategorie, tagy, autor, licence, odkaz ke zdroji (`nofollow`), podobné modely.
  Bez ceny. Komerční licence (cc0, cc_by, cc_by_sa, free_commercial): „Máte soubor? Nahrajte ho a nechte
  vytisknout“ → `/farm?source=<id>`; objednávka pak nese `catalog_model_id` a do `note` se sama zapíše
  „Model: název, autor, URL, licence“. Ostatní licence: „jen pro osobní použití, vytisknout nelze“ + odkaz na
  `/models`. Kartu, kterou si převzal designér (stejné `external_url`), nahradí tlačítko objednat jeho model.
- `matplace:translate-catalog {--top=500} {--limit=} {--dry-run}` → job `TranslateCatalogModel`: krátký neutrální
  popis v chybějících jazycích pro modely s komerční licencí a pro nejnavštěvovanější. Každé volání je v `ai_calls`.

**Vyhledávání** (`LocalCatalogSearch`): nejdřív tisknutelné karty designérů (původ `matplace`, karta vede na
`/models/{slug}`), pod nimi inspirace (štítek „inspirace“, vede na `/model/{slug}`).

**Navigace**: v hlavičce „Modely“, v patičce „Inspirace“. Designér u karty vybírá kategorii.

## Rozhodnutí nad rámec zadání

1. **Cena na stránce modelu se počítá ze `slice_summary`, ne novým slicem.** Slicovat při každém zobrazení
   by trvalo desítky sekund a zatížilo server. `ModelPricing::quote()` vezme gramy a minuty z přípravy
   souboru a dosadí je do stejného vzorce, jaký používá farma; hustotu a cenu materiálu bere podle volby.
   Je to „cena od“; závazná cena vznikne slicem objednávky jako dosud a může se o jednotky korun lišit.
2. **Strop 30 % se počítá na kus a odměna se zaokrouhluje dolů na celé koruny.**
   `floor(min(karta.royalty_czk, 0,30 × cena tisku / počet kusů))`. Víc kusů sdílí fixní poplatek objednávky,
   kus je levnější, a strop tedy může zabrat dřív než u jednoho kusu.
3. **Odměna se zmrazí po slicu, ne při založení objednávky.** Při založení ještě cena tisku neexistuje.
   `PrepareFarmOrder` ji zapíše, jakmile je cena známá, a `OrderFlow::pay` ji potvrdí při platbě.
4. **Měna designéra bez zamknuté měny**: podle země profilu (CZ → CZK, jinak EUR kurzem `farm.eur_rate`),
   první odměnou se zamkne (`Wallet::currencyOf`). Účet, který už nějaký pohyb má, zůstává v jeho měně.
5. **Oprava vrácení peněz.** `giveBack` sčítal všechny řádky knihy u objednávky, tedy i odměnu na účtu
   designéra, a zákazníkovi by po `done` vrátil o odměnu méně. `Wallet` teď u objednávky počítá jen řádky
   zákazníka.
6. **Ochrana souboru je širší než zadání.** Zadání řešilo jen tlačítko stáhnout. Bez middleware by ale každý,
   kdo zná UUID z adresy `/farm?file=…`, stáhl STL přes API. Proto hlídání na všech routách, které vydávají
   geometrii.
7. **Česká stránka inspiračního modelu existuje vždy.** Tři čtvrtiny starých popisů jsou anglické texty zdroje
   uložené bez označení jazyka. Jazyk se odhaduje (`LanguageGuess`, diakritika a častá slova) a text se uloží
   pod správný jazyk; česká adresa ukáže text zdroje, dokud nevznikne překlad. `/en/…` a `/es/…` existují jen
   s textem v tom jazyce, jinak 404 s odkazem na českou verzi a bez hreflang.
8. **Obrázky, které se nepodařilo zkopírovat**, se zobrazí ze starého webu (`LEGACY_ASSETS_URL`). Po přepnutí
   domén musí ukazovat na `https://legacy.matplace.com`.
9. **Kolekce se importují, veřejné stránky nemají.** `/collections` a správa přijdou v kroku F, jak říká zadání.
10. **Kategorii u inspiračního modelu bez kategorie** doplní AI klasifikace v kroku F; sloupce `ai_*` už jsou.
11. **Importované popisy mají odrážky na jednom řádku s textem.** Printables balí položky seznamu do odstavců;
    převod na prostý text z toho dělal odrážku na samostatném řádku.

## Co zbývá

- Doprava a měna v cenách (krok D): stránky zatím ukazují Kč; EUR se používá jen při připsání odměny.
- Sitemapy `/models` a `/model` (krok E).
- Veřejné kolekce, AI klasifikace kategorií, správa obou katalogů v adminu (krok F).
- Výplata odměn (mimo rozsah; kniha má `designer_model_id` a typy `royalty`, `royalty_reversal`).

## Nové `.env` klíče

| klíč | výchozí | k čemu |
|---|---|---|
| `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD` | – | stará DB, jen ke čtení, jen pro `matplace:import-catalog` |
| `LEGACY_THUMBS_DIR` | `/var/www/matplace/public/assets/thumbs` | odkud import kopíruje náhledy |
| `LEGACY_ASSETS_URL` | `https://legacy.matplace.com` | odkud se zobrazí obrázky, které zkopírovat nešly; do přepnutí domén `https://matplace.com` |
| `FARM_EUR_RATE` | `25` | kurz Kč → EUR |

## Testy

`tests/Feature/CatalogTest.php` (11 testů): `/models` jen s tisknutelnými kartami a filtry; cena s odměnou
zvlášť a přepočet podle volby; soubor karty jen s povolením designéra; objednávka z katalogu nese odměnu,
`done` ji připíše, vrácení po `done` ji vezme zpět a zákazník dostane celou částku; strop 30 %; vlastní model
bez odměny a zahraniční designér v EUR; import starého katalogu (dry‑run nic nezapíše, druhý běh nic
nezdvojí); NC licence bez výzvy k tisku, CC BY s ní a s poznámkou v objednávce, `/en/…` bez překladu 404;
pořadí ve vyhledávání; fronta překladů; odhad jazyka.

## Nasazení (Roman)

```
php artisan migrate --force
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker

php artisan matplace:import-catalog --dry-run     # vypíše, co by udělal
php artisan matplace:import-catalog               # kategorie, modely, náhledy, kolekce
php artisan matplace:translate-catalog --dry-run  # kolik modelů by šlo k překladu
php artisan matplace:translate-catalog --limit=50 # první dávka, zkontrolovat /admin nebo ai_calls, pak bez limitu
```

- Migrace přejmenovává sloupce `catalog_models` a převádí popisy na JSON. Na betě je v tabulce jen starý
  zkušební import, takže je to bezpečné; záloha tabulky předem neuškodí.
- `.env`: `LEGACY_DB_*` s uživatelem, který má na staré DB jen `SELECT`; `LEGACY_ASSETS_URL=https://matplace.com`
  do přepnutí domén.
- Import potřebuje číst `/var/www/matplace/public/assets/thumbs` (uživatel `www-data` to dnes umí); nic tam
  nezapisuje.
- Překlad: asi 1 600 modelů × jedno volání. S výchozím `claude-opus-5-5` to vyjde řádově na stovky korun,
  s Haiku (`ANTHROPIC_TRANSLATE_MODEL=claude-haiku-4-5-20251001`) na desítky. Skutečnou cenu ukáže `ai_calls`
  po první dávce.
