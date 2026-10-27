<?php

// La página para la búsqueda «reparar archivo stl online» (docs/AP.md §1, §3). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Reparar un archivo STL online y gratis: agujeros y caras',
    'heading' => 'Reparar un archivo STL online y gratis',
    'description' => 'Suba el STL, 3MF u OBJ que su laminador rechaza. Cerramos agujeros, corregimos caras invertidas, unimos cuerpos rotos y descarga el STL reparado gratis.',
    'h1' => 'Qué repara la herramienta, qué deja como está y cuánto tarda',
    'intro' => [
        'La herramienta es para un modelo descargado de Printables, Thingiverse o MakerWorld, para un escaneo y para un archivo exportado con errores, que el laminador rechaza o imprime con agujeros. Usted sube un STL, 3MF u OBJ de hasta 120 MB y la reparación se hace sola, sin ajustes. Cierra los agujeros de la superficie, corrige las caras invertidas, quita las caras duplicadas, las de área nula y las motas sueltas fuera del modelo, y arregla las aristas compartidas por más de dos caras. Cada cuerpo del archivo se repara por separado, así una caja con su tapa sigue siendo dos piezas. Una reparación que movería el tamaño de un cuerpo más de un 2 % se descarta y el cuerpo queda como estaba: ni la forma ni los milímetros cambian. Recibe un informe de lo que fallaba y de lo que cambió, con una tabla de antes y después: aristas abiertas, aristas compartidas por varias caras, número de cuerpos y de triángulos. El archivo original queda intacto.',
        'Un ejemplo del 27 de octubre de 2026: un busto de 60 × 52 × 78 mm con 4.053 triángulos, al que recortamos tres agujeros, duplicamos 25 caras e invertimos 40. Un laminador señala esa malla como no estanca. La reparación tardó 1,4 segundos: cerró 36 aristas abiertas, arregló 75 aristas compartidas por varias caras, quitó 25 caras duplicadas y unió 51 fragmentos sueltos en un solo cuerpo cerrado de 80,6 cm³. El tamaño se mantuvo en 60 × 52 × 78 mm. El STL reparado se descarga gratis y sin cuenta, o se abre en la calculadora con un botón: en nuestra granja se imprimiría en una hora aproximadamente por unas 110 CZK (unos 4,50 EUR, PLA, sin envío); si tiene impresora propia, descarga el proyecto para el laminador.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Elija un STL, 3MF u OBJ de hasta 120 MB o arrástrelo al recuadro. No hay nada que ajustar.'],
        ['name' => 'Espere la reparación', 'text' => 'Un modelo normal está listo en unos segundos; un archivo con millones de triángulos, en menos de un minuto. La página dice qué está pasando.'],
        ['name' => 'Lea el informe', 'text' => 'El resultado (reparado, mejorado, sin cambios), la lista de cambios y la tabla «Antes» y «Después»: aristas abiertas, aristas compartidas por varias caras, cuerpos, triángulos, volumen.'],
        ['name' => 'Descargue o pida la impresión', 'text' => 'Con el botón «Descargar el STL reparado» guarda el archivo. El segundo botón lo abre en la calculadora, donde ve los gramos, el tiempo de impresión y el precio.'],
    ],
    'faq' => [
        ['q' => 'Mi laminador dice «non-manifold», «open edges» o «mesh is not watertight». ¿Sirve esto?', 'a' => 'Sí, la reparación es justo para esos avisos. Las aristas abiertas son agujeros en la superficie, non-manifold son aristas compartidas por más de dos caras, y la herramienta arregla ambas cosas. El informe muestra cuántas aristas cerró y cuántas arregló.'],
        ['q' => '¿Cambia la reparación la forma o el tamaño del modelo?', 'a' => 'No. Los agujeros se cierran en el plano de su borde y las caras solo se giran. Si una reparación moviera el tamaño de un cuerpo más de un 2 %, se descarta y el cuerpo queda como estaba; el informe lo indica.'],
        ['q' => '¿Qué no puede reparar la herramienta?', 'a' => 'Una parte grande de superficie que falta no tiene de dónde reconstruirse, y una superficie sin grosor (una sola pared sin volumen) no se puede cerrar. El informe dice entonces «mejorado» o «sin cambios», y el resto es trabajo manual en Blender, Meshmixer o un programa CAD.'],
        ['q' => '¿En qué formato recibo el modelo reparado?', 'a' => 'Siempre en STL, aunque haya subido un 3MF o un OBJ. El STL solo contiene la forma; los colores, los nombres de las piezas y los ajustes de impresión del 3MF no se conservan.'],
        ['q' => '¿Es gratis la reparación y necesito una cuenta?', 'a' => 'La reparación y la descarga son gratis y sin cuenta, sin marca de agua y sin límite de archivos. El archivo subido sirve solo para la reparación y el cálculo y no se entrega a nadie.'],
        ['q' => '¿Por qué el informe no mostraba el volumen antes de la reparación?', 'a' => 'El volumen solo se puede calcular a partir de una malla cerrada. Mientras el modelo tiene agujeros, el volumen, y por tanto los gramos y el precio, no tienen sentido; tras la reparación la calculadora los obtiene y el laminador recibe un archivo limpio.'],
    ],
    'examples' => [],
];
