<?php

return [
    'title' => 'Beneficio al vender impresiones 3D: comisiones y margen',
    'description' => 'Lo que le queda de una venta: el precio menos las comisiones de Etsy, Fler, una tienda o un mercado, el envío, el IVA y el coste de la pieza.',
    'h1' => 'Cuánto le queda de verdad de una venta',
    'intro' => [
        'La calculadora toma el precio de venta, el coste de la pieza y la plataforma y resta todo lo que alguien se lleva por el camino: la cuota del anuncio, la comisión de la venta, el procesamiento del pago, el cambio de moneda, la cuota mensual de una tienda repartida entre las piezas vendidas o el puesto de un mercado, el envío que paga de más y el IVA si está dado de alta. Queda el beneficio por pieza, el margen sobre el precio y cuántas piezas al mes cubren sus costes fijos.',
        'Las tarifas de las plataformas se guardan con fecha y fuente, y la página dice junto al resultado a qué día corresponden; compárelas con la lista de precios actual antes de decidir. Tres precios lado a lado muestran qué hace una décima parte menos o más. El coste de la pieza viene de la herramienta de coste y el resultado pasa al plan de ventas. Todo se calcula en el navegador, nada se guarda.',
    ],
    'steps' => [
        ['name' => 'Elija dónde vende', 'text' => 'Etsy, Fler, Shopify, su propia tienda, un mercado o feria, o la granja matplace. Marque si está dado de alta en el IVA y si vende en moneda extranjera.'],
        ['name' => 'Indique la venta', 'text' => 'El precio de venta, el coste de la pieza, un descuento, el envío cobrado al cliente y el envío que paga de verdad.'],
        ['name' => 'Indique el mes', 'text' => 'Cuántas piezas vende al mes y sus costes fijos; en un mercado, la cuota del puesto y las piezas al día.'],
        ['name' => 'Lea el resultado', 'text' => 'Beneficio por pieza, margen, ingreso neto, desglose de comisiones, punto de equilibrio y tres precios lado a lado. Páselo al plan de ventas.'],
    ],
    'faq' => [
        ['q' => '¿Qué comisiones de Etsy cuentan?', 'a' => 'La cuota del anuncio de 0,20 USD, la comisión del 6,5 % sobre el precio con envío, el procesamiento del pago para la República Checa del 4 % + 10 Kč y el cambio de moneda del 2,5 % si vende en euros o dólares. Los Offsite Ads no se cuentan; la página muestra la fecha de las tarifas.'],
        ['q' => '¿Y Fler, Shopify y una tienda propia?', 'a' => 'Fler cobra una comisión del 11 % sobre la mercancía. Shopify, un plan mensual y una pasarela de pago con la comisión de Shopify por pasarela externa. Una tienda propia, un alquiler (Shoptet) y una pasarela de pago. Cada tarifa lleva su fecha y un enlace a la lista de precios.'],
        ['q' => '¿Cómo se calcula el IVA?', 'a' => 'Quien está dado de alta paga un 21 % sobre el precio con envío; la calculadora lo saca de lo que pagó el cliente. Si no está dado de alta, deje la casilla vacía; las plataformas gestionan el IVA de sus comisiones a su manera.'],
        ['q' => '¿Qué es el punto de equilibrio?', 'a' => 'Cuántas piezas al mes debe vender para que el beneficio por pieza pague los costes fijos: alquiler, software, publicidad. Si el beneficio por pieza es cero o negativo, no se cubren nunca.'],
        ['q' => '¿De dónde sale el coste de la pieza?', 'a' => 'De la herramienta de coste, que suma filamento, electricidad, desgaste, fallos y trabajo. Cuando imprime la granja matplace, su coste es nuestro precio del cálculo.'],
        ['q' => '¿Las tarifas están al día?', 'a' => 'Se guardan con fecha y fuente, y la página muestra la fecha junto al resultado. Las plataformas cambian sus tarifas; compárelas con su lista de precios antes de decidir.'],
    ],
    'examples' => [],
];
