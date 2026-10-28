<?php

// The page for the query "custom medal 3d print" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom medal to 3D print: gold, silver, bronze with a chain',
    'heading' => 'Custom medal with a chain to 3D print',
    'description' => 'A gold, silver or bronze medal for 1st, 2nd and 3rd place with a number or a name and a chain of 40 links. We print it, or you download the model free.',
    'h1' => 'How the medal is made, what you can change and what it costs with the chain',
    'intro' => [
        'The tool makes a medal for the winners of a children’s race, a tournament, a school contest or a kids’ day. With one click you choose 1st, 2nd or 3rd place: a gold, silver or bronze medal and chain with a red numeral and rim. The number, a name in two lines of 16 characters or your own picture can be changed, as can the colours and the size of the motif. The plate has seven shapes: a circle, a star, a hexagon, an octagon, a shield, a rosette with a scalloped edge and a heart, 40 to 120 mm wide, 60 mm by default. By default the motif lies flush with the plate and prints against the bed, so the face is smooth; the other option is a motif raised by 0.6 mm. Next to the medal prints the chain: 40 links by default with a round 4 mm section that snap into each other, about 88 cm in all.',
        'An example from the production calculator (22 October 2026, PLA+): a round gold 60 mm medal with a red flush numeral 1 and a chain of 40 links takes 195 × 177 mm on the bed, weighs 41 g, prints in 1 h 42 min and costs 130 CZK (about 5 EUR) from us without delivery (calculation /c/naze69mzmy0h). It is our most often ordered tool: 31 calculations and 9 paid orders in 90 days, mostly sets for races. A flush motif is printed by a printer with a spool changer on one bed, a raised one by any printer with one filament change; the slicer project and the STL are free to download, no sign-up.',
    ],
    'steps' => [
        ['name' => 'Choose the place', 'text' => '1st, 2nd or 3rd place: a gold, silver or bronze medal and chain, a red numeral and rim. You can still change the number, the name, the picture and the colours.'],
        ['name' => 'Pick the motif, the shape and the size', 'text' => 'A flush or a raised motif; the size of the motif as a share of the diameter; a rim or none. The shape a circle, a star, a hexagon, an octagon, a shield, a rosette or a heart, 40 to 120 mm wide.'],
        ['name' => 'Set the length of the chain', 'text' => 'The number of links from 0 to 80, 40 by default. Under the preview you read how many links fit the bed and how long a chain they make.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price shows in the calculation after "Continue". The model and the slicer project are free to download, no sign-up.'],
    ],
    'faq' => [
        ['q' => 'What is the difference between a flush and a raised motif?', 'a' => 'A flush motif lies in the bottom three layers (0.6 mm) next to the plate and prints against the bed: the face of the medal is smooth and the motif is level with the plate. The printer alternates two spools in the same layers, so we print it on one bed on a printer with a changer. A raised motif stands 0.6 mm above the plate and the printer changes the filament once at the height where the motif begins.'],
        ['q' => 'Which colours do the places have?', 'a' => '1st place gold PLA Silk, 2nd place silver PLA Silk, 3rd place bronze (metallic when we have it loaded, otherwise copper PLA Silk). The medal and the chain are in the metal colour, the numeral and the rim red. At the order you pick from the spools in the printers right now.'],
        ['q' => 'How long a chain do I need?', 'a' => 'For a child 60 to 70 cm, that is 28 to 32 links; for an adult 80 to 90 cm, that is 36 to 40 links. The chain goes over the head, it has no clasp.'],
        ['q' => 'How do the links join?', 'a' => 'Every link is a 30 × 18 mm oval with a round 4 mm section and a gap in the middle of the long side. The gap is just under half a millimetre narrower than the link, so the neighbour snaps into it. At the bottom the link has a narrow flat it lies on, and the rounding starts at 45°, so it prints without supports.'],
        ['q' => 'Does it fit one bed?', 'a' => 'Yes. A 60 mm medal with forty links takes 195 × 177 mm, and even a 120 mm medal with forty links fits a 220 × 220 mm bed. If you want more links than fit, the tool prints as many as fit and says so.'],
        ['q' => 'Is the chain safe for small children?', 'a' => 'It is hard plastic and does not open by itself when pulled, so it does not belong round the neck of a child under three or on climbing frames without supervision. For the smallest put the medal on a ribbon: set the number of links to zero.'],
    ],
    'examples' => [
        '1st place: a round gold 60 mm medal with a red numeral 1 and a rim flush with the plate and a chain of 40 links: 41 g, 1 h 42 min, 130 CZK.',
        '2nd place: a silver 60 mm shield with a red 2 and 40 links.',
        '3rd place: a bronze rosette with a scalloped edge, 60 mm, a red 3 and 40 links.',
    ],
];
