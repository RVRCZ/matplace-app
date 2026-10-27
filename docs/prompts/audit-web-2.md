# Audit z 26. 10. 2026, část web – session 3

Roman předal 27. 10. externí audit (`docs/prompts/audit-2026-10-26.md`, celé znění a co se při ověření nepotvrdilo).
Tohle je tvoje část: body A2, A3 (kalkulace), A4, A5, C13, C14. Řídící dělá A1, A3 (hlavní stránka), B6, B8, B9, D15,
D19; Roman rozhoduje B7, C10, C12, D16, D17, D18, D20. Piš česky, krátce, bez pomlček.

## Pracoviště

- Worktree `C:\matplace-edit-wt`, nová větev `feature/audit-web-2` z `origin/main` (po pushi řídící, nejdřív
  `git fetch origin`; obsahuje tvoji `feature/sales-path-web`, nasazenou 25. 10.). Nemerguješ ani nenasazuješ.
- Deník `docs/AN.md` §12 a dál. Texty přes `lang/src/<soubor>.json` + `lang_add.py`, JSON ověřit. Před pushem pint, tsc,
  build, cílené testy; plná sada na pozadí, nikdy dvě naráz, během běhu neupravovat soubory.
- Nedotýkej se `calculator/home.blade.php` (řídící dělá dlaždice a cenu v hero), farmy a stránky zakázky (session 2).

## Co řídící ověřila 27. 10. ráno

- A2: `/en/` má „3 model a day from a photo without an account“ (`home.benefit.free_tools`, :n = 3 z
  `ai.daily_limits.generate_guest`), „Usually 1 days from payment to handover.“ (blok důvěry, medián 0,5 dne
  zaokrouhlený) a titulek posledního videa z farmy česky „Mystical Chine Dragon V EYE – 10 h 59 min tisku za 29 s“
  (`FarmVideo.title` z DB). `/es/` totéž („modelo al día“, „Normalmente 1 días“).
- A3: na hlavní stránce zbývají dva odkazy `href="#"` z kalkulace: „Upravit tvar a rozměry“ (`calc.edit_design`) a
  „Jen samotný model (.stl)“ (volba stažení). Dlaždice řeší řídící.
- A4: karty nástrojů už mají `<picture>` s WebP 480w/800w, `sizes`, `width`/`height` i `loading="lazy"` (audit viděl jen
  JPEG fallback). Zkontroluj jen, že první dvě karty nad ohybem na telefonu nejsou `lazy` a že 400w stačí.
- A5: HTML hlavní stránky 128 kB, z toho 9 inline skriptů 32 kB; 24,8 kB je `MP_CONFIG` (materiály, profily a všechny
  texty kalkulace ve zvoleném jazyce). Gzip to srazí, ale je to nejdelší kus HTML.
- C13: `/about` nemá žádné JSON-LD, `/materials` také ne. Osobní odběr je na produkci vypnutý (`Shipping::pickup()`
  false), otevírací doba tedy nemá co říct. Firemní údaje jsou v `pages.php` (stránka pro firmy).
- C14: „Hotové modely pro:“ je skrytá sekce výsledků hledání (`#search-section`, `hidden`), ne chyba; audit ji viděl
  v HTML. „Kam dál“ a „Podobné nástroje“ jsou bloky vstupní stránky pod kalkulací (AH.md §11).

## Úkoly

1. **Plurály a jazyk v textech s čísly (A2).** `home.benefit.free_tools`, věta mediánu v bloku důvěry a vše, kde je
   `:n` s podstatným jménem, přes `trans_choice` (cs 1/2 až 4/5+, en 1/jiné, es 1/jiné). Titulek videa: na en a es
   neukazuj český titulek z DB; napiš neutrální popisek („A print from our farm“ + název modelu, pokud ho `FarmVideo`
   nese zvlášť, jinak jen popisek) nebo titulek jen na cs. Projdi `lang/*.json` a `lang/*/*.php` na další „:n model“,
   „:n days“, „:n dní“ a sjednoť. Test: stránky / , /en/, /es/ bez „1 days“, „1 días“, „model a day“ a bez české věty.
2. **Odkazy `href="#"` v kalkulaci (A3).** „Upravit tvar a rozměry“ a „Jen samotný model (.stl)“ jako `<button type="button">`
   nebo skutečný cíl; chování skriptu zachovej (calculator.ts). Projdi i stránky nástrojů (`tools/*`), zda tam nejsou další.
   Test: žádné `href="#"` na /, /tools/qr, /tools/box.
