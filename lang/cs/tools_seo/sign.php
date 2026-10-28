<?php

// Stránka pro dotaz „cedulka se jménem 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Cedulka se jménem na 3D tisk: jmenovka i klíčenka zdarma',
    'heading' => 'Cedulka se jménem na 3D tisk',
    'description' => 'Napíšete jméno nebo text do tří řádků, vyberete tvar a jedno ze třiceti písem a hned vidíte model i rozměry. STL zdarma, nebo tisk ve dvou barvách.',
    'h1' => 'Co cedulka se jménem umí, jak se tiskne dvoubarevně a kolik stojí',
    'intro' => [
        'Nástroj vytvoří z textu, který napíšete, model cedulky na dveře, jmenovky, klíčenky nebo jména bez destičky. Text může mít jeden až tři řádky, každý nejvýše 40 znaků; druhý a třetí řádek jsou menší než první. Vyberete provedení písma (vystouplé, zapuštěné, obrys, nebo jen jméno bez destičky), tvar destičky a jedno ze třiceti písem s českou diakritikou; vlastní písmo nahrát nejde. Rozměry destičky se dopočítají samy podle výšky písma (4 až 80 mm), délky textu a okraje (2 až 30 mm), tloušťka desky je 1,2 až 10 mm; přidat jde očko na klíče, vystouplý rámeček nebo zkosená horní hrana. Vystouplé písmo, obrys a jméno bez destičky se tisknou druhou barvou: tiskárna ji vymění ve výšce, kde písmo začíná.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): klíčenka „Jana“ s očkem a rámečkem, 46 × 22 mm, má 2 g, tiskne se 5 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky; deset stejných klíčenek stojí dohromady 99 Kč, protože se vejdou do minima. Cedulka na dveře „Novákovi 12“ s vystouplým písmem a rámečkem, 173 × 69 mm, má 30 g, tiskne se 46 minut a stojí také 99 Kč. Je to náš nejčastěji počítaný nástroj: za 90 dní 33 kalkulací, většina z nich skončila stažením pro vlastní tiskárnu. Model stáhnete zdarma jako STL nebo projekt pro svou tiskárnu; u dvoubarevné varianty i desku a písmo jako samostatné díly.',
    ],
    'steps' => [
        ['name' => 'Napište text', 'text' => 'Do pole „První řádek“ napíšete jméno nebo nápis, druhý a třetí řádek jsou nepovinné. Tlačítky pod poli vložíte symboly, například srdce nebo hvězdu.'],
        ['name' => 'Vyberte provedení a tvar', 'text' => 'Zvolíte provedení písma, tvar destičky a písmo, nebo začnete od předvolby (klíčenka, cedulka na dveře, jmenovka, ozdoba s očkem, jméno psacím písmem). Můžete přidat očko na klíče, vystouplý rámeček nebo zkosenou horní hranu.'],
        ['name' => 'Nastavte rozměry', 'text' => 'Výšku písma nastavíte od 4 do 80 mm, tloušťku desky od 1,2 do 10 mm. Pod náhledem hned vidíte vnější rozměry.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší farmy a barvy vyberete při objednávce. Nebo si model zdarma stáhnete jako STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jak velká může cedulka být?', 'a' => 'Velikost určuje výška písma (4 až 80 mm), délka textu a okraj kolem něj (2 až 30 mm). Šířku destičky nezadáváte, dopočítá se a ukáže se pod náhledem. Když je model na tiskárnu příliš velký, upozorní na to kalkulace v dalším kroku.'],
        ['q' => 'Kolik stojí cedulky pro celou třídu nebo tým?', 'a' => 'Deset klíčenek 46 × 22 mm se vejde do nejnižší ceny zakázky, dohromady 99 Kč; počet kusů jednoho jména zadáte v kalkulaci, pro další jména uděláte další návrh. Větší cedulky na dveře se počítají z gramů a minut, cenu vidíte dřív, než cokoli objednáte.'],
        ['q' => 'Jde písmo vytisknout jinou barvou než deska?', 'a' => 'Ano, u vystouplého písma, obrysu a jména bez destičky. Při tisku u nás vyberete druhou barvu z těch, které jsou založené ve stejné tiskárně, a tiskárna ji vymění ve výšce, kde písmo začíná. Zapuštěné písmo se tiskne jednou barvou.'],
        ['q' => 'Jak cedulku připevním na dveře?', 'a' => 'Cedulka nemá otvory na šrouby. Zadní strana je rovná, takže ji přilepíte, například oboustrannou lepicí páskou. Otvor má jen varianta s očkem na klíče.'],
        ['q' => 'Která písmena a znaky nástroj umí?', 'a' => 'Třicet písem (bezpatková, patková, psaná rukou, ozdobná i strojové), všechna s českou diakritikou, a řadu symbolů pod textovými poli. Když některý znak ve zvoleném písmu chybí, nástroj vypíše, který to je. Vlastní písmo nahrát nejde.'],
        ['q' => 'Mohu si model vytisknout sám?', 'a' => 'Ano. Model si zdarma stáhnete jako soubor STL, nebo jako hotový projekt pro zhruba 270 tiskáren s výměnou filamentu ve výšce písma. U dvoubarevné varianty stáhnete desku a písmo i jako samostatné díly.'],
    ],
    'examples' => [
        'Klíčenka Jana s očkem a rámečkem, 46 × 22 mm, písmo druhou barvou: 2 g, 5 minut, 99 Kč.',
        'Cedulka na dveře Novákovi 12, 173 × 69 mm, vystouplé písmo a rámeček: 30 g, 46 minut, 99 Kč.',
        'Jméno Ela psacím písmem bez destičky, s očkem na klíče, 37 × 19 mm.',
    ],
];
