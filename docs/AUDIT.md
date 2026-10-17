# Zákaznický audit (10/2026): proveditelnost a mapa úkolů

Řídící session, 17. 10. 2026. Vstup: zadání auditu od Romana (14 bodů). Tohle je ověření proti dnešnímu webu a kódu
(stav `main` c99905a, nasazeno) a rozpis, co z toho je hotové, co běží a co je nové, s vlastníkem, velikostí a
závislostmi. Podklady z kola 1 a 2 pohledu návštěvníka: `docs/Z.md` (§2 nálezy N1 až N17, §7 stav kola 2),
`docs/prompts/web-kolo2.md` (session 3, běží), `docs/AC.md` (tok objednávky, session 1, nasazeno 17. 10.).

Legenda: **hotovo** (na produkci), **běží** (přidělená session), **nové** (zatím nikdo), **závislost** (čeká na
Romana nebo na rozhodnutí). Velikost S (hodiny), M (den až dva), L (víc).

## Bod 1 · Cíl a tři situace

| situace | dnes | poznámka |
|---|---|---|
| mám soubor → cena | hotovo | nahrání na `/`, kalkulace `/c/<kód>`, přesný výpočet slicerem |
| nemám soubor → z fotky, textu, rozměrů | hotovo | 55 nástrojů, busta a mazlíček z fotky, „Popsat slovy“ (návrhář) |
| mám tiskárnu → model nebo projekt | hotovo | „Mám tiskárnu, stáhnout“: STL nebo projekt pro 292 tiskáren |
| placená cesta zadání → návrh → cena → provedení a doručení → zaplacení → stav | hotovo v kostře | od 17. 10. jedna stránka zakázky: materiál, nastavení, barvy s kódy, doprava, platba; stav zakázky na účtu; **pro hosty zavřeno** (`farm.public` false, Roman 17. 10.: zatím neotevírat) |

## Bod 2 · Výchozí kontrola

- Kolo 1 (13. 10., session B): 17 nálezů se snímky 390 a 1366 px, cs i en (`docs/Z.md`, `docs/img/review/`).
- Od té doby se změnilo: hlavní stránka (16. 10.), tok objednávky a barvy (17. 10.), cookie lišta, menu, přesměrování,
  kalkulace na telefonu (17. 10.). Kolo 2 pro stránku nástroje a katalog dělá session 3 a píše si skript průchodu do
  `scripts/` (ten z kola 1 v repu není).
- **Úkol A (řídící, S):** po dokončení session 3 projet znovu všechny cesty tím skriptem (390, 360, 768, 1280; cs, en)
  a doplnit sem tabulku „cesta → kroky → co se stane“ se snímky. Nálezy třídit podle auditu: chyba / překážka /
  neověřený návrh.
- Technické příčiny se ověřují v kódu a na serveru, ne ze snímků (N4 je příklad: binární STL bez normál).

## Bod 3 · P1 hlavní stránka

- Dnes: jeden sloupec (Roman 16. 10.), nadpis „Od myšlenky k výrobku“, pole pro soubor, „Nahrát soubor“ a „Popsat
  slovy“, dvě karty „Mám 3D soubor“ / „Mám fotku nebo nápad“ (snímek `scratchpad` 17. 10.), nástroje níž.
- **Nové (řídící, M):** sdělení podle auditu („Váš nápad vytiskneme ve 3D. Nahrajte soubor nebo si výrobek vytvořte
  z fotky, textu či rozměrů. Cenu zjistíte před objednáním.“ + tišší „Máte vlastní tiskárnu? Model si můžete
  stáhnout.“), vstupy pojmenované podle situace (karty už jsou, posunout nad pole pro soubor nebo vedle něj),
  druhá cesta jako výběr fotka / text / nástroje, formáty zkrátit na „STL, 3MF, STEP a další“ s limitem, příklad
  výsledku u úvodu („Z vlastního textu vznikne jmenovka“ + fotka skutečného výtisku).
