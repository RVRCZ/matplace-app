<?php

// La página para la búsqueda «organizador de cajón a medida impresión 3d» (docs/AP.md §1, §5). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Organizador de cajón a medida para imprimir en 3D, gratis',
    'heading' => 'Organizador de cajón hecho a la medida de su cajón',
    'description' => 'Indique las medidas interiores del cajón y los compartimentos y vea la vista 3D al instante. Descargue el modelo gratis como STL, o lo imprimimos.',
    'h1' => 'Qué hace el organizador, cómo diseñarlo a medida y cuánto cuesta imprimirlo',
    'intro' => [
        'La herramienta crea un organizador con compartimentos según las medidas que indique en milímetros: ancho y fondo de 30 a 400 mm, alto de 10 a 150 mm, hasta 8 filas y 8 columnas, es decir, 64 compartimentos, cada uno de al menos 8 mm por dentro. Todos los compartimentos son iguales y el organizador es una sola pieza sin tapa que se imprime sin soportes. La pared por defecto mide 1,6 mm y el fondo 1,2 mm, ambos ajustables de 0,8 a 4 mm, y las esquinas se pueden redondear. Puede empezar por el ajuste «Cajón», «Material de oficina» o «Piezas pequeñas». La vista previa se redibuja tras cada cambio y muestra las medidas exteriores y el tamaño de un compartimento, así ve si caben cubiertos, pilas o tornillos. Sirve para un cajón de cocina, de taller o de baño, y para el escritorio.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): un organizador de 200 × 120 × 40 mm con 3 × 2 compartimentos pesa 115 g, se imprime en 2 h 27 min y cuesta 250 CZK (unos 10 EUR) con nosotros (cálculo /c/l1gr0izblzwr); uno pequeño de 100 × 80 × 30 mm con 2 × 2 compartimentos pesa 41 g, se imprime en 57 minutos y cuesta 110 CZK, todo sin envío. Un organizador de 300 × 400 × 50 mm lo marca la calculadora como mayor que una cama corriente: divida ese cajón en dos organizadores juntos, o use las bandejas modulares. Si tiene impresora, descargue el modelo gratis como proyecto para su máquina (hay unas 270 impresoras para elegir) o como STL; la impresión con nosotros se envía con Packeta.',
    ],
    'steps' => [
        ['name' => 'Mida el cajón', 'text' => 'Mida el ancho, el fondo y el alto interiores del cajón en su punto más estrecho. Deje 1 a 2 mm de holgura por lado; los cajones rara vez son exactamente rectangulares.'],
        ['name' => 'Indique las medidas y los compartimentos', 'text' => 'Rellene ancho, fondo, alto, filas y columnas. Puede empezar por el ajuste «Cajón», «Material de oficina» o «Piezas pequeñas».'],
        ['name' => 'Revise la vista previa', 'text' => 'La vista giratoria muestra la forma, las medidas exteriores y el tamaño de un compartimento. Se redibuja con cada cambio.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Elija material, color y número de unidades y continúe al precio exacto. O elija «Tengo impresora, descargar el modelo».'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño de organizador puedo diseñar?', 'a' => 'Ancho y fondo de 30 a 400 mm, alto de 10 a 150 mm. Un organizador de más de 250 mm puede no caber en todas las impresoras; la calculadora lo dice enseguida y entonces divide el cajón en dos organizadores juntos.'],
        ['q' => '¿Cuántos compartimentos puede tener y pueden ser de distinto tamaño?', 'a' => 'Hasta 8 filas y 8 columnas, es decir, 64 compartimentos, cada uno de al menos 8 mm por dentro. La cuadrícula es regular; para compartimentos de distinto tamaño use el organizador modular de bandejas, donde compone usted la distribución.'],
        ['q' => '¿Qué grosor tienen las paredes y el fondo?', 'a' => 'La pared por defecto mide 1,6 mm y el fondo 1,2 mm, suficiente para cubiertos y material de oficina. En «Grosor de paredes y más» cambia ambos de 0,8 a 4 mm; para tornillos y cosas pesadas elija 2 mm o más.'],
        ['q' => '¿Puedo añadir una tapa o separadores de distinta altura?', 'a' => 'En esta herramienta no: el organizador es abierto y todas las paredes tienen la misma altura. La herramienta Caja a medida hace una caja con tapa; el Organizador modular, bandejas de distintas alturas.'],
        ['q' => '¿Cómo imprimo el organizador en casa?', 'a' => 'En plano, con el fondo abajo, sin soportes; el proyecto descargado lleva los ajustes del cálculo (capas de 0,2 mm, relleno del 15 %). Para la cocina o el baño elija PETG, que soporta mejor el calor y la humedad; si no, basta el PLA.'],
        ['q' => '¿Tengo que encargarles la impresión?', 'a' => 'No. Descargue el modelo gratis como proyecto listo para su impresora, con unas 270 impresoras para elegir, o como archivo STL. La impresión con nosotros es para quien no tiene impresora o necesita una pieza grande.'],
    ],
    'examples' => [
        'Organizador de cajón de 300 × 200 × 45 mm con ocho compartimentos.',
        'Organizador de escritorio de 200 × 100 × 60 mm, tres compartimentos para material de escritura.',
        'Organizador bajo de 160 × 120 × 25 mm con veinte compartimentos para piezas pequeñas.',
    ],
];
