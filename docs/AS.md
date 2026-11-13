# AS: Vybarvit model (jedna dílna, krok 2)

Větev `feature/dilna-vybarvovani` z `origin/main` 7dc7f357, 13. 11. 2026, worktree `C:\matplace-plate-wt`. Zadání
`zadani-dilna-vybarvovani.md` (koordinátor podle Romanova rozhodnutí „hned i štětec“), koncepce
`koncepce-jedna-dilna.md` §5 a §9. Nic nenasazeno, nic nevytištěno.

## 1. Co vzniklo

Nástroj **Vybarvit model** na `/tools/paint` (cs/en/es, v katalogu mezi nástroji „Mám soubor“). Zákazník nahraje
model (STL, 3MF, OBJ, STEP…) nebo přijde s hotovým souborem (`/tools/paint?from=<uuid>`) a v prohlížeči určí, která
část má kterou barvu:

| způsob | co dělá | stav |
|---|---|---|
| **Vylít** | ťuknutí obarví souvislou plochu až k hraně (úhel 5–90°, výchozí 30°) nebo celý díl; zastaví se i o jinou barvu | hotovo |
| **Podle výšky** | od zvolené výšky nahoru jiná barva (až 8 změn); platí pro to, co není vybarvené ručně; hranice je přesná rovina | hotovo |
| **Štětec** | tah po povrchu, velikost 0,5 mm až šestina modelu; guma je týž štětec s barvou modelu | hotovo |
| zpět / znovu | každý krok (vylití, tah, změna barvy, výšky, odebrání barvy), Ctrl+Z, tlačítka u barev i v hlavičce | hotovo |
| odhad výměn | pod modelem průběžně „asi 593 výměn filamentu, tisk delší o 21 h 45 min“ (vrstvy 0,2 mm, 2,2 min na výměnu) | hotovo |

Snímky (`docs/img/paint/`, headless Chrome, místní server): `phone-390-vase.webp` a `desk-1280-vase.webp` (váza
z nástroje: vnější plocha vylitá černě, tah štětcem, červeně od 60 mm), `phone-390-colours.webp` (okno filamentů),
`phone-390-panel.webp` (barvy a výšky), `phone-390-calculation.webp` a `desk-1280-calculation.webp` (kalkulace:
„Soubor má 3 namalované barvy“, dvě ceny, „Upravit vybarvení“), `desk-1280-reopened.webp` (vybarvený soubor otevřený
znovu), `desk-1280-cube.webp`, `desk-1280-million.webp` (milion trojúhelníků, štětec vypnut s větou),
`phone-390-en.webp` (anglicky); `facts.json` nese čísla všech průchodů.

Barvy se vybírají z **filamentů farmy** (jedna dlaždice na barvu a povrch, cívky založené v tiskárně první;
`ModelPainter::tiles`), bez katalogu farmy ze 16 základních barev. Počet barev omezuje `ColorLimit::farm()` (kolik
cívek drží největší měnič); stránka píše „Můžete přidat ještě N barev“ a víc přidat nedovolí.

Výstup je **namalovaný 3MF** (`paint_color` na trojúhelnících, jako z Bambu Studia, OrcaSliceru a Anycubic Sliceru
Next). Od té chvíle je to pro web totéž co nahraný namalovaný 3MF (AL.md §14): barvy se přečtou zpět, kalkulace ukáže
„Barevně od X · Jednobarevně Y“ a tlačítko „Vytisknout barevně“, zakázka jde po barvách s nejbližšími cívkami jednoho
stroje a farma řeže namalovaný soubor sám na jednu podložku. **Farma ani řezání se neměnily.** Soubor jde stáhnout
(`/api/tools/paint/<uuid>/colors.3mf`).

