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
        'music' => 'Música: :track (YouTube Audio Library)',
        'card' => ':material · :time de impresión',
        'card_time' => ':time de impresión',
        'tail' => ':time de impresión en :seconds s',
        'tail_plain' => ':time de impresión 3D',
        'title' => 'Time-lapse de impresión 3D – :material :color',
        'description' => "Time-lapse de una impresión de nuestra granja de impresión 3D.\n\nMaterial: :material :color\nImpresora: :printer\nTiempo de impresión: :time\n\n¿Quiere imprimir algo también? Suba un modelo, haga una foto o descríbalo con palabras y verá el precio al instante: :url\n\nPublicado con el consentimiento del cliente.",
    ],

    'weekly' => [
        'card' => 'Esta semana en la granja',
        'title' => 'Esta semana en la granja – :dates',
        'description' => "Lo que imprimió esta semana (:dates) nuestra granja de impresión 3D: :count impresiones en time-lapse. Cada pieza tiene un capítulo con un enlace a la herramienta en la que se creó.\n\n¿Quieres imprimir algo tú también? Sube un modelo, fotografía un objeto o descríbelo con palabras y verás el precio al instante: :url\n\nPublicamos los vídeos con el consentimiento de nuestros clientes.",
    ],
];
