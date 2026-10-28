<?php

// La página para la búsqueda «logotipo impreso en 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Logotipo impreso en 3D desde SVG o imagen: relieve o de pie',
    'heading' => 'Logotipo impreso en 3D desde su SVG, imagen o texto',
    'description' => 'Suba un SVG o una imagen de su logotipo, o escriba un texto, y obtenga un relieve sobre placa, una forma recortada o un logotipo de pie. Modelo gratis.',
    'h1' => 'Qué acabados hace la herramienta, qué vigilar en el original y cuánto cuesta imprimirlo',
    'intro' => [
        'La herramienta convierte un logotipo, una imagen o un texto en un modelo para impresión 3D. Puede subir un SVG con formas rellenas de hasta 400 kB o una imagen sencilla y contrastada en PNG, JPG o WebP de hasta 5 MB, o escribir hasta dos líneas de 30 caracteres. Elige uno de cuatro acabados: relieve sobre placa (redondeada, rectangular o circular), relieve esculpido a partir de una imagen, donde los tonos marcan la altura, forma recortada sin placa, o logotipo de pie en una peana con ranura. El ancho del motivo se ajusta de 20 a 250 mm y la altura sigue la proporción. La herramienta avisa de líneas más finas de 0,8 mm, que una impresora no imprime limpiamente, y de partes que no se mantendrían unidas. En el relieve sobre placa el motivo se imprime en un segundo color: la impresora lo cambia a la altura en que empieza el motivo.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): un relieve sobre placa redondeada de 100 mm de ancho con el texto LOGO y la silueta de un gato pesa 27,6 g, se imprime en 43 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo, sin envío (cálculo /c/11vhrvmivr3e). Un cartel mayor para una puerta o un mostrador se calcula por gramos y minutos, y ve el precio antes de encargar nada. Los colores se eligen entre las bobinas que tenemos en stock ahora mismo, así que no podemos garantizar un tono corporativo exacto. El modelo se descarga gratis como STL o proyecto para su impresora; con el relieve, también la placa y el motivo como piezas separadas.',
    ],
    'steps' => [
        ['name' => 'Suba el original o escriba un texto', 'text' => 'Suba un SVG o una imagen, o escriba un texto en una o dos líneas de 30 caracteres. El original subido tiene prioridad sobre el texto.'],
        ['name' => 'Elija el acabado', 'text' => 'Elija relieve sobre placa, relieve esculpido a partir de una imagen, forma recortada o logotipo de pie. En la placa elija la forma: redondeada, rectangular o circular.'],
        ['name' => 'Ajuste el tamaño', 'text' => 'Indique el ancho del motivo y su grosor. La altura sigue la proporción del original.'],
        ['name' => 'Revise la vista previa y los avisos', 'text' => 'Bajo la vista previa lee las medidas y los posibles avisos sobre líneas finas o piezas sueltas. Según ellos agrande el motivo o cambie el acabado.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Encargue la impresión en nuestra granja y elija los colores al hacer el pedido. O descargue gratis el modelo como STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Qué imagen admite la herramienta?', 'a' => 'Lo mejor es un SVG con formas rellenas de hasta 400 kB, o una imagen limpia con el motivo oscuro sobre fondo claro de hasta 5 MB. Los contornos sin relleno se omiten en el SVG. Una imagen se traza en una cuadrícula de 360 puntos como máximo en el lado largo, así que los detalles muy finos se pierden; un logotipo con texto pequeño envíelo como SVG.'],
        ['q' => '¿Puedo subir una fotografía?', 'a' => 'Solo en el acabado «Relieve esculpido a partir de una imagen», donde los tonos marcan la altura de la superficie: las zonas oscuras sobresalen más. En los demás acabados la herramienta rechaza la fotografía y remite a la herramienta Relieve y litofanía.'],
        ['q' => '¿Por qué un logotipo recortado se deshace en piezas?', 'a' => 'Una forma recortada no tiene placa, así que nada une las letras o las partes separadas de un logotipo. La herramienta dice de cuántas piezas se compone la forma. Si deben quedar unidas, elija relieve sobre placa, o una las piezas en su editor.'],
        ['q' => '¿Cómo funciona el logotipo de pie?', 'a' => 'El logotipo y la peana se imprimen como dos piezas y el logotipo se encaja en una ranura de la peana. Por resistencia, un logotipo de pie tiene al menos 2,4 mm de grosor. Las partes que no llegan a la peana, por ejemplo puntos y acentos, no se sostendrían y la herramienta lo avisa.'],
        ['q' => '¿Se puede imprimir el logotipo en dos colores?', 'a' => 'En el relieve sobre placa, sí: en la impresión con nosotros elige el segundo color y la impresora lo cambia a la altura en que empieza el motivo; el proyecto descargado lleva el cambio. Elige entre los colores cargados ahora mismo en las impresoras, así que no podemos garantizar un tono corporativo exacto.'],
        ['q' => '¿Qué tamaño de logotipo para una puerta o una recepción?', 'a' => 'Para la puerta de una oficina basta un relieve de 100 a 150 mm; para una recepción o un escaparate, de 200 a 250 mm; por encima de 250 mm el logotipo no cabe en la cama y se divide en piezas. Un logotipo de pie para un mostrador queda mejor entre 120 y 200 mm; para un cartel iluminado está la herramienta Cartel luminoso.'],
    ],
    'examples' => [
        'El texto ATELIER como relieve sobre placa redondeada de 110 × 26 mm, por ejemplo para una puerta.',
        'El texto OPEN de pie, de 120 mm de ancho en una peana, 43 mm de alto en total.',
        'La letra M como forma recortada de 80 × 72 mm, 2 mm de grosor.',
    ],
];