Vstup z jiných míst: `GET /api/files/<uuid>` nese u každého hotového souboru `paint_url` (null, když je nástroj
vypnutý); kalkulace ukazuje pod náhledem „Vybarvit model“, u už vybarveného „Upravit vybarvení“. Editor
vygenerovaného návrhu (`feature/home-create-direct`) může použít totéž: odkaz na `paint_url`, nebo
`route('tools.paint', ['from' => $uuid])`. Vybarvený soubor otevřený znovu naběhne se svými barvami (rozsahy
`groups` seřazeného STL), stejně jako 3MF namalovaný ve sliceru.

## 2. Soubory

| | |
|---|---|
| `engines/python/paint_tool.py` | zápis vybarvení + STL modelu → namalovaný 3MF; řez podle výšky, sloučení skvrn, kontrola povrchu; `sample` (zkušební těleso) |
| `app/Domain/Tools/ModelPainter.php` | meze, dlaždice barev, kontrola zápisu, založení souboru, volání enginu, `report`, `urlFor` |
| `app/Jobs/PaintModel.php` | fronta: engine, pak běžné `ProcessModelFile` |
| `app/Http/Controllers/Api/PaintApiController.php` | `POST /api/files/{uuid}/paint`, `GET /api/tools/paint/{uuid}/colors.3mf` |
| `app/Http/Controllers/PaintToolController.php`, `resources/views/tools/paint.blade.php` | stránka (rozšiřuje `tools.page`) |
| `resources/js/calc/paint_mesh.ts` | síť: svaření, sousedé, vylití, štětec se zjemněním, kroky zpět, zápis; bez three.js a bez DOM |
| `resources/js/calc/paint.ts` | modul stránky: kreslení (vlastní síť v prohlížeči stránky), výběr ťuknutím, nástroje, barvy, výšky, uložení |
| `scripts/check_paint_mesh.mjs` | testy logiky sítě (součást `npm run build`), `--bench` měření |
| `scripts/paint_walk.mjs` | průchod v headless Chrome (390 a 1280 px), snímky, čísla; `--card` obrázek karty |
| `tests/Feature/PaintToolTest.php`, `tests/fixtures/paint/cube-stroke-*.bin` | PHP testy; zápis tahu nakresleného ve skutečném prohlížeči |
| `lang/src/paint.json`, `lang/<loc>/tools_seo/paint.php`, `public/img/tools/paint-*` | texty, obsah stránky, karta |

Sdílené soubory, malé přídavky: `config/tools.php` (řádek `paint`), `config/engines.php` (blok `paint`),
`routes/web.php` (3 cesty), `viewer.ts` (`Viewer.host()`), `tool_page.ts` (modul), `calculator.ts` +
`calculator/index.blade.php` (odkaz), `api.ts` (`paint_url`), `partials/icons.blade.php` (3 ikony),
`UploadController::describe` (`paint`, `paint_url`), `PaintedColors` (`mergeBelow`), `ModelFile::printHints`
(vybarvený model se tiskne jako ten, přes který se malovalo), `package.json` (kontrola v `build`).

## 3. Zápis vybarvení

Jeden zápis nad STL modelu, verze 1. Klient ho posílá na `POST /api/files/{uuid}/paint` (multipart), server ho drží
u výsledného souboru: `tool_params.paint` a soubory v `files/<uuid>/paint/`.

```
paint: {
  v: 1,
  source:   uuid modelu, přes který se malovalo (jeho model.stl určuje pořadí trojúhelníků)
  triangles počet trojúhelníků toho STL (jiný počet = zápis patří k jinému souboru → source_changed)
  palette:  ["#rrggbb", …]      stav trojúhelníku = index do palety; 0 = nevybarveno, barva modelu
  heights:  [{z, color}]        z v mm nad nejnižším bodem; co má stav 0, bere barvu nejvyššího pásma pod sebou
  states:   paint/states.bin    bajt na trojúhelník zdroje; 255 = štětec ho zjemnil, nahrazují ho trojúhelníky z added
  added:    paint/added.bin     zjemněné trojúhelníky, 9 × float32 každý; paint/added-states.bin bajt na každý
  report:   co z toho engine udělal: barvy s podíly, sloučené skvrny, počty
}
```

