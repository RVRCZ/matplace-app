<?php

// Stránka pro dotaz „vánoční ozdoba se jménem“ (docs/AP.md §6). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Vánoční ozdoba se jménem: perníček z 3D tiskárny, zdarma',
    'heading' => 'Vánoční ozdoba se jménem: perníček na stromeček',
    'description' => 'Napíšete jméno a vyberete panáčka, srdce, hvězdu nebo stromek s bílou polevou. Perníček vytiskneme ve dvou barvách, nebo si model stáhnete zdarma.',
    'h1' => 'Jak perníček se jménem vzniká, kolik stojí a jak dlouho se tiskne',
    'intro' => [
        'Nástroj vytvoří ozdobu, která vypadá jako perník s cukrovou polevou: panáčka, srdce, hvězdu nebo stromek. Napíšete jméno do 16 znaků, vyberete písmo a zdobení polevou, vlnkou nebo rovnou linkou. Šířku nastavíte od 50 do 150 mm, tloušťku od 2 do 6 mm; očko na stužku je nahoře a posunete ho posuvníkem nebo tažením v náhledu. Perníček má dvě barvy nad sebou: hnědý podklad a bílou polevu se jménem, která vystupuje o 0,4 až 1,2 mm. Tisk se jednou zastaví a vymění se filament, takže ho zvládne každá tiskárna; na naší farmě ho vytiskneme najednou a obě barvy si vyberete při objednávce. Hnědá a bílá jsou přednastavené, perníček ale může být i červený se zlatou polevou.',
        'Příklad z kalkulace na produkci (27. 10. 2026, PLA): panáček široký 90 mm se jménem Ela má 10,8 g, tiskne se 20 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky (kalkulace /c/krswdwunexsg); šest kusů téhož perníčku stojí 140 Kč, tedy 23 Kč za kus, protože příprava zakázky se platí jednou (/c/qkmqrfmbzaln). Výtisk je ozdoba, k jídlu není. Tiskne se naplocho, lícem nahoru a bez podpěr, na větvi drží i na tenké stužce. Model i projekt pro slicer s výměnou filamentu si stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Napište jméno', 'text' => 'Jméno může mít až 16 znaků. Nástroj ho sám zmenší tak, aby se na perníček vešlo; krátké jméno vyjde větší.'],
        ['name' => 'Vyberte tvar a polevu', 'text' => 'Panáček má obličej, knoflíky a polevu na nohách, srdce a hvězda mají polevu po obvodu, stromek dvě girlandy. Poleva může být vlnka, rovná linka, nebo žádná.'],
        ['name' => 'Nastavte rozměr, očko a barvy', 'text' => 'Zadáte šířku a tloušťku, očko posunete po obrysu a oběma dílům přiřadíte filament. Hnědá a bílá jsou přednastavené.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Model i projekt s výměnou filamentu stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak dlouhé jméno se na perníček vejde?', 'a' => 'Až 16 znaků. Delší jméno vyjde menší; když by písmena byla nižší než 3 mm, nástroj na to upozorní a poradí širší perníček nebo kratší jméno. Na panáčka o šířce 90 mm se pohodlně vejde 6 až 8 písmen.'],
        ['q' => 'Dá se na perníček napsat i rok nebo věnování?', 'a' => 'Jedno pole do 16 znaků: „Ela 2026“ nebo „Babičce“ se vejde, delší věnování ne. Pro víc textu a vlastní obrázek použijte Vánoční ozdobu z obrázku nebo jmenovku z vrstev.'],
        ['q' => 'Z čeho se perníček tiskne?', 'a' => 'Doporučujeme PLA: je lehké a na větvi drží i na tenké stužce. Tiskne se naplocho, lícem nahoru, bez podpěr. Výtisk je ozdoba, k jídlu není; na skutečné cukroví je nástroj Vykrajovátko.'],
        ['q' => 'Vytisknete ho ve dvou barvách?', 'a' => 'Ano. Podklad a poleva se jménem leží nad sebou, takže stačí jedna výměna filamentu. Obě barvy si vyberete při objednávce z filamentů, které máme skladem; klasika je hnědá s bílou, jde i červená se zlatou nebo zelená s bílou.'],
        ['q' => 'Můžu perníček použít jako jmenovku na dárek?', 'a' => 'Ano. Menší perníček o šířce 50 až 60 mm s očkem přivážete stužkou k balíčku a obdarovaný si ho pověsí na stromeček. Pro jmenovky bez polevy je rychlejší nástroj Jmenovka.'],
        ['q' => 'Kolik stojí víc perníčků najednou?', 'a' => 'V kalkulaci zadáte počet kusů jednoho jména: jeden perníček 90 mm stojí 99 Kč, šest stejných 140 Kč, tedy 23 Kč za kus, protože příprava zakázky se platí jednou. Pro další jména uděláte další návrh; tisknou se vedle sebe na jedné podložce.'],
    ],
    'examples' => [
        'Perníkový panáček široký 90 mm se jménem Ela, obličejem a knoflíky z bílé polevy: 10,8 g, 20 minut, 99 Kč.',
        'Perníkové srdce 90 mm se jménem Mamince a vlnkou polevy po obvodu.',
        'Perníková hvězda 80 mm se jménem Tom, očko na stužku na horním cípu.',
    ],
];
