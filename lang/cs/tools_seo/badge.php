<?php

return [
    'title' => 'Ozdoba na jojo držák karty z obrázku a jména',
    'description' => 'Nahrajete obrázek, připíšete jméno a vznikne barevná ozdoba na navíjecí držák vstupní karty. Vytiskneme ji, nebo si model zdarma stáhnete.',
    'h1' => 'Ozdoba na navíjecí držák karty s obrázkem a jménem',
    'intro' => [
        'Nástroj udělá z obrázku plochou ozdobu, která se nalepí na čelo navíjecího držáku karty (jojo, badge reel). Obrázek nahrajete jako PNG, JPG, WebP či SVG, nebo ho vyberete z knihovny; rozloží se do barev filamentů, které máme na farmě. Pod obrázek můžete připsat jméno do 16 znaků. Šířku nastavíte od 25 do 60 mm, tvar kopíruje obrys obrázku, nebo zvolíte kruh či obdélník.',
        'Jméno se vytiskne jednou z barev obrázku, tou, která je na podkladu nejlépe čitelná, takže nepřidá další filament ani další výměnu. Barvy leží jedna na druhé a tisknou se výměnou filamentu ve výškách; na farmě zvládneme až čtyři barvy v jednom tisku. Vzadu je mělká prohlubeň na lepicí kolečko nebo suchý zip, aby ozdoba seděla na středu držáku. Držák karty není součástí výtisku.',
    ],
    'steps' => [
        ['name' => 'Vyberte obrázek', 'text' => 'Nahrajte vlastní obrázek, nebo vyberte motiv z knihovny. Pozadí se odstraní samo a v seznamu barev vidíte, které cívky se použijí.'],
        ['name' => 'Připište jméno', 'text' => 'Jméno do 16 znaků se objeví pod obrázkem. Zmenší se tak, aby nebylo širší než obrázek, velká písmena mají nejvýš 7 mm.'],
        ['name' => 'Nastavte velikost a zadní stranu', 'text' => 'Šířku měníte posuvníkem nebo tažením šipky v náhledu. Prohlubeň vzadu má průměr 10 až 30 mm a hloubku 0,4 až 2 mm; jde i vypnout.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu, přesnou cenu o krok dál. Model i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak velká má ozdoba být?', 'a' => 'Čelo běžného navíjecího držáku mívá kolem 32 mm. Ozdoba široká 35 až 45 mm ho zakryje a nepřekáží; větší než 50 mm se o oblečení zachytává.'],
        ['q' => 'Jak ozdobu na držák připevním?', 'a' => 'Oboustranným lepicím kolečkem, kolečkem suchého zipu, nebo vteřinovým lepidlem. Prohlubeň vzadu nastavte na průměr kolečka, které máte; výchozí je 19 mm. Suchý zip dovolí ozdoby střídat.'],
        ['q' => 'Jakou barvu bude mít jméno?', 'a' => 'Tu z barev obrázku, která se od podkladu nejvíc liší světlostí. Když u té barvy v seznamu vyberete jinou cívku, změní se obrázek i jméno zároveň.'],
        ['q' => 'Vydrží ozdoba dezinfekci a nošení v práci?', 'a' => 'PLA snese otírání vodou i běžnou dezinfekcí na povrchy. Na slunci za sklem auta měkne kolem 55 °C; kdo ji tam nechává, zvolí PETG.'],
        ['q' => 'Kolik barev může obrázek mít?', 'a' => 'Až osm. Na naší farmě tiskneme nejvýš čtyři různé barvy v jednom tisku včetně podkladu; návrh s více barvami si stáhnete jako projekt s výměnami pro svou tiskárnu.'],
        ['q' => 'Jak zaplatím a jak ozdobu dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Usměvavá hvězda se jménem Jana, 40 × 47 mm, tři barvy.',
        'Srdce na kulatém podkladu o průměru 38 mm, bez jména.',
        'Tlapka na obdélníku 45 × 54 mm se jménem Petr.',
    ],
];
