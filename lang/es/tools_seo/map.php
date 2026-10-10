<?php

return [
    'title' => 'Mapa 3D de una ciudad o un paisaje para imprimir',
    'description' => 'Indique una ciudad o un lugar y obtenga un mapa 3D para imprimir: edificios con sus alturas, calles y agua de OpenStreetMap, o el relieve de un paisaje. Gratis.',
    'h1' => 'Mapa 3D de una ciudad o un paisaje: del nombre de un lugar al modelo imprimible',
    'intro' => [
        'Escriba una ciudad, un pueblo, un barrio o un monumento, elija el alcance y obtendrá el mapa como una placa fina para imprimir. La ciudad tiene edificios con sus alturas reales de OpenStreetMap, calles según su tipo desde autopistas hasta senderos, ríos y estanques hundidos en la base, vías de tren y parques. El paisaje es el relieve del terreno a partir de los datos de altura de Mapzen con exageración, con agua y carreteras en la superficie y los pueblos como bloques bajos. Ninguna IA adivina nada: los datos son exactos y el modelo está listo en un minuto, sin cuenta y gratis.',
        'El mapa se imprime en plano y sin soportes. La base, las calles y los edificios son tres colores superpuestos: en nuestra granja el filamento se cambia solo a dos alturas, y el proyecto para su impresora lleva los cambios. La placa mide de 80 a 250 mm según su cama, la escala se calcula y se muestra, y en el marco va el nombre en relieve. En el estilo Miniatura los edificios tienen esquinas redondeadas y son un 50 % más altos, las calles más anchas y el marco redondeado; Limpio mantiene aristas exactas y alturas reales. Antes de construir ve la zona desde arriba, así sabe qué se imprimirá.',
    ],
    'steps' => [
        ['name' => 'Indique el lugar', 'text' => 'Una ciudad, un barrio, una calle o un monumento; o coordenadas. Elija el lugar al que se refiere, luego ciudad o paisaje y el alcance: ciudad de 500 m a 2 km, paisaje de 2 a 20 km.'],
        ['name' => 'Mire la zona y ajuste el mapa', 'text' => 'La vista previa muestra los edificios, calles, agua y zonas verdes tal como se imprimirán. Elija el estilo, el tamaño de la placa, el marco con su nombre, en la ciudad la altura de los edificios sin dato y calles en relieve o hundidas, en el paisaje la exageración.'],
        ['name' => 'Elija los colores', 'text' => 'La base, las calles y los edificios, o la base y el terreno, cada uno en su color. Los colores van superpuestos por altura, así que cualquier impresora con cambio de filamento los imprime.'],
        ['name' => 'Cree el mapa e imprima o descargue', 'text' => 'El modelo se construye en el servidor; una ciudad de 1 km, en medio minuto. Luego ve las medidas y un precio orientativo; la impresión con nosotros está a un paso, y el STL y el proyecto 3MF con los cambios se descargan gratis.'],
    ],
    'faq' => [
        ['q' => '¿De dónde salen los datos y qué precisión tienen?', 'a' => 'Edificios, calles, agua y vías de OpenStreetMap, el mapa que mantiene gente de todo el mundo; en las ciudades es muy completo, con las alturas de los edificios donde alguien las anotó (si no, vale la altura que usted fije). Alturas del terreno de los datos de Mapzen (AWS Open Data) con unos 30 m de resolución. El mapa indica la fecha de sus datos.'],
        ['q' => '¿Qué tamaño de ciudad cabe?', 'a' => 'Un cuadrado de 500 m, 1 km o 2 km alrededor del centro. En una placa de 150 mm un kilómetro está a unos 1 : 7 000 y un edificio de 20 m mide 3 mm de alto. Una ciudad mayor en una sola placa tendría edificios por debajo del tamaño de la boquilla; para una zona grande elija paisaje.'],
        ['q' => '¿Cómo se imprimen los tres colores?', 'a' => 'La base en el primer color, las calles desde su cara superior en el segundo, los edificios encima en el tercero: dos cambios de filamento por altura. Nuestra granja los hace sola; el proyecto 3MF para OrcaSlicer y PrusaSlicer los lleva. Con un solo color también funciona: el STL es un solo cuerpo.'],
        ['q' => '¿Puedo subir una imagen de un mapa?', 'a' => 'No. A partir de una imagen solo se podría adivinar; con el nombre del lugar obtiene los edificios y calles exactos. Si no encontramos el lugar, escriba sus coordenadas, por ejemplo de Google Maps.'],
        ['q' => '¿Puedo vender el mapa?', 'a' => 'Sí. Los datos de OpenStreetMap están bajo la licencia ODbL: puede imprimir, regalar y vender el modelo, solo indique «© colaboradores de OpenStreetMap». Los datos de altura de Mapzen son de libre uso.'],
        ['q' => '¿Cuántos mapas puedo hacer?', 'a' => 'Los servicios de mapas son públicos, por eso tres mapas al día sin cuenta y veinte con cuenta. La vista previa de la zona no cuenta.'],
    ],
    'examples' => [
        'Ciudad de 500 m en una placa de 150 mm, estilo Limpio: edificios según sus alturas, las calles principales, un arroyo con estanque, una vía de tren y el marco con el nombre.',
        'El mismo lugar en estilo Miniatura: edificios redondeados un 50 % más altos, calles más anchas, el parque hundido en la base.',
        'Paisaje de 5 km con una colina en el centro, exageración 2×, en una placa de 150 mm.',
    ],
];
