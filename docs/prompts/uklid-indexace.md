# Zadání: vyčistit matplace.com a dostat důležité stránky do indexu

Stav k 12. 11. 2026 večer. Rozhodl Roman („vyčistíme matplace a zaindexujeme pořádně a poctivě“), píše koordinátor.
Větev z `origin/main`, worktree `C:\matplace-content-wt`, nic nenasazuj, nesahej na produkci ani na tiskárny.

## Podklady

Celá studie: `C:\Users\roman\Documents\MatPlace\conceprt19092026\studie-navstevnost-2026-11-12.md` (přečti celou,
hlavně část 5a). Stručně:

- Ruční zásah Google nehlásí. V indexu je 5 614 stránek, mimo index 6 432; Google čte web jako obchod se
  4 190 produkty (stránky katalogu modelů).
- **Ze 40 zkoušených stránek nástrojů, vstupních stránek a článků blogu není v indexu žádná** (12× „Objeveno –
  momentálně neindexováno“, 28× „Google adresu URL nezná“), přestože odpovídají 200, mají kanonickou adresu,
  nemají zákaz indexace, jsou v mapě stránek a úvodní strana na ně odkazuje.
- Mapa stránek: 603 odeslaných adres, 0 zaindexovaných. V účtu visí tři staré mapy z let 2013 a 2018.
- Googlebot prochází 320–460 stránek denně (začátkem listopadu 2 500), skoro jen `/model/…`; čtvrtina až třetina
  končí 301 (mimo jiné adresy s `?lang=`). Googlebot-Image stahuje tisíce starých náhledů `/assets/thumbs/…`,
  které se přesměrovávají 302 na `legacy.matplace.com`. Prochází i adresy starého e-shopu
  (`/pla-luminous-filament-…`, `/klicenka-…`, `/14763`).
- Česko za 28 dní: 64 zobrazení, jen na název „matplace“.
- Do návštěv „lidí“ se počítají roboti s jednou opakovanou hlavičkou prohlížeče (12. 11.: 11 242 požadavků
  „Firefox 159“, z toho 1 128 z jednoho bloku adres datového centra), SERanking, Semrush. 9. a 10. 11. se zapsalo
  942 a 962 událostí `order_started`, jinak 0–5 denně.

## Cíl

Google má pozornost věnovat stránkám, které přivádějí zákazníky (nástroje, dárky, vstupní stránky, blog), ne
převzatému katalogu a zbytkům starého webu. Poctivě: žádné triky, jen pořádek, správné signály a kvalitní stránky.

## Co udělat

**1. Katalog modelů**
- Zjisti, co dnes dělá přepínač `CATALOG_INDEX_REQUIRES` (`docs/AL.md §9`, paměť projektu „Catalogue SEO: index only
  enriched“) a jak je nastavený ve výchozím stavu. Navrhni a zaveď pravidlo: v indexu zůstane jen stránka modelu
  s vlastní hodnotou (vlastní popis, fotka výtisku z farmy, cena z řezu souboru…), ostatní dostanou
  `noindex, follow`, vypadnou z mapy stránek a nenesou označení produktu.
- Strukturovaná data: stránky cizích modelů se nemají tvářit jako naše produkty na prodej. Projdi, co se dnes
  vypisuje (Product/Offer), a nech je jen tam, kde to odpovídá skutečnosti (cena tisku u nás).
- Spočítej z místních dat nebo z pravidla, kolik stránek zůstane a kolik vypadne; skutečná čísla na produkci
  doplní koordinátor.

**2. Zbytky starého webu**
- Adresy starého e-shopu a katalogu, které dnes končí 404, 301 nebo 302 bez smyslu: vracej 410 (zrušeno), pokud
  nemají jasný nástupce; kde nástupce je, jedno přímé 301.
- `/assets/thumbs/…` a další `/assets/*` přesměrovávané na `legacy.matplace.com`: zjisti, které stránky je ještě
  používají. Stránky, které zůstanou v indexu, mají obrázek dostat z vlastní adresy bez přesměrování; ostatní
  náhledy nepřesměrovávat (410) nebo zakázat procházení v `robots.txt`. Nic, co dnes lidem funguje, se nesmí rozbít.
- `/login`, `/auth/*`, stránky účtu: `noindex` a žádné odkazy, které na ně vedou roboty zbytečně.

