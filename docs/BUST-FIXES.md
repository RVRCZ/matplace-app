# Busta z fotky: oprava řezu hrudi a soklu

Větev `feature/bust-fixes` (z `main` 7ddf958), 4. 10. 2026. Zadání: `docs/prompts/bust-fixes.md`.
Změny jsou jen v `engines/python/mesh_tool.py`, v testu `tests/Feature/FigureToolTest.php` a ve fixtuře
`tests/Support/MeshFixtures.php`. PHP kód (`GenerateModel`, `PedestalChanger`) se nemění.

## Vzorek chyby

Zakázka `3591a079-17d0-41f1-b1fd-59ae75df022a` (3. 10. 2026, 05:53, busta z fotky, styl „socle“, 80 mm; vytištěná
ve 175 % = 83 × 63 × 140 mm). Lokální reprodukce z `files/<uuid>/source.stl` dává stejný výsledek jako server
(283 404 vs. 283 556 trojúhelníků; `mesh_tool.py` na serveru je totožný s `main`).

| před | po |
|---|---|
| ![před, zepředu](img/bust-fixes-before-front.png) | ![po, zepředu](img/bust-fixes-after-front.png) |
| ![před, z boku](img/bust-fixes-before-side.png) | ![po, z boku](img/bust-fixes-after-side.png) |
| ![před, řez středem](img/bust-fixes-before-section.png) | ![po, řez středem](img/bust-fixes-after-section.png) |

Řezy jsou svislé roviny osou nožky (y = hloubka, z = výška, mm; vlevo je přední strana busty).

## Co bylo špatně

1. **Límec soklu vyčuhoval.** `bust_shape("socle")` řezal hruď jednou šikmou plochou (oblouk zdola, celý skloněný
   o 8° dozadu kolem přední hrany) a vršek nožky („seat“) počítal tak, aby *horní hrana* límce byla v těle u zadního
   okraje límce. Svislá stěna límce (4,5 mm) a jeho 45° rozšíření (2,2 mm) tím zůstaly pod řeznou plochou vepředu
   a po stranách, kde plocha klesá – zepředu bylo vidět ≈ 5 mm límce (ve 175 % tisku 9 mm), mezi šikmou plochou
   a svislou stěnou límce vznikaly klíny/„díry“. Řez `img/bust-fixes-before-section.png`: šikmá čára spodku hrudi
   protíná modrý obdélník límce.
2. **Roztřepená spodní hrana hrudi.** `seat_of` vrátil z = 0,24 mm nad spodkem zdrojové sítě a řez (`trim_by_plane`
   + `arc_cut`) začínal přesně tam. Spodních ≈ 4 mm zdroje je ale roztrhaný okraj generátoru uzavřený voxelovým
   rebuildem (`img/bust-fixes-source-bottom.png`): obvod průřezu klesá z 278 mm (z = 0) na 216 mm (z = 4) při stejné
   ploše, tj. obrys je plný zářezů a třísek. Přední spodní hrana hotové busty byla tedy prakticky neořezaný roztrhaný
   okraj, po stranách oblouk stoupal a hranu minul.

Oba jevy se týkají jen zdrojů, kde generátor vrátí tělo až k loktům s otevřeným spodkem (u vzorků z 22. 9. a 30. 9.
je spodek hladký a nic se na nich nemění).

## Co se změnilo (`mesh_tool.py`)

- **`clean_level(m, z_from, target)`** – nová „zdravá vrstva“. Po 0,25 mm se řeže vodorovný průřez a porovnává se
  s vlastní vyhlazenou verzí (morfologické otevření a uzavření poloměrem 1 mm, tj. zářezy a třísky užší než 2 mm):
  `rough = (plocha_uzavřeného − plocha_otevřeného) / obvod` je střední hloubka drsnosti v mm, `spike` totéž jen pro
  třísky. Řez jde 0,5 mm nad nejnižší vrstvu, od níž jsou 2 mm vrstev hladké (`rough ≤ 0,06`, `spike ≤ 0,03`,
  rozměry pro 80mm model, škálují se s `target`). Strop 8 % výšky; když se do stropu hladká vrstva nenajde, model se
  nechá být (je detailní, ne roztrhaný). Změřeno na vzorcích: chybový vzorek 0,19 → 0,10 → 0,03 od z = 4,05 (řez
  zvednut o 4,05 mm); čisté vzorky 0,02–0,04 od spodku (beze změny).
  Zvednutý řez platí pro všechny podstavce: u `socle/antique/cut` je to rovina řezu hrudi, u `round/square/…`
  se tělo ořízne také (roztrhané milimetry by jinak koukaly nad okrajem podstavce) a podstavec sedí o to výš.
  Report: `cut_raised` (mm).
