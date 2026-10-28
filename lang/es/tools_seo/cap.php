<?php

// La página para la búsqueda «tapa a medida impresión 3d rosca» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Tapa a medida para imprimir en 3D: tapón, rosca o cubierta',
    'heading' => 'Tapa, tapón o cubierta a medida para imprimir en 3D según la medida tomada',
    'description' => 'Mida el cuello, el hueco o la rosca y la herramienta diseña una tapa, tapón o cubierta que encaja, se inserta o se enrosca. Impresión o descarga gratis.',
    'h1' => 'Cómo nace la tapa que falta, en qué encaja y cuánto cuesta',
    'intro' => [
        'La herramienta crea la tapa, el tapón o la cubierta que falta a partir de lo que mida. La pieza encaja sobre un borde, se inserta en un hueco o se enrosca en una rosca; la forma es redonda, rectangular o hexagonal, el diámetro o el ancho de 5 a 200 mm y la altura de 4 a 60 mm. Para una rosca elige la botella PET de 28 mm, una rosca métrica de paso grueso de M6 a M30, o indica su propio diámetro y un paso de 0,5 a 6 mm; el resultado es siempre una tapa o una tuerca para una rosca exterior, la herramienta no hace el tornillo. La holgura la añade la herramienta, usted indica la medida real. Para líquidos añada un labio de estanqueidad en la boca o un lecho para una junta, un moleteado por fuera y un borde superior redondeado. La vista previa muestra la pieza por fuera y en corte e indica a qué medida se ajusta.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): una tapa de rosca para botella PET de 28 mm con labio de estanqueidad, rosca de 12 mm de alto, pesa 5,7 g, se imprime en 22 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, el pedido mínimo, sin envío (cálculo /c/5omcbkke1541). Una cubierta para un perfil rectangular de 36 × 16 mm, insertada 15 mm, pesa 4,7 g y se imprime en 15 minutos (cálculo /c/ldniassche8t); una tapa a presión para un tarro con cuello de 70 mm pesa 17,8 g, se imprime en 39 minutos y cuesta 99 CZK (cálculo /c/hgabcfckkauy). Con varias unidades baja el precio por pieza, la preparación se paga una vez. El modelo se descarga gratis como STL o proyecto para su impresora; se imprime sin soportes.',
    ],
    'steps' => [
        ['name' => 'Elija cómo sujeta', 'text' => 'A presión sobre el borde, insertada en el hueco o enroscada en una rosca. Puede partir de un preajuste como «Botella PET (28 mm)», «Tapón de tubo» o «Cubierta de perfil».'],
        ['name' => 'Indique las medidas tomadas', 'text' => 'En una tapa a presión mida el cuello por fuera; en un tapón, el hueco por dentro; en una rosca, el diámetro sobre la rosca y el paso. Indique la medida real, sin sumar holgura.'],
        ['name' => 'Elija el cierre y la forma', 'text' => 'Para líquidos elija el labio de estanqueidad en la boca o el lecho para una junta. Puede añadir moleteado por fuera, una parte superior abombada y redondear el borde superior.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Revise la vista previa y el corte y continúe al precio exacto. O descargue el modelo gratis para su impresora; con una rosca desconocida, pruebe primero una unidad.'],
    ],
    'faq' => [
        ['q' => '¿Una tapa de rosca será estanca?', 'a' => 'Una rosca impresa no sella por sí sola, solo sujeta. Para líquidos elija el labio de estanqueidad en la boca, que se apoya en el interior del cuello, o el lecho para una junta de goma espuma o silicona. La junta no forma parte de la impresión.'],
        ['q' => '¿Qué roscas sabe hacer la herramienta?', 'a' => 'La botella PET de 28 mm (27,4 mm sobre la rosca, paso de 2,7 mm), roscas métricas de paso grueso de M6 a M30 y una rosca propia con paso de 0,5 a 6 mm. El resultado es siempre una tapa o una tuerca para una rosca exterior; la herramienta no hace el tornillo.'],
        ['q' => '¿Cómo mido el cuello o el hueco?', 'a' => 'Con un calibre: en una tapa a presión el diámetro exterior del cuello; en un tapón, el interior del hueco en dos puntos; en una rosca, el diámetro sobre las crestas y la distancia entre dos crestas vecinas. Indique la cifra real; la herramienta añade una holgura de 0,1 a 1 mm, que puede cambiar.'],
        ['q' => '¿Qué tamaño puede tener la pieza?', 'a' => 'El diámetro o el ancho del cuello puede ser de 5 a 200 mm y la altura de 4 a 60 mm. La herramienta rechaza una pieza demasiado pequeña, porque no se imprimiría firme; una tapa de más de 200 mm no cabe en la cama.'],
        ['q' => '¿Puedo usar la tapa para alimentos y bebidas?', 'a' => 'La impresión no está pensada para el contacto directo con alimentos. Si la tapa va en una bebida o un alimento, elija el lecho para una junta y ponga la junta; el plástico no debe tocar el contenido.'],
        ['q' => '¿De qué material y cómo la imprimo en casa?', 'a' => 'Tapones y tapas que deben flexar o se enroscan a menudo, en PETG; para cubiertas sin carga basta el PLA. Se imprime con la parte superior abajo, sin soportes, capas de 0,2 mm; la rosca sale más limpia con 0,12 mm. Si la tapa entra dura, añada 0,1 mm de holgura.'],
    ],
    'examples' => [
        'Tapa de rosca para botella PET de 28 mm con labio de estanqueidad, rosca de 12 mm de alto: 5,7 g, 22 minutos, 99 CZK.',
        'Cubierta para un perfil rectangular con hueco de 36 × 16 mm, se inserta 15 mm: 4,7 g, 15 minutos.',
        'Tapa a presión para un tarro con cuello de 70 mm de diámetro, 14 mm de fondo: 17,8 g, 39 minutos, 99 CZK.',
    ],
];
