<?php

// La página para la búsqueda «etiqueta de regalo impresa en 3d» (docs/AP.md §6). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Etiqueta de regalo y placa con nombre impresas en 3D, gratis',
    'heading' => 'Etiqueta de regalo con nombre impresa en 3D',
    'description' => 'Elija la forma de la placa, escriba un nombre, añada una imagen y una argolla; cada capa tiene su color. La imprimimos, o descarga el modelo gratis.',
    'h1' => 'Cómo componer la etiqueta por capas, cuánto cuesta y dónde encaja',
    'intro' => [
        'Aquí compone una placa con nombre por capas. La placa tiene una de diecinueve formas: hexágono, corazón, estrella, nube, hueso, lazo, flecha, casa, coche, gato, pez, escudo, bocadillo y más. Sobre ella coloca un nombre en una de treinta tipografías y una imagen de una biblioteca de 178 siluetas o propia; hasta doce capas, cada una con su color entre los filamentos de nuestra granja. Elige una capa haciendo clic en la vista previa, la mueve arrastrando, la escala por la esquina de su marco y la gira con la rueda, y ajusta el ancho de cada una de 5 a 250 mm. Las capas quedan una sobre otra, cada una un paso más alta, así que la impresora solo cambia el filamento a la altura correcta; en nuestra granja imprimimos hasta cuatro colores de una vez. Con una argolla, el mismo diseño es etiqueta de regalo, letrero para la puerta de una habitación infantil, etiqueta de bolso y chapa de collar.',
        'Un ejemplo de la calculadora en producción (27 de octubre de 2026, PLA): un hexágono de 120 × 60 mm con el nombre Adéla y una estrella en tres colores pesa 14,5 g, se imprime en 25 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo (cálculo /c/v9fh1s1ogsob). Una etiqueta de regalo, hexágono de 70 mm con argolla y el texto «Pro Elu», pesa 5,2 g y se imprime en 10 minutos: una cuesta 99 CZK; diez iguales, 120 CZK, es decir, 12 CZK cada una, porque la preparación del pedido se paga una vez (/c/udkkubsktt9d). El cálculo cuenta un color; las bobinas de los demás se confirman al hacer el pedido. ¿Solo quiere un nombre en una placa que crece sola alrededor del texto? Bajo las opciones del inicio de la página hay un enlace al formulario rápido.',
    ],
    'steps' => [
        ['name' => 'Empiece por la placa con nombre', 'text' => 'La página se abre con una placa, el nombre Adéla y una estrella. Cambie el nombre y elija otra forma de placa.'],
        ['name' => 'Edite las capas', 'text' => 'Haga clic en una capa de la lista o de la vista previa y cambie texto, tipografía, forma, imagen y color. En la vista previa la mueve arrastrando, la escala por la esquina del marco y la gira con la rueda.'],
        ['name' => 'Añada más, y la argolla', 'text' => 'Con los botones añade una segunda línea de texto, una imagen u otra forma, hasta doce capas; las flechas cambian qué queda sobre qué. Una casilla añade la argolla para la cinta.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto y el tiempo de impresión los muestra el cálculo tras «Continuar». El modelo y el proyecto para el laminador con los cambios de filamento se descargan gratis y sin registro.'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño tiene la etiqueta?', 'a' => 'El que ajuste: el ancho de cada capa va de 5 a 250 mm. La placa con la que se abre la página mide 120 × 60 mm y 4,6 mm de grosor; para un regalo bastan 60 a 80 mm con argolla.'],
        ['q' => '¿Cómo hago un juego de etiquetas para toda la familia?', 'a' => 'El diseño se queda en la página: cambie el nombre, mantenga la composición y continúe al cálculo con cada nombre por separado; el número de unidades de un diseño lo indica en el cálculo. Diez etiquetas iguales de 70 mm salen por 120 CZK.'],
        ['q' => '¿Qué imagen puedo añadir?', 'a' => 'Una silueta de la biblioteca (178 motivos), un SVG con formas rellenas o una imagen sencilla y contrastada en PNG o JPG. De la imagen queda una silueta de un color. Una fotografía no encaja; para eso está la herramienta Relieve y litofanía.'],
        ['q' => '¿Imprimen la etiqueta en varios colores?', 'a' => 'Sí, hasta cuatro en una impresión. Cada capa queda un paso más alta, así que el filamento cambia según la altura. Elige los colores entre los que tenemos en stock; en su propia impresora el proyecto lleva los cambios.'],
        ['q' => '¿Y si el texto sobresale de la placa?', 'a' => 'La composición es suya: reduzca el texto arrastrando la esquina de su marco, o agrande la placa. Lo que sobresale de la placa también se imprime, porque bajo cada capa se completan las inferiores.'],
        ['q' => '¿Sirve como chapa para perro?', 'a' => 'La forma de hueso con argolla, sí; para un collar elija PETG y letras de al menos 6 mm de alto. Añada el teléfono como otra capa de texto.'],
    ],
    'examples' => [
        'Hexágono de 120 × 60 mm con el nombre Adéla y una estrella, tres colores: 14,5 g, 25 minutos, 99 CZK.',
        'Hueso de 130 mm con el nombre Rex y una huella, tipografía Titan One.',
        'Nube de 100 × 55 mm con el nombre Ela y una estrella.',
    ],
];
