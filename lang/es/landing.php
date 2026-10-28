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

    // páginas de entrada de la sesión N1, lote 3 (docs/AP.md §5): litofanía, mapa
    'relief' => [
        'order' => [
            ['q' => '¿Cuánto cuesta una litofanía con ustedes?', 'farm' => true, 'a' => 'Un panel de 100 × 75 mm con soporte de mesa salió por 100 CZK; una lámpara para casquillo E27 de 100 mm de diámetro y 150 mm de alto, por 250 CZK (sin envío, cálculo del 18 de octubre de 2026). Imprimimos en material blanco; el precio exacto de su foto y tamaño lo muestra el cálculo antes de encargar nada.'],
            ['q' => '¿Qué luz pongo en la lámpara?', 'a' => 'Una bombilla LED E27 o E14 de unos 10 W como máximo; nada de incandescente ni halógena, el PLA se ablanda hacia los 55 °C. La luz cálida da tonos más suaves; la fría, un contraste más marcado. En la base abierta cabe una tira LED; en la cerrada, una vela LED.'],
            ['q' => '¿Cómo imprimo una litofanía en casa?', 'a' => 'El panel de pie y macizo, la lámpara de pie sobre su base, el relieve en plano sin soportes; el proyecto descargado para el laminador lleva los ajustes que usamos nosotros. El material es PLA blanco; cualquier otro color tiñe u oscurece la imagen.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un panel de 100 × 75 mm se imprime en 1 h 9 min aproximadamente; una lámpara de 150 mm, en unas 2 h 29 min. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Basta una foto del móvil?', 'a' => 'Sí. En una placa de 100 mm solo cabe el detalle que da una boquilla de 0,4 mm; lo que cuenta es el contraste y un solo motivo principal, no la resolución. Ajuste los deslizadores de brillo y contraste para que la herramienta indique el máximo de tonos, y la vista previa a contraluz muestra el resultado.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no; la foto no se guarda y en el modelo queda solo la forma. Solo necesita cuenta cuando nos encarga la litofanía impresa.'],
        ],
        'who' => [
            ['t' => 'Un regalo a partir de una foto', 'text' => 'Un retrato, una foto de boda o de familia como panel de 100 × 75 mm con soporte para una estantería o la ventana; la imagen aparece cuando el sol o una lámpara la atraviesan.'],
            ['t' => 'Una lámpara para la habitación infantil', 'text' => 'La foto alrededor de un cilindro con bombilla LED E27: de día una lámpara blanca, por la noche una imagen encendida.'],
            ['t' => 'Un relieve para la pared', 'text' => 'Una foto o un logotipo como imagen en relieve que se ve sin luz; como recuerdo, para la oficina o como cartel.'],
        ],
    ],

    'map' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un mapa con ustedes?', 'farm' => true, 'a' => 'La Ciudad Vieja de Praga, 500 m en placa de 150 mm, salió por 240 CZK en un color (85 g, sin envío, cálculo del 18 de octubre de 2026). Los colores de la base, las calles y los edificios los elige al hacer el pedido; ve el precio antes de encargar nada.'],
            ['q' => '¿Qué extensión elegir?', 'a' => 'Para un centro o un barrio, 500 m; para una ciudad pequeña, 1 km; para una grande, 2 km; en una placa de 150 mm de un kilómetro, un edificio de 20 m mide 3 mm. Una zona mayor elíjala como paisaje de 2 a 20 km, donde lo principal es el relieve del terreno y los pueblos son bloques bajos.'],
            ['q' => '¿Cómo imprimo el mapa en casa?', 'a' => 'En plano, sin soportes, capas de 0,2 mm. El proyecto 3MF para OrcaSlicer y PrusaSlicer lleva dos cambios de filamento (calles, edificios); la impresora se detiene, cambia la bobina y la impresión sigue. El STL es un solo cuerpo para imprimir en un color.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un mapa de 150 mm se imprime en unas 3 h 42 min. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => 'Mi pueblo no tiene alturas de edificios en el mapa. ¿Qué pasa?', 'a' => 'Los edificios sin datos reciben la altura que indique, por ejemplo 8 m para casas unifamiliares. La vista previa de la zona muestra qué edificios y calles tiene OpenStreetMap; lo que falte puede añadirlo cualquiera a OpenStreetMap, y el mapa lo tomará cuando se actualicen los datos.'],
            ['q' => '¿Qué irá en el marco?', 'a' => 'Un nombre en relieve que usted indica: el de la ciudad, una calle o una dedicatoria. El marco también se puede desactivar, y la placa termina entonces en el borde del mapa.'],
        ],
        'who' => [
            ['t' => 'Regalo de la ciudad natal', 'text' => 'El centro donde alguien creció o donde fue la boda: 500 m en placa de 150 mm con el nombre en el marco, en tres colores.'],
            ['t' => 'Barrio, campus, recinto', 'text' => 'Para arquitectos, escuelas y gestores de recintos: edificios con alturas reales y calles por tipo, en un minuto y sin dibujar.'],
            ['t' => 'Paisaje para una excursión', 'text' => 'Una colina, una presa o un valle como relieve con exageración, con carreteras y agua en la superficie; de 2 a 20 km en una sola placa.'],
        ],
    ],

    // herramientas navideñas de la sesión N1 (docs/AP.md §6): galleta, adorno, cortador, placa, topper, letra
    'gingerbread' => [
        'order' => [
            ['q' => '¿Cuándo encargar para que las galletas lleguen antes de Navidad?', 'farm' => true, 'a' => 'Una galleta de 90 mm se imprime en 20 minutos y la impresión empieza en cuanto hay una impresora libre; enviamos el mismo día en que termina, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia. En diciembre déjese margen y encargue al menos con una semana de antelación.'],
            ['q' => '¿Cómo cuelgo la galleta?', 'a' => 'Por la argolla superior, con agujero de 3 a 6 mm, pasa una cinta, un cordel o un gancho de adorno. Una galleta de 90 mm pesa unos 11 g, así que cuelga incluso de una rama fina; mueva la argolla a donde la forma quede equilibrada, por ejemplo la punta de una estrella.'],
            ['q' => '¿Qué colores elegir para la base y el glaseado?', 'a' => 'El clásico es base marrón con glaseado blanco; también quedan bien rojo con dorado, verde con blanco o azul marino con plateado. Los filamentos mate disimulan las capas mejor que los brillantes. Elige entre las bobinas que tenemos en stock ahora mismo.'],
            ['q' => '¿Cuánto cuesta el envío?', 'farm' => true, 'a' => 'Con Packeta a un punto de recogida en Chequia :cz_point, a domicilio :cz_home, a Eslovaquia desde :sk_point, a otros países de la UE desde :eu_point. Varias galletas en un pedido viajan en un solo paquete.'],
            ['q' => '¿Cómo imprimo la galleta en casa?', 'a' => 'En plano, boca arriba, sin soportes, en PLA. El proyecto descargado para OrcaSlicer, Bambu Studio o PrusaSlicer lleva una pausa a la altura del glaseado: la impresora se detiene, cambia la bobina y la impresión sigue en blanco. Una impresora con varias bobinas lo hace sola.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga las galletas impresas; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Etiquetas para el árbol de toda la familia', 'text' => 'Cada uno tiene su galleta con nombre: una cuesta 99 CZK; seis iguales, 140 CZK; para otros nombres, otro diseño.'],
            ['t' => 'Un regalo para la maestra o los compañeros', 'text' => 'Una galleta de 60 mm con nombre en una cinta en lugar de la etiqueta del paquete; tras abrirlo, cuelga en el árbol.'],
            ['t' => 'Guardería y taller', 'text' => 'Adornos con los nombres de los niños en su propia impresora: un proyecto con un cambio de filamento, 20 minutos por pieza.'],
        ],
    ],

    'ornament' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un adorno con ustedes y cuánto tarda en imprimirse?', 'farm' => true, 'a' => 'Un muñeco de jengibre de 80 mm se imprime en 18 minutos y cuesta 99 CZK, que es el pedido mínimo; seis unidades salen por 160 CZK, es decir, 27 CZK por adorno (PLA+, sin envío, cálculo del 18 de octubre de 2026). El cálculo muestra el precio de su adorno antes de encargar nada.'],
            ['q' => '¿Puedo hacer un adorno con el dibujo de un niño?', 'a' => 'Sí. Fotografíe el dibujo sobre papel blanco desde arriba con luz de día; la herramienta quita el fondo y funde los colores en 2 a 4 filamentos; repase con rotulador las líneas finas a lápiz. El adorno sigue entonces el contorno del dibujo, o lo pone en un círculo.'],
            ['q' => '¿Cuánto tarda el envío?', 'farm' => true, 'a' => 'Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point). Antes de Navidad, encargue con tiempo.'],
            ['q' => '¿Cómo imprimo el adorno en casa en varios colores?', 'a' => 'En plano, boca arriba, sin soportes. El proyecto descargado lleva cambios de filamento a las alturas de cada color; con una boquilla cambia las bobinas en la pausa, y una impresora con AMS, MMU o ACE las cambia sola.'],
            ['q' => '¿Aguanta el adorno en el exterior o junto a la chimenea?', 'a' => 'Para un árbol en casa basta el PLA. Cerca de un radiador o una chimenea elija PETG, que soporta más calor; un adorno fino no va bien al sol y la helada en el exterior, y los colores se desvanecen al sol directo.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga los adornos impresos; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Un árbol de dibujos infantiles', 'text' => 'Cada dibujo como adorno de 70 a 90 mm en tres colores, con argolla para la cinta; de una foto del dibujo en segundos.'],
            ['t' => 'Regalo de empresa con logotipo', 'text' => 'Un logotipo de un SVG como adorno en los colores de la empresa, hasta cuatro colores en una impresión, para clientes y equipo.'],
            ['t' => 'Para la abuela con los nombres de los nietos', 'text' => 'Un nombre en una de treinta tipografías sobre un círculo o una estrella, un color distinto para cada nieto.'],
        ],
    ],

    'cutter' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un cortador con ustedes?', 'farm' => true, 'a' => 'Un cortador del nombre Ela (82 × 54 mm) se imprime en 31 minutos y cuesta 99 CZK, que es el pedido mínimo (PLA+, sin envío, cálculo del 18 de octubre de 2026). Cada forma es un diseño y un cálculo propios; el número de unidades de una forma lo indica ahí.'],
            ['q' => '¿Cómo corto para que la masa no se pegue?', 'a' => 'Mantenga la masa fría y pase el cortador por harina antes de cada forma. Presione recto sobre el reborde, sin girar, y saque la forma del filo con un dedo; un filo afilado corta más limpio que uno recto.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un cortador se imprime en 25 a 35 minutos. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point). Para la repostería navideña, encargue con tiempo.'],
            ['q' => '¿Cómo imprimo el cortador en casa?', 'a' => 'Con el reborde abajo, sin soportes, en PLA o PETG; el proyecto descargado lleva la pared de 1 mm y las capas de 0,2 mm del cálculo. El sello, si se ha creado, se imprime al lado como pieza aparte.'],
            ['q' => '¿Puedo hacer un juego de letras para un nombre?', 'a' => 'Sí, de dos maneras: el nombre entero como un solo cortador para una galleta (dos líneas de 20 caracteres), o un cortador propio para cada letra, encargado por separado. Para letras pequeñas elija una tipografía gruesa para que la masa conserve la forma.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el cortador impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Galletas navideñas con nombre', 'text' => 'Un nombre o unas iniciales en tipografía manuscrita, de 80 a 120 mm, para las galletas de jengibre y de mantequilla de cada comensal.'],
            ['t' => 'Un número de cumpleaños', 'text' => 'Un número de 70 a 90 mm para las galletas de la fiesta; un año o una edad en una sola pieza.'],
            ['t' => 'Un logotipo en las galletas', 'text' => 'Un logotipo de un SVG como cortador y sello con los detalles dentro, para un evento de empresa o una cafetería.'],
        ],
    ],

    'nameplate' => [
        'order' => [
            ['q' => '¿Cuánto cuestan las etiquetas de regalo con ustedes?', 'farm' => true, 'a' => 'Una etiqueta de 70 mm con argolla se imprime en 10 minutos: una cuesta 99 CZK, que es el pedido mínimo; diez iguales, 120 CZK, es decir, 12 CZK cada una (PLA, sin envío, cálculo del 27 de octubre de 2026). Para nombres distintos hace un diseño por cada uno; ve el precio antes de encargar nada.'],
            ['q' => '¿Cómo cuelgo la etiqueta en un regalo?', 'a' => 'Marque la argolla: su agujero de 2 a 8 mm admite una cinta o un cordel. Una etiqueta de 70 mm pesa 5 g, así que cuelga de un lazo o de una rama; sin argolla la pega al papel de regalo con cinta de doble cara.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Una etiqueta se imprime en 10 a 25 minutos según el tamaño. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point). Antes de Navidad, encargue con tiempo.'],
            ['q' => '¿Cómo imprimo la etiqueta en casa en varios colores?', 'a' => 'En plano, sin soportes. El proyecto descargado lleva un cambio de filamento a la altura de cada capa; con una boquilla cambia la bobina en la pausa, y una impresora con AMS, MMU o ACE lo hace sola. El STL es un solo cuerpo para imprimir en un color.'],
            ['q' => '¿Aguanta la etiqueta en una mochila o en las llaves?', 'a' => 'Para un regalo basta el PLA. Para una mochila, las llaves o un collar elija PETG, deje el grosor de capa por defecto y una argolla de al menos 4 mm; las letras de al menos 6 mm de alto siguen legibles tras un año de uso.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga las etiquetas impresas; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Etiquetas para los regalos de Navidad', 'text' => 'Un hexágono o una estrella de 60 a 80 mm con argolla y nombre en dos colores: diez unidades por 120 CZK.'],
            ['t' => 'Letrero para la puerta infantil', 'text' => 'Una nube o una casa de 120 mm con el nombre y el animal favorito, tres colores, para la puerta o la estantería.'],
            ['t' => 'Etiqueta para mochila y llaves', 'text' => 'Un hueso, un escudo o un lazo en PETG con argolla de 4 mm, el nombre y el teléfono como dos capas de texto.'],
        ],
    ],

    'topper' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un topper con ustedes y cuándo llega?', 'farm' => true, 'a' => 'Un topper con el número 2 y el nombre Olivia (110 mm) se imprime en 55 minutos y cuesta 99 CZK, que es el pedido mínimo (PLA, sin envío, cálculo del 27 de octubre de 2026). Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta entrega en 1 a 2 días laborables; para una fiesta encargue al menos con una semana de antelación.'],
            ['q' => '¿Cómo clavo el topper para que aguante?', 'a' => 'Clave las púas en la tarta hasta la forma, mejor a través del bizcocho y no solo en la crema; dos púas aguantan mejor que una. Envuélvalas en film o métalas en pajitas, y ponga el topper en la tarta solo antes de servir.'],
            ['q' => '¿Cuánto cuesta el envío?', 'farm' => true, 'a' => 'Con Packeta a un punto de recogida en Chequia :cz_point, a domicilio :cz_home, a Eslovaquia desde :sk_point, a otros países de la UE desde :eu_point.'],
            ['q' => '¿Cómo imprimo el topper en casa?', 'a' => 'En plano, boca arriba, sin soportes, en PLA. El proyecto descargado lleva una pausa a la altura del nombre para cambiar la bobina; una impresora con varias bobinas lo hace sola. Con 3 mm de grosor basta para que no se doble.'],
            ['q' => '¿Qué colores elegir?', 'a' => 'Dorado o rosa con el nombre en blanco, negro con blanco, o un filamento con purpurina o Silk si lo tenemos en stock. Elige al hacer el pedido entre las bobinas de las impresoras; un nombre claro sobre una forma oscura es lo que mejor se lee en las fotos.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el topper impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Cumpleaños infantil', 'text' => 'La edad como número de 110 mm con el nombre en tipografía manuscrita, dos púas de 60 mm, por 99 CZK.'],
            ['t' => 'Boda y aniversario', 'text' => 'Un corazón de 120 mm con «Ela a Tom» o una fecha, dorado con blanco.'],
            ['t' => 'Nochevieja y fiesta de empresa', 'text' => 'Solo texto sin forma: «2027», «Gracias» o el nombre del equipo, 180 mm de ancho, púas de 80 mm.'],
        ],
    ],

    'name_letter' => [
        'order' => [
            ['q' => '¿Cuánto cuesta una letra con ustedes?', 'farm' => true, 'a' => 'Una letra E de 120 mm de alto con el nombre Ela se imprime en 40 minutos y cuesta 99 CZK, que es el pedido mínimo (PLA, sin envío, cálculo del 27 de octubre de 2026). Una letra de 200 mm cuesta más según sus gramos y minutos; ve el precio antes de encargar nada.'],
            ['q' => '¿Cómo cuelgo la letra en una puerta?', 'a' => 'Marque la argolla y cuélguela de una cinta o un gancho, o péguela con cinta de doble cara; una letra de 120 mm pesa 27 g. Para una estantería bastan 10 mm de grosor, o la peana con ranura del mismo archivo.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Una letra de 120 mm se imprime en 40 minutos; una de 200 mm, en más de una hora. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo imprimo la letra en casa en dos colores?', 'a' => 'En plano, boca arriba, sin soportes. El proyecto descargado lleva una pausa a la altura del nombre para cambiar la bobina; la peana se imprime al lado. Una impresora con AMS, MMU o ACE cambia el color sola.'],
            ['q' => '¿Qué colores le van bien?', 'a' => 'Dos contrastados: un cuerpo pastel con el nombre en blanco u oscuro para una habitación infantil, negro con dorado para una mesa. Los filamentos mate disimulan las capas mejor que los brillantes; elige entre las bobinas que tenemos en stock.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga la letra impresa; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Regalo de nacimiento y bautizo', 'text' => 'Una letra de 120 a 150 mm con el nombre del bebé para la estantería sobre la cuna; con «Ela 2026» como fecha.'],
            ['t' => 'La puerta de la habitación infantil', 'text' => 'Una letra de 150 mm con argolla en una cinta o con cinta de doble cara, en los colores de la habitación.'],
            ['t' => 'Boda: iniciales para la mesa', 'text' => 'Dos letras de 150 mm con los nombres de los novios, de 10 mm de grosor para que se sostengan solas en la mesa nupcial.'],
        ],
    ],

    // las siguientes 10 herramientas, lote A (docs/AP.md §9): soporte, placa, llavero
    'phone_stand' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un soporte con ustedes y cuánto tarda en imprimirse?', 'farm' => true, 'a' => 'La cuña baja de 80 mm se imprime en 1 h 8 min y cuesta 120 CZK; el caballete con repisa de 75 mm, en 1 h 45 min por 170 CZK (PLA+, sin envío, cálculo del 18 de octubre de 2026). El cálculo muestra el precio de su soporte antes de encargar nada.'],
            ['q' => '¿Cómo mido el móvil?', 'a' => 'Mida el grosor con la funda en el punto más grueso, incluido el anillo para el dedo si la funda lo tiene; la herramienta admite de 7 a 20 mm y añade sola la holgura. Mida el ancho por encima de la funda; en los tipos de mesa decide el ancho del respaldo.'],
            ['q' => '¿Cuánto tarda el envío?', 'farm' => true, 'a' => 'Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point, a domicilio :cz_home).'],
            ['q' => '¿Cómo imprimo el soporte en casa?', 'a' => 'El proyecto descargado para OrcaSlicer, Bambu Studio o PrusaSlicer lleva la orientación y los ajustes del cálculo; basta elegir su impresora; un STL lo orienta usted. Para la mesa basta PLA con relleno del 15 %; un filamento mate disimula las capas.'],
            ['q' => '¿Aguanta el soporte en el coche al sol?', 'a' => 'La pinza del coche y un soporte para el salpicadero imprímalos en PETG: el PLA tras el cristal se ablanda hacia los 55 °C y la pinza cede. Para la mesa o la pared basta el PLA.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el soporte impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Para el escritorio', 'text' => 'Un caballete con repisa a 65° con recorte para el cable: el móvil se carga y queda a la vista para llamadas y avisos.'],
            ['t' => 'Para recetas en la cocina', 'text' => 'Una cuña baja o un caballete ancho de hasta 260 mm para una tableta, a 60°, para leer la pantalla desde la encimera.'],
            ['t' => 'Para el coche', 'text' => 'Una pinza para la rejilla de ventilación según el grosor de la lama, de 1 a 4 mm, en PETG, con recorte para el cable.'],
        ],
    ],

    'sign' => [
        'order' => [
            ['q' => '¿Cuánto cuestan las placas con ustedes?', 'farm' => true, 'a' => 'Un llavero con nombre, 99 CZK, y diez iguales, 99 CZK en total; un letrero de puerta de 173 × 69 mm, 99 CZK (PLA+, sin envío, cálculo del 18 de octubre de 2026). Una placa mayor o más nombres se calculan por gramos y minutos; ve el precio antes de encargar nada.'],
            ['q' => '¿Qué forma y tipografía elegir para una puerta?', 'a' => 'Para la puerta de un piso o una oficina, una tipografía sin serifa o con serifa con letras de al menos 15 mm y un marco, para que la placa se lea desde el pasillo; deje las manuscritas para nombres y llaveros. Las letras en relieve en un segundo color se leen mejor que las rebajadas.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un llavero se imprime en 5 minutos; un letrero de puerta, en 46 minutos. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo imprimo la placa en casa en dos colores?', 'a' => 'En plano, sin soportes. El proyecto descargado lleva una pausa a la altura en que empiezan las letras: la impresora se detiene, cambia la bobina y la impresión sigue; una impresora con AMS, MMU o ACE cambia el color sola. En la versión de dos colores también descarga la placa y las letras por separado.'],
            ['q' => '¿Aguanta la placa en el exterior?', 'a' => 'Para un piso y puertas interiores basta el PLA. Para una puerta exterior o una valla elija ASA, que soporta sol y helada; el PETG aguanta a la sombra. El material se elige en la comparación de materiales del cálculo.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga las placas impresas; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Etiquetas para una clase o un equipo', 'text' => 'Llaveros con nombres de 46 × 22 mm: diez unidades por 99 CZK, cada nombre su propio diseño con la misma composición.'],
            ['t' => 'Letrero para la puerta del piso o la oficina', 'text' => '«Novákovi 12» con letras en relieve y marco, 173 × 69 mm, pegado con cinta de doble cara.'],
            ['t' => 'Un nombre para la habitación infantil', 'text' => 'Solo el nombre en manuscrita sin placa, con argolla para una cinta, o sin ella para la puerta.'],
        ],
    ],

    'keychain' => [
        'order' => [
            ['q' => '¿Cuánto cuestan los llaveros con ustedes?', 'farm' => true, 'a' => 'Un hueso de 70 mm con nombre en dos colores cuesta 99 CZK, que es el pedido mínimo; diez iguales, 100 CZK, es decir, 10 CZK cada uno (PLA, sin envío, cálculo del 28 de octubre de 2026). Los colores se eligen al hacer el pedido entre las bobinas que tenemos en stock.'],
            ['q' => '¿Cómo hago llaveros con distintos nombres para un equipo?', 'a' => 'El diseño se queda en la página: cambie el nombre, mantenga la forma y la imagen y continúe al cálculo con cada nombre por separado; el número de unidades de un nombre lo indica ahí. Todos los llaveros se imprimen juntos en una misma cama.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un llavero se imprime en 10 minutos. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo imprimo el llavero en casa en varios colores?', 'a' => 'En plano, sin soportes. El proyecto descargado lleva un cambio de filamento a la altura de cada capa; con una boquilla cambia la bobina en la pausa, y una impresora con AMS, MMU o ACE lo hace sola. El STL es un solo cuerpo para imprimir en un color.'],
            ['q' => '¿Sirve como regalo de empresa con logotipo?', 'a' => 'Sí. Suba el logotipo como SVG y queda una silueta de un color sobre una forma en el color de la empresa; un logotipo de varios colores lo procesa el formulario rápido. Diez llaveros salen por 100 CZK; el cálculo muestra el precio de una serie mayor.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga los llaveros impresos; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Llaveros para un equipo o una boda', 'text' => 'Una composición, distintos nombres, diez unidades por 100 CZK; para una boda, un corazón con los nombres y la fecha.'],
            ['t' => 'Etiqueta para mochila y llaves', 'text' => 'Una etiqueta de 80 mm en PETG con el nombre y el teléfono en dos líneas, argolla de 5 mm para un mosquetón.'],
            ['t' => 'Un regalo con logotipo', 'text' => 'Un logotipo de un SVG como silueta sobre un círculo en el color de la empresa, hasta cuatro colores en una impresión.'],
        ],
    ],

    // lote B (docs/AP.md §9): medalla, jarrón (la caja tiene sus bloques de AH.md §11)
    'medallion' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un juego de medallas para una carrera?', 'farm' => true, 'a' => 'Una medalla de 60 mm con cadena de 40 eslabones cuesta 130 CZK (PLA+, sin envío, cálculo del 22 de octubre de 2026). Para el 1.º al 3.º puesto hace tres diseños, cada uno con su propio cálculo, e indica ahí el número de unidades de cada puesto; ve el precio de todo el juego antes de encargar nada.'],
            ['q' => '¿Cómo cuelgo la medalla sin la cadena?', 'a' => 'Ajuste el número de eslabones a cero: la medalla tiene arriba una argolla con agujero de 6 mm por la que pasa una cinta de hasta 15 mm o un cordón. La cadena de eslabones solo se imprime si la quiere.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Una medalla de 60 mm con cadena se imprime en 1 h 42 min, así que un juego para tres puestos ocupa una impresora medio día. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point); para una carrera encargue al menos con una semana de antelación.'],
            ['q' => '¿Cómo imprimo la medalla en casa?', 'a' => 'El motivo a ras alterna dos bobinas en las mismas capas, y eso requiere una impresora con cambiador (AMS, MMU, ACE). Sin cambiador elija el motivo en relieve: el proyecto lleva un cambio de filamento a la altura del motivo. La cadena se imprime en plano sin soportes; en PLA Silk parece metálica.'],
            ['q' => '¿Puedo poner el logotipo del club o un nombre en la medalla?', 'a' => 'Sí. En lugar del número escribe un nombre en dos líneas de 16 caracteres, o sube una imagen, de la que queda una silueta de un color; la forma de la placa se elige entre siete. Un logotipo con detalles finos agrándelo con el motivo al 60 o 70 % del diámetro.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga las medallas impresas; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Carrera infantil y torneo', 'text' => 'Un juego para el 1.º al 3.º puesto con cadenas de 28 a 32 eslabones para niños, cada medalla 130 CZK.'],
            ['t' => 'Concurso escolar y día del niño', 'text' => 'Una medalla de 60 mm con nombre o imagen en una cinta, sin cadena, para cada participante.'],
            ['t' => 'Torneo de empresa', 'text' => 'Un escudo o una roseta de 80 mm con el logotipo de la empresa y cadena de 40 eslabones, oro PLA Silk.'],
        ],
    ],

    'vase' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un jarrón con ustedes?', 'farm' => true, 'a' => 'Un jarrón liso de 160 mm, 190 CZK; uno en espiral de 180 mm, 280 CZK; una funda de maceta acanalada de 120 mm, 260 CZK (PLA+, sin envío, cálculo del 18 de octubre de 2026). El cálculo muestra el precio de su jarrón antes de encargar nada; deciden los gramos y los minutos, en las espirales sobre todo los minutos.'],
            ['q' => '¿Qué filamento para un jarrón?', 'a' => 'Para jarrones en espiral y acanalados, PLA Silk brillante, en el que los nervios juegan con la luz; para los lisos, un filamento mate que disimule las capas. La funda de maceta, que se moja, elíjala en PETG. El color se elige entre las bobinas que tenemos en stock ahora mismo.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un jarrón liso de 160 mm se imprime en menos de 2 horas; uno en espiral de 180 mm, en 5 horas. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo imprimo el jarrón en casa?', 'a' => 'De pie, sin soportes; el proyecto descargado lleva la pared y el fondo según el diseño. El STL es un cuerpo macizo, así que puede pasar el laminador a modo jarrón con una sola pared, que imprime más rápido y silencioso pero retiene peor el agua. Los jarrones altos imprímalos con borde de adherencia para que no se despeguen de la cama.'],
            ['q' => '¿Cómo mido una maceta para la funda?', 'a' => 'Mida el diámetro exterior de la maceta en el borde superior y su altura, añada de 5 a 10 mm de holgura al diámetro e indíquelo como diámetro superior de la funda; el diámetro inferior, algo menor según la forma de la maceta. El plato se calcula solo.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el jarrón impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Flores secas para la mesa', 'text' => 'Un jarrón en espiral de 180 mm con cuello estrecho en PLA Silk; para flores secas no necesita retener agua.'],
            ['t' => 'Funda de maceta para el piso', 'text' => 'Una funda acanalada de 120 mm con drenaje y plato en PETG, el diámetro de la maceta más 5 mm.'],
            ['t' => 'Un juego de tres jarrones', 'text' => 'Jarrones lisos de 120, 160 y 200 mm con la misma forma y un solo color para una estantería o como regalo.'],
        ],
    ],

    // lote C (docs/AP.md §9): logotipo, soporte de pared, sello, cartel luminoso
    'logo' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un logotipo con ustedes?', 'farm' => true, 'a' => 'Un relieve sobre placa de 100 mm de ancho cuesta 99 CZK, que es el pedido mínimo (PLA, sin envío, cálculo del 28 de octubre de 2026). Un cartel mayor o un logotipo de pie se calcula por gramos y minutos; ve el precio antes de encargar nada, también para varias unidades.'],
            ['q' => '¿Cómo preparo el SVG del logotipo?', 'a' => 'En un solo color, con rellenos en lugar de trazos (Inkscape: Stroke to Path) y el texto convertido en trazados; degradados y máscaras no se leen. Un logotipo con texto pequeño agrándelo al menos a 100 mm para que ninguna línea baje de 0,8 mm.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un relieve de 100 mm se imprime en 43 minutos; un cartel de 250 mm, en más de dos horas. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo imprimo el logotipo en casa en dos colores?', 'a' => 'En plano, sin soportes. En el relieve sobre placa el proyecto descargado lleva una pausa a la altura en que empieza el motivo: la impresora se detiene, cambia la bobina y la impresión sigue; una impresora con AMS, MMU o ACE lo hace sola. El logotipo de pie y su peana se imprimen como dos piezas.'],
            ['q' => '¿Aguanta el logotipo en el exterior, en una fachada?', 'a' => 'En el exterior, con sol y heladas, elija ASA; en interiores basta el PLA; a la sombra, PETG. Un logotipo de más de 250 mm no cabe en la cama; divídalo en piezas o deje que la herramienta Dividir el modelo lo corte con pasadores.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el logotipo impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Cartel para la puerta de la oficina', 'text' => 'Un relieve del logotipo o del nombre de la empresa, de 120 a 150 mm, en dos colores, pegado con cinta de doble cara.'],
            ['t' => 'Logotipo para la recepción o el mostrador', 'text' => 'Un logotipo de pie de 150 a 200 mm en una peana con ranura, de al menos 2,4 mm de grosor, impreso en dos piezas.'],
            ['t' => 'Detalles con logotipo para el equipo', 'text' => 'Un logotipo recortado de 60 mm como imán o colgante; para llaveros con nombres está la herramienta Llavero.'],
        ],
    ],

    'holder' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un soporte con ustedes?', 'farm' => true, 'a' => 'Una cuna para un mando, 100 CZK; un gancho para auriculares, 99 CZK; una pinza para una escoba, 99 CZK (PLA+, sin envío, cálculo del 18 de octubre de 2026); 99 CZK es el pedido mínimo. Varios soportes en un pedido salen más baratos por unidad; ve el precio antes de encargar nada.'],
            ['q' => '¿Qué tipo de soporte para cada cosa?', 'a' => 'La cuna para un mando, un móvil o unas gafas; el bolsillo para una botella, herramientas y bolígrafos; el gancho para auriculares, un bolso o llaves con asa; la pinza para el mango de una escoba, una fregona o un rastrillo. Los ajustes predefinidos ponen las medidas de objetos corrientes; sus propias medidas los sustituyen.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'La pinza se imprime en 21 minutos; el gancho, en 27 minutos; la cuna, en 1 h 12 min. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo fijo el soporte sin taladrar?', 'a' => 'Desactive los agujeros y pegue el soporte con cinta de doble cara; solo aguanta cosas ligeras como un mando o unos auriculares. Donde ya hay un tornillo en la pared, elija colgar de un tornillo: la cabeza pasa por el agujero ancho y el soporte baja hasta la ranura.'],
            ['q' => '¿Cómo imprimo el soporte en casa?', 'a' => 'En plano con la parte trasera sobre la cama, sin soportes; el proyecto descargado lleva los ajustes del cálculo. Para cosas pesadas, suba el número de paredes o el relleno en su laminador e imprima en PETG.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el soporte impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Mandos junto al sofá', 'text' => 'Una cuna de 50 × 22 mm en el lateral de una estantería o una mesita, pegada con cinta; una por cada mando.'],
            ['t' => 'Herramientas en el taller y el garaje', 'text' => 'Pinzas para mangos y bolsillos para martillos en PETG con tornillos, con las medidas de cada pieza.'],
            ['t' => 'Auriculares en el escritorio', 'text' => 'Un gancho de 35 mm bajo el tablero o en el lateral del monitor; los auriculares no estorban en la mesa.'],
        ],
    ],

    'stamp' => [
        'order' => [
            ['q' => '¿Cuánto cuesta un sello con ustedes?', 'farm' => true, 'a' => 'Un sello de 50 mm con mango cuesta 99 CZK, que es el pedido mínimo (PLA, sin envío, cálculo del 28 de octubre de 2026). Un sello mayor o más unidades se calculan por gramos y minutos; ve el precio antes de encargar nada.'],
            ['q' => '¿Cómo hago una marca de autor para la base de las tazas?', 'a' => 'Suba el logotipo como SVG o escriba sus iniciales, elija motivo en relieve, un ancho de 20 a 40 mm y una altura de motivo de 1 a 1,5 mm; la impronta en arcilla en dureza de cuero queda limpia. Con mango el sello se sujeta mejor; sin él, lo presiona con el pulgar.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Un sello de 50 mm se imprime en 28 minutos. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Sirve el sello para jabón y masa?', 'a' => 'Sí, para todo lo blando que conserve la impronta: jabón, fondant, masa quebrada, arcilla polimérica. Para un logotipo en relieve sobre jabón elija el motivo rebajado. El plástico impreso está pensado para un contacto breve con alimentos; lave el sello a mano, no en el lavavajillas.'],
            ['q' => '¿Qué tamaño de sello elegir?', 'a' => 'Marca de autor, de 20 a 40 mm; sello decorativo para cerámica, de 50 a 80 mm; como máximo 120 mm. Cuanto mayor es el sello, más presión necesita; un motivo grande mejor imprímalo con un rodillo sobre una placa sin mango.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga el sello impreso; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Taller de cerámica', 'text' => 'Una marca de autor de 30 mm para la base de tazas y cuencos, motivo en relieve, con mango.'],
            ['t' => 'Fabricación de jabón', 'text' => 'Un motivo rebajado de 50 mm que deja un logotipo en relieve sobre el jabón; un sello en PETG soporta la humedad.'],
            ['t' => 'Repostería y fondant', 'text' => 'Un sello «Hand made» o un nombre para masa y fondant, en PLA, lavado a mano.'],
        ],
    ],

    'lightbox' => [
        'order' => [
            ['q' => '¿Cuánto cuestan las piezas con ustedes?', 'farm' => true, 'a' => 'El cartel OPEN de 180 mm, cuatro piezas, cuesta 180 CZK (PLA, sin envío, cálculo del 28 de octubre de 2026). Los colores de las piezas se eligen al encargar, en un solo pedido, cuerpo oscuro y difusor blanco; ve el precio antes de encargar nada.'],
            ['q' => '¿Qué compro para el cartel?', 'a' => 'Una tira LED de 5 V de 8 o 10 mm de ancho con la longitud que la herramienta indica bajo la vista previa, un cable USB con alimentación (basta un cargador de móvil) y cinta de doble cara o pegamento. Una tira con mando elíjala de hasta 18 × 8 mm.'],
            ['q' => '¿Cuánto tardan la impresión y el envío?', 'farm' => true, 'a' => 'Las cuatro piezas de un cartel de 180 mm se imprimen en 2 h 17 min. Enviamos el mismo día en que termina la impresión, en días laborables, y Packeta suele entregar en 1 a 2 días laborables en Chequia (a un punto de recogida :cz_point).'],
            ['q' => '¿Cómo imprimo el cartel en casa?', 'a' => 'Las piezas se imprimen en plano; el proyecto descargado lleva los ajustes del cálculo. El cuerpo, la máscara y la tapa trasera en PLA o PETG oscuro; el difusor en PLA blanco o translúcido; la máscara con puentes dentro de las letras imprímala más despacio para que los puentes aguanten.'],
            ['q' => '¿Cuánto brillará el cartel?', 'a' => 'El brillo lo da la tira, no las piezas; el difusor reparte la luz para que no se vean los LED sueltos. Una tira de 5 V consume unos pocos vatios, así que basta un cargador USB y el cartel puede estar encendido toda la tarde.'],
            ['q' => '¿Necesito una cuenta?', 'a' => 'Para el diseño y la descarga, no. Solo la necesita cuando nos encarga las piezas impresas; se paga con crédito recargado con tarjeta.'],
        ],
        'who' => [
            ['t' => 'Cafetería y tienda', 'text' => 'OPEN o el nombre del negocio, 240 mm, para el escaparate, rectángulo con tira LED, en PETG para el sol.'],
            ['t' => 'Bar en casa y sala de juegos', 'text' => 'Un cartel redondo de 160 mm con base plana para una estantería, una palabra o un nombre.'],
            ['t' => 'Luz nocturna con nombre', 'text' => 'El nombre de un niño, 120 mm, con módulo LED y mando, luz suave para la habitación.'],
        ],
    ],
];
