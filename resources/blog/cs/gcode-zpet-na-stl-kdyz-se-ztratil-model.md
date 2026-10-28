---
title: "G-kód zpět na STL: když se ztratil model a zbyl jen soubor pro tiskárnu"
excerpt: Z G-kódu na SD kartě vznikne znovu model, který zvětšíte, přebarvíte nebo vytisknete na jiné tiskárně. Jak nástroj čte vnější stěny vrstev, jak přesný je výsledek, co zvládne s barvami a co se nevrátí.
cover:
published_at: 2026-11-17 07:00:00
updated_at: 2026-11-17 07:00:00
---
Výtisk se povedl, G-kód leží na SD kartě, ale STL je pryč: smazaný, na starém počítači, nebo ho poslal kamarád a zdroj nemá. Nástroj [Model z G-kódu](/tools/gcode-model) z takového souboru postaví zpět uzavřený model, který jde znovu nařezat pro jinou tiskárnu, zvětšit nebo nechat vytisknout u nás. Tenhle článek říká, jak to funguje, jak je to přesné a kde to končí.

<!-- foto: vytištěný díl a vedle něj obrazovka s modelem obnoveným z G-kódu -->

## Co je v G-kódu a co ne

G-kód jsou dráhy tiskové hlavy: kam jet, kolik vytlačit, jak rychle. Původní tvar v něm není, ale je v něm, kde ležela vnější stěna každé vrstvy. Nástroj z těchto smyček poskládá obrys vrstvy, rozliší díry a ostrůvky podle toho, co leží v čem, a vrstvy vytáhne do tělesa. Výplň, vnitřní stěny, podpěry, límec ani čisticí věž tvar nedělají a nástroj je přeskočí. Z hlavičky vyčte slicer, tiskárnu, výšku vrstvy, čas tisku a spotřebu filamentu.

## Příklad: dva soubory z naší tiskárny

G-kód z Anycubic Slicer Next pro Kobra S1 o 5,6 MB a 191 tisících řádků, 165 vrstev po 0,2 mm, tisk 36 minut a 3,6 g, dal za 12 vteřin model 91 × 57 × 16,5 mm s 522 tisíci trojúhelníky. Dvoubarevný G-kód o 14 MB a 545 tisících řádků (39 vrstev po 0,12 mm, 5 h 16 min, 81 g) dal za 9 vteřin dva díly po barvách, černý 49 g a žlutý Silk 33 g, s barvami z hlavičky (změřeno 27. 10. 2026 na našem počítači).

## Jak přesný je výsledek

Povrch modelu leží tam, kde byl střed vnější stěny plus půl šířky linky, tedy kde byl povrch výtisku. Na známém tělese vyšel objem o 0,8 % větší než původní model, půdorys na setiny milimetru a výška zaokrouhlená na vrstvy. Rohy jsou zaoblené na půl linky a schodky vrstev v modelu zůstávají, přesně jako na výtisku. Detaily užší než jedna linka (0,4 mm) slicer nikdy netiskl, takže v G-kódu nejsou a do modelu se nevrátí: jemný reliéfní text vyjde jen tak hrubý, jak ho tiskárna opravdu tiskla.

## Barvy

Více nástrojů v jedné vrstvě, třeba písmeno vložené do destičky, dá díl na každou barvu s barvou filamentu z hlavičky a k tomu 3MF projekt s barvami. Střídání barev po výšce zůstane jako změny filamentu v daných výškách. Jeden filament dá jeden díl.

## Které soubory nástroj přečte

G-kód pro FDM tiskárny se značkami typů drah: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura; do 150 MB a 1,5 milionu řádků. Balíček .gcode.3mf z Bambu Studia je zip: rozbalte ho a nahrajte soubor .gcode ze složky Metadata. Binární .bgcode z PrusaSliceru nástroj nečte; vypněte binární G-kód v nastavení tiskárny a exportujte znovu. G-kód bez značek typů (starý Cura, Simplify3D) a soubory pro pryskyřici nebo CNC skončí hlášením, že nástroj nenašel stěny.

## Co s modelem dál

Nový model se otevře jako každý jiný soubor: spočítáte cenu, zvětšíte ho, [vydutíte](/tools/hollow) nebo [rozdělíte na díly](/tools/split), stáhnete STL, díly po barvách či 3MF projekt, nebo ho necháte vytisknout u nás. Náš farma tiskne z modelu, který nařeže pro vlastní stroje a materiál, proto samotný cizí G-kód tisknout nemůžeme; převod na model to umožní. Zvětšením se zvětší i schodky vrstev, pro dvojnásobek a víc je lepší povrch přebrousit.

[video: latest]

## Co dál

- [Model z G-kódu](/tools/gcode-model): nahrajte .gcode a stáhněte STL, díly po barvách nebo 3MF.
- [Kontrola modelu](/tools/check): ověřte, že se obnovený model vejde na tiskárnu a má uzavřenou síť.
- [Jak připravit STL soubor pro 3D tisk](/blog/jak-pripravit-stl-soubor-pro-3d-tisk): kontrola, oprava a slicer.
