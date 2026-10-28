<?php

// La página para la búsqueda «imán de nevera con foto impresión 3d» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Imán de nevera con su foto o imagen, impreso en 3D, gratis',
    'heading' => 'Imán de nevera a partir de una foto o una imagen, impreso en 3D',
    'description' => 'Una foto, una imagen o un nombre se convierte en un imán de nevera en colores de filamento con un hueco a medida para su imán. Impresión o descarga gratis.',
    'h1' => 'Cómo nace el imán, qué imán lleva dentro y cuánto cuesta',
    'intro' => [
        'La herramienta convierte una imagen en un imán de nevera. Suba un PNG, JPG, WebP o SVG, elija un motivo de la biblioteca o escriba un nombre. La imagen se reduce a 1 a 8 colores de filamentos que tenemos en la granja; el fondo de una foto se quita solo y la pieza sigue el contorno del motivo, o es un círculo o un rectángulo con la foto entera. Ancho de 40 a 100 mm, grosor de 3 a 6 mm, un marco de hasta 3 mm alrededor de la imagen y, si quiere, un borde biselado. Por detrás hay en el centro un hueco para el imán: elige un imán de disco corriente de Ø 6 × 2 a Ø 20 × 3 mm, o indica su propia medida, y lo pega, lo encaja a presión o deja un agujero pasante; para lámina magnética adhesiva elige la versión sin hueco. Si el imán es alto para el grosor elegido, la herramienta engrosa la base para que queden cuatro capas de plástico encima. Los imanes no se incluyen.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): la huella de la biblioteca en un círculo de 60 mm, de 3 mm de grosor y con hueco para un imán de Ø 10 × 2 mm, pesa 7 g, se imprime en 14 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, el pedido mínimo, sin envío (cálculo /c/gob4imtsfmor). Varias unidades del mismo diseño se indican como cantidad y el precio por pieza baja; los imanes con las fotos de toda la familia son diseños distintos. Nuestra granja imprime hasta cuatro colores en una impresión, la base y tres más; confirma la bobina de cada color al encargar. El STL, un ZIP con un STL por color y los proyectos para el slicer se descargan gratis, sin registro.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen o una foto', 'text' => 'El fondo se quita solo y la forma sigue entonces el contorno del motivo. Para conservar la foto entera, desactive la eliminación del fondo. Los deslizadores ajustan contraste, brillo y saturación de una foto.'],
        ['name' => 'Elija la forma y las medidas', 'text' => 'La forma sigue la imagen, o es un círculo o un rectángulo. Ajuste el ancho, el grosor, un marco de hasta 3 mm alrededor de la imagen y, si quiere, un borde biselado.'],
        ['name' => 'Elija el imán y su sujeción', 'text' => 'Elija la medida del imán y cómo se sujeta: un hueco para pegar con holgura de 0,1 a 0,3 mm, un hueco a presión, un agujero pasante o sin hueco para lámina magnética.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el cálculo tras «Continuar» muestra el precio y el tiempo exactos. El STL, un ZIP con un STL por color y los proyectos para el slicer se descargan gratis, sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Qué imanes encajan en el hueco?', 'a' => 'Hay preajustes para los imanes de disco corrientes de Ø 6 × 2, 8 × 3, 10 × 2, 12 × 3, 15 × 3 y 20 × 3 mm. Una medida propia puede tener de 4 a 30 mm de diámetro y de 1 a 6 mm de alto. Los imanes no forman parte de la impresión; para una pieza de 60 mm basta un neodimio de Ø 10 × 2 mm.'],
        ['q' => '¿Cómo se sujeta el imán?', 'a' => 'En un hueco para pegar lo fija con pegamento, por ejemplo cianoacrilato; la holgura alrededor del imán se ajusta de 0,1 a 0,3 mm. En un hueco a presión el imán se empuja. Un agujero pasante atraviesa toda la pieza, así que el imán puede verse por delante.'],
        ['q' => '¿Qué foto sale bien?', 'a' => 'Un dibujo, un logotipo o una foto con un motivo claro sobre un fondo tranquilo; la herramienta quita el fondo y la pieza sigue el contorno. Para un retrato deje un círculo o un rectángulo con la foto entera y 6 a 8 colores, sale como un cartel. Los detalles por debajo de 0,8 mm se funden.'],
        ['q' => '¿Y si el imán es demasiado alto para el grosor elegido?', 'a' => 'La herramienta engrosa la base por sí sola para que queden cuatro capas de plástico sobre el imán, y muestra el nuevo grosor. Si el imán no cabe dentro de la forma, le avisa de que aumente el ancho o elija un imán menor.'],
        ['q' => '¿De qué se imprime y cómo lo imprimo en casa?', 'a' => 'Recomendamos PLA. Se imprime con la cara hacia arriba, el hueco hacia abajo y sin soportes, capas de 0,2 mm; el hueco está en la base, así que la impresora puentea unos milímetros sobre él. Para lugares donde se calienta, elija PETG.'],
        ['q' => '¿Imprimen el imán en colores?', 'a' => 'Sí, hasta cuatro colores en una impresión: la base y tres colores encima. Confirma la bobina de cada color al encargar; las del diseño vienen preseleccionadas. Un diseño con más colores se descarga gratis; los colores van uno sobre otro, así que cualquier impresora lo imprime cambiando el filamento a las alturas que indica el proyecto.'],
    ],
    'examples' => [
        'Huella en un círculo de 60 mm de ancho, con hueco por detrás para un imán de Ø 10 × 2 mm: 7 g, 14 minutos, 99 CZK.',
        'Corazón rojo de 50 mm de ancho con un imán a presión de Ø 8 × 3 mm.',
        'Una foto en un rectángulo de 80 mm de ancho, 6 colores, agujero pasante para un imán de Ø 12 × 3 mm.',
    ],
];
