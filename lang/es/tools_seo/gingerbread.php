<?php

// La página para la búsqueda «adorno navideño con nombre» (docs/AP.md §6). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Adorno navideño con nombre: galleta de jengibre en 3D',
    'heading' => 'Adorno navideño con nombre: galleta de jengibre para el árbol',
    'description' => 'Escriba un nombre y elija muñeco, corazón, estrella o árbol con glaseado blanco. Imprimimos la galleta en dos colores, o descarga el modelo gratis.',
    'h1' => 'Cómo nace la galleta con nombre, cuánto cuesta y cuánto tarda en imprimirse',
    'intro' => [
        'La herramienta crea un adorno que parece una galleta de jengibre con glaseado de azúcar: un muñeco, un corazón, una estrella o un árbol. Escribe un nombre de hasta 16 caracteres, elige una tipografía y el glaseado, ondulado o en línea recta. El ancho se ajusta de 50 a 150 mm y el grosor de 2 a 6 mm; la argolla para la cinta está arriba y se mueve con el deslizador o arrastrando en la vista previa. La galleta tiene dos colores uno sobre otro: una base marrón y el glaseado blanco con el nombre, que sobresale de 0,4 a 1,2 mm. La impresión se detiene una vez para cambiar el filamento, así que cualquier impresora puede hacerla; en nuestra granja se imprime de una vez y los dos colores se eligen al hacer el pedido. Marrón y blanco vienen predefinidos, pero la galleta puede ser igualmente roja con glaseado dorado.',
        'Un ejemplo de la calculadora en producción (27 de octubre de 2026, PLA): un muñeco de 90 mm de ancho con el nombre Ela pesa 10,8 g, se imprime en 20 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo (cálculo /c/krswdwunexsg); seis unidades de la misma galleta cuestan 140 CZK, es decir, 23 CZK cada una, porque la preparación del pedido se paga una vez (/c/qkmqrfmbzaln). La impresión es un adorno, no es comestible. Se imprime en plano, boca arriba y sin soportes, y cuelga de una rama incluso con una cinta fina. El modelo y el proyecto para el laminador con el cambio de filamento se descargan gratis y sin registro.',
    ],
    'steps' => [
        ['name' => 'Escriba el nombre', 'text' => 'El nombre puede tener hasta 16 caracteres. La herramienta lo reduce sola para que quepa en la galleta; un nombre corto sale más grande.'],
        ['name' => 'Elija la forma y el glaseado', 'text' => 'El muñeco tiene cara, botones y glaseado en las piernas; el corazón y la estrella, glaseado por el borde; el árbol, dos guirnaldas. El glaseado puede ser ondulado, en línea recta o ninguno.'],
        ['name' => 'Ajuste tamaño, argolla y colores', 'text' => 'Indique el ancho y el grosor, mueva la argolla por el contorno y asigne un filamento a cada una de las dos piezas. Marrón y blanco vienen predefinidos.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto y el tiempo de impresión los muestra el cálculo tras «Continuar». El modelo y el proyecto con el cambio de filamento se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Qué longitud de nombre cabe en la galleta?', 'a' => 'Hasta 16 caracteres. Un nombre más largo sale más pequeño; si las letras quedaran por debajo de 3 mm, la herramienta avisa y sugiere una galleta más ancha o un nombre más corto. En un muñeco de 90 mm caben cómodamente de 6 a 8 letras.'],
        ['q' => '¿Puedo poner también un año o una dedicatoria?', 'a' => 'Un solo campo de hasta 16 caracteres: «Ela 2026» o «Para la abuela» cabe; una dedicatoria más larga, no. Para más texto y una imagen propia use el Adorno navideño de una imagen o la placa con nombre de capas.'],
        ['q' => '¿De qué se imprime la galleta?', 'a' => 'Recomendamos PLA: es ligero y cuelga de una rama incluso con una cinta fina. Se imprime en plano, boca arriba, sin soportes. La impresión es un adorno, no es comestible; para galletas de verdad está la herramienta Cortador de galletas.'],
        ['q' => '¿La imprimen en dos colores?', 'a' => 'Sí. La base y el glaseado con el nombre quedan uno sobre otro, así que basta un cambio de filamento. Los dos colores se eligen al hacer el pedido entre los filamentos que tenemos en stock; el clásico es marrón con blanco, y también funcionan rojo con dorado o verde con blanco.'],
        ['q' => '¿Puedo usar la galleta como etiqueta de regalo?', 'a' => 'Sí. Una galleta más pequeña, de 50 a 60 mm y con argolla, se ata al paquete con una cinta, y quien la recibe la cuelga en el árbol. Para etiquetas sin glaseado es más rápida la herramienta Placa con nombre.'],
        ['q' => '¿Cuánto cuestan varias galletas a la vez?', 'a' => 'En el cálculo indica el número de unidades de un nombre: una galleta de 90 mm cuesta 99 CZK; seis iguales, 140 CZK, es decir, 23 CZK cada una, porque la preparación del pedido se paga una vez. Para otros nombres hace otro diseño; se imprimen juntas en una misma cama.'],
    ],
    'examples' => [
        'Muñeco de jengibre de 90 mm de ancho con el nombre Ela, cara y botones de glaseado blanco: 10,8 g, 20 minutos, 99 CZK.',
        'Corazón de jengibre de 90 mm con el nombre Mamince y glaseado ondulado por el borde.',
        'Estrella de jengibre de 80 mm con el nombre Tom, argolla en la punta superior.',
    ],
];
