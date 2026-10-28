<?php

// La página para la búsqueda «placa con nombre impresa en 3d» (docs/AP.md §9). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Placa con nombre impresa en 3D: letrero, etiqueta y llavero',
    'heading' => 'Placa con nombre impresa en 3D con su texto',
    'description' => 'Escriba un nombre o texto de hasta tres líneas, elija forma y tipografía y vea el modelo al instante. STL gratis, o impresión a dos colores.',
    'h1' => 'Qué hace la placa con nombre, cómo se imprime en dos colores y cuánto cuesta',
    'intro' => [
        'A partir del texto que escribe, la herramienta crea un letrero de puerta, una etiqueta con nombre, un llavero o un nombre sin placa. El texto puede tener de una a tres líneas de hasta 40 caracteres; la segunda y la tercera son más pequeñas que la primera. Elige el estilo de las letras (en relieve, rebajadas, contorno o solo el nombre sin placa), la forma de la placa y una de treinta tipografías con acentos checos; no se puede subir una fuente propia. La placa se dimensiona sola según la altura de las letras (de 4 a 80 mm), la longitud del texto y el margen (de 2 a 30 mm); el grosor es de 1,2 a 10 mm; se puede añadir una argolla de llavero, un marco en relieve o un bisel superior. Las letras en relieve, el contorno y el nombre sin placa se imprimen en un segundo color: la impresora lo cambia a la altura en que empiezan las letras.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): el llavero «Jana» con argolla y marco, de 46 × 22 mm, pesa 2 g, se imprime en 5 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros, que es el pedido mínimo; diez llaveros iguales cuestan 99 CZK en total, porque caben en el mínimo. El letrero de puerta «Novákovi 12» con letras en relieve y marco, de 173 × 69 mm, pesa 30 g, se imprime en 46 minutos y cuesta también 99 CZK. Es nuestra herramienta más calculada: 33 cálculos en 90 días, la mayoría terminados en una descarga para la propia impresora. El modelo se descarga gratis como STL o proyecto para su impresora; en la versión de dos colores, también la placa y las letras como piezas separadas.',
    ],
    'steps' => [
        ['name' => 'Escriba el texto', 'text' => 'En «Primera línea» escriba el nombre o el texto; la segunda y la tercera línea son opcionales. Los botones bajo los campos insertan símbolos, por ejemplo un corazón o una estrella.'],
        ['name' => 'Elija el estilo y la forma', 'text' => 'Elija el estilo de las letras, la forma de la placa y la tipografía, o empiece por un ajuste (llavero, letrero de puerta, etiqueta con nombre, adorno con argolla, nombre en manuscrita). Puede añadir una argolla de llavero, un marco en relieve o un bisel superior.'],
        ['name' => 'Ajuste las medidas', 'text' => 'Ajuste la altura de las letras de 4 a 80 mm y el grosor de la placa de 1,2 a 10 mm. Las medidas exteriores aparecen al instante bajo la vista previa.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Encargue la impresión en nuestra granja y elija los colores al hacer el pedido. O descargue gratis el modelo como STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Qué tamaño puede tener la placa?', 'a' => 'El tamaño depende de la altura de las letras (de 4 a 80 mm), de la longitud del texto y del margen (de 2 a 30 mm). El ancho de la placa no se indica; se calcula y aparece bajo la vista previa. Si el modelo es demasiado grande para la impresora, el cálculo lo avisa en el paso siguiente.'],
        ['q' => '¿Cuánto cuestan placas para toda una clase o un equipo?', 'a' => 'Diez llaveros de 46 × 22 mm caben en el pedido mínimo, 99 CZK en total; el número de unidades de un nombre se indica en el cálculo, y para otros nombres se hace otro diseño. Los letreros de puerta más grandes se calculan por gramos y minutos, y ve el precio antes de encargar nada.'],
        ['q' => '¿Se pueden imprimir las letras en otro color que la placa?', 'a' => 'Sí, con letras en relieve, contorno y nombre sin placa. En la impresión con nosotros elige el segundo color entre los cargados en la misma impresora, y la impresora lo cambia a la altura en que empiezan las letras. Las letras rebajadas se imprimen en un solo color.'],
        ['q' => '¿Cómo fijo la placa a una puerta?', 'a' => 'La placa no tiene orificios para tornillos. La parte trasera es plana, así que se pega, por ejemplo con cinta de doble cara. Solo la versión con argolla de llavero tiene un agujero.'],
        ['q' => '¿Qué letras y caracteres conoce la herramienta?', 'a' => 'Treinta tipografías (sin serifa, con serifa, manuscritas, decorativas y monoespaciadas), todas con acentos checos, y una fila de símbolos bajo los campos de texto. Si falta un carácter en la tipografía elegida, la herramienta dice cuál. No se puede subir una fuente propia.'],
        ['q' => '¿Puedo imprimir el modelo yo mismo?', 'a' => 'Sí. Descargue gratis el modelo como STL, o como proyecto listo para unas 270 impresoras con el cambio de filamento a la altura de las letras. En la versión de dos colores también descarga la placa y las letras como piezas separadas.'],
    ],
    'examples' => [
        'Llavero Jana con argolla y marco, 46 × 22 mm, letras en un segundo color: 2 g, 5 minutos, 99 CZK.',
        'Letrero de puerta Novákovi 12, 173 × 69 mm, letras en relieve y marco: 30 g, 46 minutos, 99 CZK.',
        'Nombre Ela en manuscrita sin placa, con argolla de llavero, 37 × 19 mm.',
    ],
];
