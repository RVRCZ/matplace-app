<?php

return [
    'consent' => [
        'label' => 'Souhlasím, aby Matplace zveřejnil časosběrné video mého tisku na svém YouTube kanálu. (Nepovinné, souhlas můžete kdykoli odvolat.)',
    ],

    'order' => [
        'short_download' => 'Stáhnout čtvercové video (Instagram, TikTok, Shorts)',
        'title' => 'Video na YouTube',
        'on' => 'Souhlasíte se zveřejněním časosběrného videa tohoto tisku na YouTube kanálu Matplace. Video před zveřejněním kontrolujeme.',
        'off' => 'Video tohoto tisku nezveřejníme. Pokud chcete, aby se objevilo na YouTube kanálu Matplace, můžete to povolit.',
        'agree' => 'Povolit zveřejnění',
        'withdraw' => 'Odvolat souhlas',
        'withdraw_confirm' => 'Odvolat souhlas? Pokud už je video na YouTube, smažeme ho.',
        'published' => 'Video je zveřejněné:',
        'watch' => 'Přehrát na YouTube',
        'watch_wide' => 'Klasické video (16:9)',
        'saved_on' => 'Děkujeme, souhlas je uložený.',
        'saved_off' => 'Souhlas je odvolaný. Video na YouTube nebude.',
    ],

    'video' => [
        'music' => 'Hudba: :track',
        'card' => ':material · :time tisku',
        'card_time' => ':time tisku',
        'tail' => ':time tisku za :seconds s',
        'tail_plain' => ':time 3D tisku',
        'title' => 'Časosběr 3D tisku – :material :color',
        'cta' => 'Nahrajte model a hned uvidíte cenu: :url',
        'cta_tool' => 'Udělejte si vlastní: :url',
        'price' => 'Cena: :price',
        'ask_short' => 'Co vytisknout příště?',
        'comment' => ':cta Cena tohoto výtisku: :price.',
        'hashtags' => '#3dtisk #3dtiskarna #dárek',
        'title_plain' => ':name – :time tisku',
        'title_unnamed' => 'Co vyroste za :time 3D tisku',
        'description' => ":cta\nČasosběr tisku z naší 3D tiskové farmy; hotový kus a cenu vidíte na konci.\n\nMateriál: :material :color\nTiskárna: :printer\nDoba tisku: :time\n\nVideo zveřejňujeme se souhlasem zákazníka.",
    ],

    // the first second of a Short: what the viewer is about to see, by the tool that made the model (the hook)
    'hook' => [
        'figure' => 'Z jedné fotky → busta', 'pet' => 'Z fotky mazlíčka → figurka', 'relief' => 'Z fotky → reliéf', 'papel' => 'Z fotky → portrét v papíru',
        'qr' => 'QR kód, který se dá vzít do ruky', 'box' => 'Zákazník poslal rozměr → krabička', 'organizer' => 'Rozměr šuplíku → organizér',
        'vase' => 'Pár křivek → váza', 'sign' => 'Jméno → cedulka', 'nameplate' => 'Jméno → jmenovka na dveře', 'medallion' => 'Jméno → medaile s řetízkem',
        'phone_stand' => 'Tři čísla → stojánek na telefon', 'cap' => 'Rozbité víčko → nové', 'holder' => 'Rozměr → držák na míru', 'cable_holder' => 'Kabely → držák',
        'ornament' => 'Jméno → vánoční ozdoba', 'gingerbread' => 'Obrázek → vykrajovátko', 'cookie' => 'Obrázek → vykrajovátko', 'topper' => 'Jméno → zápich na dort',
        'stamp' => 'Logo → razítko', 'logo' => 'Logo → 3D', 'text' => 'Nápis → 3D', 'map' => 'Adresa → mapa', 'default' => 'Z modelu → výtisk za :time',
    ],
    // what a Short is called, by the tool (the print time follows)
    'title' => [
        'figure' => 'Busta z jedné fotky', 'pet' => 'Figurka mazlíčka z fotky', 'relief' => 'Reliéf z fotky', 'papel' => 'Portrét z fotky v papíru',
        'qr' => 'QR kód, který se dá vzít do ruky', 'box' => 'Krabička přesně podle rozměru', 'organizer' => 'Organizér přesně do šuplíku',
        'vase' => 'Váza bez jediného švu', 'sign' => 'Cedulka se jménem', 'nameplate' => 'Jmenovka na dveře', 'medallion' => 'Medaile se jménem a řetízkem',
        'phone_stand' => 'Stojánek na telefon na míru', 'cap' => 'Nové víčko místo rozbitého', 'holder' => 'Držák na míru', 'cable_holder' => 'Držák kabelů na míru',
        'ornament' => 'Vánoční ozdoba se jménem', 'gingerbread' => 'Vykrajovátko z vlastního obrázku', 'cookie' => 'Vykrajovátko na míru', 'topper' => 'Zápich na dort se jménem',
        'stamp' => 'Razítko z loga', 'logo' => 'Logo ve 3D', 'text' => 'Nápis ve 3D', 'map' => 'Mapa místa ve 3D',
    ],
    // one hashtag to the subject, next to the three of the channel
    'hashtag' => [
        'figure' => '#figurka', 'pet' => '#mazlíček', 'relief' => '#reliéf', 'papel' => '#portrét', 'qr' => '#qrkod', 'box' => '#krabička', 'organizer' => '#organizér',
        'vase' => '#váza', 'sign' => '#cedulka', 'nameplate' => '#jmenovka', 'medallion' => '#medaile', 'phone_stand' => '#stojánek', 'cap' => '#oprava', 'holder' => '#držák',
        'cable_holder' => '#držák', 'ornament' => '#vánoce', 'gingerbread' => '#vánoce', 'cookie' => '#vykrajovátko', 'topper' => '#dort', 'stamp' => '#razítko', 'logo' => '#logo',
        'text' => '#nápis', 'map' => '#mapa', 'default' => '#3dprinting',
    ],

    'weekly' => [
        'card' => 'Tento týden na farmě',
        'title' => 'Tento týden na farmě – :dates',
        'description' => ":cta\nCo se tento týden (:dates) tisklo na naší 3D tiskové farmě: :count tisků v časosběru. Každý kus má kapitolu s odkazem na nástroj, ve kterém vznikl.\n\nVidea zveřejňujeme se souhlasem zákazníků.",
    ],
];
