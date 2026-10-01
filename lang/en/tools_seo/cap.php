<?php

return [
    'title' => 'Custom lid, plug or threaded cap for 3D printing',
    'description' => 'Measure the neck or the opening and choose how the part holds: pushed on, plugged in or screwed on. Preview and price at once. We print it, or download.',
    'h1' => 'A replacement lid, a pipe plug or a profile end cap',
    'intro' => [
        'The tool builds a missing lid, plug or cover from what you measure. The part pushes over a rim, plugs into an opening or screws onto a thread. It can be round, rectangular or hexagonal. For a thread you pick the 28 mm PET bottle, a metric thread from M6 to M30, or enter your own diameter and pitch. The tool adds the clearance, so you enter the real size.',
        'The preview shows the part from outside and in a cut view, and states which size it fits. The rough price shows at once. We print the part on our print farm in Czechia: it comes by Packeta to a pickup point or to your door. With your own printer you download the model free; it prints without supports. For an unknown thread, try one piece before ordering more.',
    ],
    'steps' => [
        ['name' => 'Choose how it holds', 'text' => 'Pushed over the rim, plugged into the opening or screwed onto a thread. You can start from a preset such as "PET bottle (28 mm)" or "Pipe plug".'],
        ['name' => 'Enter what you measured', 'text' => 'For a push-on lid measure the outside of the neck, for a plug the inside of the opening, for a thread the diameter over the thread and the pitch. Enter the real size and add no clearance.'],
        ['name' => 'Choose seal and shape', 'text' => 'For liquids choose the sealing lip into the mouth or the bed for a liner. You can add a knurled rim and round the top edge.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview and the cut view and continue to the precise price; a print from us is paid from credit topped up by card. Or download the model for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Will a screw cap be watertight?', 'a' => 'A printed thread does not seal by itself. For liquids choose the sealing lip into the mouth or the bed for a liner of foam rubber or silicone. The liner is not part of the print.'],
        ['q' => 'Which threads can the tool make?', 'a' => 'The 28 mm PET bottle, coarse metric threads from M6 to M30, and a custom thread with a pitch of 0.5 to 6 mm. The result is always a cap or a nut for an outer thread; the tool does not make the bolt.'],
        ['q' => 'How big can the part be?', 'a' => 'The diameter or width of the neck can be from 5 to 200 mm and the height from 4 to 60 mm. The tool refuses a part that is too small, because it would not print sturdy.'],
        ['q' => 'Can I use the cap for food and drinks?', 'a' => 'The print is not meant for direct contact with food. If the cap is for a drink or food, choose the bed for a liner and put a liner in it.'],
        ['q' => 'Which material should the part be printed in?', 'a' => 'For plugs and caps that have to spring we recommend the strong plastic (PETG). For covers that carry no load the everyday plastic (PLA) is usually enough.'],
    ],
    'examples' => [
        'Screw cap for a 28 mm PET bottle with sealing lip, thread 12 mm high.',
        'End cap for a rectangular profile, opening 36 × 16 mm, plugs in 15 mm.',
        'Push-on lid for a jar with a 70 mm neck, 14 mm deep.',
    ],
];
