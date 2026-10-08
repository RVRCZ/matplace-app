<?php

return [
    'title' => 'Klíčenka se jménem nebo obrázkem pro 3D tisk',
    'description' => 'Napíšete jméno, nebo nahrajete obrázek. Vznikne klíčenka v barvách filamentů s očkem na kroužek. Vytiskneme ji, nebo si model zdarma stáhnete.',
    'h1' => 'Klíčenka se jménem, logem nebo vlastním obrázkem',
    'intro' => [
        'Nástroj vytvoří klíčenku ze jména, krátkého nápisu nebo z obrázku. Napíšete jeden či dva řádky a vyberete jedno ze třiceti písem, nebo nahrajete PNG, JPG, WebP či SVG, případně vyberete motiv z knihovny. Klíčenka má tvar zaobleného obdélníku, kruhu, nebo kopíruje obrys motivu. Šířku nastavíte od 30 do 100 mm, tloušťku od 2,4 do 6 mm.',
        'Očko na kroužek má otvor 3 až 8 mm a sedí tam, kam ho posunete: posuvníkem, nebo tažením přímo v náhledu. Jméno na podkladu jsou dvě barvy nad sebou, takže tisk stačí jednou zastavit a vyměnit filament. Takovou klíčenku vytiskneme i na naší tiskové farmě, stejně jako obrázek až ve čtyřech barvách: podklad a tři další. Cívku pro každou barvu potvrdíte při objednávce; obrázek s více barvami si zdarma stáhnete pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Napište jméno, nebo vyberte obrázek', 'text' => 'Text může mít dva řádky po 24 znacích. Místo něj můžete nahrát obrázek nebo vybrat motiv z knihovny; pozadí obrázku se odstraní samo.'],
        ['name' => 'Nastavte tvar, rozměry a očko', 'text' => 'Zvolíte obdélník, kruh, nebo tvar podle obrysu. Očko posunete po obrysu posuvníkem či tažením v náhledu a nastavíte průměr otvoru podle kroužku.'],
        ['name' => 'Vyberte barvy', 'text' => 'Podklad i písmo dostanou filament z těch, které máme opravdu skladem. U obrázku zvolíte počet barev, jejich pořadí a filament pro každou z nich.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu, přesnou cenu a dobu tisku o krok dál. Model i projekt pro slicer s výměnou filamentu stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak velký otvor potřebuje kroužek na klíče?', 'a' => 'Na běžný kroužek o průměru 25 až 30 mm stačí otvor 5 mm, který je přednastavený. Na karabinu nebo tlustší kroužek ho zvětšete až na 8 mm.'],
        ['q' => 'Z čeho se klíčenka tiskne a vydrží v kapse?', 'a' => 'Doporučujeme PLA nebo pevnější PETG. Při tloušťce 3 mm klíčenka běžné nošení vydrží; pro klíče od auta, které leží na slunci, zvolte PETG, PLA měkne kolem 55 °C.'],
        ['q' => 'Kolik barev může klíčenka mít?', 'a' => 'Jméno na podkladu má dvě barvy. Obrázek se převede na 1 až 8 barev, každá leží o krok výš než ta pod ní. Na naší farmě vytiskneme až čtyři barvy najednou, tedy podklad a tři další; návrh s více barvami si stáhnete s projektem, který výměny filamentu obsahuje.'],
        ['q' => 'Co když se písmena jména nedotýkají?', 'a' => 'U tvaru podle obrysu nástroj oddělená písmena a tečky spojí krátkým můstkem a upozorní na to. U obdélníku a kruhu drží všechno na podkladu.'],
        ['q' => 'Jak drobný může nápis být?', 'a' => 'Čáry tenčí než tryska tiskárny nevyjdou a nástroj na ně upozorní. Delší jméno proto dejte na širší klíčenku, nebo ho rozdělte do dvou řádků.'],
        ['q' => 'Jak zaplatím a jak klíčenku dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Klíčenka se jménem Jana na zaobleném obdélníku širokém 55 mm, očko vlevo.',
        'Klíčenka s tlapkou podle obrysu motivu, 50 mm, očko nahoře.',
        'Kulatá klíčenka 45 mm s usměvavou hvězdou.',
    ],
];
