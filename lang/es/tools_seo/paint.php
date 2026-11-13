<?php

return [
    'title' => 'Colorear un modelo 3D online: impresión multicolor',
    'description' => 'Suba un STL o un 3MF y diga en el navegador qué parte lleva qué color: rellene una superficie, coloree por altura o pinte con el pincel. Sin laminador.',
    'h1' => 'Coloreado de un modelo 3D para impresión multicolor',
    'intro' => [
        'La herramienta colorea el modelo subido directamente en el navegador. Un toque rellena toda una superficie unida hasta una arista, o una pieza entera; el pincel añade los detalles; la altura colorea franjas horizontales. Los colores se eligen entre los filamentos con los que imprimimos, y la herramienta lleva la cuenta de cuántos colores admite una impresión.',
        'El resultado es un 3MF con los colores pintados sobre el modelo, del mismo tipo que guardan Bambu Studio, OrcaSlicer o Anycubic Slicer Next. El cálculo muestra enseguida el precio en un color y en colores, y usted encarga la impresión o descarga el archivo para su propia impresora con cambiador de filamento. La forma del modelo no cambia; el pincel solo refina la malla donde se pinta.',
    ],
    'steps' => [
        ['name' => 'Suba el modelo', 'text' => 'STL, 3MF, OBJ o STEP. El modelo aparece en la vista previa; un 3MF que ya tiene colores se abre con ellos.'],
        ['name' => 'Elija los colores', 'text' => 'El color del modelo y más colores de los filamentos disponibles. La herramienta indica cuántos puede añadir todavía.'],
        ['name' => 'Coloree', 'text' => 'Rellenar: un toque colorea una superficie hasta una arista. Pincel: arrastre sobre la superficie. Por altura: otro color desde la altura elegida.'],
        ['name' => 'Vea el precio o descargue', 'text' => 'El cálculo muestra el precio en un color y en colores. Puede descargar el 3MF en color para su propio laminador.'],
    ],
    'faq' => [
        ['q' => '¿Qué impresora imprime un modelo coloreado?', 'a' => 'Una impresora que cambia de filamento durante la impresión: con una unidad AMS, MMU o ACE, o con varios cabezales. Nosotros imprimimos los modelos en color en máquinas así, de una vez y en una sola base. Los colores uno sobre otro también salen en una impresora de una boquilla si cambia la bobina a mano en cada cambio.'],
        ['q' => '¿Cuántos colores puede tener un modelo?', 'a' => 'Tantos como bobinas lleva la impresora con cambiador; la herramienta muestra el número y no deja añadir más. Tenga en cuenta que cada color adicional en la misma capa supone un cambio de filamento y alarga la impresión.'],
        ['q' => '¿Por qué la impresión multicolor cuesta más que la de un color?', 'a' => 'En cada cambio de color la impresora expulsa el filamento anterior en una torre de purga y carga el nuevo. Tarda unos dos minutos y gasta algo de plástico. Un modelo con colores uno junto a otro en toda su altura tiene ese cambio en cada capa; uno con colores superpuestos, solo uno por cambio.'],
        ['q' => '¿Qué finura pueden tener los detalles pintados?', 'a' => 'La boquilla mide 0,4 mm y no imprime una línea más fina. Por eso el pincel dibuja su borde con una precisión de unos 0,4 mm, y las motas de menos de medio milímetro cuadrado se unen al color de alrededor al guardar; la herramienta le dice cuántas eran.'],
        ['q' => '¿El coloreado cambia la forma del modelo?', 'a' => 'No. El relleno y los colores por altura solo asignan un color a las caras del modelo; el pincel además divide las caras bajo el trazo en otras más pequeñas, para que el borde del color pase por donde arrastró. La superficie y las medidas siguen iguales.'],
        ['q' => '¿Admite modelos grandes y el teléfono?', 'a' => 'El relleno y los colores por altura funcionan hasta un millón de triángulos en un ordenador y medio millón en un teléfono. El pincel refina la malla, por eso funciona hasta medio millón de triángulos en un ordenador y doscientos mil en un teléfono; con modelos mayores la herramienta desactiva el pincel y lo indica.'],
    ],
    'examples' => [],
];
