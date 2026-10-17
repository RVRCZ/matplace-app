# Zadání pro session 3: web očima návštěvníka, kolo 2 (TODO #4): stránka nástroje a katalog na telefonu, fotkové nástroje

Od Romana (17. 10. 2026): „pusťme se do #4“. Podklad: `docs/Z.md` (nálezy N1 až N17, snímky `docs/img/review/`,
např. `qr-390-cs-1.webp`, `tools-390-cs-full.webp`, `relief-390-cs-done.webp`). Tahle session dělá body 1, 6, 8 z
§4 a k nim N9, N10, N14 (část nástroje) a N15. Zbytek (kalkulace, cookie lišta, menu, blog, důvěra, přesměrování)
dělá řídící session.

## 0. Kde pracuješ a pravidla

- Tvůj worktree, nová větev **`feature/web-round2`** z aktuálního `main`
  (`git fetch origin && git checkout -b feature/web-round2 origin/main`), dokument **`docs/AF.md`**.
- Tvoje soubory: `resources/views/tools/page.blade.php`, `resources/views/components/tool-section.blade.php`,
  `resources/js/calc/tool_page.ts`, katalog `resources/views/tools/index.blade.php` (+ jeho skript a kontroler),
  `resources/views/tools/figure.blade.php`, `pet.blade.php`, `relief.blade.php` (a další fotkové), `resources/js/calc/figure.ts`,
  `relief.ts`, `resources/css/app.css` (bloky stránky nástroje a katalogu), `config/tools.php` jen kvůli ukázkovým
  obrázkům, `lang/*/toolpage.php`, `tools.php`, `figure`, `pet`, `relief`. V `param.ts` jen N15 (sdílený soubor, nejmenší
  možná změna). Nesahat: `calculator.ts` a kalkulace, hlavní stránka, `layouts/app.blade.php`, `partials/consent`,
  patička, farma, videa (session 1), `PlateLayout` (session 2), enginy.
- Pravidla: texty jen v `lang/{cs,en,es}/*.php` nebo `lang/src/<soubor>.json` + `python scripts/lang_add.py`; `git add`
  jmenovitě; žádný stash; `PYTHON_BIN` v `.env`; před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`,
  `npm run build`; testy `php -d memory_limit=2G vendor/bin/phpunit --filter …`. Jednotný styl má přednost před
  každou dílčí změnou (`docs/N.md`: oranžová jen na jedné hlavní akci na obrazovce, vedlejší akce inkoustové nebo
  šedé obrysy). Nemerguješ a nenasazuješ: push na `origin feature/web-round2` a zpráva řídící session
  (conceprt19092026-8c).
- Co se od kola 1 změnilo: stránka nástroje už nemá orientační cenu, tlačítko je „Pokračovat“ (16. 10.), povinná
  zaškrtávátka mají trvalý červený rámeček (17. 10.), tečkové logo při generování busty a mazlíčka (16. 10.).

## 1. Stránka nástroje na telefonu (N1, N14) – všech 55 nástrojů, jedna šablona

- Pod `lg` pořadí: **název + věta** (1 až 2 řádky, vidět hned) → **prohlížeč 45 až 50 vh** s jediným tlačítkem
  „Pohled“, které rozbalí dnešní lištu (3D, Shora, Zepředu, Ze strany, Zespodu, Rozložit, Přizpůsobit, Rentgen,
  Podložka) → **karta akce** (Stáhnout, Pokračovat, řádek rozměrů) hned pod prohlížečem → kroky formuláře.
  Na `lg` a výš beze změny (kolo 1: v pořádku).
- Odstavec pod lištou prohlížeče (např. u QR) na telefonu skrýt; mezery mezi kroky v panelu stejné jako mezi
  kartami; jedna oranžová akce na obrazovku.
- Fotkové nástroje (busta, mazlíček, reliéf, litofán, papel): místo prázdného prohlížeče **ukázkový výtisk**
  (fotka karty z `config/tools.php`, už existuje) a věta „Nahrajte fotku, model uvidíte asi za minutu“; po
  vytvoření modelu se obrázek vymění za prohlížeč.

## 2. Katalog `/tools` na telefonu (N6)

- Kompaktní karta (fotka 96 px vlevo, název + jeden řádek vpravo), nebo dva sloupce; rozhodni podle toho, co je
  na 390 px přehlednější pro 55 nástrojů, a napiš proč. Sekce podle kategorie **sbalené s počtem** („Jména a dárky ·
  9“), první otevřená; chipy kategorií v jednom vodorovně rolovacím řádku; hledání zůstává nahoře. První nástroj
  musí být vidět na první obrazovce. Na 1366 (3 sloupce) beze změny.

## 3. Fotkové nástroje (N8, N9, N10)

- Po dokončení modelu na `< lg` **odskrolovat na prohlížeč a kartu akce** (`scrollIntoView`), nebo kartu akce
  přilepit dolů; busta „Hotovo, otevírám kalkulaci…“ nesmí slibovat, co se nestane.
- Časy pravdivě: reliéf „asi minutu“, busta a mazlíček „1 až 3 minuty“ (dnes „pár vteřin“).
- **Před nahráním** říct nad tlačítkem: souhlas (zůstává zaškrtávátko s rámečkem), denní limit (1 bez účtu, 3 s účtem)
  a čas; po vyčerpání limitu nabídnout registraci přímo v hlášce (odkaz).
- **Velikost fotky hlídat v prohlížeči**: limit 15 MB napsat u pole; fotku nad 2 000 px zmenšit v prohlížeči
  (canvas) před odesláním (telefony dávají 8 až 12 MB), teprve pak poslat; vlastní text „Fotka je větší než 15 MB,
  zmenšete ji“ cs/en/es. Surový text validace Laravelu („The photo field must not be greater than…“) se nesmí
  ukázat: test, že `422` z nahrání fotky nese přeložený text.

## 4. Prázdný povinný text (N15)

- Cedulka, jmenovka a další textové nástroje: když je povinný text prázdný, zešedne i náhled a karta akce (nebo se
  ukáže prázdná destička), ne jen tlačítko; dnes zůstává „Jana“ a vypadá to, že se nic nestalo.

## 5. Testy a ověření

- `ToolPageTest`: na stránce nástroje je `h1` a věta před prohlížečem v HTML, tlačítko „Pohled“, karta akce;
  fotkový nástroj nese ukázkový obrázek; katalog: sekce s počty; nahrání fotky nad limit vrací přeložený text.
- Prohlížeč (headless Chrome, 390 × 844 a 1366): `qr`, `sign`, `box`, `figure`, `relief`, `/tools`, cs i en, snímky
  první obrazovky do `docs/img/web-round2/` pojmenované jako v kole 1 (`qr-390-cs-1.webp`), vedle kola 1 do AF.md
  tabulka „co je na první obrazovce“ (název, věta, tlačítko, cena) před a po. Skript B z kola 1 není v repu
  (`C:\tmp\review.mjs`, popis v Z.md §6); napiš si vlastní do `scripts/` a nech ho tam.
- **Hotovo =** push `feature/web-round2`, AF.md (rozhodnutí: karta vs. dva sloupce, jak se rozbaluje „Pohled“), snímky,
  testy, `pint`, `tsc`, `build`, zpráva řídící session.

## 6. Co není v zadání

Kalkulace a host (N2, N11), hlavní stránka (N3), cookie lišta (N5), menu (N7), blog (N12), důvěra (N13), skripty a
písmo na 404 (N16), přesměrování (N17): dělá řídící session nebo počká.
