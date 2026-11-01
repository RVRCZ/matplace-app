# Medaile: poutko na stužku vedle očka na řetěz, šířka stužky volitelná (session 3)

Zadal Roman 1. 11. 2026 dopoledne se snímkem medaile Winter League (`docs/img/zadani-medaile-poutko.png`, zeleně orámované poutko): „pro medaili by to chtělo i poutko na stužku, ne jen očko na řetěz; šíře by měla být volitelná“. Navazuje na docs/P.md §8 až §8d (medaile, session 1).

## 1. Co je dnes

- Medaile (`medallion`, rodina `shape`, `engines/python/shape_kinds.py`) má očko vždy: `eyelet = … or medal` (řádek 478). Kroužek stojí rovně nad středem na nejvyšším bodě obrysu (řádky 636 až 640), `eye_hole` 5 až 8 mm, `eye_wall` 2 až 4 mm, otvor zůstává venku (`reach = hole_d/2 + wall/2`, řádek 644). Lem `border` sleduje `medal2d` bez očka (`lem2d - ring_out`, řádky 741 až 752). Otvor se řeže i z vrstev motivu (`hole2d`, řádky 707 až 710).
- Řetěz: `links` 0 až 80 (výchozí 40) leží kolem medaile na podložce v témže dílu `body` (řádky 836 až 849, `_chain` řádek 331, `CHAIN_BED` 220), články se zacvaknou. `links = 0` řetěz nestaví (`_chain` vrací None) a `beside` je None.
- Lícem dolů (`motif = flush`) se celá medaile otáčí (`parts["use"]`), lícem nahoru stojí motiv na placce.
- Stránka: `FIELDS['medallion']` (ParametricGenerator řádek 208), `CHOICES` řádek 307, `WHEN` řádek 410, první volba `motif` (`FIRST`). Texty: `medallion.lead` („s řetězem“), `medallion.tip` (lang/*/param.php řádky 243 až 246), `tools.php` `medallion.title` „Medaile pro vítěze s řetězem“ a `medallion.hint`. Názvy dílů `lang/src/order_part_labels.json`: `body` „Medaile a řetěz“, `color_1` „Číslo a lem“ (sdílený `partLabel` v `resources/js/calc/parts.ts` a `OrderService::partLabel`, AN.md §21.4).
- Chipy volby s ikonou jen pro `body` (`resources/views/tools/_choice.blade.php` řádek 2: `$key === 'body'` a `ParametricGenerator::SHAPE_ICONS`).

## 2. Cíl

1. **Volba zavěšení `hang`** (hned za `motif`): `chain` = očko a řetěz (dnešní stav, výchozí), `ribbon` = poutko na stužku. Poutko podle snímku: plochý jazýček nad nejvyšším bodem medaile, ve stejné rovině a tloušťce jako placka, s vodorovnou štěrbinou pro stužku. Míry: štěrbina široká `ribbon_w` + 1 mm (vůle), vysoká 3 mm (stužka do 1 mm tloušťky složená napůl projde); příčka nad štěrbinou 3 mm; jazýček širší než štěrbina o 4 mm na každé straně, horní rohy zaoblené 2 mm; dole se rozšíří do medaile rameny (hull spodní hrany jazýčku s širším pásem uvnitř obrysu, jako krček u očka), aby nevznikl ostrý kout, který praská. Jazýček stojí rovně nad středem jako očko dnes (`mid`, nejvyšší bod obrysu v tom místě).
2. **Pole `ribbon_w` „Šířka stužky“** 10 až 40 mm, krok 1, výchozí 20 (běžné stužky na medaile 20, 22 a 25 mm, šňůrka 10 až 15), jen při `hang = ribbon` (`WHEN`). Při `ribbon` schovat `eye_hole`, `eye_wall` a `links`; řetěz se nestaví (jako `links = 0`), `beside` None, medaile se posuzuje jen podle své velikosti s jazýčkem.
3. **Štěrbina** průchozí celou tloušťkou, vyříznutá i z motivu a lemu (stejná cesta jako `hole2d` z vrstev). Lem sleduje obrys medaile bez jazýčku (jako dnes bez kroužku). Lícem dolů i nahoru stejně; `_soft_plate` zaoblí i jazýček, to je v pořádku. `motif_size` dál počítá s kruhem uvnitř obrysu bez jazýčku (`medal2d`).
4. **Chip volby `hang` s ikonou**: zobecni `_choice.blade.php` z `body` na volbu s vlastní sadou ikon (třeba `ParametricGenerator::CHOICE_ICONS = ['body' => SHAPE_ICONS, 'hang' => ['chain', 'ribbon']]`), ikony `hang-chain` a `hang-ribbon` do `partials/icons.blade.php` (session G tam má `shape-*`, session 3 `view-*`; přidej za ně, nepřeskládávej). Text zůstává v `title` a `aria-label`.
5. **Texty přes lang** (`lang/src/param.json`, `tools.json`, `order_part_labels.json` + `python scripts/lang_add.py …`, cs/en/es): `f.hang`, `o.chain`, `o.ribbon` (klíč `o.<hodnota>` je společný všem volbám, proto hodnoty `chain`/`ribbon`, ne `eyelet`), `f.ribbon_w` s nápovědou, `medallion.lead` a `medallion.tip` („s řetězem nebo s poutkem na stužku“), `tools.php` `medallion.title`/`hint`. Název dílu `body`: bez řetězu je to jen „Medaile“; dnes je název jeden na druh a díl, takže buď název podle parametrů (`hang`/`links`) v `partLabel` obou stran, nebo neutrální „Medaile“ pro obě varianty (řetěz zmíní popis). Vyber, co je menší zásah do session 3 mechanismu, a napiš proč. Stránka nástroje pro vyhledávače (AP.md, `heading`) jen pokud se název nástroje mění.
6. **Předvolby** `place1` až `place3` zůstávají s řetězem (uložené návrhy beze změny, `hang` výchozí `chain`). Čtvrtá předvolba „na stužku“ jen když nic nerozbije (`ToolPageTest` staví každou).

## 3. Testy

- `ShapeToolsTest` (medaile řádky 582 a 683): nový test `hang = ribbon`, `ribbon_w` 25: štěrbina 26 × 3 mm průchozí celou tloušťkou (test trojúhelníku ve štěrbině jako u trubky brčka, P.md §9a), příčka 3 mm, jazýček 34 mm, žádný řetěz, `notes["ribbon"] = {w, slot, bar}`, lem nevstupuje do jazýčku, obálka motivu stejná jako s řetězem, lícem dolů totéž otočené; varianta `chain` dává stejný model jako dnes (objem, rozměry).
- `ToolPageTest` (předvolby a výchozí stav), `ParametricToolsTest`, `FarmOnePlateTest` beze změny; `vendor/bin/pint --test`; `npx tsc --noEmit -p tsconfig.json` a `npm run build` (chipy, ikony). Snímky `docs/img/medal-ribbon.png` (náhled s poutkem) a chipy volby na 390 a 1280 px.
- Co ověří až tisk: příčka 3 × 4,4 mm drží tah za stužku, štěrbina 3 mm stačí pro stužku 1 mm.

## 4. Kde a jak

- Session 3, worktree `C:\matplace-edit-wt`, větev `feature/medal-ribbon` z main (c95f597e nebo novější). #58 (barvy lícem dolů, session G) už je v main: čísla řádků v §1 jsou z main před ním a mohou být o pár řádků posunutá, souběžná práce v `shape_kinds.py` teď žádná není. Drž změny v medailové části (definice `eyelet`, očko, lem `border`, řetěz) a nepřesouvej cizí řádky. `tools/_choice.blade.php` a ikony `shape-*` dělala session G (P.md §10), při nejasnosti se jí zeptej zprávou.
- Soubory: `engines/python/shape_kinds.py`, `app/Domain/Tools/ParametricGenerator.php` (FIELDS, CHOICES, WHEN, CHOICE_ICONS), `resources/views/tools/_choice.blade.php`, `resources/views/partials/icons.blade.php`, lang přes `lang/src/*.json`, `resources/js/calc/parts.ts` a `OrderService::partLabel` jen kvůli názvu dílu.
- Zápis: `docs/P.md` §8e („Poutko na stužku“). Texty jen přes lang soubory, `git add` jmenovitě, žádný stash. Nic nenasazuj. Po pushi zpráva řídící session: větev, commit, co je ověřené, snímky.
