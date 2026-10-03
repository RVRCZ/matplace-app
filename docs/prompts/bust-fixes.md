# Zadání: oprava dvou chyb nástroje „Figurka / busta z fotky“

Datum: 4. 10. 2026. Repozitář `C:\matplace-app` (Laravel 12), větev `feature/bust-fixes` z `main` (nasazený na
matplace.com). Pracuj ve vlastním worktree (`git worktree add C:\matplace-bust-wt feature/bust-fixes`), nikdy
`git add -A`, nikdy `git stash`. Nic nenasazuj a nepushuj bez mého pokynu.

## Chyby (podle hotové busty `bust.stl`, 83 × 63 × 140 mm, styl podstavce „socle“)

1. **Podstavec vyčuhuje z hrudi.** Horní část nožky („límec“ soklu, který má jít dovnitř těla) prochází spodkem
   hrudi ven: zepředu i ze stran je pod odříznutým hrudníkem vidět kus nohy podstavce, a v hrudi jsou mezi hrudí
   a nohou díry. Má to vypadat jako busta položená na soklu: límec je celý schovaný v těle, zvenku je vidět jen
   hladký přechod hruď → sokl.
2. **Roztřepený spodní okraj hrudi.** Obloukový řez hrudi (`arc_cut`) nekončí hladkou plochou, ale rozedranou,
   tenkou, místy děravou hranou — vypadá to jako natržený papír (viz obrázky, které ti pošlu). Očekávaný výsledek:
   čistý řez, plná plocha řezu (vnitřek busty je plný), hrana oblouku plynulá.

Oba jevy se objevují na bustách z fotky, kde generátor vrátí tělo až k loktům a spodek sítě je sám o sobě
roztřepený nebo otevřený. Pravděpodobné příčiny (ověř, nespoléhej na to):
- řez (`bust_cut` → `trim_by_plane` → `arc_cut`) vede přes místo, kde ještě zbývá původní rozedraný spodek sítě,
  nebo síť po `rebuild_solid` není v té výšce plná (tenká skořepina) a boolean nechá zbytky;
- `bust_shape("socle")`: límec `collar = r_top * 1.22` a posun nožky dopředu („the foot moves forward under the
  front of the chest“) se počítají z rozměrů v řezu, ale hruď je v místě límce užší/tenčí, než se předpokládá, takže
  límec vyjede ven; nebo se límec přičítá k hrudi, která byla řezem zúžená.

## Kde to je

- `engines/python/mesh_tool.py`: `bust_cut` (kde řezat), `rebuild_solid` (plné těleso z vygenerované sítě),
  `trim_loose`, `arc_cut`, `slant_cut`, `longer_chest`, `bust_shape`, `socle_sizes`, `pedestal_mesh`,
  tabulka `BUST_STYLES`; hlavní větev „pedestal“ kolem řádku 1150 (`seat_of`, `overlap`, spojení těla s nožkou,
  záložní „round“ podstavec při výjimce).
- PHP: `app/Jobs/GenerateModel.php` (volby `clean`, `pedestal`, …), `app/Domain/Generation/PedestalChanger.php`
  (výměna podstavce na hotovém modelu, `POST /api/files/{uuid}/pedestal`), `App\Engines\Vision`/`TripoGenerator`
  jen jako zdroj sítě.
- Testy: `tests/Feature/FigureToolTest.php` (Python s manifold3d, jinak skip), fixtury v `tests/Support`.
  Poznámky k historii úprav busty (voxel rebuild, `bust_cut` pravidla, `trim_loose`) jsou v paměti projektu
  `project_matplace_rebuild.md` (sekce 2026-09-20 až 22).

## Co udělat

1. Vezmi si ode mě vzorové soubory: zdrojovou síť z generátoru (`files/<uuid>/source.stl`, bez podstavce) a hotový
   `bust.stl` k té zakázce — řeknu ti uuid. Reprodukuj obě chyby lokálně (`python engines/python/mesh_tool.py …`
   nebo přes `PedestalChanger`) a ulož si před/po snímky (render přes `engines/python/render_tool.py`).
2. **Řez:** zajisti, že hruď pod řezem je plná a řezná plocha rovná: před `arc_cut` ořízni tělo rovinou o kousek
   nad nejnižší „zdravou“ vrstvou (tam, kde průřez přestane být roztřepený — měř obvod/plochu řezu po vrstvách
   a hledej skok), teprve pak oblouk; po řezu zkontroluj watertight a že plocha řezu je jedna souvislá oblast.
3. **Sokl:** límec musí ležet celý uvnitř hrudi: spočítej skutečný průřez hrudi v rovině límce (ne odhad z `r`),
   límec zmenši/posuň tak, aby byl uvnitř s rezervou (≥ 1,5 mm), a nožku posuň pod těžiště průřezu. Když to nejde
   (hruď je příliš úzká), raději límec vynech a nožku jen přilep k rovné řezné ploše — bez děr.
4. Stejnou kontrolu („nic z podstavce nesmí trčet ven, žádná díra mezi hrudí a nožkou“) udělej i pro „antique“;
   `round/square/hexagon/column/plaque` používají jen `overlap`, zkontroluj, že u nich překryv nevyčuhuje.
5. Test v `FigureToolTest`: na fixtuře s roztřepeným spodkem (vytvoř syntetickou: tělo + náhodně rozedrané
   trojúhelníky dole) musí po zpracování platit: watertight, řezná rovina je rovná (všechny vrcholy spodní plochy
   hrudi ve stejné z ± 0,2 mm nad oblouk), objem soklu mimo tělo = objem soklu pod řeznou rovinou (nic netrčí do
   stran), žádné trojúhelníky těla pod horní hranou límce vně nožky.
6. `docs/BUST-FIXES.md`: co bylo špatně, co se změnilo, rozhodnutí, snímky před/po (do `docs/img/`), nasazení
   (jen `php artisan optimize` + restart; Python se nebuildí). Starší hotové busty se nemění; `PedestalChanger`
   umožní podstavec vyměnit a tím i opravit.

## Pravidla

- Testy zelené: `vendor/bin/pint --dirty`, `php -d memory_limit=2G vendor/bin/phpunit` (~15 min, na pozadí).
  Žádné volání ven v testech.
- Nerozbij existující styly („socle“, „antique“, „cut“…) na bustách, které dnes vycházejí dobře — ověř na dvou
  dalších vzorech z `storage/app/files` (řeknu ti které).
- Když chybí údaj, který nejde zjistit z kódu ani ze vzorků, zeptej se v jedné zprávě najednou; jinak rozhodni sám
  a zapiš to do `docs/BUST-FIXES.md`.
