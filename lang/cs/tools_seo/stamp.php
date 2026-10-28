<?php

// Stránka pro dotaz „razítko na míru 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Razítko na míru k 3D tisku do hlíny a modelovací hmoty',
    'heading' => 'Razítko na míru k 3D tisku do hlíny a modelovací hmoty',
    'description' => 'Napíšete text nebo nahrajete motiv a nástroj vytvoří razítko s držadlem, zrcadlené pro správný otisk do keramiky a modelovací hmoty. Model zdarma.',
    'h1' => 'Do čeho razítko tiskne, jak vzniká a kolik stojí',
    'intro' => [
        'Nástroj vytvoří razítko pro otisk do keramické hlíny, polymerové hmoty, mýdla nebo těsta, tedy do měkkého materiálu, který otisk podrží. Motivem může být text do dvou řádků po 20 znacích v jednom ze čtyř písem, SVG nebo jednoduchý černobílý obrázek; nahraná předloha má přednost před textem. Motiv se zrcadlí sám, takže otisk vyjde čitelně; náhled jde přepnout na otisk, který razítko zanechá. Šířku motivu nastavíte od 15 do 120 mm, jeho výšku od 0,8 do 4 mm a vyberete razítko s kulatým držadlem, nebo jen destičku. Motiv může být vystouplý, pak se otiskne dovnitř, nebo zahloubený, pak otisk z hmoty vystoupí. Nástroj upozorní na čáry tenčí než 0,8 mm. Na inkoust a papír se tištěné razítko nehodí, jeho povrch je příliš tvrdý.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): razítko EVA široké 50 mm s držadlem a vystouplým motivem má 6,8 g, tiskne se 28 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/qr5iss66z3ip). Držadlo se tiskne zvlášť a na destičku se přilepí vteřinovým lepidlem. Razítko s logem pro keramickou dílnu nebo značkou autora na dno hrnků se tiskne stejně, jen nahrajete SVG. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Napište text nebo nahrajte motiv', 'text' => 'Text napíšete do jednoho nebo dvou řádků, nebo nahrajete SVG či obrázek s tmavým motivem na světlém pozadí. Nahraná předloha má přednost před textem.'],
        ['name' => 'Vyberte motiv a držadlo', 'text' => 'Zvolíte vystouplý, nebo zahloubený motiv a razítko s držadlem, nebo bez něj. Nastavíte šířku a výšku motivu.'],
        ['name' => 'Přepněte náhled na otisk', 'text' => 'Tlačítkem „Otisk, který zanechá“ zkontrolujete, jak bude otisk vypadat. Nástroj upozorní na čáry tenčí než 0,8 mm.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší farmy. Nebo si model zdarma stáhnete jako soubor STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Hodí se razítko na papír a inkoust?', 'a' => 'Ne, povrch tištěného razítka je na to příliš tvrdý a inkoust na něm nedrží. Je určené k otiskování do keramické hlíny, polymerové hmoty, mýdla nebo těsta.'],
        ['q' => 'Musím motiv předem zrcadlit?', 'a' => 'Ne. Nástroj zrcadlí text i nahraný obrázek sám. Předlohu připravte tak, jak má vypadat hotový otisk, a náhledem „Otisk, který zanechá“ to zkontrolujte.'],
        ['q' => 'Jak se připevní držadlo?', 'a' => 'Držadlo se tiskne jako samostatný díl a na destičku se přilepí vteřinovým lepidlem. Lepidlo není součástí výtisku; bez držadla slouží destička jako embosovací deska pod válečkem.'],
        ['q' => 'Jaký je rozdíl mezi vystouplým a zahloubeným motivem?', 'a' => 'Vystouplý motiv se do hmoty vtlačí a zanechá prohloubený otisk, třeba podpis na dno hrnku. Zahloubený motiv je vybraný v destičce, takže otisk z hmoty vystoupí, třeba reliéfní logo na mýdle.'],
        ['q' => 'Jak jemné detaily razítko zvládne?', 'a' => 'Čáry tenčí než 0,8 mm se nevytisknou čistě nebo vůbec a nástroj na ně upozorní. Pomůže zvětšit šířku razítka nebo čáry v předloze zesílit; výška motivu 1 až 1,5 mm dává do hlíny nejčitelnější otisk.'],
        ['q' => 'Jak razítko tisknout doma a jak ho čistit?', 'a' => 'Motivem nahoru, bez podpěr, z PLA nebo PETG, vrstva 0,2 mm nebo jemnější pro hladší hrany. Hlínu z razítka vymyjete vodou a kartáčkem; před otiskem do lepivé hmoty razítko poprašte kukuřičným škrobem.'],
    ],
    'examples' => [
        'Razítko EVA s držadlem, destička 56 × 23 mm, vystouplý motiv do hlíny: 6,8 g, 28 minut, 99 Kč.',
        'Dvouřádkové razítko HAND MADE s držadlem, destička 66 × 41 mm, na keramiku.',
        'Destička OK bez držadla, 46 × 25 mm, zahloubený motiv, otisk vystoupí.',
    ],
];
