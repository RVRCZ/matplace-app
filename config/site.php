<?php

/*
 * Parts of the home page that wait for something. `examples`: prints with their price (the customer audit, bod 6);
 * the numbers are real calculations of the farm on production (docs/AG.md, 17 Oct 2026), the pictures are previews
 * until Roman's photos of the prints come (then `photo` per item, a path under public/). Off until then.
 */
return [
    'examples' => (bool) env('SITE_EXAMPLES', false),
    'example_items' => [
        ['tool' => 'figure', 'mm' => 80, 'material' => 'PLA+', 'grams' => 14, 'minutes' => 59, 'price' => ['CZK' => 99], 'calc' => 'vxauvlvidinz', 'photo' => null],
        ['tool' => 'figure', 'mm' => 120, 'material' => 'PLA+', 'grams' => 40, 'minutes' => 107, 'price' => ['CZK' => 130], 'calc' => 'gnqnqzws7ahv', 'photo' => null],
        ['tool' => 'figure', 'mm' => 150, 'material' => 'PLA+', 'grams' => 72, 'minutes' => 158, 'price' => ['CZK' => 200], 'calc' => 'ccwijw6xwnku', 'photo' => null],
    ],
];