- **Závislost:** fotka skutečného výtisku od Romana (zatím „ne“, 17. 10.). Bez ní jde použít render s popiskem
  „náhled“, audit ale chce doložitelný výsledek.
- Akceptace: oba cíle (výtisk i stažení) viditelné; každá výzva vede na funkční krok; žádný slib okamžité konečné ceny.

## Bod 4 · P1 mobil

- Hotovo 17. 10.: cookie lišta na jeden řádek (telefon ~120 px místo 207), karta ceny v kalkulaci nad prohlížečem.
- Běží (session 3): stránka nástroje a katalog na 390 px, fotkové nástroje, odskrolování.
- **Nové (řídící, M):** výška úvodu na 360 × 800 a 390 × 844, navigace pro palec (dnes drobná: Nástroje, Cena ze
  souboru, Přihlásit, cs/en/es, Kč/€), jazyk a měna tišší, dotykové plochy ≥ 44 × 44 px, žádný vodorovný posun na 360,
  390, 768, 1280, text 200 %, formuláře s otevřenou klávesnicí.
- Akceptace je měřitelná skriptem (výška prvků, `scrollWidth`, velikost tlačítek); doplnit do skriptu průchodu.

## Bod 5 · P1 návaznost nástroj → kalkulace → objednávka

- Dnes: nástroj → „Pokračovat“ (Roman 16. 10. chtěl právě tohle slovo) → kalkulace → „Vytisknout u nás“ (přihlášený
  dostane zakázku hned, host se přihlásí) → zakázka → platba; „Mám tiskárnu, stáhnout“ zvlášť. Materiál a počet kusů
  z nástroje přechází do kalkulace, kvalita, kusy, velikost, podpěry a výplň z kalkulace do zakázky (AC.md).
- **Rozpor k rozhodnutí:** audit chce místo „Pokračovat“ popis výsledku („Spočítat cenu a možnosti tisku“). Doporučuji
  audit: říká, co se stane. Roman rozhodne.
- **Nové (M, rozděleno):** přejmenování akcí (nástroj: session 3; kalkulace a zakázka: řídící), kontrola, že zpět
  a obnovení stránky nic neztratí (nástroje drží parametry v adrese? ověřit; zakázka má token), neúspěšný výpočet
  zachová zadání (dnes ano, zůstává odhad), nezávazná akce nevytvoří placenou objednávku (zakázka vzniká
  nezaplacená; ověřit, že „Vytisknout u nás“ je jasně označené jako začátek objednávky), stavy načítání / úspěch /
  chyba na každém přechodu.

## Bod 6 · P1 cena a doručení

- Dnes: na stránce nástroje žádný odhad (od 17. 10.), kalkulace ukazuje „≈“ odhad a pak přesný výpočet slicerem,
  zakázka přepočítá po změně barvy nebo stroje („Přepočítat pro zvolenou barvu“) a má shrnutí před platbou (barvy se
  štítky, cena, doprava, platba). Doba tisku vs. expedice: zakázka říká „začne hned“ / fronta; doručení Zásilkovnou.
- **Nové (řídící, M):** příklady výrobků s cenou (fotka, rozměry, materiál, kusy, s dopravou nebo bez): čísla vezmu
  z kalkulace na produkci jako u článku (busta 80 mm 99 Kč, 120 mm 130 Kč, 150 mm 200 Kč), fotky od Romana;
  v zakázce věta, **proč** se cena změnila (jiný stroj, jiný druh); souhrn před platbou zkontrolovat proti skutečnému
  účtování; oddělit „tisk X h“ od „expedice do Y dní“ a „doručení Z“ s vysvětlením, že jde o odhad.
- **Závislost:** fotky výrobků; pravidla expedice (kolik dní od dotisku po předání Zásilkovně) od Romana.

## Bod 7 · P1 (návrh zvlášť) první platba

