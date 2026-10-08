<?php

return [
    'title' => 'Flexi z vlastního 3D modelu: články s kulovými klouby',
    'description' => 'Podlouhlý model rozřežeme na články s kulovými klouby, které se tisknou najednou v složeném stavu a po sejmutí se ohýbají. Had nebo drak z vlastní figurky.',
    'h1' => 'Ohebná hračka z vlastního modelu: flexi s kulovými klouby',
    'intro' => [
        'Nástroj rozřeže podlouhlý model napříč nejdelší osou (nebo osou, kterou zvolíte) na 3 až 20 článků a do každého řezu vloží kulový kloub: kouli o průměru 6 až 10 mm na krčku, která vyrůstá z jednoho článku, a dutinu s vůlí 0,35 až 0,5 mm ve druhém. Koule sedí v dutině hlouběji, než je otvor široký, takže nejde vytáhnout, a přitom se v ní otáčí. Mezi články je mezera dvou vrstev.',
        'Celek se tiskne jako jeden kus v složeném stavu, bez podpěr, a po sejmutí z podložky se klouby prolomí prsty a hračka se ohýbá jako známé tištěné dráčky. Model musí být v místě řezů aspoň tak silný jako koule plus 3 mm; kde není, nástroj to řekne a kloub tam vynechá. Flexi se otevře jako nový soubor: necháte ho vytisknout u nás, nebo si ho stáhnete.',
    ],
    'steps' => [
        ['name' => 'Nahrajte podlouhlý model', 'text' => 'Přetáhněte model do pole nebo klepněte a vyberte soubor: STL, 3MF, OBJ nebo STEP. Hodí se hadi, draci, ještěrky, ryby a cokoli s jasnou délkou a dostatečnou tloušťkou.'],
        ['name' => 'Zvolte články a klouby', 'text' => 'Osa článků, jejich počet (nebo automaticky podle koule), průměr koule a vůle v kloubu; případně výška modelu.'],
        ['name' => 'Udělejte flexi', 'text' => 'Klepněte na „Udělat flexi“. Trvá to pár vteřin; články vidíte v náhledu každý svou barvou, kloub v rentgenu.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Flexi stáhnete jako jeden STL nebo projekt pro slicer, nebo ho necháte vytisknout u nás.'],
    ],
    'faq' => [
        ['q' => 'Jak se flexi tiskne?', 'a' => 'Jako jeden kus, složený, s vrstvou 0,2 mm a bez podpěr. Horní polovina dutiny kloubu je převis, který běžná tiskárna zvládne; vůle 0,4 mm drží kouli a dutinu od sebe. Po tisku klouby prolomte prsty, první pohyb jde ztuha.'],
        ['q' => 'Jak tlustý musí model být?', 'a' => 'V místě každého řezu aspoň průměr koule + 3 mm (u koule 8 mm tedy 11 mm), aby dutina měla kolem sebe stěnu. Kde to nevyjde, nástroj kloub vynechá a řekne to; zvolte menší kouli, méně článků nebo model zvětšete.'],
        ['q' => 'Kolik článků zvolit?', 'a' => 'Automatika dává článek na každých asi 2,5 průměru koule; víc článků = ohebnější, ale kratší články drží hůř. Nejméně 3, nejvýš 20.'],
        ['q' => 'Proč se klouby nehýbou hned po tisku?', 'a' => 'Tenké vrstvy v mezeře se mohou spojit; prolomte klouby opatrně prsty, případně uvolněte vůli na 0,5 mm. Příliš volné klouby se naopak kývají: dejte 0,35 mm.'],
        ['q' => 'Dá se flexi udělat z fotky?', 'a' => 'Ano: nejdřív vytvořte figurku v nástroji Busta z fotky nebo si model stáhněte z Printables či MakerWorldu, pak ho sem nahrajte.'],
        ['q' => 'Kolik to stojí a jak výtisk dostanu?', 'a' => 'Výroba flexi je zdarma. Platíte jen tisk, pokud si ho u nás objednáte, z předplaceného kreditu. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [],
];
