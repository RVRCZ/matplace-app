<?php

return [
    'title' => 'Miska ve tvaru obrázku: odkládací tácek na míru',
    'description' => 'Nahrajete obrázek nebo vyberete siluetu a vznikne miska v jejím tvaru na klíče a šperky. Vytiskneme ji, nebo si model zdarma stáhnete.',
    'h1' => 'Odkládací miska ve tvaru vlastního obrázku',
    'intro' => [
        'Nástroj vytvoří malou misku, jejíž stěna kopíruje obrys obrázku: tlapku, srdce, list nebo cokoli, co nahrajete jako PNG, JPG, WebP či SVG nebo vyberete z knihovny. Místo obrázku můžete napsat jméno. Šířku nastavíte od 50 do 200 mm, výšku od 8 do 40 mm; stěna je silná 1,6 mm a dno 2 mm, obojí jde změnit.',
        'Kresba uvnitř obrysu se vyryje do dna, takže miska zůstává jednobarevným tiskem pro jakoukoli tiskárnu i pro naši farmu. Obrázek ve dně jde také vynechat, nebo vložit v barvách filamentů; barevné dno ale potřebuje tiskárnu, která mění filament sama, a u nás ho zatím objednat nejde.',
    ],
    'steps' => [
        ['name' => 'Vyberte obrázek nebo siluetu', 'text' => 'Nahrajte vlastní obrázek, vyberte motiv z knihovny, nebo napište jméno. Pozadí se odstraní samo a obrys se vyhladí.'],
        ['name' => 'Nastavte rozměry', 'text' => 'Šířku a výšku misky měníte posuvníky nebo tažením šipek v náhledu. Odstup obrázku od stěny určuje, kolik místa zůstane kolem kresby.'],
        ['name' => 'Zvolte, co bude ve dně', 'text' => 'Kresba vyrytá dvě vrstvy hluboko, kresba v barvách, nebo hladké dno. U jednobarevné siluety je dno vždy hladké.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu, přesnou cenu a dobu tisku o krok dál. Model i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jaký obrázek se na misku hodí?', 'a' => 'Tvar s jasným obrysem bez tenkých výběžků: zvířecí tlapka, srdce, list, mrak, hvězda. Do úzkých cípů se nic nevejde a stěna je v nich křehčí.'],
        ['q' => 'Co se z obrázku vyryje do dna?', 'a' => 'Všechny barvy obrázku kromě té, která zabírá největší plochu. U tlapky v kruhu tedy zůstane kruh jako dno a tlapka se do něj vyryje.'],
        ['q' => 'Z čeho se miska tiskne?', 'a' => 'Doporučujeme PLA, pro koupelnu nebo okno na slunci PETG. Tiskne se dnem dolů a bez podpěr.'],
        ['q' => 'Můžu mít obrázek ve dně barevně?', 'a' => 'Ano, volbou „V barvách“. Barvy jsou vložené do dna ve stejné vrstvě jako okolní plast, takže to vytiskne jen tiskárna, která mění filament sama (AMS, MMU, ACE). Model si stáhnete po barvách; na naší farmě takový tisk zatím objednat nejde.'],
        ['q' => 'Jak velkou misku zvolit?', 'a' => 'Na prsteny a náušnice stačí 80 mm, na klíče a drobné 100 až 120 mm. Výška 12 až 15 mm udrží drobnosti a nepřekáží při vybírání.'],
        ['q' => 'Jak zaplatím a jak misku dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Kulatá miska 100 mm s tlapkou vyrytou do dna, výška 15 mm.',
        'Miska ve tvaru srdce, 110 mm široká a 20 mm vysoká, s hladkým dnem.',
        'Miska ve tvaru hvězdy 120 mm s obličejem vloženým do dna v barvách.',
    ],
];
