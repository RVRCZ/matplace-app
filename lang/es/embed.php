<?php

// The calculator for other sites (/embed: the page with the code; /embed/calc: the small page inside the frame), docs/AN.md §12.
return [
    'title' => 'Calculadora de precio de impresión 3D para su web',
    'description' => 'Ponga en su web o tienda una calculadora de impresión 3D: el visitante sube un modelo y ve enseguida el precio y el tiempo de impresión. Una línea de código, gratis.',
    'h1' => 'Calculadora de precio de impresión 3D para su web',
    'lead' => '¿Escribe sobre impresión 3D o vende filamento? Inserte nuestra calculadora: el lector sube un modelo (STL, 3MF, STEP), ve enseguida el precio estimado y el tiempo de impresión y con un toque pasa a matplace.com, donde pide la impresión o descarga el modelo para su impresora. Gratis, sin registro, sin cookies.',
    'code_title' => 'Código para insertar',
    'code_hint' => 'Copie la línea en un artículo o en la plantilla de la página. La calculadora habla el idioma de la página desde la que copia el código; para una web en checo use la versión checa de esta página.',
    'copy' => 'Copiar el código',
    'copied' => 'Copiado',
    'preview_title' => 'Así se ve',
    'rules_title' => 'Conviene saber',
    'rules' => [
        'La calculadora calcula el precio de imprimir con nosotros (la granja matplace); el envío se añade al pedir.',
        'Archivos :formats hasta 120 MB; un modelo grande (millones de triángulos) recibe solo una estimación, el precio exacto llega al pedir.',
        'El archivo subido y el resultado pertenecen a quien tiene el enlace; no se guarda nada en las cookies de su web.',
        'Vemos las visitas desde su página como fuente «calculadora insertada» con el nombre de su web; no recogemos nada más sobre sus lectores.',
        'Puede quitar el código cuando quiera; los resultados que sus lectores abrieron en matplace.com siguen disponibles.',
    ],
    'contact' => '¿Quiere la calculadora en otro tamaño o con un material predeterminado? Escríbanos:',

    // the small page inside the frame
    'frame_title' => 'Calculadora de precio de impresión 3D · matplace',
    'heading' => 'Precio de impresión 3D',
    'pick' => 'Elija o arrastre un modelo',
    'formats' => ':formats, hasta :max MB',
    'reading' => 'Subiendo el archivo…',
    'measuring' => 'Midiendo el modelo…',
    'counting' => 'Calculando el precio exacto…',
    'big_model' => 'Modelo grande: el precio exacto llega al pedir.',
    'estimate' => 'Precio orientativo de impresión con nosotros',
    'exact' => 'Precio exacto de impresión con nosotros',
    'time' => 'Tiempo de impresión :time',
    'price_note' => 'Sin envío; el color, el material y el número de piezas se eligen en matplace.com.',
    'open' => 'Abrir en matplace.com',
    'again' => 'Otro archivo',
    'failed' => 'No ha funcionado. Inténtelo de nuevo o abra matplace.com.',
    'too_big' => 'El archivo supera los :max MB.',
    'not_model' => 'No podemos leer este archivo. Suba un STL, 3MF o STEP.',
    'footer_link' => 'Calculadora para su web',
    'footer' => 'Impresión 3D por encargo:',
];
