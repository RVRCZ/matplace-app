<?php

return [
    'title' => 'A helmet or armour made to measure from your own model',
    'description' => 'A helmet, mask or armour model scaled to your head or chest girth, hollowed, with windows and strap slots cut, and split for the print bed. Free, in seconds.',
    'h1' => 'A helmet or armour model scaled to your measure, hollow, with windows',
    'intro' => [
        'The tool takes a model of a helmet, mask, guard or piece of armour (from Printables or MakerWorld, say) and makes it wearable. You enter a measure, the girth of your head, chest, arm, wrist or thigh, and the tool measures the model at its widest level and scales it so the inner girth matches the measure plus play. A solid model is hollowed to a wall of 2 to 4 mm and opened at the bottom; a model that is hollow already stays as it is.',
        'Up to four windows are cut through the wall, rectangular or oval, on the front, back, sides or top and offset as you need; strap slots of 25 mm in both sides are optional. A model larger than the bed is split into pieces with pins or dovetail keys and engraved numbers. All of it takes a few seconds and is free; you bring the helmet model yourself, we supply no film characters.',
    ],
    'steps' => [
        ['name' => 'Upload a model', 'text' => 'An STL or 3MF of a helmet, mask or armour you want printed to your size.'],
        ['name' => 'Enter the measure', 'text' => 'Choose what the model goes round (head, chest, arm…) and enter the girth in centimetres; the tool shows at once how much it scales and how big the result is.'],
        ['name' => 'Windows, strap, splitting', 'text' => 'Add windows (side, shape, size, offset) and strap slots if you like. Choose your printer\'s bed and the joints of the pieces.'],
        ['name' => 'Create the model', 'text' => 'Click "Make it to measure". A few seconds later it opens with its size, inner girth, pieces and print price.'],
    ],
    'faq' => [
        ['q' => 'How is the model scaled?', 'a' => 'Evenly in all directions. The tool finds the widest horizontal section of the model, measures its inner girth (for a solid model it subtracts the wall) and picks the scale so the girth matches your measure plus play, 10 mm by default.'],
        ['q' => 'How do I measure my head?', 'a' => 'With a tape measure horizontally round the head, over the forehead and the widest point at the back, snug but not tight. An adult is usually 54 to 60 cm. For the chest measure round the widest point while breathing in.'],
        ['q' => 'What if the model is hollow already?', 'a' => 'The tool sees that in the section and leaves it as it is; the wall is not changed then. A solid model is hollowed and opened at the bottom so the head goes in.'],
        ['q' => 'Where can the windows go?', 'a' => 'On the front, back, left, right or top, offset sideways and up or down from the default eye line (60 % of the model\'s height). A window goes through the nearer wall only.'],
        ['q' => 'Does a helmet fit the printer?', 'a' => 'A helmet for a 56 cm head is about 19 cm across and fits a 220 or 250 mm bed. Bigger pieces are split with Ø 6 pins or dovetail keys and glued after printing.'],
        ['q' => 'Do you supply helmet models?', 'a' => 'No, you bring the model; film characters are protected. The tool adapts any model of your own or freely shared.'],
    ],
    'examples' => [],
];
