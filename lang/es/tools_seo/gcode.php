<?php

// La página para la búsqueda «gcode a stl» (docs/AP.md §1, §4). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Convertir G-code a STL online y gratis: el modelo de vuelta',
    'heading' => 'Convertir G-code de vuelta a STL',
    'description' => 'Suba un .gcode hasta 150 MB y las paredes exteriores se convierten en un modelo cerrado: STL, piezas por color o proyecto 3MF. Calcule el precio. Gratis.',
    'h1' => 'Qué sale del G-code, con qué exactitud y qué se pierde',
    'intro' => [
        '¿Solo tiene el G-code y el modelo original se perdió, o recibió un archivo de impresión sin su origen? La herramienta lee las trayectorias del cabezal y reconstruye un modelo macizo y cerrado a partir de las paredes exteriores de cada capa. La superficie queda donde estaba la pared exterior de la impresión; los agujeros y las islas se conservan, y las paredes interiores, el relleno, los soportes, el borde y la torre de purga no dan forma al modelo. Entiende G-code de OrcaSlicer, Bambu Studio, Anycubic Slicer, PrusaSlicer y Cura, incluidos arcos y cambios de herramienta, hasta 150 MB y 1,5 millones de líneas. De la cabecera lee el laminador, la impresora, la altura de capa, el tiempo de impresión y el filamento consumido. Los colores se conservan: varias herramientas en una capa dan una pieza por color y un proyecto 3MF con los colores; los cambios de color por altura quedan como cambios de filamento.',
        'Un ejemplo del 27 de octubre de 2026 (ejecutado en nuestro ordenador, archivos de nuestra propia impresora): un G-code de Anycubic Slicer Next para una Kobra S1, de 5,6 MB y 191 mil líneas, 165 capas de 0,2 mm, impresión de 36 minutos y 3,6 g, dio en 12 segundos un modelo de 91 × 57 × 16,5 mm con 522 mil triángulos. Un G-code de dos colores de 14 MB y 545 mil líneas (39 capas de 0,12 mm, 5 h 16 min, 81 g) dio en 9 segundos dos piezas por color, negra de 49 g y amarilla Silk de 33 g, con los colores de la cabecera. En un sólido conocido el volumen salió un 0,8 % mayor que el modelo original. El modelo nuevo se abre como cualquier otro archivo: calcula el precio, cambia el tamaño o los colores y nos lo encarga impreso, o descarga el STL, las piezas por color o el proyecto 3MF.',
    ],
    'steps' => [
        ['name' => 'Suba el G-code', 'text' => 'Arrastre el archivo .gcode al recuadro o haga clic y elíjalo. Hasta 150 MB y 1,5 millones de líneas; no hay nada que ajustar.'],
        ['name' => 'Espere unos segundos', 'text' => 'La herramienta encuentra los bucles de las paredes exteriores de cada capa, los anida y los extruye en un sólido. Una impresión normal tarda de tres a doce segundos.'],
        ['name' => 'Mire el resultado', 'text' => 'La vista previa muestra el modelo; el informe de debajo, el laminador, la impresora, la altura de capa, el tiempo, los gramos y los colores. Las piezas por color aparecen coloreadas en la vista previa.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Calcule el precio y encárguenos el modelo impreso, o descargue el STL, las piezas por color o el proyecto 3MF para su laminador.'],
    ],
    'faq' => [
        ['q' => '¿Qué exactitud tiene un modelo a partir de G-code?', 'a' => 'La superficie queda donde estaba el centro de la pared exterior más media anchura de línea, es decir, donde estaba la superficie de la impresión. En un sólido conocido el volumen salió un 0,8 % mayor, la planta con centésimas de milímetro y la altura redondeada a capas. Las esquinas quedan redondeadas media línea y los escalones de capa se conservan, como en la impresión real.'],
        ['q' => '¿Qué G-codes lee la herramienta?', 'a' => 'G-code para impresoras FDM con marcas de tipo de trayectoria: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura. No lee .bgcode binario, G-code sin marcas de tipo (Cura antiguo, Simplify3D) ni archivos para resina o CNC; en esos casos dice que no encontró paredes. Un paquete .gcode.3mf de Bambu Studio es un zip: descomprímalo y suba el archivo .gcode de su carpeta Metadata.'],
        ['q' => '¿Qué pasa con los colores?', 'a' => 'Varias herramientas en una capa (una letra incrustada en una placa, por ejemplo) dan una pieza por color con el color del filamento de la cabecera, más un proyecto 3MF con los colores. Los cambios de color por altura quedan como cambios de filamento a esas alturas. Un filamento da una pieza.'],
        ['q' => '¿Por qué faltan detalles pequeños y texto en el modelo?', 'a' => 'El modelo se forma solo con las paredes exteriores: lo que es más estrecho que una línea (0,4 mm) el laminador nunca lo imprimió, así que no está en el G-code. El relleno y las capas superior e inferior no dan forma a nada. Un texto en relieve o un patrón fino del modelo original vuelve solo tan basto como la impresora lo imprimió realmente.'],
        ['q' => '¿Puedo escalar el modelo o seguir editándolo?', 'a' => 'Sí, el resultado es un STL normal: lo escala en la calculadora, lo vacía, lo divide en piezas o hace con él un molde. Al agrandarlo también crecen los escalones de capa que quedaron en el modelo; para el doble o más, conviene lijar la superficie.'],
        ['q' => '¿Y un G-code impreso en modo jarrón?', 'a' => 'Una espiral no tiene bucles cerrados, así que la herramienta cierra cada capa donde la espiral pasa por su altura. El modelo sale macizo; para imprimirlo de nuevo como jarrón, active otra vez el modo jarrón en su laminador.'],
    ],
    'examples' => [],
];