Vylití a štětec jsou v zápisu totéž: stav trojúhelníku. Liší se jen tím, že štětec síť nejdřív zjemní. Pořadí
platnosti: ruční barva (stav ≠ 0) > pásmo výšky > barva modelu.

**Jako část společného projektu** (koncepce §3, „barvy: po dílech, vylitím, podle výšky, štětcem“) navrhuji tento
tvar; postavené jsou `palette`, `surface` a `heights`, `parts` je návrh:

```
project.colors = {
  v, palette,
  parts:   [{name, color}]              po dílech: barva celého dílu (dnes tool_params.part_colors); stav 0 dílu = tato barva
  surface: {source, states, added}      vylitím a štětcem: stav na trojúhelník nad sítí sestaveného modelu
  heights: [{z, color}]                 podle výšky
}
```

Projekt se sestaví do modelu (díly, zdobení) a `surface` se vztahuje k síti toho sestavení; proto zápis nese
`triangles` a server ho odmítne, když model mezitím změnil síť. Až bude projekt umět model přestavět, bude potřeba
vybarvení přenést na novou síť (promítnutím podle polohy); to tato větev neřeší.

Proč binární stavy a ne JSON: milion trojúhelníků je megabajt, ne desítky megabajtů textu.

## 4. Štětec: jak a proč takhle

Zadání: „jemná kresba potřebuje zjemnit síť v místě tahu (nebo jiný poctivý postup – rozhodni, změř a zdůvodni)“.
Zvažoval jsem dvě cesty:

- **Stromy dělení v `paint_color`** (jak to dělají slicery): síť se nemění, každý trojúhelník nese strom svého
  rozdělení. Náš import ale u rozděleného trojúhelníku bere barvu větší části (AL.md §14, `colors_tool.py`), takže
  náhled v kalkulaci a na zakázce, podíly barev, odhad výměn i díly po barvách by tah na hrubé síti (krychle má 12
  trojúhelníků) vůbec neviděly. Náhled by se lišil od tisku.
- **Skutečné zjemnění sítě** (zvoleno): štětec dělí hrany, vždy oba trojúhelníky hrany najednou. Síť proto nikdy
  nedostane T-spoj a zůstává tímtéž povrchem; každý trojúhelník má jednu barvu. Výsledný 3MF nese jen celé
  trojúhelníky (kódy `4`, `8`, `0C`… jako zkušební kotouče a želva), takže **náhled = cena = tisk** všude, kde web
  barvy už čte, a nic nezávisí na tom, jak slicer vykládá stromy dělení.

Postup jednoho tahu (`paint_mesh.ts`, `strokeStart` / `strokeDab` / `strokeEnd`):

1. Tah je pás: vše na povrchu do vzdálenosti poloměru od čáry středů jednotlivých otisků (spojené otisky dávají
   rovné boky, ne vroubky).
2. Trojúhelník celý uvnitř se obarví, jak je. Trojúhelník, který protíná okraj, se půlí přes nejdelší hranu
   (Rivara: když ta hrana není nejdelší i pro souseda, nejdřív se rozdělí soused; jinak vznikají jehly), dokud je
   jeho nejdelší hrana delší než `edge` = 0,4 × poloměr, nejméně 0,4 mm (tryska), nejvýš 2 mm.
3. Pak se rozřízne **přesně tam, kde okraj protíná jeho hrany** (bod se hledá půlením intervalu). Okraj tahu je tedy
   lomená čára přes body ležící na okraji, ne zubatina celých trojúhelníků.
