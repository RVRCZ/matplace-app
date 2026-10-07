<?php

return [
    'title' => 'Hollow a 3D model: a hollow model with wall and drain holes',
    'description' => 'Turn a solid model into a hollow one with a wall of 1.5 to 6 mm and drain holes in the bottom. The tool counts the grams and money saved. Free, in seconds.',
    'h1' => 'A hollow model: less material, a shorter print, the same shape outside',
    'intro' => [
        'The tool takes the inside out of a model and leaves the wall you choose, from 1.5 to 6 mm. The distance from the surface is measured on a fine grid (0.6 mm, coarser on big models, and the tool says so) and the inner surface forms where the wall thickness is reached; so it keeps the shape even in folds and the cavity never breaks through. A model that is not closed is closed first.',
        'Drain holes of 3 to 8 mm go into the bottom of the cavity: as many as fit, four at most, or the number you ask for; they can be left out. The tool counts the volume of the cavity, the grams of material saved and the difference in the print price. The hollow model opens as a new file: have it printed by us, or download it for your own printer.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop a model into the field or click to choose a file: STL, 3MF, OBJ or STEP from 5 to 1000 mm.'],
        ['name' => 'Choose the wall and the holes', 'text' => 'A wall of 1.5 to 6 mm; drain holes in the bottom with a diameter and a number, or none.'],
        ['name' => 'Hollow it', 'text' => 'Click "Hollow the model". It takes a few seconds, up to a minute for big models.'],
        ['name' => 'Order the print, or download', 'text' => 'You see how many grams and how much money the cavity saves. The X-ray view shows the wall. Download the model as STL or as a project for your slicer, or have it printed by us.'],
    ],
    'faq' => [
        ['q' => 'Why hollow a model when the slicer has infill?', 'a' => 'Infill is fine for FDM; a cavity is for resin printing (a solid part would crack and use litres), for big figures, and for things you want to fill or weigh down with sand. A hollow model uses less material even with infill in the slicer, because the infill is printed only in the wall.'],
        ['q' => 'How thick a wall should I choose?', 'a' => '2 to 3 mm for FDM (the wall is then printed with the slicer\'s infill), 2 to 3 mm for resin, 3 to 5 mm for big busts. Below 1.5 mm the tool does not go.'],
        ['q' => 'What are the drain holes for?', 'a' => 'In resin printing the uncured resin flows out and no suction builds up; in FDM you shake the supports out of the cavity through them. Switch them off if you do not want them; the cavity is then closed.'],
        ['q' => 'What happens to a model with holes?', 'a' => 'Before measuring, the tool closes it the same way our farm does. When that fails it says so; repair such a model first with the model repair tool.'],
        ['q' => 'How big a model can the tool take?', 'a' => 'Up to 1000 mm and 2 million triangles. Above 190 mm the measuring grid is coarser than 0.6 mm and the wall may differ by a tenth to half a millimetre in places; the tool says so.'],
        ['q' => 'What does it cost and how do I get the print?', 'a' => 'Hollowing is free. You pay only for the print if you order it from us, from prepaid credit. We send the print by Packeta to a pick-up point or an address in the EU.'],
    ],
    'examples' => [],
];
