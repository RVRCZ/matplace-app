---
title: "Jak připravit STL soubor pro 3D tisk: Kompletní průvodce 2026"
excerpt: Jak správně exportovat STL z CAD programu, na co si dát pozor u rozlišení, jednotek a tenkých stěn a jak model zkontrolovat a opravit online zdarma. Kalkulace na matplace.com pak model rozřeže na vrstvy sama, slicer nastavovat nemusíte.
cover: blog/20260427_212222_fbd300f8.jpg
published_at: 2026-04-27 19:07:37
updated_at: 2026-10-18 20:00:00
---
Špatně připravený soubor je nejčastější důvod, proč se tisk nepovede. Stačí pár minut při exportu a kontrole a ušetříte si zklamání. Tento průvodce vás provede exportem z CAD programu, kontrolou a opravou modelu. Nastavení tiskárny řešit nemusíte: když model nahrajete na matplace.com, kalkulace ho rozřeže na vrstvy sama a ukáže dobu tisku i cenu.

## Co je STL soubor

STL popisuje povrch tělesa sítí trojúhelníků. Je to **standardní formát pro 3D tisk**, rozumí mu každý slicer i kalkulace na matplace.com.

- Nese jen tvar: žádné barvy, textury ani materiály.
- Neukládá jednotky. Slicer předpokládá milimetry.
- Binární STL je menší a načítá se rychleji než textové.

Kromě STL přijímáme i **3MF** (nese navíc barvy a nastavení) a **OBJ**.

## Krok 1: Export z CAD programu

### Fusion 360

- Pravé tlačítko na těleso → **Save As Mesh**
- Formát **STL (Binary)**, Refinement **High** nebo **Medium**

### Tinkercad

- Vpravo nahoře **Export** → **.STL**

### SolidWorks

- **File → Save As**, typ **STL (*.stl)**
- Options → Resolution **Fine**, jednotky **Millimeters**

### Blender

- **File → Export → STL**, zaškrtněte **Selection Only**
- Scale **1.00**, Forward **Y**, Up **Z**

### FreeCAD

- Vyberte objekt → **File → Export** → **STL Mesh (*.stl)**

## Krok 2: Rozlišení

| Rozlišení | Trojúhelníků | Kdy |
|---|---|---|
| Nízké | 1 000–10 000 | jednoduché tvary, hrubé prototypy |
| Střední | 10 000–100 000 | většina modelů |
| Vysoké | 100 000–500 000 | figurky, organické tvary |

Pro tisk z plastu stačí 50 000 až 100 000 trojúhelníků. Vyšší rozlišení kvalitu nezlepší, jen zvětší soubor. Naopak příliš hrubý model má na oblých plochách viditelné hrany.

## Krok 3: Kontrola modelu

Nejčastější chyby, které tisk pokazí:

- **Díry v síti.** Model není uzavřený (vodotěsný) a slicer neví, co je uvnitř.
- **Převrácené plochy.** Některé trojúhelníky míří dovnitř místo ven.
- **Špatné jednotky.** Model exportovaný v palcích nebo metrech vyjde 25krát menší nebo tisíckrát větší.
- **Příliš tenké stěny.** Stěna pod 0,8 mm se na tiskárně nevytvoří.
- **Víc samostatných těles.** Kusy, které se nedotýkají, se tisknou zvlášť a mohou spadnout.

To všechno najde zdarma nástroj [Kontrola modelu před tiskem](/tools/check). Nahrajete model a u každého nálezu vidíte, co znamená pro tisk.

## Krok 4: Oprava

Když kontrola najde díry nebo převrácené plochy, nahrajte soubor do nástroje [Opravíme váš rozbitý soubor](/tools/repair). Zacelí díry, otočí převrácené plochy, ukáže, co se změnilo, a opravený STL si stáhnete zdarma. Funguje i pro 3MF a OBJ, které slicer odmítá.

Kalkulace na hlavní stránce drobné chyby opravuje sama. Když model prošel opravou, napíše to u něj. Původní soubor zůstane beze změny, tvar i rozměry jsou stejné.

## Krok 5: Velikost, orientace a hmotnost

- **Rozměry.** Po nahrání vidíte rozměry modelu v milimetrech. Když nesedí, změňte velikost posuvníkem.
- **Větší než tiskárna.** Nástroj [Rozdělení modelu na díly](/tools/split) model rozřeže na díly s kolíky nebo rybinovými klíči, které se vejdou na podložku. Pro opravdu velké kusy je [V životní velikosti](/tools/life-size), který model zvětší, vydutí a rozdělí najednou.
- **Masivní model.** [Vydutit model](/tools/hollow) udělá dutý model se stěnou a odtokovými otvory a spočítá ušetřené gramy.
- **Orientace.** Plochou stranu dolů, nejdetailnější nahoru, převisy pod 45°. V kalkulaci model můžete otočit a hned vidíte, jak se změní doba tisku.

## Krok 6: Slicer

Pokud tisknete u nás, slicer nastavovat nemusíte. Kalkulace model rozřeže stejně jako tiskárna: běžně vrstvou 0,2 mm a s výplní 15 %, s podpěrami jen tam, kde jsou potřeba.

Pokud tisknete doma, platí:

- **Výška vrstvy:** 0,1 mm jemně, 0,2 mm standard, 0,3 mm rychle.
- **Výplň:** 10–20 % dekorace, 30–50 % funkční díly, 80–100 % namáhané díly.
- **Podpěry:** pro převisy nad 45°, stromové spotřebují méně materiálu.
- **Brim:** pro malé díly a materiály, které se kroutí.

Z kalkulace si můžete stáhnout STL i hotový projekt pro svou tiskárnu.

## Časté otázky

### Jaký je rozdíl mezi STL a 3MF?

3MF nese navíc barvy, materiály a nastavení tisku. Pro jednobarevný tisk stačí STL. Vícebarevný 3MF rozdělí nástroj [Díly podle barev z 3MF](/tools/colors) na samostatné díly.

### Můžu poslat do tiskárny přímo STL?

Ne. Tiskárna potřebuje G‑kód, který vytvoří slicer. U nás to udělá kalkulace. Máte jen G‑kód a chybí vám model? Nástroj [Model z G‑kódu](/tools/gcode-model) z něj vytvoří zpět STL.

### Co když nemám CAD program?

Zdarma jsou Tinkercad (online, jednoduchý), FreeCAD a Blender. Často ale CAD vůbec nepotřebujete: krabičku, organizér, stojánek nebo cedulku si poskládáte v [nástrojích](/tools) zadáním rozměrů a textu. Hotový model pak rovnou jde do kalkulace.

### Kolik to bude stát?

Cenu uvidíte hned po nahrání. Skutečné ceny několika modelů a z čeho se cena skládá najdete v článku [Kolik stojí 3D tisk na zakázku](/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026). Materiál vyberete podle článku [PLA vs. PETG vs. ASA](/blog/pla-vs-petg-vs-asa-jaky-material-vybrat).

## Co dál

- [Kontrola modelu před tiskem](/tools/check): rozměry, díry, převrácené plochy a tenké stěny, u každého nálezu co znamená pro tisk.
- [Oprava 3D modelu](/tools/repair): zacelí díry a otočí plochy, opravený STL stáhnete zdarma.
- [Spočítat cenu tisku](/): nahrajte hotový soubor a uvidíte dobu tisku, spotřebu a cenu.
