<?php

return [
    'title' => 'Molde para colar a partir de un modelo 3D, online',
    'description' => 'Convierta su modelo 3D en un molde imprimible: de dos a cuatro partes o para silicona. La herramienta mide las contrasalidas y el volumen por pieza.',
    'h1' => 'Un molde imprimible de su modelo: por partes o para silicona',
    'intro' => [
        'La herramienta es para quien quiere colar su propia forma y necesita un molde. Usted sube un modelo 3D y la herramienta construye a su alrededor un molde listo para imprimir. Las formas sencillas se cuelan directamente en un molde impreso de dos a cuatro partes. Para figuras y otras formas con contrasalidas está el molde para silicona.',
        'Antes de construir el molde, la herramienta mide qué parte de la superficie no soltaría un molde rígido y la marca en rojo sobre el modelo. Recibe un molde con llaves que mantienen las partes alineadas, un orificio de llenado y la cantidad de material necesaria para una pieza. El molde se abre como archivo nuevo. Puede pedirnos la impresión o descargarlo.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre un modelo al recuadro o haga clic para elegir un archivo. El modelo debe ser un cuerpo cerrado de entre 5 y 400 mm.'],
        ['name' => 'Mire las contrasalidas', 'text' => 'La herramienta mide lo que un molde impreso no soltaría y marca esos puntos en rojo. También muestra el porcentaje de contrasalidas con 2, 3 y 4 partes.'],
        ['name' => 'Elija el tipo de molde', 'text' => 'Elija un molde impreso o un molde para silicona, la pared del molde y el número de partes. La división y la posición del corte pueden quedarse en automático.'],
        ['name' => 'Cree el molde', 'text' => 'Haga clic en «Crear el molde». La construcción tarda unos segundos, hasta dos minutos con modelos grandes.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'El botón «Precio e impresión del molde» abre el molde en la calculadora. Allí verá el precio, podrá pedir la impresión o descargar el archivo.'],
    ],
    'faq' => [
        ['q' => '¿Qué es una contrasalida y por qué importa?', 'a' => 'Una contrasalida es un punto donde el molde se engancha al desmontarlo. Un molde rígido no puede soltar una pieza así sin dañarla.'],
        ['q' => '¿Cuándo conviene el molde para silicona?', 'a' => 'Cuando la herramienta indica que la forma no saldrá de un molde impreso ni con más partes. Usted imprime una base con el modelo y un manguito, vierte silicona sobre el modelo y cuela en el molde blando que retira de él. La silicona la compra usted; la herramienta calcula cuánta hace falta.'],
        ['q' => '¿Qué hace la opción «Rellenar las contrasalidas»?', 'a' => 'Los puntos que retendrían el molde se rellenan de material en la pieza, así el molde impreso se puede desmontar. La pieza difiere del modelo en esos puntos. Antes verá el resultado en una vista previa, con el material añadido en naranja.'],
        ['q' => '¿Con qué material se imprime el molde?', 'a' => 'Una forma sin contrasalidas, con un plástico rígido común. Con contrasalidas pequeñas la herramienta recomienda TPU flexible, porque un molde rígido rompería la pieza.'],
        ['q' => '¿Qué modelos no puede procesar la herramienta?', 'a' => 'Un modelo de menos de 5 mm o de más de 400 mm, y un modelo que no se puede convertir en un cuerpo cerrado. Si el modelo tiene agujeros, repárelo antes con nuestra herramienta de reparación.'],
        ['q' => '¿Cuánto cuesta crear un molde?', 'a' => 'Crear el molde es gratis. Solo paga la impresión si nos la pide.'],
    ],
    'examples' => [],
];
