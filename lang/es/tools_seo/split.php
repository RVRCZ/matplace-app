<?php

// La página para la búsqueda «dividir stl en piezas online» (docs/AP.md §1, §4). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Dividir un STL en piezas online y gratis: pasadores y mapa',
    'heading' => 'Dividir un modelo 3D en piezas online',
    'description' => 'Un modelo mayor que su impresora se corta en piezas con pasadores o colas de milano, números en el corte y mapa de montaje. Apoyan por el corte. Gratis.',
    'h1' => 'Cómo funciona la división, qué recibe y cómo pegar las piezas',
    'intro' => [
        'La herramienta corta el modelo con planos a lo ancho, a lo profundo y a lo alto en piezas que caben en la cama elegida: nuestra granja de 250 mm, impresoras corrientes de 220 o 180 mm, o una medida propia. Hace los menos cortes posibles, los planos se mueven con deslizadores y la vista previa los muestra sobre el modelo. Cada pieza se gira para apoyar sobre su mayor cara de corte, así la unión se imprime sin soportes. En las uniones añade pasadores de 6 mm de diámetro con agujeros a ambos lados y 0,2 mm de holgura, o ranuras en cola de milano con una llave suelta de doble cara que entra por el lateral; también hay cortes lisos solo para pegar. Graba el número de cada pieza en su cara de corte y muestra en la página un mapa de cómo quedan unas respecto a otras. Admite STL, 3MF, OBJ y STEP de 5 a 1000 mm y hasta 2 millones de triángulos, cierra primero un modelo que no esté cerrado y rechaza una forma que no quepa ni con seis cortes en una dirección.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): un cilindro de ⌀ 120 × 320 mm no cabe en una cama de 250 mm. La herramienta lo dividió con un corte en dos piezas con cuatro pasadores; las dos piezas y los pasadores pesan en total 814 g, se imprimen en 13 h 8 min y cuestan 1.440 CZK (unos 58 EUR) con nosotros. La división tardó menos de un minuto. Las piezas se abren como archivo nuevo: en la vista previa cada una tiene su color y el mapa dice cuál va dónde. Las descarga una a una como STL o todas como proyecto para el laminador, o nos las encarga impresas en un solo pedido; el modelo original se conserva.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'Arrastre al recuadro un STL, 3MF, OBJ o STEP de 5 a 1000 mm, o haga clic y elija el archivo.'],
        ['name' => 'Elija la cama y las uniones', 'text' => 'Elija la impresora a la que van las piezas (250, 220, 180 mm o propia) y el tipo de unión: pasadores, colas de milano o cortes lisos. La herramienta dice cuántos cortes hacen falta y los planos se pueden mover.'],
        ['name' => 'Divida el modelo', 'text' => 'Haga clic en «Dividir el modelo». Los modelos grandes tardan hasta un minuto; la página dice qué está pasando.'],
        ['name' => 'Descargue las piezas o encargue la impresión', 'text' => 'Ve las piezas en la vista previa, cada una con su color, con el mapa y los números. Descárguelas una a una o como proyecto para el laminador, o encárguenoslas impresas.'],
    ],
    'faq' => [
        ['q' => '¿Pasadores, colas de milano o corte liso?', 'a' => 'Los pasadores son lo más resistente con pegamento y no se ven por fuera. Las llaves en cola de milano aguantan sin pegamento y las piezas se pueden volver a separar, pero la llave entra por el lateral y necesita espacio. El corte liso es el más rápido de imprimir y solo aguanta con pegamento; va bien en piezas que apoyan una sobre otra.'],
        ['q' => '¿Cómo pego las piezas?', 'a' => 'Según los números grabados en las caras de corte y el mapa de la página. Meta los pasadores en los agujeros (están en el archivo como pieza «pasadores», o use tacos de madera de Ø 6 mm) y pegue las piezas con cianoacrilato o pegamento para plásticos. Las llaves en cola de milano aguantan sin pegamento.'],
        ['q' => '¿Se notará la unión?', 'a' => 'Como una línea fina: las piezas apoyan por el corte, así que la cara de corte es plana, y los pasadores y agujeros quedan dentro. La holgura de 0,2 mm desaparece al pegar; para una superficie lisa, lije la unión y aplique masilla o pintura.'],
        ['q' => '¿Por qué las piezas apoyan sobre la cara de corte?', 'a' => 'La cara de corte es plana, así que se sujeta en la cama y la unión no necesita soportes. Una pieza con varios cortes apoya sobre el mayor. Una pieza que quiera orientar de otra forma la gira en su laminador.'],
        ['q' => '¿Y si una pieza sigue sin caber?', 'a' => 'La herramienta lo dice. Añada un plano de corte, elija una cama mayor o reduzca antes el modelo en la calculadora. Una forma que no quepa ni con seis cortes en una dirección se rechaza.'],
        ['q' => '¿Qué tamaño de modelo admite la herramienta?', 'a' => 'Hasta 1000 mm y 2 millones de triángulos; una malla más pesada se simplifica cuando es posible. Un modelo con agujeros se cierra primero como lo hace nuestra granja; si no es posible, lo dice y ayuda la herramienta Reparar el modelo.'],
    ],
    'examples' => [],
];
