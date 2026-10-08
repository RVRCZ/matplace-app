<?php

return [
    'title' => 'Fidget deslizante en su propia placa: ranura con cursor',
    'description' => 'Suba un modelo plano y obtenga una ranura en cola de milano con cursor, botón y topes. Se imprime de una vez, montado; el cursor corre y hace clic.',
    'h1' => 'Un fidget deslizante recortado en su placa, impreso de una vez',
    'intro' => [
        'La herramienta toma un modelo plano, un logo, una placa con nombre, un colgante, cualquier cosa de al menos 6 mm de grosor, y recorta una ranura en cola de milano a lo largo de su lado más largo (o del que elija), con un margen en los extremos para que el cursor no se salga. Dentro va un cursor: una barra en cola de milano 0,3 mm menor por todos los lados, con un botón de Ø 10 mm encima y un hoyuelo debajo que hace clic en las bolas de los topes del fondo de la ranura. Todo se imprime de una vez, montado, sin soportes.',
        'Usted ajusta el ancho y la profundidad de la ranura, el margen, el desplazamiento transversal, la longitud del cursor, el número de topes (0 a 5) y la holgura; el botón se puede quitar y el cursor queda a ras de la superficie. El modelo tarda unos segundos y se abre en la vista previa con la placa y el cursor en colores distintos, las medidas y el precio de impresión; lo descarga gratis o nos encarga la impresión.',
    ],
    'steps' => [
        ['name' => 'Suba una placa plana', 'text' => 'Un STL o 3MF de un logo, una placa con nombre u otra placa de al menos 6 mm de grosor. También vale la salida de nuestros generadores.'],
        ['name' => 'Ajuste la ranura', 'text' => 'Su dirección, ancho y profundidad, el margen desde los extremos y el desplazamiento transversal, para que no pase por el texto.'],
        ['name' => 'Ajuste el cursor', 'text' => 'Su longitud, el número de topes, la holgura y si lleva botón.'],
        ['name' => 'Cree el modelo', 'text' => 'Pulse «Hacer el fidget deslizante». En unos segundos se abre con la placa y el cursor, las medidas y el precio de impresión.'],
    ],
    'faq' => [
        ['q' => '¿Por qué cola de milano y no una ranura simple?', 'a' => 'Los flancos inclinados 15 grados sujetan el cursor en la ranura; no puede salirse hacia arriba. La ranura está cerrada en ambos extremos, así que tampoco se sale de lado.'],
        ['q' => '¿Cómo se libera el cursor tras la impresión?', 'a' => 'Los 0,3 mm de holgura por todos los lados se cubren con hilos finos al imprimir. Fuera de la cama, empuje el cursor de lado con el dedo; el primer movimiento rompe los hilos.'],
        ['q' => '¿Cómo funcionan los topes?', 'a' => 'En el fondo de la ranura hay bolas de Ø 2 mm donde el cursor puede detenerse; el cursor tiene un hoyuelo debajo. Al moverse sube un cuarto de milímetro sobre la bola y cae tras ella: un clic.'],
        ['q' => '¿Qué grosor debe tener la placa?', 'a' => 'Al menos la profundidad de la ranura más 2 mm de fondo, es decir 6 mm con la profundidad por defecto de 4 mm. A lo ancho, el cursor necesita el ancho de la ranura más 2 mm a cada lado.'],
        ['q' => '¿Con qué material y ajustes imprimir?', 'a' => 'PLA o PETG, capas de 0,2 mm, sin soportes, tumbado. La holgura de 0,3 mm cuenta con la precisión normal de una impresora; si va apretado, elija 0,4.'],
        ['q' => '¿Cuánto cuesta?', 'a' => 'Crear el modelo y descargarlo es gratis. Solo paga la impresión si nos la encarga.'],
    ],
    'examples' => [],
];
