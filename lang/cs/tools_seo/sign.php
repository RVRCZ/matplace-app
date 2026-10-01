<?php

return [
    'title' => 'Cedulka se jménem na 3D tisk, jmenovka i klíčenka',
    'description' => 'Napíšete jméno nebo text, vyberete tvar a písmo a hned vidíte model i cenu. Cedulku, jmenovku nebo klíčenku vám vytiskneme, nebo si ji stáhnete.',
    'h1' => 'Cedulka, jmenovka nebo klíčenka s vlastním textem',
    'intro' => [
        'Nástroj vytvoří z textu, který napíšete, model cedulky na dveře, jmenovky, klíčenky nebo jména bez destičky. Text může mít jeden nebo dva řádky, každý nejvýše 40 znaků; druhý řádek je menší než první. Vyberete provedení písma (vystouplé, zapuštěné, obrys, nebo jen jméno bez destičky), tvar destičky a jedno ze čtyř písem. Rozměry destičky se dopočítají samy podle délky textu, výšky písma a okraje.',
        'Náhled se mění při každé úpravě a pod ním vidíte vnější rozměry v milimetrech a orientační cenu. Hotový návrh si objednáte jako výtisk z naší tiskové farmy, nebo si ho zdarma stáhnete pro svou tiskárnu. Vystouplé písmo umíme vytisknout druhou barvou: tiskárna ji vymění ve výšce, kde písmo začíná. Barvy vybíráte z těch, které jsou právě založené v tiskárnách.',
    ],
    'steps' => [
        ['name' => 'Napište text', 'text' => 'Do pole „První řádek“ napíšete jméno nebo nápis, druhý řádek je nepovinný. Tlačítky pod poli vložíte symboly, například srdce nebo hvězdu.'],
        ['name' => 'Vyberte provedení a tvar', 'text' => 'Zvolíte provedení písma, tvar destičky a písmo, nebo začnete od předvolby (klíčenka, cedulka na dveře, jmenovka, ozdoba s očkem, jméno psacím písmem). Můžete přidat očko na klíče, vystouplý rámeček nebo zkosenou horní hranu.'],
        ['name' => 'Nastavte rozměry', 'text' => 'Výšku písma nastavíte od 4 do 80 mm, tloušťku desky od 1,2 do 10 mm. Pod náhledem hned vidíte vnější rozměry a orientační cenu.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší tiskové farmy a zaplatíte z předplaceného kreditu. Nebo si model zdarma stáhnete jako soubor STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jak velká může cedulka být?', 'a' => 'Velikost určuje výška písma (4 až 80 mm), délka textu a okraj kolem něj (2 až 30 mm). Šířku destičky nezadáváte, dopočítá se a ukáže se pod náhledem. Když je model na tiskárnu příliš velký, upozorní na to kalkulace v dalším kroku.'],
        ['q' => 'Jde písmo vytisknout jinou barvou než deska?', 'a' => 'Ano, u vystouplého písma, obrysu a jména bez destičky. Při tisku u nás vyberete druhou barvu z těch, které jsou založené ve stejné tiskárně, a tiskárna ji vymění ve výšce, kde písmo začíná. Zapuštěné písmo se tiskne jednou barvou.'],
        ['q' => 'Jak cedulku připevním na dveře?', 'a' => 'Cedulka nemá otvory na šrouby. Zadní strana je rovná, takže ji přilepíte, například oboustrannou lepicí páskou. Otvor má jen varianta s očkem na klíče.'],
        ['q' => 'Která písmena a znaky nástroj umí?', 'a' => 'Čtyři písma (bezpatkové, patkové, strojové a psací) včetně české diakritiky a řadu symbolů pod textovými poli. Když některý znak ve zvoleném písmu chybí, nástroj vypíše, který to je. Vlastní písmo nahrát nejde.'],
        ['q' => 'Mohu si model vytisknout sám?', 'a' => 'Ano. Model si zdarma stáhnete jako soubor STL, nebo jako hotový projekt pro zhruba 270 tiskáren. U dvoubarevné varianty stáhnete desku a písmo i jako samostatné díly.'],
        ['q' => 'Jak zaplatím a jak výtisk dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Klíčenka Jana s očkem a rámečkem, 46 × 22 mm, písmo druhou barvou.',
        'Cedulka na dveře Novákovi 12, 173 × 77 mm, vystouplé písmo a rámeček.',
        'Jméno Ela psacím písmem bez destičky, s očkem na klíče, 37 × 19 mm.',
    ],
];
