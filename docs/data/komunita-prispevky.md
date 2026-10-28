# Komunitní příspěvky k nástrojům zdarma (N1, 28. 10. 2026)

Šest krátkých odpovědí pro skupiny 3D tisk CZ/SK na Facebooku, Printables a r/3Dprinting. Jsou psané jako odpověď
na konkrétní dotaz, ne jako reklama: řeknou, co nástroj udělá, kde má hranice, a dají odkaz. Roman je posílá sám,
ideálně pod dotaz, který se opravdu objevil (kdo se ptá na non‑manifold, dostane opravu, ne litofanii). Čísla jsou
z docs/AP.md (§3 až §5). Odkazy nesou `utm_source` podle sítě; pro Printables a Facebook stačí vyměnit `reddit`
za `printables` nebo `facebook`.

Pravidla, která jsme si dali: jeden odkaz, žádné „nejlepší“, žádná cena tisku u nás v příspěvku (nástroj je
zdarma, o tisku se ptají sami), přiznat, co nástroj neumí, v EN nepsat o korunách.

---

## 1. Oprava STL (slicer hlásí non‑manifold, díry, otevřené hrany)

**CS (FB skupina / Printables):**

Když slicer hlásí non‑manifold nebo otevřené hrany, většinou jsou to díry v síti a zdvojené plochy po exportu nebo
po skenu. Zkuste to prohnat opravou tady: https://matplace.com/tools/repair?utm_source=facebook&utm_medium=community
Je to zdarma, bez účtu, bere STL/3MF/OBJ do 120 MB, zacelí díry, otočí převrácené plochy a napíše, co změnilo
(tabulka před/po). Tvar ani rozměry nemění; když by oprava posunula rozměr o víc než 2 %, zahodí ji a řekne to.
Co neumí: doplnit velký chybějící kus povrchu nebo uzavřít plochu bez tloušťky, to zůstává na Blender/Meshmixer.
Rozbitou bustu se 36 otevřenými hranami a 51 úlomky to spojilo do jednoho tělesa za 1,4 s.

**EN (r/3Dprinting):**

If the slicer says non‑manifold or open edges, it is usually holes and doubled faces from an export or a scan.
Try running it through this free repair: https://matplace.com/tools/repair?utm_source=reddit&utm_medium=community
No account, STL/3MF/OBJ up to 120 MB; it closes holes, flips inverted faces and tells you what it changed
(before/after table). It does not change the shape or size; a repair that would move a dimension by more than 2 % is
thrown away and reported. What it cannot do: rebuild a large missing patch or close a zero‑thickness surface, that
is still Blender/Meshmixer work. A broken bust with 36 open edges and 51 fragments came back as one solid in 1.4 s.

## 2. SVG do STL (logo nebo kresba jako model)

**CS:**

Na převod SVG na STL s rozměry v mm jde použít tohle: https://matplace.com/tools/svg-to-stl?utm_source=facebook&utm_medium=community
Zadáte šířku 20 až 250 mm a výšku vytažení 0,6 až 50 mm, otvory zůstanou otvory, výsledek je uzavřené těleso
(slicer nehlásí díry). Pohlídejte si v SVG výplně místo čar (Inkscape: Stroke to Path) a text převedený na křivky.
Dopředu to upozorní na čáry pod 0,8 mm, které tryska 0,4 nevytiskne. Oddělené části kresby vyjdou jako samostatné
kousky, pokud nepřepnete na reliéf na destičce. Bez účtu, zdarma.

**EN:**

For SVG to STL with real millimetres this works well: https://matplace.com/tools/svg-to-stl?utm_source=reddit&utm_medium=community
Set the width (20–250 mm) and the extrusion height (0.6–50 mm), holes stay holes, and the output is a closed solid
(no slicer complaints). Make sure the SVG has fills, not strokes (Inkscape: Stroke to Path), and text converted to
paths. It warns about lines under 0.8 mm that a 0.4 nozzle will not print. Separate parts of a drawing come out as
separate pieces unless you switch to a relief on a plate. Free, no account.

## 3. G‑kód zpět na model (ztracené STL)

**CS:**

Když zbyl jen .gcode, model se z něj dá postavit zpět z vnějších stěn vrstev: https://matplace.com/tools/gcode-model?utm_source=facebook&utm_medium=community
Čte G‑kód z Orcy, Bambu Studia, Anycubic Sliceru, PrusaSliceru a Cury do 150 MB; z hlavičky vytáhne tiskárnu,
vrstvu, čas a gramy. Dvoubarevný tisk dá díl na každou barvu. Počítejte s tím, že schodky vrstev v modelu zůstanou
a detaily pod šířku linky nejsou (slicer je nikdy netiskl); objem vyšel o 0,8 % větší než originál. Soubor 5,6 MB
(191 tisíc řádků) trval 12 s. .gcode.3mf je zip, nahrajte .gcode z Metadata; binární .bgcode to nečte.

