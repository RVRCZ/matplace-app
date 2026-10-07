<?php

return [
    'title' => 'Split a 3D model into pieces that fit your printer',
    'description' => 'A model bigger than the print bed is cut into pieces with pins or dovetail keys, numbered in the cuts and laid to print without supports. Free, online, with a map of the pieces.',
    'h1' => 'Split a model into pieces: a big print from a small printer',
    'intro' => [
        'The tool cuts a model by planes across its width, depth and height into pieces that fit the chosen bed: our farm with 250 mm, common printers with 220 or 180 mm, or your own size. It uses as few cuts as it can and you can move the planes with sliders; the preview shows them on the model. Every piece is turned so that its largest cut face lies on the bed, so it prints without supports at the joint.',
        'Into the joints it puts pins of 6 mm with holes on both sides and 0.2 mm of play, or dovetail channels with a loose double key that slides in from the side. It engraves each piece\'s number into its cut face and shows a map of how the pieces sit together. A model that is not closed is closed first. The pieces open as a new file: have them printed by us, or download them one by one.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop a model into the field or click to choose a file: STL, 3MF, OBJ or STEP from 5 to 1000 mm.'],
        ['name' => 'Choose the bed and the joints', 'text' => 'Pick the printer the pieces are for and the kind of joint: pins, dovetail keys, or plain cuts. The tool says how many cuts are needed, and you can move the planes.'],
        ['name' => 'Split the model', 'text' => 'Click "Split the model". Big models take up to a minute; the page says what is going on.'],
        ['name' => 'Order the print, or download', 'text' => 'You see the pieces in the preview, each in its colour, with a map of how to put them together. Download them one by one or as a project for your slicer, or have them printed by us.'],
    ],
    'faq' => [
        ['q' => 'How do I glue the pieces?', 'a' => 'By the numbers engraved in the cut faces and the map on the page. Push the pins into the holes (they are in the file as the piece "pins", or use wooden 6 mm dowels) and glue the pieces with superglue or a glue for plastics. Dovetail keys hold even without glue.'],
        ['q' => 'Why do the pieces lie cut face down?', 'a' => 'A cut face is flat, so it holds on the bed and the joint needs no supports. A piece with several cuts goes down on the largest one. Turn a piece differently in your slicer if you prefer.'],
        ['q' => 'What if a piece still does not fit?', 'a' => 'The tool says so. Add a cutting plane, choose a bigger bed, or scale the model down first. A shape that does not fit even after six cuts in one direction is refused.'],
        ['q' => 'What happens to a model with holes?', 'a' => 'Before cutting, the tool closes it the same way our farm does. When that fails it says so; repair such a model first with the model repair tool.'],
        ['q' => 'How big a model can the tool take?', 'a' => 'Up to 1000 mm and 2 million triangles; a heavier mesh is thinned where possible. Big models take up to a minute.'],
        ['q' => 'What does it cost and how do I get the print?', 'a' => 'Splitting is free. You pay only for the print if you order it from us, from prepaid credit. We send the print by Packeta to a pick-up point or an address in the EU.'],
    ],
    'examples' => [],
];
