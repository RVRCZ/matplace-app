<?php

// Páginas estáticas: quiénes somos, contacto, preguntas frecuentes, reclamaciones, condiciones de uso, condiciones comerciales, cookies.
// Traducción de lang/cs/pages.php (la versión jurídicamente vinculante); la estructura debe ser idéntica.
return [
    'updated' => '1 de octubre de 2026',

    'about' => [
        'title' => 'Quiénes somos: impresión 3D en nuestra propia granja',
        'description' => 'matplace es un servicio checo de impresión 3D por encargo. Suba un modelo, vea el precio al momento y lo imprimimos en nuestra granja. Enviamos a la UE.',
        'lead' => 'matplace es un servicio de impresión 3D en línea. Imprimimos en nuestras propias impresoras en Chequia, usted conoce el precio antes de pedir y le enviamos la pieza terminada con Packeta a un punto de recogida o a domicilio.',
        'sections' => [
            [
                'h' => 'Qué hacemos',
                'p' => [
                    'matplace nació de una idea sencilla: la impresión 3D debe estar al alcance de cualquiera que tenga una idea, no solo de quien tiene una impresora en casa.',
                    'Usted sube su propio modelo, elige el modelo de un diseñador o crea un producto con una de nuestras herramientas. Revisamos el modelo, lo orientamos para la impresión y calculamos el precio exacto. Usted elige el color y la forma de entrega, paga con crédito y la impresora empieza a imprimir.',
                ],
            ],
            [
                'h' => 'Cómo funciona',
                'li' => [
                    'Suba un archivo, elija un modelo del catálogo o créelo con una herramienta.',
                    'Vea la vista previa, el tiempo de impresión, el material necesario y el precio con IVA.',
                    'Elija el color, la calidad y la entrega, y pague con su crédito.',
                    'Siga la impresión en la página del pedido. Le enviamos la pieza terminada con Packeta a un punto de recogida o a domicilio.',
                ],
            ],
            [
                'h' => 'Imprimimos nosotros',
                'p' => [
                    'Todos los pedidos se imprimen en nuestra propia granja de impresión en Chequia. No pasamos los pedidos a nadie más, así que del resultado respondemos nosotros.',
                    'Al pedir, el crédito solo se retiene; se cobra cuando la impresión termina. Si la impresión no empieza o falla por nuestra causa, se devuelve entero.',
                ],
            ],
            [
                'h' => 'Para diseñadores',
                'p' => [
                    '¿Diseña modelos? Publíquelos en su portafolio de matplace. Los clientes los imprimen con nosotros y usted recibe una recompensa por cada pieza impresa. No necesita impresora.',
                    'Usted mismo fija la recompensa de cada modelo. También puede permitir la descarga gratuita del archivo con la licencia que elija.',
                ],
            ],
            [
                'h' => '¿Tiene su propia impresora?',
                'p' => [
                    'Las herramientas de la web son gratuitas. Puede descargar el modelo como archivo STL o como proyecto 3MF listo para su impresora e imprimirlo usted mismo.',
                ],
            ],
            [
                'h' => 'Titular del servicio',
                'p' => [
                    'El servicio lo presta matplace s.r.o., número de identificación 22499008, con domicilio en Rybná 716/24, Staré Město, 110 00 Praga 1, República Checa, inscrita en el Registro Mercantil del Tribunal Municipal de Praga, expediente C 417491. Contacto: info@matplace.com.',
                ],
            ],
        ],
    ],

    'contact' => [
        'title' => 'Contacto y datos de facturación',
        'description' => 'Cómo contactar con matplace s.r.o.: correo para consultas sobre pedidos y para reclamaciones, domicilio social, número de identificación y registro.',
        'lead' => 'Escríbanos por correo electrónico. Si su consulta se refiere a una impresión concreta, indique el número de pedido.',
        'sections' => [
            [
                'h' => 'Escríbanos',
                'p' => [
                    'Correo electrónico: info@matplace.com',
                    'Use esta dirección para consultas sobre pedidos, reclamaciones, solicitudes sobre sus datos personales y propuestas de colaboración.',
                ],
            ],
            [
                'h' => 'Titular y datos de facturación',
                'li' => [
                    'matplace s.r.o.',
                    'Domicilio social: Rybná 716/24, Staré Město, 110 00 Praga 1, República Checa',
                    'Número de identificación (IČO): 22499008',
                    'Inscripción en el Registro Mercantil: Tribunal Municipal de Praga, expediente C 417491',
                ],
            ],
            [
                'h' => 'Reclamaciones y datos personales',
                'p' => [
                    'Las reclamaciones se presentan por correo a info@matplace.com. La página Reclamaciones describe el procedimiento.',
                    'La Política de privacidad explica cómo tratamos los datos personales. Envíe las solicitudes sobre sus datos a la misma dirección.',
                ],
            ],
        ],
    ],

    'faq' => [
        'title' => 'Preguntas frecuentes sobre la impresión 3D',
        'description' => 'Respuestas a las preguntas habituales: precio de la impresión 3D, materiales, formatos de archivo, plazos, envío a países de la UE, crédito y pago.',
        'lead' => 'Respuestas breves a lo que más nos preguntan. ¿No encuentra su pregunta? Escriba a info@matplace.com.',
        'items' => [
            [
                'q' => '¿Cuánto cuesta una impresión?',
                'a' => 'Calculamos el precio exacto de su modelo antes de que haga el pedido. Lo forman el tiempo de impresora, el material consumido y una tarifa de preparación del pedido; los pedidos pequeños tienen un precio mínimo. El precio incluye el IVA y el envío se muestra aparte antes de pagar.',
            ],
            [
                'q' => '¿Con qué materiales imprimen?',
                'a' => 'La oferta depende de lo que haya cargado en las impresoras en cada momento: lo más habitual es PLA y PETG en varios colores y acabados. Los materiales y colores disponibles se ven al hacer el pedido.',
            ],
            [
                'q' => '¿Qué archivos aceptan y qué hago si no tengo ninguno?',
                'a' => 'La calculadora de la web acepta archivos STL, 3MF, OBJ y STEP. En el pedido de impresión se sube directamente un STL. ¿No tiene archivo? Elija un modelo del catálogo, cree un producto con una de las herramientas o genere un modelo a partir de una foto o una descripción.',
            ],
            [
                'q' => '¿Cuánto tarda?',
                'a' => 'Calculamos el tiempo de impresión de cada modelo y usted lo ve antes de pedir. Si hay una impresora libre, la impresión empieza en cuanto paga. Si no, el pedido entra en la cola y en su página se ve la estimación de inicio y de fin. Le escribimos por correo cuando cambia el estado. Al tiempo de impresión hay que sumar el del transporte.',
            ],
            [
                'q' => '¿Cómo recibo la pieza y a qué países envían?',
                'a' => 'Enviamos con Packeta a un punto de recogida o a domicilio, solo a países de la Unión Europea. En Chequia el punto de recogida cuesta 99 Kč y la entrega a domicilio 149 Kč; los países a los que enviamos y el precio del envío se muestran en el pedido antes de pagar. Una pieza de más de 70 cm de largo no se puede enviar y no aceptamos ese pedido. Por ahora no ofrecemos recogida en persona.',
            ],
            [
                'q' => '¿Cómo se paga y qué es el crédito?',
                'a' => 'Las impresiones se pagan con crédito prepago, que usted recarga con tarjeta a través de la pasarela de pago Stripe. Se puede recargar de 100 a 20 000 Kč en coronas o de 5 a 800 € en euros. Al pagar un pedido, el importe se retiene en su crédito y solo se cobra cuando la impresión termina. No ofrecemos pago contra reembolso y el crédito sin usar no se devuelve en efectivo.',
            ],
            [
                'q' => '¿En qué moneda pago?',
                'a' => 'Los clientes de Chequia pagan en coronas checas y los demás en euros. Antes del primer pago puede cambiar la moneda en la cabecera de la web. El primer pago fija la moneda de la cuenta, y los pagos y precios posteriores son solo en esa moneda.',
            ],
            [
                'q' => '¿Qué pasa si la impresión falla?',
                'a' => 'Si la impresión no empieza o falla por nuestra causa, el crédito retenido se le devuelve entero y le avisamos por correo. Después puede volver a pedir la impresión.',
            ],
            [
                'q' => '¿Puedo cancelar un pedido?',
                'a' => 'Sí, en la página del pedido, mientras la impresión no haya terminado. Antes de que empiece la impresión se devuelve todo el crédito. Si ya está en marcha, cobramos la tarifa de preparación y la parte ya impresa y devolvemos el resto; verá el importe antes de confirmar la cancelación.',
            ],
            [
                'q' => '¿Puedo descargar un modelo e imprimirlo en mi impresora?',
                'a' => 'Sí, gratis. El modelo que usted sube o crea con las herramientas se descarga como archivo STL o como proyecto 3MF preparado para su impresora. Los modelos de diseñadores solo se pueden descargar si el autor lo ha permitido.',
            ],
            [
                'q' => '¿Cómo funcionan los modelos de los diseñadores?',
                'a' => 'En el catálogo hay modelos que los diseñadores ofrecen para imprimir. Los imprimimos en nuestra granja y el autor recibe por cada pieza una recompensa, que se muestra aparte dentro del precio. En algunos modelos el autor ha permitido además la descarga gratuita del archivo con la licencia que él mismo ha elegido.',
            ],
            [
                'q' => '¿Cómo reclamo una pieza defectuosa?',
                'a' => 'Escriba a info@matplace.com, indique el número de pedido, describa el defecto y adjunte fotos. Resolvemos la reclamación en un plazo máximo de 30 días. Las pequeñas marcas de soportes, las capas visibles y las desviaciones de décimas de milímetro son propias de la impresión 3D y no son defectos.',
            ],
            [
                'q' => '¿Puedo devolver la pieza en 14 días?',
                'a' => 'No. Cada pieza se fabrica por encargo a partir de su archivo o de sus ajustes, y para esos bienes la ley no concede el derecho de desistimiento de 14 días. Conserva su derecho a reclamar por defectos.',
            ],
            [
                'q' => '¿Cómo elimino mi cuenta?',
                'a' => 'En el perfil de su cuenta, en el apartado «Eliminar la cuenta», o por correo a info@matplace.com. Borramos sus datos personales; los pedidos y los pagos debemos conservarlos por contabilidad, pero ya no estarán ligados a su persona. Las impresiones ya pagadas se terminan. El crédito sin usar se pierde al eliminar la cuenta.',
            ],
        ],
    ],

    'complaints' => [
        'title' => 'Reclamaciones y devoluciones',
        'description' => 'Cómo reclamar una pieza de matplace: qué es y qué no es un defecto de impresión 3D, qué enviarnos, en qué plazo respondemos y por qué no hay desistimiento.',
        'lead' => '¿No está satisfecho con su pieza? Aquí encontrará cómo reclamar un defecto y qué puede esperar de nosotros.',
        'sections' => [
            [
                'h' => '1. De qué respondemos',
                'p' => [
                    'Respondemos de que la pieza no tenga defectos en el momento de la entrega: se corresponde con el modelo que usted pidió y con el material, el color y los ajustes elegidos.',
                    'Si usted es consumidor, puede reclamar un defecto que se manifieste en la pieza dentro de los dos años siguientes a la entrega.',
                ],
            ],
            [
                'h' => '2. Qué no es un defecto',
                'p' => [
                    'No consideramos defecto:',
                ],
                'li' => [
                    'las pequeñas marcas de soportes, las capas visibles y las desviaciones de décimas de milímetro, propias de la impresión 3D,',
                    'las características que vienen del propio modelo: lo imprimimos tal como es y no garantizamos que la pieza cumpla la función para la que usted la diseñó o la eligió,',
                    'el desgaste por el uso habitual y los daños que haya causado usted mismo.',
                ],
            ],
            [
                'h' => '3. Cómo presentar una reclamación',
                'p' => [
                    'Envíe la reclamación por correo a info@matplace.com sin demora indebida desde que detecte el defecto. Indique en el mensaje:',
                ],
                'li' => [
                    'el número de pedido,',
                    'la descripción del defecto,',
                    'fotos en las que se vea el defecto,',
                    'la solución que propone.',
                ],
            ],
            [
                'h' => '4. Cómo resolvemos la reclamación',
                'p' => [
                    'Le confirmamos por correo que hemos recibido la reclamación. La resolvemos sin demora indebida, como máximo en 30 días desde su presentación, salvo que acordemos con usted un plazo más largo.',
                    'Si la reclamación es fundada, volvemos a imprimir la pieza o reparamos el defecto. Si eso no es posible o resulta desproporcionado, puede pedir una rebaja adecuada del precio o resolver el contrato. La rebaja o el precio devuelto se abonan en su crédito.',
                    'Si se lo pedimos, envíenos o entréguenos la pieza defectuosa; le indicaremos la dirección al tramitar la reclamación. Si la reclamación es fundada, le reembolsamos los gastos razonables del envío.',
                ],
            ],
            [
                'h' => '5. Paquete dañado en el transporte',
                'p' => [
                    'Revise el paquete al recibirlo. Si está dañado, fotografíe el embalaje y el contenido y escríbanos a info@matplace.com con el número de pedido. Los daños del transporte los tratamos como cualquier otro defecto.',
                ],
            ],
            [
                'h' => '6. Cancelación del pedido y devolución de la pieza',
                'p' => [
                    'Puede cancelar un pedido en su página mientras la impresión no haya terminado. Antes de que empiece la impresión se devuelve todo el crédito. Si ya está en marcha, cobramos la tarifa de preparación y la parte ya impresa y devolvemos el resto.',
                    'Cada pieza se fabrica a partir de su archivo o de sus ajustes. Para los bienes confeccionados conforme a las especificaciones del consumidor, la ley no concede el derecho de desistimiento de 14 días sin indicar el motivo (artículo 1837, letra d), del Código Civil checo). Por eso no aceptamos la devolución de una pieza terminada que no tenga defectos.',
                ],
            ],
            [
                'h' => '7. Resolución extrajudicial de litigios',
                'p' => [
                    'Si no llegamos a un acuerdo, el consumidor puede dirigirse a la Inspección de Comercio Checa (www.coi.cz, adr.coi.cz), que resuelve los litigios de consumo por vía extrajudicial.',
                ],
            ],
            [
                'h' => '8. Contacto para reclamaciones',
                'p' => [
                    'matplace s.r.o., número de identificación 22499008, con domicilio en Rybná 716/24, Staré Město, 110 00 Praga 1, República Checa. Correo electrónico: info@matplace.com.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Condiciones de uso',
        'description' => 'Condiciones de uso de matplace.com: cuenta, archivos subidos, herramientas y calculadora, modelos de diseñadores y licencias, contenido prohibido.',
        'lead' => 'Las reglas para usar la web, la cuenta y las herramientas. Los pedidos de impresión se rigen además por las Condiciones comerciales.',
        'sections' => [
            [
                'h' => '1. Quiénes somos',
                'p' => [
                    'La web matplace.com la gestiona matplace s.r.o., número de identificación 22499008, con domicilio en Rybná 716/24, Staré Město, 110 00 Praga 1, República Checa, inscrita en el Registro Mercantil del Tribunal Municipal de Praga, expediente C 417491. Contacto: info@matplace.com.',
                    'Estas condiciones se aplican al uso de la web, de la cuenta y de las herramientas. Los pedidos de impresión se rigen además por las Condiciones comerciales.',
                ],
            ],
            [
                'h' => '2. Qué ofrece la web',
                'p' => [
                    'La web ofrece los servicios que se enumeran a continuación. Las herramientas, la calculadora y la descarga de sus propios modelos son gratuitas. La generación de un modelo a partir de una foto o un texto tiene un límite diario; las generaciones adicionales se pagan con crédito.',
                ],
                'li' => [
                    'calculadora y revisión de modelos 3D, y herramientas para crear su propio producto,',
                    'descarga del modelo para su propia impresora,',
                    'pedido de impresión en la granja de impresión de matplace,',
                    'catálogo de modelos de diseñadores y catálogo de inspiración con enlaces a modelos de otras webs,',
                    'perfil de diseñador con portafolio público.',
                ],
            ],
            [
                'h' => '3. La cuenta',
                'p' => [
                    'La cuenta se crea con un correo y una contraseña, o a través de Google o Facebook. Hay que verificar el correo; sin ello no se puede pedir una impresión, recargar crédito ni activar el perfil de diseñador.',
                    'Indique datos verdaderos y proteja sus datos de acceso. Usted responde de lo que se haga desde su cuenta.',
                    'Puede eliminar su cuenta en el perfil en cualquier momento. Al eliminarla borramos sus datos personales, el crédito sin usar se pierde y las impresiones ya pagadas se terminan. Podemos suspender una cuenta que incumpla estas condiciones.',
                ],
            ],
            [
                'h' => '4. Los archivos que sube',
                'p' => [
                    'Usted es responsable de los archivos que sube. Suba solo modelos y fotos que haya creado o que tenga derecho a usar.',
                    'Los archivos subidos se guardan de forma privada y solo se usan para aquello para lo que los subió: calcular el precio, trabajar en una herramienta y tramitar su pedido. Los archivos subidos sin iniciar sesión se borran a los 30 días.',
                ],
            ],
            [
                'h' => '5. Qué está prohibido',
                'li' => [
                    'subir o pedir armas, sus piezas y piezas destinadas a eludir la ley,',
                    'imprimir modelos ajenos para la venta sin el consentimiento del autor o infringir de otro modo derechos de autor o de propiedad industrial ajenos,',
                    'presentar como propios modelos ajenos,',
                    'extraer el contenido de la web de forma automatizada sin nuestro consentimiento,',
                    'perturbar el funcionamiento de la web o eludir sus límites y su seguridad.',
                ],
            ],
            [
                'h' => '6. Modelos de diseñadores y licencias',
                'p' => [
                    'El diseñador publica modelos en su portafolio. Al subir un archivo confirma que es el autor del modelo y nos concede el derecho a imprimirlo para los clientes. En un remix confirma que la licencia del modelo original permite el uso comercial y las obras derivadas.',
                    'No entregamos a nadie el archivo de un modelo cuyo autor no ha permitido la descarga: solo se puede imprimir en nuestra granja. Si el autor ha permitido la descarga, el archivo queda sujeto a la licencia que eligió (CC BY, CC BY-SA, CC BY-NC o CC0), y usted debe respetarla.',
                    'Por cada pieza impresa el diseñador recibe la recompensa que él mismo ha fijado, con un máximo del 30 % del precio de la impresión. La recompensa se abona en el crédito de su cuenta cuando la impresión termina; imprimir un modelo propio no da derecho a recompensa. Si devolvemos al cliente el precio de la impresión, la recompensa se descuenta.',
                ],
            ],
            [
                'h' => '7. Catálogo de inspiración',
                'p' => [
                    'El catálogo de inspiración muestra modelos publicados en otras webs, como Printables o MakerWorld. De cada uno indicamos el autor, el enlace a la fuente y la licencia tal como la indica la fuente; no garantizamos que sea completa ni que esté actualizada.',
                    'La impresión para uso propio en la impresora alquilada es posible con todos los modelos; las impresiones para la venta, solo donde la licencia lo permite. Usted descarga el archivo en la fuente y lo sube a nuestra web; nosotros anotamos el autor y la fuente en el pedido.',
                ],
            ],
            [
                'h' => '8. Aviso de contenido ilícito',
                'p' => [
                    '¿Cree que un modelo u otro contenido vulnera sus derechos o la ley? Escriba a info@matplace.com. Indique la dirección de la página, de qué obra se trata y cómo acredita que es su autor o el titular de los derechos. Examinamos el aviso sin demora indebida y retiramos el contenido denunciado con fundamento.',
                ],
            ],
            [
                'h' => '9. Nuestra responsabilidad',
                'p' => [
                    'Los resultados de las herramientas, de la calculadora y de las comprobaciones automáticas son una ayuda. No garantizan que el producto cumpla la función que usted le quiere dar; las piezas de carga y de seguridad las diseña y las usa bajo su responsabilidad.',
                    'Los precios de la calculadora y de las páginas de los modelos son orientativos. El precio vinculante es el que ve en el pedido antes de pagar.',
                    'No garantizamos que la web esté disponible sin interrupciones. El contenido de la web (textos, gráficos, código, herramientas) está protegido por derechos de autor y no puede reutilizarse sin nuestro consentimiento.',
                ],
            ],
            [
                'h' => '10. Datos personales y cookies',
                'p' => [
                    'El tratamiento de los datos personales se describe en la Política de privacidad y el uso de cookies en la página Cookies.',
                ],
            ],
            [
                'h' => '11. Cambios y ley aplicable',
                'p' => [
                    'Podemos modificar estas condiciones. Anunciamos los cambios importantes en la web o por correo con al menos 14 días de antelación.',
                    'Estas condiciones se rigen por el derecho checo. Ello no priva al consumidor de la protección ni del derecho a acudir a los tribunales que le reconoce la legislación del país donde reside. El consumidor también puede resolver un litigio por vía extrajudicial ante la Inspección de Comercio Checa (www.coi.cz). La versión checa de estas condiciones es la jurídicamente vinculante.',
                ],
            ],
        ],
    ],

    'business_terms' => [
        'title' => 'Condiciones comerciales',
        'description' => 'Condiciones comerciales de la impresión 3D en matplace: pedido, precio, crédito y pago con tarjeta, moneda, envío con Packeta en la UE y reclamaciones.',
        'lead' => 'Las condiciones en las que imprimimos para usted. Se aplican a todo pedido de impresión hecho en matplace.com.',
        'sections' => [
            [
                'h' => '1. Vendedor',
                'p' => [
                    'El vendedor es matplace s.r.o., número de identificación 22499008, con domicilio en Rybná 716/24, Staré Město, 110 00 Praga 1, República Checa, inscrita en el Registro Mercantil del Tribunal Municipal de Praga, expediente C 417491. Contacto: info@matplace.com.',
                    'Estas condiciones se aplican a los pedidos de impresión 3D hechos en la web matplace.com. Imprimimos exclusivamente en nuestra propia granja de impresión en Chequia.',
                ],
            ],
            [
                'h' => '2. Pedido y celebración del contrato',
                'p' => [
                    'Puede pedir un cliente que haya iniciado sesión y tenga el correo verificado. El pedido se hace así:',
                ],
                'li' => [
                    'Usted sube su propio modelo, elige el modelo de un diseñador en el catálogo o usa un modelo de una herramienta.',
                    'Revisamos el modelo, lo orientamos para la impresión y calculamos el tiempo de impresión, el material necesario y el precio.',
                    'Usted elige el color, la calidad, la resistencia, el número de piezas y la forma de entrega. Antes de pagar ve el precio total con IVA y envío.',
                    'Usted confirma que acepta las condiciones y paga el pedido con su crédito. Con ello queda celebrado el contrato.',
                ],
            ],
            [
                'h' => '3. Alquiler de la impresora: qué no se puede imprimir en ella',
                'p' => [
                    'Le alquilamos la impresora: imprime su archivo con sus ajustes y es usted quien hace la copia. Usted responde de poder imprimir el modelo (es suyo, su licencia lo permite o lo imprime para uso propio). Nosotros no lo juzgamos.',
                    'El modelo se imprime tal como es. La revisión, la reparación y la orientación automáticas ayudan, pero no garantizan que la pieza cumpla su función. Las piezas de carga y de seguridad se imprimen bajo su responsabilidad.',
                    'No alquilamos la impresora para armas ni sus piezas, piezas destinadas a eludir la ley ni impresiones de modelos ajenos destinadas a la venta sin el consentimiento del autor. Un pedido así se cancela y el crédito se devuelve. Si un titular de derechos nos lo pide con fundamento, detenemos la impresión y retiramos el modelo.',
                ],
            ],
            [
                'h' => '4. Precio',
                'p' => [
                    'Usted conoce el precio antes de pedir. Lo forman el tiempo de impresora, el material consumido y una tarifa de preparación del pedido; los pedidos pequeños tienen un precio mínimo. Los precios incluyen el IVA.',
                    'En el modelo de un diseñador, el precio incluye la recompensa del autor, que se muestra aparte.',
                    'Los precios de la calculadora y de las páginas de los modelos son orientativos. El precio vinculante es el que ve en el pedido antes de pagar.',
                ],
            ],
            [
                'h' => '5. Crédito y pago',
                'p' => [
                    'Los pedidos se pagan con crédito prepago. El crédito se recarga con tarjeta a través de la pasarela Stripe; los datos de la tarjeta los trata Stripe y nosotros no los vemos.',
                    'Al pagar un pedido, el importe se retiene en su crédito. Solo se cobra cuando la impresión termina. Si la impresión no empieza o falla por nuestra causa, se devuelve entero.',
                    'El crédito sirve para pagar los servicios de matplace. El crédito sin usar no se devuelve en efectivo y se pierde al eliminar la cuenta.',
                    'No ofrecemos pago contra reembolso. Si lo solicita, le enviamos por correo el justificante de una recarga de crédito o de un pedido.',
                ],
            ],
            [
                'h' => '6. Moneda',
                'p' => [
                    'Las cuentas de los clientes de Chequia se llevan en coronas checas y las demás en euros. Antes del primer pago puede cambiar la moneda en la web. El primer pago fija la moneda de la cuenta; después usted ya no puede cambiarla.',
                    'Los precios se calculan en coronas y se convierten a euros a un tipo fijo, redondeando al alza a 0,10 €.',
                ],
            ],
            [
                'h' => '7. Entrega',
                'p' => [
                    'Le enviamos la pieza terminada con Packeta a un punto de recogida o a domicilio. Solo enviamos a los países de la Unión Europea que ofrece el pedido. No ofrecemos recogida en persona.',
                    'El tiempo de impresión y la estimación de fin se ven en el pedido. Al enviar el paquete le mandamos por correo un enlace de seguimiento.',
                    'Precios de envío con IVA para un paquete de hasta 2 kg, a un punto de recogida y a domicilio respectivamente. Una cuenta en coronas paga el precio en coronas y una cuenta en euros el precio en euros:',
                ],
                'li' => [
                    'Chequia: 99 Kč y 149 Kč, o 4,00 € y 6,00 €.',
                    'Eslovaquia: 149 Kč y 175 Kč, o 5,90 € y 6,90 €.',
                    'Polonia, Hungría, Rumanía, Eslovenia, Croacia, Bulgaria, Grecia, Lituania: 199 Kč y 225 Kč, o 7,90 € y 8,90 €.',
                    'Alemania, Austria, España, Portugal, Francia, Italia, Letonia: 249 Kč y 299 Kč, o 9,90 € y 11,90 €.',
                    'Países Bajos, Bélgica, Luxemburgo, Irlanda, Dinamarca, Suecia, Finlandia, Estonia, Chipre: 399 Kč y 575 Kč, o 15,90 € y 22,90 €.',
                    'Paquete de 2 a 5 kg: recargo de 50 Kč o 2 €. Paquete de 5 a 15 kg, solo dentro de Chequia: recargo de 100 Kč o 4 €.',
                    'A Austria, Luxemburgo e Irlanda solo enviamos a domicilio; a Chipre, solo a un punto de recogida.',
                    'Una pieza de más de 70 cm de largo, o cuyos lados sumen más de 120 cm, no se puede enviar y no aceptamos ese pedido.',
                ],
            ],
            [
                'h' => '8. Cancelación del pedido',
                'p' => [
                    'Puede cancelar un pedido en su página mientras la impresión no haya terminado. Antes de que empiece la impresión devolvemos todo el crédito. Si ya está en marcha, cobramos la tarifa de preparación y la parte ya impresa y devolvemos el resto; verá el importe antes de confirmar.',
                    'Nosotros podemos cancelar un pedido que no se pueda imprimir o que incumpla el punto 3. En ese caso devolvemos todo el crédito.',
                ],
            ],
            [
                'h' => '9. Desistimiento del contrato',
                'p' => [
                    'Cada pieza se fabrica a partir de su archivo o de sus ajustes. Se trata de bienes confeccionados conforme a las especificaciones del consumidor, por lo que no es posible desistir del contrato en 14 días sin indicar el motivo (artículo 1837, letra d), del Código Civil checo).',
                    'Ello no afecta a sus derechos por falta de conformidad.',
                ],
            ],
            [
                'h' => '10. Calidad y reclamaciones',
                'p' => [
                    'Respondemos de que la pieza no tenga defectos en el momento de la entrega. El consumidor puede reclamar un defecto que se manifieste dentro de los dos años siguientes a la entrega.',
                    'Las pequeñas marcas de soportes, las capas visibles y las desviaciones de décimas de milímetro son propias de la impresión 3D y no son defectos.',
                    'Las reclamaciones se presentan por correo a info@matplace.com. Las resolvemos en un plazo máximo de 30 días. La página Reclamaciones describe el procedimiento en detalle.',
                ],
            ],
            [
                'h' => '11. Grabación de la impresión',
                'p' => [
                    'La cámara de la impresora graba cada impresión. El vídeo solo muestra la base de impresión y la pieza, y usted puede verlo en la página del pedido.',
                    'Tras revisarlo, podemos publicar el vídeo time-lapse en el canal de YouTube de Matplace, salvo que usted desmarque el consentimiento de publicación al hacer el pedido. También puede rechazar la publicación más tarde en la página del pedido; entonces borramos el vídeo de YouTube. En las impresiones hechas a partir de fotos solo publicamos el vídeo si usted mismo marca el consentimiento.',
                ],
            ],
            [
                'h' => '12. Datos personales',
                'p' => [
                    'Tratamos los datos personales conforme a la Política de privacidad. Al transportista solo le facilitamos el nombre, la dirección y el teléfono necesarios para la entrega.',
                ],
            ],
            [
                'h' => '13. Litigios y disposiciones finales',
                'p' => [
                    'El contrato se rige por el derecho checo, en particular por el Código Civil (Ley n.º 89/2012) y por la Ley de protección del consumidor (Ley n.º 634/1992). Ello no priva al consumidor de la protección que le reconoce la legislación del país donde reside.',
                    'El consumidor puede resolver un litigio por vía extrajudicial ante la Inspección de Comercio Checa (www.coi.cz, adr.coi.cz).',
                    'A cada pedido se le aplican las condiciones vigentes en el momento de su pago. Anunciamos los cambios importantes de las condiciones en la web o por correo con al menos 14 días de antelación. La versión checa de estas condiciones es la jurídicamente vinculante.',
                ],
            ],
        ],
    ],

    'cookies' => [
        'title' => 'Cookies',
        'description' => 'Qué cookies usa matplace: las necesarias para la sesión, el idioma y la moneda, estadísticas propias sin cookies y Google Analytics y Meta solo si acepta.',
        'lead' => 'Sin su consentimiento solo usamos las cookies que la web necesita para funcionar. Las herramientas de analítica y de marketing se cargan únicamente cuando usted las permite.',
        'sections' => [
            [
                'h' => '1. Qué son las cookies',
                'p' => [
                    'Las cookies son pequeños archivos de texto que una web guarda en su navegador. Algunas son necesarias para que la web funcione. Las demás solo las usamos con su consentimiento.',
                ],
            ],
            [
                'h' => '2. Cookies necesarias',
                'p' => [
                    'Estas cookies se guardan siempre, porque sin ellas la web no funciona. No requieren consentimiento.',
                ],
                'li' => [
                    'Sesión: mantiene su sesión iniciada durante la visita y protege los formularios contra el uso indebido.',
                    'Inicio de sesión: le recuerda si elige mantener la sesión iniciada.',
                    '«lang_seen»: recuerda que ya ha visitado la web, para que la página de inicio no vuelva a redirigirle a otro idioma. Dura 1 año.',
                    '«currency»: la moneda en la que quiere ver los precios. Dura 1 año.',
                    '«ref»: el enlace del diseñador por el que ha llegado, para que la visita se le cuente a él. Dura 30 días.',
                    'Sesión anónima: asocia a su navegador los archivos subidos y los cálculos aunque no haya iniciado sesión.',
                    '«consent»: su elección en la barra de cookies.',
                ],
            ],
            [
                'h' => '3. Estadísticas propias sin cookies',
                'p' => [
                    'Medimos las visitas con estadísticas propias, directamente en nuestro servidor. Para ello no guardamos ninguna cookie adicional en su navegador. Contamos las páginas vistas, el origen de la visita y eventos como la descarga de un modelo.',
                ],
            ],
            [
                'h' => '4. Herramientas de analítica y de marketing',
                'p' => [
                    'Estas herramientas solo se cargan después de que usted las permita en la barra de cookies. Sin consentimiento no se cargan.',
                ],
                'li' => [
                    'Analítica: Google Analytics 4. Nos ayuda a entender cómo se usa la web.',
                    'Marketing: el píxel de Meta. Mide la eficacia de nuestra publicidad en las redes de Meta.',
                ],
            ],
            [
                'h' => '5. Cómo dar, cambiar o retirar el consentimiento',
                'p' => [
                    'En su primera visita aparece una barra en la que puede permitir por separado la analítica y el marketing, o rechazar ambos. Su elección se guarda en la cookie «consent».',
                    'Puede cambiar o retirar su consentimiento en cualquier momento, con la misma facilidad con que lo dio: el enlace «Configuración de cookies» al pie de cada página (y el botón bajo este texto) abre de nuevo la barra. También puede bloquear o borrar las cookies en los ajustes del navegador; sin las cookies necesarias, sin embargo, no funcionarán el inicio de sesión ni los pedidos.',
                ],
            ],
            [
                'h' => '6. Terceros',
                'p' => [
                    'Si da su consentimiento, estas empresas también tratan datos de su visita, conforme a sus propias políticas:',
                ],
                'li' => [
                    'Google: policies.google.com/privacy',
                    'Meta: facebook.com/privacy/policy',
                ],
            ],
            [
                'h' => '7. Contacto',
                'p' => [
                    'Envíe sus preguntas sobre cookies y privacidad a info@matplace.com. La web la gestiona matplace s.r.o., número de identificación 22499008.',
                ],
            ],
        ],
    ],
];
