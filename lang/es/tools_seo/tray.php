<?php

return [
    'title' => 'Bandejita con forma de imagen, a medida',
    'description' => 'Suba una imagen o elija una silueta y obtenga una bandejita con su forma para llaves y joyas. La imprimimos o descarga el modelo gratis.',
    'h1' => 'Una bandejita con la forma de su propia imagen',
    'intro' => [
        'La herramienta crea una bandejita cuya pared sigue el contorno de una imagen: una huella, un corazón, una hoja o cualquier cosa que suba en PNG, JPG, WebP o SVG o que elija en la biblioteca. En lugar de una imagen puede escribir un nombre. El ancho va de 50 a 200 mm y la altura de 8 a 40 mm; la pared mide 1,6 mm y el fondo 2 mm, y ambos se pueden cambiar.',
        'El dibujo del interior del contorno se graba en el fondo, de modo que la bandejita sigue siendo una impresión de un solo color para cualquier impresora y para nuestra granja. La imagen del fondo también puede omitirse o incrustarse en colores de filamento; un fondo en colores, eso sí, necesita una impresora que cambie de filamento sola y todavía no se puede pedir a nuestra granja.',
    ],
    'steps' => [
        ['name' => 'Elija una imagen o una silueta', 'text' => 'Suba su propia imagen, elija un motivo de la biblioteca o escriba un nombre. El fondo se elimina automáticamente y el contorno se suaviza.'],
        ['name' => 'Ajuste las medidas', 'text' => 'Cambie el ancho y la altura con los controles deslizantes o arrastrando las flechas en la vista previa. La separación entre la imagen y la pared decide cuánto espacio queda alrededor del dibujo.'],
        ['name' => 'Elija qué va en el fondo', 'text' => 'El dibujo grabado a dos capas de profundidad, el dibujo en colores o un fondo liso. Con una silueta de un solo color el fondo siempre es liso.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Junto a la vista previa aparecen las medidas y un precio orientativo; el precio exacto y el tiempo de impresión, un paso después. El modelo y el proyecto del laminador se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Qué imagen conviene para una bandejita?', 'a' => 'Una forma con un contorno claro y sin puntas finas: una huella de animal, un corazón, una hoja, una nube, una estrella. En las puntas estrechas no cabe nada y la pared es más frágil.'],
        ['q' => '¿Qué parte de la imagen se graba en el fondo?', 'a' => 'Todos los colores de la imagen menos el que ocupa la mayor superficie. Con una huella dentro de un círculo, el círculo queda como fondo y la huella se graba en él.'],
        ['q' => '¿De qué se imprime la bandejita?', 'a' => 'Recomendamos PLA, y PETG para el baño o una ventana al sol. Se imprime con el fondo hacia abajo y sin soportes.'],
        ['q' => '¿La imagen del fondo puede ir en color?', 'a' => 'Sí, con la opción «En colores». Los colores van incrustados en el fondo, en la misma capa que el plástico que los rodea, así que solo puede imprimirlo una impresora que cambie de filamento sola (AMS, MMU, ACE). El modelo se descarga color por color; una impresión así todavía no se puede pedir a nuestra granja.'],
        ['q' => '¿Qué tamaño elegir?', 'a' => 'Para anillos y pendientes bastan 80 mm; para llaves y monedas, de 100 a 120 mm. Una altura de 12 a 15 mm retiene las cosas pequeñas y no estorba al cogerlas.'],
        ['q' => '¿Cómo pago y cómo recibo la bandejita?', 'a' => 'Se paga con crédito prepago que se recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la pieza con Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [
        'Bandejita redonda de 100 mm con una huella grabada en el fondo, de 15 mm de alto.',
        'Bandejita con forma de corazón, de 110 mm de ancho y 20 mm de alto, con el fondo liso.',
        'Bandejita con forma de estrella de 120 mm con una cara incrustada en el fondo en colores.',
    ],
];
