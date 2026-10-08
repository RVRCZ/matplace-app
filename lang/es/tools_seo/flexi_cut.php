<?php

return [
    'title' => 'Flexi de su propio modelo 3D: segmentos con articulaciones',
    'description' => 'Un modelo alargado se corta en segmentos con articulaciones de bola que se imprimen de una vez, montados, y se doblan al despegarlos. Gratis.',
    'h1' => 'Un juguete flexible de su propio modelo: un flexi con articulaciones de bola',
    'intro' => [
        'La herramienta corta un modelo alargado a lo largo de su eje más largo (o del eje que elija) en 3 a 20 segmentos y pone una articulación de bola en cada corte: una bola de 6 a 10 mm sobre un cuello que sale de un segmento y una cavidad con 0,35 a 0,5 mm de holgura en el otro. La bola se asienta en la cavidad más adentro de lo ancha que es la abertura, así que no se puede sacar, pero gira. Entre los segmentos queda un hueco de dos capas.',
        'El conjunto se imprime como una sola pieza montada, sin soportes, y al despegarlo de la cama las articulaciones se sueltan con los dedos y el juguete se dobla como los conocidos dragones impresos. En los cortes el modelo debe medir al menos la bola más 3 mm; donde no, la herramienta lo avisa y omite esa articulación. El flexi se abre como un archivo nuevo: lo imprime con nosotros o lo descarga.',
    ],
    'steps' => [
        ['name' => 'Suba un modelo alargado', 'text' => 'Arrastre un modelo al recuadro o haga clic para elegir un archivo: STL, 3MF, OBJ o STEP. Van bien serpientes, dragones, lagartos, peces y cualquier cosa con una longitud clara y grosor suficiente.'],
        ['name' => 'Elija los segmentos y las articulaciones', 'text' => 'El eje de los segmentos, su número (o automático según la bola), el diámetro de la bola y la holgura de la articulación; la altura del modelo si quiere.'],
        ['name' => 'Haga el flexi', 'text' => 'Haga clic en «Hacer el flexi». Tarda unos segundos; ve los segmentos en la vista previa, cada uno de su color, la articulación con rayos X.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Descargue el flexi como un solo STL o como proyecto para su laminador, o mándelo imprimir con nosotros.'],
    ],
    'faq' => [
        ['q' => '¿Cómo se imprime un flexi?', 'a' => 'Como una sola pieza montada, con capas de 0,2 mm y sin soportes. La mitad superior de la cavidad es un voladizo que una impresora normal maneja; la holgura de 0,4 mm mantiene separadas la bola y la cavidad. Tras imprimir, suelte las articulaciones con los dedos; el primer movimiento va duro.'],
        ['q' => '¿Qué grosor debe tener el modelo?', 'a' => 'En cada corte, al menos el diámetro de la bola más 3 mm (11 mm con una bola de 8 mm), para que la cavidad tenga pared alrededor. Donde no salga, la herramienta omite la articulación y lo avisa; elija una bola menor, menos segmentos o amplíe el modelo.'],
        ['q' => '¿Cuántos segmentos elegir?', 'a' => 'El modo automático da un segmento por cada unos 2,5 diámetros de bola; más segmentos doblan más, pero los segmentos cortos sujetan peor. Mínimo 3, máximo 20.'],
        ['q' => '¿Por qué las articulaciones no se mueven nada más imprimir?', 'a' => 'Las capas finas del hueco pueden haberse fundido; suelte las articulaciones con cuidado con los dedos o abra la holgura hasta 0,5 mm. Las articulaciones demasiado sueltas bailan: use 0,35 mm.'],
        ['q' => '¿Se puede hacer un flexi a partir de una foto?', 'a' => 'Sí: primero haga una figura en la herramienta Busto a partir de una foto o descargue un modelo de Printables o MakerWorld, y súbalo aquí.'],
        ['q' => '¿Cuánto cuesta y cómo recibo la impresión?', 'a' => 'Hacer el flexi es gratis. Solo paga la impresión si la pide con nosotros, con crédito prepagado. Enviamos la impresión por Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [],
];
