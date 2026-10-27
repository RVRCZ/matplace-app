<?php

// La página para la búsqueda «svg a stl» (docs/AP.md §1, §3). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Convertir SVG a STL online y gratis: contorno a modelo 3D',
    'heading' => 'Convertidor de SVG a STL online y gratis',
    'description' => 'Suba un SVG o PNG, o escriba un texto, indique el ancho en milímetros y la altura de extrusión. Descargue un STL cerrado sin registro, o se lo imprimimos.',
    'h1' => 'Qué hace el convertidor de SVG a STL y dónde se detiene',
    'intro' => [
        'La herramienta toma las formas con relleno de un archivo SVG y las extruye en vertical hasta la altura que indique: de 0,6 a 50 mm. El ancho se ajusta de 20 a 250 mm y la otra medida sigue la proporción, de modo que el modelo tiene exactamente los milímetros que lee junto a la vista previa; las unidades escritas en el SVG no importan. Los huecos dentro de una forma siguen siendo huecos. El SVG puede tener hasta 400 kB, y las líneas sin relleno se omiten y la herramienta lo indica. En lugar de un SVG puede subir una imagen sencilla y contrastada en PNG, JPG o WebP de hasta 5 MB, elegir una silueta de la biblioteca o escribir un texto. El resultado es un sólido cerrado con base, tapa y paredes, que el laminador acepta sin reparación. La herramienta avisa de antemano de las líneas de menos de 0,8 mm, que una boquilla de 0,4 mm no imprime limpiamente, y dice de cuántas piezas sueltas se compone la forma.',
        'Un ejemplo del 27 de octubre de 2026: la silueta de gato de la biblioteca, de 80 mm de ancho y extruida a 6 mm, salió con 80 × 81 × 6 mm, 2.568 triángulos y 17,7 cm³. El cálculo le asigna 9,6 g de PLA y 17 minutos de impresión, 99 CZK (unos 4 EUR) en nuestra granja como pedido mínimo, sin envío (cálculo /c/lfa9iggn3hls). El modelo se descarga gratis y sin cuenta como STL, o como proyecto 3MF para OrcaSlicer, Bambu Studio o PrusaSlicer con los ajustes del cálculo. Si no tiene impresora, nuestra granja imprime la forma en un color cargado ahora mismo en las impresoras.',
    ],
    'steps' => [
        ['name' => 'Suba un SVG, una imagen o un texto', 'text' => 'Un SVG de hasta 400 kB, una imagen PNG, JPG o WebP de hasta 5 MB, una silueta de la biblioteca o una línea de texto. Para probar, ya está elegido el gato de la biblioteca.'],
        ['name' => 'Indique el ancho y la altura de extrusión', 'text' => 'El ancho en milímetros se ajusta con el deslizador o arrastrando la flecha en la vista previa; la altura, de 0,6 a 50 mm. El bisel del borde superior se activa con una casilla.'],
        ['name' => 'Lea los avisos', 'text' => 'Junto a la vista previa están las medidas exteriores; debajo, una nota sobre líneas de menos de 0,8 mm y sobre piezas sueltas, si las hay.'],
        ['name' => 'Descargue el STL o encargue la impresión', 'text' => 'El STL o el proyecto para el laminador se descarga gratis y sin registro. La impresión con nosotros sigue al cálculo, donde ve los gramos, los minutos y el precio.'],
    ],
    'faq' => [
        ['q' => '¿Por qué mi laminador acepta un STL de aquí sin errores cuando otro convertidor dio una malla con agujeros?', 'a' => 'Cada contorno recibe base, tapa y paredes, y los cuerpos se unen en una sola malla cerrada. El laminador no señala aristas abiertas ni caras invertidas y el modelo no necesita reparación.'],
        ['q' => '¿Cómo preparo el SVG para que se convierta entero?', 'a' => 'Todo lo que deba imprimirse tiene que ser una forma rellena. En Inkscape convierta las líneas con Stroke to Path y el texto con Object to Path; Illustrator y Affinity hacen lo mismo. Capas, máscaras y degradados no se leen.'],
        ['q' => '¿Cómo consigo medidas exactas en milímetros?', 'a' => 'Indica el ancho y la otra medida sigue la proporción, así la forma nunca se deforma. Las medidas escritas en el SVG (px, mm, pulgadas) no se usan; vale lo que lee junto a la vista previa.'],
        ['q' => '¿Por qué la forma se separó en varias piezas?', 'a' => 'Un contorno extruido no tiene placa, así que nada une las partes separadas del dibujo, y la herramienta dice cuántas son. Si deben quedar unidas, cambie el acabado a relieve sobre placa, o una el dibujo en su editor.'],
        ['q' => '¿Puedo subir un PNG o un JPG en lugar de un SVG?', 'a' => 'Sí, una imagen sencilla con el motivo oscuro sobre fondo claro. El contorno se traza en una cuadrícula de 360 puntos como máximo en el lado largo, por lo que un SVG da bordes más limpios. Una fotografía no encaja aquí; para eso está la herramienta Relieve y litofanía.'],
        ['q' => '¿Es gratis la conversión y necesito una cuenta?', 'a' => 'La conversión y la descarga del STL son gratis y sin cuenta, y el archivo subido no se entrega a nadie. Solo necesita cuenta cuando nos encarga la impresión; se paga con crédito recargado con tarjeta y enviamos con Packeta por toda la UE.'],
    ],
    'examples' => [
        'Silueta de gato de la biblioteca, 80 × 81 mm, extruida a 6 mm: 9,6 g, 17 minutos de impresión.',
        'Hoja de roble de 100 × 62 mm y 3 mm de grosor, con el borde superior biselado.',
        'Estrella de 60 × 57 mm extruida hasta 20 mm de altura.',
    ],
];
