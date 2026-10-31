# Přívěsek z obrázku: stránka se otevírá s logem Matplace podle Romanova nastavení (session 1)

Zadal Roman 31. 10. 2026 večer u `/tools/charm` (nástroj `charm`, rodina `shape`, docs/P.md): „u nástroje přívěsek z vlastního obrázku změnit vstupní stránku nástroje na matplace, s nastavením jak je na obrázku“. Jeho snímky: `docs/img/zadani-charm-matplace-1.png` (celá stránka, Vstup), `-2.png` (Rozměry), `-3.png` (Rozměry a Barvy). Navazuje na TODO #52 (nástroje se otevírají jako hotový výrobek).

## 1. Co je dnes

- `/tools/charm` se otevírá s obrázkem knihovny `ParametricGenerator::SAMPLE['charm'] = 'lib:colour/happy-ghost'` a výchozími hodnotami polí. Předvolby: `PRESETS[$kind][$name]` plus `->defaults('preset', …)` na route; podtácek `golf` ukazuje předvolbu s `artwork`, `body`, rozměry a barvami napevno, brčko `straw` předvolbu na route.
- Knihovna obrázků = `engines/artwork/<kategorie>/<slug>.svg` (`App\Domain\Tools\Artwork`, odkaz `lib:<kategorie>/<slug>`; kategorie `colour` má barevná SVG: happy-ghost, red-heart, paw-badge, snowman…). Logo Matplace je v repu jen jako rastr: `public/img/logo.png` 1200 × 721 a `public/img/logo-header.webp`; SVG není.

## 2. Cíl: po otevření přesně stav ze snímků

- Obrázek: logo Matplace (kapka a nápis), pozadí odstraněné, tvar podle obrázku.
- Vstup: `remove_bg` zapnuto, `bg_strength` 25, `smooth` 0,3 mm; jméno a druhý řádek prázdné.
- Rozměry: `body = image`, `width` 55 mm, `thickness` 3 mm, `frame` 1,7 mm, `eyelet` zapnuto, `eye_pos` 38 %, `eye_hole` 4,5 mm, `bevel` vypnuto.
- Barvy: `colors_n` 4, `relief` 0,8 mm, `flush` vypnuto, `rim` vypnuto. Barvy z obrázku na snímku: barva 3 měděná/oranžová (asi 2,5 %), barva 2 tyrkysová (asi 46 %), barva 1 tmavě zelená (asi 51 %), podklad šedá `#8c9199`. Když automatický výběr filamentů vyjde jinak než na snímku, ať je předvolba určí napevno (jako `golf`).
- Výsledek na stránce: 61,9 × 34,4 × 5,4 mm, 4 barvy, „vejde se na 250 mm“, očko vpravo dole u písmene e jako na snímku.
- „Změnit obrázek“, „Odebrat a použít text“ a výběr z knihovny fungují dál. Vlastní nastavení návštěvníka uložené v prohlížeči má přednost jen tak, jak to platí dnes (`resources/js/calc/tool_page.ts` kolem řádku 430: stránka otevřená s předvolbou ho nebere).

## 3. Jak

1. Logo do knihovny jako barevné SVG `engines/artwork/colour/matplace.svg` (odkaz `lib:colour/matplace`). Zdroj: řídící požádá Romana o SVG loga; než přijde, udělej vektor z `public/img/logo.png` (čtyři barvy, obrys po odstranění pozadí) a porovnej se snímkem; až přijde SVG od Romana, vyměň soubor. Do `engines/artwork/SOURCES.md` zapiš, že je to naše vlastní logo (ne CC0). Když nemá být logo ve výběru knihovny pro návštěvníky, přidej do `Artwork` příznak skrytí; jinak ať je vidět v kategorii barevných.
2. `PRESETS['charm']['matplace']` se všemi hodnotami výše včetně `artwork`, route `tools.charm` `->defaults('preset', 'matplace')`; `SAMPLE['charm']` může zůstat pro otevření bez předvolby. Ověř, že `bag_charm`, `keychain` a ostatní druhy se nezmění (sdílené konstanty `SHAPE_FIELDS`, `SHAPE_WHEN`).
3. Snímek výsledné stránky do `docs/img/charm-matplace.png` a porovnání s Romanovým snímkem: rozměry, barvy, poloha očka.

