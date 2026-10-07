<?php

return [
    'title' => 'Adorno de Navidad con su propia imagen o nombre',
    'description' => 'Suba una imagen o escriba un nombre y obtendrá un adorno de Navidad en colores de filamento, con ojal para la cinta. Lo imprimimos o lo descarga gratis.',
    'h1' => 'Adorno para el árbol de Navidad con una imagen, una foto o un nombre',
    'intro' => [
        'La herramienta convierte una imagen en un adorno de Navidad plano con ojal para la cinta. Suba un PNG, JPG, WebP o SVG, elija un motivo de nuestra biblioteca o escriba un nombre en lugar de la imagen. El fondo se quita solo y la imagen se reduce a entre 1 y 8 colores. Cada color se asigna al filamento más parecido que tenemos de verdad en stock en nuestra granja de impresión. El adorno sigue el contorno de la imagen, o es un círculo o una estrella, de 40 a 150 mm de ancho y de 2 a 5 mm de grosor.',
        'Los colores van uno sobre otro, cada uno de 0,4 a 1,2 mm más alto que el de debajo. Así cada capa es de un solo filamento y cualquier impresora imprime el adorno: basta cambiar el filamento a las alturas que incluye el proyecto descargable. Nuestra granja de impresión imprime hoy un color, o dos: la base y un color encima. Los diseños con más colores se descargan gratis para su impresora.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen o escriba un nombre', 'text' => 'Suba una imagen, elíjala de la biblioteca de unos 137 motivos (8 de ellos en color) o reutilice una que subió antes. El nombre o texto corto admite una o dos líneas, en una de cuatro tipografías.'],
        ['name' => 'Elija forma, tamaño y ojal', 'text' => 'El adorno sigue la imagen, o es un círculo o una estrella. Ajuste el ancho, el grosor y un marco de hasta 3 mm alrededor de la imagen. El ojal, con agujero de 3 a 6 mm, se mueve a cualquier punto del contorno con un control deslizante o arrastrándolo en la vista previa; un botón lo devuelve arriba.'],
        ['name' => 'Ajuste los colores', 'text' => 'Elija el número de colores y asigne a cualquiera otro filamento en la ventana de colores. Puede cambiar qué color va sobre cuál o unir dos colores en uno. La vista previa en 3D muestra el adorno en los colores de los filamentos y sus medidas en milímetros.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Junto a la vista previa aparece un precio orientativo; el precio exacto y el tiempo de impresión, un paso más allá. La impresión se paga con crédito prepagado. O descargue gratis y sin registro un STL, un ZIP con un STL por color o un proyecto para el laminador.'],
    ],
    'faq' => [
        ['q' => '¿Qué imagen sirve para un adorno?', 'a' => 'Lo mejor es un dibujo con pocos colores planos, como un hombrecito de jengibre o un árbol. De una foto la herramienta toma los colores principales; el contraste, el brillo y la saturación se ajustan con controles deslizantes. Para conservar la foto entera, desactive la eliminación del fondo.'],
        ['q' => '¿Puede una impresora de una sola boquilla imprimir un adorno en color?', 'a' => 'Sí. Los colores van uno sobre otro, así que solo se cambia el filamento a una altura dada; el proyecto para OrcaSlicer, Bambu Studio y PrusaSlicer incluye esos cambios. Las opciones «Colores a ras de la superficie» y «Reborde en su propio color» requieren una impresora que cambie el filamento sola (AMS, MMU, ACE).'],
        ['q' => '¿Cuántos colores imprimen ustedes?', 'a' => 'Nuestra granja imprime hoy un color, o dos: la base y un color encima, que usted elige al hacer el pedido. Un adorno con más colores se descarga gratis y se imprime en su propia impresora.'],
        ['q' => '¿Con qué material se imprime el adorno?', 'a' => 'Recomendamos PLA. Un adorno de PLA es ligero y se imprime en plano, con la cara hacia arriba y sin soportes. Elija PETG solo si el adorno va a colgar en un lugar caliente.'],
        ['q' => '¿De qué avisa la herramienta?', 'a' => 'De las líneas más finas que la boquilla. También avisa cuando ha unido trozos sueltos de la imagen con un pequeño puente para que no se separen, y cuando la imagen tiene menos colores distintos de los que usted pidió.'],
        ['q' => '¿Cómo se paga y cómo se recibe el adorno?', 'a' => 'Se paga con crédito prepagado que usted recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a domicilio en toda la UE.'],
    ],
    'examples' => [
        'Hombrecito de jengibre de 80 mm de ancho: base marrón, glaseado blanco y botones rojos.',
        'Árbol de Navidad decorado de 90 mm de ancho, en cinco colores.',
        'Bola de Navidad roja de 70 mm con una franja blanca, sin marco.',
    ],
];