**EN:**

If all you have is the .gcode, the model can be rebuilt from the outer walls of each layer: https://matplace.com/tools/gcode-model?utm_source=reddit&utm_medium=community
Reads G‑code from Orca, Bambu Studio, Anycubic Slicer, PrusaSlicer and Cura up to 150 MB, pulls the printer, layer
height, time and grams from the header, and a multi‑colour print gives one part per colour. Expect the layer steps to
stay in the model and details below the line width to be missing (the slicer never printed them); volume came out
0.8 % larger than the original. A 5.6 MB file (191k lines) took 12 s. A .gcode.3mf is a zip, upload the .gcode from
Metadata; binary .bgcode is not read.

## 4. Vydutit model (dutý se stěnou a odtokovými otvory)

**CS:**

Na vydutění (pro pryskyřici nebo velké figury) jde použít tohle: https://matplace.com/tools/hollow?utm_source=facebook&utm_medium=community
Stěna 1,5 až 6 mm, odtokové otvory 3 až 8 mm ve dně (automaticky až čtyři, nebo bez nich), bere STL/3MF/OBJ/STEP
do 1000 mm a 2 milionů trojúhelníků, neuzavřený model nejdřív uzavře. Hned ukáže objem dutiny a ušetřené gramy,
rentgen v náhledu ukáže stěnu. Zvenku se nic nezmění. U modelů nad 190 mm je mřížka měření hrubší a stěna může být
místy o desetinu až půl milimetru jiná, nástroj to řekne. Pro malé FDM díly to nemá smysl, výplň to vyřeší líp.

**EN:**

For hollowing (resin or big figures) this does the job: https://matplace.com/tools/hollow?utm_source=reddit&utm_medium=community
Wall 1.5–6 mm, drain holes 3–8 mm in the bottom (up to four automatically, or none), takes STL/3MF/OBJ/STEP up to
1000 mm and 2 M triangles, closes an open mesh first. It shows the cavity volume and grams saved right away and an
x‑ray of the wall. Nothing changes outside. Above 190 mm the measuring grid is coarser and the wall can be off by a
tenth to half a millimetre in places, it tells you. Not worth it for small FDM parts, infill does that better.

## 5. Rozdělit model na díly (nevejde se na podložku)

**CS:**

Na rozřezání modelu, který se nevejde na tiskárnu, s kolíky nebo rybinami: https://matplace.com/tools/split?utm_source=facebook&utm_medium=community
Vyberete podložku (250/220/180 mm nebo vlastní), nástroj udělá co nejméně řezů, roviny jdou posunout. Každý díl
položí řezem dolů (spoj bez podpěr), kolíky Ø 6 mm s vůlí 0,2 mm nebo rybinové klíče, do řezu vyryje číslo dílu
a ukáže mapu, jak to složit. STL/3MF/OBJ/STEP do 1000 mm. Tvar, který se nevejde ani po šesti řezech v jednom
směru, odmítne. Zdarma, bez účtu; díly si stáhnete po jednom nebo jako projekt.

**EN:**

For cutting a model that does not fit the bed, with pins or dovetails: https://matplace.com/tools/split?utm_source=reddit&utm_medium=community
Pick the bed (250/220/180 mm or custom), it makes as few cuts as possible and you can move the planes. Every part is
laid cut‑face down (no supports at the joint), pins Ø 6 mm with 0.2 mm play or dovetail keys, the part number is
engraved in the cut face and a map shows how it goes together. STL/3MF/OBJ/STEP up to 1000 mm. A shape that does not
fit even after six cuts in one direction is refused. Free, no account; parts download one by one or as a project.

## 6. Litofanie z fotky

**CS:**

Litofanii z fotky (panel, lampa na E27/E14 nebo reliéf) uděláte tady: https://matplace.com/tools/relief?utm_source=facebook&utm_medium=community
Tloušťka 0,4 až 5 mm po 0,2 mm, nástroj píše, kolik odstínů z fotky vznikne, jas/kontrast/střední tóny posuvníky,
náhled proti světlu. Sedm tvarů destičky do 250 mm, lampa s obvodem do 400 mm. Fotku neukládá. Tiskne se nastojato
a plné, z bílého PLA; do lampy jen LED, PLA měkne kolem 55 °C. Stažení STL zdarma, bez účtu.

**EN:**

For a lithophane from a photo (panel, lamp for E27/E14, or a relief) try this: https://matplace.com/tools/relief?utm_source=reddit&utm_medium=community
Thickness 0.4–5 mm in 0.2 mm steps, it tells you how many shades the photo gives, brightness/contrast/midtones
sliders, backlit preview. Seven plate shapes up to 250 mm, a lamp up to 400 mm around. The photo is not stored.
Print upright and solid in white PLA; LED only in the lamp, PLA softens around 55 °C. Free STL download, no account.
