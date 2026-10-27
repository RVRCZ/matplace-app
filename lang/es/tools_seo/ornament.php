<?php

// La página para la búsqueda «adorno navideño impreso en 3d» (docs/AP.md §6). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Adorno navideño impreso en 3D de su imagen o nombre, gratis',
    'heading' => 'Adorno navideño impreso en 3D de una imagen o un nombre',
    'description' => 'Suba una imagen o escriba un nombre y obtenga un adorno navideño en colores de filamento con argolla. Lo imprimimos, o descarga el modelo gratis.',
    'h1' => 'Cómo nace el adorno de una imagen, cuántos colores admite y cuánto cuesta',
    'intro' => [
        'La herramienta convierte una imagen en un adorno navideño plano con argolla para la cinta. Sube un PNG, JPG, WebP o SVG, elige un motivo de nuestra biblioteca (unos 137 motivos, 8 de ellos en color) o escribe un nombre en una de treinta tipografías en lugar de la imagen. El fondo se elimina solo y la imagen se reduce a entre 1 y 8 colores; cada uno se asigna al filamento más cercano que nuestra granja tiene realmente en stock. El adorno sigue el contorno de la imagen o tiene forma de círculo o estrella, mide de 40 a 150 mm de ancho y de 2 a 5 mm de grosor. Los colores quedan uno sobre otro, cada uno de 0,4 a 1,2 mm más alto, así que cualquier impresora imprime el adorno con cambios de filamento a las alturas que lleva el proyecto descargable; nuestra granja imprime hasta cuatro colores de una vez.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): un muñeco de jengibre de la biblioteca de 80 mm de ancho pesa 10 g, se imprime en 18 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo; seis unidades salen por 160 CZK, es decir, 27 CZK por adorno, porque la preparación del pedido se paga una vez. El cálculo cuenta un color; las bobinas de los demás colores se confirman al hacer el pedido. El adorno se imprime en plano, boca arriba y sin soportes, pesa unos diez gramos y cuelga incluso de una cinta fina. El STL, un ZIP con un STL por color y el proyecto para el laminador se descargan gratis y sin registro.',
    ],
    'steps' => [
        ['name' => 'Suba una imagen o escriba un nombre', 'text' => 'Suba una imagen, elija una de la biblioteca o use una que subió antes. Un nombre o un texto corto puede tener una o dos líneas, en una de treinta tipografías.'],
        ['name' => 'Elija la forma, el tamaño y la argolla', 'text' => 'El adorno sigue la imagen, o es un círculo o una estrella. Ajuste el ancho, el grosor y un margen alrededor de la imagen de hasta 3 mm. Mueva la argolla, con agujero de 3 a 6 mm, a cualquier punto del contorno con el deslizador o arrastrando; un botón la devuelve arriba.'],
        ['name' => 'Ajuste los colores', 'text' => 'Elija el número de colores y asigne a cada uno un filamento en la ventana de colores. Cambie qué color queda sobre cuál, o fusione dos colores en uno. La vista 3D muestra el adorno en los colores de los filamentos y sus medidas en milímetros.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Junto a la vista previa ve las medidas; el precio exacto y el tiempo de impresión los muestra el cálculo tras «Continuar». O descargue gratis y sin registro el STL, un ZIP con un STL por color o el proyecto para el laminador.'],
    ],
    'faq' => [
        ['q' => '¿Qué imagen va bien para un adorno?', 'a' => 'Lo mejor es un dibujo con pocos colores planos, por ejemplo un muñeco de jengibre o un árbol. De una foto la herramienta toma los colores principales; el contraste, el brillo y la saturación se afinan con deslizadores. Si debe verse la foto entera, desactive la eliminación del fondo.'],
        ['q' => '¿Qué tamaño de adorno va bien en el árbol?', 'a' => 'Para un árbol normal, de 60 a 90 mm; para uno pequeño de mesa, de 40 a 50 mm; un adorno de 80 mm pesa unos 10 g. La argolla de 3 a 6 mm admite una cinta o un gancho. Los adornos mayores, hasta 150 mm, van bien en una puerta o una ventana.'],
        ['q' => '¿Una impresora de una sola boquilla imprime también los colores?', 'a' => 'Sí. Los colores quedan uno sobre otro, así que a cada altura solo se cambia el filamento; el proyecto para OrcaSlicer, Bambu Studio y PrusaSlicer lleva esos cambios. Las opciones «Colores a ras de la superficie» y «Borde en color propio» necesitan una impresora que cambie el filamento sola (AMS, MMU, ACE).'],
        ['q' => '¿Cuántos colores imprimen ustedes?', 'a' => 'Hasta cuatro en una impresión: la base y tres colores encima. Confirma la bobina de cada color al hacer el pedido; las del diseño vienen preseleccionadas. Un adorno con más colores lo descarga gratis y lo imprime en su propia impresora.'],
        ['q' => '¿De qué material se imprime el adorno?', 'a' => 'Recomendamos PLA. Un adorno de PLA es ligero y se imprime en plano, boca arriba y sin soportes. Elija PETG solo si el adorno va a colgar en un lugar cálido.'],
        ['q' => '¿De qué avisa la herramienta?', 'a' => 'De líneas más finas que la boquilla. También de que unió piezas sueltas de la imagen con un pequeño puente para que se mantengan juntas, y de que la imagen tiene menos colores distintos de los que indicó.'],
    ],
    'examples' => [
        'Muñeco de jengibre de 80 mm de ancho: base marrón, glaseado blanco y botones rojos; 10 g, 18 minutos, 99 CZK.',
        'Árbol de Navidad decorado de 90 mm de ancho en cinco colores.',
        'Bola de Navidad roja de 70 mm con una franja blanca, sin margen.',
    ],
];
