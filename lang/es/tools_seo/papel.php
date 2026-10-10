<?php

return [
    'title' => 'Papel picado a partir de una foto: retrato o silueta en 3D',
    'description' => 'Suba la foto de una cara y obtenga un retrato en dos colores con marco calado, o una silueta calada en un panel fino. Gratis, impreso por nosotros o descargado.',
    'h1' => 'Papel picado: un retrato a partir de una foto o un panel calado con su imagen',
    'intro' => [
        'El papel picado es la guirnalda mexicana de papel de colores en la que se calan figuras y adornos; se cuelga en fiestas, bodas y, sobre todo, en el Día de Muertos. La herramienta hace lo mismo en plástico, de dos maneras. La silueta es un panel fino con la imagen calada como una ventana. El retrato es la foto de una cara convertida en un dibujo de dos colores que va sobre una base sólida, así que nada se cae ni se rompe. Puede subir una foto, un dibujo o una silueta (PNG, JPG, WebP, SVG), o elegir un motivo de la biblioteca.',
        'En el retrato todo el panel es una base clara y sobre ella se imprimen, en un segundo color oscuro, el marco y las partes oscuras de la cara: pelo, ojos, labios, sombras. La herramienta sabe separar a la persona del fondo; el fondo queda claro, o bien oscuro y sembrado de flores por las que asoma la base. Con los deslizadores decide cuánto queda oscuro, lo fino que es el dibujo y cuánto se recorta bajo los hombros. El retrato se mueve, se agranda y se gira dentro de su ventana en la misma vista previa. Se imprime en plano, con un solo cambio de filamento a la altura de la base. Alrededor de la ventana va una cenefa calada con uno de siete motivos: flores populares, flores, rombos, puntos, corazones, hojas o estrellas. Usted fija su ancho y la densidad del motivo. Las ondas con orificio pueden ir solo abajo o en todo el contorno, y en las esquinas superiores hay dos orificios para el hilo. En la silueta la herramienta sujeta con uniones finas lo que quedaría en el aire, como un rostro sobre un fondo calado. El panel mide de 80 a 250 mm de ancho y de alto.',
    ],
    'steps' => [
        ['name' => 'Elija silueta o retrato y suba una imagen', 'text' => 'Al subir una foto la herramienta pasa a retrato; con un dibujo o un motivo de la biblioteca, a silueta. Puede cambiar la elección cuando quiera.'],
        ['name' => 'Ajuste el dibujo', 'text' => '«Luz / sombra» mueve el límite entre oscuro y claro; «Detalle del retrato» decide lo finos que quedan los rasgos. Junto a la foto original ve el retrato tal como se imprimirá.'],
        ['name' => 'Elija el marco, el tamaño y la colocación', 'text' => 'El motivo y el ancho de la cenefa, las ondas, los orificios para el hilo. Arrastre el marco de la vista previa para mover el retrato en su ventana; una esquina cambia el tamaño y el tirador lo gira.'],
        ['name' => 'Escoja los colores y pida la impresión o descargue', 'text' => 'Los colores de la base y del marco salen de las bobinas que tenemos en la granja. La impresión con nosotros está a un paso; el proyecto 3MF de dos colores y el STL se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Qué foto da un buen retrato?', 'a' => 'Una cara nítida y bien iluminada, de frente o de medio perfil. Un fondo tranquilo ayuda, pero no hace falta: separamos a la persona del fondo. Lo mejor es pelo oscuro sobre piel clara; con pelo claro elija el fondo oscuro alrededor de la persona y la cabeza tendrá un contorno claro.'],
        ['q' => '¿Por qué el retrato va sobre una base y no calado?', 'a' => 'Una cara está llena de islas pequeñas: pupilas, el brillo de las gafas, mechones de pelo. Caladas, nada las sujetaría. Sobre una base cada detalle se sostiene y el dibujo puede ser mucho más fino.'],
        ['q' => '¿Cómo se imprimen los dos colores?', 'a' => 'La base se imprime en el primer color y desde su parte alta la impresión sigue en el segundo. Es un cambio de filamento a una altura, 2 mm por defecto. Nuestra granja lo hace sola. El proyecto 3MF para OrcaSlicer y PrusaSlicer lleva el cambio, así que basta una impresora de una boquilla. De lado se ve el canto claro de la base. También vale un solo color: el STL es un solo cuerpo y el dibujo sobresale como relieve.'],
        ['q' => '¿Por qué hay líneas verticales finas en la silueta?', 'a' => 'Son las uniones. Un trozo de papel que no sujetaría nada al calar lo que lo rodea queda unido con ellas al borde del hueco. Las uniones miden de 0,8 a 2,4 mm de ancho. El retrato no las necesita.'],
        ['q' => '¿Qué grosor debe tener el panel?', 'a' => 'La silueta, de 0,8 a 2 mm; por defecto, 1,2 mm. El retrato tiene una base de 1 a 3 mm y sobre ella un dibujo de 0,3 a 1,2 mm; los 2 + 0,6 mm por defecto dan una placa firme que no se dobla.'],
        ['q' => '¿Cómo pago y cómo recibo el panel?', 'a' => 'Se paga con crédito prepago que se recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [
        'Retrato de 190 × 190 mm con la cara dibujada de la biblioteca: base crema, marco azul oscuro con flores populares y ondas en todo el contorno.',
        'Silueta de 150 × 213 mm con la calavera de azúcar de la biblioteca y cenefa de flores.',
        'Marco de 160 × 120 mm con flores populares y la ventana vacía: una placa para su propio rótulo o una foto pegada.',
    ],
];
