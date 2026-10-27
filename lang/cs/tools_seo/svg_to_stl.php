<?php

// Stránka pro dotaz „svg do stl“ (docs/AP.md §1, §3). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'SVG do STL online zdarma: obrys jako 3D model k tisku',
    'heading' => 'Převod SVG do STL online zdarma',
    'description' => 'Nahrajete SVG, PNG nebo napíšete text, zadáte šířku v milimetrech a výšku vytažení. Uzavřené STL stáhnete bez registrace, nebo tvar vytiskneme od 99 Kč.',
    'h1' => 'Co převod SVG do STL umí a kde má hranice',
    'intro' => [
        'Nástroj vezme vyplněné tvary ze souboru SVG a vytáhne je kolmo do výšky, kterou zadáte: od 0,6 do 50 mm. Šířku nastavíte od 20 do 250 mm a druhý rozměr se dopočítá podle poměru stran, takže model má přesně ty milimetry, které čtete vedle náhledu; na jednotkách zapsaných v SVG nezáleží. Otvory uvnitř tvaru zůstávají otvory. Soubor SVG může mít do 400 kB a čáry bez výplně nástroj vynechá a řekne to. Místo SVG jde nahrát jednoduchý kontrastní obrázek PNG, JPG nebo WebP do 5 MB, vybrat siluetu z knihovny, nebo napsat text. Výsledek je uzavřené těleso s dnem, víkem a stěnami, které slicer vezme bez opravy. Nástroj předem upozorní na čáry tenčí než 0,8 mm, které se tryskou 0,4 mm netisknou čistě, a napíše, z kolika samostatných kousků se tvar skládá.',
        'Příklad z 27. 10. 2026: silueta kočky z knihovny, šířka 80 mm, vytažená na 6 mm, vyšla 80 × 81 × 6 mm, 2 568 trojúhelníků a 17,7 cm³. Kalkulace na ni počítá 9,6 g PLA a 17 minut tisku, u nás za 99 Kč (nejnižší cena zakázky, bez dopravy; kalkulace /c/lfa9iggn3hls). Model si stáhnete zdarma a bez účtu jako STL, nebo jako projekt 3MF pro OrcaSlicer, Bambu Studio či PrusaSlicer s nastavením z kalkulace. Kdo tiskárnu nemá, nechá tvar vytisknout na naší farmě v barvě, která je právě založená v tiskárnách.',
    ],
    'steps' => [
        ['name' => 'Nahrajte SVG, obrázek nebo text', 'text' => 'Soubor SVG do 400 kB, obrázek PNG, JPG či WebP do 5 MB, silueta z knihovny, nebo nápis. Na vyzkoušení je předvybraná kočka z knihovny.'],
        ['name' => 'Zadejte šířku a výšku vytažení', 'text' => 'Šířku v milimetrech nastavíte posuvníkem nebo tažením šipky v náhledu, výšku od 0,6 do 50 mm. Zkosení horní hrany zapnete zaškrtnutím.'],
        ['name' => 'Přečtěte si upozornění', 'text' => 'Vedle náhledu jsou vnější rozměry, pod ním poznámka o čarách pod 0,8 mm a o počtu oddělených kousků, pokud nějaké jsou.'],
        ['name' => 'Stáhněte STL, nebo objednejte tisk', 'text' => 'STL nebo projekt pro slicer stáhnete zdarma a bez registrace. Tisk u nás pokračuje do kalkulace, kde vidíte gramy, minuty a cenu.'],
    ],
    'faq' => [
        ['q' => 'Proč slicer bere STL odsud bez chyb, když jiný převodník dal děravou síť?', 'a' => 'Každý obrys dostane dno, víko i stěny a tělesa se spojí do jedné uzavřené sítě. Slicer tak nehlásí otevřené hrany ani převrácené plochy a model nepotřebuje opravu.'],
        ['q' => 'Jak připravit SVG, aby se převedlo celé?', 'a' => 'Všechno, co se má vytisknout, musí být vyplněný tvar. V Inkscapu převeďte čáry funkcí Stroke to Path a text funkcí Object to Path; stejně to umí Illustrator i Affinity. Vrstvy, masky a přechody nástroj nečte.'],
        ['q' => 'Jak nastavím přesné rozměry v milimetrech?', 'a' => 'Zadáte šířku a druhý rozměr se dopočítá podle poměru stran, takže se tvar nezkreslí. Rozměry zapsané v SVG (px, mm, palce) se nepoužijí; platí to, co čtete vedle náhledu.'],
        ['q' => 'Proč se tvar rozpadl na několik kousků?', 'a' => 'Vytažený obrys nemá destičku, takže oddělené části kresby nic nespojuje a nástroj napíše, kolik jich je. Mají-li držet pohromadě, přepněte provedení na reliéf na destičce, nebo kresbu v editoru spojte.'],
        ['q' => 'Můžu nahrát PNG nebo JPG místo SVG?', 'a' => 'Ano, jednoduchý obrázek s tmavým motivem na světlém pozadí. Obrys se hledá v mřížce nejvýše 360 bodů na delší straně, takže SVG dá hladší hrany. Fotografie sem nepatří; na tu je nástroj Reliéf a litofanie.'],
        ['q' => 'Je převod zdarma a potřebuji účet?', 'a' => 'Převod i stažení STL jsou zdarma a bez účtu, nahraný soubor nikomu neposíláme. Účet potřebujete, až když si tvar necháte vytisknout u nás; platí se z kreditu dobitého kartou a výtisk posíláme Zásilkovnou po EU.'],
    ],
    'examples' => [
        'Silueta kočky z knihovny, 80 × 81 mm, vytažená na 6 mm: 9,6 g, 17 minut tisku.',
        'Dubový list 100 × 62 mm, tloušťka 3 mm, se zkosenou horní hranou.',
        'Hvězda 60 × 57 mm vytažená do výšky 20 mm.',
    ],
];
