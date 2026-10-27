<?php

// La página para la búsqueda «revisar archivo stl antes de imprimir» (docs/AP.md §1, §3). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Revisar un archivo STL antes de imprimir, online y gratis',
    'heading' => 'Revisar un archivo STL antes de imprimir',
    'description' => 'Suba un modelo y sepa en segundos si las unidades, la malla y las caras son correctas y si cabe en la impresora. Cada hallazgo dice qué supone al imprimir.',
    'h1' => 'Qué mira la revisión, qué no valora y cómo leer el informe',
    'intro' => [
        'La revisión es para cuando tiene un STL, 3MF, OBJ o STEP y no sabe si la impresora lo aceptará. Usted sube el archivo y en unos segundos recibe un informe en tres grupos: errores, recomendaciones y lo que está bien, cada hallazgo con lo que provocará en la impresión y cómo corregirlo. Vigila las unidades (un modelo de menos de 1 mm casi seguro se guardó en metros o pulgadas; uno de más de 2 m, al revés), la medida más fina por debajo de 0,8 mm, que una boquilla de 0,4 mm no imprime, si la malla está cerrada, las caras invertidas, el número de cuerpos separados, una malla de más de 1,5 millones de triángulos que frena el laminador y una malla de menos de 40 triángulos que da una impresión facetada. También dice si el modelo cabe en una impresora con 250 × 250 × 250 mm; en un juego de piezas mide la mayor. No valora voladizos, resistencia de las paredes ni precisión, porque dependen de la impresora, el material y la orientación. Solo lee el archivo y no cambia nada.',
        'Un ejemplo del 27 de octubre de 2026: un busto de 60 × 52 × 78 mm al que recortamos tres agujeros e invertimos parte de las caras. La revisión devolvió tres líneas: tamaño 60 × 52 × 78 mm correcto, el error «la malla no está cerrada» y la recomendación «51 cuerpos separados». Con un botón lo enviamos a la reparación; después la malla está cerrada, el cuerpo es uno y la revisión queda limpia. Un modelo sano sigue adelante: el botón del precio lo abre en la calculadora, donde ve los gramos, el tiempo de impresión y nuestro precio, o descarga el proyecto para su propia impresora. La revisión es gratis, sin cuenta y sin límite de archivos.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Elija un STL, 3MF, OBJ o STEP o arrástrelo al recuadro. El tamaño máximo del archivo está escrito ahí mismo.'],
        ['name' => 'Lea el informe', 'text' => 'Junto a la vista giratoria están los hallazgos en tres grupos: «Errores», «Recomendaciones» y «Correcto», cada uno con su explicación para la impresión.'],
        ['name' => 'Repare, o continúe al precio', 'text' => 'Los agujeros y las caras invertidas van a la reparación con un botón. Un modelo sano se abre en la calculadora, donde lo encarga o lo descarga.'],
    ],
    'faq' => [
        ['q' => '¿Qué mira exactamente la revisión?', 'a' => 'Unidades (menos de 1 mm y más de 2 m es error, menos de 5 mm un aviso), la medida más fina por debajo de 0,8 mm, agujeros en la malla, caras invertidas, varios cuerpos separados, una malla de más de 1,5 millones o de menos de 40 triángulos y si el modelo cabe en 250 × 250 × 250 mm.'],
        ['q' => '¿Garantiza la revisión que el modelo se imprimirá bien?', 'a' => 'No. Los voladizos, la resistencia de las paredes y la precisión dependen de la impresora, el material y la orientación del modelo, y la revisión no los valora. Solo dice lo que se puede detectar con fiabilidad en el archivo.'],
        ['q' => 'La revisión encontró agujeros en la malla o caras invertidas. ¿Qué hago?', 'a' => 'El botón junto al hallazgo envía el modelo a la reparación. Cierra los agujeros, gira las caras y usted descarga gratis el STL reparado; el informe muestra los números de antes y después.'],
        ['q' => '¿Por qué la revisión dice que mi modelo mide solo una fracción de milímetro?', 'a' => 'Se guardó en metros o pulgadas en lugar de milímetros. Guárdelo de nuevo en milímetros, o escálelo en la calculadora por 1000 (metros) o 25,4 (pulgadas).'],
        ['q' => 'El modelo no cabe en la impresora. ¿Qué hago con él?', 'a' => 'La revisión dice qué medida supera los 250 mm. O lo reduce con la escala en la calculadora, o lo corta con la herramienta Dividir el modelo en piezas con pasadores que sí caben.'],
        ['q' => 'La revisión señala varios cuerpos separados. ¿Es un fallo?', 'a' => 'En una caja con tapa o en un juego de piezas es intencionado, y la revisión lo sabe en nuestras propias herramientas. En un modelo descargado suelen ser fragmentos de una malla rota o motas; la reparación los une o los elimina.'],
    ],
    'examples' => [],
];