4. Dvě pravidla brání tomu, aby tah zjemňoval vlastní vnitřek: trojúhelník, který barvu už má, se nedělí; malý
   trojúhelník, který z tahu vyčnívá jen dopředu, čeká na další otisky (ty ho spolknou celý, nebo ho potkají bokem).
   Co čeká, se zkouší u každého dalšího otisku znovu a na konci tahu se dořeší.
5. Roh trojúhelníku se počítá za „uvnitř“ nebo „venku“, jen když je to o víc než vlásek (0,002–0,02 mm). Bez toho
   se okraje dvou otisků, které se skoro kryjí, řezaly navzájem do nekonečna: jeden tah na telefonu vyrobil
   400 000 trojúhelníků bez plochy (nález z průchodu, opraveno, hlídá to test „wavy stroke“).

Měřeno (`node scripts/check_paint_mesh.mjs --bench`, koule Ø 60 mm, tah 54 mm štětcem 2 mm, hrana 0,4 mm):

| postup | přidané trojúhelníky (model 50 tis.) |
|---|---|
| každý otisk zjemní celý svůj okraj, trojúhelník jde podle svého středu | 22 020 |
| + pravidla z bodu 4 | 5 380 |
| + přesný řez okraje (bod 3) a rovné boky; okraj hladký | 7 400 |

Na stránce je hrana 0,4 × poloměr, takže tentýž tah štětcem 2 mm přidá asi 1 500 trojúhelníků (viz §6). Zpět a
znovu vrací jen barvy; zjemnění v síti zůstává (tvar se jím nemění). Strop: štětec smí přidat 400 000 trojúhelníků
(`engines.paint.max_added`), pak maluje hruběji a stránka to řekne.

Server zjemněnou síť **nepřebírá naslepo**: musí ležet v obalu modelu a mít stejnou plochu (do 0,1 %), jinak soubor
skončí s `mesh_changed`. Test s tahem ze skutečného prohlížeče ověřuje, že výsledek je vodotěsná krychle o objemu
1000 mm³.

## 5. Tisknutelnost a poctivost

- Na stránce je rámeček **„Co vícebarevný tisk znamená“**: výměna filamentu v každé vrstvě, kde se potkají dvě
  barvy (asi dvě minuty), čisticí věž a plast navíc, barvy nad sebou jsou nejlevnější, tryska 0,4 mm.
- **Odhad výměn při malování** (`PaintMesh.colourChanges`): stavový řádek pod modelem říká po každém kroku, kolik
  výměn filamentu vybarvení znamená a o kolik se tisk prodlouží (za každou barvu navíc ve vrstvě 0,2 mm jedna
  výměna, `engines.colors.change_minutes` na výměnu: stejný počet, z jakého kalkulace počítá cenu „barevně“; barvy
  nad sebou jedna výměna na hranici). Váza 120 mm s vnějškem jinou barvou než vnitřkem: 593 výměn, +21 h 45 min,
  kalkulace pak „Barevně od 1 200 Kč · Jednobarevně 180 Kč“. Zákazník to vidí dřív, než klikne na cenu.
- **Drobné skvrny**: souvislá plocha jedné barvy menší než 0,5 mm² (`engines.paint.speck_mm2`) se při zápisu sloučí
  s barvou, se kterou má nejdelší hranici; stránka po uložení napíše, kolik jich bylo, a na cenu pustí až druhým
  kliknutím. Když po sloučení zbude jediná barva, soubor nevznikne (`one_color`) a stránka řekne proč.
- **Malá barva schválně**: import namalovaného 3MF slučuje barvy pod 1 % plochy s tělesem (želva). U modelu
  vybarveného tady se to vypíná (`PaintedColors::mergeBelow`, origin `paint`): oči figurky jsou pod 1 % a zákazník je
  namaloval úmyslně. Hlídá to test (trojúhelník 2 mm² z 3 750 mm² zůstane barvou).
- Cena „barevně“ v kalkulaci je odhad z počtu vrstev se dvěma a více barvami (AL.md §14.5); skutečnou dá řez zakázky.

