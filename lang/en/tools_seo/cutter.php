<?php

return [
    'title' => 'Cookie cutter in your own shape for 3D printing',
    'description' => 'Type a name or a number, or upload a picture of the shape. You get a made-to-measure cookie cutter with a flange. We print it, or you download it free.',
    'h1' => 'A cookie cutter from a name, a number or your own drawing',
    'intro' => [
        'The tool makes a cookie cutter from a text or a picture. Type a name, letter or number, or upload a drawing of the shape; a pencil outline will do. You get a thin wall along the outline with a flange to press on. The shape can be 30 to 150 mm wide and the wall 10 to 30 mm high, with a tapered or straight cutting edge.',
        'When the uploaded picture has lines inside the outline, such as leaf veins or eyes and a smile, the tool turns them into a separate stamp to press into the dough after cutting. The outline is mirrored, so with the flange turned up it cuts the right way round. Order the cutter as a print from our print farm, or download the model free for your own printer.',
    ],
    'steps' => [
        ['name' => 'Type text or upload shape', 'text' => 'The text can have two lines of 20 characters each, in one of four typefaces. Instead of a text you can upload an SVG or a picture of the shape.'],
        ['name' => 'Set the sizes and edge', 'text' => 'Enter the shape width, the wall height and thickness and the flange width. A tapered edge cuts more cleanly, a straight one is sturdier.'],
        ['name' => 'Check the preview', 'text' => 'The preview shows the cutter turned the way it is used, with the stamp beside it if there is one. The closed inside of a letter, as in an O, gets its own wall, tied to the rest by flat bars.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our print farm and pay from prepaid credit. Or download the model free as an STL file or a ready project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'What kind of picture makes a cutter?', 'a' => 'A simple dark drawing on a light background, or an SVG with filled shapes. Everything the drawing encloses counts as the shape, and the largest connected area is used. Photographs are not suitable.'],
        ['q' => 'When do I also get a stamp?', 'a' => 'Only with an uploaded PNG, JPG or WebP picture that has enough lines inside the outline, and only while the option "Stamp from the inner drawing" stays ticked. A text or an SVG gives no stamp.'],
        ['q' => 'What is the cutter printed from and how is it washed?', 'a' => 'We recommend the ordinary plastic PLA or the stronger PETG. Wash it by hand in lukewarm water and never in a dishwasher: PLA starts to soften at about 55 °C.'],
        ['q' => 'Is the print suitable for contact with food?', 'a' => 'The cutter is meant for brief contact with raw dough that is baked afterwards. We do not recommend printed parts without a liner for lasting contact with food.'],
        ['q' => 'How fine can the shape be?', 'a' => 'The tool leaves out any part of the shape that is narrower than twice the wall thickness, because dough would get stuck there. So make small lettering bigger or choose a shorter text.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print by Packeta to a pickup point or to your door across the EU.'],
    ],
    'examples' => [
        'Cutter for the name Ela, handwritten, 82 × 54 mm with flange, 18 mm high.',
        'Cutter for the number 5 for birthday cookies, 72 × 93 mm, tapered cutting edge.',
        'Cutter for the letters MAMA, serif typeface, 98 × 30 mm, sturdier straight edge.',
    ],
];
