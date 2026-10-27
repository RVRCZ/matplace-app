<?php

// Stránka pro dotaz „vánoční ozdoba 3d tisk“ (docs/AP.md §6). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Vánoční ozdoba na 3D tisk z obrázku nebo jména: model zdarma',
    'heading' => 'Vánoční ozdoba na 3D tisk z obrázku nebo jména',
    'description' => 'Nahrajete obrázek nebo napíšete jméno a vznikne vánoční ozdoba v barvách filamentů s očkem na stužku. Vytiskneme ji, nebo si model stáhnete zdarma.',
    'h1' => 'Jak ozdoba z obrázku vzniká, kolik barev zvládne a kolik stojí',
    'intro' => [
        'Nástroj udělá z obrázku plochou vánoční ozdobu s očkem na stužku. Nahrajete PNG, JPG, WebP nebo SVG, vyberete motiv z naší knihovny (asi 137 motivů, 8 z nich barevných), nebo místo obrázku napíšete jméno v jednom ze třiceti písem. Pozadí se odstraní samo a obrázek se převede na 1 až 8 barev; každá se přiřadí k nejbližšímu filamentu, který máme na farmě opravdu skladem. Ozdoba kopíruje obrys obrázku, nebo má tvar kruhu či hvězdy, je široká 40 až 150 mm a silná 2 až 5 mm. Barvy leží jedna na druhé, každá o 0,4 až 1,2 mm výš, takže ozdobu vytiskne jakákoli tiskárna výměnou filamentu ve výškách, které projekt ke stažení obsahuje; naše farma vytiskne až čtyři barvy najednou.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): perníček z knihovny široký 80 mm má 10 g, tiskne se 18 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky; šest kusů vyjde na 160 Kč, tedy 27 Kč za ozdobu, protože příprava zakázky se platí jednou. Kalkulace počítá s jednou barvou, cívky pro další barvy potvrdíte při objednávce. Ozdoba se tiskne naplocho, lícem nahoru a bez podpěr, váží kolem deseti gramů a drží i na tenké stužce. STL, ZIP s jedním STL pro každou barvu i projekt pro slicer si stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek nebo napište jméno', 'text' => 'Obrázek nahrajete, vyberete z knihovny, nebo použijete ten, který jste nahráli dřív. Jméno či krátký text může mít jeden nebo dva řádky a vyberete k němu jedno ze třiceti písem.'],
        ['name' => 'Vyberte tvar, velikost a očko', 'text' => 'Ozdoba kopíruje obrázek, nebo je to kruh či hvězda. Nastavíte šířku, tloušťku a okraj kolem obrázku do 3 mm. Očko s otvorem 3 až 6 mm posunete posuvníkem nebo tažením v náhledu kamkoli po obrysu; tlačítko ho vrátí nahoru.'],
        ['name' => 'Upravte barvy', 'text' => 'Zvolíte počet barev a každé můžete v okně barev přiřadit jiný filament. Změníte, která barva leží na které, nebo dvě barvy sloučíte v jednu. Náhled ve 3D ukazuje ozdobu v barvách filamentů a rozměry v milimetrech.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Nebo si zdarma a bez registrace stáhnete STL, ZIP s jedním STL pro každou barvu či projekt pro slicer.'],
    ],
    'faq' => [
        ['q' => 'Jaký obrázek se na ozdobu hodí?', 'a' => 'Nejlépe kresba s několika plnými barvami, například perníček nebo stromek. Z fotky nástroj vybere hlavní barvy; kontrast, jas a sytost doladíte posuvníky. Když má být vidět celá fotka, vypněte odstranění pozadí.'],
        ['q' => 'Jak velká ozdoba se hodí na stromeček?', 'a' => 'Na běžný stromek 60 až 90 mm, na malý stolní 40 až 50 mm; ozdoba 80 mm váží kolem 10 g. Otvor očka 3 až 6 mm projde stužkou i háčkem. Větší ozdoby do 150 mm se hodí na dveře nebo do okna.'],
        ['q' => 'Vytiskne barevnou ozdobu i tiskárna s jednou tryskou?', 'a' => 'Ano. Barvy leží nad sebou, takže se v dané výšce jen vymění filament; projekt pro OrcaSlicer, Bambu Studio a PrusaSlicer tyto výměny obsahuje. Volby „Barvy zarovno s povrchem“ a „Lem ve vlastní barvě“ potřebují tiskárnu, která mění filament sama (AMS, MMU, ACE).'],
        ['q' => 'Kolik barev vytisknete u vás?', 'a' => 'Až čtyři v jednom tisku: podklad a tři barvy nad ním. Cívku pro každou barvu potvrdíte při objednávce, ty z návrhu jsou předvybrané. Ozdobu s více barvami si zdarma stáhnete a vytisknete na své tiskárně.'],
        ['q' => 'Z jakého materiálu se ozdoba tiskne?', 'a' => 'Doporučujeme PLA. Ozdoba z něj je lehká a tiskne se naplocho, lícem nahoru a bez podpěr. PETG zvolte jen tehdy, když bude ozdoba viset někde v teple.'],
        ['q' => 'Na co nástroj upozorní?', 'a' => 'Na čáry tenčí než tryska. Dále na to, že oddělené kousky obrázku spojil malým můstkem, aby držely pohromadě, a na to, že obrázek má méně odlišných barev, než jste zadali.'],
    ],
    'examples' => [
        'Perníček široký 80 mm: hnědý podklad, bílá poleva a červené knoflíky; 10 g, 18 minut, 99 Kč.',
        'Ozdobený vánoční stromek široký 90 mm v pěti barvách.',
        'Červená vánoční baňka 70 mm s bílým pruhem, bez okraje.',
    ],
];
