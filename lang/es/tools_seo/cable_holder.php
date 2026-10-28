<?php

// La página para la búsqueda «organizador de cables para escritorio impresión 3d» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Sujetacables de escritorio a medida para imprimir en 3D',
    'heading' => 'Generador de sujetacables de escritorio para imprimir en 3D: a la medida de sus cables',
    'description' => 'Indique el diámetro del cable y el número de ranuras y obtenga un soporte en el que los cables encajan y no caen tras la mesa. Impresión o descarga.',
    'h1' => 'Cómo nace el soporte, qué tamaño tiene y cuánto cuesta',
    'intro' => [
        'Un sujetacables es un bloque pequeño con ranuras que se pega con cinta de doble cara o se atornilla al borde de la mesa. El cable encaja en la ranura por arriba y se queda en su sitio aunque lo desconecte del aparato; la rendija sobre la ranura es algo más estrecha que el cable, por eso no se sale, y el agujero es 0,6 mm mayor para que el cable deslice. Indique de 1 a 8 cables y un diámetro de 3 a 14 mm; todas las ranuras de un soporte tienen el mismo diámetro. El fondo se ajusta de 10 a 80 mm, también la pared entre ranuras y el redondeo de las esquinas; con «Con pestañas para tornillos» recibe dos pestañas con agujeros a los lados. Sirve para cargadores, cables de monitor o de auriculares.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): un soporte para cuatro cables de 6 mm, de 61 mm de largo y 45 mm de fondo, pesa 17,1 g, se imprime en 42 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, el pedido mínimo, sin envío (cálculo /c/mjyodedmxzye); un soporte para seis cables finos de 4 mm con pestañas para tornillos pesa 24,6 g y se imprime en 56 minutos (cálculo /c/kop5zm2dhbtw). Para cada mesa de la oficina indique la cantidad y el precio por pieza baja. El modelo se descarga gratis como STL o proyecto para su impresora; se imprime sin soportes. La cinta y los tornillos no forman parte de la impresión.',
    ],
    'steps' => [
        ['name' => 'Mida el diámetro del cable', 'text' => 'Mida el diámetro del cable, no del conector. Se puede indicar un diámetro de 3 a 14 mm en pasos de medio milímetro.'],
        ['name' => 'Elija el número de cables', 'text' => 'El soporte puede tener de 1 a 8 ranuras. Todas tienen el mismo diámetro; para cables de distinto grosor diseñe varios soportes.'],
        ['name' => 'Elija la fijación', 'text' => 'Sin pestañas, el soporte se pega con cinta de doble cara. Con la opción «Con pestañas para tornillos» recibe dos pestañas con agujeros a los lados.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Revise la vista previa con las medidas y pulse «Continuar» para el precio exacto. O descargue el modelo gratis para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Qué grosor pueden tener los cables?', 'a' => 'Cables de 3 a 14 mm de diámetro: desde un cable fino de auriculares hasta el cable de alimentación de un monitor. El agujero es 0,6 mm mayor que el cable para que deslice, y la rendija de arriba es más estrecha para que no se salga.'],
        ['q' => '¿Puede un soporte llevar cables de distinto grosor?', 'a' => 'No, todas las ranuras de un soporte tienen el mismo diámetro. Para cables de distinto grosor diseñe varios soportes; dos soportes juntos salen más baratos por pieza si los indica como varias unidades.'],
        ['q' => '¿Cómo se fija el soporte y aguanta?', 'a' => 'Con cinta de doble cara en el borde o en la cara inferior del tablero, o con dos tornillos si activa las pestañas. La cinta de espuma agarra en un tablero lacado o laminado; en madera sin lacar, mejor tornillos. Ni la cinta ni los tornillos forman parte de la impresión.'],
        ['q' => '¿Qué tamaño tendrá el soporte?', 'a' => 'La longitud depende del número y el diámetro de los cables y del grosor de las paredes; la vista previa muestra las medidas exteriores exactas. Un soporte para cuatro cables de 6 mm mide unos 61 mm de largo y 45 mm de fondo; uno para seis cables de 4 mm, unos 105 mm.'],
        ['q' => '¿Tiene que pasar el conector por la ranura?', 'a' => 'No. El cable encaja en la ranura por arriba, así que el conector no se pasa por ningún sitio; se saca igual de fácil.'],
        ['q' => '¿De qué material y cómo lo imprimo en casa?', 'a' => 'PLA para la oficina y la casa; PETG donde hace calor o los cables entran y salen a menudo y la rendija debe flexar. Se imprime con las ranuras hacia arriba, sin soportes, capas de 0,2 mm; el proyecto para el slicer está listo.'],
    ],
    'examples' => [
        'Soporte para cuatro cables de 6 mm de diámetro, 61 mm de largo, pegado con cinta: 17,1 g, 42 minutos, 99 CZK.',
        'Soporte para dos cables gruesos de 10 mm de diámetro, 45 mm de largo.',
        'Soporte para seis cables finos de 4 mm con pestañas para tornillos, 105 mm de largo: 24,6 g, 56 minutos.',
    ],
];
