<?php

// La página para la búsqueda «caja a medida impresión 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Caja a medida para imprimir en 3D: con tapa y agujeros',
    'heading' => 'Caja a medida para imprimir en 3D',
    'description' => 'Indique las medidas interiores, añada tapa, agujeros en las paredes o una ranura para cable, y vea la vista previa con medidas exteriores. Modelo gratis.',
    'h1' => 'Qué hace la caja a medida, cómo encaja la tapa y cuánto cuesta imprimirla',
    'intro' => [
        'La herramienta crea una caja a partir de las medidas interiores, es decir, de lo que debe caber dentro: ancho y fondo de 10 a 300 mm, alto de 8 a 200 mm, paredes de 1,2 a 5 mm. Calcula sola las medidas exteriores y las muestra bajo la vista previa. Puede añadir una tapa encajable con reborde (0,25 mm de holgura, ajustable), hasta ocho agujeros redondos o rectangulares en la pared delantera, trasera, izquierda o derecha, esquinas redondeadas y una ranura para cable desde el borde superior de 3 a 30 mm de ancho. La caja y la tapa se imprimen una junto a otra sin soportes. Sirve para electrónica, piezas pequeñas, tarjetas SD y como envoltorio de regalo.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): una caja con tapa encajable, de 80 × 50 × 30 mm por dentro (84 × 54 × 32 mm por fuera), pesa 28 g, se imprime en 34 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo (cálculo /c/nku29wz4vieb); con tapa y ranura para cable pesa 41 g, se imprime en 51 minutos y cuesta 110 CZK, ambas sin envío. En 90 días pasaron por la calculadora 13 cajas. Para un cajón basta el PLA; para el baño o junto al horno elija PETG. El modelo se descarga gratis como STL o proyecto para su impresora; la caja y la tapa, también como piezas separadas.',
    ],
    'steps' => [
        ['name' => 'Mida lo que debe caber', 'text' => 'Indique el ancho, el fondo y el alto interiores de la caja. Deje 1 a 2 mm de holgura por cada lado del objeto.'],
        ['name' => 'Elija la tapa y el redondeo', 'text' => 'Marque «Con tapa encajable» si quiere tapa y ajuste el redondeo de las esquinas. Para un cable añada «Con ranura para cable».'],
        ['name' => 'Añada agujeros en las paredes', 'text' => 'En cada agujero elige la pared, la forma, el tamaño y la posición del centro. La posición se mide desde la esquina inferior izquierda de la pared vista desde fuera.'],
        ['name' => 'Revise la vista previa', 'text' => 'La vista previa muestra la caja, la tapa y las medidas. Si un agujero sale de la pared o dos se solapan, la herramienta lo avisa.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Continúe al precio exacto y encárguenos la impresión. O descargue gratis la caja y la tapa para su propia impresora.'],
    ],
    'faq' => [
        ['q' => '¿Indico las medidas interiores o exteriores?', 'a' => 'Las interiores. La herramienta calcula las exteriores según el grosor de las paredes y del fondo y las muestra bajo la vista previa; una caja de 80 × 50 × 30 mm con paredes de 2 mm sale con 84 × 54 × 32 mm.'],
        ['q' => '¿Qué tamaño puede tener la caja?', 'a' => 'Por dentro, de 10 a 300 mm de ancho y fondo y de 8 a 200 mm de alto. Las paredes pueden tener de 1,2 a 5 mm. Una caja de más de 250 mm no cabe en todas las impresoras; el cálculo lo avisa.'],
        ['q' => '¿Cómo se sujeta la tapa?', 'a' => 'La tapa tiene un reborde que encaja dentro de la caja. No tiene bisagras, cierre ni tornillos. Si va dura o floja, ajuste la holgura de la tapa; por defecto es de 0,25 mm. Pruebe la holgura en la primera caja de un juego nuevo.'],
        ['q' => '¿Cuántos agujeros puedo añadir y dónde?', 'a' => 'Ocho como máximo, en la pared delantera, trasera, izquierda o derecha. Un agujero debe tener al menos 2 mm, estar a 2 mm del borde y a 1,5 mm de otro agujero. No se pueden añadir agujeros en el fondo ni en la tapa.'],
        ['q' => '¿Para qué sirve la ranura para cable?', 'a' => 'Es una ranura en la pared derecha abierta hacia el borde superior. Coloca el cable en ella con su conector y la tapa se cierra encima. El ancho de la ranura se elige de 3 a 30 mm; para USB‑C bastan 10 mm; para un cable de alimentación con enchufe, 15 mm.'],
        ['q' => '¿Sirve la caja para electrónica, por ejemplo un Arduino o un ESP32?', 'a' => 'Sí: indique las medidas de la placa más 2 mm por cada lado y la altura según el componente más alto, y coloque los agujeros de los conectores según su posición desde la esquina de la pared. Añada ventilación como una fila de agujeros redondos; con calor dentro, elija PETG.'],
    ],
    'examples' => [
        'Caja con tapa encajable para cosas pequeñas, 80 × 50 × 30 mm por dentro: 28 g, 34 minutos, 99 CZK.',
        'Caja con tapa y ranura para cable, 120 × 80 × 40 mm por dentro.',
        'Caja abierta sin tapa con esquinas redondeadas, 40 × 40 × 20 mm por dentro.',
    ],
];
