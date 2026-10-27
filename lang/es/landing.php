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
            ['q' => '¿Cuánto tardan la impresión y la entrega?', 'farm' => true, 'a' => 'El cartel por defecto de 70 mm se imprime en unos 25 minutos. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
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
            ['q' => '¿Cuánto cuesta y cuánto tarda en imprimirse?', 'farm' => true, 'a' => 'Depende sobre todo del tamaño y la altura. Un organizador de 200 × 120 × 40 mm con 3 × 2 compartimentos se imprime en unas dos horas y media y sale por 250 CZK (PLA+, sin envío). El cálculo muestra el tiempo y el precio exactos antes de pedir nada.'],
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
            ['q' => '¿Cuánto cuesta y cuánto tarda en imprimirse?', 'farm' => true, 'a' => 'Una caja de 84 × 54 × 32 mm con tapa se imprime en unos 35 minutos y sale por 99 CZK (PLA+, sin envío). Para otras medidas el cálculo muestra el precio y el tiempo antes de pedir nada. Enviamos el mismo día en que termina, en días laborables.'],
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

    // páginas de entrada de la sesión N1 (docs/AP.md): SVG a STL, reparación, revisión
    'svg_to_stl' => [
        'order' => [
            ['q' => '¿Cuánto extruir la forma para que se imprima y aguante?', 'a' => 'Al menos 0,6 mm, es decir, tres capas de 0,2 mm; una etiqueta o un letrero de puerta admite 2 a 3 mm, un colgante de llaves 4 a 6 mm y una forma que deba sostenerse sola, 20 mm o más. Cuanto más alta, más gramos y minutos; el cálculo lo recalcula tras cada cambio.'],
            ['q' => '¿Cómo de finas pueden ser las líneas?', 'a' => 'Una boquilla de 0,4 mm necesita dos líneas juntas, así que al menos 0,8 mm. La herramienta marca de antemano las líneas de menos de 0,8 mm; engrósela en su editor o agrande la forma.'],
            ['q' => '¿Cuánto cuesta una forma impresa y cuánto tarda?', 'farm' => true, 'a' => 'El gato de 80 mm extruido a 6 mm pesa 9,6 g y se imprime en 17 minutos, 99 CZK con nosotros, que es el pedido mínimo (PLA, sin envío). Una forma mayor o más alta muestra sus gramos, minutos y precio en el cálculo antes de encargar nada.'],
            ['q' => '¿Puede ir el motivo en un color distinto de la base?', 'a' => 'Un contorno extruido es de un solo color. Si el motivo debe ir sobre una placa de otro color, cambie el acabado a relieve: los colores quedan uno sobre otro y la impresora los resuelve con un cambio de filamento a la altura de la placa, en nuestra granja o en casa.'],
            ['q' => '¿Cuánto tarda el envío?', 'farm' => true, 'a' => 'Enviamos el mismo día en que termina la impresión, en días laborables. Packeta suele entregar en 1 a 2 días laborables en Chequia, a un punto de recogida por :cz_point, a domicilio por :cz_home; a Eslovaquia desde :sk_point, a la UE desde :eu_point.'],
            ['q' => '¿Puedo seguir editando el resultado?', 'a' => 'Sí. El STL es un modelo normal: lo escala en la calculadora, la herramienta Puzle lo corta en piezas, Molde hace con él un molde de colada y Cortador de galletas fabrica un cortador con el mismo contorno.'],
        ],
        'who' => [
            ['t' => 'Logotipo de empresa o club', 'text' => 'Un logotipo de un archivo vectorial como letrero de mesa, colgante de llaves o emblema en una puerta, de 60 a 120 mm, exactamente según el diseño.'],
            ['t' => 'Pieza exacta de un plano 2D', 'text' => 'Un separador, una arandela, una plantilla o un inserto que tiene dibujado en CAD o en Inkscape: el ancho en milímetros, los huecos siguen siendo huecos.'],
            ['t' => 'Dibujo e icono', 'text' => 'Un dibujo infantil convertido en contorno, un icono de Noun Project o una silueta de nuestra biblioteca como adorno, imán o colgante.'],
        ],
    ],

    'repair' => [
        'order' => [
            ['q' => '¿Cómo sé que el modelo necesita reparación?', 'a' => 'El laminador avisa de non-manifold, aristas abiertas o malla no estanca, en la vista previa faltan trozos de superficie, o la impresión tiene agujeros y capas de más. Si no está seguro, suba antes el modelo a la revisión; dice qué falla y ofrece la reparación con un botón.'],
            ['q' => '¿Cuánto tarda la reparación y qué tamaño de archivo admite?', 'a' => 'Un archivo de hasta 120 MB. Un modelo normal está listo en unos segundos; una malla con millones de triángulos, en menos de un minuto; la página dice qué está pasando.'],
            ['q' => '¿Cuánto cuesta imprimir el modelo reparado?', 'farm' => true, 'a' => 'La reparación no cuesta nada. La impresión con nosotros la calcula la calculadora a partir de los gramos y los minutos; el pedido mínimo es de 99 CZK, y el busto de 60 × 52 × 78 mm del ejemplo sale por unas 110 CZK (PLA, sin envío).'],
            ['q' => '¿Sirve la reparación también para un modelo de impresión en resina?', 'a' => 'Sí, el STL es el mismo para FDM y resina, y ambos necesitan una malla cerrada. Para resina, vacíe el modelo tras la reparación con orificios de drenaje: ahorra resina y la pieza no se agrieta.'],
            ['q' => '¿Cómo recibo la impresión?', 'farm' => true, 'a' => 'La enviamos con Packeta a un punto de recogida (en Chequia :cz_point) o a una dirección en la UE. Enviamos el mismo día en que termina la impresión, en días laborables; en cuanto entregamos el paquete, llega un correo con el seguimiento.'],
            ['q' => '¿Y si el laminador sigue avisando de un error tras la reparación?', 'a' => 'El informe dice qué no se pudo hacer: un trozo grande de superficie que falta o una pared sin grosor no se reconstruyen. El resto es trabajo manual en Blender o Meshmixer; el laminador suele pasar por alto fallos pequeños y la impresión sale bien.'],
        ],
        'who' => [
            ['t' => 'Un modelo de internet', 'text' => 'Un soporte, una tapa o una figura de Printables, Thingiverse o MakerWorld que el laminador rechaza. La reparación tarda segundos y no cambia el tamaño.'],
            ['t' => 'Un escaneo con el móvil', 'text' => 'La fotogrametría y los escáneres dejan agujeros y motas. La reparación los cierra y une los fragmentos; la forma del escaneo se mantiene.'],
            ['t' => 'Una exportación de CAD o de un juego', 'text' => 'Un archivo de SketchUp, Tinkercad o un modelo de videojuego con caras invertidas y duplicadas. La reparación las gira y las elimina.'],
        ],
    ],

    'check' => [
        'order' => [
            ['q' => '¿Mira la revisión también el grosor de las paredes?', 'a' => 'Solo la medida más fina de todo el modelo: menos de 0,8 mm es error, porque una boquilla de 0,4 mm no imprime esa pared. Un punto fino dentro de un modelo por lo demás grueso no lo detecta la revisión; eso lo muestra el laminador.'],
            ['q' => '¿Qué pasa con un modelo que no supera la revisión?', 'a' => 'Los agujeros y las caras invertidas van a la reparación con un botón. Un modelo mayor que la impresora se reduce con la escala en la calculadora, o se divide en piezas con pasadores. Las unidades equivocadas se resuelven guardando de nuevo en milímetros.'],
            ['q' => 'Si la revisión es correcta, ¿puedo encargar la impresión enseguida?', 'farm' => true, 'a' => 'Sí. El botón del precio abre el modelo en la calculadora, donde ve los gramos, el tiempo de impresión y el precio; el pedido mínimo es de 99 CZK. La misma revisión se ejecuta en el cálculo, así que nada se avisa dos veces.'],
            ['q' => '¿Se revisan también STEP, 3MF y OBJ?', 'a' => 'Sí. Un STEP de CAD se convierte en malla y se revisa como un STL; en un 3MF se revisa la forma, los colores y los ajustes de impresión quedan aparte.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'El cálculo muestra el tiempo de impresión. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Guardan mi archivo?', 'a' => 'El archivo sirve para la revisión, la reparación y el cálculo y no se entrega a nadie más. La revisión no lo toca; el modelo que descarga es el mismo archivo que subió.'],
        ],
        'who' => [
            ['t' => 'Antes de imprimir en casa', 'text' => 'Un modelo descargado antes de laminar: en unos segundos sabe si tiene las unidades correctas, una malla cerrada y si cabe en la cama.'],
            ['t' => 'Taller, escuela, FabLab', 'text' => 'Archivos de alumnos hechos en Tinkercad y SketchUp: el informe le dice a cada uno qué corregir, sin instalar ningún programa.'],
            ['t' => 'Antes de encargar con nosotros', 'text' => 'Cada modelo de nuestra granja pasa por la misma revisión. Quien la hace de antemano sabe que el pedido pasará sin preguntas.'],
        ],
    ],

    // páginas de entrada de la sesión N1, lote 2 (docs/AP.md §4): vaciar, dividir, G-code
    'hollow' => [
        'order' => [
            ['q' => '¿Cuánto ahorro en el precio de impresión con ustedes?', 'farm' => true, 'a' => 'Depende del tamaño. Un cilindro de ⌀ 120 × 200 mm costó 910 CZK macizo y 500 CZK vaciado con pared de 2,5 mm (PLA+, sin envío); en una pieza pequeña el precio no cambia, porque el pedido mínimo es de 99 CZK. La herramienta muestra la diferencia justo después del vaciado y el cálculo la confirma.'],
            ['q' => '¿Se imprime un modelo hueco sin soportes?', 'a' => 'Por fuera, igual que uno macizo. Por dentro, el hueco tiene un techo que el laminador puentea o apuntala con soportes interiores; esos se sacuden por los orificios de drenaje tras imprimir. Para FDM elija una pared de 2 a 3 mm; el laminador la rellena además según sus ajustes.'],
            ['q' => '¿Cómo vacío un modelo para impresión con resina?', 'a' => 'Pared de 2 a 3 mm y al menos dos orificios de 3 a 4 mm en el fondo, para que la resina salga y no se forme vacío. Un cilindro macizo de ⌀ 120 × 200 mm se tragaría 2,3 litros de resina; hueco, un cuarto de litro aproximadamente.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un modelo hueco se imprime en menos tiempo: el cilindro del ejemplo, en 6 h 36 min en lugar de 8 h 14 min. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Puedo lastrar el hueco con arena?', 'a' => 'Sí. Vierta arena o perdigones por los orificios del fondo y séllelos con pistola de silicona o un trozo de plástico. Un pisapapeles, un soporte de bandera o una figura quedan firmes y el modelo sigue siendo ligero de imprimir.'],
            ['q' => '¿Es gratis el vaciado y necesito una cuenta?', 'a' => 'El vaciado y la descarga del modelo hueco son gratis y sin cuenta; el archivo sirve solo para el cálculo y no se entrega a nadie. Solo necesita cuenta cuando nos encarga el modelo impreso.'],
        ],
        'who' => [
            ['t' => 'Figura o busto grande', 'text' => 'Por encima de 150 mm el hueco ahorra cientos de gramos; un modelo de más de 250 mm divídalo además en piezas que quepan en la impresora.'],
            ['t' => 'Impresión con resina', 'text' => 'Una pieza maciza se agrieta y consume litros. Hueca, con pared de 2 a 3 mm y orificios de drenaje, se imprime limpia y barata.'],
            ['t' => 'Pisapapeles y soportes', 'text' => 'Un hueco para arena o perdigones: una figura, un soporte de bandera o una peana quedan firmes y la impresión sigue siendo rápida.'],
        ],
    ],

    'split' => [
        'order' => [
            ['q' => '¿Cuánto cuesta imprimir las piezas con ustedes?', 'farm' => true, 'a' => 'El cálculo suma todas las piezas y los pasadores: el cilindro de ⌀ 120 × 320 mm en dos piezas salió por 1.440 CZK (814 g, PLA+, sin envío). Encarga las piezas en un solo pedido y las recibe juntas.'],
            ['q' => '¿Cuántos cortes harán falta?', 'a' => 'La herramienta lo dice tras subir el archivo: una figura de 60 cm necesita dos cortes para una cama de 250 mm, es decir, tres piezas. Puede mover los planos a donde la unión se esconda, por ejemplo a la cintura o al borde de la peana.'],
            ['q' => '¿Qué pasadores uso y cuántos habrá?', 'a' => 'Los pasadores tienen 6 mm de diámetro y los agujeros 0,2 mm de holgura. La herramienta pone en cada unión tantos como quepan (el cilindro del ejemplo recibió cuatro) y los añade al archivo como pieza aparte; también sirven tacos de madera de Ø 6 mm.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'El cálculo suma el tiempo de todas las piezas; el cilindro del ejemplo se imprimió en 13 h 8 min. Las piezas se imprimen una tras otra, enviamos el mismo día en que termina la última, en días laborables; Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Puedo agrandar el modelo al mismo tiempo?', 'a' => 'Para eso está la herramienta Tamaño real: indica la altura en centímetros y el modelo se escala y se divide de una vez. Aquí el modelo solo se corta; cambie el tamaño antes en la calculadora.'],
            ['q' => '¿Es gratis la división y necesito una cuenta?', 'a' => 'La división y la descarga de las piezas son gratis y sin cuenta; el archivo sirve solo para el cálculo y no se entrega a nadie. Solo necesita cuenta cuando nos encarga las piezas impresas.'],
        ],
        'who' => [
            ['t' => 'Figura a tamaño real', 'text' => 'Una estatua, un busto o un personaje de videojuego de más de 250 mm: piezas con pasadores, número en el corte y mapa de montaje.'],
            ['t' => 'Cosplay y atrezo', 'text' => 'Un casco, una espada o un escudo más largos que la cama: las colas de milano aguantan sin pegamento y las piezas viajan desmontadas.'],
            ['t' => 'Una pieza larga', 'text' => 'Un riel, una tapa o un soporte de más de 250 mm: un corte, dos pasadores, y la unión lijada y pintada no se nota.'],
        ],
    ],

    'gcode' => [
        'order' => [
            ['q' => '¿Cuánto cuesta imprimir con ustedes un modelo a partir de G-code?', 'farm' => true, 'a' => 'Como con cualquier modelo: el cálculo lo lamina para nuestras impresoras y pone el precio según los gramos y los minutos; el tiempo y los gramos de la cabecera del G-code son solo orientativos. El pedido mínimo es de 99 CZK (PLA, sin envío).'],
            ['q' => '¿No pueden imprimir directamente mi G-code?', 'a' => 'No. El G-code está escrito para una impresora y sus ajustes; nuestra granja imprime a partir de un modelo que lamina para su propia máquina y material. La conversión a modelo lo hace posible y, además, puede cambiar el tamaño o los colores.'],
            ['q' => '¿Por qué la superficie del modelo es escalonada?', 'a' => 'El modelo lleva las capas de la impresión de la que procede, por ejemplo 0,2 mm. Al imprimir, los escalones quedan cubiertos por las capas nuevas y no se ven; tras agrandar el modelo, líjelos o imprima con una capa más fina.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'El cálculo muestra el tiempo de impresión. Enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => 'Tengo un .gcode.3mf de Bambu Studio o un .bgcode de Prusa. ¿Qué hago?', 'a' => 'Un archivo .gcode.3mf es un zip: descomprímalo y suba el .gcode de su carpeta Metadata. La herramienta no lee .bgcode binario; en PrusaSlicer desactive el G-code binario en los ajustes de la impresora y exporte de nuevo, o convierta el archivo con la herramienta bgcode de Prusa.'],
            ['q' => '¿Es gratis la conversión y necesito una cuenta?', 'a' => 'La conversión y la descarga son gratis y sin cuenta; el G-code sirve solo para la conversión y el cálculo y no se entrega a nadie. Solo necesita cuenta cuando nos encarga el modelo impreso.'],
        ],
        'who' => [
            ['t' => 'Modelo de origen perdido', 'text' => 'La impresión salió bien, pero el STL desapareció. Del G-code de la tarjeta SD sale un modelo que vuelve a imprimir, escalar o recolorear.'],
            ['t' => 'Un archivo de otra persona', 'text' => 'Solo le han dado un G-code para otra impresora. Como modelo lo lamina para la suya, o nos lo encarga impreso.'],
            ['t' => 'Un archivo en la tarjeta SD', 'text' => 'G-codes antiguos de la impresora como modelos en un catálogo: ve la forma, el tamaño y el precio de otra pieza.'],
        ],
    ],
];
