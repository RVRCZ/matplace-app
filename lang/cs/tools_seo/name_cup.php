<?php

return [
    'title' => 'Stojánek na tužky ze jména pro 3D tisk',
    'description' => 'Napíšete jméno a vznikne stojánek na tužky v jeho tvaru: písmena jsou stěny, mezi nimi místo na pera. Vytiskneme ho, nebo si model zdarma stáhnete.',
    'h1' => 'Stojánek na tužky ve tvaru jména',
    'intro' => [
        'Nástroj vytvoří stojánek na tužky, který má tvar jména. Napíšete jméno do 14 znaků a vyberete písmo; nejlépe vychází psací, kde se písmena spojují v jednu širokou kapsu. Šířku nastavíte od 80 do 250 mm a výšku od 40 do 120 mm. Stěna je silná 1,6 mm, dno 2 mm, obojí jde změnit.',
        'Kapsa kopíruje tvar písmen rozšířený tak, aby se do ní vešly tužky, pera i nůžky; náhled ukáže, jak široké je její nejširší místo. Oka písmen jako e nebo a se vyplní, písmena, která se nedotýkají, nástroj spojí příčkou. S podstavcem stojánek stojí pevněji a podstavec může mít jinou barvu, na to stačí jedna výměna filamentu.',
    ],
    'steps' => [
        ['name' => 'Napište jméno', 'text' => 'Krátké jméno vyjde s širší kapsou. U delšího zvětšete šířku, aby zůstalo místo na tužky.'],
        ['name' => 'Vyberte písmo', 'text' => 'Písmo vyberete ze třiceti. Psané, ve kterém se písmena spojují, dává jednu souvislou kapsu. U ostatních jsou písmena samostatné kapsy; kde se nedotýkají, spojí je příčka.'],
        ['name' => 'Nastavte rozměry a podstavec', 'text' => 'Šířku a výšku měníte posuvníky nebo tažením šipek v náhledu. Pod rozměry vidíte šířku kapsy; když je pro tužku úzká, nástroj upozorní.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu, přesnou cenu a dobu tisku o krok dál. Model i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Vejdou se do stojánku tužky?', 'a' => 'Tužka má 7 až 8 mm, potřebuje tedy kapsu širokou aspoň 9 mm. Nástroj šířku nejširšího místa kapsy vypíše a na úzkou kapsu upozorní. U jména Jana o šířce 160 mm má nejširší místo kapsy přes 30 mm.'],
        ['q' => 'Jak vysoký stojánek zvolit?', 'a' => 'Na pastelky a krátké tužky stačí 60 mm, na běžné tužky a pera 80 až 90 mm. Čím vyšší stojánek, tím víc se vyplatí podstavec.'],
        ['q' => 'Z čeho se stojánek tiskne?', 'a' => 'Doporučujeme PLA. Tiskne se nastojato a bez podpěr; stěna 1,6 mm jsou čtyři obvody tryskou 0,4 mm, což je pevné i u vysokého stojánku.'],
        ['q' => 'Může být podstavec jinou barvou?', 'a' => 'Ano. Podstavec je spodní 3 mm výtisku, takže stačí jedna výměna filamentu v této výšce. Na naší farmě si obě barvy vyberete při objednávce.'],
        ['q' => 'Jak dlouhé jméno se vejde na podložku?', 'a' => 'Stojánek může být široký až 250 mm, což je podložka naší farmy. Náhled u rozměrů ukazuje, jestli se na ni vejde.'],
        ['q' => 'Jak zaplatím a jak stojánek dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Stojánek se jménem Jana psacím písmem, široký 160 mm a vysoký 80 mm, s podstavcem v jiné barvě.',
        'Stojánek TOM bezpatkovým písmem, 150 × 90 mm, každé písmeno je samostatná kapsa.',
        'Nízký stojánek Ela na pastelky, 110 × 60 mm, bez podstavce.',
    ],
];
