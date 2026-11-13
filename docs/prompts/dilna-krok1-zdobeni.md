# Zadání: Jedna dílna, krok 1 – společné zdobení (text, reliéf z fotky, logo) na čtyřech výrobcích

Stav k 13. 11. 2026. Rozhodl Roman, píše koordinátor. Větev z `origin/main`, worktree `C:\matplace-shapes-wt`,
nic nenasazuj, nesahej na produkci ani na tiskárny.

## Souvislosti

- Koncepce: `C:\Users\roman\Documents\MatPlace\conceprt19092026\koncepce-jedna-dilna.md` (přečti celou).
  Roman rozhodl: dílna poběží na matplace.com (jednadilna.cz bude jen přesměrování), použije se vše, co už
  na webu je, nejdřív dárky pro laiky, telefon první, česky první, krok 1 má být do Vánoc a půjde do reklamy.
- Proč krok 1: přání „medaile s reliéfem z fotky“ dnes nejde splnit. Nástroj medaile bere jen umístění, číslo,
  motiv nebo text; reliéf z fotky umí jen samostatný nástroj `/tools/relief`. Každý nástroj má vlastní zdobení
  (text u cedulky jinak než u medaile, logo u klíčenky jinak než u přívěsku) a zákazníka to mate.

## Cíl

Jedno společné **zdobení**, které se dá položit na plochu kteréhokoli z těchto čtyř výrobků, se stejným ovládáním
a stejným chováním:

1. **medaile** (nástroj medaile pro vítěze, s řetízkem i na stužku),
2. **klíčenka**,
3. **vánoční ozdoba** (ozdoba na stromek se jménem; dnes vede na přívěsek – zvol správný nástroj nebo tvar),
4. **podtácek**.

Druhy zdobení:

- **text** (jeden až tři řádky, písmo, velikost, vystouplý nebo zapuštěný),
- **reliéf z fotky** (využij to, co už umí `/tools/relief`; obličej, pes, dům),
- **logo nebo kresba** (využij to, co už umí klíčenka/přívěsek z obrázku a převod SVG/loga).

U každého: na kterou plochu (přední, zadní), kde a jak velké, jak hluboké/vysoké, kterou barvou (druhý filament
tam, kde nástroj dvě barvy už umí; zarovnané barvy lícem dolů respektuj, `docs/P.md §11`).
Kombinace musí jít: např. medaile s reliéfem z fotky vpředu a jménem s rokem vzadu; klíčenka s logem a textem.

## Požadavky

- **Zdobení jako data.** Popis zdobení (druh, plocha, poloha, velikost, hloubka, barva, zdroj obrázku nebo text) je
  jeden zápis (JSON), kterému rozumí rozhraní i server; je to první kus společného „projektu“ z koncepce. Navrhni
  ho tak, aby šel později rozšířit o další druhy (QR, vzor) a další tvary, a popiš ho v dokumentaci.
- **Jedna součást rozhraní** (panel „Zdobení“) použitá ve všech čtyřech nástrojích; stejné popisky, stejné pořadí,
  ovladatelné na telefonu (390 px) prstem. Náhled se po změně překreslí bez nové stránky.
- **Jedno místo na serveru**, které zdobení vyrobí a spojí s tvarem (enginy v `engines/python`); žádné čtyři kopie.
- **Nic se nerozbije:** adresy nástrojů zůstávají, uložené návrhy a rozpracované zakázky se dál otevřou, dnešní
  volby nástrojů fungují. Kde dnešní vlastní text/logo nástroje odpovídá novému zdobení, nahraď ho; kde ne, nech
  staré a sepiš rozdíl.
- **Tisknutelnost:** nejmenší čitelné písmo a nejmenší hloubka reliéfu pro trysku 0,4 mm a vrstvu 0,2 mm; reliéf
  z fotky na medaili o průměru 40–120 mm musí dát rozumný výsledek (nastav výchozí hloubku, vyhlazení, kontrast)
  a nesmí zbytečně volat podpěry. Ověř místním řezem (recept na Orca CLI je v paměti projektu a v `docs/AL.md`).
- **Rychlost:** náhled do pár vteřin; dlouhé kroky s průběhem.
- **Cena a zakázka** fungují jako dnes (kalkulace z výsledného modelu).
- **Zkušební obrázky:** žádné fotky skutečných lidí bez souhlasu. Použij vlastní vygenerované nebo volné obrázky
  (obličej z volné databáze s licencí, pes, logo matplace z `public/img/logo.png`). Žádná placená volání AI.

## Co s rozhodováním úvodní strany

Soubor `app/Domain/Intake/IntakeRouter.php` právě mění jiná větev (`feature/home-what-to-make`, je v release
větvi `release/2026-11-13`, která se teď slučuje a nasazuje). Neupravuj ho. Do závěrečné zprávy napiš přesně, co se
má v katalogu nástrojů pro umělou inteligenci změnit (`takes` u medaile, klíčenky, ozdoby, podtácku), aby přání
„medaile s reliéfem z fotky“ vedlo na medaili se zdobením; koordinátor to zapracuje po sloučení.

## Testy a výstup

- PHP testy: zdobení jako data (uložení, načtení, ověření mezí), každý ze čtyř výrobků s každým druhem zdobení,
  kombinace vpředu a vzadu, staré uložené návrhy; testy enginu podle zvyklostí repozitáře.
- Místní kontrolní řezy: aspoň medaile 60 mm s reliéfem z fotky a textem vzadu, klíčenka s logem a textem;
  čas, gramy, zda vznikly podpěry.
- Snímky obrazovky panelu a náhledu na 390 px a 1280 px pro každý výrobek do `docs/img/`.
- Větev `feature/dilna-zdobeni`, push, neslučovat; kapitola v `docs/P.md` (nástroje tvarů) nebo vlastní soubor.
- Závěrečná zpráva koordinátorovi: co je hotové pro každý výrobek a druh zdobení, formát zápisu zdobení, co se
  nahradilo a co zůstalo po starém, výsledky testů a řezů, snímky, co nasazení nese (migrace, knihovny enginu),
  změny pro katalog úvodní strany, co je neověřené (hlavně skutečný tisk a skutečné fotky), otevřené otázky.