## 4. Testy a odevzdání

- `ToolPageTest` (každá předvolba se staví), `ParametricToolsTest`, `HeldShapesTest` bez změny výsledků; `vendor/bin/pint --test`; `npx tsc --noEmit -p tsconfig.json` a `npm run build` jen když sáhneš do TS nebo CSS.
- Session 1, worktree `C:\matplace-shapes-wt`, větev `feature/charm-matplace` z main (01392c2e nebo novější). Zápis: `docs/P.md` nová kapitola §10 („Přívěsek: logo Matplace jako výchozí“).
- Texty jen přes lang soubory, `git add` jmenovitě, žádný stash. Nic nenasazuj.
- Po pushi zpráva řídící session: větev, commit, co je ověřené, snímek. Řídící sloučí; Roman zkontroluje vzhled na produkci po nasazení.

## 5. Doplnění Romana 31. 10. večer: více tvarů podložky a tvary graficky

Roman: „přidat více tvarů podložky například ovál a zobrazit je graficky“. Snímek `docs/img/zadani-charm-tvary.png` (část Rozměry, Tvar = Podle obrázku, Kruh, Obdélník, zakroužkováno).

### Co je dnes

- `BODIES["charm"] = ("image", "circle", "rect")` v `engines/python/shape_kinds.py` (řádek 54) a `CHOICES['charm']['body']` v `ParametricGenerator` (řádek 291). Obrys podle `body` staví funkce téhož souboru kolem řádků 100 až 120: circle, hex, octagon, star, rosette, heart a shield už existují (medailon, ozdoba), rect a square také.
- Chipy volby jsou jen text: `resources/views/tools/param.blade.php` řádky 139, 310 a 365 (`<input type="radio" data-choice>` + `$tr('o', $o)`), popisky v `lang/*/param.php` jako `o.<hodnota>`.

### Cíl

1. Přívěsek nabídne víc tvarů podložky: podle obrázku, kruh, **ovál** (nový: elipsa; poměr výšky k šířce napevno kolem 0,7, nebo pole `height` viditelné jen u oválu a obdélníku přes `WHEN`, vyber to čitelnější a napiš proč), obdélník se zaoblenými rohy, srdce, šestiúhelník, hvězda (ty engine už umí, jen je pustit přívěsku). Obrázek nebo jméno se do tvaru vejde stejně jako dnes do kruhu, očko po obvodu (`eye_pos`) sedí na obrysu u každého tvaru.
2. Chipy tvaru **graficky**: každá volba `body` má malou ikonu tvaru (inline SVG přes `x-icon`, názvy `shape-image`, `shape-circle`, `shape-oval`, `shape-rect`, `shape-heart`, `shape-hex`, `shape-star`, …), text zůstane v `title` a `aria-label`; krátký popisek pod ikonou jen když se na 390 px vejde. Mechanismus udělej obecný: volba, pro kterou existuje ikona `shape-<hodnota>`, se vykreslí s ikonou, jinak textem jako dnes. Stejná volba `body` u sourozenců (keychain, ornament, magnet, coaster, bag_charm, straw, medallion) tak dostane ikony zadarmo. Nové tvary přidávej jen přívěsku; sourozencům jen tam, kde to je stejný kód a nic nerozbije.
3. Předvolba `matplace` z §2 zůstává „podle obrázku“.

### Testy

- `HeldShapesTest` a `ParametricToolsTest`: každý nový tvar přívěsku se postaví s obrázkem i se jménem, očko u oválu a srdce leží na obrysu, nic se nekříží, rozměry odpovídají šířce. Snímek chipů s ikonami do `docs/img/charm-shapes.png` (390 a 1280 px). `ToolPageTest` projde pro všechny druhy, které chipy sdílejí.
