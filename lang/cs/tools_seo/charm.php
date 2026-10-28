<?php

// Stránka pro dotaz „přívěsek z obrázku“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Přívěsek z obrázku nebo jména pro 3D tisk: návrh zdarma',
    'heading' => 'Přívěsek z vlastního obrázku nebo jména pro 3D tisk: na klíče, tašku i batoh',
    'description' => 'Z obrázku, fotky nebo jména vznikne přívěsek v barvách filamentů s očkem kdekoli na obrysu. Na klíče, tašku nebo dárek. Tisk, nebo stažení zdarma.',
    'h1' => 'Jak přívěsek vzniká, kam dát očko a kolik stojí',
    'intro' => [
        'Nástroj vytvoří z obrázku nebo jména přívěsek na klíče, tašku, batoh, jezdec zipu nebo jmenovku na dárek. Nahrajete PNG, JPG, WebP či SVG, vyberete motiv z knihovny, nebo napíšete jméno jedním ze třiceti písem; oddělené kousky písma nástroj spojí můstkem, aby přívěsek držel pohromadě. Pozadí zmizí samo a obrázek se převede na 1 až 8 barev filamentů, které máme na farmě skladem. Přívěsek kopíruje obrys obrázku, nebo je to kruh či obdélník, široký 20 až 120 mm a silný 2 až 6 mm, s okrajem kolem obrázku do 3 mm. Očko posunete posuvníkem nebo tažením v náhledu kamkoli po obrysu a nastavíte mu otvor 2 až 6 mm a stěnu 1,5 až 3 mm. Barvy leží jedna na druhé, každá o 0,6 mm výš, pokud to nezměníte.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): veselý duch z knihovny široký 45 mm, 3 mm silný, má 3 g, tiskne se 7 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/mca5ddgfzctt); jméno Ela psacím písmem široké 60 mm má 3,7 g a tiskne se 12 minut (kalkulace /c/gmfsgcpwmjl8). Víc kusů stejného návrhu zadáte jako počet kusů a cena za kus klesne; přívěsky s různými jmény jsou různé návrhy. Na farmě vytiskneme až čtyři barvy najednou, podklad a tři další; cívku každé barvy potvrdíte při objednávce. STL, ZIP s jedním STL pro každou barvu i projekt pro slicer si stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek nebo napište jméno', 'text' => 'Obrázek nahrajete, vyberete z knihovny, nebo použijete některý z dříve nahraných. Místo obrázku můžete napsat jméno či krátký text na jeden nebo dva řádky.'],
        ['name' => 'Dolaďte obrys a barvy', 'text' => 'Posuvník od 0 do 100 určuje, kolik pozadí zmizí; hrany lze vyhladit až o 1 mm. Zvolíte počet barev, v okně barev každé přiřadíte filament a případně dvě barvy sloučíte v jednu.'],
        ['name' => 'Nastavte velikost a očko', 'text' => 'Zadáte šířku, tloušťku a okraj kolem obrázku do 3 mm, případně zkosenou hranu. Očko chytíte v náhledu a přetáhnete tam, odkud má přívěsek viset.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Model si také zdarma a bez registrace stáhnete jako STL, ZIP s jedním STL pro každou barvu nebo projekt pro slicer.'],
    ],
    'faq' => [
        ['q' => 'Můžu místo obrázku použít jen jméno?', 'a' => 'Ano. Napíšete jeden nebo dva řádky a vyberete jedno ze třiceti písem, od tučných bezpatkových po psaná rukou. Oddělené kousky nástroj spojí malým můstkem, aby přívěsek držel pohromadě, a upozorní na to.'],
        ['q' => 'Kam můžu dát očko a co do něj navléknout?', 'a' => 'Kamkoli na obrys: posuvníkem, nebo tažením přímo ve 3D náhledu. Otvor má 2 až 6 mm, výchozí jsou 3 mm, stěna kroužku 1,5 až 3 mm. Do otvoru 3 mm projde běžný kroužek na klíče i karabinka na batoh; kroužek ani karabinka nejsou součástí výtisku.'],
        ['q' => 'Čím se liší od klíčenky?', 'a' => 'Klíčenka je destička s jménem a tvarem z nabídky, přívěsek kopíruje obrys vašeho obrázku nebo písma a má očko kdekoli. Na jmenovku na dárek nebo na zip batohu je přívěsek, na klíče se jménem na destičce klíčenka.'],
        ['q' => 'Jak se barvy tisknou?', 'a' => 'Barvy leží nad sebou, každá o 0,4 až 1,2 mm výš. Každá vrstva je tak z jednoho filamentu a stačí ho ve správné výšce vyměnit; projekt pro OrcaSlicer, Bambu Studio i PrusaSlicer výměny obsahuje. Barvy zarovno s povrchem a lem ve vlastní barvě vyžadují tiskárnu, která mění filament sama (AMS, MMU, ACE).'],
        ['q' => 'Kolik barev vytisknete na farmě?', 'a' => 'Až čtyři v jednom tisku: podklad a tři barvy nad ním. Cívku pro každou barvu potvrdíte při objednávce, ty z návrhu jsou předvybrané. Návrh s více barvami si zdarma stáhnete pro svou tiskárnu.'],
        ['q' => 'Z čeho se přívěsek tiskne a vydrží na klíčích?', 'a' => 'Doporučujeme PLA; tiskne se naplocho, lícem nahoru a bez podpěr. Tloušťka 3 mm a stěna očka 2 mm vydrží klíče v kapse, na tenké motivy pod 0,8 mm nástroj upozorní. Pro přívěsek, který se bude zahřívat, například v autě na slunci, je vhodnější PETG.'],
    ],
    'examples' => [
        'Veselý duch široký 45 mm, bílý s tmavýma očima a růžovými tvářemi: 3 g, 7 minut, 99 Kč.',
        'Usměvavá žlutá hvězda široká 50 mm, očko na cípu.',
        'Jméno Ela psacím písmem, šířka 60 mm, písmena spojená v jeden kus: 3,7 g, 12 minut.',
    ],
];
