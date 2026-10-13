<?php

return [
    'title' => 'Molde para colar a partir de un modelo 3D, online',
    'description' => 'Convierta su modelo 3D en molde imprimible: de 2 a 4 partes o para silicona (manguito, carcasa, bandeja, dos piezas). Con respiraderos y volúmenes en ml.',
    'h1' => 'Un molde imprimible de su modelo: por partes o para silicona',
    'intro' => [
        'Usted sube un modelo 3D y la herramienta construye a su alrededor un molde listo para imprimir. Las formas sencillas se cuelan directamente en un molde impreso de dos a cuatro partes. Para figuras, bustos y otras formas con contrasalidas está el molde para silicona en cuatro tipos: manguito, carcasa que sigue el modelo y ahorra más de la mitad de la silicona, bandeja para relieves y molde de dos piezas con placa de partición y llaves.',
        'Antes de construir el molde, la herramienta mide qué parte de la superficie no soltaría un molde rígido y la marca en rojo sobre el modelo. Calcula cuánto material necesita una pieza y cuánta silicona el molde. Encuentra los lugares donde quedaría aire en la cavidad y saca respiraderos desde ellos. Un núcleo impreso hace la pieza hueca. El molde se abre como archivo nuevo: puede pedirnos la impresión o descargarlo.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre un modelo al recuadro o haga clic para elegir un archivo. El modelo debe ser un cuerpo cerrado de entre 5 y 400 mm. La herramienta marca al instante las contrasalidas en rojo e indica el volumen de la pieza y de la silicona.'],
        ['name' => 'Elija el tipo de molde', 'text' => 'Haga clic en una imagen de tipo arriba o elija en los ajustes: molde impreso por partes, o molde para silicona y su tipo. Los respiraderos, el núcleo y la pared son opcionales; la división y el corte pueden dejarse a la herramienta.'],
        ['name' => 'Cree el molde y pida la impresión', 'text' => 'Haga clic en «Crear el molde». Tarda unos segundos, hasta dos minutos con modelos grandes. «Precio e impresión» abre el molde en el cálculo, donde ve el precio, pide la impresión o descarga el archivo.'],
    ],
    'which' => [
        ['shape' => 'Forma sencilla sin contrasalidas (colgante, azulejo, botón, jabón)', 'kind' => 'Molde impreso, 2 piezas'],
        ['shape' => 'Forma cúbica o redonda con contrasalidas leves en los lados (jarrón, taza, figura con patas)', 'kind' => 'Molde impreso, 3 o 4 piezas, quizá en TPU'],
        ['shape' => 'Figura, busto, animal: contrasalidas por todos lados y quiere varias piezas', 'kind' => 'Silicona: carcasa (menos silicona) o manguito (más sencillo)'],
        ['shape' => 'Relieve, placa, posavasos, imán: un lado plano', 'kind' => 'Silicona: bandeja'],
        ['shape' => 'Forma que debe quedar limpia por todos lados y se cuela una y otra vez', 'kind' => 'Silicona: molde de dos piezas con llaves'],
    ],
    'faq' => [
        ['q' => '¿Qué es una contrasalida y por qué importa?', 'a' => 'Una contrasalida es un lugar donde el molde se engancha al desmontarlo. Un molde rígido no suelta esa pieza sin dañarla. «Rellenar contrasalidas» rellena esos lugares con material en la pieza (naranja en la vista previa) para que el molde impreso se abra; si no, elija el molde para silicona.'],
        ['q' => '¿Qué diferencia hay entre manguito, carcasa, bandeja y molde de dos piezas?', 'a' => 'Los cuatro son recipientes impresos en los que vierte silicona sobre el modelo. El manguito es una pared recta alrededor del contorno: el más sencillo, pero gasta mucha silicona. La carcasa sigue el modelo a la distancia de la pared y suele ahorrar la mitad de la silicona; se imprime en dos mitades con soportes. La bandeja es una pieza para un modelo con dorso plano. El molde de dos piezas tiene una placa de partición con llaves cónicas: vierte dos mitades que encajan entre sí y la pieza sale limpia por todos lados.'],
        ['q' => '¿Para qué son los respiraderos y cómo los hace la herramienta?', 'a' => 'Al verter, el aire queda en cada bolsa de la cavidad más alta que su entorno: en la nariz de un busto, bajo la barbilla, en un hueco. La pieza tiene burbujas allí. La herramienta encuentra esos lugares y saca canales de 2,5 mm hacia fuera: en el plano de partición de un molde impreso, como pines finos en el modelo de uno de silicona, que dejan un canal en la silicona. Tras colar, los bebederos de los canales se cortan.'],
        ['q' => '¿Qué es el núcleo y cuándo conviene?', 'a' => 'El núcleo es un modelo impreso reducido en el grosor de pared de la pieza, con un vástago hacia la abertura de vertido. Lo coloca en el molde de silicona y la pieza sale como una carcasa del grosor elegido: ahorra resina, cera o jabón, y una pieza grande pesa menos. La herramienta indica el volumen del núcleo y del material por pieza hueca.'],
        ['q' => '¿Cuánta silicona y material necesitaré?', 'a' => 'Nada más subir el modelo, la herramienta indica el volumen de la pieza en ml y la silicona para manguito y carcasa. Tras construir el molde indica los valores exactos del tipo elegido, incluido el ahorro de la carcasa frente al manguito y el volumen del núcleo. Compre una décima parte más por lo que queda en el vaso.'],
        ['q' => '¿Qué modelo no acepta la herramienta y cuánto cuesta?', 'a' => 'Un modelo menor de 5 mm o mayor de 400 mm, y un modelo que no pueda convertirse en cuerpo cerrado; repare antes un modelo con agujeros en nuestra herramienta de reparación. Crear el molde es gratis; solo paga la impresión si nos la pide. La silicona la compra usted.'],
    ],
    'examples' => [
        'Molde impreso de dos piezas con llaves de bola, abertura de vertido y respiraderos en el plano de partición',
        'Molde impreso de cuatro cuñas para una forma con contrasalidas en los lados',
        'Molde para silicona: carcasa en dos mitades que sigue el busto y ahorra más de la mitad de la silicona',
        'Molde para silicona: bandeja de una pieza para un modelo con dorso plano',
        'Molde para silicona: dos piezas con placa de partición, llaves cónicas y caja',
    ],
];
