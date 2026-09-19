<?php

/*
 * Material catalogue used by the rough estimate, the price engine and the UI.
 * lay: how a non-technical user chooses ("pevné / lehké / venku / do ruky").
 * Densities in g/cm³. slicer_profile → config/engines.php orca.filaments key.
 */
return [
    'default' => 'PLA',
    'items' => [
        'PLA' => ['density' => 1.24, 'lay' => ['home', 'decor', 'hand'], 'technology' => 'fdm', 'sort' => 10],
        'PETG' => ['density' => 1.27, 'lay' => ['strong', 'home', 'outdoor_light'], 'technology' => 'fdm', 'sort' => 20],
        'ASA' => ['density' => 1.07, 'lay' => ['outdoor', 'strong'], 'technology' => 'fdm', 'sort' => 30],
        'TPU' => ['density' => 1.21, 'lay' => ['flexible'], 'technology' => 'fdm', 'sort' => 40],
        'PA' => ['density' => 1.14, 'lay' => ['strong', 'technical'], 'technology' => 'fdm', 'sort' => 50, 'slice' => false],
        'RESIN' => ['density' => 1.10, 'lay' => ['detail'], 'technology' => 'resin', 'sort' => 60, 'slice' => false],
    ],
];
