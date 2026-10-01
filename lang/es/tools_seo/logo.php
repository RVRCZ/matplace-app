<?php

return [
    'title' => 'Logo para impresión 3D a partir de un SVG o una imagen',
    'description' => 'Suba un SVG o una imagen sencilla, o escriba un texto. Obtendrá un relieve sobre placa, una forma recortada o un logo de pie, y verá el precio al instante.',
    'h1' => 'Logo o texto como relieve, forma recortada o rótulo de pie',
    'intro' => [
        'La herramienta convierte un logo, una imagen o un texto en un modelo para impresión 3D. Suba un SVG con formas rellenas o una imagen sencilla y contrastada (PNG, JPG o WebP), o escriba hasta dos líneas de texto. Hay cuatro versiones: relieve sobre placa, relieve modelado según la imagen, forma recortada o logo de pie sobre una base. El motivo mide de 20 a 250 mm de ancho.',
        'La vista previa muestra exactamente la forma que se imprimirá, sus medidas exteriores y un precio orientativo. La herramienta avisa de las líneas de menos de 0,8 mm, que no se imprimirían bien, y de las partes que quedarían sueltas. Puede pedir el modelo impreso en nuestra granja de impresión o descargarlo gratis para su impresora. En el relieve sobre placa, el motivo puede imprimirse en un segundo color.',
    ],
    'steps' => [
        ['name' => 'Suba el diseño o texto', 'text' => 'Suba un SVG o una imagen, o escriba un texto en una o dos líneas de hasta 30 caracteres. El diseño subido tiene prioridad sobre el texto.'],
        ['name' => 'Elija la versión', 'text' => 'Elija el relieve sobre placa, el relieve modelado según la imagen, la forma recortada o el logo de pie. Para la placa elija también su forma: redondeada, rectangular o circular.'],
        ['name' => 'Ajuste el tamaño', 'text' => 'Indique el ancho del motivo y su grosor. El alto se calcula según las proporciones del diseño.'],
        ['name' => 'Revise vista previa y avisos', 'text' => 'Bajo la vista previa verá las medidas, un precio orientativo y los avisos sobre líneas finas o piezas sueltas. Según ellos, amplíe el motivo o cambie de versión.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Pida la impresión a nuestra granja de impresión y páguela con crédito prepagado. O descargue el modelo gratis como archivo STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Qué tipo de imagen admite la herramienta?', 'a' => 'Lo mejor es un SVG con formas rellenas de hasta 400 kB, o una imagen limpia con un motivo oscuro sobre fondo claro de hasta 5 MB. Los contornos sin relleno de un SVG se omiten. La imagen se traza en una cuadrícula de 360 celdas como máximo en su lado más largo, así que los detalles muy finos se pierden.'],
        ['q' => '¿Puedo subir una fotografía?', 'a' => 'Solo en la versión «Relieve modelado según la imagen», donde los tonos fijan la altura de la superficie: las zonas oscuras son las que más sobresalen. En las demás versiones la herramienta rechaza la fotografía y remite a la herramienta Relieve y litofanía.'],
        ['q' => '¿Por qué un logo recortado se separa en piezas?', 'a' => 'La forma recortada no tiene placa, así que nada une las letras sueltas ni las partes separadas del logo. La herramienta indica de cuántas piezas consta la forma. Si deben mantenerse unidas, elija el relieve sobre placa.'],
        ['q' => '¿Cómo funciona el logo de pie?', 'a' => 'El logo y la base se imprimen como dos piezas y el logo encaja en una ranura de la base. Para que resista, el logo de pie tiene un grosor mínimo de 2,4 mm. Las partes que no llegan a la base, como puntos y tildes, no se sostendrían, y la herramienta avisa de ellas.'],
        ['q' => '¿Se puede imprimir el logo en dos colores?', 'a' => 'En el relieve sobre placa, sí: en una impresión con nosotros usted elige un segundo color y la impresora lo cambia a la altura donde empieza el motivo. Los colores disponibles son los cargados en las impresoras en ese momento, por lo que no podemos garantizar un tono corporativo exacto.'],
        ['q' => '¿Cómo se paga y cómo se recibe la impresión?', 'a' => 'Se paga con crédito prepagado que usted recarga con tarjeta; los precios se muestran en coronas checas o en euros. La impresión se recoge en persona o la enviamos con Packeta a un punto de recogida o a domicilio en toda la UE.'],
    ],
    'examples' => [
        'Texto ATELIER en relieve sobre placa redondeada de 110 × 26 mm, para una puerta.',
        'Texto OPEN de pie en su base, 120 mm de ancho, 43 mm de alto.',
        'Letra M como forma recortada de 80 × 72 mm y 2 mm de grosor.',
    ],
];
