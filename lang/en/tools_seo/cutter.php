<?php

// The page for the query "custom cookie cutter generator" (docs/AP.md §6). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom cookie cutter generator: your name or drawing, free',
    'heading' => 'Custom cookie cutter from a name or a drawing',
    'description' => 'Type a name or a number, or upload a drawing of a shape, and get a cookie cutter with a flange to press on. We print it, or you download the model free.',
    'h1' => 'How the cutter is made, from which shape, what it is printed from and what it costs',
    'intro' => [
        'The tool makes a cookie cutter from a text or a picture. You type a name, a letter or a number in two lines of 20 characters in one of thirty typefaces, or upload a drawing of a shape; even an outline drawn with a pencil and photographed will do. A thin wall follows the outline with a flange to press on: the shape 30 to 150 mm wide, the wall 10 to 30 mm high and 0.8 to 1.6 mm thick, the flange 3 to 10 mm, the cutting edge tapered or straight. When the uploaded picture has lines inside the outline, the veins of a leaf or eyes and a smile say, they become a separate stamp you press into the dough after cutting. The outline is mirrored, so turned flange up it cuts the shape the right way round; the closed inside of a letter, an O say, gets its own wall joined by flat bridges.',
        'An example from the production calculator (18 October 2026, PLA+): a cutter of the name Ela in a script typeface, 82 × 54 mm including the flange and 18 mm high, weighs 13 g, prints in 31 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order; a cutter of the number 5 for birthday biscuits, 72 × 93 mm, weighs 12 g, prints in 25 minutes and costs 99 CZK too. It prints flange down, without supports, from PLA or the sturdier PETG; wash it by hand in lukewarm water, not in the dishwasher. You download the model free as an STL or a project for your own printer, or have it printed by us.',
    ],
    'steps' => [
        ['name' => 'Type a text or upload a shape', 'text' => 'The text may have two lines of 20 characters, in one of thirty typefaces. Instead of a text you can upload an SVG or a picture of a shape.'],
        ['name' => 'Set the size and the edge', 'text' => 'Enter the width of the shape, the height and thickness of the wall and the width of the flange. A tapered edge cuts sharper, a straight one is sturdier.'],
        ['name' => 'Check the preview', 'text' => 'The preview shows the cutter turned the way it is used, and next to it the stamp if one was made. The closed inside of a letter, an O say, gets its own wall joined to the rest by flat bridges.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price and print time show in the calculation after "Continue". Or download the model free as an STL or a finished project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'What picture does the cutter come from?', 'a' => 'A simple dark drawing on a light background, or an SVG with filled shapes. Everything the drawing encloses counts as the shape and the largest connected area is used. A photo does not suit; trace a child’s drawing with a marker and photograph it from above.'],
        ['q' => 'When is a stamp made too?', 'a' => 'Only from an uploaded PNG, JPG or WebP picture with enough lines inside the outline, and when the option "Stamp from the drawing inside" stays ticked. A text or an SVG gives no stamp.'],
        ['q' => 'How big a cutter should I choose?', 'a' => '50 to 70 mm for ordinary biscuits, 100 to 150 mm for a big gingerbread or a name, 30 mm at least. A wall 18 mm high is enough for shortcrust and gingerbread dough; for a thick dough choose 25 mm.'],
        ['q' => 'What is the cutter printed from and how do I wash it?', 'a' => 'We recommend ordinary PLA or the sturdier PETG. Wash it by hand in lukewarm water, never in the dishwasher: PLA softens at around 55 °C.'],
        ['q' => 'Is the print suitable for contact with food?', 'a' => 'The cutter is meant for a brief touch with raw dough that is then baked. For lasting contact with food we do not recommend printed parts without a liner.'],
        ['q' => 'How fine can the shape be?', 'a' => 'Protrusions narrower than twice the wall thickness are left out of the shape, because dough would get stuck in them. Make small text bigger, or choose a shorter word and a bolder typeface.'],
    ],
    'examples' => [
        'A cutter of the name Ela in a script typeface, 82 × 54 mm including the flange, 18 mm high: 13 g, 31 minutes, 99 CZK.',
        'A cutter of the number 5 for birthday biscuits, 72 × 93 mm, tapered cutting edge: 12 g, 25 minutes, 99 CZK.',
        'A cutter of the letters MAMA in a serif typeface, 98 × 30 mm, straight sturdier edge.',
    ],
];
