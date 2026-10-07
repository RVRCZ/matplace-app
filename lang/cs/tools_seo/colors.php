<?php

return [
    'title' => 'Barevný 3MF na díly podle barev: rozdělení online',
    'description' => 'Nahrajte vícebarevný 3MF z Bambu Studia, Orcy nebo PrusaSliceru a dostanete samostatný díl na každou barvu: STL pro tisk po jedné barvě nebo pro lepení.',
    'h1' => 'Barevný model rozdělený na díly podle barev',
    'intro' => [
        'Nástroj přečte barvy z 3MF: materiály souboru, přiřazení extruderu u objektů a dílů i malování štětcem z Bambu Studia, Orca Sliceru nebo PrusaSliceru. Každá barva se stane samostatným dílem. Těleso, které je celé v jedné barvě, zůstane, jak je; barva namalovaná na povrchu se vyřízne jako vložka zadané hloubky (obvykle 1,2 mm) a tělo dostane stejně hluboké vybrání, aby vložka zapadla.',
        'Díly stáhnete po jednom jako STL a vytisknete každý svou barvou na libovolné tiskárně, nebo je necháte na místě a vytisknete najednou jako vícemateriálový tisk. Náhled ukáže díly v barvách souboru a říká, odkud barvy pocházejí. U trojúhelníků, které slicer při malování rozdělil, platí barva větší části a nástroj to řekne. Rozdělení je zdarma, model neopouští náš server a po měsíci se maže.',
    ],
    'steps' => [
        ['name' => 'Nahrajte barevný 3MF', 'text' => 'Soubor uložený ze sliceru s barvami, nebo 3MF s materiály z CAD programu. Nástroj hned ukáže nalezené barvy a jejich podíl.'],
        ['name' => 'Zvolte hloubku vložek', 'text' => 'Jak hluboko má namalovaná barva do modelu sahat, 0,6 až 3 mm. U samostatných těles na tom nezáleží.'],
        ['name' => 'Rozdělte', 'text' => 'Klepněte na „Rozdělit podle barev“. Za pár vteřin je hotovo: díly v náhledu v barvách, seznam s názvy a podíly.'],
        ['name' => 'Stáhněte nebo objednejte', 'text' => 'Každý díl zvlášť jako STL, nebo celek pro tisk najednou. Cenu tisku u nás vidíte hned.'],
    ],
    'faq' => [
        ['q' => 'Jaké barvy nástroj pozná?', 'a' => 'Materiály 3MF (basematerials, skupiny barev), barvy na objektu i na jednotlivých trojúhelnících, extruder přiřazený objektu nebo dílu v Bambu Studiu, Orce a PrusaSliceru a malování štětcem (paint_color, mmu_segmentation). Barvy filamentů bere z projektu; bez nich použije pevnou paletu.'],
        ['q' => 'Co se stane s namalovanou barvou?', 'a' => 'Namalované plochy se posunou dovnitř o zadanou hloubku a uzavřou stěnami, vznikne vložka. Tělo dostane stejně hluboké vybrání. Vložka je o 0,05 mm vyšší, aby šla po tisku zabrousit do roviny.'],
        ['q' => 'Proč má díl nečekanou barvu?', 'a' => 'Slicer při malování dělí trojúhelníky na menší; nástroj dává celému trojúhelníku barvu jeho větší části a napíše, kolika trojúhelníků se to týká. Jemnější výsledek dostanete, když model před malováním zjemníte.'],
        ['q' => 'Dá se to vytisknout najednou?', 'a' => 'Ano: celek drží díly na svých místech, ve sliceru je přiřadíte extruderům nebo slotům AMS a tisknete jako jeden vícemateriálový tisk. Na jednobarevné tiskárně tisknete díly po jednom a slepíte je.'],
        ['q' => 'Kolik barev zvládne?', 'a' => 'Až 16; menší barvy nad tento počet připadnou té největší. Soubor může mít až 2 miliony trojúhelníků.'],
        ['q' => 'Ukládáte můj model?', 'a' => 'Model zůstává na našem serveru jen kvůli výpočtu a stažení a po měsíci se maže. Nikomu ho neposíláme.'],
    ],
    'examples' => [],
];
