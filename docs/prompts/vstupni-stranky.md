# Zadání pro session 3: pět vstupních stránek (důkazy, odpovědi před objednáním) a Core Web Vitals

Z auditu SEO (18. 10. 2026): největší potenciál mají konkrétní potřeby, pro které už máme nástroj: **cena 3D tisku
z modelu** (hlavní stránka = kalkulace), **figurka z vlastní fotky** (busta `/tools/figure`, mazlíček
`/tools/pet-figurine`), **QR cedulka** (`/tools/qr`, Wi‑Fi, menu), **organizér na míru**, **krabička na míru**
(`/tools/...`, viz `config/tools.php`). Na každé z nich má být vidět výsledek (fotka výtisku, rozměry, cena, doba,
doručení) a odpovědi, které člověk potřebuje před objednáním. Rychlost (Core Web Vitals) audit neměřil.

## 0. Kde pracuješ a pravidla

- Větev **`feature/landing-pages`** z aktuálního `main`, dokument **`docs/AH.md` §11**.
- Soubory: `resources/views/calculator/index.blade.php` (hlavní stránka), stránky nástrojů přes stávající šablonu
  (`tools/page.blade.php` jen přidáním volitelných bloků, ne přestavbou), `config/tools.php` (nové klíče bloků na
  nástroj), `lang/src/*.json` + `python scripts/lang_add.py`, `app/Support/Schema.php` (FAQ a Product JSON‑LD),
  `scripts/review_round2.mjs` (měření), testy `ToolPageTest`, `WebRound2Test`, `AuditRestTest`, `SeoTest`.
  Nesahat: logika nástrojů (`param.ts`, enginy), kalkulace (jen bloky pod ní), farma, platby.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, `tsc`, `build`, testy; nemergovat, nenasazovat; hotovo =
  push + zpráva řídící session (conceprt19092026-8c). Snímky do `docs/img/landing/`.

## 1. Bloky na každé z pěti stránek (pod nástrojem, ne nad ním)

1. **Výsledek**: 2–4 příklady „takhle to dopadlo“: fotka výtisku, rozměry, materiál a barva, cena, doba tisku, kdy
   odešlo. Fotky dodá Roman (zatím zástupný obrázek a `config('site.examples')` jako u audit-rest, blok se bez fotek
   neukáže; čísla z produkce: busta 80 mm 99 Kč, 120 mm 130 Kč, 150 mm 200 Kč; ostatní si spočítej na matplace.com
   bez objednávky).
2. **Než objednáte** (FAQ 5–7 otázek na stránku, cs/en/es, JSON‑LD `FAQPage`): jak dlouho to trvá, co stojí doprava,
   jaké materiály a barvy, přesnost a omezení (busta: podobnost, co fotka musí mít; QR: čitelnost, velikost, počet
   kusů; organizér/krabička: jak změřit prostor, tolerance), co když se mi to nelíbí, bez účtu/s účtem, limit zdarma.
3. **Pro koho** (QR: kavárna, ubytování, obchod; figurka: dárek; organizér: dílna, kuchyň, šuplík): tři krátké
   odstavce s konkrétním použitím a rozměrem.
4. **Odkazy dál**: související nástroje a článek z blogu (session 1 je souběžně přepisuje: cena, příprava STL, busta).
5. Texty: slovník z auditu (3D model / soubor vs. výtisk / výrobek), žádné „zdarma“ bez limitu u rozhodnutí.

## 2. Core Web Vitals

Změř Lighthouse (mobil i desktop) na těch pěti stránkách před a po: `npx lighthouse <url> --preset=... --output=json`
(instaluj do scratchpadu, ne do repa) nebo přes puppeteer + PerformanceObserver; čísla LCP, CLS, INP/TBT, velikost
JS/CSS, největší obrázek. Oprav, co je levné: rozměry obrázků (CLS), `loading="lazy"` mimo první obrazovku,
`fetchpriority="high"` na LCP obrázku, fonty `font-display`, odložené skripty třetích stran (žádné by být neměly),
3D náhled až po interakci nebo po načtení stránky. Čísla před/po do AH.md.

## 3. Testy a hotovo

`LandingPagesTest`: pět stránek nese bloky FAQ (JSON‑LD validní), „pro koho“, odkazy na existující routy; bez
`site.examples` se blok výsledků neukáže; tři jazyky bez chybějících klíčů. **Hotovo =** push `feature/landing-pages`,
AH.md §11 (bloky, čísla CWV před/po, co chybí od Romana), zpráva řídící session.