## 6. Měření

Romanovo PC, headless Chrome s grafikou AMD Radeon (ANGLE D3D11), `scripts/paint_walk.mjs`; souběžně běžely sady
testů jiných sessions, čísla jsou spíš horní. Model = hrbolatá koule Ø 60 mm (`paint_tool.py sample`).

| trojúhelníků | otevření celkem (nahrání, zpracování, načtení) | z toho v prohlížeči: stažení STL / svaření a sousedé / buffery | ťuknutí „vylít“ na celé těleso | tah štětcem (25 bodů) | snímek |
|---|---|---|---|---|---|
| 12 (krychle) | 3,4–4,9 s | 0,35–0,41 s / 1 ms / 1 ms | 49–123 ms | 0,38–0,47 s, +1 600 až 2 800 | 4,7–5,0 ms |
| 49 728 | 4,0–4,8 s | 0,37–0,48 s / 34–41 ms / 7 ms | 75–161 ms | 0,39–0,45 s, +1 300 až 1 700 | 4,8–5,0 ms |
| 198 468 | 5,5–6,4 s | 0,38–0,46 s / 125–183 ms / 15 ms | 113–194 ms | 0,43–0,44 s, +1 500 až 1 900 | 4,7–4,9 ms |
| 478 170 | 8,6 s | 0,47 s / 310 ms / 22 ms | 241 ms | 0,45 s, +2 800 | 4,8 ms |
| 998 000 | 25,4–27,9 s | 0,62–0,70 s / 0,65–0,83 s / 38–47 ms | 405–542 ms | štětec vypnut | 4,8–5,0 ms |

(„Ťuknutí“ je celé: nový obrázek čísel trojúhelníků, vylití, přepočet ploch, překreslení. „Tah“ je 25 bodů po 16 ms
bez těch čekání, tedy výběr místa a zjemnění pro celý tah. „Snímek“ je odstup dvou snímků stránky s modelem na
obrazovce; grafika tohoto PC milion trojúhelníků stíhá, o telefonu to neříká nic. Šířka 390 px je týž prohlížeč
s dotykem, ne telefon.) Uložení od kliknutí po hotový soubor s místním serverem (fronta sync): 7–10 s u 50 tis.,
15 s u 200 tis., 27 s u 480 tis., 50–53 s u milionu.

Samotná logika bez grafiky (`--bench`, Node): svaření a sousedé 1 M trojúhelníků 0,55–1,05 s, vylití všeho
0,11–0,20 s, tah 60 otisků 0,36–0,78 s.

Server (PHPUnit v jednom procesu, fronta sync; na produkci to dělá worker):

| trojúhelníků | úloha vybarvení celkem | `paint_tool.py` | 3MF → STL | čtení barev | rozbor sítě | 3MF |
|---|---|---|---|---|---|---|
| 49 728 | 6,1 s | 2,7 s | 0,5 s | 2,5 s | 0,4 s | 0,8 MB |
| 198 468 | 13,4 s | 4,3 s | 2,4 s | 5,0 s | 1,7 s | 3,2 MB |
| 998 000 | 58,9 s | 21,5 s | 13,1 s | 19,6 s | 4,5 s | 16,4 MB |

**Meze** (`config/engines.php`, blok `paint`; stránka je zákazníkovi říká větou):

| | |
|---|---|
| model nad 1 000 000 trojúhelníků, na dotykovém zařízení nad 500 000 | neotevře se: „Model má N trojúhelníků, nástroj jich zvládne nejvýš …. Zjednodušte ho…“; binární STL se pozná z hlavičky ještě před nahráním |
| štětec na počítači do 500 000, na dotykovém zařízení do 200 000 | nad tím jen vylití a výšky: „Štětec na tomto zařízení pracuje do …; vylití plochy a barvy podle výšky fungují dál.“ |
| štětec přidá nejvýš 400 000 trojúhelníků | pak kreslí hruběji a řekne to |

