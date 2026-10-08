<?php

return [
    'title' => 'Stojánek na samolepicí lístečky s vlastní siluetou',
    'description' => 'Nahrajete obrázek nebo napíšete jméno a vznikne stojánek na bloček lístečků se siluetou a drážkou na tužku. Vytiskneme ho, nebo si model stáhnete.',
    'h1' => 'Stojánek na lístečky s vaším obrázkem nebo jménem',
    'intro' => [
        'Nástroj vytvoří stolní stojánek na samolepicí lístečky. Vpředu je miska udělaná na míru bločku: zadáte stranu bločku od 50 do 105 mm (běžný má 76 mm) a hloubku od 8 do 30 mm, miska má kolem bločku milimetr vůle a vpředu výřez na palec. Za miskou stojí silueta z vašeho obrázku nebo jméno, široké 50 až 150 mm.',
        'Obrázek nahrajete jako SVG nebo jednoduchý kontrastní obrázek, vyberete ho z knihovny siluet, nebo napíšete jméno jedním ze třiceti písem. Mezi miskou a siluetou je žlábek na tužku, který jde vypnout. Stojánek se tiskne ve dvou dílech bez podpěr: silueta naležato, takže má čisté obě strany, a zasune se patkou do drážky v misce. Každý díl může mít jinou barvu.',
    ],
    'steps' => [
        ['name' => 'Vyberte obrázek, nebo napište jméno', 'text' => 'Silueta z knihovny, vlastní SVG či obrázek, nebo jméno do 16 znaků. Nahraný obrázek má přednost před jménem.'],
        ['name' => 'Zadejte rozměr bločku', 'text' => 'Strana bločku a hloubka misky. Na sto lístečků stačí 12 mm, na dva bločky na sobě 22 až 25 mm.'],
        ['name' => 'Nastavte siluetu', 'text' => 'Šířku měníte posuvníkem nebo tažením šipky v náhledu, tloušťku od 2,4 do 5 mm. Drážku na tužku zapnete nebo vypnete.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu. Při objednávce vyberete barvu misky i siluety; model i projekt pro slicer stáhnete zdarma.'],
    ],
    'faq' => [
        ['q' => 'Na jaké bločky stojánek je?', 'a' => 'Na čtvercové samolepicí bločky. Nejčastější rozměr je 76 × 76 mm, menší mají 51 mm a velké 101 mm; změřte ten svůj a zadejte stranu. Obdélníkové bločky nástroj zatím neumí.'],
        ['q' => 'Drží silueta v misce sama?', 'a' => 'Ano, patka sedí v drážce hluboké 6 mm s vůlí čtvrt milimetru na každou stranu. Kdo chce mít jistotu, kápne do drážky lepidlo.'],
        ['q' => 'Jaký obrázek se hodí?', 'a' => 'Silueta s širokým spodkem: zvíře, které sedí nebo stojí, dům, strom, srdce. Tvar, který se země dotýká jedním bodem, dostane širší patku, aby stál.'],
        ['q' => 'Mohou mít miska a silueta různé barvy?', 'a' => 'Ano, jsou to dva samostatné díly a každý se tiskne svým filamentem. Barvy vyberete při objednávce z těch, které jsou právě založené v tiskárnách.'],
        ['q' => 'Jak je stojánek velký?', 'a' => 'S kočkou širokou 90 mm a bločkem 76 mm má miska 85 × 104 mm a celý stojánek je vysoký 107 mm. Rozměry pro svůj návrh čtete vedle náhledu.'],
        ['q' => 'Jak zaplatím a jak stojánek dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Stojánek na bloček 76 mm se siluetou kočky širokou 90 mm a drážkou na tužku.',
        'Stojánek se jménem Jana psacím písmem, bez drážky na tužku.',
        'Malý stojánek na bloček 51 mm s hvězdou širokou 70 mm.',
    ],
];
