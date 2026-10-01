<?php

return [
    'title' => 'Plantilla para pintar con su propio texto o motivo',
    'description' => 'Escriba un texto o suba un motivo y la herramienta crea una plantilla. Unos puentes sujetan el interior de las letras. La imprimimos o la descarga.',
    'h1' => 'Plantilla con texto o motivo que no se desarma',
    'intro' => [
        'La herramienta recorta un texto o un motivo en una placa fina: una plantilla para pintar, estarcir o pulverizar. El texto admite dos líneas de 24 caracteres cada una, o puede subir un SVG o una imagen sencilla. El interior de letras como la O o la A se caería de la plantilla. Por eso la herramienta lo une al marco con puentes estrechos e indica cuántos ha añadido.',
        'El motivo mide de 30 a 250 mm de ancho, el margen de 5 a 40 mm y el grosor de 0,8 a 3 mm. La vista previa muestra exactamente la plantilla que recibirá, con sus puentes. Puede pedir la plantilla impresa en nuestra granja de impresión o descargar el modelo gratis para su impresora. Para uso repetido recomendamos PETG: es más flexible y la pintura se lava mejor.',
    ],
    'steps' => [
        ['name' => 'Escriba texto o suba motivo', 'text' => 'Escriba el texto en una o dos líneas. En lugar de texto puede subir un SVG con formas rellenas o una imagen con un motivo oscuro sobre fondo claro.'],
        ['name' => 'Ajuste tamaño y margen', 'text' => 'Indique el ancho del motivo y el margen alrededor. El alto de la plantilla se calcula según el motivo.'],
        ['name' => 'Revise los puentes', 'text' => 'La vista previa muestra por dónde pasan los puentes y debajo aparece su número. El ancho de los puentes, de 0,8 a 3 mm, se cambia en la sección «Grosor de paredes y más».'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Pida la impresión a nuestra granja de impresión y páguela con crédito prepagado. O descargue el modelo gratis como archivo STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Por qué hay puentes en las letras?', 'a' => 'Las letras cerradas, como la O, la A o la B, tienen un interior que se caería al recortarlas. Un puente lo sujeta al marco. Deja una franja fina sin pintar, que se puede retocar con un pincel.'],
        ['q' => '¿Qué tamaño puede tener la plantilla?', 'a' => 'El motivo puede medir de 30 a 250 mm de ancho y 300 mm de alto como máximo, más el margen. Una plantilla grande puede no caber en la placa de impresión; el cálculo del paso siguiente lo indica.'],
        ['q' => '¿Con qué material conviene imprimir la plantilla?', 'a' => 'Para uso repetido recomendamos PETG: es más flexible que el plástico corriente PLA y la pintura se lava mejor. La plantilla se imprime tumbada y sin soportes.'],
        ['q' => '¿Qué motivo puedo subir?', 'a' => 'Un SVG con formas rellenas de hasta 400 kB, o una imagen PNG, JPG o WebP con un motivo oscuro sobre fondo claro. Se recorta lo que es oscuro en la imagen; la opción «Invertir claro y oscuro» lo cambia. La herramienta no acepta una fotografía con tonos continuos.'],
        ['q' => '¿Se puede cambiar la tipografía de la plantilla?', 'a' => 'No, la plantilla usa una sola tipografía sin serifa en negrita. Para otra letra, prepare el texto en un programa de dibujo, conviértalo en trazados con relleno y súbalo como SVG.'],
        ['q' => '¿Cómo se paga y cómo se recibe la impresión?', 'a' => 'Se paga con crédito prepagado que usted recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a domicilio en toda la UE.'],
    ],
    'examples' => [
        'Plantilla BOA 8 para marcar cajas, 144 × 52 mm, con puentes en las letras.',
        'Plantilla FRAGILE para cajas y embalajes, 184 × 50 mm, 1,2 mm de grosor.',
        'Plantilla de dos líneas No. 27, para un número de casa, 124 × 130 mm.',
    ],
];
