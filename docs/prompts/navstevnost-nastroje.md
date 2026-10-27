# Návštěvnost: nástroje jako vstupní brány, vánoční nástroje, blog – nová session (N1)

Roman 27. 10. 2026: hlavní cíl je teď **návštěvnost a opakované návraty**, ne prodej. Podklad: `docs/prompts/priority-navstevnost-2026-10-27.md`
(body 1, 3 a část 4 „vánoční“), audit webu `docs/prompts/audit-2026-10-26.md` (C11, C12 jen jako kontext). Piš česky, krátce,
bez pomlček. Jsi obsahová session: texty, struktura stránek nástrojů, blog.

## Pracoviště

- Worktree **`C:\matplace-content-wt`** (připravený: `.env`, `vendor`, `node_modules` jako junction na `C:\matplace-app\node_modules`,
  nikdy ho nemaž přes `rm -rf`), větev `feature/tools-seo-pages` z main. Nemerguješ ani nenasazuješ, to dělá řídící
  (`conceprt19092026-91`); po každé ucelené dávce push a jedna zpráva řídící přes `SendMessage`.
- Deník `docs/AP.md` (nový). Texty jen v `lang/{cs,en,es}/tools_seo/<nástroj>.php` (title, description, h1, intro, faq…),
  `lang/{cs,en,es}/landing.php` + `config/landing.php` (bloky pod nástrojem: příklady, otázky, pro koho, kam dál),
  `config/tools.php` jen klíč `article` (vazba na článek). Šablony `tools/_content.blade.php`, `tools/_landing.blade.php` měň
  jen, když to texty vyžadují. `PYTHON_BIN` v `.env` míří na Store Python. Před pushem `vendor/bin/pint --dirty`, testy
  `ToolPageTest|AuditRestTest|ToolsCatalogTest|SitemapTest` (plná sada na pozadí, nikdy dvě naráz, během běhu neupravovat).
- Nedotýkej se kalkulace, hlavní stránky (`calculator/*`), farmy, `/tools` přehledu (session 3), YouTube (session 2).

## Co už existuje (nevymýšlej znovu)

- 58 veřejných nástrojů, každý má `tools_seo` texty ve 3 jazycích a stránku s FAQPage JSON-LD; 6 nástrojů má bloky vstupní
  stránky (`config/landing.php`, AH.md §11: příklady s cenou, Než objednáte, Pro koho, Kam dál). `ToolSeo::tools()` a
  `ToolSeo::texts()` je čtou. Sitemapy nástrojů staví `php artisan matplace:sitemap`.
- Blog: 22 článků, `php artisan blog:sync` (viz `app/Console/Commands/BlogSync.php` a docs/AK.md §5, kde je zdroj článků
  a formát), 15 jich vyšlo 18. 10. najednou; fotky výtisků zatím nejsou (`docs/FOTKY-BLOG.md`, Roman je dluží; nová session
  N3 dodá fotky z fotoboxu bez pozadí). Article JSON-LD s autorem a datem je.
- Data, která se nedají okopírovat: `docs/data/odhad-vs-rez-2026-10-25-prod-overhang.csv` (400 kalkulací: odhad proti
  skutečnému řezu, trojúhelníky, převisy), docs/M.md §6 (jak se trefuje odhad), čísla farmy `App\Domain\Farm\PublicStats`.
- Search Console export zatím není; Roman ho dodá do `docs/data/search-console-<datum>.csv` (dotazy per stránka).

## Úkoly (v tomhle pořadí)

1. **Výběr 10 nástrojů s největším hledaným potenciálem** (CZ + EN; ES jen přeložit). Kandidáti z auditu: svg_to_stl, repair,
   check, hollow, split, gcode, map, relief (litofanie), qr, organizer, figure, cookie (vykrajovátka). Pro každý napiš
   do AP.md: hlavní dotaz cs a en („svg do stl online“, „svg to stl converter free“), 3 vedlejší dotazy, konkurence
   (zdarma bez registrace?), co stránka dnes má a co chybí. Bez Search Console odhaduj a označ to jako odhad.
2. **Stránky těch 10 nástrojů**: title a H1 na dotaz (ne název nástroje), description, 300 až 600 slov vlastního textu pod
   nástrojem (co umí, limity, jeden příklad s reálnými čísly z kalkulace nebo z nástroje, kde lze; „fotka výtisku“ jen jako
   místo pro fotku z N3, nic generovaného), FAQ 4 až 6 otázek (jen pravdivé odpovědi), propojení nástroj ↔ článek ↔ 2 až 3
   příbuzné nástroje. cs, en, es. Nesmí vzniknout šablonový text (každý nástroj jiná osnova podle toho, na co se lidé ptají).
   Po každých 3 až 4 nástrojích push a zpráva řídící (nasazuje se po dávkách).
3. **Vánoční nástroje do poloviny listopadu**: gingerbread (perníčky), ornament, cookie (vykrajovátka), nameplate / sign
   (jmenovky), topper, name_letter: texty na dotazy „vykrajovátko na míru“, „vánoční ozdoba se jménem“, „jmenovka na dárek
   3D tisk“ atd. (cs, en, es), bloky vstupní stránky s cenou z kalkulace, 2 články k Vánocům (viz 4).
4. **Blog**: od teď 1 až 2 články týdně, ne naráz. Připrav 6 článků: 4 na reálné dotazy (odhad bez Search Console, označ),
   2 z dat farmy („Jak přesně umíme odhadnout cenu bez řezu: 400 kalkulací“ z CSV, „Co lidé tisknou nejčastěji“ z
   PublicStats a kalkulací). Každý: místo pro 1 fotku výtisku (N3), vložené zveřejněné video z farmy (`FarmVideo` published,
   nocookie až po kliknutí jako na stránkách nástrojů), vložená sdílená kalkulace `/c/<kód>` (existující, ověř, že je veřejná).
   Zjisti, zda `blog:sync` umí datum vydání dopředu; když ne, navrhni minimální úpravu a napiš ji (rozložení publikace).
5. **Interní prolinkování**: z každého článku na nástroj a zpět (`config/tools.php` `article`), z nástroje na příbuzné
   (`config/landing.php` „kam dál“ pro nových 10).

## Ověření a výstup

Testy nástrojů a klíčů, build, snímky 390/1280 px pro 3 upravené stránky nástrojů (`node scripts/review_round2.mjs`
nebo headless Chrome), délky title do 60 a description do 155 znaků ve 3 jazycích, JSON-LD validní, sitemapa po
`matplace:sitemap`. Zpráva řídící po každé dávce: změněné soubory, co se změnilo, jak ověřeno, co zůstalo.
