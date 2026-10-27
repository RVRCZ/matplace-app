<?php

// Stránka pro dotaz „qr kód 3d tisk“ (docs/AP.md §1, §5). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'QR kód na 3D tisk: cedulka a stojánek ke stažení zdarma',
    'heading' => 'QR kód na 3D tisk: cedulka se stojánkem',
    'description' => 'Vložíte odkaz, text nebo Wi‑Fi a nástroj vytvoří cedulku s QR kódem, kterou telefon přečte, a kontrolně ho přečte. STL zdarma, nebo dvoubarevný tisk u nás.',
    'h1' => 'Jak QR cedulka vzniká, proč musí být dvoubarevná a jak velká má být',
    'intro' => [
        'Nástroj vytvoří destičku s vystouplým QR kódem. Kód může otevřít web, jídelní lístek, recenze nebo platební odkaz, případně připojit telefon k Wi‑Fi: vložíte odkaz nebo text o délce 4 až 300 znaků, u Wi‑Fi název sítě, heslo a zabezpečení, a nástroj technický zápis složí sám. Pod kód lze přidat popisek do 40 znaků, k cedulce stolní stojánek, do kterého se zasune v mírném sklonu, nebo otvor na zavěšení. Velikost kódu nastavíte od 30 do 150 mm. Nástroj hlídá čitelnost: jedno pole kódu musí mít aspoň 0,9 mm, jinak vás vyzve kód zvětšit nebo odkaz zkrátit, a hotový návrh ještě kontrolně přečte stejně, jako to udělá telefon. Kód jde přečíst jen ve dvou barvách: světlá destička, tmavý kód.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): cedulka s odkazem na web, 70 × 83 × 3 mm, jedno pole kódu kolem 2 mm, má 14 g, tiskne se 25 minut a stojí u nás 99 Kč (kalkulace /c/lomf0wzlshw4); cedulka s Wi‑Fi o velikosti 90 mm se stolním stojánkem má 35 g, tiskne se 1 h 1 min a stojí 110 Kč, obojí v jedné barvě a bez dopravy. Při tisku u nás vyberete druhou barvu při objednávce a tiskárna ji vymění sama ve výšce destičky; stažený projekt pro vlastní tiskárnu má výměnu filamentu už vloženou, STL je jedno těleso. Odkaz si z náhledu vyzkoušejte telefonem ještě před tiskem, vytištěný kód už změnit nejde.',
    ],
    'steps' => [
        ['name' => 'Vložte odkaz, text, nebo Wi‑Fi', 'text' => 'Do pole „Odkaz nebo text“ vložíte adresu nebo jiný text; u Wi‑Fi název sítě, heslo a zabezpečení. Do pole „Popisek pod kódem“ krátký nápis, třeba název sítě; může zůstat prázdné.'],
        ['name' => 'Nastavte velikost', 'text' => 'Zadáte velikost kódu včetně volného okraje, 30 až 150 mm. Podle potřeby zaškrtnete stolní stojánek, nebo otvor na zavěšení.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Pod náhledem vidíte počet polí kódu a velikost jednoho pole. Když je kód na zvolenou velikost příliš hustý, nástroj napíše, na kolik milimetrů ho zvětšit.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší farmy a barvu destičky i kódu vyberete při objednávce. Nebo si model zdarma stáhnete jako STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jak udělám QR kód na Wi‑Fi?', 'a' => 'Nahoře zvolíte „Wi‑Fi“ a vyplníte název sítě, heslo a zabezpečení (WPA/WPA2, WEP, nebo bez hesla); skrytou síť zaškrtnete. Telefon po načtení kódu nabídne připojení. Heslo zůstává jen v kódu a v uloženém návrhu.'],
        ['q' => 'Proč nástroj chce kód zvětšit nebo odkaz zkrátit?', 'a' => 'Delší text znamená hustší kód a menší pole; pod 0,9 mm ho tryska 0,4 mm nevytiskne čistě a telefon ho nepřečte. Dlouhý odkaz zkraťte na adresu, kterou máte pod kontrolou, nebo cedulku zvětšete; nástroj řekne, na kolik milimetrů.'],
        ['q' => 'Jde vytištěný kód později změnit?', 'a' => 'Ne. Kód je součástí výtisku, proto odkaz před objednávkou vyzkoušejte. Pokud se cíl může změnit, vložte adresu, kterou umíte přesměrovat, třeba na vlastní doméně.'],
        ['q' => 'Proč musí být cedulka dvoubarevná?', 'a' => 'Telefon potřebuje kontrast mezi kódem a pozadím. Vystouplý kód vytištěný jednou barvou nepřečte, stín hran nestačí. Doporučujeme světlou destičku a tmavý kód; kód světlý na tmavé destičce čtou jen některé telefony.'],
        ['q' => 'Jak drží cedulka ve stojánku?', 'a' => 'Stojánek je samostatný díl s drážkou. Cedulka dostane dole prázdný pruh 10 mm, kterým se do drážky zasune, a stojí zakloněná asi o 12 stupňů, takže se čte od stolu i vestoje.'],
        ['q' => 'Co když mám vlastní tiskárnu?', 'a' => 'Model si zdarma stáhnete jako STL, nebo jako projekt pro svou tiskárnu. Projekt má ve výšce destičky vloženou výměnu filamentu: tiskárna se zastaví, vyměníte cívku a tisk pokračuje druhou barvou. Tiskárna s více cívkami to udělá sama.'],
    ],
    'examples' => [
        'QR cedulka 70 × 83 mm s odkazem na web a popiskem matplace.com: 14 g, 25 minut, 99 Kč.',
        'QR cedulka 90 × 113 mm s popiskem Wi‑Fi ve stolním stojánku, pro hosty kavárny.',
        'Malá QR destička 40 × 40 mm bez popisku, například na obal výrobku.',
    ],
];
