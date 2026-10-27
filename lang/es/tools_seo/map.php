<?php

// La página para la búsqueda «mapa 3d de una ciudad para imprimir» (docs/AP.md §1, §5). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Mapa 3D de ciudad para imprimir desde OpenStreetMap, gratis',
    'heading' => 'Mapa 3D de cualquier ciudad a partir de su nombre',
    'description' => 'Escriba una ciudad o un lugar y en un minuto tiene un mapa 3D para imprimir: edificios con alturas, calles y agua de OpenStreetMap, o un relieve. Gratis.',
    'h1' => 'Qué contiene el mapa, cómo se imprime en tres colores y dónde están los límites',
    'intro' => [
        'Escriba una ciudad, un pueblo, un barrio o un monumento, elija la extensión y recibe el mapa como una placa fina para imprimir. Una ciudad tiene edificios con sus alturas reales de OpenStreetMap (donde falta la altura, vale la que indique), calles por tipo desde autopistas hasta senderos, ríos y estanques hundidos en la base, vías de tren y parques. Un paisaje es un relieve del terreno a partir de los datos de elevación de Mapzen, con unos 30 m de resolución y exageración ajustable, con agua y carreteras en la superficie y pueblos como bloques bajos. Ninguna inteligencia artificial adivina nada: los datos son exactos, su fecha figura en el mapa y el modelo está listo en menos de un minuto. La extensión de una ciudad es de 500 m, 1 km o 2 km; la de un paisaje, de 2 a 20 km; la placa mide de 80 a 250 mm según la cama, la escala se calcula y se muestra, y el marco lleva el nombre en relieve. En el estilo Miniatura los edificios tienen esquinas redondeadas y son la mitad más altos; el estilo Liso conserva aristas exactas y alturas reales. Antes de construir ve una vista previa de la zona desde arriba.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): la Ciudad Vieja de Praga, un cuadrado de 500 m en una placa de 150 mm (escala aproximada 1 : 3.300), estilo Liso, pesa 85 g, se imprime en 3 h 42 min y cuesta 240 CZK (unos 10 EUR) con nosotros en un solo color, sin envío. El mapa se imprime en plano y sin soportes. La base, las calles y los edificios son tres colores uno sobre otro: en nuestra granja el filamento cambia solo a dos alturas, el proyecto 3MF para OrcaSlicer y PrusaSlicer lleva los cambios y el STL es un solo cuerpo para imprimir en un color. Sin cuenta puede hacer tres mapas al día; con cuenta, veinte; la vista previa de la zona no cuenta.',
    ],
    'steps' => [
        ['name' => 'Indique el lugar', 'text' => 'El nombre de una ciudad, un barrio, una calle o un monumento; o coordenadas. Elija el lugar que quiere decir y escoja ciudad o paisaje y la extensión: ciudad de 500 m a 2 km, paisaje de 2 a 20 km.'],
        ['name' => 'Mire la vista previa y ajuste el mapa', 'text' => 'La vista previa de la zona muestra edificios, carreteras, agua y zonas verdes tal como se imprimirán. Elija el estilo, el tamaño de la placa, el marco con el nombre; en la ciudad, la altura de los edificios sin datos y las calles en relieve o hundidas; en el paisaje, la exageración.'],
        ['name' => 'Elija los colores', 'text' => 'La base, las calles y los edificios, o la base y el terreno, cada uno con su color. Los colores quedan uno sobre otro por altura, así que cualquier impresora con cambio de filamento puede con ellos.'],
        ['name' => 'Cree el mapa e imprima o descargue', 'text' => 'El modelo se construye en el servidor; una ciudad de 1 km, en medio minuto. Después ve las medidas; el precio exacto y la impresión con nosotros están un paso más allá, y el STL y el proyecto 3MF con los cambios se descargan gratis.'],
    ],
    'faq' => [
        ['q' => '¿De dónde salen los datos y qué exactitud tienen?', 'a' => 'Edificios, calles, agua y vías de OpenStreetMap, el mapa que mantiene gente de todo el mundo; en las ciudades es muy completo, con alturas de edificios donde alguien las registró (si no, vale la altura que indique). Alturas del terreno de los datos de Mapzen (AWS Open Data), con unos 30 m de resolución. La fecha de los datos figura en el mapa.'],
        ['q' => '¿Qué tamaño de ciudad cabe?', 'a' => 'Un cuadrado de 500 m, 1 km o 2 km alrededor del centro. En una placa de 150 mm, un kilómetro queda a escala aproximada 1 : 7.000 y un edificio de 20 m mide 3 mm de alto. Una ciudad mayor en una sola placa tendría edificios más pequeños que la boquilla; para una zona grande elija paisaje.'],
        ['q' => '¿Cómo se imprimen los tres colores?', 'a' => 'La base con el primer color, las calles desde su borde superior con el segundo, los edificios encima con el tercero: dos cambios de filamento por altura. Nuestra granja los hace sola; el proyecto 3MF para OrcaSlicer y PrusaSlicer lleva los cambios. En un solo color también funciona: el STL es un solo cuerpo.'],
        ['q' => '¿Puedo subir una imagen de un mapa?', 'a' => 'No. De una imagen solo se podría adivinar; del nombre del lugar obtiene edificios y calles exactos. Si no encontramos el lugar, indique coordenadas, por ejemplo de Google Maps.'],
        ['q' => '¿Puedo vender el mapa?', 'a' => 'Sí. Los datos de OpenStreetMap están bajo licencia ODbL: puede imprimir, regalar y vender el modelo, solo mencione «© colaboradores de OpenStreetMap». Los datos de elevación de Mapzen son de uso libre.'],
        ['q' => '¿Cuántos mapas puedo hacer?', 'a' => 'Los servicios de mapas son públicos, por eso sin cuenta tres mapas al día y con cuenta veinte. La vista previa de la zona no cuenta.'],
    ],
    'examples' => [
        'Ciudad de 500 m en placa de 150 mm, estilo Liso: edificios por altura, calles principales, un arroyo con estanque, vía de tren y marco con el nombre; 85 g, 3 h 42 min.',
        'El mismo lugar en estilo Miniatura: edificios redondeados la mitad más altos, calles más anchas, parque hundido en la base.',
        'Paisaje de 5 km con una colina en el centro, exageración 2×, en placa de 150 mm.',
    ],
];