- Dnes: kreditem, nebo kartou; při nedostatku kreditu tlačítko „Zaplatit kartou :amount (kredit :balance)“ platí
  jen chybějící část (`farm.order.pay_card_rest`), stav „čeká na banku“ / „platba přišla, zakázka nešla spustit“,
  testy `FarmCardPaymentTest`.
- **Ověřit (řídící, S až M):** průchod s nulovým, nedostatečným a dostatečným kreditem na produkci v testovacím
  režimu banky; zamítnutá a přerušená platba (zakázka zůstává, opakování bez dvojího účtování); FAQ a texty, které
  mluví o nutnosti dobít kredit (přepsat). Výsledkem popis stavů a návrat při problému.
- Kód plateb (`OrderFlow`, `Wallet`) je jen pro řídící session.

## Bod 8 · P2 přehled nástrojů

- Běží (session 3): kompaktní karty, sbalené kategorie s počty, chipy v řádku, první nástroj na první obrazovce.
- **Doplnit session 3 nebo další kolo (M):** úvodní výběr hlavních použití (dárek se jménem, organizér, krabička,
  QR cedulka, z fotky) označený jako redakční, dokud nejsou data; karty sjednotit (název, co je potřeba zadat, ukázka,
  jedna akce); synonyma v hledání; prázdný výsledek s „zrušit filtry“ a příbuznými nástroji; návrat z detailu drží
  filtr. Data o použití nástrojů jsou v `events` (trychtýř v administraci), pořadí lze opřít o ně.

## Bod 9 · P2 Wi-Fi QR

- Dnes: nástroj QR bere obsah jako text; pro Wi-Fi radí ruční zápis `WIFI:T:WPA;S:…;P:…;;`.
- **Nové (volná session, M):** typy Web / Wi-Fi / Text; Wi-Fi: název sítě, heslo, zabezpečení (WPA/WPA2, WEP, bez),
  skrytá síť; escapování `\ ; , : "`; kontrola a případná úprava adresy u Webu ukázaná uživateli; přepnutí typu nemění
  rozměry a vzhled cedulky; kontrola čitelnosti a dvoubarevné provedení zůstávají; heslo a obsah Wi-Fi QR nejdou do
  analytiky ani do logů (ověřit `events` a logy generování). Testy na speciální znaky; načtení na Androidu a iOS
  ověří Roman.
- Soubory: stránka QR (`param.ts` je sdílený, změna co nejmenší), engine `engines/python/param_tool.py` (QR).

## Bod 10 · P2 důvěra a skutečné ukázky

- Dnes: O nás, Kontakt (e-mail, s.r.o.), FAQ, Materiály; bez fotek lidí a provozu; karty nástrojů mají fotky z
  Gemini (ne skutečné výtisky); štítek „Nejčastěji tištěné“ na hlavní stránce je redakční (pořadí z 13. 10.).
- **Závislost (Roman, zatím „ne“):** fotky výtisků a farmy, představení lidí, doba odpovědi na kontaktu (jen pokud ji
  provoz drží). Bez podkladů jde udělat jen: u ukázek rozlišit „fotka výtisku“ / „náhled“, štítek „Nejčastěji
  tištěné“ přejmenovat na „Náš výběr“, nebo ho opřít o `events` (řídící, S).

## Bod 11 · P1 průběžně: texty a stavy

