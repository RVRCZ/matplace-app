<?php

return [
    'title' => 'Coloured 3MF into parts by colour: split it online',
    'description' => 'Upload a multi-colour 3MF from Bambu Studio, Orca or PrusaSlicer and get a separate part for every colour: STL files to print one colour at a time or to glue.',
    'h1' => 'A coloured model split into parts by colour',
    'intro' => [
        'The tool reads the colours of a 3MF: the file\'s materials, the extruder assigned to objects and parts, and brush painting from Bambu Studio, Orca Slicer or PrusaSlicer. Every colour becomes a part of its own. A body that is all one colour stays as it is; a colour painted on a surface is cut out as an inlay of the depth you set (1.2 mm as a rule) and the body gets a recess of the same depth, so the inlay fits.',
        'You download the parts one by one as STL and print each in its colour on any printer, or leave them in place and print them in one go as a multi-material job. The preview shows the parts in the file\'s colours and says where the colours came from. A triangle the slicer subdivided while painting takes the colour of its larger share, and the tool says so. Splitting is free, the model never leaves our server and is deleted after a month.',
    ],
    'steps' => [
        ['name' => 'Upload a coloured 3MF', 'text' => 'A file saved from a slicer with its colours, or a 3MF with materials from a CAD program. The tool shows the colours it found and their shares at once.'],
        ['name' => 'Choose the depth of the inlays', 'text' => 'How deep a painted colour reaches into the model, 0.6 to 3 mm. Separate bodies do not care.'],
        ['name' => 'Split', 'text' => 'Click "Split by colour". A few seconds later it is done: the parts in the preview in their colours, a list with names and shares.'],
        ['name' => 'Download or order', 'text' => 'Every part on its own as STL, or the whole for printing in one go. The price of a print from us shows right away.'],
    ],
    'faq' => [
        ['q' => 'Which colours does the tool recognise?', 'a' => '3MF materials (basematerials, colour groups), colours on an object and on single triangles, the extruder assigned to an object or part in Bambu Studio, Orca and PrusaSlicer, and brush painting (paint_color, mmu_segmentation). Filament colours come from the project; without them a fixed palette is used.'],
        ['q' => 'What happens to a painted colour?', 'a' => 'The painted faces are pushed inward by the depth you set and closed with walls: an inlay. The body gets a recess of the same depth. The inlay stands 0.05 mm proud so it can be sanded flush after printing.'],
        ['q' => 'Why does a part have an unexpected colour?', 'a' => 'A slicer subdivides triangles while painting; the tool gives a whole triangle the colour of its larger share and says how many triangles that concerns. A finer result comes from a finer mesh before painting.'],
        ['q' => 'Can it be printed in one go?', 'a' => 'Yes: the whole keeps the parts in their places; in the slicer you assign them to extruders or AMS slots and print one multi-material job. On a single-colour printer you print the parts one by one and glue them.'],
        ['q' => 'How many colours can it handle?', 'a' => 'Up to 16; smaller colours beyond that number join the largest one. A file may have up to 2 million triangles.'],
        ['q' => 'Do you keep my model?', 'a' => 'The model stays on our server only for the computation and the download and is deleted after a month. We send it to nobody.'],
    ],
    'examples' => [],
];
