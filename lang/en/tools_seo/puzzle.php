<?php

// The page for the query "3d printed puzzle generator" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed puzzle generator: jigsaw from a model or photo',
    'heading' => '3D printed puzzle generator: a jigsaw from your model or photo, free',
    'description' => 'A flat model, a relief from a photo, a logo or a lithophane cut into a jigsaw of 2 × 2 to 8 × 8 pieces with knobs or pins, numbered underneath. Free.',
    'h1' => 'How the puzzle is made, how big the pieces should be and what it costs',
    'intro' => [
        'The tool cuts a flat model into a jigsaw: a grid of 2 × 2 to 8 × 8 pieces with classic knobs, a head and a neck that fit together with 0.2 mm of play. A relief from a photo, a logo, a lithophane or a coaster from our tools suits it, or anything flat up to 25 mm; a taller model gets hidden Ø 3 mm pins in the sides instead, because a knob would not slide into thick plastic. The knobs and their directions are laid out by the tool; the pieces at the rim follow the model\'s outline. The number of every piece is engraved underneath, so the puzzle can be put together without the picture, and a tray with a 6 mm rim can be added for the finished puzzle to drop into. A model that is not closed is closed first; a piece that would fall apart because of a hole keeps its biggest bit, and the tool says so.',
        'An example from the production calculator (28 October 2026, PLA): the round 100 mm coaster with the snowman from the Coaster tool cut into 3 × 4 pieces with knobs and numbers underneath weighs 22.6 g, prints in 1 h 3 min and costs 99 CZK (about 4 EUR) from us without delivery (calculation /c/wnr4wkkungpz); the pieces print together side by side and every piece can have its own colour when ordering. The puzzle opens as a new file: have it printed by us, or download the pieces one by one or as a project for your slicer, free and without registration. The tool itself is free.',
    ],
    'steps' => [
        ['name' => 'Upload a flat model', 'text' => 'Drop a model into the field or click to choose a file: STL, 3MF, OBJ or STEP, best a relief, a logo, a lithophane or a coaster up to 25 mm high. A model from our tools comes here straight from its calculation.'],
        ['name' => 'Choose the grid and the joints', 'text' => 'Rows and columns, jigsaw knobs (the size of the head and the play) or hidden pins, numbers underneath and a tray.'],
        ['name' => 'Make the puzzle', 'text' => 'Click "Make the puzzle". It takes a few seconds; you see the pieces in the preview, each in its colour, and the tool says how big they are.'],
        ['name' => 'Order the print, or download', 'text' => 'Download the pieces one by one or as a project for your slicer, or have them printed by us; the tray and the pins are pieces of their own.'],
    ],
    'faq' => [
        ['q' => 'How big should the pieces be?', 'a' => 'At least 12 mm, better 25 to 40 mm: knobs of that size print reliably and the pieces are easy to handle. A 100 mm coaster gives about 33 × 25 mm at 3 × 4 pieces; a 200 mm relief takes 6 × 6. Smaller pieces are refused.'],
        ['q' => 'Will the knobs hold, or will the pieces fall apart?', 'a' => 'The knobs have 0.2 mm of play (0.1 to 0.4 can be set), which on a usual printer with a 0.4 mm nozzle gives a firm but separable joint. Too tight: more play; too loose: less. The head of the knob can be 25 to 45 % of the piece.'],
        ['q' => 'When should I choose hidden pins?', 'a' => 'When the top is to stay without knobs, as on a lithophane or a logo, or when the model is taller than 25 mm. The pins of Ø 3 mm are in the file as a piece of their own; the pieces go onto them and are glued.'],
        ['q' => 'Can I make a puzzle from a photo?', 'a' => 'Yes, through two tools: first turn the photo into a relief or a lithophane in the Relief and lithophane tool, or into a coaster in colours, and send the finished model here. A puzzle from a lithophane is put together against a window, a puzzle from a coaster has colours.'],
        ['q' => 'What is the tray for?', 'a' => 'A tray with a 6 mm rim and a 1.5 mm base following the model\'s outline; the finished puzzle drops into it with play and can be displayed or hung. It prints as a piece of its own.'],
        ['q' => 'How do I print the puzzle at home?', 'a' => 'The pieces flat, face up, without supports, 0.2 mm layers, PLA; the slicer project has them laid out side by side. For a coaster in colours the filament changes at the colour heights stay in. The tray prints bottom down.'],
    ],
    'examples' => [],
];
