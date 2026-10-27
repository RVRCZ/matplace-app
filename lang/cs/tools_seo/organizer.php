<?php

// Stránka pro dotaz „organizér do šuplíku na míru“ (docs/AP.md §1, §5). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Organizér do šuplíku na míru: návrh online, model zdarma',
    'heading' => 'Organizér do šuplíku na míru',
    'description' => 'Zadáte vnitřní rozměry zásuvky a počet přihrádek, hned vidíte 3D náhled. Model stáhnete zdarma jako STL nebo projekt pro svou tiskárnu, nebo ho vytiskneme.',
    'h1' => 'Co organizér umí, jak ho navrhnout na míru a kolik stojí tisk',
    'intro' => [
        'Nástroj vytvoří organizér s přihrádkami podle rozměrů, které zadáte v milimetrech: šířka a hloubka od 30 do 400 mm, výška od 10 do 150 mm, nejvýše 8 řad a 8 sloupců, tedy 64 přihrádek, každá uvnitř aspoň 8 mm. Všechny přihrádky jsou stejně velké a organizér je jeden kus bez víka, který se tiskne bez podpěr. Výchozí stěna má 1,6 mm a dno 1,2 mm, obojí změníte v rozmezí 0,8 až 4 mm, rohy jdou zaoblit. Můžete začít od předvolby „Zásuvka“, „Kancelářské potřeby“ nebo „Drobné součástky“. Náhled se po každé změně překreslí a ukáže vnější rozměry i velikost jedné přihrádky, takže vidíte, jestli se do ní vejde příbor, baterie nebo šroubky. Hodí se do zásuvky v kuchyni, v dílně nebo v koupelně a také na stůl.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): organizér 200 × 120 × 40 mm se 3 × 2 přihrádkami má 115 g, tiskne se 2 h 27 min a stojí u nás 250 Kč (kalkulace /c/l1gr0izblzwr); malý 100 × 80 × 30 mm se 2 × 2 přihrádkami má 41 g, tiskne se 57 minut a stojí 110 Kč, vše bez dopravy. Organizér 300 × 400 × 50 mm už kalkulace označí jako větší než běžná podložka: takovou zásuvku rozdělte na dva organizéry vedle sebe, nebo použijte modulární misky. Kdo má vlastní tiskárnu, stáhne si model zdarma jako projekt pro svůj stroj (na výběr je asi 270 tiskáren) nebo jako STL; tisk u nás posíláme Zásilkovnou.',
    ],
    'steps' => [
        ['name' => 'Změřte zásuvku', 'text' => 'Změřte vnitřní šířku, hloubku a výšku zásuvky v nejužším místě. Na každé straně si nechte 1 až 2 mm vůle, zásuvky nebývají přesně pravoúhlé.'],
        ['name' => 'Zadejte rozměry a přihrádky', 'text' => 'Vyplňte šířku, hloubku, výšku, řady a sloupce. Můžete začít od předvolby „Zásuvka“, „Kancelářské potřeby“ nebo „Drobné součástky“.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Otočný náhled ukáže tvar, vnější rozměry a velikost jedné přihrádky. Překreslí se s každou změnou.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vyberte materiál, barvu a počet kusů a pokračujte k přesné ceně. Nebo zvolte „Mám tiskárnu, stáhnout model“.'],
    ],
    'faq' => [
        ['q' => 'Jak velký organizér mohu navrhnout?', 'a' => 'Šířka a hloubka od 30 do 400 mm, výška od 10 do 150 mm. Organizér nad 250 mm se nemusí vejít na každou tiskárnu; kalkulace to hned řekne a zásuvku pak rozdělíte na dva organizéry vedle sebe.'],
        ['q' => 'Kolik přihrádek může mít a mohou být různě velké?', 'a' => 'Nejvýše 8 řad a 8 sloupců, tedy 64 přihrádek, každá uvnitř aspoň 8 mm. Mřížka je pravidelná; pro různě velké přihrádky použijte modulární organizér z misek, kde si rozložení poskládáte sami.'],
        ['q' => 'Jak silné jsou stěny a dno?', 'a' => 'Výchozí stěna má 1,6 mm a dno 1,2 mm, to na příbory a kancelářské potřeby stačí. Pod volbou „Tloušťka stěn a další“ obojí změníte v rozmezí 0,8 až 4 mm; na šroubky a těžké věci zvolte 2 mm a víc.'],
        ['q' => 'Mohu přidat víko nebo dělicí stěny jiné výšky?', 'a' => 'V tomto nástroji ne: organizér je otevřený a všechny stěny mají stejnou výšku. Krabičku s víkem udělá nástroj Krabička na míru, různě vysoké misky nástroj Modulární organizér.'],
        ['q' => 'Jak organizér tisknout doma?', 'a' => 'Naplocho dnem dolů, bez podpěr; stažený projekt má nastavení z kalkulace (vrstva 0,2 mm, výplň 15 %). Do kuchyně nebo koupelny zvolte PETG, které lépe snáší teplo a vlhko, jinak stačí PLA.'],
        ['q' => 'Musím si organizér nechat vytisknout u vás?', 'a' => 'Nemusíte. Model si stáhnete zdarma jako projekt připravený pro vaši tiskárnu, na výběr je asi 270 tiskáren, nebo jako samotný soubor STL. Tisk u nás je pro ty, kdo tiskárnu nemají nebo potřebují velký kus.'],
    ],
    'examples' => [
        'Organizér do zásuvky 300 × 200 × 45 mm s osmi přihrádkami.',
        'Organizér na stůl 200 × 100 × 60 mm, tři přihrádky na psací potřeby.',
        'Nízký organizér 160 × 120 × 25 mm s dvaceti přihrádkami na drobné součástky.',
    ],
];
