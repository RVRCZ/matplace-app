<?php

// The page for the query "modular drawer organizer generator" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Modular drawer organizer generator: bins made to measure',
    'heading' => 'Modular drawer organizer generator: loose bins made to measure',
    'description' => 'Enter your drawer\'s inside, divide it into a grid and lay out bins of different sizes. Each bin prints on its own and lifts out. Print, or download free.',
    'h1' => 'How to design the set of bins, what it costs and how it prints',
    'intro' => [
        'A modular organizer is a set of separate bins that fill a drawer exactly. Enter the inside width and depth of the drawer, 60 to 600 mm, and the height of the bins, 15 to 120 mm, divide the area into a grid of up to 12 × 12 cells and lay out up to 24 bins of different sizes on the cells; one bin can cover several cells. Every bin prints separately, so you can lift it out, rearrange it or wash it, and every one can have another colour. There is a gap of 0.6 mm between the bins and the corners are rounded. When the bins go on a desk or a shelf, not into a drawer, you add an outer tray that keeps them together. Unlike a one-piece organizer the set suits a large drawer: the bins fit any printer.',
        'An example from the production calculator (28 October 2026, PLA): four bins in three sizes for a 300 × 150 mm drawer, 40 mm high, weigh 188 g together, print in 3 h 45 min and cost 310 CZK (about 12 EUR) from us without delivery (calculation /c/fsgkqtoncl9f); you pick the colour of every bin when ordering, in one order. The tool shows the layout in the preview and under it the list "What gets printed" with the size of every bin; the calculation one step further prices the whole set. If you have a printer, you download the single bins as STLs or the whole set as a project for your printer, free.',
    ],
    'steps' => [
        ['name' => 'Measure inside the drawer', 'text' => 'Enter the width and depth inside the drawer and the height of the bins. Take 1 to 2 mm off the measured size, drawer walls are rarely perfectly straight.'],
        ['name' => 'Divide it into a grid', 'text' => 'Choose the number of columns and rows. The tool shows how many millimetres one grid cell measures; it needs to be at least 15 mm.'],
        ['name' => 'Lay out the bins', 'text' => 'Tap an empty cell and then the opposite corner of the bin. Tap a finished bin to select it and change its colour or remove it.'],
        ['name' => 'Check the list', 'text' => 'Under the preview is the list "What gets printed" with the size of each bin. The precise price of the whole set is one step further, in the calculation.'],
        ['name' => 'Order the print or download', 'text' => 'Continue to the precise price and order the set from us. Or download the single parts or the project under "I have a printer, download the model".'],
    ],
    'faq' => [
        ['q' => 'How big a drawer can the tool handle?', 'a' => 'The inside of the drawer can be from 60 to 600 mm in both width and depth, and the bins can be 15 to 120 mm high. Every bin prints separately, so even a large drawer is made of smaller parts that fit an ordinary printer.'],
        ['q' => 'How many bins can a set have?', 'a' => 'At most 24 bins per set. The grid can have up to 12 columns and 12 rows, and one bin can cover several cells; a cell must measure at least 15 mm.'],
        ['q' => 'What is the outer tray for?', 'a' => 'The tray keeps the bins together when they stand on a desk or a shelf and not in a drawer. A big tray may not fit a printer, the calculation says so; the bins work without it.'],
        ['q' => 'Will the bins lift out of the drawer easily?', 'a' => 'Yes. There is a gap between the bins, 0.6 mm by default, and the corners of the bins are rounded. You change the gap, the wall and the floor thickness under "Wall thickness and more".'],
        ['q' => 'Can every bin have a different colour?', 'a' => 'Yes. You pick a colour for each bin in the design, and for a print from us you choose the colour of every bin from the spools loaded in our printers, in one order. At home you print every bin on its own from its spool.'],
        ['q' => 'When should I choose the one-piece organizer?', 'a' => 'When the drawer is up to 250 mm, so a one-piece organizer fits an ordinary bed, and you want the compartments fixed in place, choose the Drawer organizer tool: it is one part without gaps. The set of bins suits a larger drawer and contents that change: you rearrange the bins or add one.'],
    ],
    'examples' => [
        'Four bins in three sizes for a 300 × 150 mm drawer, 40 mm high: 188 g, 3 h 45 min, 310 CZK.',
        'Four equal square bins for a 160 × 160 mm drawer, 30 mm high.',
        'Four bins in a desk tray, inside 240 × 120 mm, height 45 mm.',
    ],
];
