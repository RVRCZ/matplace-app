<?php

// The page for the query "phone stand 3d print custom" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom phone stand to 3D print: six types, free STL',
    'heading' => 'Custom phone stand to 3D print',
    'description' => 'Pick one of six types, enter the thickness of your phone with its case and the angle, and see the preview at once. Free download, or we print it.',
    'h1' => 'Which stands the tool makes, how to measure the phone and what a print costs',
    'intro' => [
        'The tool offers six kinds of holder: a plate with hooks, a wave, an A-frame with a shelf, a low wedge, a wall pocket and a car clip for the air vent; plus variants with a shape cut out of the backrest, a corrugated sheet, a flat plate with a slide-in foot, a Z ribbon and an animal silhouette with a saddle. The stand is computed from the thickness of your phone with its case, which you enter from 7 to 20 mm, and its width; for desk types you set the angle and the backrest height up to 200 mm and the width up to 260 mm, so the wedge and the wide types serve a tablet too. Depending on the type you add a cut-out for the charging cable, a lightening window in the backrest or screw holes. The angle is part of the design and cannot be changed after printing; the stand only holds the phone, there is no charger in it.',
        'An example from the production calculator (18 October 2026, PLA+): a low wedge 80 mm wide at 60° weighs 39 g, prints in 1 h 8 min and costs 120 CZK (about 5 EUR) from us; an A-frame with a shelf 75 mm wide at 65° weighs 65 g, prints in 1 h 45 min and costs 170 CZK (about 7 EUR), both without delivery. For a desk or a wall PLA is enough, print the car clip from PETG, which takes the heat behind glass better. You download the model free as an STL or a project for your printer (about 270 printers to choose from), or have it printed by us; after the medal, the phone stand is our most often ordered tool.',
    ],
    'steps' => [
        ['name' => 'Pick the kind of holder', 'text' => 'For a desk the plate with hooks, the wave, the A-frame with a shelf or the low wedge. The pocket goes on a wall and the clip on the air vent of a car.'],
        ['name' => 'Measure the phone with its case', 'text' => 'Enter the thickness of the phone with its case, 7 to 20 mm, and its width. The tool adds a little play by itself.'],
        ['name' => 'Adjust the angle and the extras', 'text' => 'Set the angle and the height of the backrest. Depending on the type add a cut-out for the charging cable, a lightening window in the backrest or screw mounting.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview and go on to the exact price with "Continue". Or download the model free for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Which angle should I choose?', 'a' => '60° for watching video on a desk (the low wedge), 65 to 70° for typing and face unlock (the A-frame or the wave), less on a bedside table so that the screen is not seen from the bed. The angle is fixed in the model and does not change after printing; if unsure, print two.'],
        ['q' => 'Does a phone with a case fit the stand?', 'a' => 'Yes, if you enter the thickness of the phone with its case. The tool works with 7 to 20 mm and adds a little play by itself; too much play would let the phone wobble.'],
        ['q' => 'Can I charge the phone in the stand?', 'a' => 'Yes. The wave, the A-frame, the wedge, the wall pocket and the car clip can have a cut-out for the charging cable and have it by default. The plate with hooks has none, the phone stands on two hooks in it.'],
        ['q' => 'Does the stand suit a tablet too?', 'a' => 'The width goes up to 260 mm and the backrest up to 200 mm, so the wedge and the wide desk types serve a tablet. A device with its case may be 20 mm thick at most.'],
        ['q' => 'How are the wall pocket and the car clip fixed?', 'a' => 'The pocket is stuck on with double-sided tape, or screwed on with four screws once you switch the screw option on. The clip slides onto a vent slat whose thickness you enter from 1 to 4 mm. Neither tape nor screws are part of the print.'],
        ['q' => 'What material should I print the stand from?', 'a' => 'For a desk or a wall PLA is enough; a matte filament hides the layers. Print the car clip and a dashboard stand from PETG, because PLA behind glass in the sun softens at around 55 °C.'],
    ],
    'examples' => [
        'A desk A-frame with a shelf, 75 mm wide, backrest at 65°, cable cut-out: 65 g, 1 h 45 min, 170 CZK.',
        'A low wedge for watching video, 80 mm wide, 70 mm deep, 60°: 39 g, 1 h 8 min, 120 CZK.',
        'A wall pocket for a phone 80 mm wide, four screw holes.',
        'A desk A-frame with a cat cut out of the backrest, 80 mm wide; a shape from the library or your own.',
        'A corrugated sheet in an S, 70 mm wide, 70°, ribs every 6 mm.',
        'A flat plate with hearts and a slide-in foot, 70 mm wide, two parts printed flat.',
        'A Z ribbon, a 10 mm band with a stripe in a second colour, 70 mm wide.',
        'A dog silhouette from the library, 100 mm high, a saddle for the phone in front.',
    ],
];
