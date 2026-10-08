<?php

return [
    'title' => 'Jmenovka ve tvaru srdce, mraku či kosti s obrázkem',
    'description' => 'Napíšete jméno, vyberete jeden z dvaceti tvarů destičky a motiv vedle textu. Jmenovku vytiskneme ve dvou barvách, nebo si model zdarma stáhnete.',
    'h1' => 'Jmenovka a cedulka ve tvaru s vlastním motivem',
    'intro' => [
        'Nástroj vytvoří destičku se jménem v jednom z dvaceti tvarů: srdce, hvězda, mrak, kost, stuha, šipka, domek, auto, kočka, ryba, štít, bublina a další. Napíšete jeden nebo dva řádky po 40 znacích a vyberete jedno ze třiceti písem. Tvar se sám zvětší právě tak, aby se do něj text s okrajem vešel, takže nic nepřesahuje a nemusíte nic počítat. Výšku písma nastavíte od 4 do 80 mm.',
        'Vedle textu může stát obrázek: motiv z knihovny siluet nebo vlastní SVG či jednoduchý obrázek. Postavíte ho vlevo, vpravo nebo nad text a vytiskne se stejně jako písmo. Písmo a lem umíme vytisknout druhou barvou, tiskárna ji vymění ve výšce, kde písmo začíná. Očko na zavěšení přidáte vlevo, vpravo nebo nahoru, takže ze stejného návrhu je cedulka na dveře pokojíčku, visačka na tašku i známka na obojek.',
    ],
    'steps' => [
        ['name' => 'Napište jméno', 'text' => 'Jeden nebo dva řádky, druhý je menší. Pod poli je řada symbolů, které jdou vložit do textu.'],
        ['name' => 'Vyberte tvar a písmo', 'text' => 'Dvacet tvarů destičky vidíte jako dlaždice, třicet písem jako jejich názvy vysázené v daném písmu.'],
        ['name' => 'Přidejte obrázek a očko', 'text' => 'Motiv z knihovny nebo vlastní obrázek postavíte vlevo, vpravo či nad text. Očko má tři možné strany.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu. Při objednávce vyberete barvu destičky a barvu písma; model i projekt pro slicer stáhnete zdarma.'],
    ],
    'faq' => [
        ['q' => 'Jak velká jmenovka vyjde?', 'a' => 'Podle textu a tvaru. Jméno Jana s písmem 14 mm a hvězdou vyjde na mraku 97 × 53 mm. Tvary s úzkým středem, třeba kost nebo šipka, vycházejí delší; zmenšíte je menším písmem nebo okrajem.'],
        ['q' => 'Proč je kost nebo šipka o tolik větší než obdélník?', 'a' => 'Text musí celý ležet uvnitř tvaru. Kost má uprostřed úzký dřík, takže se zvětšuje, dokud se do dříku nevejde výška písma. Krátké jméno na jednom řádku jí sedí nejlíp.'],
        ['q' => 'Jaký obrázek můžu přidat?', 'a' => 'Siluetu z knihovny, SVG s vyplněnými tvary, nebo jednoduchý kontrastní obrázek PNG či JPG. Fotografie se nehodí; na tu je nástroj Reliéf a litofanie.'],
        ['q' => 'Vytisknete jmenovku ve dvou barvách?', 'a' => 'Ano. Písmo, obrázek a lem leží nad destičkou, takže stačí jedna výměna filamentu. Obě barvy vyberete při objednávce z těch, které jsou právě založené v tiskárnách.'],
        ['q' => 'Hodí se jako známka pro psa?', 'a' => 'Tvar kosti s očkem ano, na obojek volte PETG a písmo aspoň 6 mm. Telefonní číslo dejte na druhý řádek; menší než 4 mm už se netiskne čistě.'],
        ['q' => 'Jak zaplatím a jak jmenovku dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Mrak se jménem Jana a hvězdou vlevo, 97 × 53 mm, písmo a lem druhou barvou.',
        'Kost se jménem Rex a tlapkou vpravo, 110 × 51 mm, písmo Titan One.',
        'Srdce 61 × 54 mm se jménem Ela, rokem 2020 a očkem nahoře, písmo Lobster.',
    ],
];
