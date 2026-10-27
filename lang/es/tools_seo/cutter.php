<?php

// La página para la búsqueda «cortador de galletas a medida» (docs/AP.md §6). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Cortador de galletas a medida con su nombre o dibujo, gratis',
    'heading' => 'Cortador de galletas a medida con un nombre o un dibujo',
    'description' => 'Escriba un nombre o un número, o suba el dibujo de una forma, y obtenga un cortador con reborde. Lo imprimimos, o descarga el modelo gratis.',
    'h1' => 'Cómo nace el cortador, de qué forma, de qué se imprime y cuánto cuesta',
    'intro' => [
        'La herramienta crea un cortador de galletas a partir de un texto o una imagen. Escribe un nombre, una letra o un número en dos líneas de 20 caracteres en una de treinta tipografías, o sube el dibujo de una forma; basta incluso un contorno hecho a lápiz y fotografiado. Nace una pared fina que sigue el contorno, con un reborde para presionar: forma de 30 a 150 mm de ancho, pared de 10 a 30 mm de alto y de 0,8 a 1,6 mm de grosor, reborde de 3 a 10 mm, filo afilado o recto. Si la imagen subida tiene líneas dentro del contorno, por ejemplo las nervaduras de una hoja o unos ojos y una sonrisa, se convierten en un sello aparte que se presiona en la masa después de cortar. El contorno está reflejado, así que, girado con el reborde hacia arriba, corta la forma en el sentido correcto; el interior cerrado de una letra, por ejemplo la O, recibe su propia pared unida por puentes planos.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): un cortador del nombre Ela en tipografía manuscrita, de 82 × 54 mm con el reborde y 18 mm de alto, pesa 13 g, se imprime en 31 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo; un cortador del número 5 para galletas de cumpleaños, de 72 × 93 mm, pesa 12 g, se imprime en 25 minutos y cuesta también 99 CZK. Se imprime con el reborde abajo, sin soportes, en PLA o en PETG, más resistente; se lava a mano con agua tibia, no en el lavavajillas. El modelo se descarga gratis como STL o proyecto para su impresora, o se encarga impreso con nosotros.',
    ],
    'steps' => [
        ['name' => 'Escriba un texto o suba una forma', 'text' => 'El texto puede tener dos líneas de 20 caracteres, en una de treinta tipografías. En lugar de texto puede subir un SVG o la imagen de una forma.'],
        ['name' => 'Ajuste las medidas y el filo', 'text' => 'Indique el ancho de la forma, el alto y el grosor de la pared y el ancho del reborde. El filo afilado corta mejor; el recto es más resistente.'],
        ['name' => 'Revise la vista previa', 'text' => 'La vista previa muestra el cortador girado tal como se usa y, a su lado, el sello si se ha creado. El interior cerrado de una letra, por ejemplo la O, recibe su propia pared unida al resto por puentes planos.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto y el tiempo de impresión los muestra el cálculo tras «Continuar». O descargue gratis el modelo como STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿De qué imagen sale el cortador?', 'a' => 'De un dibujo sencillo oscuro sobre fondo claro, o de un SVG con formas rellenas. Cuenta como forma todo lo que el dibujo encierra y se usa la mayor área continua. Una fotografía no sirve; repase el dibujo de un niño con rotulador y fotografíelo desde arriba.'],
        ['q' => '¿Cuándo se crea también un sello?', 'a' => 'Solo con una imagen subida en PNG, JPG o WebP que tenga bastantes líneas dentro del contorno, y si deja marcada la opción «Sello del dibujo interior». Con texto y con SVG no se crea sello.'],
        ['q' => '¿Qué tamaño de cortador elegir?', 'a' => 'Para galletas normales, de 50 a 70 mm; para una galleta grande o un nombre, de 100 a 150 mm; como mínimo 30 mm. Una pared de 18 mm de alto basta para masa quebrada y de jengibre; para una masa gruesa elija 25 mm.'],
        ['q' => '¿De qué se imprime el cortador y cómo se lava?', 'a' => 'Recomendamos PLA corriente o PETG, más resistente. Lávelo a mano con agua tibia, nunca en el lavavajillas: el PLA se ablanda hacia los 55 °C.'],
        ['q' => '¿Es apta la impresión para el contacto con alimentos?', 'a' => 'El cortador está pensado para un contacto breve con masa cruda que luego se hornea. Para un contacto prolongado con alimentos no recomendamos piezas impresas sin revestimiento.'],
        ['q' => '¿Cómo de fina puede ser la forma?', 'a' => 'Los salientes más estrechos que el doble del grosor de la pared se omiten de la forma, porque la masa se quedaría atascada. Agrande el texto pequeño, o elija una palabra más corta y una tipografía más gruesa.'],
    ],
    'examples' => [
        'Cortador del nombre Ela en tipografía manuscrita, 82 × 54 mm con el reborde, 18 mm de alto: 13 g, 31 minutos, 99 CZK.',
        'Cortador del número 5 para galletas de cumpleaños, 72 × 93 mm, filo afilado: 12 g, 25 minutos, 99 CZK.',
        'Cortador de las letras MAMA en tipografía con serifa, 98 × 30 mm, filo recto más resistente.',
    ],
];
