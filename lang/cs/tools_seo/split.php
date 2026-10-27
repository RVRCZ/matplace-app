<?php

// Stránka pro dotaz „rozdělit stl na díly“ (docs/AP.md §1, §4). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Rozdělit STL na díly online zdarma: kolíky, rybiny a mapa',
    'heading' => 'Rozdělit 3D model na díly online',
    'description' => 'Model větší než tiskárna rozřežeme na díly s kolíky nebo rybinovými klíči, s čísly v řezu a mapou, jak je složit. Díly leží řezem dolů, bez podpěr. Zdarma.',
    'h1' => 'Jak rozdělení funguje, co dostanete a jak díly slepíte',
    'intro' => [
        'Nástroj rozřeže model rovinami napříč šířkou, hloubkou a výškou na díly, které se vejdou na zvolenou podložku: naši farmu 250 mm, běžné tiskárny 220 nebo 180 mm, nebo vlastní rozměr. Řezů dělá co nejméně, roviny můžete posunout posuvníky a náhled je ukazuje přímo na modelu. Každý díl otočí tak, aby ležel největší řeznou plochou na podložce, takže se v místě spoje tiskne bez podpěr. Do spojů přidá kolíky průměru 6 mm s dírami na obou stranách a vůlí 0,2 mm, nebo rybinové drážky s volným oboustranným klíčem, který se zasune ze strany; jdou i hladké řezy jen na lepení. Do řezné plochy každého dílu vyryje jeho číslo a na stránce ukáže mapu, jak díly leží vůči sobě. Bere STL, 3MF, OBJ i STEP od 5 do 1000 mm a do 2 milionů trojúhelníků, neuzavřený model nejdřív uzavře a tvar, který se nevejde ani po šesti řezech v jednom směru, odmítne.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): váleček ⌀ 120 × 320 mm se na podložku 250 mm nevejde. Nástroj ho rozdělil jedním řezem na dva díly se čtyřmi kolíky; oba díly a kolíky mají dohromady 814 g, tisknou se 13 h 8 min a u nás stojí 1 440 Kč. Rozdělení trvalo do minuty. Díly se otevřou jako nový soubor: v náhledu má každý svou barvu a mapa říká, který patří kam. Stáhnete je po jednom jako STL nebo všechny jako projekt pro slicer, nebo je necháte vytisknout u nás v jedné objednávce; původní model zůstane.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Přetáhněte do pole STL, 3MF, OBJ nebo STEP od 5 do 1000 mm, nebo klepněte a vyberte soubor.'],
        ['name' => 'Zvolte podložku a spoje', 'text' => 'Vyberte tiskárnu, pro kterou díly jsou (250, 220, 180 mm nebo vlastní), a druh spoje: kolíky, rybinové klíče, nebo hladké řezy. Nástroj řekne, kolik řezů je potřeba, roviny můžete posunout.'],
        ['name' => 'Rozdělte model', 'text' => 'Klepněte na „Rozdělit model“. U velkých modelů to trvá do minuty; stránka říká, co se právě děje.'],
        ['name' => 'Stáhněte díly, nebo objednejte tisk', 'text' => 'Díly vidíte v náhledu každý svou barvou, s mapou a čísly. Stáhnete je po jednom nebo jako projekt pro slicer, nebo je necháte vytisknout u nás.'],
    ],
    'faq' => [
        ['q' => 'Kolíky, rybiny, nebo hladký řez?', 'a' => 'Kolíky jsou nejpevnější s lepidlem a zvenku nejsou vidět. Rybinové klíče drží i bez lepidla a díly jdou rozebrat, klíč se ale zasouvá ze strany, takže potřebuje místo. Hladký řez je nejrychlejší na tisk a drží jen lepidlem; hodí se na díly, které stojí na sobě.'],
        ['q' => 'Jak díly slepím?', 'a' => 'Podle čísel vyrytých v řezných plochách a mapy na stránce. Kolíky zasuňte do děr (jsou v souboru jako díl „kolíky“, nebo použijte dřevěné Ø 6 mm) a díly slepte vteřinovým lepidlem nebo lepidlem na plasty. Rybinové klíče drží i bez lepidla.'],
        ['q' => 'Bude spoj vidět?', 'a' => 'Jako tenká čára: díly leží řezem dolů, takže řezná plocha je rovná, a kolíky i díry jsou uvnitř. Vůle 0,2 mm se po slepení ztratí; kdo chce hladký povrch, spoj přebrousí a přetře tmelem nebo barvou.'],
        ['q' => 'Proč díly leží řeznou plochou dolů?', 'a' => 'Řezná plocha je rovná, takže na podložce drží a v místě spoje nejsou potřeba podpěry. U dílu s více řezy jde dolů ta největší. Jinak otočený díl si ve sliceru otočíte sami.'],
        ['q' => 'Co když se díl stále nevejde?', 'a' => 'Nástroj to řekne. Přidejte rovinu řezu, zvolte větší podložku, nebo model nejdřív zmenšete v kalkulaci. Tvar, který se nevejde ani po šesti řezech v jednom směru, nástroj odmítne.'],
        ['q' => 'Jak velký model nástroj zvládne?', 'a' => 'Do 1000 mm a do 2 milionů trojúhelníků; těžší síť zjednoduší, pokud to jde. Model s dírami nejdřív uzavře stejně jako naše farma; když to nejde, řekne to a pomůže nástroj Oprava modelu.'],
    ],
    'examples' => [],
];
