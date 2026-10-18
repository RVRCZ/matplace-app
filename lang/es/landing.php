<?php

// Páginas de entrada (docs/AH.md §11): los bloques bajo la herramienta. "order" = Antes de pedir (FAQ, 'farm' => true
// solo mientras ofrecemos imprimir con nosotros), "who" = Para quién. App\Support\LandingPage rellena los marcadores.
return [
    'titles' => [
        'result' => 'Así quedó',
        'result_lead' => 'Impresiones reales de nuestra granja con su tamaño, material, precio y tiempo de impresión.',
        'order' => 'Antes de pedir',
        'who' => 'Para quién es',
        'next' => 'Siguiente paso',
        'next_tools' => 'Herramientas parecidas',
        'next_blog' => 'Para leer',
    ],

    'calc' => [
        'order' => [
            ['q' => '¿Cuánto tarda en llegar la impresión?', 'farm' => true, 'a' => 'El cálculo muestra el tiempo de impresión justo después de subir el archivo. La impresión empieza en cuanto hay una impresora libre. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia. Todos los tiempos son una estimación.'],
            ['q' => '¿Cuánto cuesta el envío?', 'farm' => true, 'a' => 'Con Packeta a un punto de recogida en Chequia :cz_point, a domicilio :cz_home, a Eslovaquia desde :sk_point, a otros países de la UE desde :eu_point. El pedido muestra el importe exacto según el país y el peso del paquete.'],
            ['q' => '¿Qué tan exactos son el tamaño y el precio?', 'a' => 'El cálculo toma su archivo tal como es: medidas en milímetros que puede cambiar y el resultado exacto del laminador para el tiempo y el material. La calidad estándar imprime en capas de 0,2 mm. La revisión del modelo avisa de paredes demasiado finas y de piezas que no caben en la impresora.'],
            ['q' => '¿Puedo solo descargar el modelo e imprimirlo en casa?', 'a' => 'Sí. «Descargar el modelo» le da un archivo STL o un proyecto listo para su impresora (OrcaSlicer, Bambu Studio, PrusaSlicer) con los ajustes del cálculo.'],
            ['q' => '¿Y si el modelo está dañado?', 'a' => 'El cálculo intenta calcularlo igualmente y la revisión del modelo dice qué falla (agujeros, caras invertidas o duplicadas). Puede repararlo gratis con la herramienta de reparación y seguir con el archivo reparado.'],
            ['q' => '¿Y si la impresión no me gusta?', 'farm' => true, 'a' => 'Antes de pedir ve la vista 3D, el tamaño, el tiempo de impresión y el precio. Cada impresión se fabrica por encargo, por eso no se puede devolver sin motivo en 14 días. Un defecto (grietas, capas que faltan, un tamaño distinto del pedido) se reclama según nuestra política de reclamaciones.'],
        ],
        'who' => [
            ['t' => 'Un repuesto de internet', 'text' => 'Encontró un soporte, una tapa o una rueda en Printables o Thingiverse. Suba el STL, vea su tamaño y el tiempo de impresión y decida si lo imprimimos nosotros o usted en casa.'],
            ['t' => 'Su propio modelo CAD', 'text' => 'Diseñó una pieza en Fusion, Onshape o FreeCAD. El cálculo revisa las paredes y el tamaño, muestra el material y acepta también archivos STEP.'],
            ['t' => 'Tiene su propia impresora', 'text' => 'Descargue un proyecto para su máquina con los ajustes del cálculo. Los modelos de dos colores llevan también el cambio de filamento.'],
        ],
    ],

    'figure' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un busto impreso y de qué tamaño es?', 'farm' => true, 'a' => 'Un busto de 80 mm de alto sale con nosotros por 99 CZK, de 120 mm por 130 CZK y de 150 mm por 200 CZK (PLA+, una pieza, sin envío; cálculos en la web del 17/10/2026). Cambie el tamaño en el cálculo y el precio se ajusta.'],
            ['q' => '¿Cuánto tardan la impresión y la entrega?', 'farm' => true, 'a' => 'El modelo tarda de 2 a 4 minutos. Un busto de 80 mm se imprime en una hora aproximadamente, uno de 150 mm en algo menos de tres horas. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia.'],
            ['q' => '¿De qué y en qué color se imprime el busto?', 'a' => 'En un solo color de PLA o PLA+. A las figuras les van mejor los tonos mate, en los que se notan menos las capas.'],
            ['q' => '¿Y si el modelo no me gusta?', 'a' => 'Primero lo ve en 3D. Dentro del límite diario (:guest sin cuenta, :account con cuenta) puede probar otra foto; la base, el nombre y la dedicatoria se cambian sin generar de nuevo.'],
            ['q' => '¿Puedo descargar el modelo e imprimirlo yo?', 'a' => 'Sí, como STL o como proyecto para su impresora. Los bustos que imprimimos no necesitaron soportes; la revisión del cálculo lo comprueba en su modelo.'],
            ['q' => '¿Cómo recibo la impresión?', 'farm' => true, 'a' => 'La enviamos con Packeta a un punto de recogida o a una dirección en la UE (en Chequia desde :cz_point). Al entregar el paquete le llega un correo con el enlace de seguimiento.'],
        ],
        'who' => [
            ['t' => 'Un regalo con dedicatoria', 'text' => 'Un busto de 120 mm con nombre y dedicatoria en la base, para un cumpleaños, un aniversario o como agradecimiento. Cabe en una estantería o en el escritorio.'],
            ['t' => 'Un compañero, un entrenador, una profesora', 'text' => 'Para una despedida o el fin de temporada: basta una foto de frente y la firma del equipo cabe en la base.'],
            ['t' => 'Para usted', 'text' => 'Su propia figura de 80 mm para el escritorio, o una base para pintar con acrílicos.'],
        ],
    ],

    'pet' => [
        'order' => [
            ['q' => '¿Cuánto tardan la impresión y la entrega?', 'farm' => true, 'a' => 'El modelo tarda de 2 a 4 minutos y la impresión de una a tres horas según el tamaño. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia.'],
            ['q' => '¿De qué y en qué color se imprime la figura?', 'a' => 'En un solo color de PLA o PLA+. El pelo y los detalles están en la forma, no en el color; los tonos mate los muestran mejor.'],
            ['q' => '¿Y si el modelo no me gusta?', 'a' => 'Primero lo ve en 3D. Dentro del límite diario (:guest sin cuenta, :account con cuenta) puede probar otra foto u otro estilo; la base se cambia sin generar de nuevo.'],
            ['q' => '¿Puedo descargar el modelo e imprimirlo yo?', 'a' => 'Sí, como STL o como proyecto para su impresora. La revisión del cálculo avisa de patas, orejas o colas finas que se imprimirían mal.'],
            ['q' => '¿Cómo recibo la impresión?', 'farm' => true, 'a' => 'La enviamos con Packeta a un punto de recogida o a una dirección en la UE (en Chequia desde :cz_point). Al entregar el paquete le llega un correo con el enlace de seguimiento.'],
        ],
        'who' => [
            ['t' => 'Un regalo para amantes de perros y gatos', 'text' => 'Una figura de 80 a 100 mm con el nombre del animal en la base, para la estantería o junto al comedero.'],
            ['t' => 'En recuerdo', 'text' => 'A partir de la foto favorita de una mascota que ya no está. Basta una foto nítida del animal entero de lado o de frente.'],
            ['t' => 'Para el coche o el escritorio', 'text' => 'Una figura más pequeña, de unos 60 mm, que cabe junto al monitor o en el salpicadero.'],
        ],
    ],

    'qr' => [
        'order' => [
            ['q' => '¿Qué tamaño debe tener el cartel para que se lea?', 'a' => 'En una mesa o un mostrador bastan los 70 mm por defecto; cada módulo del código mide entonces unos 2 mm. En una pared o en la entrada, donde se lee desde más lejos, elija un cartel mayor. En cada tamaño la herramienta muestra el tamaño del módulo y comprueba que el código se lea.'],
            ['q' => '¿Cuántos puedo pedir a la vez?', 'farm' => true, 'a' => 'Hasta 64 en un pedido. El cálculo muestra cuántos caben en una placa (el cartel por defecto: 6 en 250 × 250 mm); las demás placas se imprimen una tras otra.'],
            ['q' => '¿Cuánto tardan la impresión y la entrega?', 'farm' => true, 'a' => 'El cartel por defecto se imprime en unos 27 minutos. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Aguanta el cartel en el exterior?', 'a' => 'Para la mesa y el interior basta el PLA. Para sol directo y lluvia elija un material marcado para exterior en la comparación de materiales del cálculo.'],
            ['q' => '¿Y si el código no se lee?', 'a' => 'Pruebe el enlace con el móvil desde la vista previa en la pantalla antes de pedir. La herramienta vigila el tamaño del módulo y el contraste; si la impresión no se lee por un defecto de impresión, la reclama.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'No para el diseño ni la descarga. Solo la necesita para pedir la impresión con nosotros.'],
        ],
        'who' => [
            ['t' => 'Cafeterías y restaurantes', 'text' => 'La carta o el pedido en mesa: un cartel de 70 mm con soporte en cada mesa, el código lleva a su carta.'],
            ['t' => 'Alojamientos y Airbnb', 'text' => 'Wi‑Fi para huéspedes sin teclear la contraseña: un cartel de 100 mm en la estantería de la entrada, un escaneo y ya están conectados.'],
            ['t' => 'Tiendas, puestos, salones', 'text' => 'Un enlace a su tienda online, a las reseñas de Google o a las reservas: un cartel en la caja junto al datáfono.'],
        ],
    ],

    'organizer' => [
        'order' => [
            ['q' => '¿Cómo mido el cajón?', 'a' => 'Mida el ancho y el fondo interiores en el punto más estrecho (las esquinas del fondo suelen ser redondeadas) y reste de 2 a 3 mm para que el organizador entre y salga. Elija una altura unos milímetros menor que la del cajón, o no cerrará.'],
            ['q' => '¿Qué tan exactas son las medidas?', 'a' => 'Una impresora mantiene las medidas con unas décimas de milímetro, y una pieza grande puede encoger un poco al enfriarse. Por eso deje de 2 a 3 mm de holgura alrededor.'],
            ['q' => '¿Cuánto cuesta y cuánto tarda en imprimirse?', 'farm' => true, 'a' => 'Depende sobre todo del tamaño y la altura. El cálculo muestra el tiempo exacto y el precio antes de pedir nada; un organizador grande se imprime durante varias horas.'],
            ['q' => '¿Cabe un organizador grande en la impresora?', 'a' => 'El cálculo muestra enseguida si cabe en la impresora. Si es demasiado grande, divida el cajón en dos organizadores o use las bandejas modulares.'],
            ['q' => '¿Qué material?', 'a' => 'Para un cajón de oficina o taller basta el PLA. Para el baño o cerca del horno elija PETG, que soporta mejor el calor y la humedad.'],
            ['q' => '¿Y si no encaja?', 'farm' => true, 'a' => 'Fabricamos la impresión con sus medidas, por eso no se puede devolver sin motivo. Si no coincide con las medidas del pedido, es un defecto y lo reclama.'],
        ],
        'who' => [
            ['t' => 'Taller', 'text' => 'Puntas, tornillos y tacos en el cajón del banco: una cuadrícula de 4 × 3 compartimentos, de unos 40 mm de alto.'],
            ['t' => 'Cocina', 'text' => 'Cubiertos, especias y utensilios pequeños: un organizador exactamente del ancho del cajón, en PETG.'],
            ['t' => 'Escritorio', 'text' => 'Bolígrafos, cargadores y cables: un organizador bajo de 20 a 30 mm para que el cajón cierre bien.'],
        ],
    ],

    'box' => [
        'order' => [
            ['q' => '¿Cómo mido lo que va dentro?', 'a' => 'Mida el objeto en su punto más ancho y añada de 1 a 2 mm por cada lado. La herramienta usa las medidas interiores y calcula las exteriores según las paredes y el fondo.'],
            ['q' => '¿Qué tan exacto es el ajuste?', 'a' => 'Una impresora mantiene las medidas con unas décimas de milímetro. La tapa tiene 0,25 mm de holgura, que puede cambiar; pruébela en la primera caja de una serie nueva.'],
            ['q' => '¿Cuánto cuesta y cuánto tarda en imprimirse?', 'farm' => true, 'a' => 'Una caja pequeña se imprime en una hora aproximadamente; el cálculo muestra el precio y el tiempo exacto antes de pedir nada. Enviamos el mismo día en que termina, en días laborables.'],
            ['q' => '¿Qué material?', 'a' => 'Para cosas pequeñas y electrónica dentro basta el PLA. Para calor o humedad elija PETG.'],
            ['q' => '¿Cuánto cuesta el envío?', 'farm' => true, 'a' => 'Con Packeta a un punto de recogida en Chequia :cz_point, a domicilio :cz_home, a Eslovaquia desde :sk_point, a otros países de la UE desde :eu_point.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'No para el diseño ni la descarga. Solo la necesita para pedir la impresión con nosotros.'],
        ],
        'who' => [
            ['t' => 'Electrónica', 'text' => 'Una caja para un Arduino, un ESP32 o una fuente, con agujeros para cables y un corte para el conector.'],
            ['t' => 'Cosas pequeñas', 'text' => 'Pendientes, tarjetas SD, botones de repuesto: una cajita con tapa, a medida.'],
            ['t' => 'Envoltorio de regalo', 'text' => 'Una caja hecha a la medida del regalo, en el color que elija, con ranura para una cinta si quiere.'],
        ],
    ],
];
