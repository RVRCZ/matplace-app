<?php

// La página para la búsqueda «medalla personalizada impresión 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Medalla personalizada en 3D: oro, plata o bronce con cadena',
    'heading' => 'Medalla personalizada con cadena para imprimir en 3D',
    'description' => 'Medalla de oro, plata o bronce para el 1.º, 2.º y 3.º puesto con número o nombre y cadena de 40 eslabones. La imprimimos, o descarga el modelo gratis.',
    'h1' => 'Cómo nace la medalla, qué se puede cambiar y cuánto cuesta con la cadena',
    'intro' => [
        'La herramienta crea una medalla para los ganadores de una carrera infantil, un torneo, un concurso escolar o un día del niño. Con un clic elige el 1.º, 2.º o 3.º puesto: medalla y cadena de oro, plata o bronce con número y borde rojos. El número, un nombre de dos líneas de 16 caracteres o una imagen propia se pueden cambiar, igual que los colores y el tamaño del motivo. La placa tiene siete formas: círculo, estrella, hexágono, octógono, escudo, roseta con borde festoneado y corazón, de 40 a 120 mm de ancho, 60 mm por defecto. Por defecto el motivo queda a ras de la placa y se imprime contra la cama, así que el frente es liso; la otra opción es un motivo en relieve de 0,6 mm. Junto a la medalla se imprime la cadena: 40 eslabones por defecto, de sección redonda de 4 mm, que encajan entre sí, unos 88 cm en total.',
        'Un ejemplo de la calculadora en producción (22 de octubre de 2026, PLA+): una medalla redonda de oro de 60 mm con un 1 rojo a ras de la placa y cadena de 40 eslabones ocupa 195 × 177 mm en la cama, pesa 41 g, se imprime en 1 h 42 min y cuesta 130 CZK (unos 5 EUR) con nosotros, sin envío (cálculo /c/naze69mzmy0h). Es nuestra herramienta más encargada: 31 cálculos y 9 pedidos pagados en 90 días, sobre todo juegos para carreras. El motivo a ras lo imprime una impresora con cambiador de bobinas en una sola cama; el motivo en relieve, cualquier impresora con un cambio de filamento; el proyecto para el laminador y el STL se descargan gratis y sin registro.',
    ],
    'steps' => [
        ['name' => 'Elija el puesto', 'text' => '1.º, 2.º o 3.º puesto: medalla y cadena de oro, plata o bronce, número y borde rojos. El número, el nombre, la imagen y los colores se pueden seguir cambiando.'],
        ['name' => 'Elija el motivo, la forma y el tamaño', 'text' => 'Motivo a ras o en relieve; tamaño del motivo en porcentaje del diámetro; con borde o sin él. Forma de círculo, estrella, hexágono, octógono, escudo, roseta o corazón, de 40 a 120 mm de ancho.'],
        ['name' => 'Ajuste la longitud de la cadena', 'text' => 'Número de eslabones de 0 a 80, 40 por defecto. Bajo la vista previa lee cuántos eslabones caben en la cama y qué longitud de cadena dan.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto lo muestra el cálculo tras «Continuar». El modelo y el proyecto para el laminador se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Qué diferencia hay entre el motivo a ras y el motivo en relieve?', 'a' => 'El motivo a ras queda en las tres capas inferiores (0,6 mm) junto a la placa y se imprime contra la cama: el frente de la medalla es liso y el motivo está al nivel de la placa. La impresora alterna dos bobinas en las mismas capas, por eso lo imprimimos en una sola cama en una impresora con cambiador. El motivo en relieve sobresale 0,6 mm de la placa y la impresora cambia el filamento una vez a la altura en que empieza el motivo.'],
        ['q' => '¿Qué colores tienen los puestos?', 'a' => '1.º puesto, oro PLA Silk; 2.º, plata PLA Silk; 3.º, bronce (metálico cuando lo tenemos cargado; si no, cobre PLA Silk). La medalla y la cadena van en el color metálico; el número y el borde, en rojo. Al hacer el pedido elige entre las bobinas que están ahora mismo en las impresoras.'],
        ['q' => '¿Qué longitud de cadena necesito?', 'a' => 'Para un niño bastan de 60 a 70 cm, es decir, de 28 a 32 eslabones; para un adulto, de 80 a 90 cm, de 36 a 40 eslabones. La cadena se pasa por la cabeza, no tiene cierre.'],
        ['q' => '¿Cómo se unen los eslabones?', 'a' => 'Cada eslabón es un óvalo de 30 × 18 mm con sección redonda de 4 mm y una abertura en medio del lado largo. La abertura es casi medio milímetro más estrecha que el eslabón, así que el vecino encaja en ella. Abajo el eslabón tiene una pequeña cara plana sobre la que apoya y el redondeo empieza a 45°, así que se imprime sin soportes.'],
        ['q' => '¿Cabe todo en una sola cama?', 'a' => 'Sí. Una medalla de 60 mm con cuarenta eslabones ocupa 195 × 177 mm, e incluso una de 120 mm con cuarenta eslabones cabe en una cama de 220 × 220 mm. Si quisiera más eslabones de los que caben, la herramienta imprime los que caben y lo avisa.'],
        ['q' => '¿Es segura la cadena para niños pequeños?', 'a' => 'Es de plástico duro y no se abre sola al tirar, por eso no debe ir al cuello de niños menores de tres años ni en estructuras de juego sin supervisión. Para los más pequeños ponga la medalla en una cinta: ajuste el número de eslabones a cero.'],
    ],
    'examples' => [
        '1.º puesto: medalla redonda de oro de 60 mm con un 1 rojo y borde a ras de la placa y cadena de 40 eslabones: 41 g, 1 h 42 min, 130 CZK.',
        '2.º puesto: escudo de plata de 60 mm con un 2 rojo y 40 eslabones.',
        '3.º puesto: roseta de bronce con borde festoneado de 60 mm, un 3 rojo y 40 eslabones.',
    ],
];
