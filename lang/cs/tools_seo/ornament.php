<?php

return [
    'title' => 'Vánoční ozdoba z vlastního obrázku nebo jména',
    'description' => 'Nahrajete obrázek nebo napíšete jméno a vznikne vánoční ozdoba v barvách filamentů s očkem na stužku. Vytiskneme ji, nebo si model zdarma stáhnete.',
    'h1' => 'Vánoční ozdoba na stromeček z obrázku, fotky nebo jména',
    'intro' => [
        'Nástroj udělá z obrázku plochou vánoční ozdobu s očkem na stužku. Nahrajete PNG, JPG, WebP nebo SVG, vyberete motiv z naší knihovny, nebo místo obrázku napíšete jméno. Pozadí se odstraní samo a obrázek se převede na 1 až 8 barev. Každá barva se přiřadí k nejbližšímu filamentu, který máme na tiskové farmě opravdu skladem. Ozdoba kopíruje obrys obrázku, nebo má tvar kruhu či hvězdy, je široká 40 až 150 mm a silná 2 až 5 mm.',
        'Barvy leží jedna na druhé a každá je o 0,4 až 1,2 mm výš než ta pod ní. Každou vrstvu tak tvoří jeden filament a ozdobu vytiskne jakákoli tiskárna: stačí vyměnit filament ve výškách, které projekt ke stažení obsahuje. Naše tisková farma vytiskne až čtyři barvy najednou: podklad a tři další. Cívku pro každou barvu potvrdíte při objednávce; návrhy s více barvami si zdarma stáhnete pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek nebo napište jméno', 'text' => 'Obrázek nahrajete, vyberete z knihovny asi 137 motivů (8 z nich je barevných), nebo použijete ten, který jste nahráli dřív. Jméno či krátký text může mít jeden nebo dva řádky a vyberete k němu jedno ze třiceti písem.'],
        ['name' => 'Vyberte tvar, velikost a očko', 'text' => 'Ozdoba kopíruje obrázek, nebo je to kruh či hvězda. Nastavíte šířku, tloušťku a okraj kolem obrázku do 3 mm. Očko s otvorem 3 až 6 mm posunete posuvníkem nebo tažením v náhledu kamkoli po obrysu; tlačítko ho vrátí nahoru.'],
        ['name' => 'Upravte barvy', 'text' => 'Zvolíte počet barev a každé můžete v okně barev přiřadit jiný filament. Změníte, která barva leží na které, nebo dvě barvy sloučíte v jednu. Náhled ve 3D ukazuje ozdobu v barvách filamentů a rozměry v milimetrech.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte orientační cenu, přesnou cenu a dobu tisku o krok dál. Tisk zaplatíte z předplaceného kreditu. Nebo si zdarma a bez registrace stáhnete STL, ZIP s jedním STL pro každou barvu či projekt pro slicer.'],
    ],
    'faq' => [
        ['q' => 'Jaký obrázek se na ozdobu hodí?', 'a' => 'Nejlépe kresba s několika plnými barvami, například perníček nebo stromek. Z fotky nástroj vybere hlavní barvy; kontrast, jas a sytost doladíte posuvníky. Když má být vidět celá fotka, vypněte odstranění pozadí.'],
        ['q' => 'Vytiskne barevnou ozdobu i tiskárna s jednou tryskou?', 'a' => 'Ano. Barvy leží nad sebou, takže se v dané výšce jen vymění filament; projekt pro OrcaSlicer, Bambu Studio a PrusaSlicer tyto výměny obsahuje. Volby „Barvy zarovno s povrchem“ a „Lem ve vlastní barvě“ potřebují tiskárnu, která mění filament sama (AMS, MMU, ACE).'],
        ['q' => 'Kolik barev vytisknete u vás?', 'a' => 'Až čtyři v jednom tisku: podklad a tři barvy nad ním. Cívku pro každou barvu potvrdíte při objednávce, ty z návrhu jsou předvybrané. Ozdobu s více barvami si zdarma stáhnete a vytisknete na své tiskárně.'],
        ['q' => 'Z jakého materiálu se ozdoba tiskne?', 'a' => 'Doporučujeme PLA. Ozdoba z něj je lehká a tiskne se naplocho, lícem nahoru a bez podpěr. PETG zvolte jen tehdy, když bude ozdoba viset někde v teple.'],
        ['q' => 'Na co nástroj upozorní?', 'a' => 'Na čáry tenčí než tryska. Dále na to, že oddělené kousky obrázku spojil malým můstkem, aby držely pohromadě, a na to, že obrázek má méně odlišných barev, než jste zadali.'],
        ['q' => 'Jak zaplatím a jak ozdobu dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Perníček široký 80 mm: hnědý podklad, bílá poleva a červené knoflíky.',
        'Ozdobený vánoční stromek široký 90 mm v pěti barvách.',
        'Červená vánoční baňka 70 mm s bílým pruhem, bez okraje.',
    ],
];