- **`arc_cut(body, m, cx, z_cut, rise, tilt, flat_half, tilt_from)`** – oblouk má uprostřed rovnou plošku
  (`flat_half = r_límce + 2 mm` na každou stranu) a teprve od ní stoupá k ramenům; sklon dozadu (8°) začíná až za
  límcem (`tilt_from = y_nožky + r_límce + 2 mm`), vepředu je řez vodorovný. Pod celým límcem je tedy rovná plocha
  v z = `cut_z`.
- **`bust_shape("socle")`** – límec leží celý v hrudi: `room_for()` spočítá skutečné průřezy hrudi ve třech výškách
  (spodek rozšíření 0,3 mm nad řezem, polovina, vršek límce = `cut_z + 0,3 + flare + collar_h + overlap`), každý
  zmenší o `r_límce + 1,5 mm` a protne. Nožka stojí v nejbližším bodě této oblasti k těžišti průřezu hrudi (hlídá se
  jen, aby těžiště busty nebylo víc než 0,45 r_nohy za osou nožky). Oblouk se po posunu nožky přepočítá (až 3 průchody).
  Když se límec nevejde: nejdřív bez rozšíření (jen `r_top`, rezerva 1,5 mm), pak rezerva 0,5 mm, a když ani to,
  `ValueError("collar_does_not_fit")` → stávající záloha na „round“ (`socle_fallback`).
  Pevné rozhodnutí: límec je skrytý *celý* (i 45° rozšíření), zvenku vidět jen krček `r_top` vcházející kolmo do
  rovné plochy. Viditelná výška nohy = `foot − collar_h` (≈ 26 mm u 80mm busty), skrytá část 0,3 + flare + collar_h,
  překryv 2,8 mm nad tím.
- **`socle_mesh`** – profil bere `r_collar`, `flare`, `collar_h` ze `sizes` (bez rozšíření, když je `flare = 0`);
  `pedestal_height` = celá noha po vršek límce bez překryvu.
- **`bust_shape("antique")`** – destička musí ležet v hrudi s rezervou 1,5 mm ve třech výškách (opsaná kružnice
  destičky); jinak se posune pod střed hrudi, jinak zmenší (0,85, 0,7), jinak `ValueError` → „round“. Rovná část
  zaoblení spodku (`round_under`) je středěná na destičku a kryje ji.
- **`round/square/hexagon/column/plaque`** – geometrie beze změny (jen zvednutý řez, viz výše); jejich překryv je vršek
  jednoho válce/hranolu od podložky, nic z něj netrčí – ověřeno na `round` u chybového vzorku.
- **Report** (JSON `normalize`, u `socle`/`antique`, v souřadnicích zapsaného souboru): `cut_z`, `foot_x`, `foot_y`,
  `foot_r`, `collar_r`, `collar_top_z`, `foot_outside_mm3` (objem nohy mimo tělo), `foot_below_cut_mm3` (objem nohy
  pod rovinou řezu). Rovnost obou objemů = nic z límce není vidět. U všech čtyř vzorků je rovnost přesná.

## Ověření na vzorcích (ze serveru, `storage/app/models/files/<uuid>/source.stl`)

| uuid | styl | spodek zdroje | `cut_raised` | noha mimo / pod řezem (mm³) |
|---|---|---|---|---|
| 3591a079 (chyba) | socle | roztrhaný do 4 mm | 4,05 | 7 374,7 / 7 374,7 |
| 3591a079 | antique | – | 4,05 | 4 328,3 / 4 328,3 |
| 3591a079 | cut, round | – | 4,05 | – |
| fdfff703 (30. 9., bunda, ruce) | socle | hladký | 0 | 7 367,9 / 7 367,9 |
| 0c4644a6 (22. 9.) | socle | hladký | 0 | 7 371,4 / 7 371,4 |
| 4eb484d3 (ležící hlava, 49 mm vysoká) | socle | drsný do 6,5 mm (> strop 3,9) | 0 | 4 100,2 / 4 100,2 |
| 3591a079 | socle + jméno „Lucie“ | – | 4,05 | 7 397,4 / 7 397,4 |
| 3591a079 | socle, sink 20 % | čistý po sink-řezu | 0 | 7 363,8 / 7 363,8 |
| fdfff703 | antique (hruď prodloužena o 12,9 mm) | hladký | 0 | 4 328,6 / 4 328,6 |

