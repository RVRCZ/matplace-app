<?php

// La página para la búsqueda «topper de tarta con nombre» (docs/AP.md §6). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Topper de tarta con nombre y número, impreso en 3D, gratis',
    'heading' => 'Topper de tarta con nombre y número',
    'description' => 'Un número, un corazón, una estrella o un texto, con el nombre en un segundo color y púas. Lo imprimimos en dos colores, o descarga el modelo gratis.',
    'h1' => 'Cómo nace el topper, qué tamaño debe tener y cuánto cuesta',
    'intro' => [
        'La herramienta crea un topper de tarta: un número grande, un corazón, una estrella o un círculo con el nombre encima en otra tipografía y otro color, o solo el texto sin forma. Basta escribir un nombre de hasta 20 caracteres y un número de hasta tres cifras, por ejemplo «2» y «Olivia». El ancho de la forma se ajusta de 50 a 200 mm; el nombre puede sobresalir de la forma y se mueve arriba o abajo, en treinta tipografías. Abajo el topper tiene una o dos púas de 30 a 100 mm. Todo es una sola pieza plana de 2 a 4 mm de grosor; las letras que sobresaldrían o no se tocarían las respalda una copia ensanchada en el color de la forma y las une un puente. El nombre sobresale de 0,4 a 1,2 mm y se imprime en el segundo color, así que basta un cambio de filamento; en nuestra granja el topper se imprime de una vez y los dos colores se eligen al hacer el pedido.',
        'Un ejemplo de la calculadora en producción (27 de octubre de 2026, PLA): un número 2 de 110 mm de ancho con el nombre Olivia en tipografía manuscrita y dos púas de 60 mm pesa 30 g, se imprime en 55 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo (cálculo /c/4jdvyezywc3l, un color; el segundo se elige al hacer el pedido). Se imprime en plano sin soportes; con 3 mm de grosor es ligero y no se dobla. El plástico impreso no está pensado para el contacto con alimentos, así que envuelva las púas en film o métalas en una pajita. El modelo y el proyecto con el cambio de filamento se descargan gratis y sin registro; para una fiesta encargue con tiempo, el cálculo muestra el tiempo de impresión y entrega.',
    ],
    'steps' => [
        ['name' => 'Escriba el nombre y el número', 'text' => 'Un nombre o un texto corto y un número, por ejemplo la edad del homenajeado. Para el corazón, la estrella y el círculo no hace falta número.'],
        ['name' => 'Elija la forma y la tipografía', 'text' => 'Número, corazón, estrella, círculo o solo texto. El nombre tiene treinta tipografías; una manuscrita en la que las letras se tocan se mantiene unida sola; en las demás la herramienta une las letras.'],
        ['name' => 'Ajuste el tamaño y las púas', 'text' => 'El ancho de la forma, el ancho del nombre y su posición se cambian con deslizadores y la vista previa se redibuja al momento. Una o dos púas; para una tarta alta elija púas más largas.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto y el tiempo de impresión los muestra el cálculo tras «Continuar». El modelo y el proyecto con el cambio de filamento se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Puede un topper impreso tocar la tarta?', 'a' => 'El plástico impreso no está pensado para el contacto con alimentos. Envuelva las púas en film antes de clavarlas, o métalas en una pajita. La forma con el nombre queda por encima de la tarta y no toca la crema.'],
        ['q' => '¿Qué tamaño de topper elegir?', 'a' => 'Para una tarta de 20 cm de diámetro va bien una forma de 100 a 120 mm de ancho. Púas de 60 mm bastan para una tarta normal; para una de pisos elija de 80 a 100 mm.'],
        ['q' => '¿Puedo hacer un topper con un año o un texto de fiesta?', 'a' => 'Sí. El número admite como máximo tres cifras, así que escriba el año como texto: «2027» o «Feliz año nuevo» cabe en 20 caracteres y lleva la tipografía que elija. Sin forma detrás del texto, elija el acabado «solo texto».'],
        ['q' => '¿De qué se imprime el topper?', 'a' => 'Recomendamos PLA. Con 3 mm de grosor el topper es ligero y no se dobla; se imprime en plano y sin soportes. Tras la fiesta lávelo a mano con agua tibia y vuelva a usarlo.'],
        ['q' => '¿Lo imprimen en dos colores?', 'a' => 'Sí. La forma con las púas y el nombre quedan uno sobre otro, así que basta un cambio de filamento. Los dos colores se eligen al hacer el pedido entre los filamentos que tenemos en stock; dorado con rosa, o negro con blanco, están entre los más habituales.'],
        ['q' => '¿Y si las letras no tocan la forma?', 'a' => 'Bajo el nombre queda su copia ensanchada en el color de la forma, así que aguantan incluso las letras que sobresalen. Las piezas sueltas se unen con un puente corto y la herramienta lo avisa.'],
    ],
    'examples' => [
        'Topper con el número 2 de 110 mm de ancho y el nombre Olivia en tipografía manuscrita, dos púas de 60 mm: 30 g, 55 minutos, 99 CZK.',
        'Corazón de 120 mm con el texto Ela a Tom para una tarta de boda.',
        'Texto Všechno nejlepší de 180 mm de ancho sin forma, púas de 80 mm.',
    ],
];
