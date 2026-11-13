<?php

return [
    'title' => 'Vybarvit 3D model online: vícebarevný tisk z STL a 3MF',
    'description' => 'Nahrajte STL nebo 3MF a v prohlížeči určete, která část má kterou barvu: vylitím plochy, podle výšky nebo štětcem. Bez sliceru, i na telefonu.',
    'h1' => 'Vybarvení 3D modelu pro vícebarevný tisk',
    'intro' => [
        'Nástroj obarví nahraný model přímo v prohlížeči. Ťuknutím vylijete barvu na celou souvislou plochu až k hraně nebo na celý díl, štětcem domalujete detaily a podle výšky obarvíte vodorovné pásy. Barvy vybíráte z filamentů, ze kterých tiskneme, a nástroj hlídá, kolik barev se do jednoho tisku vejde.',
        'Výsledek je 3MF s barvami namalovanými na modelu, stejný, jaký ukládá Bambu Studio, OrcaSlicer nebo Anycubic Slicer Next. V kalkulaci hned uvidíte cenu jednobarevně i barevně a tisk si objednáte, nebo si soubor stáhnete pro vlastní tiskárnu s měničem filamentů. Tvar modelu se nemění, štětec jen zjemní síť tam, kde malujete.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'STL, 3MF, OBJ nebo STEP. Model se ukáže v náhledu; 3MF, který už barvy má, se otevře s nimi.'],
        ['name' => 'Vyberte barvy', 'text' => 'Barvu modelu a další barvy z nabídky filamentů. Nástroj říká, kolik jich ještě můžete přidat.'],
        ['name' => 'Vybarvěte', 'text' => 'Vylít: ťuknutí obarví plochu až k hraně. Štětec: táhnete po povrchu. Podle výšky: od zvolené výšky nahoru jiná barva.'],
        ['name' => 'Zjistěte cenu, nebo stáhněte', 'text' => 'Kalkulace ukáže cenu jednobarevně i barevně. Barevný 3MF si můžete stáhnout pro vlastní slicer.'],
    ],
    'faq' => [
        ['q' => 'Na jaké tiskárně se vybarvený model vytiskne?', 'a' => 'Na tiskárně, která umí během tisku měnit filament: s jednotkou AMS, MMU, ACE nebo s více hlavami. U nás tiskneme barevné modely na takových strojích najednou, na jedné podložce. Barvy nad sebou zvládne i tiskárna s jednou tryskou, když při změně barvy vyměníte cívku ručně.'],
        ['q' => 'Kolik barev může model mít?', 'a' => 'Tolik, kolik cívek drží tiskárna s měničem; nástroj počet ukazuje a víc barev přidat nedovolí. Počítejte s tím, že každá další barva ve stejné vrstvě znamená výměnu filamentu a prodlužuje tisk.'],
        ['q' => 'Proč je barevný tisk dražší než jednobarevný?', 'a' => 'Při každé výměně barvy tiskárna starý filament vytlačí do čisticí věže a nový zavede. Trvá to asi dvě minuty a stojí trochu plastu. Model s barvami vedle sebe po celé výšce má takovou výměnu v každé vrstvě, model s barvami nad sebou jen jednu na každou změnu.'],
        ['q' => 'Jak jemné detaily se dají namalovat?', 'a' => 'Tryska má průměr 0,4 mm, tenčí čáru nevytiskne. Štětec proto kreslí okraj s přesností kolem 0,4 mm a skvrny menší než půl milimetru čtverečního při uložení sloučíme s okolní barvou; kolik jich bylo, vám nástroj řekne.'],
        ['q' => 'Změní vybarvení tvar modelu?', 'a' => 'Ne. Vylití a barvy podle výšky jen přiřadí barvu ploškám modelu, štětec navíc rozdělí plošky pod tahem na menší, aby okraj barvy šel tam, kudy jste táhli. Povrch i rozměry zůstávají stejné.'],
        ['q' => 'Zvládne nástroj velké modely a telefon?', 'a' => 'Vylití a barvy podle výšky fungují do milionu trojúhelníků na počítači a do půl milionu na telefonu. Štětec zjemňuje síť, proto pracuje do půl milionu trojúhelníků na počítači a do dvou set tisíc na telefonu; u větších modelů nástroj štětec vypne a řekne to.'],
    ],
    'examples' => [],
];
