<?php

// La página para la búsqueda «letra 3d con nombre» (docs/AP.md §6). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Letra 3D con nombre: decoración y regalo a medida, gratis',
    'heading' => 'Una letra grande con nombre como regalo',
    'description' => 'Escriba un nombre y obtenga su primera letra en grande con el nombre en un segundo color. La imprimimos en dos colores, o descarga el modelo gratis.',
    'h1' => 'Cómo nace la letra con nombre, cuándo se sostiene sola y cuánto cuesta',
    'intro' => [
        'La herramienta crea la primera letra del nombre en grande y escribe sobre ella el nombre completo. Basta indicar un nombre de hasta 20 caracteres: la letra se elige sola, o elige otra, por ejemplo la inicial del apellido. La altura se ajusta de 60 a 200 mm y el grosor de 3 a 15 mm; a partir de 10 mm la mayoría de las letras se sostienen solas en una estantería, y cualquiera puede ponerse en una peana con ranura que se imprime a su lado. La letra grande tiene tres tipografías en negrita; el nombre, treinta, incluidas manuscritas. La herramienta coloca el nombre donde la letra tiene más espacio: a lo largo de un trazo horizontal, o de pie junto a uno vertical; sobresale de 0,4 a 2 mm y se imprime en el segundo color. Los dos colores quedan uno sobre otro, así que basta detener la impresión una vez y cambiar el filamento; en nuestra granja la letra se imprime de una vez y los dos colores se eligen al hacer el pedido.',
        'Un ejemplo de la calculadora en producción (27 de octubre de 2026, PLA): una letra E de 120 mm de alto y 8 mm de grosor con el nombre Ela en tipografía manuscrita pesa 27 g, se imprime en 40 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo (cálculo /c/vhfifzrzy5in, un color; el segundo se elige al hacer el pedido). Con 8 mm se pone en la peana del mismo archivo; a partir de 10 mm se sostiene sola. Se imprime en plano sin soportes. Va bien en la puerta de una habitación infantil, en una estantería o como regalo de nacimiento o de santo; el modelo y el proyecto con el cambio de filamento se descargan gratis y sin registro.',
    ],
    'steps' => [
        ['name' => 'Escriba el nombre', 'text' => 'La letra grande es la primera letra del nombre. Si quiere otra, por ejemplo la inicial del apellido, escríbala en el segundo campo.'],
        ['name' => 'Elija las tipografías', 'text' => 'Para la letra grande, sin serifa, con serifa o monoespaciada, siempre en negrita. Para el nombre, cualquiera de las treinta tipografías.'],
        ['name' => 'Ajuste la altura, el grosor y los colores', 'text' => 'La vista previa muestra dónde cupo el nombre y qué tamaño tiene. Asigne a la letra y al nombre un filamento de los que tenemos en stock.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto y el tiempo de impresión los muestra el cálculo tras «Continuar». El modelo y el proyecto con el cambio de filamento se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Dónde se escribe el nombre en la letra?', 'a' => 'Donde la letra tiene más espacio. En letras con trazo vertical (E, T, L, I, K) el nombre va de pie junto a él; en trazos horizontales anchos, a lo largo. La herramienta encuentra el sitio sola y la vista previa lo muestra al momento.'],
        ['q' => '¿Y si el nombre no cabe?', 'a' => 'En letras redondas y estrechas (O, S, C) el nombre sale más pequeño. Si quedara por debajo de 4 mm de alto, la herramienta avisa: ayuda una letra más alta, un nombre más corto u otra tipografía de la letra grande.'],
        ['q' => '¿Puedo escribir una fecha o una palabra en lugar de un nombre?', 'a' => 'Sí, cualquier texto de hasta 20 caracteres: «Ela 2026», «Familia Novák» o «Navidad». Elija entonces la letra grande a mano en el segundo campo para que corresponda a lo que debe decir la decoración.'],
        ['q' => '¿Se sostiene la letra?', 'a' => 'A partir de 10 mm de grosor la mayoría de las letras se sostienen solas sobre una superficie plana; las más finas se ponen en la peana con ranura que está en el archivo. Las letras con pie estrecho, como P o F, es más seguro apoyarlas o pegarlas a una puerta con cinta de doble cara.'],
        ['q' => '¿De qué se imprime y cuántos colores tiene?', 'a' => 'Recomendamos PLA. La letra tiene dos colores: el cuerpo y el nombre que queda encima. Basta un cambio de filamento, así que cualquier impresora la imprime; con nosotros elige los dos colores al hacer el pedido.'],
        ['q' => '¿Qué tamaño de letra pueden imprimir?', 'a' => 'Hasta 200 mm de alto; la cama de nuestra granja mide 250 × 250 mm. La vista previa muestra junto a las medidas si la letra cabe en la cama; las letras anchas como M y W son más anchas a la misma altura.'],
    ],
    'examples' => [
        'Letra E de 120 mm de alto con el nombre Ela en tipografía manuscrita a lo largo del trazo vertical: 27 g, 40 minutos, 99 CZK.',
        'Letra T con serifa de 150 mm de alto con el nombre Tomáš.',
        'Letra I de 100 mm de alto y 12 mm de grosor con el nombre Ivana, que se sostiene sola.',
    ],
];
