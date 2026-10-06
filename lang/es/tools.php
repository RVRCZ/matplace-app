<?php

// The catalogue of tools (/tools): categories, search and the words each tool is found by. The names and hints of
// the tools themselves (tools.<tool>.title …) are older and live in lang/<locale>.json; Laravel looks there first.
return [
    'search' => 'Buscar una herramienta',
    'search.hint' => 'p. ej. llavero, caja, logo, molde',
    'search.none' => 'Aún no hay una herramienta así. Pruebe otra palabra o díganos qué necesita fabricar.',
    'search.count' => 'Encontradas: :n',
    'verified' => 'verificado imprimiendo :date',
    'all' => 'Todas las herramientas',

    'cats' => [
        'images' => 'Imágenes y logos',
        'names' => 'Nombres y regalos',
        'home' => 'Hogar y almacenaje',
        'parts' => 'Piezas y mecánica',
        'toys' => 'Juguetes y juegos',
        'signs' => 'Carteles y rótulos',
        'craft' => 'Taller y oficio',
        'edit' => 'Editar un modelo',
        'sell' => 'Venta y planificación',
    ],

    'keywords' => [
        'calc' => 'precio presupuesto calculadora coste imprimir stl 3mf obj step archivo',
        'repair' => 'reparar arreglar agujeros malla manifold stl roto',
        'check' => 'comprobar imprimible paredes medidas errores',
        'mold' => 'molde colada silicona yeso resina cera jabón copia',
        'organizer' => 'organizador cajón compartimentos bandeja orden',
        'modular' => 'modular cubetas cuadrícula cajón compartimentos gridfinity',
        'box' => 'caja tapa carcasa electrónica aberturas cable',
        'phone_stand' => 'soporte teléfono móvil tablet coche pared',
        'holder' => 'soporte gancho clip mando auriculares escoba botella pared',
        'cap' => 'tapa tapón cubierta rosca botella pet tubo perfil m10',
        'cable_holder' => 'sujetacables cables cargador escritorio orden',
        'vase' => 'jarrón maceta macetero plato espiral flores',
        'figure' => 'busto figura foto retrato estatua modelo 3d desde foto',
        'relief' => 'litofanía relieve foto lámpara imagen retroiluminada',
        'gifts' => 'regalo nombre navidad cumpleaños boda llavero adorno',
        'sign' => 'cartel placa llavero nombre puerta etiqueta texto',
        'qr' => 'código qr wifi enlace cartel soporte menú pago',
        'logo' => 'logo svg imagen a 3d emblema empresa relieve extrusión',
        'cutter' => 'cortador galletas jengibre masa sello fondant',
        'stamp' => 'sello impresión tinta nombre logo cerámica jabón',
        'stencil' => 'plantilla pintar aerosol letras rótulo',
        'lightbox' => 'rótulo luminoso led caja de luz anuncio retroiluminado neón',
        'spare' => 'repuesto pieza rota consulta modelar',
    ],
];