- Hotovo částečně: hláška při selhání přesného výpočtu (17. 10.), varování nástrojů ve třech jazycích (E #11),
  test na nepřeložené klíče na stránkách nástrojů.
- **Nové (řídící, M, průřezově):** slovník: „3D model“ / „soubor“ pro digitální výsledek, „výtisk“ / „výrobek“ pro
  fyzickou objednávku; akce vytvořit / stáhnout / objednat tisk / zaplatit; „zdarma“ vždy s limitem na místě
  rozhodnutí (busta: 1 denně bez účtu, 3 s účtem; session 3 to posouvá nad tlačítko); u každého upravovaného
  procesu prázdný stav, načítání, úspěch, chyba; test, že žádný použitý klíč nechybí v cs, en, es.

## Bod 12 · Měření

- Dnes: `events` (měřeno se souhlasem, `measure.ts`), trychtýř v administraci (`/admin/stats/funnel`), traffic
  „lidé, ne roboti“ (docs/O.md), potvrzená platba ze stavu banky (karta: `card.status`).
- **Nové (volná session, S až M):** doplnit chybějící události (výběr vstupní cesty, zobrazení ceny, zahájení
  objednávky, přechod k platbě, úspěšné stažení), rozdělení mobil / počítač a nový / vracející se, TODO #10 (naše
  zkušební průchody a skripty nepočítat), popis událostí v docs s kontrolou, že neobsahují fotky, hesla ani texty
  zadání. Vyhodnocení s velikostí vzorku; dnes 6 až 12 lidí denně, takže na závěry o konverzi je třeba týdny.

## Bod 13 · Ověření

- Automaticky: `FarmOrderFlowTest`, `ToolsFlowTest`, `FarmCardPaymentTest`, `ToolPageTest`, nové testy kola 2; skript
  průchodu (session 3) rozšířit o cesty z auditu a o měření (vodorovný posun, velikost tlačítek, fokus).
- Ručně (Roman): skutečný mobil (klávesnice, soubory, 3D náhled), Android a iOS u QR, test s pěti lidmi (4 z 5 bez
  nápovědy). Výsledek slouží k nalezení problémů, ne jako důkaz růstu.

## Bod 14 · Pořadí a předání

**Etapa 1 (hned, po dokončení session 3):** A kontrola stavu a mapa cest; hlavní stránka a navigace na mobilu
(body 3, 4; řídící); popisky akcí a zachování zadání (bod 5; session 3 nástroje, řídící kalkulace a zakázka);
rozlišení cen a souhrn (bod 6 bez fotek; řídící); slovník a stavy (bod 11; řídící + session 3).

**Etapa 2:** katalog podle bodu 8 (session 3, kolo 2b); Wi-Fi QR (volná session); příklady s cenou a důvěra
(řídící, po fotkách od Romana); měření (volná session, s TODO #10).

**Zvlášť:** platba (bod 7): ověření průchodů a texty hned (řídící, S), návrh změn podle výsledku.

Ke každé části: seznam změn (commit a doc `docs/<písmeno>.md`), snímky 390 a 1366 do `docs/img/`, výsledky testů,
známá omezení; u plateb popis stavů a návrat při problému.

## Rozhodnutí a podklady od Romana

**Odpovědi 17. 10. 2026 večer:** 1. platí audit (popisky říkají, co se stane: „Spočítat cenu a možnosti tisku“,
„Stáhnout model“, „Objednat tisk“); 2. fotky výtisků a farmy dodá; 3. expedice: zakázka se odesílá v den dotištění,
v pracovní dny (víkend → pondělí); 4. štítek „Nejčastěji tištěné“ → „Náš výběr“. Farma pro hosty zůstává zavřená.


1. „Pokračovat“ (16. 10.) vs. „Spočítat cenu a možnosti tisku“ (audit): které platí?
2. Fotky skutečných výtisků a farmy, lidé, doba odpovědi: bez nich zůstávají body 3 (příklad), 6 (příklady cen)
   a 10 otevřené.
3. Pravidla expedice (dny od dotisku po předání dopravci) pro oddělení tisk / expedice / doručení.
4. Farma pro hosty zůstává zavřená (17. 10.), takže cesta „host → objednávka“ končí přihlášením; audit s tím
   počítá („požadavek na přihlášení“ zaznamenat), ale „zahájení objednávky bez účtu“ není možné.
5. Štítek „Nejčastěji tištěné“: opřít o data, nebo přejmenovat.
