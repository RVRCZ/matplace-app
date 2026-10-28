<?php

// The page for the query "custom box generator 3d print" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom box generator for 3D printing: lid and holes, free',
    'heading' => 'Custom box to 3D print, sized to what goes inside',
    'description' => 'Enter the inner size, add a lid, holes in the walls or a cable slot, and see the preview with the outer size at once. Free model, or we print it.',
    'h1' => 'What the custom box does, how the lid fits and what a print costs',
    'intro' => [
        'The tool makes a box from the inner size, that is from the thing that should fit inside: width and depth 10 to 300 mm, height 8 to 200 mm, walls 1.2 to 5 mm. It computes the outer size by itself and shows it under the preview. You can add a push-on lid with a rim (0.25 mm of play, adjustable), up to eight round or rectangular holes in the front, back, left or right wall, rounded corners and a cable slot from the top edge 3 to 30 mm wide. The box and the lid print side by side without supports. It serves for electronics, small parts, SD cards and as gift packaging.',
        'An example from the production calculator (18 October 2026, PLA+): a box with a push-on lid, 80 × 50 × 30 mm inside (84 × 54 × 32 mm outside), weighs 28 g, prints in 34 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order (calculation /c/nku29wz4vieb); with a lid and a cable slot it weighs 41 g, prints in 51 minutes and costs 110 CZK, both without delivery. In 90 days 13 boxes went through the calculator. For a drawer PLA is enough, for a bathroom or next to the oven choose PETG. You download the model free as an STL or a project for your printer; the box and the lid also as separate parts.',
    ],
    'steps' => [
        ['name' => 'Measure what should fit', 'text' => 'Enter the width, depth and height inside the box. Leave 1 to 2 mm of play on every side of the thing.'],
        ['name' => 'Choose the lid and the rounding', 'text' => 'Tick "With a push-on lid" if you want the lid, and set the rounding of the corners. For a cable add "With a cable slot".'],
        ['name' => 'Add holes in the walls', 'text' => 'For every hole you pick the wall, the shape, the size and the position of its centre. The position is measured from the bottom left corner of the wall seen from outside.'],
        ['name' => 'Check the preview', 'text' => 'The preview shows the box, the lid and the sizes. If a hole runs off the wall or two overlap, the tool says so.'],
        ['name' => 'Order the print or download', 'text' => 'Continue to the exact price and order the print from us. Or download the box and the lid free for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Do I enter the inner or the outer size?', 'a' => 'The inner one. The tool computes the outer size from the wall and bottom thickness and shows it under the preview; a box of 80 × 50 × 30 mm with 2 mm walls comes out at 84 × 54 × 32 mm.'],
        ['q' => 'How big can the box be?', 'a' => 'Inside from 10 to 300 mm in width and depth and from 8 to 200 mm in height. The walls can be 1.2 to 5 mm thick. A box over 250 mm does not fit every printer; the calculation says so.'],
        ['q' => 'How does the lid hold?', 'a' => 'The lid has a rim that fits inside the box. It has no hinges, lock or screws. If it is tight or loose, adjust the lid play; the default is 0.25 mm. Test the play on the first box of a new set.'],
        ['q' => 'How many holes can I add, and where?', 'a' => 'Eight at most, in the front, back, left or right wall. A hole must be at least 2 mm, 2 mm from the edge and 1.5 mm from another hole. Holes cannot go into the bottom or the lid.'],
        ['q' => 'What is the cable slot for?', 'a' => 'It is a groove in the right wall open to the top edge. You lay the cable in it with its plug and the lid closes over it. You choose the slot width from 3 to 30 mm; 10 mm is enough for USB-C, 15 mm for a power cable with its plug.'],
        ['q' => 'Does the box suit electronics, an Arduino or an ESP32 say?', 'a' => 'Yes: enter the board size plus 2 mm on every side and the height by the tallest component, and place the connector holes by their position from the corner of the wall. Add vents as a row of round holes; for heat inside choose PETG.'],
    ],
    'examples' => [
        'A box with a push-on lid for small things, 80 × 50 × 30 mm inside: 28 g, 34 minutes, 99 CZK.',
        'A box with a lid and a cable slot, 120 × 80 × 40 mm inside.',
        'An open box without a lid with rounded corners, 40 × 40 × 20 mm inside.',
    ],
];
