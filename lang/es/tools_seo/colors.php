<?php

return [
    'title' => '3MF de colores en piezas por color: división online',
    'description' => 'Suba un 3MF multicolor de Bambu Studio, Orca o PrusaSlicer y obtenga una pieza por cada color: archivos STL para imprimir color a color o para pegar.',
    'h1' => 'Un modelo de colores dividido en piezas por color',
    'intro' => [
        'La herramienta lee los colores de un 3MF: los materiales del archivo, el extrusor asignado a objetos y piezas, y el pintado con pincel de Bambu Studio, Orca Slicer o PrusaSlicer. Cada color se convierte en una pieza propia. Un cuerpo que es todo de un color se queda como está; un color pintado sobre una superficie se recorta como una incrustación de la profundidad que indique (1,2 mm normalmente) y el cuerpo recibe un rebaje de la misma profundidad para que encaje.',
        'Descarga las piezas una a una como STL y las imprime cada una en su color en cualquier impresora, o las deja en su sitio y las imprime de una vez como trabajo multimaterial. La vista previa muestra las piezas en los colores del archivo e indica de dónde salen. Un triángulo que el slicer subdividió al pintar toma el color de su parte mayor, y la herramienta lo avisa. La división es gratis, el modelo no sale de nuestro servidor y se borra al cabo de un mes.',
    ],
    'steps' => [
        ['name' => 'Suba un 3MF de colores', 'text' => 'Un archivo guardado desde el slicer con sus colores, o un 3MF con materiales de un programa CAD. La herramienta muestra enseguida los colores encontrados y su proporción.'],
        ['name' => 'Elija la profundidad de las incrustaciones', 'text' => 'Cuánto entra un color pintado en el modelo, de 0,6 a 3 mm. A los cuerpos separados no les afecta.'],
        ['name' => 'Divida', 'text' => 'Pulse «Dividir por colores». En unos segundos está listo: las piezas en la vista previa con sus colores y una lista con nombres y proporciones.'],
        ['name' => 'Descargue o encargue', 'text' => 'Cada pieza por separado como STL, o el conjunto para imprimir de una vez. El precio de la impresión con nosotros se ve al momento.'],
    ],
    'faq' => [
        ['q' => '¿Qué colores reconoce la herramienta?', 'a' => 'Materiales 3MF (basematerials, grupos de colores), colores en el objeto y en triángulos sueltos, el extrusor asignado a un objeto o pieza en Bambu Studio, Orca y PrusaSlicer, y el pintado con pincel (paint_color, mmu_segmentation). Los colores de los filamentos salen del proyecto; sin ellos se usa una paleta fija.'],
        ['q' => '¿Qué pasa con un color pintado?', 'a' => 'Las caras pintadas se desplazan hacia dentro la profundidad indicada y se cierran con paredes: una incrustación. El cuerpo recibe un rebaje de la misma profundidad. La incrustación sobresale 0,05 mm para poder lijarla a ras tras la impresión.'],
        ['q' => '¿Por qué una pieza tiene un color inesperado?', 'a' => 'El slicer subdivide los triángulos al pintar; la herramienta da a todo el triángulo el color de su parte mayor y dice a cuántos triángulos afecta. Un resultado más fino se obtiene con una malla más fina antes de pintar.'],
        ['q' => '¿Se puede imprimir de una vez?', 'a' => 'Sí: el conjunto mantiene las piezas en su sitio; en el slicer las asigna a extrusores o ranuras del AMS e imprime un solo trabajo multimaterial. En una impresora de un color imprime las piezas una a una y las pega.'],
        ['q' => '¿Cuántos colores admite?', 'a' => 'Hasta 16; los colores menores por encima de ese número se unen al mayor. El archivo puede tener hasta 2 millones de triángulos.'],
        ['q' => '¿Guardan mi modelo?', 'a' => 'El modelo se queda en nuestro servidor solo para el cálculo y la descarga y se borra al cabo de un mes. No se lo enviamos a nadie.'],
    ],
    'examples' => [],
];
