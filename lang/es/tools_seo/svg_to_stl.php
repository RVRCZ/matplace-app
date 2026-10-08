<?php

return [
    'title' => 'SVG a STL: convierta un contorno en modelo 3D online',
    'description' => 'Suba un SVG o una imagen sencilla, indique el ancho y la altura en milímetros y descargue el STL gratis y sin registro. O se lo imprimimos.',
    'h1' => 'Convertidor de SVG a STL: un contorno extruido en altura',
    'intro' => [
        'La herramienta toma las formas con relleno de un archivo SVG y las extruye en vertical hasta la altura que indique: de 0,6 a 50 mm. El ancho se ajusta de 20 a 250 mm y la otra medida sigue la proporción, de modo que el modelo tiene exactamente los milímetros que lee junto a la vista previa. Los huecos dentro de una forma siguen siendo huecos. En lugar de un SVG puede subir una imagen sencilla y contrastada en PNG, JPG o WebP, elegir un motivo de la biblioteca o escribir un texto.',
        'El resultado es un sólido cerrado y listo para el laminador, no una malla con agujeros que haya que reparar. La herramienta avisa de antemano de las líneas de menos de 0,8 mm y dice de cuántas piezas sueltas se compone la forma. El borde superior puede llevar un bisel. El modelo se descarga como STL o como proyecto listo para el laminador, o se encarga impreso en nuestra granja de impresión en un color que esté cargado ahora mismo en las impresoras.',
    ],
    'steps' => [
        ['name' => 'Suba un SVG', 'text' => 'Elija un archivo SVG de hasta 400 kB, o una imagen PNG, JPG o WebP de hasta 5 MB. Para probar, ya hay elegido un motivo de la biblioteca.'],
        ['name' => 'Indique el ancho y la altura', 'text' => 'El ancho de la forma se ajusta con el deslizador o arrastrando la flecha en la vista previa; el grosor, de 0,6 a 50 mm. El bisel del borde superior se activa con una casilla.'],
        ['name' => 'Revise la vista previa', 'text' => 'Junto a la vista previa lee las medidas exteriores y un precio orientativo; debajo, los avisos sobre líneas finas o piezas sueltas.'],
        ['name' => 'Descargue el STL o encargue la impresión', 'text' => 'El modelo se descarga gratis y sin registro, como STL o como proyecto para el laminador. La impresión en nuestra granja se paga con crédito prepago.'],
    ],
    'faq' => [
        ['q' => '¿Qué SVG admite la herramienta?', 'a' => 'Un archivo de hasta 400 kB con formas rellenas. Las líneas sin relleno se omiten y la herramienta lo indica; conviértalas antes en contornos en su programa de dibujo (Stroke to Path en Inkscape) y los textos en curvas.'],
        ['q' => '¿En qué unidades sale el modelo?', 'a' => 'En milímetros. Las medidas escritas en el SVG no importan: la forma se escala al ancho que indique y la otra medida sigue la proporción.'],
        ['q' => '¿Por qué la forma sale en varias piezas?', 'a' => 'Un contorno extruido no tiene placa, así que nada une las partes separadas de un dibujo. La herramienta dice cuántas son. Si deben quedar unidas, cambie el acabado a relieve sobre placa.'],
        ['q' => '¿Puedo subir un PNG o un JPG en lugar de un SVG?', 'a' => 'Sí, una imagen sencilla con el motivo oscuro sobre fondo claro. Se traza en una cuadrícula de 360 puntos como máximo en el lado largo, por lo que un SVG da bordes más limpios. Una fotografía no encaja aquí; para eso está la herramienta Relieve y litofanía.'],
        ['q' => '¿Hasta qué altura se puede extruir la forma?', 'a' => 'De 0,6 a 50 mm. Hasta 3 mm sale una etiqueta o un adorno, hacia 5 mm un colgante o un posavasos y, a partir de 20 mm, una forma que se sostiene sola.'],
        ['q' => '¿Cómo pago y cómo recibo la impresión?', 'a' => 'Se paga con crédito prepago que se recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [
        'Silueta de gato de la biblioteca, 80 × 81 mm, extruida a 6 mm.',
        'Hoja de roble de 100 × 62 mm y 3 mm de grosor, con el borde superior biselado.',
        'Estrella de 60 × 57 mm extruida hasta 20 mm de altura.',
    ],
];
