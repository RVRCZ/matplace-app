<?php

// Stránka pro dotaz „3d mapa města k tisku“ (docs/AP.md §1, §5). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => '3D mapa města k tisku z OpenStreetMap: model zdarma online',
    'heading' => '3D mapa města k tisku z názvu místa',
    'description' => 'Zadáte město, čtvrť nebo místo a do minuty máte 3D mapu k tisku: budovy s výškami, ulice a voda z OpenStreetMap, nebo reliéf krajiny. STL a 3MF zdarma.',
    'h1' => 'Co mapa obsahuje, jak se tiskne ve třech barvách a kde jsou hranice',
    'intro' => [
        'Napište město, obec, čtvrť nebo památku, vyberte rozsah a dostanete mapu jako tenkou desku k tisku. Město má budovy se skutečnými výškami z OpenStreetMap (kde výška chybí, platí ta, kterou zadáte), ulice podle druhu od dálnic po pěšiny, řeky a rybníky zapuštěné do podkladu, železnici a parky. Krajina je reliéf terénu z výškových dat Mapzen s rozlišením kolem 30 m a nastavitelným převýšením, s vodou a silnicemi na povrchu a obcemi jako nízkými bloky. Žádná umělá inteligence nic neodhaduje: data jsou přesná, na mapě je jejich datum a model je hotový do minuty. Rozsah města je 500 m, 1 km nebo 2 km, krajiny 2 až 20 km; deska má 80 až 250 mm podle podložky, měřítko se dopočítá a ukáže, na rám přijde vyvýšený název. Ve stylu Miniatura mají budovy zaoblené rohy a jsou o půl vyšší, ve stylu Hladká drží přesné hrany a skutečné výšky. Před stavbou vidíte náhled oblasti shora.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): pražské Staré Město, čtverec 500 m na desce 150 mm (měřítko asi 1 : 3 300), styl Hladká, má 85 g, tiskne se 3 h 42 min a stojí u nás 240 Kč jednou barvou, bez dopravy. Mapa se tiskne naplocho bez podpěr. Podklad, silnice a budovy jsou tři barvy nad sebou: na naší farmě se filament vymění sám ve dvou výškách, projekt 3MF pro OrcaSlicer a PrusaSlicer má výměny v sobě a STL je jedno těleso pro tisk jednou barvou. Bez účtu uděláte tři mapy denně, s účtem dvacet; náhled oblasti se do limitu nepočítá.',
    ],
    'steps' => [
        ['name' => 'Zadejte místo', 'text' => 'Název města, čtvrti, ulice nebo památky; nebo souřadnice. Vyberte, které místo myslíte, a zvolte město nebo krajinu a rozsah: město 500 m až 2 km, krajina 2 až 20 km.'],
        ['name' => 'Prohlédněte si náhled a nastavte mapu', 'text' => 'Náhled oblasti ukazuje budovy, silnice, vodu a zeleň, jak se vytisknou. Zvolte styl, velikost desky, rám s názvem, u města výšku budov bez údaje a silnice vyvýšené nebo zapuštěné, u krajiny převýšení.'],
        ['name' => 'Vyberte barvy', 'text' => 'Podklad, silnice a budovy, nebo podklad a terén, každý svou barvou. Barvy leží nad sebou podle výšky, takže je zvládne každá tiskárna s výměnou filamentu.'],
        ['name' => 'Vytvořte mapu a tiskněte nebo stáhněte', 'text' => 'Model se staví na serveru, město 1 km do půl minuty. Pak vidíte rozměry; přesnou cenu a tisk u nás najdete o krok dál, STL a projekt 3MF s výměnami stáhnete zdarma.'],
    ],
    'faq' => [
        ['q' => 'Odkud jsou data a jak jsou přesná?', 'a' => 'Budovy, ulice, voda a železnice z OpenStreetMap, tedy z mapy, kterou doplňují lidé po celém světě; ve městech je velmi úplná, výšky budov tam, kde je někdo zapsal (jinak platí výška, kterou zadáte). Výšky terénu z dat Mapzen (AWS Open Data) s rozlišením kolem 30 m. Na mapě je datum dat.'],
        ['q' => 'Jak velké město se vejde?', 'a' => 'Čtverec 500 m, 1 km nebo 2 km kolem středu. Na desce 150 mm je kilometr v měřítku asi 1 : 7 000, budova 20 m je 3 mm vysoká. Větší město na jedné desce by mělo budovy pod velikost trysky; na velké území vyberte krajinu.'],
        ['q' => 'Jak se tisknou tři barvy?', 'a' => 'Podklad první barvou, od jeho horní hrany silnice druhou, nad nimi budovy třetí: dvě výměny filamentu ve výšce. Naše farma je udělá sama, projekt 3MF pro OrcaSlicer a PrusaSlicer má výměny v sobě. Jednou barvou to jde taky: STL je jedno těleso.'],
        ['q' => 'Můžu nahrát snímek mapy?', 'a' => 'Ne. Z obrázku by se dalo jen hádat; z názvu místa dostanete přesné budovy a ulice. Když místo nenajdeme, zadejte souřadnice, třeba z Mapy.cz nebo Google Maps.'],
        ['q' => 'Smím mapu prodávat?', 'a' => 'Ano. Data OpenStreetMap jsou pod licencí ODbL: model si můžete vytisknout, darovat i prodávat, jen uveďte „© přispěvatelé OpenStreetMap“. Výšková data Mapzen jsou volně k použití.'],
        ['q' => 'Kolik map můžu udělat?', 'a' => 'Mapové služby jsou veřejné, proto bez účtu tři mapy denně, s účtem dvacet. Náhled oblasti se do limitu nepočítá.'],
    ],
    'examples' => [
        'Město 500 m na desce 150 mm, styl Hladká: budovy podle výšek, hlavní ulice, potok s rybníkem, železnice a rám s názvem; 85 g, 3 h 42 min.',
        'Totéž místo ve stylu Miniatura: zaoblené budovy o půl vyšší, širší ulice, park zapuštěný do podkladu.',
        'Krajina 5 km s kopcem uprostřed, převýšení 2×, na desce 150 mm.',
    ],
];
