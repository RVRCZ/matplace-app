<?php

// Stránka pro dotaz „obraz z filamentu“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Obraz z filamentu z obrázku nebo fotky: vrstvy, zdarma',
    'heading' => 'Obraz z filamentu: obrázek nebo fotka jako vrstvy barevného plastu',
    'description' => 'Obrázek nebo fotku převede nástroj do 1 až 8 barev filamentů: vrstvené desky v rámu, nebo jeden tisk s barvami nad sebou. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Dva způsoby, jak obraz vyrobit, a co stojí',
    'intro' => [
        'Nástroj převede obrázek nebo fotku do 1 až 8 barev filamentů, které máme na farmě skladem, a postaví z nich obraz na zeď o šířce 50 až 250 mm. Ve výchozím režimu je to vrstvený obraz: každá barva je samostatná deska 1,5 až 3 mm silná a desky se skládají za sebe jako vystřihovánka; zadní nese celou siluetu, přední jen svou barvu. Mezi deskami jsou distanční sloupky 2 až 5 mm, takže obraz má hloubku a stín; kdo chce tenký obraz, nechá desky naplocho. K tomu kulatý nebo čtvercový rám s drážkou na hřebík a volitelně místem na LED pásek. Druhý režim je jeden tisk: podklad a na něm barvy po 0,4 mm nad sebou, v každé vrstvě jediný filament, takže ho vytiskne každá tiskárna výměnou filamentu ve výškách z projektu. Pozadí fotky se odstraní samo, u fotky přes celou plochu to vypnete. U každé barvy vidíte cívku, kterou nástroj vybral, a vyměníte ji za jinou z katalogu, barvy přeskládáte nebo sloučíte.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): sněhulák z knihovny jako vrstvený obraz ze čtyř desek v kulatém rámu 120 mm má 105,6 g, tiskne se 2 h 46 min a stojí u nás 200 Kč bez dopravy (kalkulace /c/tmwh0ok9ixpv); barvu každé desky i rámu vyberete při objednávce v jedné zakázce. Perníček jako jeden tisk 120 mm široký, podklad a tři barvy nad sebou, má 15,9 g, tiskne se 26 minut a stojí 99 Kč (kalkulace /c/etcdmot38mpy). Vedle náhledu je návod: pořadí desek od zadní k přední s kresbou každé z nich. Desky si stáhnete každou zvlášť jako STL, nebo celý návrh jako projekt pro slicer, zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek', 'text' => 'Obrázek nahrajete, vyberete z knihovny, nebo použijete dříve nahraný. Pozadí se odstraní samo; u fotky, která má vyplnit celou plochu, odstranění vypněte.'],
        ['name' => 'Zvolte, jak to vyrobit', 'text' => 'Vrstvený obraz z desek s rámem, nebo jeden tisk s barvami nad sebou. Nastavíte šířku, tloušťku desek a mezeru mezi nimi, případně rám a místo na LED pásek.'],
        ['name' => 'Dolaďte barvy', 'text' => 'Zvolíte počet barev a každé přiřadíte filament z našeho katalogu. Barvy můžete posunout dopředu či dozadu, nebo dvě sloučit v jednu; u fotky přepněte čtení na „malba“.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu ukáže kalkulace po „Pokračovat“. Desky si stáhnete každou zvlášť jako STL, nebo celý návrh jako projekt pro slicer, zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak se vrstvený obraz skládá?', 'a' => 'Každou desku vytisknete v její barvě a pak je lepíte podle návodu od zadní k přední. Distanční sloupky jsou součástí desky a zapadnou pod desku před ní. Stačí vteřinové lepidlo. Rám se tiskne dnem dolů a desky se vlepí dovnitř.'],
        ['q' => 'Co když mám jen jednobarevnou tiskárnu?', 'a' => 'Vrstvený obraz je pro ni jako dělaný: každá deska je jednobarevný tisk. Jeden tisk s barvami nad sebou zvládne také, projekt obsahuje výměny filamentu ve správných výškách; tiskárna se zastaví a vy filament vyměníte. Tiskárna s AMS, MMU nebo ACE vymění cívky sama.'],
        ['q' => 'Z jakého obrázku to vyjde nejlépe?', 'a' => 'Z kresby nebo loga s několika plnými barvami. Z fotky nástroj v režimu „malba“ vybere hlavní barvy a zjednoduší plochy na milimetry, výsledek je plakátový; pro portrét ve stupních šedi se hodí spíš litofanie. Linky tenčí než 0,8 mm by se jako samostatná deska zlomily, proto je nástroj přiřadí barvě za nimi a řekne to.'],
        ['q' => 'Jaký rám a jak se věší?', 'a' => 'Kulatý nebo čtvercový, 6 až 20 mm široký, s drážkou na hřebík v zadní stěně. S volbou „Místo na LED pásek“ stojí desky o 10 mm dál od zadní stěny a dole je výřez na kabel; pásek si pořídíte sami.'],
        ['q' => 'Jak se liší od HueForge a od litofanie?', 'a' => 'HueForge míchá odstíny průsvitem tenkých vrstev a potřebuje přesně nastavený slicer; náš jeden tisk klade plné barvy nad sebe, takže vypadá stejně na každé tiskárně a výsledek vidíte předem. Litofanie má jen jednu barvu a obraz vzniká tloušťkou proti světlu; obraz z filamentu je barevný i bez světla.'],
        ['q' => 'Z čeho se obraz tiskne a v jakých barvách?', 'a' => 'Z PLA, které má největší výběr barev a tiskne se naplocho bez podpěr. Barvy jsou cívky, které máme opravdu skladem, takže výtisk vypadá jako náhled. Při objednávce u nás vyberete barvu každé desky i rámu v jedné zakázce.'],
    ],
    'examples' => [
        'Sněhulák jako vrstvený obraz ze čtyř desek v kulatém rámu 120 mm: 105,6 g, 2 h 46 min, 200 Kč.',
        'Menší obraz 100 mm ze tří desek bez rámu, naplocho: 25 g, 46 minut, 99 Kč, nejnižší cena zakázky.',
        'Perníček jako jeden tisk 120 mm: podklad a tři barvy po 0,4 mm nad sebou, 15,9 g, 26 minut, 99 Kč.',
    ],
];
