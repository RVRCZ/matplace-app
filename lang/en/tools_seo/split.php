<?php

// The page for the query "split stl model online" (docs/AP.md §1, §4). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Split an STL into parts online, free: pins, dovetails, a map',
    'heading' => 'Split a 3D model into parts online',
    'description' => 'A model bigger than your printer is cut into parts with pins or dovetail keys, numbers in the cut and a map of how they fit. Parts lie cut side down. Free.',
    'h1' => 'How the split works, what you get and how to glue the parts',
    'intro' => [
        'The tool cuts the model with planes across its width, depth and height into parts that fit the bed you choose: our farm at 250 mm, common printers at 220 or 180 mm, or your own size. It makes as few cuts as possible, you can move the planes with sliders and the preview shows them right on the model. Every part is turned to lie on its largest cut face, so the joint prints without supports. Into the joints it adds pins of 6 mm diameter with holes on both sides and 0.2 mm of play, or dovetail grooves with a loose double-sided key that slides in from the side; plain cuts for gluing only are there too. It engraves each part’s number into its cut face and shows a map on the page of how the parts lie against each other. It takes STL, 3MF, OBJ and STEP from 5 to 1000 mm and up to 2 million triangles, closes a model that is not closed first, and refuses a shape that does not fit even after six cuts in one direction.',
        'An example from the production calculator (18 October 2026, PLA+): a cylinder of ⌀ 120 × 320 mm does not fit a 250 mm bed. The tool split it with one cut into two parts with four pins; both parts and the pins together weigh 814 g, print in 13 h 8 min and cost 1,440 CZK (about 58 EUR) from us. The split took under a minute. The parts open as a new file: in the preview each has its own colour and the map says which goes where. You download them one by one as STL or all of them as a slicer project, or have them printed by us in one order; the original model stays.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop an STL, 3MF, OBJ or STEP of 5 to 1000 mm into the field, or click and choose the file.'],
        ['name' => 'Choose the bed and the joints', 'text' => 'Pick the printer the parts are for (250, 220, 180 mm or your own) and the kind of joint: pins, dovetail keys, or plain cuts. The tool says how many cuts are needed and you can move the planes.'],
        ['name' => 'Split the model', 'text' => 'Click "Split the model". Large models take up to a minute; the page says what is going on.'],
        ['name' => 'Download the parts or order the print', 'text' => 'You see the parts in the preview, each in its own colour, with the map and the numbers. Download them one by one or as a slicer project, or have them printed by us.'],
    ],
    'faq' => [
        ['q' => 'Pins, dovetails, or a plain cut?', 'a' => 'Pins are the strongest with glue and invisible from the outside. Dovetail keys hold without glue and the parts come apart again, but the key slides in from the side, so it needs room. A plain cut is the quickest to print and holds by glue alone; it suits parts that stand on each other.'],
        ['q' => 'How do I glue the parts?', 'a' => 'By the numbers engraved in the cut faces and the map on the page. Push the pins into the holes (they are in the file as the part "pins", or use wooden 6 mm dowels) and glue the parts with superglue or a glue for plastics. Dovetail keys hold without glue.'],
        ['q' => 'Will the joint show?', 'a' => 'As a thin line: the parts lie cut side down, so the cut face is flat, and the pins and holes are inside. The 0.2 mm of play disappears after gluing; for a smooth surface, sand the joint and fill or paint it.'],
        ['q' => 'Why do the parts lie on their cut face?', 'a' => 'The cut face is flat, so it holds on the bed and the joint needs no supports. A part with several cuts goes down on the largest one. A part you want turned differently you turn in your slicer.'],
        ['q' => 'What if a part still does not fit?', 'a' => 'The tool says so. Add a cutting plane, choose a bigger bed, or scale the model down in the calculator first. A shape that does not fit even after six cuts in one direction is refused.'],
        ['q' => 'How big a model can the tool handle?', 'a' => 'Up to 1000 mm and 2 million triangles; a heavier mesh is simplified when possible. A model with holes is closed first the way our farm does it; if that fails, the tool says so and the Model repair tool helps.'],
    ],
    'examples' => [],
];
