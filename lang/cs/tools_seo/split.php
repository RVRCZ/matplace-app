<?php

return [
    'title' => 'Rozdělení 3D modelu na díly, které se vejdou na tiskárnu',
    'description' => 'Model větší než podložka tiskárny rozřežeme na díly s kolíky nebo rybinovými klíči, s čísly v řezu a položené pro tisk bez podpěr. Zdarma, online, s mapou dílů.',
    'h1' => 'Rozdělení modelu na díly: velký výtisk z malé tiskárny',
    'intro' => [
        'Nástroj rozřeže model rovinami napříč šířkou, hloubkou a výškou na díly, které se vejdou na zvolenou podložku: naši farmu 250 mm, běžné tiskárny 220 nebo 180 mm, nebo vlastní rozměr. Řezů je co nejméně a roviny můžete posunout posuvníky, náhled je ukazuje přímo na modelu. Každý díl se otočí tak, aby ležel největší řeznou plochou na podložce, takže se v místě spoje tiskne bez podpěr.',
        'Do spojů přidá kolíky průměru 6 mm s dírami na obou stranách a vůlí 0,2 mm, nebo rybinové drážky s volným oboustranným klíčem, který se zasune ze strany. Do řezné plochy každého dílu vyryje jeho číslo a na stránce ukáže mapu, jak díly leží vůči sobě. Model, který není uzavřený, nejdřív uzavře. Díly se otevřou jako nový soubor: necháte je vytisknout u nás, nebo si je stáhnete po jednom.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Přetáhněte model do pole nebo klepněte a vyberte soubor: STL, 3MF, OBJ nebo STEP od 5 do 1000 mm.'],
        ['name' => 'Zvolte podložku a spoje', 'text' => 'Vyberte tiskárnu, pro kterou díly jsou, a druh spoje: kolíky, rybinové klíče, nebo jen hladké řezy. Nástroj řekne, kolik řezů je potřeba, a roviny můžete posunout.'],
        ['name' => 'Rozdělte model', 'text' => 'Klepněte na „Rozdělit model“. U velkých modelů to trvá do minuty; stránka říká, co se právě děje.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Díly vidíte v náhledu každý svou barvou, s mapou, jak je složit. Stáhnete je po jednom nebo jako projekt pro slicer, nebo je necháte vytisknout u nás.'],
    ],
    'faq' => [
        ['q' => 'Jak díly slepím?', 'a' => 'Podle čísel vyrytých v řezných plochách a mapy na stránce. Kolíky zasuňte do děr (jsou v souboru jako díl „kolíky“, nebo použijte dřevěné Ø 6 mm) a díly slepte vteřinovým lepidlem nebo lepidlem na plasty. Rybinové klíče drží i bez lepidla.'],
        ['q' => 'Proč díly leží řeznou plochou dolů?', 'a' => 'Řezná plocha je rovná, takže na podložce drží a v místě spoje nejsou potřeba podpěry. U dílu s více řezy jde dolů ta největší. Jinak otočený díl si ve sliceru otočíte sami.'],
        ['q' => 'Co když se díl stále nevejde?', 'a' => 'Nástroj to řekne. Přidejte rovinu řezu, zvolte větší podložku, nebo model nejdřív zmenšete. Tvar, který se nevejde ani po šesti řezech v jednom směru, nástroj odmítne.'],
        ['q' => 'Co se stane s modelem, který má díry?', 'a' => 'Před řezáním ho nástroj uzavře stejným postupem, jaký používá naše farma. Když to nejde, řekne to; takový model nejdřív opravte v nástroji Oprava modelu.'],
        ['q' => 'Jak velký model nástroj zvládne?', 'a' => 'Do 1000 mm a do 2 milionů trojúhelníků; těžší síť zjednoduší, pokud to jde. Velké modely trvají do minuty.'],
        ['q' => 'Kolik to stojí a jak výtisk dostanu?', 'a' => 'Rozdělení je zdarma. Platíte jen tisk, pokud si ho u nás objednáte, z předplaceného kreditu. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [],
];
