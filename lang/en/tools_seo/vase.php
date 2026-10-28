<?php

// The page for the query "3d printed vase generator" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed vase generator: spiral, ribbed, pot cover, free',
    'heading' => '3D printed vase to your own size',
    'description' => 'Choose the height, the diameters, the shape and the surface: smooth, ribbed or spiral; a pot cover gets drainage and a saucer. Free model, or we print it.',
    'h1' => 'Which shapes the vase takes, what a print costs and whether it holds water',
    'intro' => [
        'The tool makes a vase or a plant pot cover from the height and the diameters you enter: height 40 to 300 mm, top and bottom diameter 30 to 250 mm. You pick one of four shapes (a narrow neck, bulging, a straight cone or a tulip) and a smooth, ribbed or spiral surface; for the ribbed and the spiral ones you set the number of ribs, the depth of the grooves and the twist, and choose sharp or round waves. On the narrow-neck shape you move the narrowing up or down and can have up to three of them. A pot cover can have drainage holes in the bottom and a separate saucer. The wall and the bottom are 1.6 mm by default (wall 0.8 to 4 mm, bottom 0.8 to 5 mm). The preview shows the real outer size, because bulging shapes and ribs are wider than the diameters you enter.',
        'An example from the production calculator (18 October 2026, PLA+): a smooth narrow-neck vase 160 mm high weighs 76 g, prints in 1 h 57 min and costs 190 CZK (about 8 EUR) from us; a 180 mm spiral vase with 18 ribs weighs 80 g, prints in 5 h 7 min and costs 280 CZK (about 11 EUR); a ribbed 120 mm pot cover with drainage weighs 99 g, prints in 3 h 45 min and costs 260 CZK without the saucer, all without delivery. The cover with its saucer side by side measures 287 mm, so the calculation prints it on two beds or you make it smaller. A printed vase need not be watertight; for cut flowers put a glass inside. You download the model free as an STL or a project for your printer.',
    ],
    'steps' => [
        ['name' => 'Choose the purpose and the shape', 'text' => 'Pick a vase or a pot cover and one of four shapes. You can start from the preset "Spiral", "Ribbed", "Smooth" or "Plant pot".'],
        ['name' => 'Enter the size', 'text' => 'Set the height, the top diameter and the bottom diameter. For a cover measure the pot you will put in it and add some play.'],
        ['name' => 'Tune the surface', 'text' => 'For a ribbed or spiral surface set the number of ribs, the depth of the grooves and the twist. The tool warns when the wall between the grooves would come out too thin.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview and go on to the exact price with "Continue". Or download the model free for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Does a printed vase hold water?', 'a' => 'Not necessarily. Microscopic leaks can remain between the layers. For cut flowers put a glass or a test tube inside the vase, or seal the inside with varnish; for dried flowers and as a pot cover you need not bother.'],
        ['q' => 'How big a vase can I design?', 'a' => 'The height from 40 to 300 mm, the top and bottom diameter from 30 to 250 mm. A tall or wide vase does not fit every printer; the bed of our farm is 250 × 250 mm and the calculation says so.'],
        ['q' => 'Why does a spiral vase cost more than a smooth one?', 'a' => 'Ribs and twist add surface and the printer follows a winding path: the 180 mm spiral vase printed in 5 h 7 min, the smooth 160 mm one in 1 h 57 min at a similar weight. The price comes from grams and minutes, hence the 90 CZK difference.'],
        ['q' => 'What are the drainage holes and the saucer for?', 'a' => 'With a pot cover, excess water drains through the holes in the bottom and the saucer catches it. The saucer is a separate part and the tool computes its diameter from the bottom.'],
        ['q' => 'Why is the vase wider than the diameters I entered?', 'a' => 'The diameters apply to the top rim and the bottom. The narrow-neck and the bulging shape widen between them and the ribs add more millimetres. The preview shows the real outer size.'],
        ['q' => 'How thick is the wall, and can it print in vase mode?', 'a' => 'The wall and the bottom are 1.6 mm by default; the wall goes from 0.8 to 4 mm, the bottom from 0.8 to 5 mm. The downloaded STL is a solid body, so in your slicer you can print it in vase mode as a single wall too; we print the wall as designed with infill, which holds water better.'],
    ],
    'examples' => [
        'A spiral narrow-neck vase, 180 mm high, top diameter 62 mm, 18 ribs: 80 g, 5 h 7 min, 280 CZK.',
        'A smooth narrow-neck vase, 160 mm high, top diameter 70 mm: 76 g, 1 h 57 min, 190 CZK.',
        'A ribbed pot cover with a saucer, 120 mm high, top diameter 130 mm: 99 g without the saucer, 3 h 45 min, 260 CZK.',
        'A vase with two narrowings and round waves, 200 mm high, top diameter 70 mm.',
    ],
];
