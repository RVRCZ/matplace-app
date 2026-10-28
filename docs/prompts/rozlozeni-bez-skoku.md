# Rozložení stránek nástroje, kalkulace a zakázky: náhled a akce stále vidět, žádné skoky – session 3 a session G

Roman 28. 10. 2026 večer, podle vzoru paramet.io: panel voleb roluje uvnitř sebe, náhled vpravo a tlačítka zůstávají na
místě; a „když se zmáčkne nějaké tlačítko, tak to někam skočí a hned nevím, kde jsem“. Piš česky, krátce, bez pomlček.
Žádná změna výpočtů, plateb ani textů kroků; jen rozložení, rolování a chování po akci.

## Rozdělení

- **Session 3** (`conceprt19092026-01`, `C:\matplace-edit-wt`, větev `feature/layout-sticky-tools-calc` z main): stránka
  nástroje (`tools/page.blade.php`, `tool_page.ts`, kroky `tool-nav`) a stránka kalkulace (`calculator/index.blade.php`,
  `calculator.ts`). Deník AN.md §18.
- **Session G** (`conceprt19092026-cf`, `C:\matplace-guest-wt`, větev `feature/layout-sticky-order` z main): stránka
  zakázky (`farm/order.blade.php`, `farm.ts`). Deník AO.md §5.
- Společný styl: jedna sada tříd/komponent (`sticky` panel, spodní lišta), domluvte si ji zprávou, než začnete, ať
  vypadají stejně; session 3 ji navrhne první (má dvě stránky), session G převezme.

## Co má platit na desktopu (od `lg`)

1. **Náhled je přilepený** (sticky) na výšku okna; u nástroje už je (`#tool-stage`), u kalkulace a zakázky ne.
2. **Volby rolují uvnitř svého sloupce**: sloupec s poli má výšku okna (minus hlavička), vlastní posuvník, lišta kroků
   (nástroj) nahoře uvnitř, hlavní akce (Spočítat / Stáhnout / Vytisknout u nás; Zaplatit) **vždy vidět** dole
   v panelu nebo pod náhledem. Při 1366×768 se musí vejít náhled i karta tlačítek; když ne, náhled se zmenší, ne
   tlačítka.
3. **Zakázka**: vlevo náhled a velké číslo ceny, vpravo kroky (materiál a barvy, doručení, platba) rolují; dole přilepená
   lišta „Celkem k zaplacení 259 Kč · Zaplatit kartou“ (mění se s volbami, totéž tlačítko jako dnes, jen stále vidět).
4. **Kalkulace**: karta ceny s „Vytisknout u nás“ přilepená nahoře pravého sloupce, pod ní rolují volby, materiály,
   příklady a blok důvěry.

## Na telefonu (do `lg`)

- Pořadí zůstává (náhled, cena, volby), ale hlavní akce je ve **spodní přilepené liště** s cenou (nástroj: „Spočítat
  cenu“ / po výpočtu cena + „Vytisknout u nás“; kalkulace: cena + „Vytisknout u nás“; zakázka: celkem + „Zaplatit“).
  Lišta nesmí zakrývat poslední pole formuláře (spodní odsazení obsahu) ani cookie lištu (ta je výš, nebo se lišty
  skládají). Při otevřené klávesnici se lišta nechová divně (test na Androidu v emulaci Chrome).

## Žádné skoky po akci

- Projděte všechna `scrollIntoView`, `scrollTo`, `location.hash`, `scroll-mt` a `focus()` na těch třech stránkách
  (Spočítat, Přepočítat, Vytvořit, Použít, kroky v liště, chyby validace, dokončení generování). Pravidlo: **neroluj,
  když je cíl už vidět**; když vidět není, roluj plynule jen tolik, aby byl vidět, a to uvnitř panelu, ne celou
  stránkou. Změnu, která nastala mimo oči (nová cena, hotový model), **zvýrazni** na místě (krátké zvýraznění karty
  ceny, badge na liště), nepřesouvej uživatele.
- Validace: chybné pole se ukáže v panelu (rolování panelu) a dostane focus, stránka stojí.
- Lišta kroků u nástroje přepíná sekce uvnitř panelu; stránka se nehýbe.

## Ověření

- Snímky a průchody v headless Chrome na 390, 768, 1280 a 1366×768: stránka nástroje (qr, box, figure), kalkulace
  s výsledkem (kostka), zakázka hosta (po nasazení objednávky bez účtu) ve stavu před zaplacením; bez vodorovného
  posuvu, tlačítka vidět v každém kroku, žádný skok po akcích (ověřit `window.scrollY` před a po kliknutí v testu
  s puppeteerem/Chrome, kde to jde; jinak ručně a zapsat).
- Testy: `ToolPageTest`, `ToolsFlowTest`, `CalculationFlowTest`, `FarmGuestCheckoutTest`, `FarmSalesPathTest`,
  `AuditRestTest` zelené; pint, tsc, build. Zpráva řídící (`conceprt19092026-91`) po pushi s výstupem.
