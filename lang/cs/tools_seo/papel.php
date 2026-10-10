<?php

return [
    'title' => 'Papel picado z fotky: portrét nebo silueta pro 3D tisk',
    'description' => 'Nahrajete fotku tváře a vznikne portrét ve dvou barvách v prolamovaném rámu, nebo silueta vyříznutá v tenkém panelu. Zdarma, tisk u nás i stažení.',
    'h1' => 'Papel picado: portrét z fotky nebo prolamovaný panel z obrázku',
    'intro' => [
        'Papel picado je mexická girlanda z barevného papíru, do kterého se vysekávají obrázky a ornamenty; věší se o svátcích, svatbách a hlavně na Den mrtvých. Nástroj dělá totéž z plastu, a to dvěma způsoby. Silueta je tenký panel, ve kterém je obrázek vyříznutý skrz jako okno. Portrét je fotka tváře převedená na kresbu ve dvou barvách, která leží na pevném podkladu, takže z ní nic nevypadne ani se neodlomí. Nahrát můžete fotku, kresbu nebo siluetu (PNG, JPG, WebP, SVG), případně vybrat motiv z knihovny.',
        'U portrétu je celý panel světlá podkladová deska a na ní je druhou, tmavou barvou vytištěný rám a tmavé plochy tváře: vlasy, oči, rty, stíny. Nástroj umí oddělit postavu od pozadí; pozadí pak může zůstat světlé, nebo být tmavé a posázené květy, kterými prosvítá podklad. Posuvníky nastavíte, kolik má být tmavých ploch, jak jemná má být kresba a kolik se má oříznout pod rameny. Portrét v okně posunete, zvětšíte a pootočíte přímo v náhledu. Tiskne se naplocho, filament se mění jednou ve výšce podkladu. Kolem okna je okraj prolamovaný jedním ze sedmi vzorů: lidové květy, květy, kosočtverce, puntíky, srdíčka, lístky nebo hvězdy. Nastavíte jeho šířku a hustotu vzoru. Zoubky s dírkou mohou být jen dole, nebo po celém obvodu, a v horních rozích jsou dva otvory na šňůru. U siluety nástroj sám přichytí tenkými spojkami části, které by zůstaly viset ve vzduchu, například obličej ve vyříznutém pozadí. Panel má šířku a výšku 80 až 250 mm.',
    ],
    'steps' => [
        ['name' => 'Vyberte siluetu nebo portrét a nahrajte obrázek', 'text' => 'Po nahrání fotky se nástroj přepne na portrét, u kresby a motivu z knihovny na siluetu. Volbu můžete kdykoli změnit.'],
        ['name' => 'Dolaďte kresbu', 'text' => '„Světlo / stín“ posouvá hranici mezi tmavou a světlou, „Kresba portrétu“ určuje, jak jemné rysy zůstanou. Vedle původní fotky vidíte portrét přesně tak, jak se vytiskne.'],
        ['name' => 'Zvolte rám, velikost a umístění', 'text' => 'Vzor a šířka okraje, zoubky, otvory na šňůru. Portrét v okně posunete tažením rámečku v náhledu, roh mění velikost a úchyt otáčí.'],
        ['name' => 'Vyberte barvy a objednejte tisk, nebo stáhněte', 'text' => 'Barvy podkladu a rámu vybíráte z cívek, které máme na farmě. Tisk u nás objednáte jedním krokem, projekt 3MF se dvěma barvami i STL stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jaká fotka se hodí na portrét?', 'a' => 'Ostrá, dobře osvětlená tvář zepředu nebo z poloprofilu. Klidné pozadí pomáhá, ale není nutné: postavu od pozadí oddělíme. Nejlíp vychází kontrast tmavých vlasů a světlé pleti; u světlých vlasů zvolte tmavé pozadí kolem postavy, hlava pak má jasný obrys.'],
        ['q' => 'Proč je portrét na podkladu a není vyříznutý skrz?', 'a' => 'Tvář má spoustu drobných ostrůvků: zorničky, odlesky brýlí, pramínky vlasů. Vyříznuté skrz by nic nedrželo. Na podkladu drží každý detail a kresba může být mnohem jemnější.'],
        ['q' => 'Jak se tisknou dvě barvy?', 'a' => 'Podklad se vytiskne první barvou a od jeho horní hrany pokračuje tisk druhou. Je to jedna výměna filamentu ve výšce, standardně ve 2 mm. Naše farma ji udělá sama. Stažený projekt 3MF pro OrcaSlicer i PrusaSlicer výměnu obsahuje, takže stačí tiskárna s jednou tryskou. Z boku je vidět světlá hrana podkladu. Jednou barvou to jde taky: STL je jedno těleso a kresba vystupuje jako reliéf.'],
        ['q' => 'Proč jsou v siluetě tenké svislé čárky?', 'a' => 'To jsou spojky. Kousek papíru, který by po vyříznutí okolí nic nedrželo, je jimi přichycen k okraji otvoru. Šířku spojek nastavíte od 0,8 do 2,4 mm. Portrét spojky nepotřebuje.'],
        ['q' => 'Jak silný má panel být?', 'a' => 'Silueta od 0,8 do 2 mm, výchozí je 1,2 mm. Portrét má podklad 1 až 3 mm a na něm kresbu 0,3 až 1,2 mm; výchozí 2 + 0,6 mm dává pevnou destičku, která se neprohne.'],
        ['q' => 'Jak zaplatím a jak panel dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Portrét 190 × 190 mm z kreslené tváře z knihovny: krémový podklad, tmavě modrý rám s lidovými květy a zoubky po celém obvodu.',
        'Silueta 150 × 213 mm s cukrovou lebkou z knihovny a okrajem z květů.',
        'Rám 160 × 120 mm s lidovými květy a prázdným oknem: destička na vlastní nápis nebo nalepenou fotku.',
    ],
];
