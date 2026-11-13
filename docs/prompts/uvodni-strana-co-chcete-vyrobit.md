# Zadání: úvodní strana „Co chcete vyrobit?“ – text první, ochrana proti spamu, sezonní nápověda

Stav k 12. 11. 2026. Rozhodl Roman, píše koordinátor. Navazuje na rozcestník úvodní strany (TODO #78 a #80,
`docs/AN.md §22`, `config/home.php`, `app/Domain/Intake/*`, `resources/views/calculator/home.blade.php`,
`resources/js/site/intake.ts`). Větev z `origin/main`, worktree `C:\matplace-edit-wt`, nic nenasazuj, nesahej na
produkci ani na tiskárny.

## Proč

- Stella chtěla „čelenku s rohy na Mikuláše“: přání bez fotky. Dnešní úvodní strana začíná výzvou k focení.
- Data 9.–12. 11. 2026 (cizí návštěvníci): 13 odeslaných přání, z toho 8 spam (reklamy, „chtěl jsem znát vaši cenu“
  v islandštině, maďarštině, arabštině), 3 jsou beze změny odeslané příklady ze štítků, 1 nejasné („mia“), 1 skutečné
  s e-mailem. Lidé tedy potřebují vidět, co napsat, a textové pole přitahuje spam, který stojí peníze za vyhodnocení.

## Co udělat

**1. Jednoduchá úvodní strana**
- Nadpis **„Co chcete vyrobit?“** a hned pod ním **velké textové pole jako první a hlavní prvek**.
- U pole (ne až pod ním) tlačítko **přidat fotku / vyfotit / obrázek** a **diktování** (mikrofon jen tam, kde ho
  prohlížeč umí; jinak se neukáže). Fotka je nepovinný doplněk textu; samotná fotka bez textu dál funguje.
- Pod polem **nápovědné štítky** (viz 3), tlačítko „Vytvořit“ a malý odkaz „Mám hotový 3D soubor“ (dnešní nahrání
  souboru zůstává funkční).
- Všechno ostatní na stránce zjednoduš tak, aby první obrazovka na telefonu (390 px) ukázala nadpis, pole, štítky
  a tlačítko bez posouvání. Co dnes pod tím je (co se stane potom, ukázky), nech níž.
- Cesty za odesláním (nástroj / tvar z fotky / člověk) se nemění; změní se jen vstup. Přání bez fotky, které neumí
  žádný nástroj (čelenka s rohy), musí dostat poctivou odpověď a nabídku předat člověku s e-mailem, ne slepou uličku.

**2. Ochrana proti spamu (před voláním modelu, aby spam nestál peníze)**
- Neviditelné pole (honeypot) a minimální doba od načtení po odeslání.
- Omezení počtu odeslání na relaci a IP adresu za čas.
- Levná pravidla nad textem: odkazy a HTML, typické reklamní fráze, délka, text ve zcela jiném písmu než jazyk
  stránky bez fotky; nastavitelné v konfiguraci. Opatrně: cizinec smí psát anglicky nebo španělsky.
- Co pravidla označí jako spam: uloží se se stavem „spam“, **nepošle se modelu**, v administraci přání se
  ve výchozím pohledu neukazuje (přepínač „ukázat i spam“), dá se ručně vrátit. Odesílatel dostane neutrální odpověď.
- Bez obrázkové captchy; pokud by pravidla nestačila, navrhni další stupeň v závěrečné zprávě.
- Spočítej na dosavadních přáních z testovací sady (`IntakeCases`, `docs/AN.md §22`) a na 8 spamových textech
  z dat výše (koordinátor je dodá na požádání; typy jsou popsané), kolik pravidla chytí a kolik poctivých zadrží.

**3. Sezonní nápověda (štítky podle období a nadcházejících událostí)**
- Kalendář událostí v konfiguraci (např. `config/home.php`): Mikuláš, Vánoce, Silvestr, Valentýn, Velikonoce,
  Den matek, Den otců, konec školního roku, začátek školy, Halloween/Dušičky, narozeniny a svatby jako celoroční.
  U každé události: od kdy do kdy se nabízí (s předstihem na výrobu a dopravu, ne až v den události), jazyky/země,
  kde platí (Mikuláš jen cs; en/es mají své), a 2–4 příklady přání.
- Štítek = krátký název + **vzorové přání, které se po kliknutí vepíše do pole** (zákazník ho má upravit; ne hned
  odeslat). Příklad pro teď: „Čelenka s rohy na Mikuláše“, „Ozdoba na stromek se jménem“, „Figurka našeho psa“.
- Vždy se ukáže několik sezonních a několik stálých (busta z fotky, náhradní díl…). Bez sezonní události jen stálé.
- Administrátor může v administraci událost vypnout, změnit data a texty příkladů bez nasazení (stejným způsobem
  jako ostatní nastavení úvodní strany).
- Každý příklad musí vést někam smysluplně: ověř na `IntakeRouter`, kam vzorové přání spadne (nástroj, tvar, člověk),
  a nenabízej příklady, které dnes končí odmítnutím.

**4. Měření**
- Události: zobrazení úvodní strany, kliknutí na štítek (který), připojení fotky, použití diktování, odeslání
  (text / text+fotka / fotka / soubor), označeno jako spam. Tak, aby šlo za měsíc říct, čím lidé začínají.
- Nezapomeň na značku provozních relací (`staff`), aby se do čísel nepočítal Roman.

## Texty

Česky, anglicky, španělsky přes `lang/src/*.json` + `python scripts/lang_add.py`. `heading` stránky = H1.
Po změně textů spusť `ToolPageTest|LandingPagesTest|SeoTest`.

## Testy a výstup

- PHP testy: pořadí a přítomnost prvků, pravidla proti spamu (spam se nepošle modelu), výběr štítků podle data
  a jazyka (pevné datum v testu), úprava událostí v administraci, měřené události.
- Snímky úvodní strany 390 px a 1280 px v cs/en/es do `docs/img/`.
- Větev `feature/home-what-to-make`, push, neslučovat; kapitola v `docs/AN.md` za §22.
- Závěrečná zpráva koordinátorovi: co je hotové, snímky, čísla pravidel proti spamu, seznam událostí s daty,
  co se liší od zadání a proč, otevřené otázky pro Romana.
