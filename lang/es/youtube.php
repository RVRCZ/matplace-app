<?php

return [
    'consent' => [
        'label' => 'Acepto que Matplace publique el vídeo time-lapse de mi impresión en su canal de YouTube. (Opcional; puede retirarlo en cualquier momento.)',
    ],

    'order' => [
        'short_download' => 'Descargar el vídeo cuadrado (Instagram, TikTok, Shorts)',
        'title' => 'Vídeo en YouTube',
        'on' => 'Acepta que el time-lapse de esta impresión se publique en el canal de YouTube de Matplace. Revisamos cada vídeo antes de publicarlo.',
        'off' => 'No publicaremos el vídeo de esta impresión. Si quiere que aparezca en el canal de YouTube de Matplace, puede permitirlo.',
        'agree' => 'Permitir la publicación',
        'withdraw' => 'Retirar el consentimiento',
        'withdraw_confirm' => '¿Retirar el consentimiento? Si el vídeo ya está en YouTube, lo borraremos.',
        'published' => 'El vídeo está publicado:',
        'watch' => 'Ver en YouTube',
        'watch_wide' => 'Vídeo clásico (16:9)',
        'saved_on' => 'Gracias, el consentimiento está guardado.',
        'saved_off' => 'El consentimiento está retirado. El vídeo no estará en YouTube.',
    ],

    'video' => [
        'music' => 'Música: :track',
        'card' => ':material · :time de impresión',
        'card_time' => ':time de impresión',
        'tail' => ':time de impresión en :seconds s',
        'tail_plain' => ':time de impresión 3D',
        'title' => 'Time-lapse de impresión 3D – :material :color',
        'cta' => 'Suba un modelo y verá el precio al instante: :url',
        'cta_tool' => 'Haga el suyo: :url',
        'price' => 'Precio: :price',
        'ask_short' => '¿Qué imprimimos la próxima vez?',
        'comment' => ':cta Esta impresión costó :price.',
        'hashtags' => '#3dtisk #3dtiskarna #dárek',
        'title_plain' => ':name – :time de impresión',
        'title_unnamed' => 'Lo que crece en :time de impresión 3D',
        'description' => ":cta\nTime-lapse de una impresión de nuestra granja de impresión 3D; la pieza terminada y su precio están al final.\n\nMaterial: :material :color\nTiempo de impresión: :time\n\nPublicado con el consentimiento del cliente.",
    ],

    'hook' => [
        'figure' => 'Una foto → un busto', 'pet' => 'La foto de una mascota → una figura', 'relief' => 'Una foto → un relieve', 'papel' => 'Una foto → un retrato de papel',
        'qr' => 'Un código QR que se puede tocar', 'box' => 'El cliente envió una medida → una caja', 'organizer' => 'La medida del cajón → un organizador',
        'vase' => 'Unas curvas → un jarrón', 'sign' => 'Un nombre → un letrero', 'nameplate' => 'Un nombre → una placa de puerta', 'medallion' => 'Un nombre → una medalla con cadena',
        'phone_stand' => 'Tres números → un soporte de móvil', 'cap' => 'Una tapa rota → una nueva', 'holder' => 'Una medida → un soporte', 'cable_holder' => 'Cables → un soporte',
        'ornament' => 'Un nombre → un adorno navideño', 'gingerbread' => 'Un dibujo → un cortador', 'cookie' => 'Un dibujo → un cortador', 'topper' => 'Un nombre → un adorno de tarta',
        'stamp' => 'Un logo → un sello', 'logo' => 'Un logo → 3D', 'text' => 'Un rótulo → 3D', 'map' => 'Una dirección → un mapa', 'default' => 'Un modelo → una impresión en :time',
    ],
    'title' => [
        'figure' => 'Un busto a partir de una foto', 'pet' => 'La figura de una mascota a partir de una foto', 'relief' => 'Un relieve a partir de una foto', 'papel' => 'Un retrato de papel a partir de una foto',
        'qr' => 'Un código QR que se puede tocar', 'box' => 'Una caja exactamente a medida', 'organizer' => 'Un organizador exacto para el cajón',
        'vase' => 'Un jarrón sin costura', 'sign' => 'Un letrero con nombre', 'nameplate' => 'Una placa de puerta', 'medallion' => 'Una medalla con nombre y cadena',
        'phone_stand' => 'Un soporte de móvil a medida', 'cap' => 'Una tapa nueva por una rota', 'holder' => 'Un soporte a medida', 'cable_holder' => 'Un soporte de cables a medida',
        'ornament' => 'Un adorno navideño con nombre', 'gingerbread' => 'Un cortador de galletas con su propio dibujo', 'cookie' => 'Un cortador de galletas a medida', 'topper' => 'Un adorno de tarta con nombre',
        'stamp' => 'Un sello a partir de un logo', 'logo' => 'Un logo en 3D', 'text' => 'Un rótulo en 3D', 'map' => 'El mapa de un lugar en 3D',
    ],
    'hashtag' => [
        'figure' => '#figurka', 'pet' => '#mazlíček', 'relief' => '#reliéf', 'papel' => '#portrét', 'qr' => '#qrkod', 'box' => '#krabička', 'organizer' => '#organizér',
        'vase' => '#váza', 'sign' => '#cedulka', 'nameplate' => '#jmenovka', 'medallion' => '#medaile', 'phone_stand' => '#stojánek', 'cap' => '#oprava', 'holder' => '#držák',
        'cable_holder' => '#držák', 'ornament' => '#vánoce', 'gingerbread' => '#vánoce', 'cookie' => '#vykrajovátko', 'topper' => '#dort', 'stamp' => '#razítko', 'logo' => '#logo',
        'text' => '#nápis', 'map' => '#mapa', 'default' => '#3dprinting',
    ],

    'mail' => [
        'subject' => 'El vídeo de su impresión :number está en YouTube',
        'title' => 'Su vídeo ya está publicado',
        'body' => 'El time-lapse de la impresión «:name» está publicado en nuestro canal. Gracias por dejarnos mostrarlo.',
        'watch' => 'Ver en YouTube',
        'share' => 'Si le gusta, compártalo: cada vez que lo comparte ayuda a una granja pequeña más que un anuncio.',
        'next' => 'La próxima pieza puede imprimirla aquí: :url',
    ],

    'weekly' => [
        'card' => 'Esta semana en la granja',
        'title' => 'Esta semana en la granja – :dates',
        'description' => ":cta\nLo que imprimió esta semana (:dates) nuestra granja de impresión 3D: :count impresiones en time-lapse. Cada pieza tiene un capítulo con un enlace a la herramienta en la que se creó.\n\nPublicamos los vídeos con el consentimiento de nuestros clientes.",
    ],
];
