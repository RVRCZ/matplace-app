# Zadání pro session 2 (farma, řezání): vše na OrcaSlicer 2.5 a rušení překonaného řezání

Stav k 12. 11. 2026. Rozhodl Roman, zadává koordinátor. Větev z `origin/main`, práce ve vlastním worktree
(`C:\matplace-gcode-wt`), nic nenasazuj a nesahej na tiskárny ani na produkční `.env`; nasazení jde přes koordinátora.

## Rozhodnutí

1. **Všechny tiskárny řezat v OrcaSliceru 2.5**, i vícebarevné tisky. Dnes: `ORCA_BIN` = 2.4 pro Kobry,
   `ORCA_BIN_TOOLCHANGER` = 2.5 (`/opt/orca/squashfs-root-250a`) jen pro Prusu Core One INDX.
2. **Překonané řezání se nesmí dořezávat.** Když zákazník změní volby, běžící řezání téže zakázky se má ukončit
   a nové začít hned.

## Podklady (měřeno 11.–12. 11. 2026)

- Stínidlo `lampshadev2-hollow.stl` (958 tis. trojúhelníků, stromové podpěry, 15 %), farmářský profil Kobra S1 + PLA,
  Romanův PC: Orca 2.4 64 a 75 s, Orca 2.5 40 a 47 s (asi −37 %). Odhad tisku 15 h 46 min (2.4) proti 13 h 18 min
  (2.5) se stejným profilem: neví se, který je blíž skutečnosti.
- Zkušební dvojice 2JCZ-419 se shodným nastavením, vytištěno na U2 (PETG) a U1 (PLA): 2.4 a 2.5 daly stejný čas
  (77,0 / 78,3 min; 44,3 / 44,2 min) i spotřebu a Roman nevidí rozdíl v kvalitě. Orca 2.5 přidává 94 nových položek
  nastavení, všechny zůstaly na výchozích hodnotách; společné položky se nelišily.
- Zakázka 341 (U1, 1 M trojúhelníků): po změně voleb `timings.queue_s = 172.81`, běh 3 čekal na dořezání běhu 2;
  samotný slicer pak 209 s. Událost „choices changed while slicing: run 3 replaces run 2“.
- Server: 4 jádra (EPYC), 8 GB, tři fronty `matplace-worker`, `@2`, `@3`. Orca používá všechna jádra, takže souběžná
  řezání se o ně dělí.
- Skripty a výstupy měření: koordinátorův scratchpad `slicecmp/bench.py`, `safran/u2/make_pair.py`.

## Co udělat

**A. Přechod na 2.5**
- Najdi vše, co předpokládá 2.4 (`OrcaSlicer::slice`, volba binárky, `ONE_PLATE_PROCESS`, opravy „Orca 2.4“
  z `docs/AL.md §10`, mapování filamentů před `HEADER_BLOCK_START`, `MmuAcePatcher`, časosběr `TimelapseGcode`,
  změny barvy podle výšky a jejich řádek `; matplace filaments`, kopie jako instance, hrubý odhad velkých modelů).
- Kontrolní řezy 2.4 proti 2.5 místním Orca CLI (recept v paměti „Local Orca CLI control slice“), pro každou
  tiskárnu a případ: Kobra S1 jednobarevně s časosběrem; S1 dvě až čtyři barvy na jedné podložce; S1 změny podle
  výšky; Kobra X sedm barev (vzorek `Downloads\vzorek-7-barev-nad-sebou.3mf`); Kobra 3 Max; Prusa beze změny.
  U každého porovnej: počet nástrojů a výměn, pořadí `T`, čisticí věž, hlavičku, čas, gramy, a že následné úpravy
  farmy (pozice v ACE, předehřev, parkování) sedí na stejná místa.
- Přepínání nech konfigurací: jedna proměnná určí výchozí slicer, návrat na 2.4 musí být změna jednoho řádku
  v `.env` bez nasazení kódu. Na serveru je 2.5 jako alfa sestavení `250a`; zjisti, zda je novější stabilní 2.5,
  a doporuč, kterou verzi nasadit.
- Odhady ceny a času: ověř na třech velkých modelech, jak se liší odhad 2.4 a 2.5, a napiš, co to udělá s cenou
  (čas × sazba) a s `time_factor` tiskáren.

**B. Rušení překonaného řezání**
- Když přijde nový běh téže zakázky (změna voleb, „Přeřezat“), běžící proces sliceru starého běhu ukonči
  (i zjednodušování sítě) a nový pusť hned; výsledek starého běhu se nesmí zapsat.
- Pozor na `KillMode=mixed` a `TimeoutStopSec=960` u jednotek front (3. 11. 2026) a na sdílené mezivýsledky
  (uložené zjednodušení, cache řezů): zrušení nesmí nechat poškozený soubor v cache.
- Zákazník vidí, že se počítá nová verze, bez mezistavu „chyba“.

**C. Souběh**
- Změř na serveru (jen čtením logů a `timings`), jak často se řezání opravdu potkají a kolik přitom ztrácejí.
  Navrhni, zda omezit počet souběžných těžkých řezů nebo vlákna jednoho řezu; nic z toho nezaváděj bez čísel.

**D. Nepočítat podpěry tam, kde nejsou potřeba (Roman 12. 11. 2026: souhlasí)**
- Měření (stínidlo, Orca 2.5, farmářský profil S1 + PLA, Romanův PC): se stromovými podpěrami 35–39 s, bez podpěr
  22 s. Klasické podpěry 25 s, ale 358 g místo 268 g, takže stromové zůstávají.
- Při volbě „Automaticky“ rozhodni před řezáním z geometrie, zda model podpěry potřebuje (převisy pod prahem
  25° od vodorovné vůči zvolené orientaci, plocha a výška nad podložkou; mosty do `max_bridge_length` se nepočítají).
  Když ne, řež s `enable_support=0`. Výpočet musí být řádově rychlejší než ušetřený čas (cíl pod 1 s na
  350 tis. trojúhelníků, na zjednodušené síti).
- Bezpečná strana: při pochybnosti podpěry nech. Ověř na sadě modelů, že rozhodnutí „bez podpěr“ nikdy nenastane
  tam, kde Orca s podpěrami nějaké vygeneruje (porovnej `supports_used` ze skutečných řezů; použij modely
  v `C:\Users\roman\Documents\MODELS` a zakázky z testů). Výsledek zapiš do `timings` (např. `supports_check_s`,
  `supports_needed`), ať je vidět, kolik to šetří.
- Zákazník i administrace dál vidí pravdivý údaj „podpěry: ne“; volba „Bez podpěr“ a ruční přepnutí fungují jako dnes.

## Testy a výstup

- PHP testy pro volbu sliceru a pro zrušení překonaného běhu (falešný engine + skutečný proces, který se dá zabít).
- Větev `feature/orca25-all`, push, neslučovat. Doplněná kapitola v `docs/AL.md` s tabulkou kontrolních řezů a se
  seznamem toho, co zůstává neověřené na skutečných tiskárnách.
- Pro Romana krátký seznam zkušebních tisků před zapnutím pro zákazníky: jeden vícebarevný na S1, jeden na Kobře X,
  jeden se změnou podle výšky, jeden velký model kvůli odhadu času.
