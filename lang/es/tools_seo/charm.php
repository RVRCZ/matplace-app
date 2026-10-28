<?php

// La página para la búsqueda «colgante personalizado impresión 3d» (docs/AP.md §12). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Colgante con su imagen o nombre, impreso en 3D, gratis',
    'heading' => 'Colgante con su propia imagen o nombre, impreso en 3D: para llaves, bolso y mochila',
    'description' => 'Una imagen, foto o nombre se convierte en un colgante en colores de filamento con ojal en el contorno. Para llaves, bolso o regalo. Impresión o descarga.',
    'h1' => 'Cómo nace el colgante, dónde va el ojal y cuánto cuesta',
    'intro' => [
        'La herramienta convierte una imagen o un nombre en un colgante para llaves, bolso, mochila, tirador de cremallera o etiqueta de regalo. Suba un PNG, JPG, WebP o SVG, elija un motivo de la biblioteca o escriba un nombre con una de treinta tipografías; los trozos sueltos de una tipografía se unen con un puente para que el colgante quede entero. El fondo desaparece solo y la imagen se reduce a 1 a 8 colores de filamentos que tenemos en la granja. El colgante sigue el contorno de la imagen, o es un círculo o un rectángulo, de 20 a 120 mm de ancho y de 2 a 6 mm de grosor, con un marco de hasta 3 mm alrededor de la imagen. El ojal se mueve por el contorno con un deslizador o arrastrándolo en la vista previa, con un agujero de 2 a 6 mm y una pared de 1,5 a 3 mm. Los colores van uno sobre otro, cada uno 0,6 mm más alto si no lo cambia.',
        'Un ejemplo de la calculadora en producción (28 de octubre de 2026, PLA): el fantasma alegre de la biblioteca, de 45 mm de ancho y 3 mm de grosor, pesa 3 g, se imprime en 7 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, el pedido mínimo, sin envío (cálculo /c/mca5ddgfzctt); el nombre Ela en caligráfica, de 60 mm de ancho, pesa 3,7 g y se imprime en 12 minutos (cálculo /c/gmfsgcpwmjl8). Varias unidades del mismo diseño se indican como cantidad y el precio por pieza baja; colgantes con nombres distintos son diseños distintos. Nuestra granja imprime hasta cuatro colores en una impresión, la base y tres más; confirma la bobina de cada color al encargar. El STL, un ZIP con un STL por color y el proyecto para el slicer se descargan gratis, sin registro.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen o escriba un nombre', 'text' => 'Suba una imagen, elija una de la biblioteca o reutilice una subida antes. En lugar de una imagen puede escribir un nombre o un texto corto en una o dos líneas.'],
        ['name' => 'Afine el contorno y los colores', 'text' => 'Un deslizador de 0 a 100 fija cuánto fondo desaparece; los bordes se pueden suavizar hasta 1 mm. Elija el número de colores, asigne a cada uno un filamento en la ventana de colores y, si quiere, fusione dos colores en uno.'],
        ['name' => 'Ajuste el tamaño y el ojal', 'text' => 'Indique el ancho, el grosor y un marco de hasta 3 mm alrededor de la imagen, con borde biselado si lo quiere. Agarre el ojal en la vista previa y arrástrelo al punto del que debe colgar.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el cálculo tras «Continuar» muestra el precio y el tiempo exactos. También puede descargar el modelo como STL, ZIP con un STL por color o proyecto para el slicer, gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Puedo usar solo un nombre en lugar de una imagen?', 'a' => 'Sí. Escriba una o dos líneas y elija una de treinta tipografías, de las sans serif en negrita a las manuscritas. La herramienta une los trozos sueltos con un pequeño puente para que el colgante quede entero, y lo avisa.'],
        ['q' => '¿Dónde puede ir el ojal y qué paso por él?', 'a' => 'En cualquier punto del contorno: con un deslizador o arrastrándolo en la vista 3D. El agujero mide de 2 a 6 mm, 3 mm por defecto, y la pared del aro de 1,5 a 3 mm. Por un agujero de 3 mm pasan una anilla de llaves corriente y un mosquetón de mochila; ninguno forma parte de la impresión.'],
        ['q' => '¿En qué se diferencia del llavero?', 'a' => 'El llavero es una placa con un nombre y una forma de una lista; el colgante sigue el contorno de su imagen o de su letra y lleva el ojal donde quiera. Para una etiqueta de regalo o un tirador de cremallera, el colgante; para llaves con un nombre en placa, el llavero.'],
        ['q' => '¿Cómo se imprimen los colores?', 'a' => 'Los colores van uno sobre otro, cada uno de 0,4 a 1,2 mm más alto. Cada capa es así de un solo filamento, que se cambia a la altura correcta; el proyecto para OrcaSlicer, Bambu Studio y PrusaSlicer lleva los cambios. Los colores a ras de la superficie y el borde en color propio necesitan una impresora que cambia el filamento sola (AMS, MMU, ACE).'],
        ['q' => '¿Cuántos colores imprime la granja?', 'a' => 'Hasta cuatro en una impresión: la base y tres colores encima. Confirma la bobina de cada color al encargar; las del diseño vienen preseleccionadas. Un diseño con más colores se descarga gratis para su propia impresora.'],
        ['q' => '¿De qué se imprime el colgante y aguanta en las llaves?', 'a' => 'Recomendamos PLA; se imprime plano, con la cara hacia arriba y sin soportes. Un grosor de 3 mm y una pared de ojal de 2 mm aguantan las llaves en el bolsillo, y la herramienta avisa de motivos finos por debajo de 0,8 mm. Para un colgante que se calentará, por ejemplo en un coche al sol, es mejor el PETG.'],
    ],
    'examples' => [
        'Fantasma alegre de 45 mm de ancho, blanco con ojos oscuros y mejillas rosas: 3 g, 7 minutos, 99 CZK.',
        'Estrella amarilla sonriente de 50 mm de ancho, el ojal en una punta.',
        'El nombre Ela en letra manuscrita, 60 mm de ancho, letras unidas en una pieza: 3,7 g, 12 minutos.',
    ],
];
