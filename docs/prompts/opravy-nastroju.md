# Zadání pro session E: opravy a úpravy nástrojů podle toho, jak je Roman prochází

Tahle session nestaví nový nástroj. Dostává **krátké, číslované úkoly** od řídící session (Roman si prochází nástroje
na matplace.com, píše postřehy řídící session, ta z nich dělá přesné úkoly s odkazy na soubory a podmínkou hotovosti).
Úkoly chodí zprávou (SendMessage od `conceprt19092026-50`) a zároveň se zapisují do **`docs/prompts/opravy.md`**
(číslo, datum, co, kde, hotovo kdy, commit). Když přijde úkol, který koliduje s prací jiné session nebo je větší než
pár hodin, napiš to řídící session, nezačínej ho.

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-fixes-wt`**, větev **`feature/tool-fixes`** (z `main`, 10. 10. 2026). Připravené: `.env`
  (sqlite, `APP_URL=http://localhost:8018`, `PYTHON_BIN`), `node_modules` junction do `C:\matplace-app\node_modules`
  (nemaž, nepřepisuj), `vendor`, `public/build`. Místní server `php artisan serve --port=8018`.
- Řídící session slévá do `main` a nasazuje; ty **nemerguješ a nenasazuješ**. Každý úkol = jeden commit s číslem úkolu
  v první řádce („Opravy #12: …“), push na `origin feature/tool-fixes`, zpráva řídící session „#12 hotovo, commit …,
  testy …“. Před commitem **vždy** `git fetch origin main && git merge origin/main` (ať je větev slévatelná) – když
  merge hlásí konflikt, nic neřeš sám, napiš řídící session.
- Pravidla pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php` (všechny tři jazyky najednou); testy
  `php -d memory_limit=2G vendor/bin/phpunit --filter …` (jen dotčené sady, celá sada jednou denně); před pushem
  `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`, `npm run build`; **nikdy `taskkill /F /IM python.exe`**, nikdy
  `queue:retry all`; jedna session na worktree; `git add` jen jmenovitě; žádný `git stash`.
- **Souběh** (10. 10.): session 1 má `feature/tools-shapes` (obrázkové a textové nástroje, `param.ts`, `shape_kinds.py`,
  `creative_kinds.py`), session B `feature/papel-photo` (papel picado), session D `feature/color-picker` (okno barev
  `colors.ts`, `Palette.php`, validace barev), session C `feature/tools-sell` (prodej, AI studio), farma = řídící session.
  Úkol, který sahá do těchto souborů, dostaneš jen s výslovnou poznámkou „koordinováno“; jinak se ho neujímej.
- Model: Opus 5.5; geometrické úkoly (Python v `engines/python`) dostanou v zadání poznámku „geometrie“, pak Fable 5.1.

## 1. Jak vypadá úkol

```
#7 · 11. 10. · /tools/box · „Zaoblení rohů“ má mít krok 0,5 a maximum 20 mm
kde: app/Domain/Tools/ParametricGenerator.php FIELDS['box']['radius'], lang/*/param.php 'box.radius.hint'
hotovo = posuvník jde po 0,5, max 20, náhled i STL sedí; test ParametricToolsTest::test_box_fields
```

Co s ním: udělat přesně to, co je psáno; když něco v zadání nesedí s kódem, napsat to zpět s návrhem (ne mlčky
udělat něco jiného). Nerozšiřovat („když už jsem tam…“) – další postřehy napiš do zprávy, řídící session z nich udělá
další úkoly.

## 2. Co platí pro všechny opravy

- Texty: cs, en, es zároveň, v `lang/*/<skupina>.php`; SEO texty nástroje v `lang/*/tools_seo/<druh>.php`.
- Rozměry a rozsahy polí: `ParametricGenerator::FIELDS` (min, max, výchozí, krok) + totéž v `engines/python/*_kinds.py`
  (`LIMITS`), obě místa; test v `ParametricToolsTest`.
- Stránka nástroje: `resources/views/tools/page.blade.php`, `resources/js/calc/tool_page.ts` (`Stage`), `param.ts`;
  karta a ukázky `php artisan matplace:tool-examples <druh> --force` (a `--card`).
- Nic z farmy (`app/Domain/Farm`, `app/Jobs/PrepareFarmOrder.php`, `resources/js/calc/farm.ts`) a nic z okna barev
  (`colors.ts`) – to mají jiné session.
- Když oprava mění výstup nástroje (geometrii), napiš do zprávy, že se má dotčený nástroj znovu zkušebně vytisknout
  (štítek „ověřeno tiskem“ je v `config/tools.php` `verified`; nastavuje řídící session).

## 3. Log úkolů

`docs/prompts/opravy.md` vede řídící session; ty do něj dopisuješ jen sloupec „hotovo / commit“. Při prvním spuštění
soubor založ, pokud chybí, s hlavičkou tabulky: `| # | datum | nástroj | co | kde | hotovo | commit |`.
