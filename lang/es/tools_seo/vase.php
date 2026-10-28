<?php

// La página para la búsqueda «jarrón impresión 3d a medida» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Jarrón para imprimir en 3D a medida: espiral, acanalado',
    'heading' => 'Jarrón para imprimir en 3D a su medida',
    'description' => 'Elija alto, diámetros, forma y superficie: lisa, acanalada o en espiral; la funda de maceta lleva drenaje y plato. Modelo gratis, o lo imprimimos.',
    'h1' => 'Qué formas admite el jarrón, cuánto cuesta imprimirlo y si retiene el agua',
    'intro' => [
        'La herramienta crea un jarrón o una funda de maceta a partir del alto y los diámetros que indique: alto de 40 a 300 mm, diámetros superior e inferior de 30 a 250 mm. Elige una de cuatro formas (cuello estrecho, abombado, cono recto o tulipán) y una superficie lisa, acanalada o en espiral; en la acanalada y la espiral ajusta el número de nervios, la profundidad de los surcos y la torsión, y elige ondas agudas o redondas. En la forma de cuello estrecho mueve el estrechamiento arriba o abajo y puede tener hasta tres. La funda de maceta puede llevar orificios de drenaje en el fondo y un plato aparte. La pared y el fondo tienen 1,6 mm por defecto (pared de 0,8 a 4 mm, fondo de 0,8 a 5 mm). La vista previa muestra las medidas exteriores reales, porque las formas abombadas y los nervios son más anchos que los diámetros indicados.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): un jarrón liso de cuello estrecho de 160 mm de alto pesa 76 g, se imprime en 1 h 57 min y cuesta 190 CZK (unos 8 EUR) con nosotros; un jarrón en espiral de 180 mm con 18 nervios pesa 80 g, se imprime en 5 h 7 min y cuesta 280 CZK (unos 11 EUR); una funda de maceta acanalada de 120 mm con drenaje pesa 99 g, se imprime en 3 h 45 min y cuesta 260 CZK sin el plato, todo sin envío. La funda con su plato uno junto a otro mide 287 mm, así que el cálculo la imprime en dos camas o la reduce usted. Un jarrón impreso no tiene por qué ser estanco; para flores cortadas ponga un vaso dentro. El modelo se descarga gratis como STL o proyecto para su impresora.',
    ],
    'steps' => [
        ['name' => 'Elija el uso y la forma', 'text' => 'Elija jarrón o funda de maceta y una de cuatro formas. Puede empezar por el ajuste «Espiral», «Acanalado», «Liso» o «Maceta».'],
        ['name' => 'Indique las medidas', 'text' => 'Ajuste el alto, el diámetro superior y el inferior. En la funda, mida la maceta que va a meter y añada holgura.'],
        ['name' => 'Afine la superficie', 'text' => 'En la superficie acanalada o en espiral ajuste el número de nervios, la profundidad de los surcos y la torsión. La herramienta avisa si la pared entre surcos saldría demasiado fina.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Revise la vista previa y pase al precio exacto con «Continuar». O descargue gratis el modelo para su propia impresora.'],
    ],
    'faq' => [
        ['q' => '¿Retiene el agua un jarrón impreso?', 'a' => 'No necesariamente. Entre las capas pueden quedar fugas microscópicas. Para flores cortadas ponga dentro un vaso o un tubo de ensayo, o selle el interior con barniz; para flores secas y como funda de maceta no hace falta.'],
        ['q' => '¿Qué tamaño de jarrón puedo diseñar?', 'a' => 'El alto de 40 a 300 mm, los diámetros superior e inferior de 30 a 250 mm. Un jarrón alto o ancho no cabe en todas las impresoras; la cama de nuestra granja mide 250 × 250 mm y el cálculo lo avisa.'],
        ['q' => '¿Por qué un jarrón en espiral cuesta más que uno liso?', 'a' => 'Los nervios y la torsión añaden superficie y la impresora sigue un camino sinuoso: el jarrón en espiral de 180 mm se imprimió en 5 h 7 min; el liso de 160 mm, en 1 h 57 min con un peso parecido. El precio sale de gramos y minutos, de ahí los 90 CZK de diferencia.'],
        ['q' => '¿Para qué sirven los orificios de drenaje y el plato?', 'a' => 'En la funda de maceta, el agua sobrante sale por los orificios del fondo y el plato la recoge. El plato es una pieza aparte y la herramienta calcula su diámetro según el fondo.'],
        ['q' => '¿Por qué el jarrón es más ancho que los diámetros indicados?', 'a' => 'Los diámetros valen para el borde superior y el fondo. La forma de cuello estrecho y la abombada se ensanchan entre ambos y los nervios añaden más milímetros. La vista previa muestra las medidas exteriores reales.'],
        ['q' => '¿Qué grosor tiene la pared y se puede imprimir en modo jarrón?', 'a' => 'La pared y el fondo tienen 1,6 mm por defecto; la pared va de 0,8 a 4 mm y el fondo de 0,8 a 5 mm. El STL descargado es un cuerpo macizo, así que en su laminador también puede imprimirlo en modo jarrón con una sola pared; nosotros imprimimos la pared según el diseño, con relleno, que retiene mejor el agua.'],
    ],
    'examples' => [
        'Jarrón en espiral de cuello estrecho, 180 mm de alto, diámetro superior de 62 mm, 18 nervios: 80 g, 5 h 7 min, 280 CZK.',
        'Jarrón liso de cuello estrecho, 160 mm de alto, diámetro superior de 70 mm: 76 g, 1 h 57 min, 190 CZK.',
        'Funda de maceta acanalada con plato, 120 mm de alto, diámetro superior de 130 mm: 99 g sin el plato, 3 h 45 min, 260 CZK.',
        'Jarrón con dos estrechamientos y ondas redondas, 200 mm de alto, diámetro superior de 70 mm.',
    ],
];
