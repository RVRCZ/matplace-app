# Zadání: stav farmy – otevřeno / odstávka / zavřeno, a texty podle něj

Stav k 13. 11. 2026. Rozhodl Roman, píše koordinátor. Větev z `origin/main`, worktree `C:\matplace-gcode-wt`,
nic nenasazuj, nesahej na produkci ani na tiskárny.

## Proč

Roman má farmu často vypnutou kvůli úpravám a opravám. Web přitom dál říká „tiskneme“, „obvykle do druhého dne“,
„doručení do Vánoc při objednání do 15. 12.“ a ukazuje ceny tisku. Návštěvníka to mate. Zároveň nechceme, aby při
každé krátké odstávce zmizely ceny a možnost objednat: stránky by se měnily pod rukama Googlu i lidem z reklamy.

## Dnešní stav (ověř v kódu)

- `/admin/farm/settings` („Ceny a pravidla“), blok „Přepínače webu“:
  - `farm_open` „Farma přijímá zakázky“ – vypnuto: tlačítko VYTISKNOUT zmizí, nové zakázky nejdou založit;
  - `farm_public` „Farma je veřejná“ – vypnuto: tlačítko vidí jen administrátor;
  - `require_approval` – každá zaplacená zakázka čeká na schválení administrátorem.
- `app/Domain/Farm/FarmGate.php` rozhoduje, kdo smí objednat; `AppServiceProvider` přenáší nastavení do konfigurace.

## Co udělat

**1. Tři stavy místo dvou přepínačů (pro zákazníka)**

| Stav | Kdy | Co vidí zákazník |
|---|---|---|
| Otevřeno | běžný provoz | vše jako dnes |
| Odstávka | krátká pauza kvůli úpravám a opravám | ceny zůstávají, objednat a zaplatit jde; místo slibů rychlosti poctivá věta „Farma má teď odstávku. Tisk začne nejdřív {datum}.“ (bez data: „jakmile farmu znovu spustíme“); zakázka čeká ve frontě a nevytiskne se, dokud farma není otevřená |
| Zavřeno | delší přestávka | texty o tisku u nás a ceny tisku zmizí; zůstávají nástroje, náhled, stažení modelu a ostatní obsah; nové zakázky nejdou založit |

- Administrace: jedna jasná volba stavu (tři možnosti) v „Ceny a pravidla“ a pro stav Odstávka pole
  „tisk začne nejdřív“ (datum, nepovinné) a krátká poznámka pro zákazníky (nepovinná, cs/en/es nebo jen cs s rozumným
  výchozím textem v ostatních jazycích). Stav musí být vidět i na přehledu „Tiskárny a fronta“ (nahoře výrazně),
  aby Roman nezapomněl farmu otevřít. Dosavadní přepínače `farm_open`, `farm_public`, `require_approval` nezruš
  bez náhrady: navrhni, jak se na nový stav mapují (např. Zavřeno = `farm_open` vypnuto; Odstávka = otevřeno +
  zakázky se nespouštějí), a zachovej dnešní chování pro administrátora (ten smí objednat vždy kvůli zkouškám).
- Odstávka nesmí spustit žádný tisk zákazníka: zaplacená zakázka zůstane ve frontě; po přepnutí na Otevřeno
  se fronta rozjede obvyklou cestou. Administrátorské zkušební zakázky se tisknou i za odstávky.
- E-maily a stránka zakázky při odstávce říkají totéž (zakázka přijata, tisk začne nejdřív…).

**2. Texty řízené stavem**

- Najdi všechna místa, kde web slibuje rychlost nebo termín tisku a doručení nebo tvrdí „tiskneme“:
  kalkulačka, stránka zakázky („Usually within a day from payment to handover“, „Printing with us in numbers“),
  stránky nástrojů, vstupní stránky (`/gifts`, `/3d-tisk-praha`), `/farm`, patička, e-maily, strukturovaná data.
  Slib doručení do Vánoc (`christmas_deadline`) také.
- Při Otevřeno beze změny. Při Odstávce místo slibu rychlosti věta o odstávce s datem; slib „do Vánoc“ jen pokud
  datum konce odstávky ten slib ještě dovoluje, jinak nic. Při Zavřeno texty o tisku u nás a ceny tisku nezobrazovat.
- Titulky, popisy a nadpisy stránek pro vyhledávače se se stavem **nemění** (žádné přepisování SEO textů podle
  odstávky); mění se jen sliby a tlačítka v těle stránky.
- Úvodní strana: soubory `resources/views/calculator/home.blade.php`, `config/home.php`, `app/Domain/Intake/*`
  a `resources/js/site/intake.ts` mění větev `feature/home-what-to-make`, která je v release větvi
  `release/2026-11-13` (právě se slučuje a nasazuje). Na konci práce udělej `git fetch origin`; pokud `origin/main`
  už obsahuje `app/Domain/Intake/IntakeHints.php`, slij `origin/main` do své větve a teprve pak uprav sliby na
  úvodní straně („Doručení do Vánoc při objednání do 15. 12.“, „Během minuty navrhneme…“). Pokud ne, úvodní stranu
  neupravuj a napiš do zprávy přesně, co se v ní má změnit.

**3. Reklama**

Kampaň povede na stránky výrobků. Přidej do administrace u stavu upozornění: „Při odstávce nebo zavření zvažte
pozastavení reklamy.“ Nic dalšího.

## Souběh s jinou prací

`feature/admin-farm-overview` (v release větvi) přestavěla přehled „Tiskárny a fronta“ a přidala farmy jako místa
(tabulka `farms`, každá má `enabled`). Stav z tohoto zadání je stav celé služby, ne jedné farmy; po slití
`origin/main` (viz výše) ho ukaž na novém přehledu. Soubory zemědělské fronty a agenta měň jen v nutné míře.

## Testy a výstup

- PHP testy: tři stavy (kdo smí objednat, co se zobrazí na kalkulačce, zakázce, stránce nástroje, vstupní stránce),
  zakázka zaplacená za odstávky se nespustí a po otevření se spustí, administrátor tiskne i za odstávky, e-mail,
  mapování starých přepínačů. Po změně textů `ToolPageTest|LandingPagesTest|SeoTest`.
- Snímky kalkulačky a stránky nástroje ve stavu Odstávka a Zavřeno (390 px a 1280 px) a nastavení v administraci.
- Větev `feature/farm-state`, push, neslučovat; kapitola v `docs/AL.md`.
- Závěrečná zpráva koordinátorovi: kde se stav nastavuje, jak se mapují staré přepínače, seznam míst s texty,
  která se řídí stavem, co nasazení nese (migrace), co se liší od zadání a proč, otevřené otázky.
