<?php

// La página para la búsqueda «soporte para móvil impresión 3d a medida» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Soporte para móvil a medida para imprimir en 3D: seis tipos',
    'heading' => 'Soporte para móvil a medida para imprimir en 3D',
    'description' => 'Elija uno de seis tipos, indique el grosor del móvil con funda y la inclinación, y vea la vista previa al instante. Descarga gratis, o lo imprimimos.',
    'h1' => 'Qué soportes hace la herramienta, cómo medir el móvil y cuánto cuesta imprimirlo',
    'intro' => [
        'La herramienta ofrece seis tipos de soporte: placa con ganchos, onda, caballete con repisa, cuña baja, bolsillo de pared y pinza para la rejilla de ventilación del coche; además, variantes con una forma recortada en el respaldo, chapa ondulada, placa plana con pie encajable, cinta en Z y silueta de animal con asiento. El soporte se calcula a partir del grosor del móvil con funda, que indica de 7 a 20 mm, y de su ancho; en los tipos de mesa ajusta la inclinación y la altura del respaldo hasta 200 mm y el ancho hasta 260 mm, así que la cuña y los tipos anchos sirven también para una tableta. Según el tipo añade un recorte para el cable de carga, una ventana de aligeramiento en el respaldo u orificios para tornillos. La inclinación forma parte del diseño y no se puede cambiar tras imprimir; el soporte solo sujeta el móvil, no lleva cargador.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): una cuña baja de 80 mm de ancho a 60° pesa 39 g, se imprime en 1 h 8 min y cuesta 120 CZK (unos 5 EUR) con nosotros; un caballete con repisa de 75 mm de ancho a 65° pesa 65 g, se imprime en 1 h 45 min y cuesta 170 CZK (unos 7 EUR), ambos sin envío. Para la mesa o la pared basta el PLA; la pinza del coche imprímala en PETG, que soporta mejor el calor tras el cristal. El modelo se descarga gratis como STL o proyecto para su impresora (unas 270 impresoras para elegir), o se encarga impreso con nosotros; después de la medalla, el soporte para móvil es nuestra herramienta más encargada.',
    ],
    'steps' => [
        ['name' => 'Elija el tipo de soporte', 'text' => 'Para la mesa, la placa con ganchos, la onda, el caballete con repisa o la cuña baja. El bolsillo va en la pared y la pinza en la rejilla de ventilación del coche.'],
        ['name' => 'Mida el móvil con funda', 'text' => 'Indique el grosor del móvil con funda, de 7 a 20 mm, y su ancho. La herramienta añade sola una pequeña holgura.'],
        ['name' => 'Ajuste la inclinación y los extras', 'text' => 'Ajuste la inclinación y la altura del respaldo. Según el tipo añade un recorte para el cable de carga, una ventana de aligeramiento en el respaldo o fijación con tornillos.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Revise la vista previa y pase al precio exacto con «Continuar». O descargue gratis el modelo para su propia impresora.'],
    ],
    'faq' => [
        ['q' => '¿Qué inclinación elegir?', 'a' => '60° para ver vídeo en la mesa (cuña baja), de 65 a 70° para escribir y desbloquear con la cara (caballete u onda), menos en la mesita de noche para que la pantalla no se vea desde la cama. La inclinación queda fija en el modelo y no cambia tras imprimir; si duda, imprima dos.'],
        ['q' => '¿Cabe un móvil con funda en el soporte?', 'a' => 'Sí, si indica el grosor del móvil con funda. La herramienta trabaja de 7 a 20 mm y añade sola una pequeña holgura; demasiada holgura dejaría el móvil bailando.'],
        ['q' => '¿Puedo cargar el móvil en el soporte?', 'a' => 'Sí. La onda, el caballete, la cuña, el bolsillo de pared y la pinza del coche pueden llevar un recorte para el cable de carga y lo llevan por defecto. La placa con ganchos no lo tiene; el móvil apoya en dos ganchos.'],
        ['q' => '¿Sirve el soporte también para una tableta?', 'a' => 'El ancho llega a 260 mm y el respaldo a 200 mm, así que la cuña y los tipos de mesa anchos sirven para una tableta. El dispositivo con funda puede tener como máximo 20 mm de grosor.'],
        ['q' => '¿Cómo se fijan el bolsillo de pared y la pinza del coche?', 'a' => 'El bolsillo se pega con cinta de doble cara o, si activa la opción de tornillos, se atornilla con cuatro tornillos. La pinza se encaja en una lama de la rejilla cuyo grosor indica de 1 a 4 mm. Ni la cinta ni los tornillos forman parte de la impresión.'],
        ['q' => '¿De qué material imprimo el soporte?', 'a' => 'Para la mesa o la pared basta el PLA; un filamento mate disimula las capas. La pinza del coche y un soporte para el salpicadero imprímalos en PETG, porque el PLA tras el cristal al sol se ablanda hacia los 55 °C.'],
    ],
    'examples' => [
        'Caballete de mesa con repisa, 75 mm de ancho, respaldo a 65°, recorte para el cable: 65 g, 1 h 45 min, 170 CZK.',
        'Cuña baja para ver vídeo, 80 mm de ancho, 70 mm de fondo, 60°: 39 g, 1 h 8 min, 120 CZK.',
        'Bolsillo de pared para un móvil de 80 mm de ancho, cuatro orificios para tornillos.',
        'Caballete de mesa con un gato recortado en el respaldo, 80 mm de ancho; forma de la biblioteca o propia.',
        'Chapa ondulada en S, 70 mm de ancho, 70°, nervios cada 6 mm.',
        'Placa plana con corazones y pie encajable, 70 mm de ancho, dos piezas impresas en plano.',
        'Cinta en Z, banda de 10 mm con una franja de un segundo color, 70 mm de ancho.',
        'Silueta de perro de la biblioteca, 100 mm de alto, asiento para el móvil delante.',
    ],
];
