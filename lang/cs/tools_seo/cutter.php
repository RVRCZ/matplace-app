<?php

return [
    'title' => 'Vykrajovátko na cukroví podle vlastního tvaru',
    'description' => 'Napíšete jméno nebo číslici, případně nahrajete obrázek tvaru. Vznikne vykrajovátko na míru s obrubou. Vytiskneme ho, nebo si model zdarma stáhnete.',
    'h1' => 'Vykrajovátko ze jména, číslice nebo vlastní kresby',
    'intro' => [
        'Nástroj vytvoří vykrajovátko na cukroví z textu nebo z obrázku. Napíšete jméno, písmeno či číslici, nebo nahrajete kresbu tvaru, stačí i obrys nakreslený tužkou. Vznikne tenká stěna podle obrysu s obrubou, za kterou se vykrajovátko přitlačí. Šířku tvaru nastavíte od 30 do 150 mm, výšku stěny od 10 do 30 mm a zvolíte zúženou, nebo rovnou řeznou hranu.',
        'Když nahraný obrázek obsahuje čáry uvnitř obrysu, například žilky listu nebo oči a úsměv, vytvoří z nich nástroj samostatné razítko, které po vykrojení otisknete do těsta. Obrys je zrcadlený, takže po otočení obrubou nahoru vykrajuje tvar ve správném směru. Vykrajovátko si objednáte jako výtisk z naší tiskové farmy, nebo si model zdarma stáhnete pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Napište text nebo nahrajte tvar', 'text' => 'Text může mít dva řádky po 20 znacích a vyberete k němu jedno ze třiceti písem. Místo textu můžete nahrát SVG nebo obrázek tvaru.'],
        ['name' => 'Nastavte rozměry a hranu', 'text' => 'Zadáte šířku tvaru, výšku a tloušťku stěny a šířku obruby. Zúžená hrana řeže ostřeji, rovná je pevnější.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Náhled ukazuje vykrajovátko otočené tak, jak se používá, a vedle něj razítko, pokud vzniklo. Uzavřený vnitřek písmene, například u O, dostane vlastní stěnu, kterou se zbytkem spojí ploché příčky.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší tiskové farmy a zaplatíte z předplaceného kreditu. Nebo si model zdarma stáhnete jako soubor STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Z jakého obrázku vykrajovátko vznikne?', 'a' => 'Z jednoduché tmavé kresby na světlém pozadí nebo z SVG s vyplněnými tvary. Za tvar se počítá všechno, co kresba uzavírá, a použije se největší souvislá plocha. Fotografie se nehodí.'],
        ['q' => 'Kdy vznikne i razítko?', 'a' => 'Jen u nahraného obrázku PNG, JPG nebo WebP, který má uvnitř obrysu dost čar, a když necháte zaškrtnutou volbu „Razítko z kresby uvnitř“. U textu a u SVG razítko nevzniká.'],
        ['q' => 'Z čeho se vykrajovátko tiskne a jak se myje?', 'a' => 'Doporučujeme běžný plast PLA nebo pevnější PETG. Myjte ho ručně ve vlažné vodě, do myčky nepatří: PLA měkne už kolem 55 °C.'],
        ['q' => 'Je výtisk vhodný pro styk s potravinami?', 'a' => 'Vykrajovátko je určené pro krátký dotyk se syrovým těstem, které se potom peče. Pro trvalý styk s potravinami tištěné díly bez vložky nedoporučujeme.'],
        ['q' => 'Jak jemný může tvar být?', 'a' => 'Výběžky užší než dvojnásobek tloušťky stěny nástroj z tvaru vypustí, protože by se v nich těsto zasekávalo. Drobný text proto zvětšete, nebo zvolte kratší nápis.'],
        ['q' => 'Jak zaplatím a jak výtisk dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Vykrajovátko jména Ela psacím písmem, 82 × 54 mm včetně obruby, výška 18 mm.',
        'Vykrajovátko číslice 5 na narozeninové cukroví, 72 × 93 mm, zúžená řezná hrana.',
        'Vykrajovátko písmen MAMA patkovým písmem, 98 × 30 mm, rovná pevnější hrana.',
    ],
];
