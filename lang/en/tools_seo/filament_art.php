<?php

// The page for the query "filament painting generator free" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Filament art generator: a picture or photo in filament, free',
    'heading' => 'Filament painting generator: a picture or a photo as layers of coloured filament',
    'description' => 'Upload a picture or a photo, get it in 1 to 8 filament colours: layered plates in a frame, or one print with stacked colours. Print, or download free.',
    'h1' => 'Two ways to make the picture, and what it costs',
    'intro' => [
        'The tool reduces a picture or a photo to 1 to 8 colours of filaments we really have in stock on our print farm and builds a picture for the wall of them, 50 to 250 mm wide. By default it is a layered picture: every colour is a plate of its own, 1.5 to 3 mm thick, and the plates stack one behind another like a paper cut; the back one carries the whole silhouette, the front one only its colour. Spacer posts of 2 to 5 mm between the plates give the picture depth and shadow; for a thin picture the plates lie flat. Add a round or square frame with a slot for a nail and, if you like, room for an LED strip. The second way is one print: a base plate with the colours 0.4 mm one above another, one filament per layer, so any printer prints it by swapping the filament at the heights the project names. The background of a photo goes by itself; for a photo over the whole area you switch that off. For every colour you see the spool the tool chose and can swap it for another from the catalogue, reorder the colours or merge two.',
        'An example from the production calculator (28 October 2026, PLA): the snowman from the library as a layered picture of four plates in a round 120 mm frame weighs 105.6 g, prints in 2 h 46 min and costs 200 CZK (about 8 EUR) from us without delivery (calculation /c/tmwh0ok9ixpv); you pick the colour of every plate and of the frame when ordering, in one order. The gingerbread man as one print 120 mm wide, a base and three colours stacked, weighs 15.9 g, prints in 26 minutes and costs 99 CZK (calculation /c/etcdmot38mpy). Next to the preview is the guide: the plates in order from the back to the front, each one drawn. Download every plate as its own STL, or the whole design as a project for your slicer, free and without registration.',
    ],
    'steps' => [
        ['name' => 'Upload a picture', 'text' => 'Upload a picture, pick one from the library, or reuse one you uploaded earlier. The background goes by itself; for a photo that is to fill the whole area, switch the removal off.'],
        ['name' => 'Choose how it is made', 'text' => 'A layered picture of plates with a frame, or one print with the colours one above another. Set the width, the thickness of the plates and the gap between them, the frame and room for an LED strip if you want it.'],
        ['name' => 'Fine-tune the colours', 'text' => 'Choose how many colours and give each one a filament from our catalogue. Colours can be moved forward or back, or two merged into one; for a photo switch the reading to "painting".'],
        ['name' => 'Order the print, or download', 'text' => 'Next to the preview you see the size; the calculation after "Continue" shows the exact price. Download every plate as its own STL, or the whole design as a project for your slicer, free and without registration.'],
    ],
    'faq' => [
        ['q' => 'How is a layered picture assembled?', 'a' => 'Print every plate in its colour, then glue them by the guide from the back to the front. The spacer posts are part of the plate and go under the plate in front of them. Superglue is enough. The frame prints bottom down and the plates are glued inside it.'],
        ['q' => 'What if I only have a single-colour printer?', 'a' => 'The layered picture is made for it: every plate is a single-colour print. One print with the colours as steps works too; the project holds filament changes at the right heights, the printer stops and you swap the filament. A printer with AMS, MMU or ACE swaps the spools itself.'],
        ['q' => 'Which pictures come out best?', 'a' => 'Drawings and logos with a few solid colours. Of a photo the tool in "painting" mode takes the main colours and simplifies the areas to millimetres, so the result is poster-like; for a greyscale portrait a lithophane is the better choice. Lines thinner than 0.8 mm would break as a plate of their own, so the tool gives them to the colour behind them and says so.'],
        ['q' => 'What frame, and how does it hang?', 'a' => 'Round or square, 6 to 20 mm wide, with a slot for a nail in the back. With "Room for an LED strip" the plates stand 10 mm further from the back and there is a slot for the cable at the bottom; the strip is yours to buy.'],
        ['q' => 'How does it differ from HueForge and from a lithophane?', 'a' => 'HueForge blends shades through the translucency of thin layers and needs filament changes set precisely in the slicer; our one print stacks solid colours, so it looks the same on every printer and you see the result beforehand. A lithophane has one colour and the picture comes from thickness against light; filament art is in colour without any light.'],
        ['q' => 'What is the picture printed from, and in which colours?', 'a' => 'From PLA, which has the widest choice of colours and prints flat without supports. The colours are spools we really have in stock, so the print looks like the preview. For a print from us you pick the colour of every plate and of the frame when ordering, in one order.'],
    ],
    'examples' => [
        'A snowman as a layered picture of four plates in a round 120 mm frame: 105.6 g, 2 h 46 min, 200 CZK.',
        'A smaller picture of 100 mm from three plates without a frame, flat: 25 g, 46 minutes, 99 CZK, the lowest order price.',
        'A gingerbread man as one print 120 mm wide: a base and three colours 0.4 mm one above another, 15.9 g, 26 minutes, 99 CZK.',
    ],
];
