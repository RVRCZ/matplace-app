<?php

return [
    'title' => 'Cortador de galletas con su propia forma',
    'description' => 'Escriba un nombre o un número, o suba una imagen de la forma. Obtendrá un cortador de galletas a medida con pestaña. Lo imprimimos o lo descarga gratis.',
    'h1' => 'Cortador de galletas a partir de un nombre, un número o un dibujo',
    'intro' => [
        'La herramienta crea un cortador de galletas con un texto o una imagen. Escriba un nombre, una letra o un número, o suba un dibujo; basta un contorno a lápiz. Obtendrá una pared fina que sigue el contorno, con pestaña para presionar. La forma mide de 30 a 150 mm de ancho y la pared de 10 a 30 mm de alto, con filo afilado o recto.',
        'Si la imagen subida tiene líneas dentro del contorno, como los nervios de una hoja o ojos y una sonrisa, la herramienta las convierte en un sello aparte que se presiona sobre la masa tras cortar. El contorno va en espejo: con la pestaña hacia arriba, corta en el sentido correcto. Puede pedir el cortador impreso en nuestra granja de impresión o descargar el modelo gratis para su impresora.',
    ],
    'steps' => [
        ['name' => 'Escriba texto o suba forma', 'text' => 'El texto admite dos líneas de 20 caracteres cada una, en una de treinta tipografías. En lugar de texto puede subir un SVG o una imagen de la forma.'],
        ['name' => 'Ajuste medidas y filo', 'text' => 'Indique el ancho de la forma, el alto y el grosor de la pared y el ancho de la pestaña. El filo afilado corta más limpio, el recto es más robusto.'],
        ['name' => 'Revise la vista previa', 'text' => 'La vista previa muestra el cortador girado tal como se usa y, a su lado, el sello si lo hay. El interior cerrado de una letra, como en la O, recibe su propia pared, unida al resto por barras planas.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Pida la impresión a nuestra granja de impresión y páguela con crédito prepagado. O descargue el modelo gratis como archivo STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿De qué imagen sale un cortador?', 'a' => 'De un dibujo oscuro y sencillo sobre fondo claro, o de un SVG con formas rellenas. Todo lo que el dibujo encierra cuenta como forma, y se usa la mayor zona continua. Las fotografías no sirven.'],
        ['q' => '¿Cuándo se obtiene también un sello?', 'a' => 'Solo con una imagen subida en PNG, JPG o WebP que tenga suficientes líneas dentro del contorno, y mientras la opción «Sello del dibujo interior» siga marcada. Con un texto o un SVG no hay sello.'],
        ['q' => '¿Con qué material se imprime el cortador y cómo se lava?', 'a' => 'Recomendamos el plástico corriente PLA o el PETG, más resistente. Lávelo a mano con agua tibia y nunca en el lavavajillas: el PLA empieza a ablandarse hacia los 55 °C.'],
        ['q' => '¿Es apta la pieza para el contacto con alimentos?', 'a' => 'El cortador está pensado para un contacto breve con masa cruda que después se hornea. No recomendamos piezas impresas sin forro para un contacto prolongado con alimentos.'],
        ['q' => '¿Qué nivel de detalle admite la forma?', 'a' => 'La herramienta elimina las partes de la forma más estrechas que el doble del grosor de la pared, porque la masa se quedaría atascada en ellas. Por eso conviene ampliar los textos pequeños o elegir un texto más corto.'],
        ['q' => '¿Cómo se paga y cómo se recibe la impresión?', 'a' => 'Se paga con crédito prepagado que usted recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a domicilio en toda la UE.'],
    ],
    'examples' => [
        'Cortador del nombre Ela, letra manuscrita, 82 × 54 mm con pestaña, alto 18 mm.',
        'Cortador del número 5 para galletas de cumpleaños, 72 × 93 mm, filo afilado.',
        'Cortador de las letras MAMA con serifa, 98 × 30 mm, filo recto más robusto.',
    ],
];
