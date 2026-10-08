<?php

return [
    'title' => 'Casco o armadura a medida a partir de su propio modelo',
    'description' => 'Un modelo de casco, máscara o armadura escalado a su contorno de cabeza o pecho, vaciado, con visores y ranuras de correa, dividido para la cama. Gratis.',
    'h1' => 'Un modelo de casco o armadura escalado a su medida, hueco, con visores',
    'intro' => [
        'La herramienta toma un modelo de casco, máscara, protector o pieza de armadura (de Printables o MakerWorld, por ejemplo) y lo prepara para llevarlo puesto. Usted indica una medida, el contorno de cabeza, pecho, brazo, muñeca o muslo, y la herramienta mide el modelo en su nivel más ancho y lo escala para que el contorno interior coincida con la medida más la holgura. Un modelo macizo se vacía con una pared de 2 a 4 mm y se abre por abajo; un modelo ya hueco se deja como está.',
        'En la pared se abren hasta cuatro visores, rectangulares u ovalados, en el frente, la espalda, los lados o arriba y desplazados según convenga; las ranuras de correa de 25 mm en ambos lados son opcionales. Un modelo mayor que la cama se divide en piezas con pasadores o colas de milano y números grabados. Todo tarda unos segundos y es gratis; el modelo del casco lo trae usted, no suministramos personajes de películas.',
    ],
    'steps' => [
        ['name' => 'Suba un modelo', 'text' => 'Un STL o 3MF de un casco, una máscara o una armadura que quiera imprimir a su talla.'],
        ['name' => 'Indique la medida', 'text' => 'Elija qué rodea el modelo (cabeza, pecho, brazo…) e indique el contorno en centímetros; la herramienta muestra enseguida cuánto lo escala y qué tamaño tendrá.'],
        ['name' => 'Visores, correa, división', 'text' => 'Añada visores (lado, forma, tamaño, desplazamiento) y, si quiere, ranuras de correa. Elija la cama de su impresora y las uniones de las piezas.'],
        ['name' => 'Cree el modelo', 'text' => 'Pulse «Hacer a medida». En unos segundos se abre con sus medidas, el contorno interior, las piezas y el precio de impresión.'],
    ],
    'faq' => [
        ['q' => '¿Cómo se escala el modelo?', 'a' => 'Por igual en todas las direcciones. La herramienta busca la sección horizontal más ancha del modelo, mide su contorno interior (en un modelo macizo resta la pared) y elige la escala para que el contorno coincida con su medida más la holgura, 10 mm por defecto.'],
        ['q' => '¿Cómo me mido la cabeza?', 'a' => 'Con una cinta métrica en horizontal alrededor de la cabeza, por la frente y el punto más ancho de atrás, ajustada pero sin apretar. Un adulto suele tener de 54 a 60 cm. Para el pecho, mida por el punto más ancho al inspirar.'],
        ['q' => '¿Y si el modelo ya es hueco?', 'a' => 'La herramienta lo ve en la sección y lo deja como está; entonces no cambia la pared. Un modelo macizo se vacía y se abre por abajo para que entre la cabeza.'],
        ['q' => '¿Dónde van los visores?', 'a' => 'En el frente, la espalda, la izquierda, la derecha o arriba, desplazados hacia los lados y arriba o abajo desde la línea de los ojos por defecto (60 % de la altura del modelo). Un visor atraviesa solo la pared más cercana.'],
        ['q' => '¿Cabe un casco en la impresora?', 'a' => 'Un casco para una cabeza de 56 cm mide unos 19 cm de ancho y cabe en una cama de 220 o 250 mm. Las piezas mayores se dividen con pasadores de Ø 6 o colas de milano y se pegan tras la impresión.'],
        ['q' => '¿Suministran modelos de cascos?', 'a' => 'No, el modelo lo trae usted; los personajes de películas están protegidos. La herramienta adapta cualquier modelo propio o de libre difusión.'],
    ],
    'examples' => [],
];
