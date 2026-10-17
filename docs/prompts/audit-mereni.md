# Zadání pro session 4: měření přínosu (audit bod 12) a nepočítat naše průchody (TODO #10)

Podklad: `docs/AUDIT.md` bod 12, `docs/O.md` (provoz: lidé, ne roboti), administrace `/admin/stats/funnel`
(`StatsController@funnel`, třída trychtýře), tabulka `events` (`type`, `subject_type`, `subject_id`, `source`,
`utm`, `meta`, `session_id`, `user_id`, `locale`), klient `resources/js/site/measure.ts` (se souhlasem; GA a Meta
pixel jen po souhlasu), události z nástrojů (`param.ts`, `tool_page.ts`, `relief.ts`, `edit.ts`, `art.ts`).

## 0. Kde a pravidla

- Tvůj worktree, větev **`feature/audit-events`** z aktuálního `main`, dokument **`docs/AJ.md`**.
- Soubory: `measure.ts`, zapisovač událostí (najdi: kde vzniká řádek v `events`, API i server), třída trychtýře a
  pohled `admin/stats/funnel`, `config/measure*.php` (je-li), skripty session (`scripts/review_round2.mjs`,
  `scripts/check_preview_colors.mjs`, `scratchpad` skripty řídící: `check_boxes.mjs`, `check_home.mjs`) jen pro
  značku. Nesahat: nástroje samotné (jen volání měření), kalkulace a zakázka (jen volání měření; změny tam
  domluv s řídící session), platby.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, `tsc`, `build`, testy; nemergovat, nenasazovat;
  hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Inventura (nejdřív, do AJ.md)

- Seznam všech typů událostí, které dnes vznikají (klient i server), s místem vzniku a obsahem `meta`. Co z toho
  trychtýř v administraci používá a jak počítá kroky. Kde se odvozuje „potvrzená platba“ (musí být ze stavu platby
  na serveru, ne z návštěvy děkovací stránky; karta má `card.status`).

## 2. Doplnit jen chybějící kroky

Audit chce sledovat: výběr vstupní cesty (soubor / fotka / text / nástroj / stažení), úspěšné nahrání nebo
vytvoření modelu, zobrazení ceny (odhad a přesná), zahájení objednávky („Vytisknout u nás“), přechod k platbě,
potvrzená platba (server), úspěšné stažení (STL i projekt). Doplnit jen to, co chybí, se stejným pojmenováním jako
dnešní události. Žádná událost nenese fotky, hesla, texty zadání ani adresy z QR; `meta` jen čísla a kódy.

## 3. Dělení a vyhodnocení

- Trychtýř v administraci rozdělit: mobil / počítač (podle šířky nebo UA uložené u sezení), nový / vracející se
  (sezení), vstupní cesta; u každého čísla velikost vzorku. Metriky z auditu: podíl návštěvníků s cenou, podíl
  zahájených objednávek dokončených platbou, místa odchodů, četnost chyb, úspěšnost stažení.
- **TODO #10:** naše skripty a headless prohlížeče posílají značku (cookie `mp_test=1` nebo hlavička
  `X-Matplace-Test`), kterou zapisovač událostí vyřadí (`meta.test` nebo úplně nezapíše) a „lidé“ v O.md ji nepočítají;
  značku nastav ve skriptech v repu a popiš v AJ.md, jak ji dát ručně (rozšíření prohlížeče, `document.cookie`).

## 4. Testy a ověření

- Testy událostí: každý nový typ vznikne v testovacím průchodu (nahrání → cena → objednávka → platba v testovacím
  režimu), potvrzená platba jen po stavu banky, událost se značkou test se nepočítá; `meta` bez zakázaného obsahu
  (test projde seznam klíčů).
- **Hotovo =** push `feature/audit-events`, AJ.md (popis událostí, co se měří, jak se vyhodnotí a od kdy; velikost
  vzorku dnes: 6 až 12 lidí denně), testy, `pint`, `tsc`, `build`, zpráva řídící session.
