# Zadání pro session B: web očima návštěvníka (proč odcházejí) – kolo 1 = nález a návrh

Od Romana (13. 10. 2026): „musí to být zajímavé pro návštěvníky, funkce a kvalitu nástrojů doladíme postupně, chci
jednotný styl; zatím není návštěvnost, ale je škoda, že odcházejí jen proto, že se jim web nelíbí.“

Cíl kola 1: projít web tak, jak ho vidí člověk, který přišel poprvé (z vyhledávače nebo z odkazu), a napsat
**co ho zastaví, zmate nebo odradí**, seřazené podle dopadu, s důkazem (snímek) a s návrhem opravy včetně odhadu
práce. **Kolo 1 nic nemění v kódu** – jen nález a návrh. Roman vybere, co se udělá; kolo 2 je pak implementace.

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-papel-wt`** (tvoje), větev `feature/map-maker` je slitá; pro kolo 1 nová větev
  **`feature/web-review`** z aktuálního `main` (jen dokument a snímky). Dokument: **`docs/Z.md`**, snímky do
  `docs/img/review/` (webp, ≤ 300 kB každý, pojmenované `<stranka>-<sirka>-<jazyk>-<n>.webp`).
- Řídící session (`conceprt19092026-5c`) slévá a nasazuje; ty nemerguješ. Commit po celcích, push
  `origin feature/web-review`, zpráva řídící session, až bude Z.md k přečtení.
- Pravidla pro všechny session platí (texty jen v lang, `git add` jmenovitě, žádný stash, jedna session na worktree,
  nikdy `taskkill /F /IM python.exe`).
- Díváš se na **produkci** `https://matplace.com` (to vidí lidé), ne na místní server; místní použij jen tam, kde
  potřebuješ správce (skryté nástroje neřeš – návštěvník je nevidí).

## 1. Jak projít (pokaždé jako nový návštěvník: anonymní okno, bez přihlášení)

Šířky: **390 px** (telefon, většina návštěv z vyhledávače) a **1366 px** (notebook). Jazyky: cs a en (es jen
namátkou). Headless Chrome na snímky; u každé stránky si ulož **první obrazovku bez skrolování** – to je to, podle
čeho se člověk rozhoduje, jestli zůstane.

Cesty:
1. **Vstup z vyhledávače na nástroj** (nejčastější vstup): `/tools/qr`, `/tools/phone-stand`, `/tools/sign`,
   `/tools/figure`, `/tools/box` – rozumím do 5 s, co tu dostanu a co mám udělat? Je vidět výsledek (obrázek) dřív než
   formulář? Kolik polí je na první obrazovce a kolik z nich je nutných?
2. **Hlavní stránka** `/`: co je první věta, je jasné, co matplace je (tisk na zakázku + nástroje na vlastní návrh)?
   Karty nástrojů (od dnešního dne fotky – posuď až po mé zprávě, že je nasazená první dávka), kroky „jak to jde“,
   důvěra (kdo jsme, fotky skutečných výtisků, cena, dodání).
3. **Stránka nástrojů** `/tools`: orientace (3 vstupy, kategorie, hledání), karty, délka stránky na telefonu.
4. **Jeden nástroj až k ceně**: `/tools/phone-stand` → návrh → „Materiál a počet kusů“ → „Pokračovat ke kalkulaci“ →
   kalkulace → stažení/poptávka (farma je pro hosty zavřená – jen k tlačítku). Kde se člověk ztratí? Kolik kliknutí?
   Co se stane při chybě (prázdný text u cedulky, velká fotka u reliéfu)?
5. **Kalkulace ze souboru** `/` s vlastním STL (vezmi `tests/fixtures` nebo kostku): srozumitelnost ceny, dodání.
6. **Reliéf/busta z fotky** `/tools/relief`, `/tools/figure`: nahrání, čekání (co vidím, než je hotovo), výsledek.
7. **Stránky důvěry**: o nás/kontakt/obchodní podmínky/ceny (co existuje v `/pages`), patička, 404.

U každé cesty: čas do první smysluplné obrazovky (LCP z Chrome `--enable-benchmarking` nebo Lighthouse, pokud je
`npx lighthouse` k dispozici – jinak alespoň velikost přenesených dat a počet požadavků), text, který je
nesrozumitelný nebo cizí (klíče, anglicismy v cs), nefunkční nebo matoucí ovládání, vzhled (nejednotné karty,
tlačítka, mezery, fonty, barvy – porovnej s pravidly z docs/N.md §… „oranžová jen na hlavní akci“, Inter).

## 2. Porovnání

Roman má rád printpal (předlohy map a figurky v `docs/prompts/mapa.md`, `figurka-mazlicek.md`). Projdi
printpal.io (a jeden další, např. makerworld „Make“ generátory nebo tinkercad landing) stejnýma očima a napiš,
**co dělají pro první dojem lépe** (první obrazovka, obrázky, počet kroků, texty) a co naopak my děláme lépe (bez
přihlášení, cena hned, farma). Ne kopírovat – pojmenovat rozdíl.

## 3. Čísla, která máme

- `docs/O.md` (návštěvnost: lidé, ne roboti) a `/admin/stats` – řídící session ti pošle výtah z produkce
  (návštěvy za 14 dní, vstupní stránky, podíl telefonů, návštěvy s jednou stránkou, nejčastější nástroje). Použij
  je k seřazení: co lidé vidí nejčastěji, má největší váhu.

## 4. Výstup `docs/Z.md`

1. **Shrnutí pro Romana** (10 řádků): tři největší důvody, proč člověk odejde, a co s tím.
2. **Nálezy** seřazené podle dopadu: každý má snímek, kde to je (stránka, šířka, jazyk), co je špatně (jednou
   větou), proč to odrazuje, návrh opravy, odhad práce (S = do 2 h, M = půl dne, L = den a víc), kdo to vlastní
   (session 1 shapes / 3 edit / 4 sell / B / D / E / řídící podle `docs/prompts/*.md` a `opravy.md`).
3. **Návrh kola 2**: nejvýš 10 změn, které dají největší posun za nejmenší práci; jednotný styl karet a stránek
   nástrojů má přednost (Roman: „chci jednotný styl“).
4. **Co jsi neuměla posoudit** (bez dat, bez přístupu) – napiš, ať to neřešíme dohady.

**Hotovo =** Z.md se snímky na větvi `feature/web-review`, push, zpráva řídící session. Nic v kódu. Odhad: den práce;
kdyby to rostlo, pošli shrnutí po cestách 1–4 a zbytek dodej potom.

## 5. Co není v zadání

- Opravy (kolo 2 po Romanově výběru). Výjimka: zjevně rozbitá věc (500, nefunkční tlačítko) – nahlas hned řídící
  session, ta rozhodne, kdo opraví.
- SEO texty nástrojů (`lang/*/tools_seo`), blog, admin.
