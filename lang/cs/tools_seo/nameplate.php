<?php

// Stránka pro dotaz „jmenovka na dárek 3d tisk“ (docs/AP.md §6). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Jmenovka na dárek a cedulka se jménem z 3D tiskárny, zdarma',
    'heading' => 'Jmenovka na dárek z 3D tiskárny',
    'description' => 'Vyberete tvar destičky, napíšete jméno, přidáte obrázek a očko; každá vrstva má barvu filamentu. Jmenovku vytiskneme, nebo si model stáhnete zdarma.',
    'h1' => 'Jak jmenovku poskládat z vrstev, kolik stojí a kam se hodí',
    'intro' => [
        'Jmenovku si tu poskládáte z vrstev. Destička má jeden z devatenácti tvarů: šestiúhelník, srdce, hvězda, mrak, kost, stuha, šipka, domek, auto, kočka, ryba, štít, bublina a další. Na ni položíte jméno v jednom ze třiceti písem a obrázek z knihovny 178 siluet nebo vlastní; vrstev může být až dvanáct a každá má svou barvu z filamentů, které máme na farmě. Vrstvu vyberete klepnutím v náhledu, tažením ji posunete, rohem rámečku zvětšíte a kolečkem otočíte, šířku každé měníte od 5 do 250 mm. Vrstvy leží nad sebou, každá o krok výš, takže tiskárně stačí ve správné výšce vyměnit filament; na naší farmě vytiskneme až čtyři barvy v jednom tisku. S očkem na zavěšení je z téhož návrhu jmenovka na dárek, cedulka na dveře pokojíčku, visačka na tašku i známka na obojek.',
        'Příklad z kalkulace na produkci (27. 10. 2026, PLA): šestiúhelník 120 × 60 mm se jménem Adéla a hvězdou ve třech barvách má 14,5 g, tiskne se 25 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky (kalkulace /c/v9fh1s1ogsob). Jmenovka na dárek, šestiúhelník 70 mm s očkem a nápisem „Pro Elu“, má 5,2 g a tiskne se 10 minut: jedna stojí 99 Kč, deset stejných 120 Kč, tedy 12 Kč za kus, protože příprava zakázky se platí jednou (/c/udkkubsktt9d). Kalkulace počítá s jednou barvou, cívky pro další potvrdíte při objednávce. Chcete jen rychle jméno na destičce, která se sama zvětší kolem textu? Pod volbami na začátku stránky je odkaz na rychlý formulář.',
    ],
    'steps' => [
        ['name' => 'Začněte od jmenovky', 'text' => 'Stránka se otevře s destičkou, jménem Adéla a hvězdou. Přepište jméno a vyberte jiný tvar destičky.'],
        ['name' => 'Upravte vrstvy', 'text' => 'Klepněte na vrstvu v seznamu nebo v náhledu a změňte text, písmo, tvar, obrázek a barvu. V náhledu ji tažením posunete, rohem rámečku zvětšíte a kolečkem otočíte.'],
        ['name' => 'Přidejte další a očko', 'text' => 'Tlačítky přidáte druhý řádek textu, obrázek nebo další tvar, nejvýš dvanáct vrstev; šipky mění, co leží na čem. Očko na stužku zapnete zaškrtnutím.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Model i projekt pro slicer s výměnami filamentu stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak velká jmenovka vyjde?', 'a' => 'Jak ji nastavíte: šířku každé vrstvy měníte od 5 do 250 mm. Jmenovka, se kterou se stránka otevře, má 120 × 60 mm a tloušťku 4,6 mm; na dárek stačí 60 až 80 mm s očkem.'],
        ['q' => 'Jak udělat sadu jmenovek pro celou rodinu?', 'a' => 'Návrh zůstává na stránce: přepíšete jméno, necháte rozložení a pokračujete do kalkulace pro každé jméno zvlášť; počet kusů jednoho návrhu zadáte v kalkulaci. Deset stejných jmenovek 70 mm vyjde na 120 Kč.'],
        ['q' => 'Jaký obrázek můžu přidat?', 'a' => 'Siluetu z knihovny (178 motivů), SVG s vyplněnými tvary, nebo jednoduchý kontrastní obrázek PNG či JPG. Z obrázku zůstane silueta v jedné barvě. Fotografie se nehodí; na tu je nástroj Reliéf a litofanie.'],
        ['q' => 'Vytisknete jmenovku ve více barvách?', 'a' => 'Ano, až ve čtyřech v jednom tisku. Každá vrstva leží o krok výš, takže se filament mění podle výšky. Barvy vyberete z těch, které máme skladem; na vlastní tiskárně má projekt výměny v sobě.'],
        ['q' => 'Co když text přesahuje destičku?', 'a' => 'Rozložení je na vás: text zmenšete tažením za roh rámečku, nebo zvětšete destičku. Co z destičky přesahuje, se vytiskne také, protože pod každou vrstvou se doplní ty nižší.'],
        ['q' => 'Hodí se jako známka pro psa?', 'a' => 'Tvar kosti s očkem ano; na obojek volte PETG a písmo aspoň 6 mm vysoké. Telefonní číslo přidejte jako další vrstvu textu.'],
    ],
    'examples' => [
        'Šestiúhelník 120 × 60 mm se jménem Adéla a hvězdou, tři barvy: 14,5 g, 25 minut, 99 Kč.',
        'Kost 130 mm se jménem Rex a tlapkou, písmo Titan One.',
        'Mrak 100 × 55 mm se jménem Ela a hvězdou.',
    ],
];
