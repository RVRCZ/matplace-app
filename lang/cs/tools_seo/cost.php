<?php

return [
    'title' => 'Náklady 3D tisku na vlastní tiskárně: kalkulačka online',
    'description' => 'Spočítejte, co vás výtisk doopravdy stojí: filament, elektřina, opotřebení tiskárny, zmetky a práce. Doporučená cena s marží a vedle ní cena tisku u nás.',
    'h1' => 'Kolik stojí výtisk na vaší tiskárně a za kolik ho prodat',
    'intro' => [
        'Kalkulačka sečte všechno, co se do jednoho kusu schová: cenu filamentu podle gramů ze sliceru, proud podle příkonu tiskárny a doby tisku, odpis tiskárny rozpočítaný na hodiny její životnosti, podíl zmetků, které zaplatí povedené kusy, vaši práci v minutách a ostatní náklady jako obal nebo štítek. Výsledek je náklad na kus a doporučená cena s marží, kterou si zvolíte.',
        'Z kalkulace tisku na matplace se gramy a hodiny předvyplní a vedle vašeho nákladu uvidíte, za kolik stejný kus vytiskneme my. Kdo má u nás profil tiskárny, má po přihlášení předvyplněnou svou hodinovou sazbu a cenu filamentu. Všechno se počítá přímo v prohlížeči, nic se neukládá na server a nastavení si stránka pamatuje do příští návštěvy.',
    ],
    'steps' => [
        ['name' => 'Zadejte kus', 'text' => 'Cena filamentu za kilogram, gramy a hodiny ze sliceru. Z kalkulace tisku se doplní samy.'],
        ['name' => 'Zadejte tiskárnu', 'text' => 'Příkon, cena elektřiny, cena tiskárny a kolik hodin vytiskne, než ji odepíšete; podíl zmetků.'],
        ['name' => 'Zadejte práci', 'text' => 'Hodinová sazba a minuty na kus: nastavení, sundání, očištění, zabalení; ostatní náklady na kus.'],
        ['name' => 'Zvolte marži', 'text' => 'Procento nad náklad. Stránka hned ukáže náklad, cenu a kolik vydělá hodina tisku; cenu pošlete dál do zisku prodejce nebo plánu.'],
    ],
    'faq' => [
        ['q' => 'Proč počítat zmetky?', 'a' => 'Každý nepovedený tisk spotřeboval filament, proud a čas tiskárny. Když se nepovede jeden z dvaceti, nesou ho těch devatenáct povedených; kalkulačka to přičítá k nákladu na kus.'],
        ['q' => 'Jak odhadnout životnost tiskárny?', 'a' => 'Stolní tiskárna vydrží tisíce hodin, než potřebuje větší opravu nebo ji nahradíte. 5 000 hodin odpovídá třem rokům po pár hodinách denně; u levné tiskárny dejte míň, u průmyslové víc.'],
        ['q' => 'Co je správná marže?', 'a' => 'Z marže platíte poplatky platformy, dopravu navíc a riziko. Pro prodej přes Etsy nebo Fler bývá rozumná marže 40–100 %; přesně to ukáže nástroj Zisk prodejce.'],
        ['q' => 'Kde vezmu gramy a hodiny?', 'a' => 'Ze sliceru po nařezání modelu, nebo z kalkulace tisku na matplace: nahrajte model, kalkulace ho nařeže a tlačítkem přejdete sem s předvyplněnými čísly.'],
        ['q' => 'Co znamená „vytisknout u nás“?', 'a' => 'Cena z kalkulace za stejný kus na naší farmě, včetně materiálu a času. Nezahrnuje vaši práci ani zmetky; hodí se k porovnání, jestli se vyplatí tisknout doma.'],
        ['q' => 'Ukládáte moje čísla?', 'a' => 'Ne. Kalkulačka počítá v prohlížeči a nastavení si pamatuje jen ve vašem prohlížeči. Na server neodchází nic.'],
    ],
    'examples' => [],
];