Mez pro telefon je odhad z měření na počítači (telefon je 3–5× pomalejší a má méně paměti); na skutečném telefonu
neověřeno. Zjednodušení sítě přímo v nástroji jsem nestavěl (zadání říká „zjednodušení nebo jen vylití“); šlo by
přes `MeshDecimator`, který farma už má.

## 7. Řezy skutečnou Orcou

`PaintToolTest::test_a_coloured_model_slices_on_one_plate_with_the_real_orca` (běží jen s `ORCA_LOCAL`, čísla do
`PAINT_SAMPLES`): krychle 10 mm s tahem ze skutečného prohlížeče (jedna strana vylitá černě, červený tah přes dvě
strany, od 6,3 mm nahoru červeně), zakázka barevně ve trojnásobné velikosti (30 mm), profily farmy pro Kobru S1, tři
cívky jednoho stroje s agentem:

| | Orca 2.4 | Orca 2.5 |
|---|---|---|
| stav | `sliced`, jedna podložka, `one_plate_project` = painted | totéž |
| filamenty | 7,1 + 6,9 + 5,4 g = 19,3 g | 7,2 + 6,9 + 5,5 g = 19,6 g |
| výměny | 198 (T0 72×, T1 53×, T2 74×) ve 150 vrstvách | 198 |
| čas | 458 min | 468 min |
| cena (místní ceník) | 420 Kč | 430 Kč |
| řez / celá příprava zakázky | 2,3 s / 5,7 s | 5,7 s |

Tatáž krychle 30 mm třemi způsoby (Orca 2.4, tentýž test):

| | výměny | čas | filament | cena (místní ceník) |
|---|---|---|---|---|
| jednobarevně (`colors=one`) | 0 | 23 min | 9,8 g | 120 Kč |
| dvě barvy nad sebou (jen podle výšky, od 5,1 mm × 3) | 1 | 30 min | 11,3 g | 120 Kč |
| tři barvy vedle sebe (vylitá strana, tah štětcem, výška) | 198 | 458 min | 19,3 g | 420 Kč |

Barvy nad sebou stojí sedm minut a gram a půl navíc; barvy vedle sebe po celé výšce dvacetkrát delší tisk. To je
důvod rámečku z §5 a věty „Nejlevnější jsou barvy nad sebou“.

## 8. Testy

- `tests/Feature/PaintToolTest.php` (7 testů): vylití + výška → 3MF, barvy přečtené zpět, podíly, řez sítě ve
  výšce, zápis u souboru, stažení, dvě ceny; malá barva zůstává a skvrna pod trysku se sloučí; zápis, který
  k modelu nepatří, je odmítnut a rozbitý skončí s důvodem; tah ze skutečného prohlížeče nechá krychli vodotěsnou;
  zakázka po barvách na jedné podložce (falešný slicer); stránka ve třech jazycích s barvami farmy, katalog,
  vypnutí v `/admin/tools`; skutečná Orca volitelně.
- `scripts/check_paint_mesh.mjs`: svaření, sousedé, vylití podle úhlu a o jinou barvu, kotouč štětce do 1 % plochy,
  žádný T-spoj (`sound()`), stejná plocha po zjemnění, zpět a znovu i přes zjemněné trojúhelníky, strop
  trojúhelníků, vlnitý tah bez trojúhelníků bez plochy, pás s rovnými boky do 0,5 %, počet výměn filamentu
  (barvy nad sebou 1, strana jinou barvou 50 na 50 vrstev).
- `pint --dirty`, `npx tsc --noEmit -p .`, `npm run build`.

## 9. Co nasazení nese

- Žádná migrace. Nové soubory enginu potřebují jen numpy a trimesh (na serveru jsou).
- Nástroj je v `config/tools.php` zapnutý (`available: true`): po nasazení je v katalogu, v sitemapě a kalkulace
  ukazuje odkaz „Vybarvit model“ u každého hotového souboru. Vypnout jde v `/admin/tools` (stránka, API i odkaz
  zmizí).
