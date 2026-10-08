<?php

return [
    'title' => 'SVG do STL: převod obrysu na 3D model online',
    'description' => 'Nahrajete SVG nebo jednoduchý obrázek, zadáte šířku a výšku v milimetrech a stáhnete STL zdarma a bez registrace. Nebo vám tvar vytiskneme.',
    'h1' => 'Převod SVG do STL: obrys vytažený do výšky',
    'intro' => [
        'Nástroj vezme vyplněné tvary ze souboru SVG a vytáhne je kolmo do výšky, kterou zadáte: od 0,6 do 50 mm. Šířku nastavíte od 20 do 250 mm a druhý rozměr se dopočítá podle poměru stran, takže model má přesně ty milimetry, které čtete vedle náhledu. Otvory uvnitř tvaru zůstávají otvory. Místo SVG jde nahrát i jednoduchý kontrastní obrázek PNG, JPG nebo WebP, vybrat motiv z knihovny, nebo napsat text.',
        'Výsledek je uzavřené těleso připravené pro slicer, žádná děravá síť, kterou byste museli opravovat. Nástroj předem upozorní na čáry tenčí než 0,8 mm a napíše, z kolika samostatných kousků se tvar skládá. Horní hranu můžete nechat zkosit. Model stáhnete jako STL nebo jako hotový projekt pro slicer, anebo ho objednáte jako výtisk z naší tiskové farmy v barvě, která je právě založená v tiskárnách.',
    ],
    'steps' => [
        ['name' => 'Nahrajte SVG', 'text' => 'Vyberete soubor SVG do 400 kB, nebo obrázek PNG, JPG či WebP do 5 MB. Na vyzkoušení je předvybraný motiv z knihovny.'],
        ['name' => 'Zadejte šířku a výšku vytažení', 'text' => 'Šířku tvaru nastavíte posuvníkem nebo tažením šipky v náhledu, tloušťku od 0,6 do 50 mm. Zkosení horní hrany zapnete jedním zaškrtnutím.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Vedle náhledu čtete vnější rozměry a orientační cenu, pod ním upozornění na tenké čáry nebo oddělené kousky.'],
        ['name' => 'Stáhněte STL, nebo objednejte tisk', 'text' => 'Model stáhnete zdarma a bez registrace jako STL nebo jako projekt pro slicer. Výtisk z naší farmy zaplatíte z předplaceného kreditu.'],
    ],
    'faq' => [
        ['q' => 'Jaké SVG nástroj zpracuje?', 'a' => 'Soubor do 400 kB s vyplněnými tvary. Čáry bez výplně nástroj vynechá a napíše to; v kreslicím programu je proto předem převeďte na obrysy (v Inkscapu funkce Stroke to Path) a text na křivky.'],
        ['q' => 'V jakých jednotkách model vyjde?', 'a' => 'V milimetrech. Na rozměrech zapsaných v SVG nezáleží: tvar se zvětší nebo zmenší na šířku, kterou zadáte, a druhý rozměr se dopočítá podle poměru stran.'],
        ['q' => 'Proč se tvar skládá z několika kousků?', 'a' => 'Vytažený obrys nemá destičku, takže oddělené části kresby nic nespojuje. Nástroj napíše, kolik jich je. Mají-li držet pohromadě, přepněte provedení na reliéf na destičce.'],
        ['q' => 'Můžu nahrát PNG nebo JPG místo SVG?', 'a' => 'Ano, jednoduchý obrázek s tmavým motivem na světlém pozadí. Převádí se v mřížce nejvýše 360 bodů na delší straně, SVG proto dá hladší hrany. Fotografie sem nepatří, na tu je nástroj Reliéf a litofanie.'],
        ['q' => 'Jak vysoko jde tvar vytáhnout?', 'a' => 'Od 0,6 do 50 mm. Do 3 mm vznikne štítek nebo ozdoba, kolem 5 mm přívěsek či podtácek a od 20 mm tvar, který stojí sám.'],
        ['q' => 'Jak zaplatím a jak výtisk dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Silueta kočky z knihovny, 80 × 81 mm, vytažená na 6 mm.',
        'Dubový list 100 × 62 mm, tloušťka 3 mm, se zkosenou horní hranou.',
        'Hvězda 60 × 57 mm vytažená do výšky 20 mm.',
    ],
];
