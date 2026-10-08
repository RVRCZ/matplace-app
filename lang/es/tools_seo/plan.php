<?php

return [
    'title' => 'Plan de ventas de impresiones 3D: ingresos y beneficio',
    'description' => 'Productos con coste, precio y piezas al mes, costes fijos y temporada: ingresos y beneficio por mes, punto de equilibrio, gráfico, CSV y PDF.',
    'h1' => 'Un año de ventas en una tabla, con los pasos de esta semana',
    'intro' => [
        'El planificador toma sus productos, el coste y el precio por pieza y cuántas vende en un mes normal, los costes fijos y la temporada (uniforme, Navidad, verano y mercados, escuela, o sus propios múltiplos por mes) y calcula doce meses por delante: ingresos, costes, beneficio y su acumulado, el mes en que entra en positivo, y un gráfico. Las cifras cambian mientras escribe.',
        'El planificador también lee en el plan lo que falta y arma una lista de pasos para esta semana: calcular un coste, poner precio, fotografiar, publicar, elegir un canal. Los pasos marcados se recuerdan. El plan se queda en el navegador; con sesión iniciada lo guarda en su cuenta y lo abre en otro sitio. Descárguelo como CSV para una hoja de cálculo o como PDF de una página.',
    ],
    'steps' => [
        ['name' => 'Indique los productos', 'text' => 'Nombre, coste y precio por pieza (de las herramientas de coste y beneficio), piezas al mes; marque lo que ya está fotografiado y publicado.'],
        ['name' => 'Costes fijos y canal', 'text' => 'Lo que paga al mes aunque no venda; el canal principal de venta y el primer mes del plan.'],
        ['name' => 'Elija la temporada', 'text' => 'Uniforme, Navidad, verano y mercados, escuela, o su propio múltiplo para cada mes.'],
        ['name' => 'Lea y descargue', 'text' => 'Beneficio e ingresos del año, punto de equilibrio, gráfico y tabla; los pasos de la semana. CSV, PDF, guardar en la cuenta.'],
    ],
    'faq' => [
        ['q' => '¿Cómo funciona la temporada?', 'a' => 'Cada mes tiene un múltiplo de un mes normal. La Navidad duplica noviembre y sube diciembre aún más; verano y mercados suben de mayo a septiembre. Una temporada propia admite doce números suyos.'],
        ['q' => '¿Qué es el punto de equilibrio?', 'a' => 'El mes del plan en que el beneficio acumulado supera por primera vez el cero: hasta entonces los costes fijos pesan más que lo que ganan las ventas. Si no ocurre en el año, el plan lo dice.'],
        ['q' => '¿De dónde salen el coste y el precio?', 'a' => 'De la herramienta de coste y de la de beneficio del vendedor; los enlaces están en el plan. Un producto traído de allí tiene sus números rellenados.'],
        ['q' => '¿Qué hace la lista de pasos?', 'a' => 'El planificador mira lo que falta en el plan: un producto sin coste o sin precio, un precio por debajo del coste, sin foto, sin publicar, sin canal elegido, sin costes fijos. Con eso hace una lista para marcar esta semana.'],
        ['q' => '¿Dónde se guarda el plan?', 'a' => 'En el navegador, así que sobrevive a una recarga. Con sesión iniciada lo guarda en su cuenta con un nombre, hasta 20 planes, y los abre en otro dispositivo.'],
        ['q' => '¿Cuánto cuesta?', 'a' => 'El planificador, el CSV y el PDF son gratis. Solo paga una impresión si nos la encarga.'],
    ],
    'examples' => [],
];
