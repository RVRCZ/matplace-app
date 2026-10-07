<?php

return [
    'title' => 'Dividir un modelo 3D en piezas que quepan en su impresora',
    'description' => 'Un modelo mayor que la cama de impresión se corta en piezas con pasadores o llaves de cola de milano, numeradas en los cortes y apoyadas para imprimir sin soportes. Gratis y en línea.',
    'h1' => 'Dividir un modelo en piezas: una impresión grande en una impresora pequeña',
    'intro' => [
        'La herramienta corta un modelo con planos a lo ancho, a lo fondo y a lo alto en piezas que quepan en la cama elegida: nuestra granja de 250 mm, impresoras comunes de 220 o 180 mm, o una medida propia. Usa los menos cortes posibles y los planos se pueden mover con deslizadores; la vista previa los muestra sobre el modelo. Cada pieza se gira para apoyar su mayor cara de corte en la cama, de modo que se imprime sin soportes en la unión.',
        'En las uniones pone pasadores de 6 mm con agujeros a ambos lados y 0,2 mm de holgura, o canales de cola de milano con una llave doble suelta que entra de lado. Graba el número de cada pieza en su cara de corte y muestra un mapa de cómo van las piezas unas respecto a otras. Un modelo que no está cerrado se cierra antes. Las piezas se abren como un archivo nuevo: las imprime con nosotros o las descarga una a una.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre un modelo al recuadro o haga clic para elegir un archivo: STL, 3MF, OBJ o STEP de 5 a 1000 mm.'],
        ['name' => 'Elija la cama y las uniones', 'text' => 'Elija la impresora para la que son las piezas y el tipo de unión: pasadores, llaves de cola de milano o cortes lisos. La herramienta dice cuántos cortes hacen falta y puede mover los planos.'],
        ['name' => 'Divida el modelo', 'text' => 'Haga clic en «Dividir el modelo». Los modelos grandes tardan hasta un minuto; la página dice qué está pasando.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Ve las piezas en la vista previa, cada una de su color, con un mapa para montarlas. Descárguelas una a una o como proyecto para su laminador, o mándelas imprimir con nosotros.'],
    ],
    'faq' => [
        ['q' => '¿Cómo pego las piezas?', 'a' => 'Siguiendo los números grabados en las caras de corte y el mapa de la página. Meta los pasadores en los agujeros (están en el archivo como la pieza «pasadores», o use tacos de madera de 6 mm) y pegue las piezas con cianoacrilato o pegamento para plásticos. Las llaves de cola de milano aguantan incluso sin pegamento.'],
        ['q' => '¿Por qué las piezas se apoyan por la cara de corte?', 'a' => 'La cara de corte es plana: se sujeta en la cama y la unión no necesita soportes. Una pieza con varios cortes se apoya por el mayor. Si prefiere otra orientación, gírela en su laminador.'],
        ['q' => '¿Y si una pieza sigue sin caber?', 'a' => 'La herramienta lo avisa. Añada un plano de corte, elija una cama mayor o reduzca antes el modelo. Una forma que no cabe ni con seis cortes en una dirección se rechaza.'],
        ['q' => '¿Qué pasa con un modelo con agujeros?', 'a' => 'Antes de cortar, la herramienta lo cierra con el mismo método que usa nuestra granja. Si no lo consigue, lo dice; repare ese modelo primero con la herramienta de reparación.'],
        ['q' => '¿Qué tamaño de modelo admite la herramienta?', 'a' => 'Hasta 1000 mm y 2 millones de triángulos; una malla más pesada se simplifica cuando es posible. Los modelos grandes tardan hasta un minuto.'],
        ['q' => '¿Cuánto cuesta y cómo recibo la impresión?', 'a' => 'Dividir es gratis. Solo paga la impresión si la pide con nosotros, con crédito prepagado. Enviamos la impresión por Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [],
];
