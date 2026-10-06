<?php

return [
    'title' => 'Reparar un modelo 3D online: STL, 3MF y OBJ gratis',
    'description' => 'Suba el STL, 3MF u OBJ que su programa de corte rechaza. Cerramos agujeros, corregimos caras invertidas y usted descarga gratis el STL reparado.',
    'h1' => 'Reparamos el modelo que su programa de corte rechaza',
    'intro' => [
        'La herramienta es para modelos descargados de internet, escaneados o exportados con errores, que el programa de corte rechaza o imprime mal. Usted sube un archivo STL, 3MF u OBJ y la reparación se hace sola. Cerramos los agujeros de la superficie, corregimos las caras invertidas y quitamos las caras duplicadas, las de área nula y las motas sueltas fuera del modelo.',
        'Recibe un informe de lo que fallaba y de lo que hemos cambiado, con una tabla de valores antes y después de la reparación. Puede descargar gratis el modelo reparado en STL o abrirlo en la calculadora y pedirnos la impresión. El archivo original queda intacto. La reparación no cambia la forma ni el tamaño del modelo.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Elija un archivo o arrástrelo al recuadro. El archivo puede tener como máximo 120 MB.'],
        ['name' => 'Espere la reparación', 'text' => 'La reparación se hace sola y no hay nada que ajustar. Con archivos grandes puede tardar un minuto.'],
        ['name' => 'Lea el informe', 'text' => 'Verá el resultado, la lista de cambios y una tabla con las columnas «Antes» y «Después»: aristas abiertas, caras invertidas, número de cuerpos y de triángulos.'],
        ['name' => 'Descargue o pida la impresión', 'text' => 'Con el botón «Descargar el STL reparado» guarda el modelo. El segundo botón lo abre en la calculadora, donde verá el precio y podrá pedir la impresión.'],
    ],
    'faq' => [
        ['q' => '¿Es gratis la reparación del modelo?', 'a' => 'Sí. La reparación y la descarga del archivo reparado son gratis y sin registro.'],
        ['q' => '¿Qué fallos repara la herramienta?', 'a' => 'Agujeros en la superficie, caras invertidas, caras duplicadas, caras de área nula, aristas compartidas por más de dos caras y motas sueltas fuera del modelo. Cada cuerpo del archivo se repara por separado, así una caja y su tapa siguen siendo dos piezas.'],
        ['q' => '¿Cambia la reparación la forma o el tamaño del modelo?', 'a' => 'No. Una reparación que cambiaría la forma o el tamaño se descarta y el cuerpo queda como estaba.'],
        ['q' => '¿Y si el modelo no se puede reparar?', 'a' => 'Algunos fallos no se pueden reparar automáticamente y el informe lo dice. Lo que queda necesita un arreglo manual en un programa de modelado. Aun así puede intentar imprimir; los programas de corte suelen tolerar fallos pequeños.'],
        ['q' => '¿En qué formato recibo el modelo reparado?', 'a' => 'Siempre en STL, aunque haya subido un 3MF o un OBJ. El formato STL solo contiene la forma, sin colores ni ajustes de impresión.'],
    ],
    'examples' => [],
];
