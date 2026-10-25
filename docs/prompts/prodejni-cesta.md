# Prodejní cesta webu matplace.com – zadání Romana z 25. 10. 2026 (celé)

Rozděleno řídící session na `prodejni-cesta-web.md` (session 3: body 1, 3 až 11, 13), `prodejni-cesta-farma.md`
(session 2: body 2, 3, 6, 7, 13) a práci řídící (bod 5 hlavní stránka, bod 12 hlavičky, nasazení, závěrečná zpráva).
Stav v `docs/TODO.md` #43 až #45. Níže původní znění.

---

Projdi a vylepši web matplace.com podle níže uvedených priorit. Pracuj s reálným kódem a obsahem webu, nejen s obecnými doporučeními. Před každou změnou ověř aktuální stav a respektuj stávající architekturu, styly, překlady a způsob nasazení. Nic nemaž ani nevypínej bez ověření, že to není používáno jinou částí webu.

HLAVNÍ CÍL
Zvýšit počet dokončených objednávek tisku, zpřehlednit cestu návštěvníka a odstranit konkrétní obsahové a technické nesrovnalosti. Nepřidávej další funkce bez souvislosti s tímto cílem.

PRIORITA 1 – OPRAV PRODEJNÍ CESTU
1. Katalog https://matplace.com/models ve veřejném auditu hlásil „V tomto výběru zatím není žádný model“, přestože na něj https://matplace.com/model odkazuje jako na katalog modelů připravených k tisku. Ověř příčinu a naprav datový nebo navigační stav tak, aby návštěvník nenarazil na slepou cestu. Pokud nemají být žádné modely dostupné, odstraň zavádějící odkazy a prázdnou stránku dočasně vyřaď z indexace a sitemapy. Pokud mají být dostupné, oprav jejich načítání a zobrazení.

2. Sjednoť platbu. Na webu se objevuje „kreditem nebo kartou“, ale jinde povinný předplacený kredit. Nejprve zjisti skutečný průběh objednávky a potom uprav všechny dotčené texty ve všech jazycích. Pokud to stávající systém umožní, navrhni a po ověření implementuj přímou platbu za objednávku bez povinného dobíjení kreditu. Povinný kredit nenahrazuj nekompatabilním řešením; v takovém případě alespoň umožni dobití přesné částky a jasně vysvětli nakládání se zbytkem kreditu.

3. Ukaž orientační celkovou cenu včetně dopravy dříve v objednávkovém procesu. U malých výtisků může doprava zásadně změnit cenu. Zachovej správný výpočet podle země a hmotnosti a jasně odliš odhad od konečné ceny.

PRIORITA 2 – ZPŘEHLEDNĚNÍ NÁSTROJŮ
4. Vylepši https://matplace.com/tools pro 58 nástrojů. Přidej jednoduché vyhledávání podle názvu, účelu a synonym a filtry podle záměru, například dárek, fotka, domácnost, náhradní díl a firma. Zachovej stávající kategorie a responzivní chování. Vyhledávání musí fungovat i s českou diakritikou a bez ní.

5. Na hlavní stránce a v přehledu nástrojů zdůrazni primární cesty: Mám 3D model, Mám fotku nebo nápad, Chci dárek, Potřebuji díl, Chci vytvořit něco pro firmu. Nepřetěžuj první obrazovku dalšími bloky.

PRIORITA 3 – DŮVĚRA A KONVERZE
6. Doplň do prodejní cesty reálné ukázky hotových výtisků, cenu, skutečnou dobu výroby a případné ověřené recenze. Nic nevymýšlej. Použij jen dostupné autentické materiály a ověřené údaje. Propoj ukázky s nástrojem nebo kalkulací, ze které lze rovnou pokračovat.

7. Zkontroluj texty kolem kalkulace, registrace, dobíjení kreditu, výběru barvy, dopravy a platby. Odstraň opakování a rozpory. U každého kroku musí být jasné, co návštěvník udělá dál a zda už něco platí.

PRIORITA 4 – SEO A LOKALIZACE
8. Přepiš obecné SEO titulky a popisy stránek /tools, /model a jejich jazykových verzí tak, aby přesně popisovaly obsah a hlavní záměr návštěvníka. Nevznikají z toho šablonovité nebo keywordově přeplácané texty.

9. Přelož zbývající anglické prvky českého katalogu, zejména Previous, Next a Showing 1 to 48. Zkontroluj i anglickou a španělskou verzi.

10. Navrhni několik užitečných kurátorských vstupních stránek podle záměru, například 3D tištěné dárky se jménem, náhradní díly na míru a 3D tisk pro firmy. Vytvoř je pouze tehdy, pokud pro ně existuje dostatek skutečného obsahu, modelů a nástrojů. Nepřidávej tenké stránky jen pro SEO.

11. Zachovej správné canonical URL, hreflang, HTTP 404 a stávající pravidla indexace. Do sitemapy patří pouze indexovatelné kanonické stránky. Nepřidávej do ní stránky s noindex. Po změnách sitemapu a strukturovaná data ověř.

TECHNICKÁ VYLEPŠENÍ
12. Po ověření kompatibility doplň bezpečnostní hlavičky Content-Security-Policy včetně frame-ancestors a Permissions-Policy. CSP nejprve připrav tak, aby nerozbila nahrávání souborů, 3D náhled, kalkulace, platby, analytics ani marketingové nástroje se souhlasem. Zvaž skrytí přesné verze nginx. Nezasahuj do fungujícího HTTPS, HSTS a cachování statických souborů.

13. Doplň měření konverzního trychtýře: začátek a dokončení nahrání, chyba nahrání, dokončená kalkulace, klik na Vytisknout, začátek registrace, dobíjení kreditu, platba a dokončená objednávka. Měření respektuj cookie souhlas a neodesílej obsah nahraných modelů, osobní údaje ani jiné citlivé hodnoty.

OVĚŘENÍ
- Nejprve proveď změny s nejvyšším dopadem a nejmenším rizikem.
- Zachovej češtinu, angličtinu a španělštinu a mobilní zobrazení.
- Otestuj hlavní scénáře: nahrání modelu, výpočet ceny, stažení modelu, výběr nástroje, vytvoření modelu, objednávka a platba.
- Otestuj odkazy mezi /model a /models a prázdné i naplněné stavy katalogu.
- Spusť standardní testy a build projektu a oprav chyby způsobené změnami.
- Nereportuj něco jako hotové bez ověření.

VÝSTUP
Na závěr uveď seznam změněných souborů, co se skutečně změnilo, jak byly scénáře otestovány, výsledky testů a buildu a co zůstalo nevyřešené. Odděľ implementované změny od dalších doporučení.
