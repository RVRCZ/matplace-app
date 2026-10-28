<?php

// La página para la búsqueda «posavasos personalizado impresión 3d» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Posavasos con foto o dibujo para imprimir en 3D, gratis',
    'heading' => 'Posavasos con su propia foto o dibujo para imprimir en 3D',
    'description' => 'Posavasos redondo, cuadrado o hexagonal con una foto, un dibujo o un nombre en colores de filamento, con ranuras antideslizantes. Impresión o descarga.',
    'h1' => 'Cómo nace el posavasos, cuántos colores admite y cuánto cuesta',
    'intro' => [
        'La herramienta crea un posavasos con su imagen: un círculo, un cuadrado o un hexágono de 80 a 120 mm de ancho y de 3 a 6 mm de grosor. Suba un PNG, JPG, WebP o SVG, elija un motivo de la biblioteca o escriba un nombre. Una foto entera llena el posavasos hasta el borde; un motivo recortado queda en el centro. La imagen se reduce a 1 a 8 colores de filamentos que tenemos en la granja, y los colores sobresalen de la base por escalones, de 0,4 mm por defecto, así que un vaso se apoya sin problema. Añada ranuras por debajo para que el posavasos no resbale en una mesa mojada, un marco de hasta 4 mm alrededor de la imagen o un borde biselado. Una parte superior del todo plana la da la opción «Colores a ras de la superficie», que necesita una impresora con cambio de filamento.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): un posavasos redondo de 100 mm con el muñeco de nieve de la biblioteca, de 4 mm de grosor y con ranuras por debajo, pesa 20,4 g, se imprime en 40 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, el pedido mínimo, sin envío (cálculo /c/xqbu3qsmyqm0); un juego de cuatro iguales cuesta 170 CZK (cálculo /c/57k7ndo2ibxo), porque la preparación se paga una vez. Nuestra granja imprime hasta cuatro colores en una impresión, la base y tres más; confirma la bobina de cada color al encargar. El modelo y el proyecto para el slicer con los cambios de color se descargan gratis, sin registro.',
    ],
    'steps' => [
        ['name' => 'Suba una foto o un dibujo', 'text' => 'Suba una imagen, elija una de la biblioteca o reutilice una subida antes. Para una foto entera desactive la eliminación del fondo; si la deja activada, solo queda el motivo del centro.'],
        ['name' => 'Elija la forma y las medidas', 'text' => 'Un círculo, un cuadrado o un hexágono. Ajuste el ancho, el grosor y un marco de hasta 4 mm alrededor de la imagen, y si quiere un borde biselado y ranuras por debajo.'],
        ['name' => 'Afine los colores', 'text' => 'Elija el número de colores y asigne a cada uno un filamento en la ventana de colores. Fije cuánto sobresale cada color (0,2 a 0,8 mm); también puede fusionar dos colores en uno.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el cálculo tras «Continuar» muestra el precio y el tiempo exactos. Para un juego basta con fijar la cantidad. El modelo y el proyecto para el slicer se descargan gratis, sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Se apoyará el vaso recto en el posavasos?', 'a' => 'Sí. Por defecto los colores van escalonados de 0,4 mm y un vaso se apoya en ellos sin problema. Para una superficie del todo plana use la opción «Colores a ras de la superficie», que solo imprime una impresora que cambia el filamento sola (AMS, MMU, ACE).'],
        ['q' => '¿De qué se imprime el posavasos y aguanta una taza caliente?', 'a' => 'Recomendamos PLA: aguanta bebidas frías y templadas, pero se ablanda hacia los 55 °C. Para una taza recién sacada del fuego elija PETG. El posavasos se imprime plano y sin soportes.'],
        ['q' => '¿Qué foto sale bien en un posavasos?', 'a' => 'Mejor un dibujo, un logotipo o una foto con pocos colores planos; un retrato reducido a 4 a 8 colores sale como un cartel. Para una foto entera desactive la eliminación del fondo; si no, solo queda el motivo del centro. Los detalles por debajo de 0,8 mm se funden.'],
        ['q' => '¿Para qué sirven las ranuras de abajo?', 'a' => 'Evitan que el posavasos resbale en una mesa mojada y que una gota debajo haga ventosa. Son opcionales y no añaden tiempo de impresión.'],
        ['q' => '¿Cómo encargo un juego de posavasos?', 'a' => 'Un juego es el mismo posavasos en varias unidades: basta con fijar la cantidad, y el precio por pieza baja porque la preparación se paga una vez. Posavasos con motivos distintos son diseños distintos, cada uno se encarga aparte.'],
        ['q' => '¿Cuántos colores imprimen y cómo lo imprimo en casa?', 'a' => 'Con nosotros hasta cuatro en una impresión: la base y tres colores encima; confirma la bobina de cada uno al encargar. En casa cualquier impresora imprime un diseño con más colores: los colores van uno sobre otro y el proyecto para OrcaSlicer, Bambu Studio y PrusaSlicer lleva los cambios de filamento a las alturas correctas.'],
    ],
    'examples' => [
        'Posavasos redondo de 100 mm con un muñeco de nieve y ranuras por debajo: 20,4 g, 40 minutos, 99 CZK; juego de cuatro, 170 CZK.',
        'Posavasos hexagonal de 95 mm con una huella, marco de 2 mm, cuatro colores.',
        'Posavasos cuadrado de 100 mm con una foto en toda la cara, 6 colores, borde biselado.',
    ],
];
