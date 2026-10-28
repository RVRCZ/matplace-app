<?php

// The page for the query "lightbox sign 3d print" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed lightbox sign with an LED strip: logo or text',
    'heading' => '3D printed lightbox sign with an LED strip',
    'description' => 'Type a text or upload a logo and the tool designs a lightbox sign of four printed parts for a 5 V LED strip. We print the parts, or you download them.',
    'h1' => 'What the lightbox sign consists of, what to buy and what the parts cost',
    'intro' => [
        'The tool designs a lightbox sign of four printed parts: the body, the front mask with the motif cut out, the diffuser that spreads the light, and the back cover. The motif is a text of up to two lines of 16 characters, or an uploaded SVG or simple picture; narrow bridges hold the insides of the letters in the mask. The shape can be rectangular, or round with a flat foot so that it stands on a shelf. You set the width from 80 to 300 mm and the depth from 25 to 80 mm; the height of a rectangle follows the motif and the margin. You pick the light source first: an LED strip 8 or 10 mm wide, or an LED module with a controller; the tool keeps the minimum depth of the body accordingly and says how many metres of strip fit round the inside. We print only the plastic parts; the 5 V LED strip, a USB power supply and double-sided tape you buy separately.',
        'An example from the production calculator (28 October 2026, PLA): the rectangular sign OPEN 180 mm wide and 37 mm deep, all four parts side by side, weighs 97 g, prints in 2 h 17 min and costs 180 CZK (about 7 EUR) from us, without delivery (calculation /c/haqwdtiseafu). The body should be dark so that light passes only through the motif, the diffuser white or translucent; you pick the colour of every part when ordering, in one order, as a question below explains. Assembly is yours: stick the strip round the inside, pass the cable through the hole, put the diffuser and the mask in from the front. You download the parts free as STLs or a project for your printer.',
    ],
    'steps' => [
        ['name' => 'Type a text or upload a logo', 'text' => 'Type the text in one or two lines, or upload an SVG or a picture with a dark motif on a light background. Narrow bridges hold the insides of the letters in the mask.'],
        ['name' => 'Pick the shape and the light source', 'text' => 'Choose a rectangular or a round shape and the light source: an LED strip 8 mm or 10 mm wide, or an LED module with a controller. The tool keeps the minimum depth of the body by the source.'],
        ['name' => 'Set the size', 'text' => 'Enter the width and the depth. For the rectangular shape the height follows the motif and the margin around it.'],
        ['name' => 'Look at the parts', 'text' => 'The preview shows the assembly pulled apart by layers and can be switched to the single parts. Under it is the list of things that are not part of the print.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our farm. Or download the parts free as STL files or a finished project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Is the LED strip part of the print?', 'a' => 'No. We print four plastic parts. The LED strip or module, a USB cable with power and double-sided tape or glue you buy yourself; the tool lists what and how much under the preview.'],
        ['q' => 'Which LED strip suits the sign?', 'a' => 'A 5 V strip 8 or 10 mm wide, or an LED module or a strip with a controller up to 18 × 8 mm. The tool says the length of strip you need under the preview. The cable goes through a 3 to 10 mm hole in the body; for 5 V a USB charger is enough.'],
        ['q' => 'Which colours should the parts be printed in?', 'a' => 'The body, the front mask and the back cover should be dark, so that light passes only through the motif, and the diffuser white or translucent. From us you pick the colour of every part when ordering, in one order: dark for the body, the mask and the cover, white for the diffuser. At home print the diffuser from white PLA and the other parts from a dark one.'],
        ['q' => 'How do I assemble the sign?', 'a' => 'Stick the LED strip round the inside of the body and pass the cable through the hole. The diffuser and the front mask go in from the front onto the step in the body, the back cover goes on from behind. Nothing is screwed; a drop of glue secures the parts if you like.'],
        ['q' => 'How big can the sign be?', 'a' => 'The width is 80 to 300 mm and the height of a rectangular sign follows the motif, 300 mm at most. The round shape is nine tenths of the width high. Every part has to fit the print bed, which the calculation points out in the next step.'],
        ['q' => 'Can the sign hang on a wall or in a shop window?', 'a' => 'The body has a flat back, so you stick it on with double-sided tape or hang it on a hook by the back cover; the cable runs down or back. For a window in the sun choose PETG, PLA next to hot glass softens.'],
    ],
    'examples' => [
        'A rectangular lightbox sign OPEN, 180 × 64 mm, 37 mm deep, for an LED strip: 97 g, 2 h 17 min, 180 CZK.',
        'A round lightbox sign BAR with a flat foot, 160 × 144 mm, standing on a shelf.',
        'A two-line lightbox sign CAFÉ LUNA, 240 × 172 mm, for a counter or a shop window.',
    ],
];
