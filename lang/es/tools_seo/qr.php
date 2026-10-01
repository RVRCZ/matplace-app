<?php

return [
    'title' => 'Código QR para impresión 3D: cartel y soporte de mesa',
    'description' => 'Pegue un enlace, un texto o los datos del Wi-Fi y la herramienta crea un cartel con un código QR legible. Lo imprimimos en dos colores o lo descarga.',
    'h1' => 'Cartel QR con texto y soporte',
    'intro' => [
        'La herramienta crea una placa con un código QR en relieve. El código puede abrir una web, un menú o un enlace de pago, o conectar el móvil al Wi-Fi: pegue un enlace o un texto de 4 a 300 caracteres. Bajo el código cabe un texto de hasta 40 caracteres, y un soporte de mesa sostiene el cartel algo inclinado. El código mide de 30 a 150 mm.',
        'La herramienta comprueba que el código se pueda leer. Cada módulo debe medir al menos 0,9 mm; si no, le pide ampliar el código o acortar el enlace. Además lee el diseño terminado como comprobación. El código solo se lee en dos colores: placa clara y código oscuro. Con nosotros usted elige el segundo color al hacer el pedido; el proyecto descargado ya lleva el cambio de filamento.',
    ],
    'steps' => [
        ['name' => 'Pegue el enlace o texto', 'text' => 'Ponga la dirección u otro texto en el campo «Enlace o texto» y unas palabras, por ejemplo el nombre de la red, en «Texto bajo el código». Este segundo campo puede quedar vacío.'],
        ['name' => 'Ajuste el tamaño', 'text' => 'Indique el tamaño del código con el margen libre, de 30 a 150 mm. Marque el soporte de mesa si lo quiere.'],
        ['name' => 'Revise la vista previa', 'text' => 'Bajo la vista previa verá el número de módulos, el tamaño de un módulo y un precio orientativo. Si el código es demasiado denso para el tamaño elegido, la herramienta indica cuántos milímetros necesita.'],
        ['name' => 'Pida la impresión o descargue', 'text' => 'Pida la impresión a nuestra granja de impresión, elija los colores de la placa y del código y pague con crédito prepagado. O descargue el modelo gratis como archivo STL o como proyecto listo para su impresora.'],
    ],
    'faq' => [
        ['q' => '¿Cómo hago un código QR para el Wi-Fi?', 'a' => 'Escriba en el campo del enlace un texto con la forma WIFI:T:WPA;S:nombre de la red;P:contraseña;; y el móvil que lo lea ofrecerá conectarse. La herramienta no tiene un formulario aparte para el Wi-Fi.'],
        ['q' => '¿Se puede cambiar después un código impreso?', 'a' => 'No. El código forma parte de la pieza, así que pruebe el enlace antes de hacer el pedido. Si el destino puede cambiar, use una dirección que usted controle y pueda redirigir.'],
        ['q' => '¿Por qué el cartel debe ser de dos colores?', 'a' => 'El móvil necesita contraste entre el código y el fondo. No puede leer un código en relieve impreso en un solo color. Recomendamos una placa clara y un código oscuro.'],
        ['q' => '¿Cómo se sujeta el cartel en el soporte?', 'a' => 'El soporte es una pieza aparte con una ranura. El cartel recibe abajo una franja lisa de 10 mm que entra en la ranura, y queda inclinado hacia atrás unos 12 grados.'],
        ['q' => '¿Y si tengo mi propia impresora?', 'a' => 'El modelo se descarga gratis como archivo STL o como proyecto para su impresora. El proyecto lleva un cambio de filamento a la altura de la placa: la impresora se detiene, usted cambia la bobina y la impresión sigue en el segundo color.'],
        ['q' => '¿Cómo se paga y cómo se recibe la impresión?', 'a' => 'Se paga con crédito prepagado que usted recarga con tarjeta; los precios se muestran en coronas checas o en euros. Enviamos la impresión con Packeta a un punto de recogida o a domicilio en toda la UE.'],
    ],
    'examples' => [
        'Cartel QR de 70 × 83 mm con enlace a una web y texto matplace.com.',
        'Cartel QR 90 × 113 mm, texto Wi-Fi, en soporte de mesa para un café.',
        'Placa QR pequeña de 40 × 40 mm sin texto, por ejemplo para un envase.',
    ],
];