![vzorek 2 po](img/bust-fixes-sample2-after.png) ![vzorek 3 po](img/bust-fixes-sample3-after.png)
![antique po](img/bust-fixes-after-antique.png)

Vzhledové rozdíly oproti dnešku u dobrých bust: spodek hrudi je uprostřed vodorovný (dřív celý skloněný o 8°),
sklon začíná až za límcem; noha stojí pod těžištěm průřezu hrudi (dřív posunutá dopředu k přední hraně).

## Test

`FigureToolTest::test_torn_bottom_gets_a_flat_cut_and_the_socle_collar_stays_inside_the_chest` – syntetická busta
(`MeshFixtures::tornBustStl`: oválná hruď, krk, hlava, spodní 3 mm náhodně rozedrané zářezy 1,3 mm široké, seed
pevný). Po `normalize … socle` platí: watertight, 1 těleso, `cut_raised > 1,5`, objem nohy mimo tělo = objem pod
řezem (±1 %), vršek límce ≥ 3 mm nad řezem; a nezávisle nad trojúhelníky výsledného STL (čistě v PHP):
pod rovinou řezu (−0,2 mm) jsou jen trojúhelníky do `foot_r + 0,3` od osy nožky (nic z hrudi nevisí dolů, nic
z nohy netrčí do stran); všechny trojúhelníky s normálou dolů do `collar_r + 2` od osy leží v `cut_z ± 0,2`
(rovná řezná plocha). Totéž tělo projde i `antique` a `round`. Bez Pythonu s manifold3d se test přeskočí.

Výklad bodu „žádné trojúhelníky těla pod horní hranou límce vně nožky“ ze zadání: pod rovinou řezu nesmí být nic
než noha (hruď nevisí pod řez, límec netrčí do stran); nad řezem límec nemůže být vidět, protože objem nohy mimo
tělo = objem pod řezem.

## Nasazení

Jen `php artisan optimize` (+ restart fronty/`php-fpm` podle `docs/DEPLOY-BETA.md`); Python se nebuildí, žádné nové
balíčky (manifold3d, trimesh, scipy, scikit-image už na serveru jsou). Starší hotové busty se nemění; zákazník
(nebo my) je opraví přes „Změnit podstavec“ (`POST /api/files/{uuid}/pedestal`), které jede ze `source.stl`
a projde novým kódem.

## Otevřené / rozhodnuto bez dotazu

- Umístění nožky „pod těžiště průřezu“ (zadání) místo dřívějšího posunu dopředu: měřeno od přední hrany hrudi leží
  osa nožky u vzorku 3591a079 o 1,5 mm a u fdfff703 o 3,7 mm dál vzadu než dřív; vzhledově odpovídá bustě ležící
  na soklu a těžiště busty zůstává nad nohou.
- Strop zvednutí řezu 8 % výšky: vzorek 4eb484d3 (drsný do 6,5 mm z 49 mm) se nechává být.
- `trim_loose`, `bust_cut` a `rebuild_solid` se neměnily; příčina roztřepení není v nich, ale v tom, kde se řezalo.

---

# Tři klasické busty: na bloku, na disku, na noze (17. 11. 2026)

