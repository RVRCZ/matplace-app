<?php

// The page for the query "threaded cap generator 3d print" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Threaded cap and lid generator for 3D printing, free STL',
    'heading' => 'Threaded cap, lid or plug generator for 3D printing, made to measure',
    'description' => 'Measure the neck, opening or thread and the tool designs a cap, lid or plug that pushes on, plugs in or screws on. Print from us, or download the STL free.',
    'h1' => 'How the missing cap is made, what it fits and what it costs',
    'intro' => [
        'The tool builds a missing lid, plug or cover from what you measure. The part pushes over a rim, plugs into an opening or screws onto a thread; the shape is round, rectangular or hexagonal, the diameter or width 5 to 200 mm, the height 4 to 60 mm. For a thread you pick the 28 mm PET bottle, a coarse metric thread from M6 to M30, or enter your own diameter and a pitch of 0.5 to 6 mm; the result is always a cap or a nut for an outer thread, the tool does not make the bolt. The tool adds the clearance, so you enter the real size. For liquids add a sealing lip into the mouth or a bed for a liner, a knurled rim round the outside and a rounded top edge. The preview shows the part from outside and in a cut view and states which size it fits.',
        'An example from the production calculator (28 October 2026, PLA): a screw cap for a 28 mm PET bottle with a sealing lip, thread 12 mm high, weighs 5.7 g, prints in 22 minutes and costs 99 CZK (about 4 EUR) from us, the lowest order price, without delivery (calculation /c/5omcbkke1541). An end cap for a rectangular 36 × 16 mm profile, plugged in 15 mm, weighs 4.7 g and prints in 15 minutes (calculation /c/ldniassche8t); a push-on lid for a jar with a 70 mm neck weighs 17.8 g, prints in 39 minutes and costs 99 CZK (calculation /c/hgabcfckkauy). For several pieces the price per piece falls, the setup is paid once. The model downloads free as an STL or a project for your printer; it prints without supports.',
    ],
    'steps' => [
        ['name' => 'Choose how it holds', 'text' => 'Pushed over the rim, plugged into the opening or screwed onto a thread. You can start from a preset such as "PET bottle (28 mm)", "Pipe plug" or "Profile end cap".'],
        ['name' => 'Enter what you measured', 'text' => 'For a push-on lid measure the outside of the neck, for a plug the inside of the opening, for a thread the diameter over the thread and the pitch. Enter the real size and add no clearance.'],
        ['name' => 'Choose seal and shape', 'text' => 'For liquids choose the sealing lip into the mouth or the bed for a liner. You can add a knurled rim, a domed top and a rounded top edge.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview and the cut view and continue to the precise price. Or download the model free for your own printer; for an unknown thread, try one piece first.'],
    ],
    'faq' => [
        ['q' => 'Will a screw cap be watertight?', 'a' => 'A printed thread does not seal by itself, it only holds. For liquids choose the sealing lip into the mouth, which presses against the inside of the neck, or the bed for a liner of foam rubber or silicone. The liner is not part of the print.'],
        ['q' => 'Which threads can the tool make?', 'a' => 'The 28 mm PET bottle (27.4 mm over the thread, 2.7 mm pitch), coarse metric threads from M6 to M30, and a custom thread with a pitch of 0.5 to 6 mm. The result is always a cap or a nut for an outer thread; the tool does not make the bolt.'],
        ['q' => 'How do I measure the neck or the opening?', 'a' => 'With callipers: for a push-on lid the outside diameter of the neck, for a plug the inside of the opening at two places, for a thread the diameter over the crests and the distance between two neighbouring crests. Enter the real figure; the tool adds a clearance of 0.1 to 1 mm, which you can change.'],
        ['q' => 'How big can the part be?', 'a' => 'The diameter or width of the neck can be from 5 to 200 mm and the height from 4 to 60 mm. The tool refuses a part that is too small, because it would not print sturdy; a cap over 200 mm does not fit the print bed.'],
        ['q' => 'Can I use the cap for food and drinks?', 'a' => 'The print is not meant for direct contact with food. If the cap is for a drink or food, choose the bed for a liner and put a liner in it; the plastic itself should not meet the contents.'],
        ['q' => 'Which material, and how do I print it at home?', 'a' => 'Plugs and caps that have to spring or are screwed often from PETG; for covers that carry no load PLA is enough. It prints top down, without supports, 0.2 mm layers; a thread comes out cleaner with 0.12 mm. If the cap goes on stiffly, add 0.1 mm of clearance.'],
    ],
    'examples' => [
        'Screw cap for a 28 mm PET bottle with a sealing lip, thread 12 mm high: 5.7 g, 22 minutes, 99 CZK.',
        'End cap for a rectangular profile, opening 36 × 16 mm, plugs in 15 mm: 4.7 g, 15 minutes.',
        'Push-on lid for a jar with a 70 mm neck, 14 mm deep: 17.8 g, 39 minutes, 99 CZK.',
    ],
];
