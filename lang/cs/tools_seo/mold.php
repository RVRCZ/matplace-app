<?php

return [
    'title' => 'Forma na odlévání z 3D modelu: vytvořte ji online',
    'description' => 'Z vlastního 3D modelu vytvoříte formu k tisku: ze dvou až čtyř dílů, nebo formu na silikon. Nástroj změří podřezy a spočítá objem hmoty na odlitek.',
    'h1' => 'Forma k tisku z vašeho modelu: dělená, nebo na silikon',
    'intro' => [
        'Nástroj je pro ty, kdo chtějí svůj tvar odlévat a potřebují k němu formu. Nahrajete 3D model a nástroj kolem něj postaví formu připravenou k tisku. Jednoduché tvary jdou odlévat přímo do tištěné formy ze dvou až čtyř dílů. Pro sošky a jiné tvary s podřezy je určená forma na silikon.',
        'Ještě před stavbou nástroj změří, kterou část povrchu by tuhá forma nepustila, a označí ji na modelu červeně. Dostanete formu se zámky, které drží díly v zákrytu, s nalévacím otvorem a s údajem, kolik hmoty je potřeba na jeden odlitek. Forma se otevře jako nový soubor. Necháte ji vytisknout u nás, nebo si ji stáhnete.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Přetáhněte model do pole nebo klepněte a vyberte soubor. Model musí být uzavřené těleso o velikosti 5 až 400 mm.'],
        ['name' => 'Prohlédněte si podřezy', 'text' => 'Nástroj změří, co by tištěná forma nepustila, a označí ta místa červeně. Ukáže také podíl podřezů pro 2, 3 a 4 díly.'],
        ['name' => 'Zvolte typ formy', 'text' => 'Vyberte tištěnou formu, nebo formu na silikon, tloušťku stěny a počet dílů. Dělení a polohu řezu můžete nechat na automatice.'],
        ['name' => 'Vytvořte formu', 'text' => 'Klepněte na „Vytvořit formu“. Stavba trvá pár vteřin, u velkých modelů až dvě minuty.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tlačítkem „Cena a tisk formy“ otevřete formu v kalkulaci. Tam zjistíte cenu, objednáte tisk nebo si soubor stáhnete.'],
    ],
    'faq' => [
        ['q' => 'Co je podřez a proč vadí?', 'a' => 'Podřez je místo, za které se forma při rozebírání zachytí. Tuhá forma takový odlitek nepustí bez poškození.'],
        ['q' => 'Kdy zvolit formu na silikon?', 'a' => 'Když nástroj ukáže, že tvar z tištěné formy nepůjde vyndat ani při více dílech. Vytisknete podstavec s modelem a objímku, model zalijete silikonem a odléváte do měkké formy, kterou z modelu stáhnete. Silikon si pořídíte sami, nástroj spočítá, kolik ho je potřeba.'],
        ['q' => 'Co dělá volba „Vyplnit podřezy“?', 'a' => 'Místa, která by formu držela, se v odlitku zaplní hmotou, takže tištěná forma půjde rozebrat. Odlitek se v těch místech liší od modelu. Výsledek uvidíte předem v náhledu, přidaná hmota je oranžová.'],
        ['q' => 'Z jakého materiálu formu tisknout?', 'a' => 'Tvar bez podřezů z běžného tuhého plastu. Při malých podřezech nástroj doporučí pružné TPU, protože z tuhé formy by se odlitek vylamoval.'],
        ['q' => 'Jaký model nástroj nezpracuje?', 'a' => 'Model menší než 5 mm nebo větší než 400 mm a model, ze kterého se nepodaří udělat uzavřené těleso. Model s dírami nejdřív opravte v našem nástroji na opravu.'],
        ['q' => 'Kolik vytvoření formy stojí?', 'a' => 'Vytvoření formy je zdarma. Platíte jen tisk, pokud si ho u nás objednáte.'],
    ],
    'examples' => [],
];
