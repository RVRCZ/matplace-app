<?php

// Stránka pro dotaz „zápich do dortu se jménem“ (docs/AP.md §6). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Zápich do dortu se jménem a číslem: 3D tisk na míru, zdarma',
    'heading' => 'Zápich do dortu se jménem a číslem',
    'description' => 'Číslo, srdce, hvězda nebo jen nápis se jménem druhou barvou a hroty na zapíchnutí. Zápich vytiskneme ve dvou barvách, nebo si model stáhnete zdarma.',
    'h1' => 'Jak zápich vzniká, jak velký má být a kolik stojí',
    'intro' => [
        'Nástroj vytvoří zápich do dortu: velké číslo, srdce, hvězdu nebo kruh a přes ně jméno jiným písmem a jinou barvou, nebo jen samotný nápis bez tvaru. Stačí napsat jméno do 20 znaků a číslo do tří číslic, třeba „2“ a „Olivia“. Šířku tvaru nastavíte od 50 do 200 mm, jméno může tvar přesahovat a posunete ho výš nebo níž; jméno má třicet písem. Dole má zápich jeden nebo dva hroty dlouhé 30 až 100 mm. Všechno je jeden plochý kus silný 2 až 4 mm; písmena, která by přečnívala nebo se nedotýkala, nástroj sám podloží rozšířenou kopií v barvě tvaru a spojí můstkem. Jméno vystupuje o 0,4 až 1,2 mm a tiskne se druhou barvou, takže stačí jedna výměna filamentu; na naší farmě zápich vytiskneme najednou a obě barvy si vyberete při objednávce.',
        'Příklad z kalkulace na produkci (27. 10. 2026, PLA): číslo 2 široké 110 mm se jménem Olivia psacím písmem a dvěma hroty 60 mm má 30 g, tiskne se 55 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky (kalkulace /c/4jdvyezywc3l, jedna barva, druhou vyberete při objednávce). Tiskne se naplocho bez podpěr; při tloušťce 3 mm je lehký a neohýbá se. Tištěný plast není určený pro styk s potravinou, hroty proto obalte fólií nebo zasuňte do brčka. Model i projekt s výměnou filamentu stáhnete zdarma a bez registrace; na oslavu objednávejte s předstihem, dobu tisku a doručení ukáže kalkulace.',
    ],
    'steps' => [
        ['name' => 'Napište jméno a číslo', 'text' => 'Jméno nebo krátký nápis a k němu číslo, například věk oslavence. Pro srdce, hvězdu a kruh číslo nepotřebujete.'],
        ['name' => 'Vyberte tvar a písmo', 'text' => 'Číslo, srdce, hvězda, kruh, nebo jen nápis. Jméno má třicet písem; psané, kde se písmena dotýkají, drží pohromadě samo, u ostatních nástroj písmena spojí.'],
        ['name' => 'Nastavte velikost a hroty', 'text' => 'Šířku tvaru, šířku jména a jeho polohu měníte posuvníky a náhled se hned překreslí. Hroty mohou být jeden nebo dva; pro vysoký dort zvolte delší.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Model i projekt s výměnou filamentu stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Může se tištěný zápich dotýkat dortu?', 'a' => 'Tištěný plast není určený pro styk s potravinou. Hroty proto před zapíchnutím obalte potravinářskou fólií, nebo je zasuňte do brčka. Samotný tvar se jménem je nad dortem a krému se nedotýká.'],
        ['q' => 'Jak velký zápich zvolit?', 'a' => 'Na dort o průměru 20 cm se hodí tvar široký 100 až 120 mm. Hroty dlouhé 60 mm stačí na běžný dort, na vysoký patrový zvolte 80 až 100 mm.'],
        ['q' => 'Jde udělat zápich s rokem nebo nápisem na oslavu?', 'a' => 'Ano. Číslo má nejvýš tři číslice, rok tedy napište jako nápis: „2027“ nebo „Šťastný nový rok“ se do 20 znaků vejde a vyberete mu písmo. Bez tvaru za nápisem zvolte provedení „jen nápis“.'],
        ['q' => 'Z čeho se zápich tiskne?', 'a' => 'Doporučujeme PLA. Při tloušťce 3 mm je zápich lehký a přitom se neohýbá; tiskne se naplocho a bez podpěr. Po oslavě ho omyjte ručně ve vlažné vodě a použijte znovu.'],
        ['q' => 'Vytisknete ho ve dvou barvách?', 'a' => 'Ano. Tvar s hroty a jméno leží nad sebou, takže stačí jedna výměna filamentu. Obě barvy si vyberete při objednávce z filamentů, které máme skladem; zlatá a růžová, nebo černá a bílá patří k nejčastějším.'],
        ['q' => 'Co když se písmena nedotýkají tvaru?', 'a' => 'Pod jménem leží jeho rozšířená kopie v barvě tvaru, takže drží i písmena, která tvar přesahují. Oddělené kousky nástroj spojí krátkým můstkem a upozorní na to.'],
    ],
    'examples' => [
        'Zápich s číslem 2 širokým 110 mm a jménem Olivia psacím písmem, dva hroty 60 mm: 30 g, 55 minut, 99 Kč.',
        'Srdce 120 mm s nápisem Ela a Tom na svatební dort.',
        'Nápis Všechno nejlepší široký 180 mm bez tvaru, hroty 80 mm.',
    ],
];
