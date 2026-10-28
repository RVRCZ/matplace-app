<?php

// La página para la búsqueda «sello personalizado impresión 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Sello a medida para imprimir en 3D para arcilla y pasta',
    'heading' => 'Sello a medida para imprimir en 3D para arcilla y pasta de modelar',
    'description' => 'Escriba un texto o suba un motivo y la herramienta crea un sello con mango, en espejo para una impronta legible en cerámica y pasta. Modelo gratis.',
    'h1' => 'En qué imprime el sello, cómo nace y cuánto cuesta',
    'intro' => [
        'La herramienta crea un sello para improntas en arcilla cerámica, arcilla polimérica, jabón o masa, es decir, en un material blando que conserve la marca. El motivo puede ser un texto de hasta dos líneas de 20 caracteres en una de cuatro tipografías, un SVG o una imagen sencilla en blanco y negro; el original subido tiene prioridad sobre el texto. El motivo se refleja solo, así que la impronta se lee bien; la vista previa se puede cambiar a la impronta que deja el sello. El ancho del motivo se ajusta de 15 a 120 mm, su altura de 0,8 a 4 mm, y elige un sello con mango redondo o solo una placa. El motivo puede ir en relieve, y entonces se hunde en el material, o rebajado, y entonces la impronta sobresale. La herramienta avisa de líneas más finas de 0,8 mm. Un sello impreso no sirve para tinta y papel; su superficie es demasiado dura.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): el sello EVA de 50 mm de ancho con mango y motivo en relieve pesa 6,8 g, se imprime en 28 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo, sin envío (cálculo /c/qr5iss66z3ip). El mango se imprime aparte y se pega a la placa con cianoacrilato. Un sello con logotipo para un taller de cerámica o la marca de autor en la base de las tazas se imprime igual; basta subir un SVG. El modelo se descarga gratis como STL o proyecto para su impresora.',
    ],
    'steps' => [
        ['name' => 'Escriba un texto o suba un motivo', 'text' => 'Escriba el texto en una o dos líneas, o suba un SVG o una imagen con el motivo oscuro sobre fondo claro. El original subido tiene prioridad sobre el texto.'],
        ['name' => 'Elija el motivo y el mango', 'text' => 'Elija motivo en relieve o rebajado y sello con mango o sin él. Ajuste el ancho y la altura del motivo.'],
        ['name' => 'Cambie la vista previa a la impronta', 'text' => 'Con «La impronta que deja» comprueba cómo quedará la marca. La herramienta avisa de líneas más finas de 0,8 mm.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Encargue la impresión en nuestra granja. O descargue gratis el modelo como STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Sirve el sello para papel y tinta?', 'a' => 'No, la superficie de un sello impreso es demasiado dura para eso y la tinta no se adhiere. Está pensado para improntas en arcilla cerámica, arcilla polimérica, jabón o masa.'],
        ['q' => '¿Tengo que reflejar el motivo de antemano?', 'a' => 'No. La herramienta refleja sola el texto y la imagen subida. Prepare el original tal como debe verse la impronta terminada y compruébelo con la vista «La impronta que deja».'],
        ['q' => '¿Cómo se fija el mango?', 'a' => 'El mango se imprime como pieza aparte y se pega a la placa con cianoacrilato. El pegamento no forma parte de la impresión; sin mango, la placa sirve como placa de gofrado bajo un rodillo.'],
        ['q' => '¿Qué diferencia hay entre el motivo en relieve y el rebajado?', 'a' => 'El motivo en relieve se hunde en el material y deja una impronta hundida, por ejemplo una firma en la base de una taza. El motivo rebajado está vaciado en la placa, así que la impronta sobresale del material, por ejemplo un logotipo en relieve sobre jabón.'],
        ['q' => '¿Qué detalle admite el sello?', 'a' => 'Las líneas más finas de 0,8 mm no se imprimen limpiamente o no se imprimen, y la herramienta lo avisa. Ayuda agrandar el sello o engrosar las líneas del original; una altura de motivo de 1 a 1,5 mm da la impronta más legible en arcilla.'],
        ['q' => '¿Cómo imprimo el sello en casa y cómo lo limpio?', 'a' => 'Con el motivo hacia arriba, sin soportes, en PLA o PETG, capas de 0,2 mm o más finas para bordes más lisos. La arcilla se lava del sello con agua y un cepillo; antes de sellar un material pegajoso, espolvoree el sello con almidón de maíz.'],
    ],
    'examples' => [
        'Sello EVA con mango, placa de 56 × 23 mm, motivo en relieve para arcilla: 6,8 g, 28 minutos, 99 CZK.',
        'Sello de dos líneas HAND MADE con mango, placa de 66 × 41 mm, para cerámica.',
        'Placa OK sin mango, 46 × 25 mm, motivo rebajado, la impronta sobresale.',
    ],
];