**3. Přesměrování a jazyky**
- Adresy s `?lang=`: vnitřní odkazy, kanonické adresy, hreflang a mapa stránek mají ukazovat rovnou na konečnou
  adresu (`/en/…`, `/es/…`); žádné řetězy 301.
- Najdi další vnitřní odkazy, které vedou přes přesměrování (z logu jich Googlebot dostává stovky denně).

**4. Mapa stránek a robots.txt**
- V mapě jen indexovatelné adresy s odpovědí 200 a kanonickou adresou na sebe; skutečné `lastmod`.
  Ověř, že v ní jsou všechny nástroje ve všech jazycích, `/gifts`, `/3d-tisk-praha`, `/tools`, blog.
- `robots.txt`: zvaž zákaz procházení zjevného odpadu (staré `/assets/thumbs`, vnitřní hledání, parametry řazení),
  ale nezakazuj nic, co má být v indexu nebo co potřebuje vykreslení stránky.

**5. Vnitřní odkazy**
- Stránky, které Google prochází nejčastěji (katalog modelů, úvodní strana, blog), mají vést na stránky nástrojů
  a vstupní stránky: patička s hlavními nástroji a službami, u modelu smysluplný odkaz „Nechat vytisknout“ /
  příbuzný nástroj. Žádné farmy odkazů; jen to, co pomůže i člověku.
- Úvodní stranu právě přestavuje jiná session (větev `feature/home-what-to-make`, text „Co chcete vyrobit?“);
  její soubory (`resources/views/calculator/home.blade.php`, `config/home.php`, `app/Domain/Intake/*`,
  `resources/js/site/intake.ts`) neupravuj. Titulek a popis úvodní strany sjednotí ona.

**6. Kvalita stránek, které mají být v indexu**
- U stránek nástrojů, `/gifts`, `/3d-tisk-praha`, `/tools` a článků zkontroluj: jedinečný titulek a popis, jeden H1,
  dost vlastního textu, správné hreflang a kanonická adresa, strukturovaná data služby/organizace (včetně míst
  farem, až budou adresy), rychlost načtení. Sepiš, co je slabé, a oprav, co jde bez nových textů od Romana.

**7. Měření**
- Roboty s opakovanou hlavičkou z datových center, SERanking, Semrush a podobné zařaď mezi roboty (pravidla
  v jednom místě, s testy); přehled návštěvnosti má ukazovat lidi bez nich.
- Dohledej v kódu, co zapisuje `order_started`, a proč mohlo vzniknout 942 a 962 zápisů za den; navrhni ochranu.

**8. Kontrola stavu indexu jedním příkazem**
- Nový příkaz (např. `php artisan searchconsole:inspect`) nad stávajícím `SearchConsoleClient`: pro seznam
  důležitých adres (z konfigurace + z mapy stránek) se zeptá na stav indexace
  (`POST https://searchconsole.googleapis.com/v1/urlInspection/index:inspect` s `inspectionUrl`, `siteUrl`;
  stačí stávající oprávnění jen pro čtení) a na stav map (`GET webmasters/v3/sites/<site>/sitemaps`), výsledek
  uloží do tabulky a vypíše souhrn (zaindexováno / objeveno / nezná). Denní limit Googlu je 2 000 dotazů:
  příkaz má mít strop. V testech jen `Http::fake`.
- Týdenní přehled pro Romana (už existuje podle `docs/O.md`) doplň o tento souhrn.

## Co připravit pro Romana (ne dělat sám)

- Seznam deseti adres k ručnímu „Požádat o indexování“ v Search Console, v pořadí důležitosti.
- Krátký postup: smazat tři staré mapy stránek v Search Console; co zkontrolovat za týden a za tři týdny.

## Testy a výstup

- PHP testy na pravidlo indexace katalogu, mapu stránek, 410 u starých adres, odkazy bez `?lang=`, rozpoznání
  robotů, nový příkaz. Po změně textů `ToolPageTest|LandingPagesTest|SeoTest`.
- Větev `feature/seo-cleanup-index`, push, neslučovat; kapitola v `docs/AP.md` (nebo vlastní soubor podle zvyklostí).
- Závěrečná zpráva koordinátorovi: co je hotové po bodech 1–8, čísla (kolik stránek katalogu zůstává v indexu podle
  pravidla), seznam změn, které nasazení nese (migrace, konfigurace serveru, proměnné v `.env`), co je potřeba
  ověřit na produkci, seznam pro Romana, co se liší od zadání a proč.
