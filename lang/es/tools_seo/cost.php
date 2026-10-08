<?php

return [
    'title' => 'Coste de la impresión 3D en su propia impresora: calculadora',
    'description' => 'Lo que de verdad le cuesta una impresión: filamento, electricidad, desgaste, fallos y trabajo. Precio recomendado con margen y, al lado, nuestro precio.',
    'h1' => 'Cuánto cuesta una impresión en su impresora y por cuánto venderla',
    'intro' => [
        'La calculadora suma todo lo que se esconde en una pieza: el filamento según los gramos del slicer, la electricidad según la potencia de la impresora y el tiempo de impresión, la impresora amortizada en las horas de su vida, la parte de impresiones fallidas que pagan las buenas, su trabajo en minutos y otros costes como embalaje o etiqueta. El resultado es el coste por pieza y un precio recomendado con el margen que elija.',
        'Desde un cálculo de impresión en matplace se rellenan los gramos y las horas, y junto a su coste ve por cuánto imprimimos nosotros la misma pieza. Quien tiene perfil de impresora recibe, tras iniciar sesión, su tarifa por hora y el precio del filamento. Todo se calcula en el navegador, nada se guarda en el servidor y la página recuerda los ajustes hasta la próxima visita.',
    ],
    'steps' => [
        ['name' => 'Indique la pieza', 'text' => 'El precio del filamento por kilogramo, los gramos y las horas del slicer. Desde un cálculo de impresión se rellenan solos.'],
        ['name' => 'Indique la impresora', 'text' => 'Potencia, precio de la electricidad, precio de la impresora y las horas que imprime antes de amortizarla; la parte de impresiones fallidas.'],
        ['name' => 'Indique el trabajo', 'text' => 'Su tarifa por hora y los minutos por pieza: preparar, retirar, limpiar, embalar; otros costes por pieza.'],
        ['name' => 'Elija el margen', 'text' => 'Un porcentaje sobre el coste. La página muestra al instante el coste, el precio y lo que gana una hora de impresión; envíe el precio al beneficio del vendedor o al plan.'],
    ],
    'faq' => [
        ['q' => '¿Por qué contar las impresiones fallidas?', 'a' => 'Cada impresión fallida gastó filamento, electricidad y tiempo de la impresora. Si falla una de cada veinte, las diecinueve buenas la cargan; la calculadora lo suma al coste por pieza.'],
        ['q' => '¿Cómo estimo la vida de la impresora?', 'a' => 'Una impresora de mesa funciona miles de horas antes de una reparación mayor o de sustituirla. 5 000 horas son unos tres años a unas horas al día; dé menos a una impresora barata y más a una industrial.'],
        ['q' => '¿Cuál es el margen correcto?', 'a' => 'El margen paga las comisiones de la plataforma, el envío extra y el riesgo. Para vender en Etsy o Fler es habitual un margen del 40 al 100 %; la herramienta Beneficio del vendedor lo muestra con exactitud.'],
        ['q' => '¿De dónde saco los gramos y las horas?', 'a' => 'Del slicer tras laminar el modelo, o de un cálculo de impresión en matplace: suba el modelo, el cálculo lo lamina y un botón le trae aquí con los números rellenados.'],
        ['q' => '¿Qué significa «imprimirlo con nosotros»?', 'a' => 'El precio del cálculo para la misma pieza en nuestra granja, material y tiempo incluidos. No incluye su trabajo ni los fallos; sirve para ver si imprimir en casa compensa.'],
        ['q' => '¿Guardan mis números?', 'a' => 'No. La calculadora calcula en el navegador y recuerda los ajustes solo en su navegador. Nada va al servidor.'],
    ],
    'examples' => [],
];
