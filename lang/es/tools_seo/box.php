<?php

return [
    'title' => 'Caja a medida con tapa y aberturas para imprimir en 3D',
    'description' => 'Indique las medidas interiores y añada tapa, aberturas o ranura para el cable. Vista previa y precio al momento. Impresión o descarga gratis.',
    'h1' => 'Una caja a la medida de lo que debe caber dentro',
    'intro' => [
        'La herramienta crea una caja a partir de sus medidas interiores, es decir, del objeto que debe caber dentro. Las medidas exteriores las calcula ella. La caja sirve para electrónica, piezas pequeñas o un regalo. Puede añadir una tapa encajable con reborde, hasta ocho aberturas redondas o rectangulares en las paredes y una ranura para el cable que parte del borde superior.',
        'La vista previa giratoria muestra la caja y la tapa, y debajo las medidas interiores y exteriores. El precio orientativo se recalcula con cada cambio. Imprimimos la caja en nuestra granja de impresión en Chequia: le llega con Packeta a un punto de recogida o a domicilio. También puede imprimirla usted, el modelo se descarga gratis. La caja y la tapa se imprimen una al lado de la otra sin soportes.',
    ],
    'steps' => [
        ['name' => 'Mida lo que debe caber', 'text' => 'Indique el ancho, el fondo y el alto interiores de la caja. Deje unos milímetros de margen alrededor del objeto.'],
        ['name' => 'Elija tapa y redondeo', 'text' => 'Marque «Con tapa encajable» si quiere tapa y ajuste el redondeo de esquinas. Para un cable añada «Con ranura para el cable».'],
        ['name' => 'Añada aberturas en las paredes', 'text' => 'En cada abertura elige la pared, la forma, el tamaño y la posición del centro. La posición se mide desde la esquina inferior izquierda de la pared vista desde fuera.'],
        ['name' => 'Revise vista previa y precio', 'text' => 'La vista previa muestra la caja, la tapa, las medidas y un precio orientativo. Si una abertura se sale de la pared o dos se solapan, la herramienta lo avisa.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Continúe al precio exacto y pídanos la impresión; se paga con crédito recargado con tarjeta. O descargue la caja y la tapa para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Indico las medidas interiores o las exteriores?', 'a' => 'Las interiores. La herramienta calcula las exteriores según el grosor de pared y de fondo y las muestra bajo la vista previa.'],
        ['q' => '¿Qué tamaño puede tener la caja?', 'a' => 'Por dentro, de 10 a 300 mm de ancho y de fondo y de 8 a 200 mm de alto. Las paredes pueden tener de 1,2 a 5 mm de grosor.'],
        ['q' => '¿Cómo se sujeta la tapa?', 'a' => 'La tapa tiene un reborde que entra en la caja. No lleva bisagras, cierre ni tornillos. Si queda dura o floja, cambie la holgura de la tapa; por defecto es de 0,25 mm.'],
        ['q' => '¿Cuántas aberturas puedo añadir y dónde?', 'a' => 'Ocho como máximo, en la pared frontal, trasera, izquierda o derecha. Cada abertura debe medir al menos 2 mm, estar a 2 mm del borde y a 1,5 mm de la siguiente. No se pueden añadir aberturas en el fondo ni en la tapa.'],
        ['q' => '¿Para qué sirve la ranura del cable?', 'a' => 'Es una ranura en la pared derecha, abierta hasta el borde superior. El cable entra con su conector y la tapa cierra encima. El ancho de la ranura se elige entre 3 y 30 mm.'],
    ],
    'examples' => [
        'Caja con tapa encajable para cosas pequeñas, interior 80 × 50 × 30 mm.',
        'Caja con tapa y ranura para el cable, interior 120 × 80 × 40 mm.',
        'Caja abierta sin tapa, esquinas redondeadas, interior 40 × 40 × 20 mm.',
    ],
];
