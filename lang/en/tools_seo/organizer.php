<?php

// The page for the query "drawer organizer generator 3d print" (docs/AP.md §1, §5). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom drawer organizer generator for 3D printing, free STL',
    'heading' => 'Drawer organizer made to your drawer',
    'description' => 'Enter the inner size of your drawer and the number of compartments and see the 3D preview at once. Download the model free as an STL, or we print it.',
    'h1' => 'What the organizer does, how to design it to measure and what a print costs',
    'intro' => [
        'The tool makes an organizer with compartments to the sizes you enter in millimetres: width and depth from 30 to 400 mm, height from 10 to 150 mm, up to 8 rows and 8 columns, that is 64 compartments, each at least 8 mm inside. All compartments are the same size and the organizer is one piece without a lid that prints without supports. The default wall is 1.6 mm and the bottom 1.2 mm, both adjustable from 0.8 to 4 mm, and the corners can be rounded. You can start from the preset "Drawer", "Desk supplies" or "Small parts". The preview redraws after every change and shows the outer size and the size of one compartment, so you see whether cutlery, batteries or screws will fit. It suits a drawer in the kitchen, the workshop or the bathroom, and a desk.',
        'An example from the production calculator (18 October 2026, PLA+): a 200 × 120 × 40 mm organizer with 3 × 2 compartments weighs 115 g, prints in 2 h 27 min and costs 250 CZK (about 10 EUR) from us (calculation /c/l1gr0izblzwr); a small 100 × 80 × 30 mm one with 2 × 2 compartments weighs 41 g, prints in 57 minutes and costs 110 CZK, all without delivery. A 300 × 400 × 50 mm organizer is flagged by the calculator as bigger than a common bed: split such a drawer into two organizers side by side, or use the modular bins. If you own a printer, download the model free as a project for your machine (about 270 printers to choose from) or as an STL; a print from us ships by Packeta.',
    ],
    'steps' => [
        ['name' => 'Measure the drawer', 'text' => 'Measure the inner width, depth and height of the drawer at its narrowest point. Leave 1 to 2 mm of play on each side, drawers are rarely exactly square.'],
        ['name' => 'Enter the size and the compartments', 'text' => 'Fill in the width, depth, height, rows and columns. You can start from the preset "Drawer", "Desk supplies" or "Small parts".'],
        ['name' => 'Check the preview', 'text' => 'The rotatable preview shows the shape, the outer size and the size of one compartment. It redraws with every change.'],
        ['name' => 'Order the print or download', 'text' => 'Choose the material, the colour and the number of pieces and continue to the exact price. Or choose "I have a printer, download the model".'],
    ],
    'faq' => [
        ['q' => 'How big an organizer can I design?', 'a' => 'Width and depth from 30 to 400 mm, height from 10 to 150 mm. An organizer over 250 mm may not fit every printer; the calculator says so at once, and you then split the drawer into two organizers side by side.'],
        ['q' => 'How many compartments can it have, and can they differ in size?', 'a' => 'Up to 8 rows and 8 columns, that is 64 compartments, each at least 8 mm inside. The grid is regular; for compartments of different sizes use the modular organizer of bins, where you lay out the arrangement yourself.'],
        ['q' => 'How thick are the walls and the bottom?', 'a' => 'The default wall is 1.6 mm and the bottom 1.2 mm, enough for cutlery and desk supplies. Under "Wall thickness and more" you change both from 0.8 to 4 mm; for screws and heavy things choose 2 mm or more.'],
        ['q' => 'Can I add a lid or dividers of a different height?', 'a' => 'Not in this tool: the organizer is open and all walls have the same height. The Custom box tool makes a box with a lid, the Modular organizer tool bins of different heights.'],
        ['q' => 'How do I print the organizer at home?', 'a' => 'Flat, bottom down, without supports; the downloaded project carries the settings of the calculation (0.2 mm layers, 15 % infill). For the kitchen or the bathroom choose PETG, which takes heat and damp better; otherwise PLA is enough.'],
        ['q' => 'Do I have to have it printed by you?', 'a' => 'No. Download the model free as a project ready for your printer, with about 270 printers to choose from, or as a plain STL file. A print from us is for those without a printer or with a large piece.'],
    ],
    'examples' => [
        'A 300 × 200 × 45 mm drawer organizer with eight compartments.',
        'A 200 × 100 × 60 mm desk organizer, three compartments for pens.',
        'A low 160 × 120 × 25 mm organizer with twenty compartments for small parts.',
    ],
];
