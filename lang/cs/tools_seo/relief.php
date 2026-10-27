<?php

// Stránka pro dotaz „litofanie z fotky“ (docs/AP.md §1, §5). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Litofanie z fotky online zdarma: panel, lampa nebo reliéf',
    'heading' => 'Litofanie z fotky online zdarma',
    'description' => 'Z fotky vznikne litofanie v sedmi tvarech, lampa s fotkou kolem dokola nebo reliéf na zeď. Model je hotový asi za minutu, fotku neukládáme. Stažení zdarma.',
    'h1' => 'Jak litofanie vzniká, jaká fotka se hodí a kde má nástroj hranice',
    'intro' => [
        'Litofanie je tenká destička, na které se fotka objeví, až když za ni dáte světlo: tmavá místa jsou silnější a propustí méně světla, světlá tenčí. Nástroj z fotky udělá právě to, ve třech provedeních: panel do okna nebo na stojánek, lampu, kde je tatáž fotka obtočená kolem válce se dnem na objímku E27 nebo E14, a reliéf na zeď, který je vidět i bez světla, protože světlá místa vystupují nahoru. Destička může být obdélník, kruh, ovál, srdce, oblouk, stromek nebo vlastní silueta z knihovny, šířka a výška do 250 mm, lampa s obvodem do 400 mm (průměr asi 127 mm) a výškou do 250 mm. Tloušťka jde od 0,4 do 5 mm po krocích 0,2 mm a nástroj hned píše, kolik odstínů z fotky vznikne; jas, kontrast a střední tóny upravíte posuvníky a náhled ukáže destičku podsvícenou. Fotku použijeme jen k výpočtu a neukládáme ji.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): panel 100 × 75 mm se stolním stojánkem má 27 g, tiskne se 1 h 9 min a stojí u nás 100 Kč; lampa na objímku E27 o průměru 100 mm a výšce 150 mm má 111 g, tiskne se 2 h 29 min a stojí 250 Kč (bez dopravy). Obě vznikly z vygenerovaného zkušebního obrázku, ne z fotky člověka. Nejlépe vychází portrét nebo fotka s výrazným kontrastem a jedním hlavním motivem; drobné detaily v pozadí se v odstínech tloušťky ztratí. Litofanii vytiskneme z bílého materiálu, aby jí světlo procházelo, nebo si model stáhnete jako STL či projekt pro svou tiskárnu s nastavením, které se nám osvědčilo.',
    ],
    'steps' => [
        ['name' => 'Vyberte, co vyrobit', 'text' => 'Fotopanel do okna nebo na stojánek, lampu s fotkou kolem dokola, nebo reliéf na zeď bez podsvícení.'],
        ['name' => 'Nahrajte fotku', 'text' => 'Vyberte nebo vyfoťte obrázek (JPG, PNG, WebP). Jas, kontrast a střední tóny upravíte posuvníky, obrácení světlých a tmavých míst zaškrtnutím.'],
        ['name' => 'Zvolte tvar a rozměry', 'text' => 'U panelu tvar destičky, šířku, výšku a rámeček; u lampy obvod, výšku a objímku. Doplňky: otvor nebo očko na zavěšení, stolní stojánek.'],
        ['name' => 'Vytvořte model', 'text' => 'Klepněte na „Vytvořit model“. Model se asi za minutu otevře s náhledem proti světlu; pak stažení zdarma, nebo kalkulace a tisk u nás.'],
    ],
    'faq' => [
        ['q' => 'Jaký je rozdíl mezi litofanií a reliéfem?', 'a' => 'Litofanie ukáže obrázek až proti světlu, třeba v okně nebo před lampou; bez světla je to bílá destička s nezřetelným vzorem. Reliéf je vidět i bez světla, protože světlá místa fotky vystupují nad desku; hodí se na zeď nebo na poličku.'],
        ['q' => 'Jaká fotka se hodí nejlépe?', 'a' => 'Portrét nebo fotka s výrazným kontrastem a jedním motivem, ostrá, bez protisvětla. Nástroj píše, kolik odstínů tloušťky po 0,2 mm z fotky vznikne; posuvníky jasu, kontrastu a středních tónů slabší fotku zlepší. Tmavý motiv na světlém pozadí vyjde lépe než naopak.'],
        ['q' => 'Proč musí být litofanie bílá a čím ji podsvítit?', 'a' => 'Barevný plast světlo zabarví a tmavý ho nepropustí; bílý PLA dá čistý obraz. Stačí denní světlo v okně, LED pásek nebo LED žárovka v lampě. Klasickou žárovku nepoužívejte, PLA měkne už kolem 55 °C.'],
        ['q' => 'Jak funguje lampa?', 'a' => 'Fotka se obtočí kolem válce o zadaném obvodu, uvnitř je hladká stěna a dno s otvorem Ø 40 mm pro objímku E27 (nebo Ø 28 mm pro E14). Můžete zvolit i otevřené dno pro LED pásek, nebo zavřené pro LED svíčku.'],
        ['q' => 'Ukládáte moji fotku?', 'a' => 'Ne. Fotka slouží jen k výpočtu modelu a hned ji zahodíme; v modelu zůstává jen tvar. Když se k nastavení vrátíte, je potřeba ji vybrat znovu.'],
        ['q' => 'Jak velký a jak silný může model být?', 'a' => 'Panel do 250 × 250 mm, lampa s obvodem do 400 mm a výškou do 250 mm. Litofanie je silná nejvýše 5 mm, nejtenčí místo od 0,4 mm; čím větší rozpětí, tím víc odstínů, ale tím déle se tiskne.'],
    ],
    'examples' => [],
];
