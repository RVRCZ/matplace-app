<?php

// Stránka pro dotaz „puzzle z 3d modelu“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Puzzle pro 3D tisk z modelu nebo z fotky: generátor zdarma',
    'heading' => 'Puzzle z 3D modelu nebo z fotky: skládačka pro 3D tisk zdarma',
    'description' => 'Plochý model, reliéf z fotky, logo nebo litofanie rozřezaná na puzzle 2 × 2 až 8 × 8 dílků se zámky nebo kolíky, s čísly zespodu a táckem. Online, zdarma.',
    'h1' => 'Jak puzzle vzniká, jak velké dílky zvolit a kolik stojí',
    'intro' => [
        'Nástroj rozřeže plochý model na puzzle: mřížku 2 × 2 až 8 × 8 dílků s klasickými zámky, tedy hlavou a krčkem, které do sebe zapadnou s vůlí 0,2 mm. Hodí se reliéf z fotky, logo, litofanie nebo podtácek z našich nástrojů, nebo cokoli plochého do 25 mm; vyšší model dostane místo zámků skryté kolíky Ø 3 mm v bocích, protože zámek by se v tlustém plastu nedal zasunout. Zámky i jejich směr nástroj rozloží sám, dílky na kraji sledují obrys modelu. Do spodní strany každého dílku vyryje číslo, takže se puzzle složí i bez obrázku, a volitelně přidá tácek s lemem 6 mm, do kterého složené puzzle zapadne. Model, který není uzavřený, nástroj nejdřív uzavře; dílek, který by se kvůli otvoru rozpadl, si nechá největší kousek a řekne to.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): kulatý podtácek 100 mm se sněhulákem z nástroje Podtácek rozřezaný na 3 × 4 dílky se zámky a s čísly zespodu má 22,6 g, tiskne se 1 h 3 min a stojí u nás 99 Kč bez dopravy (kalkulace /c/wnr4wkkungpz); dílky se tisknou najednou vedle sebe a každý dílek může mít při objednávce svou barvu. Puzzle se otevře jako nový soubor: necháte ho vytisknout u nás, nebo si dílky stáhnete po jednom či jako projekt pro slicer, zdarma a bez registrace. Nástroj sám je zdarma.',
    ],
    'steps' => [
        ['name' => 'Nahrajte plochý model', 'text' => 'Přetáhněte model do pole nebo klepněte a vyberte soubor: STL, 3MF, OBJ nebo STEP, nejlépe reliéf, logo, litofanii nebo podtácek do 25 mm výšky. Model z našich nástrojů sem pošlete rovnou z kalkulace.'],
        ['name' => 'Zvolte mřížku a spojení', 'text' => 'Počet řad a sloupců, puzzle zámky (velikost hlavy a vůle) nebo skryté kolíky, čísla zespodu a tácek.'],
        ['name' => 'Udělejte puzzle', 'text' => 'Klepněte na „Udělat puzzle“. Trvá to pár vteřin; dílky vidíte v náhledu každý svou barvou a nástroj řekne, jak jsou velké.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Dílky stáhnete po jednom nebo jako projekt pro slicer, nebo je necháte vytisknout u nás; tácek a kolíky jsou samostatné díly.'],
    ],
    'faq' => [
        ['q' => 'Jak velké mají dílky být?', 'a' => 'Aspoň 12 mm, lépe 25 až 40 mm: tak velké zámky se tisknou spolehlivě a dílky se dobře berou do ruky. Podtácek 100 mm dává při 3 × 4 dílky asi 33 × 25 mm; reliéf 200 mm unese 6 × 6. Menší dílky nástroj odmítne.'],
        ['q' => 'Budou zámky držet, nebo se dílky rozpadnou?', 'a' => 'Zámky mají vůli 0,2 mm (jde změnit od 0,1 do 0,4), což u běžné tiskárny s tryskou 0,4 mm dává pevné, ale rozebíratelné spojení. Příliš těsné zámky zvětšete vůli, volné zmenšete; hlavu zámku nastavíte na 25 až 45 % dílku.'],
        ['q' => 'Kdy zvolit skryté kolíky?', 'a' => 'Když má vrch zůstat bez zámků, třeba u litofanie nebo loga, nebo když je model vyšší než 25 mm. Kolíky Ø 3 mm jsou v souboru jako samostatný díl, dílky se na ně nasadí a slepí.'],
        ['q' => 'Jde udělat puzzle z fotky?', 'a' => 'Ano, přes dva nástroje: fotku nejdřív převedete na reliéf nebo litofanii v nástroji Reliéf a litofanie, nebo na barevný podtácek, a hotový model pošlete sem. Puzzle z litofanie se skládá proti oknu, puzzle z podtácku má barvy.'],
        ['q' => 'K čemu je tácek?', 'a' => 'Tácek s lemem 6 mm a dnem 1,5 mm podle obrysu modelu; složené puzzle do něj zapadne s vůlí a dá se vystavit nebo pověsit. Tiskne se jako samostatný díl.'],
        ['q' => 'Jak puzzle tisknout doma?', 'a' => 'Dílky naplocho, lícem nahoru, bez podpěr, vrstva 0,2 mm, PLA; projekt pro slicer je má rozložené vedle sebe. U vícebarevného podtácku zůstávají výměny filamentu ve výškách barev. Tácek se tiskne dnem dolů.'],
    ],
    'examples' => [],
];
