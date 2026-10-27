<?php

// La página para la búsqueda «vaciar modelo 3d online» (docs/AP.md §1, §4). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Vaciar un STL online y gratis: carcasa con orificios',
    'heading' => 'Vaciar un modelo 3D online y gratis',
    'description' => 'Convierta un STL, 3MF, OBJ o STEP macizo en un modelo hueco con pared de 1,5 a 6 mm y orificios de drenaje. Ve al instante los gramos ahorrados. Gratis.',
    'h1' => 'Qué hace el vaciado con el modelo, cuándo compensa y dónde se detiene',
    'intro' => [
        'La herramienta quita el interior del modelo y deja una pared del grosor que elija, de 1,5 a 6 mm. Mide la distancia desde la superficie en una cuadrícula fina de 0,6 mm (más basta en modelos de más de 190 mm, y la herramienta lo dice) y la superficie interior se forma donde se alcanza el grosor de pared; por eso sigue la forma incluso en los pliegues y el hueco nunca sale a la superficie. Por fuera el modelo no cambia ni una décima de milímetro. En el fondo del hueco aparecen orificios de drenaje de 3 a 8 mm: tantos como quepan, cuatro como máximo, o el número que indique; también se pueden desactivar. Admite STL, 3MF, OBJ y STEP de 5 a 1000 mm y hasta 2 millones de triángulos; un modelo que no esté cerrado se cierra primero, igual que lo hace nuestra granja. El modelo hueco se abre como archivo nuevo y el original se conserva.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+, capas de 0,2 mm, relleno del 15 %): un cilindro macizo de ⌀ 120 × 200 mm pesa 503 g, se imprime en 8 h 14 min y cuesta 910 CZK (unos 37 EUR) con nosotros. Vaciado con pared de 2,5 mm y tres orificios pesa 223 g, se imprime en 6 h 36 min y cuesta 500 CZK (unos 20 EUR): 280 g y 410 CZK menos, igual por fuera. En impresión con resina la diferencia es aún mayor: el cilindro macizo se tragaría 2,3 litros de resina; el hueco con pared de 2,5 mm, un cuarto de litro aproximadamente (calculado a partir del volumen, no impreso). La herramienta muestra el volumen del hueco, los gramos ahorrados y la diferencia de precio justo después del vaciado; la radiografía de la vista previa muestra la pared. El modelo se descarga como STL o proyecto para el laminador, o se imprime con nosotros.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre al recuadro un STL, 3MF, OBJ o STEP de 5 a 1000 mm, o haga clic y elija el archivo.'],
        ['name' => 'Elija la pared y los orificios', 'text' => 'Grosor de pared de 1,5 a 6 mm. Orificios de drenaje en el fondo con diámetro de 3 a 8 mm y cantidad, automáticamente hasta cuatro, o ninguno.'],
        ['name' => 'Vaciar', 'text' => 'Haga clic en «Vaciar el modelo». Tarda unos segundos, hasta un minuto en modelos grandes; la página dice qué está pasando.'],
        ['name' => 'Lea lo que ahorra y continúe', 'text' => 'El volumen del hueco, los gramos y el dinero ahorrados, la radiografía de la pared. Después, descarga del STL o del proyecto para el laminador, o impresión con nosotros.'],
    ],
    'faq' => [
        ['q' => '¿Cuándo compensa vaciar y cuándo no?', 'a' => 'Una pieza pequeña para FDM déjela maciza: el laminador le pone un relleno del 15 % y el hueco ahorraría unos gramos. Compensa en figuras y bustos grandes de más de 150 mm, siempre en impresión con resina (una pieza maciza se agrieta y consume litros) y en cosas que quiera lastrar con arena o en las que quiera meter algo.'],
        ['q' => '¿Qué grosor de pared elegir?', 'a' => 'Para FDM, de 2 a 3 mm; la pared se sigue imprimiendo con relleno según el laminador, así que aguanta. Para resina, de 2 a 3 mm; para bustos grandes de más de 300 mm, de 3 a 5 mm. La herramienta no baja de 1,5 mm, esa carcasa se imprime mal.'],
        ['q' => '¿Para qué sirven los orificios de drenaje y dónde quedan?', 'a' => 'Siempre en el fondo del hueco, en el lado sobre el que el modelo se apoya en la cama. En resina, por ellos sale la resina sin curar y no se forma vacío; en FDM, por ellos sacude los soportes del hueco o vierte arena. Si no los quiere, desactívelos y el hueco queda cerrado.'],
        ['q' => '¿Cambia la forma por fuera?', 'a' => 'No. La superficie exterior queda exactamente como estaba; el hueco termina siempre al grosor elegido por debajo de ella, también en pliegues y zonas finas, donde simplemente no se forma. Las medidas que muestra la calculadora son las mismas antes y después.'],
        ['q' => '¿Qué pasa con un modelo que tiene agujeros?', 'a' => 'Antes de medir, la herramienta lo cierra igual que lo hace nuestra granja. Si no es posible, lo dice; pase antes ese modelo por la herramienta Reparar el modelo y repita el vaciado.'],
        ['q' => '¿Qué tamaño de modelo admite la herramienta?', 'a' => 'Hasta 1000 mm y 2 millones de triángulos. Por encima de 190 mm la cuadrícula de medición es más basta que 0,6 mm y la pared puede variar en algunos puntos entre una décima y medio milímetro; la herramienta lo indica junto al resultado.'],
    ],
    'examples' => [],
];
