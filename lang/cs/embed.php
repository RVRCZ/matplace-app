<?php

// The calculator for other sites (/embed: the page with the code; /embed/calc: the small page inside the frame), docs/AN.md §12.
return [
    'title' => 'Kalkulačka ceny 3D tisku na váš web',
    'description' => 'Vložte si na web nebo e-shop kalkulačku 3D tisku: návštěvník nahraje model a hned vidí cenu a dobu tisku. Jeden řádek kódu, zdarma.',
    'h1' => 'Kalkulačka ceny 3D tisku na váš web',
    'lead' => 'Píšete o 3D tisku nebo prodáváte filament? Vložte si naši kalkulačku: čtenář nahraje model (STL, 3MF, STEP), hned vidí odhad ceny a doby tisku a jedním klepnutím přejde na matplace.com, kde si výtisk objedná nebo stáhne model pro svou tiskárnu. Zdarma, bez registrace, bez cookies.',
    'code_title' => 'Kód k vložení',
    'code_hint' => 'Zkopírujte řádek do článku nebo šablony stránky. Jazyk kalkulačky odpovídá jazyku stránky, ze které kód kopírujete; na českém webu použijte českou verzi této stránky.',
    'copy' => 'Zkopírovat kód',
    'copied' => 'Zkopírováno',
    'preview_title' => 'Takhle vypadá',
    'rules_title' => 'Co je dobré vědět',
    'rules' => [
        'Kalkulačka počítá cenu tisku u nás (farma matplace), doprava se přičte až v objednávce.',
        'Soubory :formats do 120 MB; velký model (miliony trojúhelníků) dostane jen odhad, přesnou cenu spočítáme při objednávce.',
        'Nahraný soubor a výsledek patří tomu, kdo má odkaz; nic se neukládá do cookies vašeho webu.',
        'Návštěvy z vaší stránky vidíme jako zdroj „vložená kalkulačka“ s názvem vašeho webu; nic víc o vašich čtenářích nesbíráme.',
        'Kód můžete kdykoli odstranit; výsledky, které čtenáři otevřeli na matplace.com, zůstanou dostupné.',
    ],
    'contact' => 'Chcete kalkulačku v jiné velikosti nebo s předvoleným materiálem? Napište nám:',

    // the small page inside the frame
    'frame_title' => 'Kalkulačka ceny 3D tisku · matplace',
    'heading' => 'Cena 3D tisku',
    'pick' => 'Vyberte nebo přetáhněte model',
    'formats' => ':formats, do :max MB',
    'reading' => 'Nahrávám soubor…',
    'measuring' => 'Měřím model…',
    'counting' => 'Počítám přesnou cenu…',
    'big_model' => 'Velký model: přesnou cenu spočítáme při objednávce.',
    'estimate' => 'Orientační cena tisku u nás',
    'exact' => 'Přesná cena tisku u nás',
    'time' => 'Doba tisku :time',
    'price_note' => 'Bez dopravy; barvu, materiál a počet kusů nastavíte na matplace.com.',
    'open' => 'Otevřít na matplace.com',
    'again' => 'Jiný soubor',
    'failed' => 'Nepovedlo se. Zkuste to znovu, nebo otevřete matplace.com.',
    'too_big' => 'Soubor je větší než :max MB.',
    'not_model' => 'Tenhle soubor neumíme přečíst. Nahrajte STL, 3MF nebo STEP.',
    'footer_link' => 'Kalkulačka na váš web',
    'footer' => '3D tisk na zakázku:',
];
