# Opravy nástrojů (log úkolů pro session E)

Úkoly zadává řídící session podle postřehů Romana; session E dopisuje sloupce „hotovo“ a „commit“.

| # | datum | nástroj | co | kde | hotovo | commit |
|---|---|---|---|---|---|---|
| 1 | 10. 10. | admin + katalog nástrojů | zapnutí/skrytí jednotlivých nástrojů v adminu; skrytý nástroj zůstává správci přístupný k testování | viz úkol #1 níže | | |

## Úkol #1 · 10. 10. 2026 · Zapnutí a skrytí nástrojů v adminu (koordinováno: dotýká se `config/tools.php`, `ToolsController`, sitemapy, `/gifts`)

**Co Roman chce:** v adminu přepínat, který nástroj je **veřejně vidět**, a vypnutý nástroj dál **otevřít a doladit jako
správce** (stránka funguje, náhled, stažení i „Vytisknout u nás“), jen není v katalogu, v sitemapě, v odkazech a pro
nepřihlášené vrací 404.

**Dnes:** `config/tools.php` má u každého nástroje `available => true|false`; podle toho se skládá katalog `/tools`,
karty (`matplace:tool-examples --card`), sitemapa (`SitemapController`), odkazy z `/gifts` a stránek nástrojů
(`ToolsController`, kontrola v `ToolsCatalogTest`). Vypnutý nástroj dnes nevidí ani správce (brčko, otvírák, vložka
do zásuvky jsou `available => false` a Roman je nemůže vyzkoušet na webu).

**Udělat:**
1. Tabulka `tool_flags` (migrace): `tool` (klíč z `config/tools.php`, unikátní), `public` (bool, výchozí = hodnota
   `available` z configu), `note` (text, nepovinná), `updated_by`, časy. Model `ToolFlag`. Služba
   `App\Domain\Tools\ToolVisibility` s `isPublic(string $tool): bool` (= `config available` && `flag.public`,
   výchozí bez řádku = config) a `canOpen(?User $user, string $tool): bool` (= `isPublic` || správce), s cache
   (`Cache::remember('tool_flags', 60)`, smazat při uložení).
2. Všechna místa, která dnes čtou `available`, čtou **`ToolVisibility::isPublic`** (katalog `/tools`, kategorie, karty,
   `/gifts`, sitemapa, odkazy „další nástroje“, OG obrázky, vyhledávání nástrojů, `ToolsCatalogTest`). Stránka
   nástroje: nepřihlášený nebo běžný uživatel → 404 (jako dnes); **správce** → stránka normálně + horní lišta
   „Nástroj je skrytý pro veřejnost, vidíte ho jako správce“ s odkazem do adminu, a `<meta name="robots"
   content="noindex">`. API nástroje (`/api/tools/param`, `/api/tools/edit/…`) pro skrytý nástroj: povolit jen
   správci (stejná kontrola), jinak 404.
3. Admin: nová stránka **`/admin/tools`** (položka „Nástroje“ v admin navigaci vedle katalogu): tabulka všech nástrojů
   z configu – název, cesta, kategorie, `verified`, stav v configu, přepínač **Veřejně viditelný**, poznámka, kdo a kdy
   změnil; uložení řádku bez reloadu (jako jinde v adminu), hromadné „zobrazit vše / skrýt vše“ ne. Filtry: vše /
   skryté / neověřené tiskem.
4. Texty cs/en/es (`lang/*/tools.php` nebo `admin.php`, kde jsou texty adminu): název stránky, sloupce, lišta pro
   správce, poznámka.
5. Testy: `tests/Feature/ToolVisibilityTest.php` – skrytý nástroj: katalog ho neukazuje, sitemapa ne, stránka 404
   pro hosta i uživatele, 200 + lišta pro správce, API 404 pro hosta a 201 pro správce; přepnutí v adminu změní vše
   bez restartu (cache); nástroj s `available => false` v configu se chová jako skrytý a správce ho otevře;
   `ToolsCatalogTest` upravit tak, aby používal `ToolVisibility`.
6. `docs/U.md`? Ne – tahle session píše do `docs/prompts/opravy.md` (řádek úkolu) a krátce do `docs/TOOLS-ADMIN.md`
   (co to je, jak to funguje, cache, migrace).

**Hotovo =** správce v adminu skryje třeba `/tools/box`: zmizí z `/tools`, `/gifts`, sitemapy a karet; host dostane 404;
správce stránku otevře s lištou, vyzkouší náhled, stažení i „Vytisknout u nás“; po zapnutí je vše zpět. Testy zelené,
`pint`, `tsc`, `build`. Nasazení = 1 migrace (napiš to do zprávy řídící session).

**Nedělat:** neměnit `config/tools.php` hodnoty `available` ani `verified`; nepřidávat další pole do adminu (pořadí,
kategorie) – to budou další úkoly.
