<?php

return [
    'title' => 'Cuadro de filamento: placas en capas o una sola impresión',
    'description' => 'Una imagen o foto como cuadro de pared en colores de filamento: placas en capas en un marco o una impresión con colores apilados. Lo imprimimos o lo descarga.',
    'h1' => 'Cuadro de filamento: una foto o un dibujo como capas de plástico',
    'intro' => [
        'La herramienta reduce una imagen o una foto a entre 1 y 8 colores de filamentos que tenemos de verdad en nuestra granja de impresión y construye con ellos un cuadro para la pared de 50 a 250 mm de ancho. Por defecto es un cuadro en capas: cada color es una placa propia de 1,5 a 3 mm, y las placas se apilan una tras otra como un recortable; la trasera lleva toda la silueta y la delantera solo su color. Entre las placas hay separadores de 2 a 5 mm que dan profundidad y sombra; para un cuadro fino, las placas van planas. Puede añadir un marco redondo o cuadrado con ranura para un clavo y, si quiere, sitio para una tira LED.',
        'La segunda forma es una sola impresión: una base y sobre ella los colores a 0,4 mm uno sobre otro. En cada capa de la impresión hay un solo filamento, así que cualquier impresora lo imprime cambiando el filamento en las alturas que indica el proyecto. En cada color ve la bobina que eligió la herramienta y puede cambiarla por otra de nuestro catálogo, reordenar los colores o fusionar dos. Junto a la vista previa está la guía: las placas en orden, de la trasera a la delantera, cada una dibujada.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen', 'text' => 'Suba una imagen, elija una de la biblioteca o reutilice una subida antes. El fondo se quita solo; para una foto que deba llenar toda la superficie, desactive la eliminación.'],
        ['name' => 'Elija cómo se fabrica', 'text' => 'Un cuadro de placas en capas con marco, o una impresión con los colores uno sobre otro. Ajuste el ancho, el grosor de las placas y la separación entre ellas, el marco y el sitio para la tira LED si lo quiere.'],
        ['name' => 'Afine los colores', 'text' => 'Elija cuántos colores y asigne a cada uno un filamento de nuestro catálogo. Los colores se pueden mover adelante o atrás, o fusionar dos en uno.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas y un precio orientativo; el precio exacto, un paso más adelante. Descargue cada placa como STL propio o todo el diseño como proyecto para su laminador, gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Cómo se monta el cuadro en capas?', 'a' => 'Imprima cada placa en su color y péguelas siguiendo la guía, de la trasera a la delantera. Los separadores forman parte de la placa y quedan bajo la placa de delante. Basta con cianoacrilato. El marco se imprime con el fondo abajo y las placas se pegan dentro.'],
        ['q' => '¿Y si solo tengo una impresora de un color?', 'a' => 'El cuadro en capas está hecho para ella: cada placa es una impresión de un solo color. La impresión con los colores en escalones también funciona; el proyecto incluye los cambios de filamento a las alturas correctas, la impresora se detiene y usted cambia el filamento.'],
        ['q' => '¿Qué imágenes salen mejor?', 'a' => 'Dibujos y logotipos con pocos colores planos. De una foto la herramienta toma los colores principales y el resultado es tipo póster; para un retrato es mejor una litofanía. Las líneas de menos de 0,8 mm se romperían como placa propia, así que la herramienta las pasa al color de detrás y lo avisa.'],
        ['q' => '¿Qué marco lleva y cómo se cuelga?', 'a' => 'Redondo o cuadrado, de 6 a 20 mm de ancho, con una ranura para un clavo en la parte trasera. Con «Sitio para una tira LED» las placas quedan 10 mm más lejos del fondo y abajo hay una ranura para el cable; la tira la compra usted.'],
        ['q' => '¿De qué material se imprime el cuadro?', 'a' => 'De PLA, que tiene la mayor variedad de colores y se imprime plano sin soportes. Los colores son bobinas que tenemos de verdad en stock, así que la impresión se parece a la vista previa.'],
        ['q' => '¿Cómo pago y cómo recibo la impresión?', 'a' => 'Paga con crédito prepagado que recarga con tarjeta; los precios se muestran en coronas o en euros. Enviamos la impresión por Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [
        'Un muñeco de nieve como cuadro en capas de cinco placas en un marco redondo de 180 mm, con separadores.',
        'Un árbol de Navidad de la biblioteca como cuatro placas sin marco, 150 mm; la placa trasera lleva toda la silueta.',
        'Un hombre de jengibre en una sola impresión de 120 × 140 mm: una base y tres colores a 0,4 mm uno sobre otro.',
    ],
];
