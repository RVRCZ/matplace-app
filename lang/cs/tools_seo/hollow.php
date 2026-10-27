<?php

// Stránka pro dotaz „vydutit 3d model“ (docs/AP.md §1, §4). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Vydutit 3D model online zdarma: dutý STL se stěnou a otvory',
    'heading' => 'Vydutit 3D model online zdarma',
    'description' => 'Z plného STL, 3MF, OBJ nebo STEP uděláte dutý model se stěnou 1,5 až 6 mm a odtokovými otvory. Hned vidíte ušetřené gramy a koruny. Zdarma, bez účtu.',
    'h1' => 'Co vydutění udělá s modelem, kdy se vyplatí a kde končí',
    'intro' => [
        'Nástroj odebere vnitřek modelu a nechá stěnu, jakou zvolíte, od 1,5 do 6 mm. Vzdálenost od povrchu měří na jemné mřížce 0,6 mm (u modelů nad 190 mm hrubší a nástroj to řekne) a vnitřní plocha vzniká tam, kde dosáhne tloušťky stěny; proto drží tvar i v záhybech a dutina nikde nevyjde na povrch. Zvenku se model nezmění ani o desetinu milimetru. Ve dně dutiny vzniknou odtokové otvory o průměru 3 až 8 mm: automaticky tolik, kolik se jich vejde, nejvýš čtyři, nebo zadaný počet; jdou i vypnout. Bere STL, 3MF, OBJ i STEP od 5 do 1000 mm a do 2 milionů trojúhelníků; model, který není uzavřený, nejdřív uzavře stejným postupem jako naše farma. Dutý model se otevře jako nový soubor a původní zůstane.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+, vrstva 0,2 mm, výplň 15 %): plný váleček ⌀ 120 × 200 mm má 503 g, tiskne se 8 h 14 min a stojí u nás 910 Kč. Vydutý se stěnou 2,5 mm a třemi otvory má 223 g, tiskne se 6 h 36 min a stojí 500 Kč: o 280 g a 410 Kč méně, zvenku stejný. U pryskyřicového tisku je rozdíl ještě větší: plný váleček by spolkl 2,3 litru pryskyřice, dutý se stěnou 2,5 mm asi čtvrt litru (spočítáno z objemu, ne tištěno). Nástroj ukáže objem dutiny, ušetřené gramy a rozdíl ceny hned po vydutění; rentgen v náhledu ukáže stěnu. Model stáhnete jako STL nebo projekt pro slicer, nebo ho necháte vytisknout u nás.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Přetáhněte do pole STL, 3MF, OBJ nebo STEP od 5 do 1000 mm, nebo klepněte a vyberte soubor.'],
        ['name' => 'Zvolte stěnu a otvory', 'text' => 'Tloušťka stěny 1,5 až 6 mm. Odtokové otvory ve dně s průměrem 3 až 8 mm a počtem, automaticky až čtyři, nebo bez nich.'],
        ['name' => 'Vydutit', 'text' => 'Klepněte na „Vydutit model“. Trvá to pár vteřin, u velkých modelů do minuty; stránka říká, co se právě děje.'],
        ['name' => 'Přečtěte si, co ušetříte, a pokračujte', 'text' => 'Objem dutiny, ušetřené gramy a koruny, rentgen stěny. Pak stažení STL nebo projektu pro slicer, nebo tisk u nás.'],
    ],
    'faq' => [
        ['q' => 'Kdy se vydutění vyplatí a kdy ne?', 'a' => 'Malý díl pro FDM nechte plný: slicer mu dá výplň 15 % a dutina by ušetřila pár gramů. Vyplatí se u velkých figur a bust nad 150 mm, u pryskyřicového tisku vždy (plný díl praská a spotřebuje litry) a u věcí, které chcete zatížit pískem nebo do nich něco vložit.'],
        ['q' => 'Jak silnou stěnu zvolit?', 'a' => 'Pro FDM 2 až 3 mm; stěna se dál tiskne s výplní podle sliceru, takže drží. Pro pryskyřici 2 až 3 mm, pro velké busty nad 300 mm 3 až 5 mm. Pod 1,5 mm nástroj nejde, taková skořepina se tiskne špatně.'],
        ['q' => 'K čemu jsou odtokové otvory a kde budou?', 'a' => 'Vždy ve dně dutiny, tedy na straně, kterou model stojí na podložce. U pryskyřice jimi vyteče nevytvrzená pryskyřice a nevznikne podtlak; u FDM jimi vysypete podpěry z dutiny nebo nasypete písek. Kdo je nechce, vypne je a dutina zůstane uzavřená.'],
        ['q' => 'Změní se tvar zvenku?', 'a' => 'Ne. Vnější povrch zůstává přesně, jak byl, dutina končí vždy ve zvolené tloušťce pod ním, i v záhybech a tenkých místech, kde se prostě nevytvoří. Rozměry, které ukazuje kalkulace, jsou stejné před i po.'],
        ['q' => 'Co se stane s modelem, který má díry?', 'a' => 'Před měřením ho nástroj uzavře stejným postupem, jaký používá naše farma. Když to nejde, řekne to; takový model nejdřív projeďte nástrojem Oprava modelu a vydutění zopakujte.'],
        ['q' => 'Jak velký model nástroj zvládne?', 'a' => 'Do 1000 mm a do 2 milionů trojúhelníků. Nad 190 mm je mřížka měření hrubší než 0,6 mm a stěna může být místy o desetinu až půl milimetru jiná; nástroj to u výsledku napíše.'],
    ],
    'examples' => [],
];
