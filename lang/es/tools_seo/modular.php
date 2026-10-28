<?php

// La página para la búsqueda «organizador modular de cajón generador» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Organizador modular de cajón a medida: bandejas gratis',
    'heading' => 'Generador de organizador modular de cajón: bandejas sueltas a medida',
    'description' => 'Indique el interior del cajón, divídalo en cuadrícula y coloque bandejas de varios tamaños. Cada una se imprime aparte y se saca. Impresión o descarga.',
    'h1' => 'Cómo diseñar el juego de bandejas, cuánto cuesta y cómo se imprime',
    'intro' => [
        'Un organizador modular es un juego de bandejas separadas que llenan un cajón con exactitud. Indique el ancho y el fondo interiores del cajón, de 60 a 600 mm, y la altura de las bandejas, de 15 a 120 mm, divida el área en una cuadrícula de hasta 12 × 12 celdas y coloque en ellas hasta 24 bandejas de distintos tamaños; una bandeja puede ocupar varias celdas. Cada bandeja se imprime por separado, así que la saca, la recoloca o la lava cuando quiera, y cada una puede tener otro color. Entre las bandejas hay un juego de 0,6 mm y las esquinas van redondeadas. Cuando las bandejas no van en un cajón sino en una mesa o una estantería, añade una bandeja exterior que las mantiene juntas. A diferencia de un organizador de una pieza, el juego sirve para un cajón grande: las bandejas caben en cualquier impresora.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): cuatro bandejas de tres tamaños para un cajón de 300 × 150 mm, de 40 mm de alto, pesan juntas 188 g, se imprimen en 3 h 45 min y cuestan 310 CZK (unos 12 EUR) con nosotros, sin envío (cálculo /c/fsgkqtoncl9f); el color de cada bandeja se elige al encargar, en un solo pedido. La herramienta muestra la distribución en la vista previa y debajo la lista «Qué se imprime» con las medidas de cada bandeja; el cálculo del paso siguiente da el precio exacto de todo el juego. Si tiene impresora, descarga gratis cada bandeja como STL o todo el juego como proyecto para su impresora.',
    ],
    'steps' => [
        ['name' => 'Mida el interior del cajón', 'text' => 'Indique el ancho y el fondo interiores del cajón y la altura de las bandejas. Reste 1 o 2 mm a las medidas tomadas, las paredes de un cajón rara vez son del todo rectas.'],
        ['name' => 'Divídalo en una cuadrícula', 'text' => 'Elija el número de columnas y filas. La herramienta muestra cuántos milímetros mide una celda; debe medir al menos 15 mm.'],
        ['name' => 'Coloque las bandejas', 'text' => 'Toque una celda vacía y después la esquina opuesta de la bandeja. Toque una bandeja terminada para seleccionarla y cambiarle el color o quitarla.'],
        ['name' => 'Revise la lista', 'text' => 'Bajo la vista previa está la lista «Qué se imprime» con las medidas de cada bandeja. El precio exacto de todo el juego está un paso más allá, en el cálculo.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Continúe al precio exacto y encargue el juego con nosotros. O descargue las piezas sueltas o el proyecto bajo «Tengo impresora, descargar el modelo».'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño de cajón admite la herramienta?', 'a' => 'El interior del cajón puede medir de 60 a 600 mm tanto de ancho como de fondo, y las bandejas pueden tener de 15 a 120 mm de alto. Cada bandeja se imprime aparte, así que incluso un cajón grande se compone de piezas pequeñas que caben en una impresora corriente.'],
        ['q' => '¿Cuántas bandejas puede tener un juego?', 'a' => 'Como máximo 24 bandejas por juego. La cuadrícula puede tener hasta 12 columnas y 12 filas, y una bandeja puede ocupar varias celdas; una celda debe medir al menos 15 mm.'],
        ['q' => '¿Para qué sirve la bandeja exterior?', 'a' => 'La bandeja mantiene juntas las bandejas cuando están sobre una mesa o una estantería y no en un cajón. Una bandeja grande puede no caber en la impresora, el cálculo lo avisa; las bandejas funcionan sin ella.'],
        ['q' => '¿Saldrán las bandejas del cajón con facilidad?', 'a' => 'Sí. Entre las bandejas hay un juego de 0,6 mm por defecto y sus esquinas van redondeadas. El juego, el grosor de las paredes y del fondo se cambian bajo «Grosor de paredes y más».'],
        ['q' => '¿Puede tener cada bandeja un color distinto?', 'a' => 'Sí. Elige un color para cada bandeja en el diseño y, en la impresión con nosotros, el color de cada bandeja entre las bobinas cargadas en nuestras impresoras, en un solo pedido. En casa imprime cada bandeja aparte con su bobina.'],
        ['q' => '¿Cuándo elegir el organizador de una pieza?', 'a' => 'Cuando el cajón mide hasta 250 mm, de modo que un organizador de una pieza cabe en una cama corriente, y quiere los compartimentos fijos en su sitio, elija la herramienta Organizador de cajón: es una sola pieza sin holguras. El juego de bandejas va bien en un cajón mayor y donde el contenido cambia: recoloca las bandejas o añade una.'],
    ],
    'examples' => [
        'Cuatro bandejas de tres tamaños para un cajón de 300 × 150 mm, 40 mm de alto: 188 g, 3 h 45 min, 310 CZK.',
        'Cuatro bandejas cuadradas iguales para un cajón de 160 × 160 mm, 30 mm de alto.',
        'Cuatro bandejas en una bandeja de mesa, interior de 240 × 120 mm, 45 mm de alto.',
    ],
];
