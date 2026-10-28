<?php

// The page for the query "cable holder generator 3d print" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Cable holder generator for 3D printing: desk clip, free STL',
    'heading' => 'Cable holder generator for 3D printing: a desk clip made to your cables',
    'description' => 'Enter the cable diameter and the number of slots and get a holder your cables click into and stop falling behind the desk. Print, or download free.',
    'h1' => 'How the holder is made, how big it comes out and what it costs',
    'intro' => [
        'A cable holder is a small block with slots that you stick with double-sided tape or screw onto the edge of a desk. The cable clicks into a slot from above and stays in place, even when unplugged from the device; the slit above the slot is slightly narrower than the cable, so it does not fall out, and the hole is 0.6 mm larger, so the cable slides freely. Enter 1 to 8 cables and a cable diameter of 3 to 14 mm; all slots of one holder have the same diameter. Set the depth from 10 to 80 mm, the wall between the slots and the rounding of the corners too; with "With screw tabs" it gets two tabs with holes at the sides. It suits chargers, monitor and headphone cables.',
        'An example from the production calculator (28 October 2026, PLA): a holder for four 6 mm cables, 61 mm long and 45 mm deep, weighs 17.1 g, prints in 42 minutes and costs 99 CZK (about 4 EUR) from us, the lowest order price, without delivery (calculation /c/mjyodedmxzye); a holder for six thin 4 mm cables with screw tabs weighs 24.6 g and prints in 56 minutes (calculation /c/kop5zm2dhbtw). For every desk in the office enter the quantity and the price per piece falls. The model downloads free as an STL or a project for your printer; it prints without supports. Tape and screws are not part of the print.',
    ],
    'steps' => [
        ['name' => 'Measure the cable diameter', 'text' => 'Measure the diameter of the cable, not of the plug. You can enter a diameter from 3 to 14 mm in half-millimetre steps.'],
        ['name' => 'Choose the number of cables', 'text' => 'The holder can have 1 to 8 slots. All slots have the same diameter; for cables of different thickness design several holders.'],
        ['name' => 'Choose the fixing', 'text' => 'Without tabs the holder sticks with double-sided tape. With the option "With screw tabs" it gets two tabs with holes at the sides.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview with the size and press "Continue" for the precise price. Or download the model free for your own printer.'],
    ],
    'faq' => [
        ['q' => 'How thick can the cables be?', 'a' => 'Cables from 3 to 14 mm in diameter: from a thin headphone cable to a monitor power cable. The hole is 0.6 mm larger than the cable so that the cable slides freely, and the slit above it is narrower so that it does not fall out.'],
        ['q' => 'Can one holder take cables of different thickness?', 'a' => 'No, all slots of one holder have the same diameter. For cables of different thickness design several holders; two holders side by side come cheaper per piece when you enter them as several pieces.'],
        ['q' => 'How is the holder fixed, and does it hold?', 'a' => 'With double-sided tape on the edge or the underside of the desk top, or with two screws when you switch on the screw tabs. Foam tape holds on a lacquered or laminated top; on bare wood screws are better. Tape and screws are not part of the print.'],
        ['q' => 'How big will the holder be?', 'a' => 'The length depends on the number and diameter of the cables and on the wall thickness; the preview shows the exact outer size. A holder for four 6 mm cables is about 61 mm long and 45 mm deep, one for six 4 mm cables about 105 mm.'],
        ['q' => 'Does the plug have to pass through the slot?', 'a' => 'No. The cable clicks into the slot from above, so the plug is not threaded through anything; it comes out just as easily.'],
        ['q' => 'Which material, and how do I print it at home?', 'a' => 'PLA for the office and home, PETG where it is warm or the cables come in and out often and the slit has to spring. It prints slots up, without supports, 0.2 mm layers; the slicer project is ready.'],
    ],
    'examples' => [
        'Holder for four cables 6 mm in diameter, 61 mm long, fixed with tape: 17.1 g, 42 minutes, 99 CZK.',
        'Holder for two thick cables 10 mm in diameter, 45 mm long.',
        'Holder for six thin 4 mm cables with screw tabs, 105 mm long: 24.6 g, 56 minutes.',
    ],
];
