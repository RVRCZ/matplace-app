<?php

return [
    'title' => 'Kontrola 3D modelu před tiskem online a zdarma',
    'description' => 'Nahrajete model a dozvíte se, zda má správné rozměry, uzavřenou síť a správně otočené plochy. U každého nálezu je napsáno, co znamená pro tisk.',
    'h1' => 'Zkontrolujte model dřív, než ho pošlete na tiskárnu',
    'intro' => [
        'Kontrola je pro chvíli, kdy máte model a nejste si jistí, jestli je připravený k tisku. Nahrajete soubor a za okamžik dostanete zprávu rozdělenou na chyby, doporučení a věci, které jsou v pořádku. U každého nálezu je napsáno, co způsobí při tisku a jak ho napravit.',
        'Kontrolujeme jen to, co umíme zjistit spolehlivě: rozměry a jednotky, uzavřenost sítě, převrácené plochy, počet samostatných těles a hustotu sítě. Převisy, pevnost stěn a přesnost nehodnotíme, protože závisí na tiskárně, materiálu a natočení modelu. Kontrola je zdarma, bez registrace a na souboru nic nemění.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Vyberte soubor nebo ho přetáhněte do pole. Podporované formáty a největší velikost souboru jsou napsané přímo u něj.'],
        ['name' => 'Přečtěte si zprávu', 'text' => 'Vedle otočného náhledu uvidíte nálezy ve třech skupinách: „Chyby“, „Doporučení“ a „V pořádku“.'],
        ['name' => 'Pokračujte k ceně', 'text' => 'Tlačítkem „Zjistit cenu a objednat tisk“ otevřete model v kalkulaci. Tam objednáte tisk u nás, nebo si model stáhnete.'],
    ],
    'faq' => [
        ['q' => 'Co přesně kontrola hlídá?', 'a' => 'Špatné jednotky, model tenčí než 0,8 mm, díry v síti, převrácené plochy, víc samostatných těles a příliš hustou nebo příliš hrubou síť. Také to, zda se model vejde na běžnou tiskárnu s prostorem 250 × 250 × 250 mm.'],
        ['q' => 'Zaručí kontrola, že se model povede vytisknout?', 'a' => 'Ne. Převisy, pevnost stěn a přesnost závisí na tiskárně, materiálu a natočení modelu a kontrola je neposuzuje.'],
        ['q' => 'Kontrola našla díry v síti nebo převrácené plochy. Co dál?', 'a' => 'Použijte náš nástroj na opravu modelu. Díry zacelí, plochy otočí a opravený soubor si stáhnete zdarma.'],
        ['q' => 'Proč kontrola hlásí, že model měří jen zlomek milimetru?', 'a' => 'Nejspíš byl uložen v metrech nebo palcích místo milimetrů. Uložte ho znovu v milimetrech a nahrajte ještě jednou.'],
        ['q' => 'Změní kontrola můj soubor?', 'a' => 'Ne. Kontrola soubor jen čte a nic v něm neupravuje.'],
    ],
    'examples' => [],
];
