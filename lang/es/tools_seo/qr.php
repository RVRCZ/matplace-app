<?php

// La página para la búsqueda «código qr impreso en 3d» (docs/AP.md §1, §5). 'heading' = el H1 de la página, 'h1' = el título del contenido bajo la herramienta.
return [
    'title' => 'Código QR para imprimir en 3D: placa con soporte, STL gratis',
    'heading' => 'Código QR impreso en 3D: placa con soporte',
    'description' => 'Pegue un enlace, un texto o su Wi‑Fi y la herramienta crea una placa con un código QR que el móvil lee y lo comprueba. STL gratis, o imprimimos.',
    'h1' => 'Cómo nace la placa QR, por qué necesita dos colores y qué tamaño debe tener',
    'intro' => [
        'La herramienta crea una placa con un código QR en relieve. El código puede abrir una web, una carta, sus reseñas o un enlace de pago, o conectar el móvil a su Wi‑Fi: pega un enlace o un texto de 4 a 300 caracteres; para Wi‑Fi, el nombre de la red, la contraseña y la seguridad, y la herramienta compone sola la notación técnica. Bajo el código cabe un rótulo de hasta 40 caracteres, y la placa puede llevar un soporte de mesa en el que se encaja con una ligera inclinación, o un agujero para colgarla. El tamaño del código se ajusta de 30 a 150 mm. La herramienta vigila la legibilidad: cada módulo del código debe medir al menos 0,9 mm; si no, le pide agrandar el código o acortar el enlace, y vuelve a leer el diseño terminado como lo haría un móvil. Un código solo se lee en dos colores: placa clara, código oscuro.',
        'Un ejemplo de la calculadora en producción (18 de octubre de 2026, PLA+): una placa con enlace a una web, de 70 × 83 × 3 mm y módulos de unos 2 mm, pesa 14 g, se imprime en 25 minutos y cuesta 99 CZK (unos 4 EUR) con nosotros (cálculo /c/lomf0wzlshw4); una placa Wi‑Fi de 90 mm con soporte de mesa pesa 35 g, se imprime en 1 h 1 min y cuesta 110 CZK, ambas en un color y sin envío. En la impresión con nosotros elige el segundo color al hacer el pedido y la impresora lo cambia sola a la altura de la placa; el proyecto que descarga para su propia impresora ya lleva el cambio de filamento, y el STL es un solo cuerpo. Pruebe el enlace desde la vista previa con el móvil antes de imprimir; un código impreso ya no se puede cambiar.',
    ],
    'steps' => [
        ['name' => 'Pegue un enlace, un texto o su Wi‑Fi', 'text' => 'En «Enlace o texto» pegue la dirección u otro texto; para Wi‑Fi, el nombre de la red, la contraseña y la seguridad. En «Rótulo bajo el código», un texto corto, por ejemplo el nombre de la red; puede quedar vacío.'],
        ['name' => 'Ajuste el tamaño', 'text' => 'Indique el tamaño del código con su margen libre, de 30 a 150 mm. Marque el soporte de mesa o el agujero para colgar según necesite.'],
        ['name' => 'Revise la vista previa', 'text' => 'Bajo la vista previa ve el número de módulos y el tamaño de cada uno. Si el código es demasiado denso para el tamaño elegido, la herramienta dice cuántos milímetros agrandarlo.'],
        ['name' => 'Encargue la impresión o descargue', 'text' => 'Encargue la impresión en nuestra granja y elija el color de la placa y del código al hacer el pedido. O descargue gratis el modelo como STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Cómo hago un código QR para la Wi‑Fi?', 'a' => 'Arriba elija «Wi‑Fi» y rellene el nombre de la red, la contraseña y la seguridad (WPA/WPA2, WEP o sin contraseña); marque si la red está oculta. Tras escanear, el móvil ofrece conectarse. La contraseña queda solo en el código y en el diseño guardado.'],
        ['q' => '¿Por qué la herramienta pide agrandar el código o acortar el enlace?', 'a' => 'Un texto más largo da un código más denso con módulos más pequeños; por debajo de 0,9 mm una boquilla de 0,4 mm no los imprime limpios y el móvil no los lee. Acorte un enlace largo a una dirección que controle, o agrande la placa; la herramienta dice cuántos milímetros.'],
        ['q' => '¿Se puede cambiar después un código impreso?', 'a' => 'No. El código forma parte de la impresión, así que pruebe el enlace antes de pedir. Si el destino puede cambiar, use una dirección que pueda redirigir, por ejemplo en su propio dominio.'],
        ['q' => '¿Por qué la placa tiene que ser de dos colores?', 'a' => 'El móvil necesita contraste entre el código y el fondo. Un código en relieve impreso en un solo color no se lee; la sombra de los bordes no basta. Recomendamos placa clara y código oscuro; un código claro sobre placa oscura solo lo leen algunos móviles.'],
        ['q' => '¿Cómo se sujeta la placa en el soporte?', 'a' => 'El soporte es una pieza aparte con una ranura. La placa lleva abajo una franja libre de 10 mm que entra en la ranura, y queda inclinada hacia atrás unos 12 grados, así se lee desde la mesa y de pie.'],
        ['q' => '¿Y si tengo impresora propia?', 'a' => 'Descargue gratis el modelo como STL o como proyecto para su impresora. El proyecto lleva un cambio de filamento a la altura de la placa: la impresora se detiene, cambia la bobina y la impresión sigue con el segundo color. Una impresora con varias bobinas lo hace sola.'],
    ],
    'examples' => [
        'Placa QR de 70 × 83 mm con enlace a una web y el rótulo matplace.com: 14 g, 25 minutos, 99 CZK.',
        'Placa QR de 90 × 113 mm con rótulo Wi‑Fi en soporte de mesa, para los clientes de una cafetería.',
        'Placa QR pequeña de 40 × 40 mm sin rótulo, por ejemplo para el envase de un producto.',
    ],
];
