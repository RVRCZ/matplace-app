<?php

return [
    'title' => 'Revisar un modelo 3D antes de imprimir, online y gratis',
    'description' => 'Suba un modelo y sepa si el tamaño, la malla y la orientación de las caras son correctos. Cada hallazgo explica qué supone para la impresión.',
    'h1' => 'Revise su modelo antes de enviarlo a la impresora',
    'intro' => [
        'La revisión sirve para cuando tiene un modelo y no está seguro de que esté listo para imprimir. Usted sube el archivo y enseguida recibe un informe dividido en errores, recomendaciones y cosas que están bien. Cada hallazgo explica qué provocará en la impresión y cómo corregirlo.',
        'Revisamos solo lo que podemos detectar con fiabilidad: tamaño y unidades, si la malla está cerrada, caras invertidas, número de cuerpos separados y densidad de la malla. No valoramos voladizos, resistencia de las paredes ni precisión, porque dependen de la impresora, el material y la orientación. La revisión es gratis, sin registro y no cambia nada en su archivo.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Elija un archivo o arrástrelo al recuadro. Los formatos admitidos y el tamaño máximo están escritos ahí mismo.'],
        ['name' => 'Lea el informe', 'text' => 'Junto a la vista giratoria verá los hallazgos en tres grupos: «Errores», «Recomendaciones» y «Correcto».'],
        ['name' => 'Continúe al precio', 'text' => 'El botón «Ver el precio y pedir la impresión» abre el modelo en la calculadora. Allí puede pedirnos la impresión o descargar el modelo.'],
    ],
    'faq' => [
        ['q' => '¿Qué mira exactamente la revisión?', 'a' => 'Unidades equivocadas, un modelo de menos de 0,8 mm de grosor, agujeros en la malla, caras invertidas, varios cuerpos separados y una malla demasiado densa o demasiado basta. También indica si el modelo cabe en una impresora normal con un espacio de 250 × 250 × 250 mm.'],
        ['q' => '¿Garantiza la revisión que el modelo se imprimirá bien?', 'a' => 'No. Los voladizos, la resistencia de las paredes y la precisión dependen de la impresora, el material y la orientación del modelo, y la revisión no los valora.'],
        ['q' => 'La revisión encontró agujeros en la malla o caras invertidas. ¿Qué hago?', 'a' => 'Use nuestra herramienta de reparación de modelos. Cierra los agujeros, corrige las caras y usted descarga gratis el archivo reparado.'],
        ['q' => '¿Por qué la revisión dice que mi modelo mide solo una fracción de milímetro?', 'a' => 'Lo más probable es que se guardara en metros o pulgadas en lugar de milímetros. Guárdelo de nuevo en milímetros y vuelva a subirlo.'],
        ['q' => '¿Cambia la revisión mi archivo?', 'a' => 'No. La revisión solo lee el archivo y no modifica nada.'],
    ],
    'examples' => [],
];
