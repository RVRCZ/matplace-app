<?php

return [
    'title' => 'QR kód na 3D tisk: cedulka a stolní stojánek',
    'description' => 'Vložíte odkaz, text nebo údaje k Wi-Fi a nástroj vytvoří cedulku s QR kódem, kterou telefon přečte. Vytiskneme ji dvoubarevně, nebo si ji stáhnete.',
    'h1' => 'QR cedulka s popiskem a stojánkem',
    'intro' => [
        'Nástroj vytvoří destičku s vystouplým QR kódem. Kód může otevřít web, jídelní lístek nebo platební odkaz, případně připojit telefon k Wi-Fi: vložíte odkaz nebo text o délce 4 až 300 znaků. Pod kód lze přidat popisek do 40 znaků a k cedulce stolní stojánek, do kterého se zasune v mírném sklonu. Velikost kódu nastavíte od 30 do 150 mm.',
        'Nástroj hlídá čitelnost. Jedno pole kódu musí mít aspoň 0,9 mm, jinak vás vyzve kód zvětšit nebo odkaz zkrátit, a hotový návrh ještě kontrolně přečte. Kód jde přečíst jen ve dvou barvách: světlá destička, tmavý kód. Při tisku u nás vyberete druhou barvu při objednávce, stažený projekt pro vlastní tiskárnu má výměnu filamentu už vloženou.',
    ],
    'steps' => [
        ['name' => 'Vložte odkaz nebo text', 'text' => 'Do pole „Odkaz nebo text“ vložíte adresu nebo jiný text, do pole „Popisek pod kódem“ krátký nápis, například název sítě. Popisek můžete nechat prázdný.'],
        ['name' => 'Nastavte velikost', 'text' => 'Zadáte velikost kódu včetně volného okraje, 30 až 150 mm. Podle potřeby zaškrtnete stolní stojánek, nebo otvor na zavěšení.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Pod náhledem vidíte počet polí kódu, velikost jednoho pole a orientační cenu. Když je kód na zvolenou velikost příliš hustý, nástroj napíše, na kolik milimetrů ho zvětšit.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší tiskové farmy, vyberete barvu destičky i kódu a zaplatíte z předplaceného kreditu. Nebo si model zdarma stáhnete jako soubor STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jak udělám QR kód na Wi-Fi?', 'a' => 'Do pole pro odkaz napíšete text ve tvaru WIFI:T:WPA;S:název sítě;P:heslo;; a telefon po načtení nabídne připojení. Samostatný formulář na Wi-Fi nástroj nemá.'],
        ['q' => 'Jde vytištěný kód později změnit?', 'a' => 'Ne. Kód je součástí výtisku, proto odkaz před objednávkou vyzkoušejte. Pokud se cíl může změnit, vložte adresu, kterou máte pod kontrolou a umíte ji přesměrovat.'],
        ['q' => 'Proč musí být cedulka dvoubarevná?', 'a' => 'Telefon potřebuje kontrast mezi kódem a pozadím. Vystouplý kód vytištěný jednou barvou nepřečte. Doporučujeme světlou destičku a tmavý kód.'],
        ['q' => 'Jak drží cedulka ve stojánku?', 'a' => 'Stojánek je samostatný díl s drážkou. Cedulka dostane dole prázdný pruh 10 mm, kterým se do drážky zasune, a stojí zakloněná asi o 12 stupňů.'],
        ['q' => 'Co když mám vlastní tiskárnu?', 'a' => 'Model si zdarma stáhnete jako soubor STL, nebo jako projekt pro svou tiskárnu. Projekt má ve výšce destičky vloženou výměnu filamentu: tiskárna se zastaví, vyměníte cívku a tisk pokračuje druhou barvou.'],
        ['q' => 'Jak zaplatím a jak výtisk dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'QR cedulka 70 × 83 mm s odkazem na web a popiskem matplace.com.',
        'QR cedulka 90 × 113 mm s popiskem Wi-Fi ve stolním stojánku, pro hosty kavárny.',
        'Malá QR destička 40 × 40 mm bez popisku, například na obal výrobku.',
    ],
];
