<?php

return [
    'title' => 'Posavasos con su propia imagen o foto para imprimir en 3D',
    'description' => 'Un posavasos redondo, cuadrado o hexagonal con una foto o un dibujo en colores de filamento. Lo imprimimos o descarga el modelo gratis para su impresora.',
    'h1' => 'Posavasos con su propia foto, dibujo o nombre',
    'intro' => [
        'La herramienta crea un posavasos con su imagen: un círculo, un cuadrado o un hexágono de 80 a 120 mm de ancho y de 3 a 6 mm de grosor. Suba un PNG, JPG, WebP o SVG, elija un motivo de la biblioteca o escriba un nombre. Una foto entera llena el posavasos hasta el marco; un motivo recortado queda en el centro. La imagen se reduce a entre 1 y 8 colores de filamentos que tenemos de verdad en stock en nuestra granja de impresión.',
        'Los colores sobresalen de la base en escalones, de 0,4 mm cada uno por defecto, así que un vaso se apoya en el posavasos sin problema. Puede añadir ranuras por debajo para que el posavasos no resbale en una mesa mojada. La parte superior totalmente plana se consigue con la opción «Colores a ras de la superficie», pero esta requiere una impresora que cambie el filamento sola. Nuestra granja imprime hoy uno o dos colores; un diseño con más colores se descarga gratis para su impresora.',
    ],
    'steps' => [
        ['name' => 'Suba una foto o un dibujo', 'text' => 'Suba una imagen, elíjala de la biblioteca o reutilice una que subió antes. Para una foto entera desactive la eliminación del fondo; si la deja activada, solo queda el motivo en el centro.'],
        ['name' => 'Elija la forma y las medidas', 'text' => 'Un círculo, un cuadrado o un hexágono. Ajuste el ancho, el grosor y un marco de hasta 4 mm alrededor de la imagen, y si quiere un borde biselado y ranuras por debajo.'],
        ['name' => 'Afine los colores', 'text' => 'Elija el número de colores y asigne a cada uno un filamento en la ventana de colores. Fije cuánto sube cada color (de 0,2 a 0,8 mm); también puede unir dos colores en uno.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Junto a la vista previa aparecen las medidas y un precio orientativo; el precio exacto y el tiempo de impresión, un paso más allá. Para un juego basta indicar la cantidad. El modelo y el proyecto para el laminador se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Queda el vaso recto sobre el posavasos?', 'a' => 'Sí. Por defecto los colores van escalonados de 0,4 mm en 0,4 mm y un vaso se apoya en ellos sin problema. Para una parte superior totalmente plana use la opción «Colores a ras de la superficie», que solo imprime una impresora que cambie el filamento sola (AMS, MMU, ACE).'],
        ['q' => '¿Con qué material se imprime el posavasos y aguanta una taza caliente?', 'a' => 'Recomendamos PLA: aguanta bebidas frías y calientes, pero se ablanda hacia los 55 °C. Para una taza recién sacada del fuego elija PETG. El posavasos se imprime en plano y sin soportes.'],
        ['q' => '¿Para qué sirven las ranuras por debajo?', 'a' => 'Evitan que el posavasos resbale en una mesa mojada. Las ranuras son opcionales.'],
        ['q' => '¿Cuántos colores imprimen ustedes?', 'a' => 'Hoy uno, o dos: la base y un color encima, que usted elige al hacer el pedido. Un diseño con más colores se descarga gratis; los colores van uno sobre otro, así que cualquier impresora lo imprime cambiando el filamento a las alturas que incluye el proyecto.'],
        ['q' => '¿Cómo pido un juego de posavasos?', 'a' => 'Un juego es el mismo posavasos en varias unidades: basta indicar la cantidad.'],
        ['q' => '¿Cómo se paga y cómo se recibe el posavasos?', 'a' => 'Se paga con crédito prepagado que usted recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a domicilio en toda la UE.'],
    ],
    'examples' => [
        'Posavasos redondo de 100 mm de ancho con un muñeco de nieve.',
        'Posavasos hexagonal de 95 mm con una huella y ranuras por debajo.',
        'Posavasos cuadrado de 100 mm con un árbol de Navidad.',
    ],
];
