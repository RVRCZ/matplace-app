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
        // the default models of the tools, priced on matplace.com on 18 Oct 2026 (docs/AH.md §11)
        ['tool' => 'qr', 'mm' => 70, 'size' => '70 × 83 × 3 mm', 'material' => 'PLA+', 'grams' => 14, 'minutes' => 25, 'price' => ['CZK' => 99], 'calc' => 'lomf0wzlshw4', 'photo' => null],
        ['tool' => 'organizer', 'mm' => 200, 'size' => '200 × 120 × 40 mm', 'material' => 'PLA+', 'grams' => 115, 'minutes' => 147, 'price' => ['CZK' => 250], 'calc' => 'l1gr0izblzwr', 'photo' => null],
        ['tool' => 'box', 'mm' => 84, 'size' => '84 × 54 × 32 mm', 'material' => 'PLA+', 'grams' => 28, 'minutes' => 34, 'price' => ['CZK' => 99], 'calc' => 'nku29wz4vieb', 'photo' => null],
        // the search entry pages of session N1 (docs/AP.md §2): the cat of the SVG to STL page, priced on matplace.com on 27 Oct 2026; the photo comes from session N3
        ['tool' => 'svg_to_stl', 'mm' => 80, 'size' => '80 × 81 × 6 mm', 'material' => 'PLA', 'grams' => 9.6, 'minutes' => 17, 'price' => ['CZK' => 99], 'calc' => 'lfa9iggn3hls', 'photo' => null],
    ],
];
