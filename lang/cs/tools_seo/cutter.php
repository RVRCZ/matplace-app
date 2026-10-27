<?php

// Stránka pro dotaz „vykrajovátko na míru“ (docs/AP.md §6). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Vykrajovátko na míru ze jména nebo kresby, model zdarma',
    'heading' => 'Vykrajovátko na míru ze jména nebo kresby',
    'description' => 'Napíšete jméno nebo číslici, nebo nahrajete kresbu tvaru, a vznikne vykrajovátko s obrubou i razítkem. Vytiskneme ho, nebo si model stáhnete zdarma.',
    'h1' => 'Jak vykrajovátko vzniká, z jakého tvaru, z čeho se tiskne a kolik stojí',
    'intro' => [
        'Nástroj vytvoří vykrajovátko na cukroví z textu nebo z obrázku. Napíšete jméno, písmeno či číslici ve dvou řádcích po 20 znacích v jednom ze třiceti písem, nebo nahrajete kresbu tvaru, stačí i obrys nakreslený tužkou a vyfocený. Vznikne tenká stěna podle obrysu s obrubou, za kterou se vykrajovátko přitlačí: šířka tvaru 30 až 150 mm, výška stěny 10 až 30 mm, stěna 0,8 až 1,6 mm, obruba 3 až 10 mm, řezná hrana zúžená nebo rovná. Když nahraný obrázek obsahuje čáry uvnitř obrysu, například žilky listu nebo oči a úsměv, vznikne z nich samostatné razítko, které po vykrojení otisknete do těsta. Obrys je zrcadlený, takže po otočení obrubou nahoru vykrajuje tvar ve správném směru; uzavřený vnitřek písmene, třeba u O, dostane vlastní stěnu spojenou plochými příčkami.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): vykrajovátko jména Ela psacím písmem, 82 × 54 mm včetně obruby a 18 mm vysoké, má 13 g, tiskne se 31 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky; vykrajovátko číslice 5 na narozeninové cukroví, 72 × 93 mm, má 12 g, tiskne se 25 minut a stojí také 99 Kč. Tiskne se obrubou dolů, bez podpěr, z PLA nebo pevnějšího PETG; myje se ručně ve vlažné vodě, do myčky nepatří. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu, nebo ho necháte vytisknout u nás.',
    ],
    'steps' => [
        ['name' => 'Napište text nebo nahrajte tvar', 'text' => 'Text může mít dva řádky po 20 znacích a vyberete k němu jedno ze třiceti písem. Místo textu můžete nahrát SVG nebo obrázek tvaru.'],
        ['name' => 'Nastavte rozměry a hranu', 'text' => 'Zadáte šířku tvaru, výšku a tloušťku stěny a šířku obruby. Zúžená hrana řeže ostřeji, rovná je pevnější.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Náhled ukazuje vykrajovátko otočené tak, jak se používá, a vedle něj razítko, pokud vzniklo. Uzavřený vnitřek písmene, například u O, dostane vlastní stěnu, kterou se zbytkem spojí ploché příčky.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Nebo si model zdarma stáhnete jako STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Z jakého obrázku vykrajovátko vznikne?', 'a' => 'Z jednoduché tmavé kresby na světlém pozadí nebo z SVG s vyplněnými tvary. Za tvar se počítá všechno, co kresba uzavírá, a použije se největší souvislá plocha. Fotografie se nehodí; dětskou kresbu obtáhněte fixem a vyfoťte shora.'],
        ['q' => 'Kdy vznikne i razítko?', 'a' => 'Jen u nahraného obrázku PNG, JPG nebo WebP, který má uvnitř obrysu dost čar, a když necháte zaškrtnutou volbu „Razítko z kresby uvnitř“. U textu a u SVG razítko nevzniká.'],
        ['q' => 'Jak velké vykrajovátko zvolit?', 'a' => 'Na běžné cukroví 50 až 70 mm, na velký perník nebo nápis se jménem 100 až 150 mm, nejméně 30 mm. Výška stěny 18 mm stačí na linecké i perníkové těsto; na vysoké těsto zvolte 25 mm.'],
        ['q' => 'Z čeho se vykrajovátko tiskne a jak se myje?', 'a' => 'Doporučujeme běžný plast PLA nebo pevnější PETG. Myjte ho ručně ve vlažné vodě, do myčky nepatří: PLA měkne už kolem 55 °C.'],
        ['q' => 'Je výtisk vhodný pro styk s potravinami?', 'a' => 'Vykrajovátko je určené pro krátký dotyk se syrovým těstem, které se potom peče. Pro trvalý styk s potravinami tištěné díly bez vložky nedoporučujeme.'],
        ['q' => 'Jak jemný může tvar být?', 'a' => 'Výběžky užší než dvojnásobek tloušťky stěny nástroj z tvaru vypustí, protože by se v nich těsto zasekávalo. Drobný text proto zvětšete, nebo zvolte kratší nápis a tučnější písmo.'],
    ],
    'examples' => [
        'Vykrajovátko jména Ela psacím písmem, 82 × 54 mm včetně obruby, výška 18 mm: 13 g, 31 minut, 99 Kč.',
        'Vykrajovátko číslice 5 na narozeninové cukroví, 72 × 93 mm, zúžená řezná hrana: 12 g, 25 minut, 99 Kč.',
        'Vykrajovátko písmen MAMA patkovým písmem, 98 × 30 mm, rovná pevnější hrana.',
    ],
];
