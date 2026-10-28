<?php

// La página para la búsqueda «cartel luminoso impresión 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Cartel luminoso para imprimir en 3D con tira LED, gratis',
    'heading' => 'Cartel luminoso para imprimir en 3D con tira LED',
    'description' => 'Escriba un texto o suba un logotipo y la herramienta diseña un cartel luminoso de cuatro piezas para una tira LED de 5 V. Las imprimimos, o las descarga.',
    'h1' => 'De qué se compone el cartel luminoso, qué comprar y cuánto cuestan las piezas',
    'intro' => [
        'La herramienta diseña un cartel luminoso de cuatro piezas impresas: el cuerpo, la máscara frontal con el motivo recortado, el difusor que reparte la luz y la tapa trasera. El motivo es un texto de hasta dos líneas de 16 caracteres, o un SVG o una imagen sencilla subidos; unos puentes estrechos sujetan el interior de las letras en la máscara. La forma puede ser rectangular, o redonda con base plana para que se sostenga en una estantería. El ancho se ajusta de 80 a 300 mm y el fondo de 25 a 80 mm; el alto del rectángulo sigue el motivo y el margen. La fuente de luz se elige antes: tira LED de 8 o 10 mm de ancho, o módulo LED con controlador; según ella la herramienta vigila el fondo mínimo del cuerpo e indica cuántos metros de tira caben por el perímetro interior. Imprimimos solo las piezas de plástico; la tira LED de 5 V, la alimentación USB y la cinta de doble cara se compran aparte.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): el cartel rectangular OPEN de 180 mm de ancho y 37 mm de fondo, las cuatro piezas una junto a otra, pesa 97 g, se imprime en 2 h 17 min y cuesta 180 CZK (unos 7 EUR) con nosotros en un solo color, sin envío (cálculo /c/haqwdtiseafu). La máscara frontal y el cuerpo deben ser oscuros para que la luz pase solo por el motivo; el difusor, blanco o translúcido; cómo repartir los colores entre las piezas se explica en una pregunta más abajo. El montaje es suyo: pegue la tira por el perímetro, pase el cable por el agujero, coloque el difusor y la máscara por delante. Las piezas se descargan gratis como STL o proyecto para su impresora.',
    ],
    'steps' => [
        ['name' => 'Escriba un texto o suba un logotipo', 'text' => 'Escriba el texto en una o dos líneas, o suba un SVG o una imagen con el motivo oscuro sobre fondo claro. Unos puentes estrechos sujetan el interior de las letras en la máscara.'],
        ['name' => 'Elija la forma y la fuente de luz', 'text' => 'Elija forma rectangular o redonda y la fuente de luz: tira LED de 8 mm o 10 mm de ancho, o módulo LED con controlador. Según la fuente, la herramienta vigila el fondo mínimo del cuerpo.'],
        ['name' => 'Ajuste las medidas', 'text' => 'Indique el ancho y el fondo. En la forma rectangular el alto sigue el motivo y el margen que lo rodea.'],
        ['name' => 'Mire las piezas', 'text' => 'La vista previa muestra el conjunto desplegado por capas y se puede cambiar a cada pieza. Debajo está la lista de cosas que no forman parte de la impresión.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Encargue la impresión en nuestra granja. O descargue gratis las piezas como archivos STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿La tira LED forma parte de la impresión?', 'a' => 'No. Imprimimos cuatro piezas de plástico. La tira o el módulo LED, un cable USB con alimentación y la cinta de doble cara o el pegamento los compra usted; la herramienta indica bajo la vista previa qué y cuánto.'],
        ['q' => '¿Qué tira LED va bien en el cartel?', 'a' => 'Una tira de 5 V de 8 o 10 mm de ancho, o un módulo LED o una tira con controlador de hasta 18 × 8 mm. La herramienta indica la longitud de tira necesaria bajo la vista previa. El cable pasa por un agujero del cuerpo de 3 a 10 mm; para 5 V basta un cargador USB.'],
        ['q' => '¿En qué colores imprimo las piezas?', 'a' => 'El cuerpo, la máscara frontal y la tapa trasera deben ser oscuros, para que la luz pase solo por el motivo, y el difusor blanco o translúcido. Un pedido se imprime con nosotros en un solo color. Descargue por tanto el difusor como pieza aparte y súbalo como modelo propio en un segundo pedido, o imprímalo en casa en PLA blanco.'],
        ['q' => '¿Cómo monto el cartel?', 'a' => 'Pegue la tira LED por el perímetro interior del cuerpo y pase el cable por el agujero. El difusor y la máscara frontal se colocan por delante sobre el escalón del cuerpo; la tapa trasera se encaja por detrás. No hay tornillos; quien quiera, asegura las piezas con una gota de pegamento.'],
        ['q' => '¿Qué tamaño puede tener el cartel?', 'a' => 'El ancho es de 80 a 300 mm y el alto de un cartel rectangular sigue el motivo, 300 mm como máximo. La forma redonda tiene de alto nueve décimas del ancho. Cada pieza tiene que caber en la cama de impresión, lo que el cálculo avisa en el paso siguiente.'],
        ['q' => '¿Se puede colgar el cartel en la pared o en un escaparate?', 'a' => 'El cuerpo tiene la parte trasera plana, así que se pega con cinta de doble cara o se cuelga de un gancho por la tapa trasera; el cable sale hacia abajo o hacia atrás. Para un escaparate al sol elija PETG; el PLA junto a un cristal caliente se ablanda.'],
    ],
    'examples' => [
        'Cartel luminoso rectangular OPEN, 180 × 64 mm, 37 mm de fondo, para tira LED: 97 g, 2 h 17 min, 180 CZK.',
        'Cartel luminoso redondo BAR con base plana, 160 × 144 mm, de pie en una estantería.',
        'Cartel luminoso de dos líneas CAFÉ LUNA, 240 × 172 mm, para un mostrador o un escaparate.',
    ],
];
