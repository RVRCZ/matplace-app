<?php

return [
    'title' => 'Make a model life size: scale, hollow and split into pieces',
    'description' => 'Give a height and the tool scales the model, hollows big ones and cuts it into pinned pieces that fit the printer. With grams, a price and a map. Free.',
    'h1' => 'Life size: a bust, a figure or a statue at a metre scale',
    'intro' => [
        'The tool scales a model to the height you give in centimetres, from 5 to 100 cm. Pieces over 200 cm³ are hollowed with a wall of 2 to 5 mm by size, so they do not cost a fortune or print for days; round the future cuts the model stays solid so that the pins have something to sit in. Then it cuts the model by planes into pieces that fit the chosen bed: our farm with 250 mm, common printers with 220 or 180 mm, or your own size.',
        'Before anything is built it says how big the model will be, whether it will be hollow and how many pieces it comes to. The result is pieces with pins of 6 mm, numbers engraved in the cuts, laid cut face down, with an estimate of grams and the print price and a map of how the pieces sit. Above 20 pieces the tool warns. The pieces open as a new file: have them printed by us, or download them one by one.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop a model into the field or click to choose a file: STL, 3MF, OBJ or STEP. Busts, figures and statuettes suit it; the model must not exceed a metre after scaling.'],
        ['name' => 'Give the height and the bed', 'text' => 'The height in centimetres, the printer the pieces are for and the kind of joint. The tool says at once how big the model will be and how many pieces it makes.'],
        ['name' => 'Scale and split', 'text' => 'Click "Scale and split". Big models take up to a minute; the page says what is going on.'],
        ['name' => 'Order the print, or download', 'text' => 'You see the pieces in the preview, each in its colour, with grams, a price and a map. Download them one by one or as a project for your slicer, or have them printed by us.'],
    ],
    'faq' => [
        ['q' => 'Why is the model hollow?', 'a' => 'A solid bust 40 cm tall would weigh over a kilogram and print for days. A cavity with a wall of 2 to 5 mm saves most of the material and leaves the outside unchanged. Hollowing can be switched off.'],
        ['q' => 'How are the pieces joined?', 'a' => 'Pins of 6 mm in holes with 0.2 mm of play (in the file as the piece "pins", or use wooden dowels) and a glue for plastics. Round the cuts the model stays solid, so the pins sit in material. Dovetail keys and plain cuts are the other two options.'],
        ['q' => 'Where do I get a model?', 'a' => 'From our bust-from-a-photo tool, from Printables or MakerWorld, or from your own scan. The model must be a closed body; what is not is closed by the tool.'],
        ['q' => 'How big a model can the tool take?', 'a' => 'Up to 1000 mm after scaling; an input of up to 2 million triangles. Big models take up to a minute; their hollowing grid is coarser and the tool says so.'],
        ['q' => 'What if a piece still does not fit?', 'a' => 'The tool says so. Choose a bigger bed, or a smaller height. A shape that does not fit even after six cuts in one direction is refused.'],
        ['q' => 'What does it cost and how do I get the print?', 'a' => 'Scaling and splitting are free. You pay only for the print if you order it from us, from prepaid credit. We send the print by Packeta to a pick-up point or an address in the EU.'],
    ],
    'examples' => [],
];
