<?php

return [
    'title' => 'Modelo a tamaño real: ampliar, vaciar y dividir en piezas',
    'description' => 'Indique una altura y la herramienta amplía el modelo, vacía los grandes y lo corta en piezas con pasadores que caben en la impresora. Con gramos y precio.',
    'h1' => 'A tamaño real: un busto, una figura o una estatua a escala de un metro',
    'intro' => [
        'La herramienta amplía un modelo a la altura que indique en centímetros, de 5 a 100 cm. Las piezas de más de 200 cm³ se vacían con una pared de 2 a 5 mm según el tamaño, para que no cuesten una fortuna ni se impriman durante días; alrededor de los futuros cortes el modelo queda macizo para que los pasadores tengan dónde asentarse. Después lo corta con planos en piezas que caben en la cama elegida: nuestra granja de 250 mm, impresoras comunes de 220 o 180 mm, o una medida propia.',
        'Antes de construir nada dice cuánto medirá el modelo, si será hueco y cuántas piezas saldrán. El resultado son piezas con pasadores de 6 mm, números grabados en los cortes y apoyadas por la cara de corte, con una estimación de gramos y precio de impresión y un mapa de cómo van las piezas. Por encima de 20 piezas la herramienta avisa. Las piezas se abren como un archivo nuevo: las imprime con nosotros o las descarga una a una.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre un modelo al recuadro o haga clic para elegir un archivo: STL, 3MF, OBJ o STEP. Van bien bustos, figuras y estatuillas; el modelo no debe superar un metro tras la ampliación.'],
        ['name' => 'Indique la altura y la cama', 'text' => 'La altura en centímetros, la impresora para la que son las piezas y el tipo de unión. La herramienta dice enseguida cuánto medirá el modelo y cuántas piezas saldrán.'],
        ['name' => 'Ampliar y dividir', 'text' => 'Haga clic en «Ampliar y dividir». Los modelos grandes tardan hasta un minuto; la página dice qué está pasando.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Ve las piezas en la vista previa, cada una de su color, con gramos, precio y mapa. Descárguelas una a una o como proyecto para su laminador, o mándelas imprimir con nosotros.'],
    ],
    'faq' => [
        ['q' => '¿Por qué el modelo es hueco?', 'a' => 'Un busto macizo de 40 cm pesaría más de un kilo y se imprimiría durante días. Una cavidad con pared de 2 a 5 mm ahorra la mayor parte del material y no cambia el exterior. El vaciado se puede desactivar.'],
        ['q' => '¿Cómo se unen las piezas?', 'a' => 'Pasadores de 6 mm en agujeros con 0,2 mm de holgura (están en el archivo como la pieza «pasadores», o use tacos de madera) y pegamento para plásticos. Alrededor de los cortes el modelo queda macizo, así que los pasadores asientan en material. Las llaves de cola de milano y los cortes lisos son las otras dos opciones.'],
        ['q' => '¿De dónde saco un modelo?', 'a' => 'De nuestra herramienta Busto a partir de una foto, de Printables o MakerWorld, o de su propio escaneo. El modelo debe ser un cuerpo cerrado; lo que no lo es, la herramienta lo cierra.'],
        ['q' => '¿Qué tamaño de modelo admite la herramienta?', 'a' => 'Hasta 1000 mm tras la ampliación; una entrada de hasta 2 millones de triángulos. Los modelos grandes tardan hasta un minuto; su rejilla de vaciado es más gruesa y la herramienta lo avisa.'],
        ['q' => '¿Y si una pieza sigue sin caber?', 'a' => 'La herramienta lo avisa. Elija una cama mayor o una altura menor. Una forma que no cabe ni con seis cortes en una dirección se rechaza.'],
        ['q' => '¿Cuánto cuesta y cómo recibo la impresión?', 'a' => 'Ampliar y dividir es gratis. Solo paga la impresión si la pide con nosotros, con crédito prepagado. Enviamos la impresión por Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [],
];