Větev `feature/bust-classic` (z `origin/main` 71a8685c). Zadání: Romanův obrázek vytištěné busty („potřebujeme
takovýhle typ busty, podstavec bez jména i s jménem“) a druhý obrázek tří bust vedle sebe („všechny 3 typy přidat,
projdu je pak s ideálními fotkami a pak nějaké skryjeme a nějakou nastavíme jako default“). Obrázky a vzorky:
`Documents\MatPlace\conceprt19092026\busta-klasicka\`, výsledky v podsložce `vysledky\`.

## Co přibylo

| typ (`pedestal`) | na stránce | hruď | noha | jméno |
|---|---|---|---|---|
| `classic` | Busta na bloku | paže seříznuté dvěma rovinami, sklon 13°, rovná spodní plocha | nízký zaoblený čtverec se zkosenou horní hranou, 11 % výšky busty (desetina celku) | vystouplé na přední stěně bloku |
| `classic_round` | Busta na disku | totéž, sklon 20° (hruď dole užší) | nízký kulatý disk se zaoblenou hranou, 8,5 % | ohnuté po přední části obvodu disku |
| `classic_stem` | Busta na noze | totéž se sklonem 10°, zespodu zaoblená do krátké nohy | noha na kulaté patě se zaoblenou hranou, 16 % | ohnuté po obvodu paty |

Hruď a noha jsou v kódu dvě věci: `classic_shape()` udělá hruď a změří spodek, `classic_foot()` postaví nohu
(`stand`: `block`, `disc`, `stem`). Všechno je v `BUST_STYLES` v `engines/python/mesh_tool.py`; staré styly
`socle`, `antique`, `cut` a obyčejné podstavce se nezměnily (kontrola níže).

## Jak se hruď měří (`classic_sides`, `chest_yaw`)

1. **Natočení hrudi.** Generátor vrací bustu pootočenou o libovolný úhel a `face_front` zná jen čtvrtotáčky.
   Hruď s pažemi je zřetelně delší přes ramena než do hloubky, takže hlavní osa jejích řezů (druhé momenty plochy,
   pět řezů ve spodní šestině) je osa ramen. Tělo se o ten úhel srovná (3° až 40°; kulatější obrys než 1,25 : 1
   se nechá být). Vzorek 159 byl pootočený o 32°: bez srovnání přišel o lopatku a druhá paže zůstala.
2. **Šířka.** Mezi rovným řezem a krkem (`neck_level`) se tělo krájí na 25 řezů, z každého se bere jen největší
   obrys (volně visící paže je vlastní obrys). Střed je střed krku. Polovina šířky hrudi s pažemi je medián spodní
   poloviny řezů, každá strana zvlášť (zvednutá paže neposune druhou plochu), obě držené do 10 % od průměru.
3. **Linie ramen** je nejvyšší řez, který má ještě 85 % té šířky; nad ním obrys ubíhá ke krku.
4. **Roviny.** Každá prochází na linii ramen ve vzdálenosti `keep` = 0,78 poloviny šířky od středu a dolů se
   naklání dovnitř o `lean`. U spodního řezu nikdy blíž než 0,55 poloviny šířky (dlouhá hruď). Když nad krkem
   něco přesahuje rovinu (vlasy, kapuce, hlava), řeže se jen pod krkem (`side_top_z`); u pěti vzorků to nenastalo.
5. Krátká hruď se protáhne dolů jako u stylu `cut` (nejvýš o 10 % výšky).

## Noha (`classic_foot`)

- **Blok**: obdélník kolem spodní plochy hrudi, na každé straně o 3,5 % její šířky větší (nejméně o zkosení
  + 1 mm, aby celá spodní plocha ležela na rovné horní ploše; nejvýš po šířku ramen, pokud není spodek sám širší),
  rohy o poloměru 14 % kratší strany, zkosení horní hrany 20 % výšky (nejvýš 3 mm), fazetka 0,4 mm u podložky.
  Blok není vynucený čtverec: kopíruje hloubku hrudi, jinak by z něj vpředu a vzadu byl tác.
- **Disk**: poloměr = nejvzdálenější bod spodní plochy + 1 mm + část zaoblení; zaoblení horní hrany 38 % výšky.
- **Noha na patě**: poloměr nohy 8,5 % výšky busty (nejvýš 0,3 kratšího rozměru spodku), pata 20 %, výkružek mezi
  nimi; hruď se zespodu zaoblí stávajícím `round_under` do výšky nejvýš 26 % výšky busty a nejvýš tří čtvrtin
  cesty k linii ramen. Hruď se pro tenhle typ neprotahuje o čtvrtinu jako u `antique` (u vzorku 158 by z překřížených
  paží vznikl 30 mm vysoký vytažený sloup).
- **Jméno**: jedna řádka verzálkami (`raised_name`, Montserrat), vystouplé 0,7 až 1,2 mm. Na bloku 60 % výšky
  přední stěny a nejvýš 92 % její rovné části; dlouhé jméno se zmenší, za zaoblený roh nikdy nejde. Na disku a patě
  ohnuté po obvodu jako u `socle`, nejvýš 130°. Věnování tyhle typy nemají (blok je na druhou řádku nízký).
- Hruď zasahuje 1 mm do bloku nebo disku, noha 2,5 mm do hrudi: výsledek je jedno uzavřené těleso.

Do hlášení `normalize` přibylo: `side_lean`, `side_left_x`, `side_right_x`, `side_top_z`, `cut_z`, `foot_x`,
`foot_y`, `chest_w_mm`, `chest_d_mm`, `block_w_mm`, `block_d_mm`, `block_h_mm`, `name_cap_mm`, `foot_r`,
`chest_squared` (o kolik stupňů se hruď srovnala). Klíče končící `_mm` se přepočítávají do měřítka výsledného souboru.

## Porovnání s levou bustou z obrázku

`socle` (Moderní busta) i `antique` (Antická busta) nechávají paže a celou šířku ramen; `socle` má hruď seříznutou
do oblouku na noze s pásem, `antique` má zaoblený spodek, ale stojí na tabulce a soklu. Levá busta z obrázku má paže
pryč, hruď zaoblenou do krátké nohy a kulatou patu: siluetou je nejblíž `antique`, ale ani jeden styl jí není, proto
vlastní typ `classic_stem`. Stávající styly jsem neměnil. Na vzorku 158 (překřížené paže) `socle` spadne na obyčejný
kulatý podstavec (`collar_does_not_fit`) a `antique` protáhne hruď o 28 mm do vytaženého sloupu; to je stav před
touto prací, vidět je na listu `porovnani-se-vzorem.png` jen vzorek 156.

## Výběr typů bez kódu: `/admin/ai` → „Typy busty“

`App\Domain\Generation\BustStyles`, uložené ve `farm_settings` pod `ai.bust_styles` jako
`{shown: [...], default: "..."}` (žádná migrace). U každého typu zaškrtávátko (zobrazený / skrytý) a jedna volba
„výchozí“ mezi zobrazenými; `POST /admin/ai/bust-styles`. Čte to:

- stránka busty (`tools/figure.blade.php`): nabídka = zobrazené typy + obyčejné podstavce, zaškrtnutý výchozí,
  u každého zobrazeného typu jedna věta (`figure.pedestal.about.*`);
- validace `pedestal` v `POST /api/generate`: skrytý typ → 422;
- busta bez uvedeného podstavce dostane výchozí typ (dřív `round`); postava dál `round`;
- volná změna podstavce (`POST /api/files/{uuid}/pedestal` a výběr v kalkulačce): skrytý typ nejde zvolit znovu,
  ale model, který ho má, ho v nabídce vidí a může si u něj změnit jméno nebo přejít jinam.

Dokud Roman nic nezmění: zobrazené všechny (pořadí `classic`, `classic_round`, `classic_stem`, `socle`,
`antique`, `cut`), výchozí `socle` jako dosud. Když skryje všechny, busta začíná na kulatém podstavci.

## Ověření

- **Vzorky**: pět surových výstupů generátoru přes celé `normalize` tak, jak ho volá `GenerateModel` pro bustu
  (`clean,pedestal,solid`, `front: auto`, `front_prior: 3`, `cut: bust`, `tidy: true`), ve třech typech, se jménem
  a bez: 30 běhů, obrázky zepředu zleva, zepředu a z boku ve `vysledky\vzorek-<n>-<typ>[-name].png`, přehled
  `prehled-vsech.png`, porovnání s Romanovými obrázky `porovnani-se-vzorem.png`.
- **Staré styly beze změny**: 36 kombinací (fixtury `cubeStl` a `tornBustStl` × `socle`, `antique`, `cut`, `round`,
  `square`, `hexagon`, `column`, `plaque`, `none` × se jménem a bez) vyrobeno starým a novým `mesh_tool.py`,
  sha256 výstupních STL shodné. K tomu čtyři běhy cestou busty (`cut: bust`, `front: auto`, `tidy: true`): `antique`
  a `cut` shodné, `socle` a `round` se liší o setiny procenta objemu, a stejně se liší i dva běhy starého kódu mezi
  sebou (`trim_loose` vzorkuje povrch náhodně), takže tam bajtová shoda neexistuje ani bez mé změny.
- **Testy**: `FigureToolTest` (tvar tří typů na roztřepené bustě: rovné boční plochy, nic za nimi, půdorys bloku
  proti hrudi, jméno jen vpředu a mimo rohy, jedno uzavřené těleso; stránka; změna podstavce tam a zpět)
  a `BustStylesTest` (skrytí, výchozí, uložené modely).

## Co ukáže až tisk

Sklon bočních ploch 10 až 20° je převis hluboko pod hranicí podpěr, ale lesk a vrstvy na velké rovné ploše ukáže
až tisk. Zaoblený spodek typu „na noze“ je převis jako u `antique`: bez podpěr se nevytiskne. Písmo na bloku je
při 150 mm vysoké 7 až 9,5 mm, na disku a patě 4,5 až 6 mm (vyšší číslo je jméno s háčky a čárkami); pod 3 mm (malé busty) hlásí `small_text_mm`. Hloubka
písma 0,7 až 1,2 mm a zkosená hrana bloku jsou odhad podle ostatních podstavců.
