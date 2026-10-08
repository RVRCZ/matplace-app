<?php

return [
    'title' => 'Vydutit 3D model: dutý model se stěnou a odtokovými otvory',
    'description' => 'Z plného modelu uděláte dutý se stěnou 1,5 až 6 mm a odtokovými otvory ve dně. Nástroj spočítá ušetřené gramy i korunu. Zdarma, online, za pár vteřin.',
    'h1' => 'Dutý model: méně materiálu, kratší tisk, stejný tvar zvenku',
    'intro' => [
        'Nástroj odebere vnitřek modelu a nechá stěnu, jakou si zvolíte, od 1,5 do 6 mm. Vzdálenost od povrchu se měří na jemné mřížce (0,6 mm, u velkých modelů hrubší a nástroj to řekne) a vnitřní plocha vzniká tam, kde dosáhne tloušťky stěny; proto drží tvar i v záhybech a dutina nikde nevyjde na povrch. Model, který není uzavřený, nástroj nejdřív uzavře.',
        'Ve dně dutiny vzniknou odtokové otvory o průměru 3 až 8 mm: automaticky tolik, kolik se jich vejde, nejvýš čtyři, nebo zadaný počet; jdou i vypnout. Nástroj spočítá objem dutiny, ušetřené gramy materiálu a rozdíl v ceně tisku. Dutý model se otevře jako nový soubor: necháte ho vytisknout u nás, nebo si ho stáhnete pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Přetáhněte model do pole nebo klepněte a vyberte soubor: STL, 3MF, OBJ nebo STEP od 5 do 1000 mm.'],
        ['name' => 'Zvolte stěnu a otvory', 'text' => 'Tloušťka stěny 1,5 až 6 mm; odtokové otvory ve dně s průměrem a počtem, nebo bez nich.'],
        ['name' => 'Vydutit', 'text' => 'Klepněte na „Vydutit model“. Trvá to pár vteřin, u velkých modelů do minuty.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vidíte, kolik gramů a korun dutina ušetří. Rentgen v náhledu ukáže stěnu. Model stáhnete jako STL nebo projekt pro slicer, nebo ho necháte vytisknout u nás.'],
    ],
    'faq' => [
        ['q' => 'Proč model vydutit, když slicer umí výplň?', 'a' => 'Výplň je dobrá pro FDM tisk; dutina se hodí pro pryskyřicový tisk (plný díl by praskal a spotřeboval litry), pro velké figury a pro věci, do kterých chcete něco vložit nebo je zatížit pískem. Dutý model má i ve sliceru s výplní méně materiálu, protože výplň se tiskne jen ve stěně.'],
        ['q' => 'Jak silnou stěnu zvolit?', 'a' => 'Pro FDM 2 až 3 mm (stěna se dál tiskne s výplní podle sliceru), pro pryskyřici 2 až 3 mm, pro velké busty 3 až 5 mm. Pod 1,5 mm nástroj nejde.'],
        ['q' => 'K čemu jsou odtokové otvory?', 'a' => 'U pryskyřicového tisku vyteče nevytvrzená pryskyřice a nevznikne podtlak; u FDM jimi vysypete podpěry z dutiny. Kdo je nechce, vypne je, dutina je pak uzavřená.'],
        ['q' => 'Co se stane s modelem, který má díry?', 'a' => 'Před měřením ho nástroj uzavře stejným postupem, jaký používá naše farma. Když to nejde, řekne to; takový model nejdřív opravte v nástroji Oprava modelu.'],
        ['q' => 'Jak velký model nástroj zvládne?', 'a' => 'Do 1000 mm a do 2 milionů trojúhelníků. Nad 190 mm je mřížka měření hrubší než 0,6 mm a stěna může být místy o desetinu až půl milimetru jiná; nástroj to řekne.'],
        ['q' => 'Kolik to stojí a jak výtisk dostanu?', 'a' => 'Vydutění je zdarma. Platíte jen tisk, pokud si ho u nás objednáte, z předplaceného kreditu. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [],
];
