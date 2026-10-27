<?php

// La página para la búsqueda «litofanía de una foto online» (docs/AP.md §1, §5). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Litofanía de una foto online: panel, lámpara o relieve',
    'heading' => 'Litofanía de una foto online y gratis',
    'description' => 'De una foto sale una litofanía en siete formas, una lámpara con la foto alrededor o un relieve de pared. Lista en un minuto, la foto no se guarda. Gratis.',
    'h1' => 'Cómo nace una litofanía, qué foto le va bien y dónde se detiene la herramienta',
    'intro' => [
        'Una litofanía es una placa fina en la que la foto aparece solo cuando pone luz detrás: las zonas oscuras son más gruesas y dejan pasar menos luz; las claras, más finas. La herramienta hace justo eso con su foto, en tres versiones: un panel para la ventana o un soporte, una lámpara en la que la misma foto envuelve un cilindro con base para casquillo E27 o E14, y un relieve para la pared que se ve sin luz, porque las zonas claras sobresalen. La placa puede ser rectángulo, círculo, óvalo, corazón, arco, árbol o una silueta propia de la biblioteca, de hasta 250 mm de ancho y alto; la lámpara, hasta 400 mm de perímetro (unos 127 mm de diámetro) y 250 mm de alto. El grosor va de 0,4 a 5 mm en pasos de 0,2 mm y la herramienta dice al instante cuántos tonos dará la foto; el brillo, el contraste y los medios tonos se ajustan con deslizadores y la vista previa muestra la placa iluminada por detrás. La foto se usa solo para el cálculo y no se guarda.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): un panel de 100 × 75 mm con soporte de mesa pesa 27 g, se imprime en 1 h 9 min y cuesta 100 CZK (unos 4 EUR) con nosotros; una lámpara para casquillo E27, de 100 mm de diámetro y 150 mm de alto, pesa 111 g, se imprime en 2 h 29 min y cuesta 250 CZK (unos 10 EUR), sin envío. Ambas salieron de una imagen de prueba generada, no de la foto de una persona. Lo que mejor queda es un retrato o una foto con contraste marcado y un solo motivo; los detalles pequeños del fondo se pierden en los tonos de grosor. Imprimimos la litofanía en material blanco para que la luz la atraviese, o descarga el modelo como STL o como proyecto para su impresora con los ajustes que nos funcionan.',
    ],
    'steps' => [
        ['name' => 'Elija qué fabricar', 'text' => 'Un panel para la ventana o un soporte, una lámpara con la foto alrededor, o un relieve para la pared sin retroiluminación.'],
        ['name' => 'Suba la foto', 'text' => 'Elija o tome una imagen (JPG, PNG, WebP). Ajuste brillo, contraste y medios tonos con los deslizadores; invierta claros y oscuros con una casilla.'],
        ['name' => 'Elija la forma y las medidas', 'text' => 'En el panel, la forma de la placa, el ancho, el alto y el marco; en la lámpara, el perímetro, el alto y el casquillo. Extras: agujero o argolla para colgar, soporte de mesa.'],
        ['name' => 'Cree el modelo', 'text' => 'Haga clic en «Crear el modelo». En un minuto aproximadamente se abre con la vista previa a contraluz; después, descarga gratis, o cálculo e impresión con nosotros.'],
    ],
    'faq' => [
        ['q' => '¿Qué diferencia hay entre litofanía y relieve?', 'a' => 'La litofanía muestra la imagen solo a contraluz, en una ventana o delante de una lámpara; sin luz es una placa blanca con un dibujo apenas visible. El relieve se ve sin luz, porque las zonas claras de la foto sobresalen de la placa; va bien en una pared o una estantería.'],
        ['q' => '¿Qué foto queda mejor?', 'a' => 'Un retrato o una foto con contraste marcado y un solo motivo, nítida, sin contraluz. La herramienta dice cuántos tonos de grosor en pasos de 0,2 mm dará la foto; los deslizadores de brillo, contraste y medios tonos mejoran una foto floja. Un motivo oscuro sobre fondo claro sale mejor que al revés.'],
        ['q' => '¿Por qué la litofanía tiene que ser blanca y cómo la ilumino?', 'a' => 'El plástico de color tiñe la luz y el oscuro no la deja pasar; el PLA blanco da una imagen limpia. Basta la luz del día en una ventana, una tira LED o una bombilla LED en la lámpara. No use una bombilla incandescente: el PLA se ablanda hacia los 55 °C.'],
        ['q' => '¿Cómo funciona la lámpara?', 'a' => 'La foto envuelve un cilindro del perímetro indicado, con pared lisa por dentro y una base con agujero de Ø 40 mm para casquillo E27 (o Ø 28 mm para E14). También puede elegir base abierta para una tira LED, o cerrada para una vela LED.'],
        ['q' => '¿Guardan mi foto?', 'a' => 'No. La foto sirve solo para calcular el modelo y se descarta enseguida; en el modelo queda solo la forma. Si vuelve a los ajustes, hay que elegirla de nuevo.'],
        ['q' => '¿Qué tamaño y grosor puede tener el modelo?', 'a' => 'Un panel de hasta 250 × 250 mm; una lámpara de hasta 400 mm de perímetro y 250 mm de alto. La litofanía tiene como máximo 5 mm de grosor y el punto más fino desde 0,4 mm; cuanto mayor es el rango, más tonos, pero más larga la impresión.'],
    ],
    'examples' => [],
];
