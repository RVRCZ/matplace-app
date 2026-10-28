<?php

// La página para la búsqueda «generador de texto 3d para imprimir» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Generador de texto 3D para imprimir: palabra de pie, gratis',
    'heading' => 'Generador de texto 3D para imprimir: un nombre o una palabra que se sostiene',
    'description' => 'Escriba un nombre o una palabra, elija una de 30 tipografías y obtenga un texto 3D de letras gruesas que se sostiene solo. Impresión o descarga gratis.',
    'h1' => 'Cómo nace el texto, qué tamaño tiene y cuánto cuesta',
    'intro' => [
        'La herramienta convierte un texto en un rótulo que se sostiene solo en una estantería, una mesa o un escaparate. Escriba de una a tres líneas de hasta 40 caracteres, elija una de treinta tipografías y la altura de las mayúsculas, de 4 a 80 mm. Las letras tienen de 1,2 a 30 mm de grosor y se apoyan en una base común, así que incluso una tipografía cuyas letras no se tocan queda unida; las líneas superpuestas las une un travesaño. Añada una silueta de la biblioteca o su propio SVG, junto a las letras o encima. El texto se imprime tumbado sobre la espalda y sin soportes, por lo que el frente queda plano y liso. Si es demasiado fino para su altura y podría volcar, la herramienta lo avisa en la vista previa.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): la palabra HOME en Archivo Black, con letras de 30 mm de alto y 12 mm de grosor, mide 144 × 34 mm, pesa 21,1 g, se imprime en 40 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, sin envío (cálculo /c/bkdbbtgjavzn). El texto de dos líneas KAVÁRNA u Jany en Bebas Neue, de 20 mm de grosor, pesa 55,7 g, se imprime en 1 h 53 min y cuesta 130 CZK (cálculo /c/qxcoca4ayqkb). Ve el precio antes de encargar nada, también para varias unidades. El modelo se descarga gratis como STL o proyecto para su impresora, sin registro.',
    ],
    'steps' => [
        ['name' => 'Escriba el texto', 'text' => 'De una a tres líneas; la primera es la mayor. Los símbolos bajo los campos entran en el texto con un toque; añada una imagen de la biblioteca o suba un SVG.'],
        ['name' => 'Elija la tipografía', 'text' => 'Treinta tipografías se muestran como fichas con su nombre compuesto en la propia fuente. Las sans serif en negrita se sostienen mejor; las manuscritas son más decorativas.'],
        ['name' => 'Ajuste la altura y el grosor', 'text' => 'La altura de la letra fija el tamaño del texto; el grosor, su profundidad. Para sostenerse, el texto debe tener al menos un quinto de profundidad respecto a su altura; con menos, la herramienta avisa.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el cálculo tras «Continuar» muestra el precio exacto. El modelo y el proyecto para el slicer se descargan gratis y sin cuenta.'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño tendrá el texto?', 'a' => 'La palabra HOME con letras de 30 mm sale de 144 mm de largo, 34 mm de alto y 12 mm de fondo. La longitud crece con el número de letras y con la altura; en la cama de impresión cabe un texto de hasta 250 mm. Divida un texto más largo en dos líneas, la herramienta las une con un travesaño.'],
        ['q' => '¿Se sostendrá de verdad?', 'a' => 'Sí, si tiene al menos un quinto de profundidad respecto a su altura; con menos, la herramienta avisa. Un texto de una línea de 30 mm se sostiene desde 8 mm; dos líneas necesitan de 15 a 20 mm. Un texto con una imagen alta sobre las letras pesa arriba: dele un tercio más de profundidad.'],
        ['q' => '¿Y si las letras no se tocan?', 'a' => 'No importa. Todas se apoyan en la base y las líneas superpuestas las une un travesaño. Las tildes y los puntos que quedarían en el aire se sujetan con una unión corta, así que un nombre con acentos también es una sola pieza.'],
        ['q' => '¿Qué tipografía elijo?', 'a' => 'Una sans serif en negrita para una palabra corta, como Archivo Black o Bebas Neue. Una manuscrita para un nombre, por ejemplo Pacifico o Lobster. Use las caligráficas muy finas a partir de 30 mm; si no, la herramienta avisa de líneas más finas de 0,8 mm, que no se imprimen limpiamente.'],
        ['q' => '¿Se puede imprimir el texto en dos colores?', 'a' => 'El texto de pie es una sola pieza de un color. Para un segundo color, divida el texto en dos diseños y encargue cada uno en otro color, o elija el cartel, donde las letras van en un color distinto de la placa.'],
        ['q' => '¿Cómo imprimo el texto en casa?', 'a' => 'Sobre la espalda, con el frente hacia abajo, sin soportes, capas de 0,2 mm, PLA o PETG. El proyecto descargado para OrcaSlicer y PrusaSlicer ya tiene el texto tumbado; desde el STL, túmbelo usted sobre el frente. La parte inferior de la base queda lisa al despegarla de la cama.'],
    ],
    'examples' => [
        'La palabra HOME en Archivo Black, 144 × 34 mm, 12 mm de fondo: 21,1 g, 40 minutos, 99 CZK.',
        'El nombre Ela en caligráfica con un corazón en la misma base, 143 × 48 mm, 15 mm de fondo.',
        'Dos líneas, KAVÁRNA u Jany, en Bebas Neue, 20 mm de fondo: 55,7 g, 1 h 53 min, 130 CZK.',
    ],
];
