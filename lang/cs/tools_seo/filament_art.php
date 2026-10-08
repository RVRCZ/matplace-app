<?php

return [
    'title' => 'Obraz z filamentu: vrstvené desky, nebo jeden tisk',
    'description' => 'Z obrázku nebo fotky vznikne obraz na zeď v barvách filamentů: vrstvené desky v rámu, nebo jeden tisk s barvami nad sebou. Vytiskneme ho, nebo si ho stáhnete.',
    'h1' => 'Obraz z filamentu: fotka nebo kresba jako vrstvy plastu',
    'intro' => [
        'Nástroj převede obrázek nebo fotku do 1 až 8 barev filamentů, které máme na tiskové farmě skladem, a postaví z nich obraz na zeď o šířce 50 až 250 mm. Ve výchozím režimu je to vrstvený obraz: každá barva je samostatná deska 1,5 až 3 mm silná a desky se skládají za sebe jako vystřihovánka, zadní nese celou siluetu, přední jen svou barvu. Mezi deskami jsou distanční sloupky 2 až 5 mm, takže obraz má hloubku a stín; kdo chce tenký obraz, nechá desky naplocho. K tomu kulatý nebo čtvercový rám s drážkou na hřebík a volitelně místem na LED pásek.',
        'Druhý režim je jeden tisk: podklad a na něm barvy po 0,4 mm nad sebou. V každé vrstvě tisku je jediný filament, takže obraz vytiskne každá tiskárna výměnou filamentu ve výškách, které projekt obsahuje. U každé barvy vidíte cívku, kterou nástroj vybral, a můžete ji vyměnit za jinou z našeho katalogu, barvy přeskládat nebo sloučit. Vedle náhledu je návod: pořadí desek od zadní k přední s kresbou každé z nich.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek', 'text' => 'Obrázek nahrajete, vyberete z knihovny, nebo použijete dříve nahraný. Pozadí se odstraní samo; u fotky, která má vyplnit celou plochu, odstranění vypněte.'],
        ['name' => 'Zvolte, jak to vyrobit', 'text' => 'Vrstvený obraz z desek s rámem, nebo jeden tisk s barvami nad sebou. Nastavíte šířku, tloušťku desek a mezeru mezi nimi, případně rám a místo na LED pásek.'],
        ['name' => 'Dolaďte barvy', 'text' => 'Zvolíte počet barev a každé přiřadíte filament z našeho katalogu. Barvy můžete posunout dopředu či dozadu, nebo dvě sloučit v jednu.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu, přesnou cenu o krok dál. Desky si stáhnete každou zvlášť jako STL, nebo celý návrh jako projekt pro slicer, zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak se vrstvený obraz skládá?', 'a' => 'Každou desku vytisknete v její barvě a pak je lepíte podle návodu od zadní k přední. Distanční sloupky jsou součástí desky a zapadnou pod desku před ní. Stačí vteřinové lepidlo. Rám se tiskne dnem dolů a desky se vlepí dovnitř.'],
        ['q' => 'Co když mám jen jednobarevnou tiskárnu?', 'a' => 'Vrstvený obraz je pro ni jako dělaný: každá deska je jednobarevný tisk. Jeden tisk s barvami nad sebou zvládne také, projekt obsahuje výměny filamentu ve správných výškách; tiskárna se zastaví a vy filament vyměníte.'],
        ['q' => 'Z jakého obrázku to vyjde nejlépe?', 'a' => 'Z kresby nebo loga s několika plnými barvami. Z fotky nástroj vybere hlavní barvy a výsledek je plakátový; pro portrét se hodí spíš litofanie. Linky tenčí než 0,8 mm by se jako samostatná deska zlomily, proto je nástroj přiřadí barvě za nimi a řekne to.'],
        ['q' => 'Jaký rám a jak se věší?', 'a' => 'Kulatý nebo čtvercový, 6 až 20 mm široký, s drážkou na hřebík v zadní stěně. S volbou „Místo na LED pásek“ stojí desky o 10 mm dál od zadní stěny a dole je výřez na kabel; pásek si pořídíte sami.'],
        ['q' => 'Z čeho se obraz tiskne?', 'a' => 'Z PLA, které má největší výběr barev a tiskne se naplocho bez podpěr. Barvy jsou cívky, které máme opravdu skladem, takže výtisk vypadá jako náhled.'],
        ['q' => 'Jak zaplatím a jak obraz dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Sněhulák jako vrstvený obraz z pěti desek v kulatém rámu 180 mm, s distančními sloupky.',
        'Vánoční stromek z knihovny jako čtyři desky bez rámu, 150 mm, zadní deska nese celou siluetu.',
        'Perníček jako jeden tisk 120 × 140 mm: podklad a tři barvy po 0,4 mm nad sebou.',
    ],
];
