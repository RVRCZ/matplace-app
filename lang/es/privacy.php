<?php

return [
    'title' => 'Política de privacidad',
    'effective' => 'Vigente desde el 26 de septiembre de 2026 · matplace s.r.o.',
    'footer' => ['privacy' => 'Política de privacidad', 'terms' => 'Condiciones de la granja de impresión', 'youtube' => 'Nuestro canal de YouTube'],

    'sections' => [
        [
            'h' => '1. Responsable del tratamiento',
            'p' => ['El responsable del tratamiento de sus datos personales es matplace s.r.o., número de identificación 22499008, con domicilio en Rybná 716/24, Staré Město, 110 00 Praga 1, República Checa, inscrita en el Registro Mercantil del Tribunal Municipal de Praga, expediente C 417491 («nosotros»). Contacto: info@matplace.com.'],
        ],
        [
            'h' => '2. Qué datos tratamos',
            'li' => [
                'Datos de la cuenta – nombre, correo, teléfono, dirección (si los rellena), cuenta de Google o Facebook vinculada.',
                'Archivos y solicitudes – modelos 3D, fotos y descripciones de texto con los que calculamos el precio o generamos un modelo.',
                'Datos del pedido – material, color, precio, forma de entrega, dirección de envío, estado de la impresión.',
                'Grabación de la impresión – imágenes de la cámara de la impresora y el vídeo time-lapse hecho con ellas. Solo muestran la base de impresión y el objeto impreso.',
                'Datos de pago – los trata únicamente la pasarela de pago Stripe; nosotros guardamos solo el identificador y el estado del pago y su saldo de crédito.',
                'Datos técnicos – dirección IP, tipo de navegador, hora de acceso, cookies necesarias.',
            ],
        ],
        [
            'h' => '3. Para qué tratamos los datos',
            'li' => [
                'Calcular el precio, imprimir y entregar los pedidos, gestionar su cuenta y su crédito – ejecución de un contrato (art. 6.1.b RGPD).',
                'Obligaciones contables y fiscales – obligación legal (art. 6.1.c RGPD).',
                'Seguridad del servicio y prevención de abusos – interés legítimo (art. 6.1.f RGPD).',
                'Publicar el vídeo time-lapse en YouTube – solo con su consentimiento (art. 6.1.a RGPD), véase el punto 5.',
            ],
        ],
        [
            'h' => '4. Con quién compartimos los datos',
            'p' => ['No vendemos datos. Solo los compartimos en la medida necesaria con estos encargados:'],
            'li' => [
                'Stripe, Inc. – pagos con tarjeta al recargar crédito (stripe.com/privacy).',
                'Anthropic, PBC – reconocer el objeto de una foto y revisar las fotos subidas (anthropic.com/privacy).',
                'Tripo (VAST) – generar un modelo 3D a partir de una foto o un texto, solo si usa esta función.',
                'Google LLC (YouTube) – vídeos time-lapse cuya publicación ha aceptado.',
                'Hetzner Online GmbH – alojamiento, servidores en la UE.',
                'Transportistas – solo nombre, dirección y teléfono para entregar el paquete.',
            ],
        ],
        [
            'h' => '5. Vídeos de impresión y YouTube API Services',
            'p' => [
                'La cámara de la impresora graba cada impresión de nuestra granja y con sus imágenes hacemos un breve vídeo time-lapse. Puede verlo en la página de su pedido.',
                'Si lo acepta (una casilla opcional al pedir o un botón en la página del pedido), nuestro servidor sube el vídeo a nuestro propio canal de YouTube «Matplace – 3D» como privado. Solo se hace público después de que nuestro equipo lo revise. Para subirlo usamos YouTube API Services. A YouTube enviamos solo el vídeo con su título y descripción (material, color, tipo de impresora, tiempo de impresión); nunca su nombre, correo ni archivos subidos. En el pedido guardamos solo el identificador del vídeo en YouTube y su estado.',
                'Puede retirar el consentimiento en cualquier momento en la página del pedido. Entonces borramos el vídeo de YouTube automáticamente. También puede pedir el borrado en info@matplace.com.',
                'Nuestra aplicación no accede a su cuenta de Google ni de YouTube y no lee ninguno de sus datos de YouTube. El acceso a la API de YouTube lo usa solo nuestro equipo para nuestro propio canal.',
                'Al ver vídeos en YouTube acepta las Condiciones de servicio de YouTube (https://www.youtube.com/t/terms). El tratamiento de datos por parte de Google se rige por la Política de privacidad de Google (https://policies.google.com/privacy). Puede revisar y retirar el acceso de cualquier aplicación a su cuenta de Google en https://myaccount.google.com/permissions.',
            ],
        ],
        [
            'h' => '6. Transferencias fuera de la UE',
            'p' => ['Stripe, Anthropic y Google tienen su sede en EE. UU., Tripo fuera de la UE. Las transferencias se basan en las cláusulas contractuales tipo de la Comisión Europea u otro mecanismo del capítulo V del RGPD.'],
        ],
        [
            'h' => '7. Cuánto tiempo guardamos los datos',
            'li' => [
                'Cuenta – mientras exista; se borra o anonimiza en 30 días tras cerrarla.',
                'Pedidos y pagos – 5 años desde su finalización (normas contables y fiscales), facturas hasta 10 años según la ley.',
                'Fotos para reconocimiento y generación – como máximo 1 día; archivos subidos de forma anónima 30 días.',
                'Vídeos time-lapse – con el pedido mientras exista su cuenta; en YouTube hasta que retire el consentimiento o los borremos.',
                'Registros técnicos – como máximo 12 meses.',
            ],
        ],
        [
            'h' => '8. Sus derechos',
            'p' => ['Tiene derecho de acceso, rectificación, supresión, limitación del tratamiento, portabilidad, oposición, a retirar el consentimiento en cualquier momento y a presentar una reclamación ante la Oficina checa de Protección de Datos (uoou.gov.cz). Envíe sus solicitudes a info@matplace.com; respondemos en 30 días.'],
        ],
        [
            'h' => '9. Borrar la cuenta y los datos',
            'p' => ['Escriba desde el correo de su cuenta a info@matplace.com con el asunto «Borrar cuenta». En 30 días borramos o anonimizamos su perfil, datos de contacto, archivos subidos y vínculos con Google/Facebook, y borramos sus vídeos de YouTube. Los documentos que la ley nos obliga a conservar quedan en la contabilidad, anonimizados respecto a su perfil.'],
        ],
        [
            'h' => '10. Cookies y seguridad',
            'p' => ['Usamos solo cookies necesarias para iniciar sesión, proteger formularios y recordar sus opciones. No usamos cookies publicitarias ni de seguimiento de terceros. La comunicación está cifrada (TLS), las contraseñas se guardan como hash unidireccional y nunca vemos los números de tarjeta.'],
        ],
        [
            'h' => '11. Cambios',
            'p' => ['Anunciaremos los cambios importantes en la web o por correo con al menos 14 días de antelación.'],
        ],
    ],
];
