<?php

return [
    'title' => 'Modelo desde G-code: STL o 3MF del G-code, por colores',
    'description' => 'Suba un .gcode de hasta 150 MB y de las paredes exteriores nace un modelo estanco: STL, una pieza por color o un proyecto 3MF. Calcule el precio e imprímalo.',
    'h1' => 'Un modelo de vuelta desde el G-code: imprímalo otra vez, en otro tamaño o colores',
    'intro' => [
        '¿Solo tiene el G-code y el modelo original se perdió? La herramienta lee las trayectorias del cabezal y reconstruye un modelo macizo y estanco a partir de las paredes exteriores de cada capa. La superficie queda donde estaba la pared exterior de la impresión; los agujeros y las islas se conservan, las paredes interiores, el relleno, los soportes y el borde no influyen en la forma. Entiende el G-code de OrcaSlicer, Bambu Studio, Anycubic Slicer, PrusaSlicer y Cura, con arcos y cambios de herramienta.',
        'De la cabecera del archivo lee el laminador, la impresora, la altura de capa, el tiempo de impresión y el filamento gastado. Las impresiones a color se conservan: varias herramientas en una capa dan una pieza por color y un proyecto 3MF con los colores; los colores que se alternan por altura quedan como cambios de filamento. El modelo nuevo se abre como cualquier otro archivo: calcule el precio, cambie el tamaño o los colores y mándelo imprimir con nosotros, o descárguelo.',
    ],
    'steps' => [
        ['name' => 'Suba el G-code', 'text' => 'Arrastre el archivo .gcode al recuadro o haga clic para elegirlo. Hasta 150 MB y 1,5 millones de líneas; no hay nada que ajustar.'],
        ['name' => 'Espere unos segundos', 'text' => 'La herramienta encuentra los bucles de pared exterior de cada capa, los anida y los extruye en un sólido. Una impresión normal tarda de tres a diez segundos.'],
        ['name' => 'Mire el resultado', 'text' => 'La vista previa muestra el modelo; el informe debajo, el laminador, la impresora, la altura de capa, el tiempo, los gramos y los colores. Las piezas por color aparecen coloreadas.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Calcule el precio y mande imprimir el modelo con nosotros, o descargue el STL, las piezas por color o un proyecto 3MF para su laminador.'],
    ],
    'faq' => [
        ['q' => '¿Qué precisión tiene un modelo sacado del G-code?', 'a' => 'La superficie queda donde estaba el centro de la pared exterior más media anchura de línea, es decir, donde estaba la superficie de la impresión. En un cuerpo conocido el volumen salió un 0,8 % mayor, la planta con error de centésimas de milímetro y la altura redondeada a capas. Las esquinas quedan redondeadas a media línea y los escalones de capa permanecen, como en una impresión real; los detalles menores que una línea faltan.'],
        ['q' => '¿Qué archivos G-code lee la herramienta?', 'a' => 'G-code para impresoras FDM con marcas de tipo de trayectoria: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura. No lee paquetes .gcode.3mf, .bgcode binario, G-code sin marcas de tipo (Cura antiguo, Simplify3D) ni archivos para resina o CNC; en esos casos dice que no encontró paredes.'],
        ['q' => '¿Qué pasa con los colores?', 'a' => 'Varias herramientas en una misma capa (una letra incrustada en una placa, por ejemplo) dan una pieza por color con el color del filamento de la cabecera, más un proyecto 3MF con los colores. Los colores que se alternan por altura quedan como cambios de filamento a esas alturas. Un solo filamento da una sola pieza.'],
        ['q' => '¿Puedo ampliar el modelo o seguir editándolo?', 'a' => 'Sí, el modelo es un STL normal: escálelo en el cálculo, vacíelo, divídalo o haga un molde con las demás herramientas. Al ampliarlo también se amplían los escalones de capa que quedan en el modelo.'],
        ['q' => '¿Cuánto cuesta y cómo recibo la impresión?', 'a' => 'La conversión es gratis. Solo paga la impresión si la pide con nosotros, con crédito prepagado. Enviamos la impresión por Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [],
];
