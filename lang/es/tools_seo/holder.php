<?php

// La página para la búsqueda «soporte de pared a medida impresión 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Soporte de pared a medida para imprimir en 3D: cuna, gancho',
    'heading' => 'Soporte de pared a medida para imprimir en 3D',
    'description' => 'Mida el objeto, elija cómo debe sujetarlo y el soporte sale a medida: cuna, bolsillo, gancho o pinza, con tornillos o cinta. Modelo gratis.',
    'h1' => 'Qué soportes hace la herramienta, cómo medir y cuánto cuesta imprimirlos',
    'intro' => [
        'La herramienta crea un soporte a partir de las medidas del objeto que quiere colgar en la pared: un mando a distancia, una botella, una herramienta, unos auriculares, una escoba. Elige uno de cuatro tipos: cuna con frente bajo, bolsillo cerrado, gancho para todo lo que tenga asa o correa, o pinza que agarra un mango redondo de hasta 60 mm de diámetro. Indica las medidas reales del objeto (ancho de 10 a 300 mm, grosor de 5 a 150 mm) y la herramienta añade sola 0,8 mm de holgura; la pinza, en cambio, es un poco más estrecha. El soporte se fija con tornillos de 4 mm y tacos, colgándolo de un tornillo ya puesto en la pared, o con cinta de doble cara sin agujeros. La vista previa muestra la forma con sus agujeros y medidas, y el modelo se imprime sin soportes.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): una cuna para un mando de 50 × 22 mm, de 70 mm de alto, pesa 27 g, se imprime en 1 h 12 min y cuesta 100 CZK (unos 4 EUR) con nosotros; un gancho para auriculares pesa 11 g, se imprime en 27 minutos y cuesta 99 CZK; una pinza para un palo de escoba de 24 mm pesa 8 g, se imprime en 21 minutos y cuesta 99 CZK, todo sin envío. En 90 días pasaron por la calculadora 11 soportes. Para cosas ligeras bastan PLA y cinta; para las pesadas elija PETG y tornillos; los tornillos, los tacos y la cinta no forman parte de la impresión. El modelo se descarga gratis como STL o proyecto para su impresora.',
    ],
    'steps' => [
        ['name' => 'Mida el objeto', 'text' => 'Mida el ancho y el grosor del objeto; en un mango, su diámetro. Indique las medidas reales sin reserva; la holgura la añade la herramienta.'],
        ['name' => 'Elija cómo debe sujetarlo', 'text' => 'La cuna y el bolsillo abrazan el objeto, el gancho sirve para todo lo que tenga asa o correa y la pinza agarra un mango redondo. Puede empezar por un ajuste, por ejemplo «Mando a distancia» o «Auriculares».'],
        ['name' => 'Elija la fijación', 'text' => 'Deje activados los agujeros para tornillos, o desactívelos y pegue el soporte con cinta de doble cara. Los agujeros pueden ser redondos o de ojo de cerradura para colgarlo de un tornillo en la pared.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Revise la vista previa y pase al precio exacto con «Continuar». O descargue gratis el modelo para su propia impresora.'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño de objeto admite el soporte?', 'a' => 'El ancho del objeto, de 10 a 300 mm; el grosor, de 5 a 150 mm. La pinza es solo para mangos redondos de hasta 60 mm de diámetro. Un soporte de más de 250 mm no cabe en la cama de nuestra granja; el cálculo lo avisa.'],
        ['q' => '¿Cuánto peso aguanta el soporte?', 'a' => 'No indicamos carga; depende del material, los tornillos y la pared. Para cosas pesadas elija un plástico resistente (PETG) y tornillos con tacos. La cinta de doble cara solo aguanta cosas ligeras, por ejemplo un mando o unos auriculares.'],
        ['q' => '¿Qué tornillos necesito?', 'a' => 'Los agujeros son para tornillos de 4 mm de diámetro con taco. Ni los tornillos ni los tacos forman parte de la impresión.'],
        ['q' => '¿Qué significa colgar de un tornillo en la pared?', 'a' => 'La cabeza de un tornillo que ya está en la pared pasa por el agujero ancho, el soporte baja y queda colgado en la ranura estrecha. Para quitarlo, levántelo. Va bien donde no quiere taladrar agujeros nuevos.'],
        ['q' => '¿Debo añadir holgura a las medidas?', 'a' => 'No. La herramienta añade 0,8 mm de holgura alrededor del objeto, que puede cambiar de 0,3 a 2 mm. La pinza, en cambio, es un poco más estrecha para agarrar el mango; mida el diámetro del mango con un calibre, no a ojo.'],
        ['q' => '¿Aguanta el soporte en el baño o en el exterior?', 'a' => 'En el baño y el garaje elija PETG, que soporta humedad y calor; en el exterior al sol, ASA. El PLA basta para una habitación o una oficina. El material se elige en la comparación de materiales del cálculo.'],
    ],
    'examples' => [
        'Cuna para un mando de 50 × 22 mm, 70 mm de alto, con tornillos: 27 g, 1 h 12 min, 100 CZK.',
        'Gancho para auriculares, 35 mm de ancho, 45 mm de fondo, gancho de 25 mm de alto: 11 g, 27 minutos, 99 CZK.',
        'Pinza para un palo de escoba de 24 mm de diámetro, 25 mm de alto, con tornillos: 8 g, 21 minutos, 99 CZK.',
    ],
];
