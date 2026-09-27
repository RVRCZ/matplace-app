<?php

/*
 * Material catalogue used by the rough estimate, the price engine and the UI.
 * lay: how a non-technical user chooses ("pevné / lehké / venku / do ruky").
 * Densities in g/cm³. slicer_profile → config/engines.php orca.filaments key.
 * props: what the comparison table says about a material. heat = °C at which a part starts to soften; strength 1–3;
 *        outdoor yes | limited | no; food liner (only with a liner or a coating) | no; time and price are factors
 *        against PLA (slower printing, dearer filament), used to convert one calculation to the other materials.
 */
return [
    'default' => 'PLA',
    'items' => [
        'PLA' => ['density' => 1.24, 'lay' => ['home', 'decor', 'hand'], 'technology' => 'fdm', 'sort' => 10,
            'props' => ['heat' => 55, 'strength' => 2, 'flexible' => false, 'outdoor' => 'no', 'food' => 'liner', 'time' => 1.0, 'price' => 1.0]],
        'PETG' => ['density' => 1.27, 'lay' => ['strong', 'home', 'outdoor_light'], 'technology' => 'fdm', 'sort' => 20,
            'props' => ['heat' => 75, 'strength' => 3, 'flexible' => false, 'outdoor' => 'limited', 'food' => 'liner', 'time' => 1.1, 'price' => 1.05]],
        'ASA' => ['density' => 1.07, 'lay' => ['outdoor', 'strong'], 'technology' => 'fdm', 'sort' => 30,
            'props' => ['heat' => 95, 'strength' => 3, 'flexible' => false, 'outdoor' => 'yes', 'food' => 'no', 'time' => 1.15, 'price' => 1.3]],
        'TPU' => ['density' => 1.21, 'lay' => ['flexible'], 'technology' => 'fdm', 'sort' => 40,
            'props' => ['heat' => 80, 'strength' => 2, 'flexible' => true, 'outdoor' => 'limited', 'food' => 'no', 'time' => 1.6, 'price' => 1.5]],
        'PA' => ['density' => 1.14, 'lay' => ['strong', 'technical'], 'technology' => 'fdm', 'sort' => 50, 'slice' => false,
            'props' => ['heat' => 110, 'strength' => 3, 'flexible' => false, 'outdoor' => 'limited', 'food' => 'no', 'time' => 1.25, 'price' => 2.0]],
        'RESIN' => ['density' => 1.10, 'lay' => ['detail'], 'technology' => 'resin', 'sort' => 60, 'slice' => false],
    ],
];
