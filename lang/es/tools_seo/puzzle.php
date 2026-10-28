<?php

// La página para la búsqueda «puzzle impreso en 3d generador» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Puzzle para imprimir en 3D de un modelo o una foto: gratis',
    'heading' => 'Generador de puzzles impresos en 3D: un rompecabezas de su modelo o su foto, gratis',
    'description' => 'Un modelo plano, un relieve de una foto, un logotipo o una litofanía cortado en un puzzle de 2 × 2 a 8 × 8 piezas con pestañas o pasadores. Gratis.',
    'h1' => 'Cómo nace el puzzle, qué tamaño de pieza elegir y cuánto cuesta',
    'intro' => [
        'La herramienta corta un modelo plano en un puzzle: una cuadrícula de 2 × 2 a 8 × 8 piezas con pestañas clásicas, cabeza y cuello, que encajan con 0,2 mm de holgura. Sirve un relieve de una foto, un logotipo, una litofanía o un posavasos de nuestras herramientas, o cualquier cosa plana de hasta 25 mm; un modelo más alto recibe en su lugar pasadores ocultos de Ø 3 mm en los lados, porque una pestaña no entraría en plástico grueso. Las pestañas y su dirección las reparte la herramienta; las piezas del borde siguen el contorno del modelo. En la cara inferior de cada pieza graba su número, así que el puzzle se monta incluso sin la imagen, y opcionalmente añade una bandeja con borde de 6 mm en la que encaja el puzzle montado. Un modelo que no está cerrado se cierra primero; una pieza que se desharía por un agujero se queda con su trozo mayor, y la herramienta lo avisa.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): el posavasos redondo de 100 mm con el muñeco de nieve de la herramienta Posavasos cortado en 3 × 4 piezas con pestañas y números por debajo pesa 22,6 g, se imprime en 1 h 3 min y cuesta 99 CZK (unos 4 EUR) con nosotros, sin envío (cálculo /c/wnr4wkkungpz); las piezas se imprimen juntas, una junto a otra, y cada pieza puede tener su color al encargar. El puzzle se abre como un archivo nuevo: lo manda imprimir con nosotros, o descarga las piezas una a una o como proyecto para su slicer, gratis y sin registro. La herramienta en sí es gratuita.',
    ],
    'steps' => [
        ['name' => 'Suba un modelo plano', 'text' => 'Arrastre el modelo al campo o haga clic y elija un archivo: STL, 3MF, OBJ o STEP, mejor un relieve, un logotipo, una litofanía o un posavasos de hasta 25 mm de alto. Un modelo de nuestras herramientas llega aquí directo desde su cálculo.'],
        ['name' => 'Elija la cuadrícula y la unión', 'text' => 'Filas y columnas, pestañas de puzzle (tamaño de la cabeza y holgura) o pasadores ocultos, números por debajo y bandeja.'],
        ['name' => 'Haga el puzzle', 'text' => 'Haga clic en «Hacer el puzzle». Tarda unos segundos; ve las piezas en la vista previa, cada una en su color, y la herramienta dice qué tamaño tienen.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Descargue las piezas una a una o como proyecto para su slicer, o mándelas imprimir con nosotros; la bandeja y los pasadores son piezas aparte.'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño deben tener las piezas?', 'a' => 'Al menos 12 mm, mejor de 25 a 40 mm: pestañas de ese tamaño se imprimen con fiabilidad y las piezas se manejan bien. Un posavasos de 100 mm da unos 33 × 25 mm con 3 × 4 piezas; un relieve de 200 mm admite 6 × 6. Las piezas más pequeñas se rechazan.'],
        ['q' => '¿Aguantarán las pestañas o se soltarán las piezas?', 'a' => 'Las pestañas tienen 0,2 mm de holgura (se puede ajustar de 0,1 a 0,4), lo que en una impresora corriente con boquilla de 0,4 mm da una unión firme pero desmontable. Demasiado apretadas: más holgura; flojas: menos. La cabeza de la pestaña puede ser del 25 al 45 % de la pieza.'],
        ['q' => '¿Cuándo elegir pasadores ocultos?', 'a' => 'Cuando la cara superior debe quedar sin pestañas, como en una litofanía o un logotipo, o cuando el modelo mide más de 25 mm de alto. Los pasadores de Ø 3 mm van en el archivo como pieza aparte; las piezas se montan sobre ellos y se pegan.'],
        ['q' => '¿Puedo hacer un puzzle de una foto?', 'a' => 'Sí, con dos herramientas: primero convierta la foto en un relieve o una litofanía en la herramienta Relieve y litofanía, o en un posavasos de colores, y mande el modelo terminado aquí. Un puzzle de litofanía se monta a contraluz; un puzzle de posavasos tiene colores.'],
        ['q' => '¿Para qué sirve la bandeja?', 'a' => 'Una bandeja con borde de 6 mm y fondo de 1,5 mm según el contorno del modelo; el puzzle montado encaja en ella con holgura y se puede exponer o colgar. Se imprime como pieza aparte.'],
        ['q' => '¿Cómo imprimo el puzzle en casa?', 'a' => 'Las piezas planas, con la cara hacia arriba, sin soportes, capas de 0,2 mm, PLA; el proyecto para el slicer las tiene colocadas una junto a otra. En un posavasos de colores se conservan los cambios de filamento a las alturas de los colores. La bandeja se imprime con el fondo abajo.'],
    ],
    'examples' => [],
];
