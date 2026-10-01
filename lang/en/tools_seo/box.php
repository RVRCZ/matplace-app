<?php

return [
    'title' => 'Custom box with a lid and openings for 3D printing',
    'description' => 'Enter the inside size, add a lid, openings in the walls or a cable slot. The preview and a rough price show at once. We print it, or download it free.',
    'h1' => 'A box sized to what has to fit inside',
    'intro' => [
        'The tool builds a box from its inside size, that is, from the thing that has to fit in it. It works out the outer size itself. The box suits electronics, small parts or a gift. You can add a slip-on lid with a lip, up to eight round or rectangular openings in the walls and a cable slot that runs from the top rim.',
        'The rotatable preview shows the box and the lid, with the inner and outer size below it. The rough price is recalculated after every change. We print the box on our print farm in Czechia: you pick it up in person, or it comes by Packeta. You can also print it yourself, the model is free to download. The box and the lid print side by side without supports.',
    ],
    'steps' => [
        ['name' => 'Measure what goes inside', 'text' => 'Enter the width, depth and height inside the box. Leave a few millimetres of room around the thing.'],
        ['name' => 'Choose lid and rounding', 'text' => 'Tick "With a slip-on lid" if you want a lid, and set the corner rounding. For a cable add "With a slot for the cable".'],
        ['name' => 'Add openings in the walls', 'text' => 'For each opening you choose the wall, the shape, the size and where its centre is. The position is measured from the bottom-left corner of the wall seen from outside.'],
        ['name' => 'Check preview and price', 'text' => 'The preview shows the box, the lid, the sizes and a rough price. If an opening reaches outside the wall or two of them overlap, the tool tells you.'],
        ['name' => 'Order the print or download', 'text' => 'Continue to the precise price and order the print from us; you pay from credit topped up by card. Or download the box and the lid for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Do I enter the inside or the outside size?', 'a' => 'The inside size. The tool works out the outer size from the wall and floor thickness and shows it under the preview.'],
        ['q' => 'How big can the box be?', 'a' => 'Inside from 10 to 300 mm in width and depth and from 8 to 200 mm in height. The walls can be 1.2 to 5 mm thick.'],
        ['q' => 'How does the lid stay on?', 'a' => 'The lid has a lip that drops inside the box. It has no hinges, lock or screws. If it is tight or loose, change the lid clearance; the default is 0.25 mm.'],
        ['q' => 'How many openings can I add, and where?', 'a' => 'Eight at most, in the front, back, left or right wall. An opening must be at least 2 mm in size, 2 mm from the edge and 1.5 mm from the next one. Openings cannot be added to the floor or the lid.'],
        ['q' => 'What is the cable slot for?', 'a' => 'It is a slot in the right wall, open to the top rim. The cable goes in with its plug on and the lid closes over it. The slot can be 3 to 30 mm wide.'],
    ],
    'examples' => [
        'Box with a slip-on lid for small things, inside 80 × 50 × 30 mm.',
        'Box with a lid and a cable slot, inside 120 × 80 × 40 mm.',
        'Open box without a lid, rounded corners, inside 40 × 40 × 20 mm.',
    ],
];
