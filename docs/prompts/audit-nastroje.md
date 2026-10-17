# Zadání pro session 3: audit, etapa 1 na stránkách nástrojů (popisky akcí, zachování zadání, stavy, průchod)

Podklad: `docs/AUDIT.md` (zákaznický audit ověřený proti webu, Romanovy odpovědi 17. 10.: popisky podle auditu,
expedice v den dotištění v pracovní dny, štítek „Náš výběr“). Navazuje na tvoje kolo 2 (`docs/AF.md`, sloučeno do
main 17. 10.). Řídící session dělá hlavní stránku, navigaci, kalkulaci, zakázku a platbu; ty stránky nástrojů a
katalog a průchod webem.

## 0. Kde a pravidla

- Tvůj worktree, větev **`feature/audit-tools`** z aktuálního `main`, dokument **`docs/AH.md`**.
- Tvoje soubory jako v kole 2 (`tools/page.blade.php`, `tool_page.ts`, katalog, fotkové nástroje, `param.ts` jen
  nejmenší změny, `lang/src/<soubor>.json` + `python scripts/lang_add.py`, `scripts/review_round2.mjs`). Nesahat:
  kalkulace (`calculator/*`, `calculator.ts`), hlavní stránka, `layouts/app.blade.php`, farma, platby.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, `tsc`, `build`, testy s filtrem; nemergovat,
  nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Popisky akcí říkají, co se stane (audit bod 5, Roman: „audit“)

- Tlačítko stránky nástroje „Pokračovat“ (`param.go`) → **„Spočítat cenu a možnosti tisku“** / „Get the price and
  print options“ / „Calcular precio y opciones de impresión“. „Stáhnout“ → **„Stáhnout model“**. Fotkové nástroje:
  „Vytvořit model“ zůstává; kde tlačítko vede rovnou k ceně, pojmenovat to tak.
- Věta pod tlačítky (`param.go.hint`) musí sedět: kalkulace ukáže přesnou dobu tisku a cenu, nic neslibuje hned.
- Rozlišit tři výsledky: získám soubor / otevřu další nastavení / začínám placený krok. Na stránce nástroje je jen
  první a druhý; třetí je až v kalkulaci („Vytisknout u nás“, řídící).

## 2. Zachování zadání a stavy (bod 5 a 11)

- Zpět z kalkulace na nástroj a obnovení stránky nástroje nesmí ztratit zadání (text, rozměry, volby, fotky tam, kde
  prohlížeč dovolí). Ověř, jak dnes nástroje drží parametry (adresa, `history`, úložiště), a doplň, co chybí;
  u fotkových nástrojů je model v adrese (`?generation=`).
- Neúspěšné generování zachová zadání a nabídne opakování; hláška lidsky, bez technických detailů.
- Každý nástroj má zřetelný prázdný stav, načítání (tečkové logo nebo stavový řádek), úspěch a chybu; zkontroluj
  všech 55 stránek skriptem (viz 4), ne ručně.
- Slovník (bod 11): na stránkách nástrojů „3D model“ / „soubor“ pro digitální výsledek, „výtisk“ / „výrobek“ pro
  fyzický; „zdarma“ vždy s limitem na místě rozhodnutí; test, že žádný použitý klíč nechybí v cs, en, es.

## 3. Katalog (bod 8, část bez dat)

- Nahoře „Náš výběr“ (Roman): dárek se jménem, organizér, krabička, QR cedulka, z fotky; karty sjednotit (název,
  co zadám, ukázka, jedna akce); synonyma v hledání (jmenovka = cedulka = štítek; krabička = box; apod.); prázdný
  výsledek nabídne „zrušit filtry“ a příbuzné nástroje; návrat z detailu drží filtr a hledání.
- Štítek „Nejčastěji tištěné“ se na hlavní stránce přejmenovává (řídící); v katalogu nic takového bez dat.

## 4. Průchod webem (audit bod 2 a 13, úkol A z AUDIT.md)

- `scripts/review_round2.mjs` rozšířit na šířky **360, 390, 768, 1280** a cesty z auditu bodu 13 (soubor → cena →
  objednávka; nástroj → kalkulace → objednávka; model → stažení; QR web a Wi-Fi; přihlášení uprostřed objednávky;
  neplatný soubor, překročený limit, neúspěšný výpočet). Měřit: vodorovný posun (`scrollWidth > innerWidth`),
  dotykové plochy hlavních akcí ≥ 44 × 44 px, `h1` a hlavní akce na první obrazovce, překryv cookie lištou, text
  200 % (zoom), fokus z klávesnice.
- Výstup do AH.md: tabulka „cesta → kroky → co se stane“ se snímky v `docs/img/audit/`, nálezy tříděné podle auditu
  (chyba / překážka / neověřený návrh). Co je mimo tvoje soubory (kalkulace, zakázka, hlavní stránka), jen zapiš
  pro řídící session.

## 5. Hotovo

Push `feature/audit-tools`, AH.md (změny, tabulka cest, snímky, známá omezení), testy, `pint`, `tsc`, `build`, zpráva
řídící session.
