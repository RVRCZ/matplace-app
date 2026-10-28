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

    // el Short "¿Cuánto cuesta la impresión 3D?" (youtube:price-short, docs/AL.md §13.7)
    'price' => [
        'card' => '¿Cuánto cuesta la impresión 3D?',
        'things' => '{1} :n cosa con su precio|[2,*] :n cosas con sus precios',
        'pieces' => '{1} :n pieza|[2,*] :n piezas',
        'title' => '¿Cuánto cuesta la impresión 3D? :things',
        'ask' => '¿Qué valdría la pena imprimir para usted? Cuéntenoslo en un comentario.',
        'line' => ':name – :price – :url',
        'own_file' => 'Modelo propio del cliente',
        'description' => ":cta\n:pieces de nuestra granja de impresión 3D y lo que costaron. El precio siempre sale del modelo: su tamaño, el material y el tiempo de impresión. El suyo lo sabe en un minuto, sin registro.\n\nEn el vídeo:",
        'hashtags' => '#Shorts #impresion3d #granja3d #cuantocuesta #precio',
    ],

    'monthly' => [
        'card' => 'Cómo funciona nuestra granja de impresión 3D',
        'intro_chapter' => 'Introducción',
        'title' => 'Cómo funciona una granja de impresión 3D: del modelo al paquete (:month)',
        'ask' => '¿Qué mandaría imprimir? Cuéntenoslo en un comentario.',
        'steps' => [
            ['title' => '1. Suba un modelo o una foto', 'sub' => 'STL, 3MF, OBJ – o una foto para una figura y herramientas a medida'],
            ['title' => '2. El precio al momento', 'sub' => 'Laminamos el modelo y ve el precio en un minuto, sin cuenta'],
            ['title' => '3. Impresión en la granja', 'sub' => 'Impresoras Anycubic Kobra, PLA, PETG y más'],
            ['title' => '4. Foto de la pieza terminada', 'sub' => 'Cada impresión se fotografía en la caja de fotos'],
            ['title' => '5. Envío', 'sub' => 'Punto de recogida o recogida en persona'],
        ],
        'numbers' => [
            'recent' => '{1} Últimos 30 días: :n impresión|[2,*] Últimos 30 días: :n impresiones',
            'printed' => '{1} Impreso hasta hoy: :n pieza|[2,*] Impreso hasta hoy: :n piezas',
            'days' => '{0} Del pago a la entrega: el mismo día|{1} Del pago a la entrega: :d día|[2,*] Del pago a la entrega: :d días',
            'videos' => '{1} :n vídeo en nuestro canal|[2,*] :n vídeos en nuestro canal',
        ],
        'description' => ":cta\n¿Cómo funciona una granja de impresión 3D y cómo saber el precio de una impresión 3D? Este vídeo sigue un pedido de principio a fin (:month): la subida de un modelo o una foto, el laminado y el precio en un minuto, la impresión, la foto de la pieza terminada y el envío, con time-lapses de nuestras impresiones entre medias.\n\nNuestras cifras: :numbers\n\nCapítulos:",
        'hashtags' => '#impresion3d #granja3d #comofunciona #precioimpresion3d',
    ],
];
