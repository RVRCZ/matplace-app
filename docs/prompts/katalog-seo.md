# Zadání pro session 2: inspirační katalog a vyhledávače (co indexovat, co obohatit)

Z auditu SEO (18. 10. 2026, ověřeno): tři katalogové sitemapy nesou 12 480 adres (`/model/{slug}` ve všech jazycích,
které model „má“), na produkci je 8 592 `catalog_models` (`App\Models\CatalogModel`, scope `shown()`), vlastní text
stránky je převážně šablona (název, autor, licence, odkaz na původní web, obecný postup), popis anglicky uvnitř české
stránky, bez vlastní fotky výtisku. Stránka je indexovaná (bez `noindex`) a má canonical. Taková stránka těžko
uspěje proti původnímu zdroji modelu a tisíce takových stránek snižují důvěru v celý web.

Rozhodnutí (Roman 18. 10.): **indexovat jen modely, které mají něco navíc; zbytek nechat dostupný, ale `noindex`;
vybrané modely postupně obohatit.** Search Console se teprve připojuje, takže výběr podle dat přijde později.

## 0. Kde pracuješ a pravidla

- Větev **`feature/catalog-seo`** z aktuálního `main`, dokument **`docs/AL.md` §9**.
- Soubory: `app/Models/CatalogModel.php`, `app/Http/Controllers/InspirationController.php`,
  `resources/views/catalog/*` (stránka modelu, kategorie), `app/Support/Sitemaps.php` (+ příkaz `matplace:sitemap`),
  `app/Support/Seo*.php` je‑li, nový příkaz `catalog:enrich`, `lang/src/*.json` + `python scripts/lang_add.py`, testy
  `tests/Feature/Catalog*Test.php`, `SitemapTest`. Gemini: `config/services.php` klíč už je (`GEMINI_API_KEY`),
  použij stávající klienta (hledej `Gemini` v `app/Domain`), model flash. Nesahat: nástroje, kalkulace, farma, blog.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, testy; nemergovat, nenasazovat; hotovo = push + zpráva
  řídící session (conceprt19092026-8c). Příkazy na produkci spouští řídící.

## 1. Co udělat

1. **Pravidlo „má něco navíc“** (`CatalogModel::isIndexable()`): česky napsaný vlastní popis (`description.cs`
   vzniklý u nás, ne jen překlad šablony: sloupec `enriched_at`), **a** skutečná cena z kalkulace dostupná
   (`quote.available`), **a** `file_available`; k tomu volitelně vlastní fotka výtisku (`images` s příznakem `ours`).
   Vše ostatní dostane `<meta name="robots" content="noindex, follow">` (přes `$noindex` layoutu; `follow`, ať
   odkazy na kategorie a nástroje zůstanou) a **nebude v sitemapě**; kategorie a seznamy zůstávají indexovatelné.
2. **Stránka modelu**: česká část nahoře (náš popis, rozměry, odhad gramů/minut, cena a „Vytisknout u nás“, kategorie,
   podobné modely u nás), původní anglický popis níž sbalený jako „Původní popis autora (anglicky)“; en/es stránky
   stejně se svým jazykem. Žádné duplicitní titulky mezi jazyky.
3. **`catalog:enrich {--top=300} {--ids=} {--dry-run}`**: pro modely podle `view_count` (a později podle Search
   Console) vytvoří Gemini **český popis vlastními slovy** (2 krátké odstavce: co to je a k čemu, na co si dát pozor
   při tisku a jaké provedení u nás doporučit; bez vymyšlených rozměrů, ty bere z `max_mm`/`slice_summary`), uloží do
   `description.cs` + `enriched_at`, zapíše cenu; en/es z téhož podkladu, když stránka v tom jazyce existuje. Ukázky
   pěti výsledků do AL.md (schválí Roman, než se pustí na stovky). Cena volání a odhad pro 300 modelů do AL.md.
4. **Sitemapy** jen indexovatelné modely; `matplace:sitemap` vypíše počty (indexované / noindex); `lastmod` =
   `enriched_at` nebo `updated_at`.
5. **Přehled v adminu**: na stránce katalogu v adminu (najdi, kde je správa `catalog_models`; jinak `/admin/catalog`
   nové minimum) sloupce: zobrazení, obohaceno, indexováno; filtr; tlačítko „obohatit vybrané“ (fronta).
6. Nic nemazat, nic neskrývat návštěvníkům: `shown()` zůstává, mění se jen robots a sitemapa.

## 2. Testy a hotovo

`CatalogSeoTest`: model bez obohacení má noindex,follow a není v sitemapě; obohacený s cenou je indexovaný a v
sitemapě; stránka cs ukazuje český popis nahoře a původní popis sbaleně; `catalog:enrich --dry-run` nic nezapíše
(Gemini falešný přes `Http::fake`); počty ze `matplace:sitemap`. **Hotovo =** push `feature/catalog-seo`, AL.md §9
(pravidlo, čísla: kolik modelů zůstane indexovaných, cena obohacení, pět ukázek), zpráva řídící session.
