<?php

return [
    'title' => 'Vaciar un modelo 3D: un modelo hueco con pared y drenaje',
    'description' => 'Convierta un modelo macizo en uno hueco con pared de 1,5 a 6 mm y agujeros de drenaje. La herramienta calcula los gramos y el dinero ahorrados. Gratis.',
    'h1' => 'Un modelo hueco: menos material, impresión más corta, la misma forma por fuera',
    'intro' => [
        'La herramienta quita el interior del modelo y deja la pared que elija, de 1,5 a 6 mm. La distancia a la superficie se mide en una rejilla fina (0,6 mm; más gruesa en modelos grandes, y la herramienta lo avisa) y la superficie interior se forma donde se alcanza el grosor de la pared; así conserva la forma incluso en los pliegues y la cavidad nunca sale a la superficie. Un modelo que no está cerrado se cierra antes.',
        'En la base de la cavidad se hacen agujeros de drenaje de 3 a 8 mm: tantos como quepan, cuatro como máximo, o el número que pida; también se pueden omitir. La herramienta calcula el volumen de la cavidad, los gramos de material ahorrados y la diferencia en el precio de impresión. El modelo hueco se abre como un archivo nuevo: lo imprime con nosotros o lo descarga para su impresora.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre un modelo al recuadro o haga clic para elegir un archivo: STL, 3MF, OBJ o STEP de 5 a 1000 mm.'],
        ['name' => 'Elija la pared y los agujeros', 'text' => 'Una pared de 1,5 a 6 mm; agujeros de drenaje en la base con su diámetro y número, o ninguno.'],
        ['name' => 'Vacíelo', 'text' => 'Haga clic en «Vaciar el modelo». Tarda unos segundos, hasta un minuto en modelos grandes.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Ve cuántos gramos y cuánto dinero ahorra la cavidad. La vista de rayos X muestra la pared. Descargue el modelo como STL o como proyecto para su laminador, o mándelo imprimir con nosotros.'],
    ],
    'faq' => [
        ['q' => '¿Por qué vaciar un modelo si el laminador tiene relleno?', 'a' => 'El relleno sirve para FDM; la cavidad es para la impresión en resina (una pieza maciza se agrietaría y gastaría litros), para figuras grandes y para cosas que quiera rellenar o lastrar con arena. Un modelo hueco gasta menos material incluso con relleno en el laminador, porque el relleno solo se imprime en la pared.'],
        ['q' => '¿Qué grosor de pared elegir?', 'a' => 'De 2 a 3 mm para FDM (la pared se imprime luego con el relleno del laminador), de 2 a 3 mm para resina, de 3 a 5 mm para bustos grandes. Por debajo de 1,5 mm la herramienta no va.'],
        ['q' => '¿Para qué son los agujeros de drenaje?', 'a' => 'En la impresión en resina la resina sin curar sale y no se forma vacío; en FDM saca por ellos los soportes de la cavidad. Si no los quiere, desactívelos; la cavidad queda cerrada.'],
        ['q' => '¿Qué pasa con un modelo con agujeros?', 'a' => 'Antes de medir, la herramienta lo cierra con el mismo método que usa nuestra granja. Si no lo consigue, lo dice; repare ese modelo primero con la herramienta de reparación.'],
        ['q' => '¿Qué tamaño de modelo admite la herramienta?', 'a' => 'Hasta 1000 mm y 2 millones de triángulos. Por encima de 190 mm la rejilla de medición es más gruesa que 0,6 mm y la pared puede variar entre una décima y medio milímetro; la herramienta lo avisa.'],
        ['q' => '¿Cuánto cuesta y cómo recibo la impresión?', 'a' => 'Vaciar es gratis. Solo paga la impresión si la pide con nosotros, con crédito prepagado. Enviamos la impresión por Packeta a un punto de recogida o a una dirección en la UE.'],
    ],
    'examples' => [],
];