- Nová úloha fronty `PaintModel` (fronta `interactive`): workery je potřeba po nasazení restartovat jako obvykle.
- Požadavek uložení může nést až asi 29 MB (zjemněné trojúhelníky); limit nahrávání webu je 120 MB.
- `npm run build` nově spouští `scripts/check_paint_mesh.mjs` (pár vteřin).
- Volitelné proměnné: `PAINT_MAX_TRIANGLES`, `PAINT_MAX_TRIANGLES_PHONE`, `PAINT_MAX_ADDED`,
  `PAINT_BRUSH_TRIANGLES`, `PAINT_BRUSH_TRIANGLES_PHONE`, `PAINT_SPECK_MM2`.
- Vybarvený soubor má `origin = paint` (ne `tool`): v „Mých modelech“ je vidět ve výpisu všech, filtr podle původu
  (nahrané / vytvořené / z nástroje) ho neukáže pod žádnou ze tří voleb.

Místní zkoušení v prohlížeči: `.env` tohoto worktree ukazuje na `database/dev.sqlite` se schématem z 15. 10., které
se nesmí migrovat. Průchody proto běžely nad odkladištním souborem mimo repozitář: schéma, které si PHPUnit postaví
v paměti (se seedery farmy), zapsané příkazem SQLite `VACUUM INTO`; žádné `artisan migrate` nad žádným souborem.
Server: `php -S 127.0.0.1:8061` s `DB_DATABASE` na ten soubor (přes `artisan serve` Python serveru nenašel numpy).

## 10. Neověřeno a otevřené otázky

Neověřeno:

- **Skutečný tisk.** Nic nevytištěno; řezy jsou jen místní Orca CLI.
- **Skutečný telefon.** Průchod je headless Chrome s dotykem a šířkou 390 px na počítači s jeho grafikou. Výkon,
  paměť (milion trojúhelníků), Safari na iOS a ovládání prstem na skutečném přístroji jsem nezkoušel.
- Stažený 3MF v Bambu Studiu, Orce (okno) a Anycubic Sliceru Next jsem neotvíral; Orca CLI ho čte (přes `repaint`).
  PrusaSlicer `paint_color` nečte, proto ho text u stažení nejmenuje.
- Řez velkého vybarveného modelu (stovky tisíc trojúhelníků s tahy) skutečnou Orcou a čas úlohy na produkčním
  serveru.
- Jiné prohlížeče než Chrome.

Otevřené otázky pro Romana a koordinátora:

1. **Jen podle výšky = výměna ve vrstvě?** Model obarvený pouze výškami jde stejnou cestou jako malovaný 3MF
   (tiskárna s měničem, čisticí věž): v řezu vyšla jedna výměna, +7 min a +1,5 g proti jedné barvě (§7). Návrhy
   z nástrojů mají pro barvy nad sebou vlastní cestu (`color_changes`, výměna ve výšce bez věže). Sjednotit, tedy
   zápis jen s `heights` posílat tou cestou? Dnes to není nutné, jen by to ušetřilo věž.
2. **Zjednodušení v nástroji** pro modely nad meze (dnes jen věta a vylití).
3. **Viditelnost po nasazení:** hned veřejně, nebo nejdřív vypnuto v `/admin/tools` a zkouška na produkci?
4. **Cena barevného tisku.** 198 výměn u krychle 30 mm dá 420 Kč místního ceníku; produkční ceník a případný
   příplatek za výměnu jsou Romanovo rozhodnutí (AL.md §14.7).
5. Vybarvení se drží jen na stránce: po znovunačtení stránky před uložením je pryč (po uložení se adresa přepne na
   vybarvený soubor a ten se otevře s barvami). Ukládat rozpracované do prohlížeče?
