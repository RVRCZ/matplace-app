<?php

return [
    'title' => 'Imagen a partir de una descripción: silueta o dibujo',
    'description' => 'Describa una cosa y reciba una silueta, un dibujo de líneas o una imagen en color para un cortador de galletas, un sello, una plantilla o un llavero. Gratis.',
    'h1' => 'Una imagen a partir de una descripción para las herramientas que la necesitan',
    'intro' => [
        'Muchas herramientas de matplace necesitan una imagen: el cortador de galletas un contorno, el sello un dibujo de líneas, la plantilla una silueta, la imagen en filamento unos pocos colores planos. Sin un dibujo propio ni nada adecuado en nuestra biblioteca, describa la cosa con palabras y la imagen aparece en segundos: una silueta negra sobre blanco, líneas negras o una imagen de colores planos; cuadrada, horizontal o vertical. Las siluetas y los dibujos se convierten en blanco y negro limpio para que las herramientas los acepten sin retoques.',
        'Descargue la imagen terminada como PNG; además queda 30 días entre sus imágenes: en cualquier herramienta abra la ventana de imagen y la pestaña «mis imágenes». Las imágenes las dibuja el modelo Gemini de Google y cada una nos cuesta algo, por eso hay unas pocas al día sin cuenta y más con cuenta. Si el primer intento no sale, descríbalo de otra forma: una sola cosa, forma clara, sin fondo ni texto.',
    ],
    'steps' => [
        ['name' => 'Describa la cosa', 'text' => 'Una sola cosa con forma clara: «un gato sentado de perfil», «un abeto con estrella», «un tractor de lado».'],
        ['name' => 'Elija estilo y forma', 'text' => 'Silueta para un cortador o una plantilla, dibujo de líneas para un sello, imagen en color para el arte en filamento; cuadrada, horizontal o vertical.'],
        ['name' => 'Deje que aparezca la imagen', 'text' => 'Está en pantalla en unos segundos; si no convence, cambie la descripción y pruebe otra vez.'],
        ['name' => 'Úsela en una herramienta', 'text' => 'Descargue el PNG o abra directamente una herramienta y busque la imagen en «mis imágenes».'],
    ],
    'faq' => [
        ['q' => '¿Qué descripción funciona mejor?', 'a' => 'Una sola cosa, forma clara, quizá una vista (de perfil, de lado, desde arriba). Sin fondos, texto, marcos ni estilo de dibujo: el estilo sale de los botones y el modelo recibe las instrucciones de nosotros.'],
        ['q' => '¿Por qué la silueta es blanco y negro puro?', 'a' => 'Las herramientas quieren un contorno limpio: cada píxel pertenece a la cosa o no. Por eso las siluetas y los dibujos se convierten en blanco y negro puro; una imagen en color se queda como salió.'],
        ['q' => '¿Cuántas imágenes al día?', 'a' => 'Dos sin cuenta, diez con cuenta; pagamos al modelo por cada imagen. Si no le basta, escríbanos.'],
        ['q' => '¿Dónde encuentro la imagen después?', 'a' => 'Durante treinta días entre sus imágenes en cualquier herramienta que acepte una (la ventana de imagen, la pestaña «mis imágenes»). Un PNG descargado es suyo para siempre.'],
        ['q' => '¿De quién es la imagen y quién la ve?', 'a' => 'Solo usted la ve. Las imágenes del modelo Gemini pueden usarse para sus productos; la descripción que escribe no se envía a nadie más ni se guarda con su nombre.'],
    ],
    'examples' => [],
];
