<?php

return [
    'title' => 'Custom drawer organizer: design it and have it 3D printed',
    'description' => 'Enter the size of your drawer and the number of compartments and see a preview and a rough price at once. We print it, or you download the model free.',
    'h1' => 'An organizer with compartments that fits your drawer',
    'intro' => [
        'The tool builds an organizer with compartments from the sizes you enter in millimetres. It suits a drawer in the kitchen, the workshop or the bathroom, and also a desk with office supplies. You set the width, depth and height, the number of rows and columns and the corner rounding. All compartments are the same size, and the organizer is one piece without a lid.',
        'The preview redraws after every change and shows the outer size and the size of one compartment, with a rough price next to it. We print the organizer on our print farm in Czechia: you pick it up in person, or we send it by Packeta. With your own printer, you download the model free as a ready project for your printer or as an STL file.',
    ],
    'steps' => [
        ['name' => 'Measure the drawer', 'text' => 'Measure the inside width, depth and height of the drawer. Leave 1 to 2 mm of play on each side, drawers are rarely perfectly square.'],
        ['name' => 'Enter sizes and compartments', 'text' => 'Fill in the width, depth, height, rows and columns. You can start from the preset "Drawer", "Office supplies" or "Small parts".'],
        ['name' => 'Check preview and price', 'text' => 'The rotatable preview shows the shape, the outer size and the size of one compartment. The rough price is recalculated with every change.'],
        ['name' => 'Order the print or download', 'text' => 'Pick the material, colour and quantity and continue to the precise price; a print from us is paid from credit topped up by card. Or choose "I have a printer, download the model".'],
    ],
    'faq' => [
        ['q' => 'How big can the organizer be?', 'a' => 'The width and depth can be from 30 to 400 mm, the height from 10 to 150 mm. A large organizer may not fit every printer; the next step shows whether it fits ours.'],
        ['q' => 'How many compartments can it have?', 'a' => 'At most 8 rows and 8 columns, which makes 64 compartments. One compartment must be at least 8 mm inside, otherwise the tool asks you to change the numbers.'],
        ['q' => 'Can the compartments have different sizes?', 'a' => 'Not in this tool, the grid is regular. For compartments of different sizes use the modular organizer from bins, where you lay out the bins yourself.'],
        ['q' => 'How thick are the walls and the floor?', 'a' => 'By default the wall is 1.6 mm and the floor 1.2 mm. Under "Wall thickness and more" you can change both between 0.8 and 4 mm.'],
        ['q' => 'Do I have to order the print from you?', 'a' => 'No. You can download the model free as a project prepared for your printer, with about 270 printers to choose from, or as a plain STL file. It prints without supports.'],
    ],
    'examples' => [
        'Drawer organizer 300 × 200 × 45 mm with eight compartments.',
        'Desk organizer 200 × 100 × 60 mm, three compartments for pens and pencils.',
        'Low organizer 160 × 120 × 25 mm with twenty compartments for small parts.',
    ],
];
