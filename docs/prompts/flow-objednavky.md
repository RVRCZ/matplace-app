# Zadání pro session 2: stránka zakázky řeže velký model až po výběru voleb a potvrzení barvy (TODO #41)

Od Romana (24. 10. 2026, po draku 1,8 mil. trojúhelníků): „První stránka je rychlá kalkulace pro ty, co mají tiskárnu
a chtějí stáhnout model. Druhá musí být pečlivější, abychom se neokradli, ale tak rychlá, aby byla dodržena kvalita
podle zvoleného a barva. Proto bych na straně objednávky přepočítal až po zvolení parametrů a potvrzení barvy, ne hned,
jak tam zákazník přejde.“ Řídící doplnila práh: malé modely jako dnes, velké čekají.

## Co dnes zdržuje

Zakázka draka na farmě (B8, 24. 10.): 4 min 15 s = zjednodušení sítě 52 s + Orca 3 min 16 s + 7 s. Přeřez po změně
voleb znovu minuty. Stránka zakázky začne řezat hned při příchodu s výchozím nastavením a výchozí barvou; zákazník pak
mění volby a každá změna je další řez. Podpěry nejsou páka (stromové 204 s, obyčejné 278 s, štíhlé stromové 197 s na
stejné síti; bez podpěr 82 s), zjednodušení už má cache podle bajtů souboru (`MeshDecimator`, `storage/app/mesh-cache`).

## Úkol

1. **Práh „velký model“** na jednom místě (`App\Domain\Farm\…`, config `farm.big_model` nebo vedle
   `engines.mesh.decimate_above`): model je velký, když má přes `decimate_above` trojúhelníků (dnes 400 000), **nebo**
   když řez na první stránce (kalkulace, `calculations.timings.slice_s`) trval přes 60 s. Ostatní je malý.
2. **Malý model**: stránka zakázky beze změny (řez hned při příchodu, přeřez po změně, 15 až 20 s).
3. **Velký model**: zakázka vznikne, ale řez se **nespustí**. Stránka ukáže volby (barvy a druhy filamentu podle strojů,
   kvalita, pevnost, podpěry, kusy, jednotky), **orientační cenu z odhadu** přepočítanou podle zvolených voleb (stejný
   odhad jako na první stránce, `rough` z kalkulace, přepočet v prohlížeči podle kvality, výplně, podpěr a druhu
   filamentu; označeno jako orientační) a větu „Přesnou cenu spočítáme po potvrzení barvy, trvá to asi N minut“
   (N z `slice_s` kalkulace, pokud je, jinak podle trojúhelníků). Řez spustí tlačítko **„Spočítat přesnou cenu“** nebo
   potvrzení barvy (jedno tlačítko, ne dvě). Objednat a zaplatit jde až po dokončení řezu, jako dnes.
4. **Během řezu** zůstávají volby na stránce (řídící 24. 10., main 584aace: `#farm-presets` se při `uploaded` nehýbe,
   změna čeká na tlačítko po dokončení). Rozšířit: změna voleb během řezu u velkého modelu řez **zruší a spustí znovu**
   (zrušení běžící úlohy fronty nebo označení výsledku jako neplatného; hotové kombinace z cache řezu okamžitě, jak dnes).
5. **Nový stav zakázky** pro „čeká na volby“ (např. `awaiting_choices`, nebo `uploaded` s příznakem `slice_deferred`):
   stavový automat `OrderFlow::move`, maily obsluze (`alertAdmin`) se neposílají, auditní události ano, admin stránka
   zakázky stav umí pojmenovat, `FarmGate` a lhůty pro nezaplacené zakázky s ním počítají (zakázka bez řezu se po
   týdnu uklidí jako nezaplacená).
6. **Tolerance zjednodušení podle vrstvy**: `MeshDecimator` dostane výšku vrstvy zvolené kvality; limit p99 =
   čtvrtina vrstvy, nejhorší bod = jedna vrstva (0,2 mm → 0,05 / 0,2; 0,28 → 0,07 / 0,28; 0,12 → 0,03 / 0,12). Klíč
   cache nese limity, takže se nic nepomíchá. Dnes padá cíl 350 000 u draka na jediném bodu 0,32 mm (p99 0,045 by
   prošlo); s tolerancí podle vrstvy půjde hrubá a běžná kvalita na 350 000 a Orca bude zhruba o polovinu rychlejší.
7. **Zpracování po nahrání**: u kalkulace 554 čekal řez 23 s (`wait_s`) na zpracování souboru po nahrání (2,7 mil.
   trojúhelníků). Zjistit, co v `ProcessModelFile` trvá (měření sítě v PHP po trojúhelnících?), a zkrátit (numpy přes
   `engines/python`, nebo měřit z hlavičky a nechat přesné hodnoty na řez).
8. Volitelně, až bude čas: zjednodušit zdrojový model jednou při nahrání a odvozovat z něj print.stl (zakázka by cache
   trefila vždy a orientace i stín pro rozložení kopií by běžely na 702 k místo 1,8 M; rozložení dnes 15 s).

## Ověření

Testy: velký model čeká a nespustí řez; tlačítko spustí; změna během řezu zruší a spustí znovu; malý model jako dnes;
stav v mailech a adminu; tolerance podle vrstvy v klíči cache. Měření na draku (`/tmp/dragon_thin.stl` na serveru,
nebo `Mystical_Chine_Dragon_V_EYE(1).stl` z Downloads Romana): čas od příchodu na stránku k ceně před a po, počet řezů
na zakázku. Zapsat do `docs/AL.md` (nová část) čísla před → po.

Větev **`feature/order-flow-big`** z aktuálního `main`. Netknout: agent, nástroje, první stránku kalkulace (ta je
session 3, `docs/prompts/kalkulace-odhad.md`). Texty přes `lang/{cs,en,es}/farm.php` nebo `lang/src/<soubor>.json` +
`scripts/lang_add.py`, JSON po úpravě validovat. Pint, testy farm/order zelené, push + zpráva řídící session.
