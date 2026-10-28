<?php

// Stránka pro dotaz „víčko na míru 3d tisk“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Víčko na míru pro 3D tisk: zátka, krytka nebo závit zdarma',
    'heading' => 'Víčko, zátka nebo krytka na míru pro 3D tisk podle naměřeného rozměru',
    'description' => 'Změříte hrdlo, otvor nebo závit a nástroj navrhne víčko, zátku nebo krytku, která se nasadí, zasune nebo našroubuje. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Jak chybějící víčko vzniká, co pasuje a kolik stojí',
    'intro' => [
        'Nástroj vytvoří chybějící víčko, zátku nebo krytku podle toho, co naměříte. Díl se buď nasadí na hrdlo, zasune do otvoru, nebo našroubuje na závit; tvar je kulatý, obdélníkový nebo šestihranný, průměr či šířka od 5 do 200 mm, výška 4 až 60 mm. U závitu vyberete PET lahev 28 mm, metrický závit hrubé řady M6 až M30, nebo zadáte vlastní průměr a stoupání 0,5 až 6 mm; vzniká vždy víčko nebo matice na vnější závit, šroub nástroj nevyrobí. Vůli přidá nástroj sám, zadáváte skutečný rozměr. Na tekutiny přidáte těsnicí kroužek do hrdla nebo lůžko pro vložku, po obvodu vroubkování, nahoře zaoblenou hranu. Náhled ukazuje díl zvenku i v řezu a píše, na jaký rozměr pasuje.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): šroubovací víčko na PET lahev 28 mm s těsnicím kroužkem, závit vysoký 12 mm, má 5,7 g, tiskne se 22 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/5omcbkke1541). Krytka do obdélníkového profilu 36 × 16 mm, zasunutá 15 mm, má 4,7 g a tiskne se 15 minut (kalkulace /c/ldniassche8t); nasazovací víčko na sklenici s hrdlem 70 mm má 17,8 g, tiskne se 39 minut a stojí 99 Kč (kalkulace /c/hgabcfckkauy). Na víc kusů klesne cena za kus, příprava se platí jednou. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu; tiskne se bez podpěr.',
    ],
    'steps' => [
        ['name' => 'Vyberte, jak má držet', 'text' => 'Nasazení na hrdlo, zasunutí do otvoru, nebo našroubování na závit. Můžete začít od předvolby, například „PET lahev (28 mm)“, „Zátka do trubky“ nebo „Krytka profilu“.'],
        ['name' => 'Zadejte naměřené rozměry', 'text' => 'U nasazovacího víčka měřte hrdlo zvenku, u zátky otvor uvnitř, u závitu průměr přes závit a stoupání. Zadávejte skutečný rozměr, vůli nepřičítejte.'],
        ['name' => 'Zvolte těsnění a tvar', 'text' => 'Na tekutiny vyberte těsnicí kroužek do hrdla nebo lůžko pro vložku. Můžete přidat vroubkování po obvodu, klenutý vršek a zaoblit horní hranu.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Zkontrolujte náhled i řez a pokračujte k přesné ceně. Nebo si model stáhněte zdarma pro svou tiskárnu; u neznámého závitu si nejdřív vyzkoušejte jeden kus.'],
    ],
    'faq' => [
        ['q' => 'Bude šroubovací víčko těsnit?', 'a' => 'Samotný tištěný závit netěsní, jen drží. Na tekutiny zvolte těsnicí kroužek do hrdla, který se opře o vnitřek hrdla, nebo lůžko pro vložku z pěnové gumy či silikonu. Vložka není součástí výtisku.'],
        ['q' => 'Jaké závity nástroj umí?', 'a' => 'PET lahev 28 mm (průměr přes závit 27,4 mm, stoupání 2,7 mm), metrické závity hrubé řady M6 až M30 a vlastní závit se stoupáním 0,5 až 6 mm. Vzniká vždy víčko nebo matice na vnější závit, šroub nástroj nevyrobí.'],
        ['q' => 'Jak změřit hrdlo nebo otvor?', 'a' => 'Posuvným měřítkem: u nasazovacího víčka vnější průměr hrdla, u zátky vnitřní rozměr otvoru na dvou místech, u závitu průměr přes vrcholy závitu a vzdálenost dvou sousedních vrcholů. Zadejte skutečné číslo, vůli 0,1 až 1 mm přidá nástroj a můžete ji změnit.'],
        ['q' => 'Jak velký díl mohu navrhnout?', 'a' => 'Průměr nebo šířka hrdla může být od 5 do 200 mm a výška od 4 do 60 mm. Příliš malý díl nástroj odmítne, protože by nešel vytisknout pevně; víčko větší než 200 mm se nevejde na podložku.'],
        ['q' => 'Mohu víčko použít na potraviny a nápoje?', 'a' => 'Výtisk není určen pro přímý styk s potravinami. Má-li víčko přijít na nápoj nebo jídlo, zvolte lůžko pro vložku a vložku do něj dejte; samotný plast se s obsahem nemá potkat.'],
        ['q' => 'Z jakého materiálu díl tisknout a jak doma?', 'a' => 'Zátky a víčka, která mají pružit nebo se často šroubují, z PETG; na krytky bez namáhání stačí PLA. Tiskne se vrškem dolů, bez podpěr, vrstva 0,2 mm; závit vyjde čistěji s vrstvou 0,12 mm. Když víčko jde ztuha, zvětšete vůli o 0,1 mm.'],
    ],
    'examples' => [
        'Šroubovací víčko na PET lahev 28 mm s těsnicím kroužkem, závit vysoký 12 mm: 5,7 g, 22 minut, 99 Kč.',
        'Krytka do obdélníkového profilu s otvorem 36 × 16 mm, zasune se 15 mm: 4,7 g, 15 minut.',
        'Nasazovací víčko na sklenici s hrdlem o průměru 70 mm, hluboké 14 mm: 17,8 g, 39 minut, 99 Kč.',
    ],
];
