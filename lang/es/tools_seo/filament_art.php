<?php

// La página para la búsqueda «cuadro de filamento generador gratis» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Cuadro de filamento: imagen o foto en capas de color, gratis',
    'heading' => 'Generador de cuadros de filamento: una imagen o una foto como capas de filamento de colores',
    'description' => 'Suba una imagen o foto y obténgala en 1 a 8 colores de filamento: placas en capas en un marco, o una impresión con colores apilados. Impresión o descarga.',
    'h1' => 'Dos maneras de fabricar el cuadro, y cuánto cuesta',
    'intro' => [
        'La herramienta reduce una imagen o una foto a 1 a 8 colores de filamentos que de verdad tenemos en la granja y construye con ellos un cuadro para la pared de 50 a 250 mm de ancho. Por defecto es un cuadro en capas: cada color es una placa propia de 1,5 a 3 mm de grosor y las placas se apilan una tras otra como un recortable; la trasera lleva toda la silueta, la delantera solo su color. Entre las placas hay postes separadores de 2 a 5 mm, que dan al cuadro profundidad y sombra; quien quiera un cuadro fino deja las placas planas. Se añade un marco redondo o cuadrado con ranura para un clavo y, si quiere, sitio para una tira LED. La segunda manera es una sola impresión: una base y encima los colores de 0,4 mm uno sobre otro, un filamento por capa, así que cualquier impresora la imprime cambiando el filamento a las alturas que indica el proyecto. El fondo de una foto se quita solo; para una foto que llene toda el área, lo desactiva. En cada color ve la bobina que eligió la herramienta y puede cambiarla por otra del catálogo, reordenar los colores o fusionar dos.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): el muñeco de nieve de la biblioteca como cuadro en capas de cuatro placas en un marco redondo de 120 mm pesa 105,6 g, se imprime en 2 h 46 min y cuesta 200 CZK (unos 8 EUR) con nosotros, sin envío (cálculo /c/tmwh0ok9ixpv); el color de cada placa y del marco se elige al encargar, en un solo pedido. El hombre de jengibre como una sola impresión de 120 mm de ancho, base y tres colores apilados, pesa 15,9 g, se imprime en 26 minutos y cuesta 99 CZK (cálculo /c/etcdmot38mpy). Junto a la vista previa está la guía: las placas en orden de atrás hacia delante, cada una dibujada. Descargue cada placa como STL propio, o todo el diseño como proyecto para su slicer, gratis y sin registro.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen', 'text' => 'Suba una imagen, elija una de la biblioteca o reutilice una subida antes. El fondo se quita solo; para una foto que deba llenar toda el área, desactive la eliminación.'],
        ['name' => 'Elija cómo fabricarlo', 'text' => 'Un cuadro en capas de placas con marco, o una sola impresión con los colores uno sobre otro. Ajuste el ancho, el grosor de las placas y la separación entre ellas, el marco y el sitio para una tira LED si lo quiere.'],
        ['name' => 'Afine los colores', 'text' => 'Elija cuántos colores y asigne a cada uno un filamento de nuestro catálogo. Los colores se pueden mover adelante o atrás, o fusionar dos en uno; para una foto cambie la lectura a «pintura».'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve el tamaño; el cálculo tras «Continuar» muestra el precio exacto. Descargue cada placa como STL propio, o todo el diseño como proyecto para su slicer, gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Cómo se monta un cuadro en capas?', 'a' => 'Imprima cada placa en su color y péguelas según la guía, de atrás hacia delante. Los postes separadores forman parte de la placa y encajan bajo la placa que va delante. Basta con cianoacrilato. El marco se imprime con el fondo abajo y las placas se pegan dentro.'],
        ['q' => '¿Y si solo tengo una impresora de un color?', 'a' => 'El cuadro en capas está hecho para ella: cada placa es una impresión de un solo color. La impresión única con los colores en escalones también funciona; el proyecto lleva los cambios de filamento a las alturas correctas, la impresora se detiene y usted cambia el filamento. Una impresora con AMS, MMU o ACE cambia las bobinas sola.'],
        ['q' => '¿Qué imágenes salen mejor?', 'a' => 'Dibujos y logotipos con pocos colores planos. De una foto, la herramienta en modo «pintura» toma los colores principales y simplifica las áreas a milímetros, así que el resultado es de cartel; para un retrato en escala de grises es mejor una litofanía. Las líneas más finas de 0,8 mm se romperían como placa propia, por eso la herramienta las asigna al color de detrás y lo avisa.'],
        ['q' => '¿Qué marco, y cómo se cuelga?', 'a' => 'Redondo o cuadrado, de 6 a 20 mm de ancho, con ranura para un clavo en la parte trasera. Con «Sitio para tira LED» las placas quedan 10 mm más lejos del fondo y abajo hay una ranura para el cable; la tira la compra usted.'],
        ['q' => '¿En qué se diferencia de HueForge y de una litofanía?', 'a' => 'HueForge mezcla tonos mediante la translucidez de capas finas y necesita cambios de filamento ajustados con precisión en el slicer; nuestra impresión única apila colores sólidos, así que se ve igual en cualquier impresora y el resultado se ve de antemano. Una litofanía tiene un solo color y la imagen nace del grosor a contraluz; el cuadro de filamento es de colores sin ninguna luz.'],
        ['q' => '¿De qué se imprime el cuadro y en qué colores?', 'a' => 'De PLA, que tiene la mayor variedad de colores y se imprime plano sin soportes. Los colores son bobinas que de verdad tenemos en stock, así que la impresión se parece a la vista previa. En la impresión con nosotros elige el color de cada placa y del marco al encargar, en un solo pedido.'],
    ],
    'examples' => [
        'Un muñeco de nieve como cuadro en capas de cuatro placas en un marco redondo de 120 mm: 105,6 g, 2 h 46 min, 200 CZK.',
        'Un cuadro menor de 100 mm con tres placas sin marco, plano: 25 g, 46 minutos, 99 CZK, el pedido mínimo.',
        'Un hombre de jengibre como una sola impresión de 120 mm: base y tres colores de 0,4 mm uno sobre otro, 15,9 g, 26 minutos, 99 CZK.',
    ],
];
