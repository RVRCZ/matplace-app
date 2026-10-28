<?php

// La página para la búsqueda «pendientes impresos en 3d generador» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Pendientes impresos en 3D con su imagen: un par, gratis',
    'heading' => 'Generador de pendientes impresos en 3D: un par con su propia imagen o texto',
    'description' => 'Una imagen, un motivo o un texto corto se convierte en un par de pendientes, iguales o en espejo, en colores de filamento. Impresión o descarga gratis.',
    'h1' => 'Cómo nace el par, cuánto pesa y cuánto cuesta',
    'intro' => [
        'La herramienta crea un par de pendientes a partir de una imagen. Los dos son iguales, o el segundo va en espejo para que el motivo mire hacia el mismo lado en ambas orejas. Suba un PNG, JPG, WebP o SVG, elija un motivo de la biblioteca o escriba un texto corto de hasta 12 caracteres por línea. Un pendiente mide de 10 a 60 mm de ancho y de 1,6 a 3 mm de grosor; sigue el contorno de la imagen, o es un círculo, con un marco de hasta 2 mm alrededor de la imagen. El ojal, con un agujero de 1,5 a 3 mm, va en cualquier punto del contorno, con un deslizador o arrastrándolo en la vista previa. La imagen se reduce a 1 a 8 colores de filamentos que tenemos en stock; los colores van uno sobre otro, cada uno de 0,2 a 0,8 mm más alto. Los ganchos y las anillas no se incluyen; se compran en una mercería.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): un par de corazones rojos de la biblioteca, cada uno de 28 mm de ancho y 2,4 mm de grosor, pesa en total 2,2 g, se imprime en 6 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, el pedido mínimo, sin envío (cálculo /c/5xbjn5tfg5gn). Un pendiente pesa alrededor de un gramo, así que no tira. Varios pares del mismo diseño se indican como cantidad y el precio por par baja. Nuestra granja imprime hasta cuatro colores en una impresión, la base y tres más; confirma la bobina de cada color al encargar. El STL y el proyecto para el slicer con los cambios de color se descargan gratis, sin registro.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen o escriba un texto', 'text' => 'Suba una imagen, elija una de la biblioteca o reutilice una subida antes. El fondo se quita solo; un deslizador fija cuánto desaparece.'],
        ['name' => 'Elija el par y el tamaño', 'text' => 'Elija pendientes iguales o en espejo, y una forma que siga la imagen o un círculo. Ajuste el ancho de un pendiente, el grosor y un marco de hasta 2 mm alrededor de la imagen.'],
        ['name' => 'Coloque el ojal y elija los colores', 'text' => 'Mueva el ojal con un deslizador o arrastrándolo en la vista previa. En la ventana de colores puede dar a cada color otro filamento, cambiar el orden de los colores o fusionar dos.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'La vista previa muestra los dos pendientes en los colores de filamento y las medidas en milímetros. El precio y el tiempo exactos llegan un paso más allá, en el cálculo. El STL y el proyecto para el slicer se descargan gratis, sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Se incluyen los ganchos?', 'a' => 'No. Recibe solo las piezas de plástico con ojal. Los ganchos, las anillas o los pernos se venden en mercerías y los pasa usted por el ojal; a un ojal de 2 mm le va una anilla de 4 a 5 mm.'],
        ['q' => '¿Para qué sirve el par en espejo?', 'a' => 'El pendiente derecho va volteado, así que el motivo mira hacia el mismo lado en ambas orejas. Con un motivo simétrico, como un corazón, basta un par igual; no ponga en espejo un texto, se leería al revés.'],
        ['q' => '¿Cuánto pesa un pendiente y aguanta?', 'a' => 'El PLA es ligero: un pendiente de 32 mm de ancho y 2,4 mm de grosor pesa de 1 a 2 gramos, así que no tira de la oreja. El ojal va unido al cuerpo; con 2,4 mm de grosor y una pared de ojal de 1,6 mm aguanta el uso diario, y la herramienta avisa de motivos finos por debajo de 0,8 mm.'],
        ['q' => '¿Qué imagen sale bien?', 'a' => 'Una silueta sencilla o un motivo con 2 a 4 colores: un corazón, una estrella, una hoja, una letra. A 30 mm los detalles finos de una foto se funden, así que los retratos quedan mejor en un imán o un posavasos. Un nombre corto o unas iniciales se escriben en lugar de la imagen.'],
        ['q' => '¿De qué se imprimen y cómo los imprimo en casa?', 'a' => 'Recomendamos PLA; los dos pendientes se imprimen juntos, planos, con la cara hacia arriba y sin soportes, capas de 0,2 mm o más finas. El proyecto para OrcaSlicer, Bambu Studio y PrusaSlicer lleva los cambios de filamento a las alturas de los colores. Los colores a ras de la superficie necesitan una impresora que cambia el filamento sola (AMS, MMU, ACE).'],
        ['q' => '¿Cuántos colores imprimen?', 'a' => 'Hasta cuatro en una impresión: la base y tres colores encima. Confirma la bobina de cada color al encargar; las del diseño vienen preseleccionadas. Un diseño con más colores lo descarga y lo imprime en su propia impresora.'],
    ],
    'examples' => [
        'Un par de corazones rojos, cada uno de 28 mm de ancho: 2,2 g, 6 minutos, 99 CZK.',
        'Un par en espejo de fantasmas, cada uno de 32 mm de ancho, el ojal en la punta.',
        'Pendientes redondos de 30 mm con una estrella sonriente, tres colores.',
    ],
];