3. **Obrázky (A4).** Jen ověř nad ohybem (první dvě karty na telefonu bez `lazy`, `fetchpriority` jen u jednoho) a napiš
   do AN.md výsledek; měnit jen když je co.
4. **Inline konfigurace (A5).** Navrhni a udělej, co je bezpečné: `MP_CONFIG` jako samostatný cachovaný JSON
   (`/api/config` už existuje: verze v adrese podle hashe obsahu a jazyka, `Cache-Control` na den) načtený skriptem před
   kalkulací, s fallbackem inline, když požadavek selže; nebo aspoň texty kalkulace ven z HTML. Změř HTML před a po
   (gzip i bez). 3D prohlížeč líně až po souboru je větší věc: jen posuď a napiš odhad do AN.md, nedělej.
5. **Strukturovaná data `/about` a `/materials` (C13).** `/about`: `Organization` (název, IČO, adresa z `pages.php`, logo,
   `sameAs` na kanál YouTube a sociální sítě, které web uvádí), `LocalBusiness` až bude osobní odběr s dobou (Roman
   rozhodne, viz D17). `/materials`: `ItemList` materiálů nebo `FAQPage` z otázek, které stránka má. Jen z toho, co
   stránky ukazují; validní JSON-LD, test.
6. **Rozcestník výš (C14).** „Kam dál“ a „Podobné nástroje“ pod kalkulací: posuď, zda je na hlavní stránce přesunout nad
   patičku do jedné řádky odkazů hned pod blok nástrojů, bez zatížení první obrazovky; udělej jen když to nezvětší HTML.
   `#search-section` nech.

## Ověření a výstup

Cílené testy + plná sada na pozadí, build, snímky 390/1280 px pro /, /en/, /about. Zpráva řídící
(conceprt19092026-91) po dokončení: změněné soubory, co se změnilo, jak ověřeno, výsledky, co zůstalo.

## Doplněk 27. 10.: návštěvnost (Roman: hlavní cíl je teď návštěvnost, ne prodej)

Podklad `docs/prompts/priority-navstevnost-2026-10-27.md` body 2, 4, 7, 8. Úkoly 1 až 6 výše zůstávají (jsou to opravy
kvality), ale tyhle mají přednost. Nová session N1 dělá texty nástrojů a blog (`navstevnost-nastroje.md`), session 2
YouTube a fotky výtisků (`youtube-kanal.md`); s N1 si nepřekrývej soubory `tools_seo`, `landing`.

7. **Sdílet výsledek** u každého nástroje a kalkulace: tlačítko (Web Share API, jinak zkopírovat odkaz `/c/<kód>`),
   událost `share` v Track (bez obsahu), text cs/en/es. Odkaz, který někdo sdílí, je návštěva zdarma.
8. **Vložitelná kalkulačka**: stránka `/embed/calc` (minimální: nahrání → cena → „otevřít na matplace.com“ s utm) a
   stránka `/embed` s kódem `<iframe>` pro blogy a e-shopy s filamentem. `frame-ancestors` je dnes `'self'`
   (`config/security.php`, CSP jen Report-Only): pro `/embed/*` je třeba povolit vše; domluv s řídící (klíč v konfiguraci
   per cesta). Měřit `embed` zdroj (utm_source=embed, referer domény do statistiky).
9. **Měření návštěvnosti**: v `/admin/stats` jedna metrika fáze: unikátní návštěvníci, kteří použili aspoň jeden nástroj
   za týden (z Track událostí s `tool`), tabulka per nástroj a jazyk, týdně; import exportu Search Console
   (`docs/data/search-console-<datum>.csv`, dotazy per stránka) do téže stránky, aby šlo vidět imprese → kliknutí →
   použití per nástroj. Roman export dodá.
10. **Návraty**: u nástrojů s dlouhým generováním (busta, mazlíček 2 až 4 min) volitelný e-mail „pošleme vám výsledek“
    bez účtu (jen odkaz na výsledek, souhlas v textu, žádný newsletter bez dalšího souhlasu); RSS nových nástrojů a článků
    (`/feed`), odkaz v patičce.
11. **Katalog Inspirace** (`/model`, 375 indexovaných z 8 592): připrav obě cesty a dej řídící odhad: (a) hodnota navíc na
    detailu, cena ve 3 materiálech a doba tisku z kalkulace souboru (jen modely s `file_available`), `Product` + `Offer`;
    (b) přepínač, který indexuje jen modely s kalkulací (dnes `CATALOG_INDEX_REQUIRES`), ostatní noindex. Roman rozhodne,
    neudělej žádnou bez jeho slova.
