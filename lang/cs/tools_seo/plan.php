<?php

return [
    'title' => 'Plán prodeje 3D tisků na rok: tržby, zisk, bod zvratu',
    'description' => 'Produkty s nákladem, cenou a kusy za měsíc, fixní náklady a sezóna: tržby a zisk po měsících, bod zvratu, graf, CSV a PDF. A kroky na tento týden.',
    'h1' => 'Rok prodeje v jedné tabulce, s kroky na tento týden',
    'intro' => [
        'Plánovač vezme vaše produkty – náklad a cenu na kus a kolik kusů v běžném měsíci prodáte –, fixní náklady a sezónu (rovnoměrně, Vánoce, léto a trhy, škola, nebo vlastní násobky po měsících) a spočítá dvanáct měsíců dopředu: tržby, náklady, zisk a jeho kumulaci, měsíc, ve kterém se dostanete do plusu, a graf. Čísla se mění hned, jak píšete.',
        'K tomu plánovač vyčte z plánu, co chybí, a sestaví seznam kroků na tento týden: spočítat náklad, nacenit, nafotit, vystavit, vybrat kanál. Odškrtnuté kroky si plán pamatuje. Plán zůstává v prohlížeči; po přihlášení ho uložíte k účtu a otevřete jinde. Stáhnete ho jako CSV do tabulky nebo jako PDF na jednu stránku.',
    ],
    'steps' => [
        ['name' => 'Zadejte produkty', 'text' => 'Název, náklad a cena na kus (z nástrojů Náklady tisku a Zisk prodejce), kusy za měsíc; zaškrtněte, co už je nafocené a vystavené.'],
        ['name' => 'Fixní náklady a kanál', 'text' => 'Co platíte měsíčně i bez prodeje; hlavní kanál prodeje a první měsíc plánu.'],
        ['name' => 'Zvolte sezónu', 'text' => 'Rovnoměrně, Vánoce, léto a trhy, škola, nebo vlastní násobek pro každý měsíc.'],
        ['name' => 'Čtěte a stahujte', 'text' => 'Zisk a tržby za rok, bod zvratu, graf a tabulka; seznam kroků na týden. CSV, PDF, uložení k účtu.'],
    ],
    'faq' => [
        ['q' => 'Jak funguje sezóna?', 'a' => 'Každý měsíc má násobek běžného měsíce. Vánoce zvednou listopad na dvojnásobek a prosinec ještě výš, léto a trhy zvedají květen až září. Vlastní sezóna nechá zadat dvanáct čísel.'],
        ['q' => 'Co je bod zvratu?', 'a' => 'Měsíc plánu, ve kterém kumulovaný zisk poprvé přesáhne nulu: do té doby fixní náklady převyšují to, co prodeje vydělají. Když se to v roce nestane, plán to řekne.'],
        ['q' => 'Odkud vzít náklad a cenu?', 'a' => 'Z nástroje Náklady tisku na vlastní tiskárně a ze Zisku prodejce; odkazy jsou v plánu. Produkt přenesený odtamtud má čísla předvyplněná.'],
        ['q' => 'Co dělá seznam kroků?', 'a' => 'Plánovač se podívá, co v plánu chybí: produkt bez nákladu nebo ceny, cena pod nákladem, nenafoceno, nevystaveno, nevybraný kanál, chybějící fixní náklady. Z toho udělá odškrtávací seznam na tento týden.'],
        ['q' => 'Kde se plán ukládá?', 'a' => 'V prohlížeči, takže přežije obnovení stránky. Po přihlášení ho uložíte k účtu pod názvem, až 20 plánů, a otevřete je na jiném zařízení.'],
        ['q' => 'Kolik to stojí?', 'a' => 'Plánovač, CSV i PDF jsou zdarma. Platíte jen tisk, pokud si ho u nás objednáte.'],
    ],
    'examples' => [],
];
