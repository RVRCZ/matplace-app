<?php

// The page for the query "custom charm 3d print" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom charm from a picture or name, 3D printed, free STL',
    'heading' => 'Custom charm from your own picture or name, 3D printed: for keys, bags and backpacks',
    'description' => 'A picture or a name becomes a charm in filament colours with an eyelet anywhere on the outline. For keys, a bag or a gift tag. Print or download free.',
    'h1' => 'How the charm is made, where the eyelet goes and what it costs',
    'intro' => [
        'The tool turns a picture or a name into a charm for keys, a bag, a backpack, a zip pull or a gift tag. Upload a PNG, JPG, WebP or SVG, pick a motif from the library, or type a name in one of thirty typefaces; loose pieces of a typeface are tied with a bridge so that the charm holds together. The background disappears by itself and the picture is reduced to 1 to 8 colours of filaments we have in stock on the farm. The charm follows the outline of the picture, or is a circle or a rectangle, 20 to 120 mm wide and 2 to 6 mm thick, with a frame of up to 3 mm round the picture. The eyelet moves anywhere along the outline by a slider or by dragging it in the preview, with a hole of 2 to 6 mm and a wall of 1.5 to 3 mm. The colours lie one on another, each 0.6 mm higher unless you change it.',
        'An example from the production calculator (28 October 2026, PLA): the happy ghost from the library, 45 mm wide and 3 mm thick, weighs 3 g, prints in 7 minutes and costs 99 CZK (about 4 EUR) from us, the lowest order price, without delivery (calculation /c/mca5ddgfzctt); the name Ela in a script, 60 mm wide, weighs 3.7 g and prints in 12 minutes (calculation /c/gmfsgcpwmjl8). Several pieces of the same design go in as the quantity and the price per piece falls; charms with different names are different designs. Our farm prints up to four colours in one print, the base and three more; you confirm the spool of each colour when ordering. The STL, a ZIP with one STL per colour and the slicer project download free, without registration.',
    ],
    'steps' => [
        ['name' => 'Upload a picture or type a name', 'text' => 'Upload a picture, pick one from the library, or reuse one you uploaded earlier. Instead of a picture you can type a name or a short text on one or two lines.'],
        ['name' => 'Tune the outline and colours', 'text' => 'A slider from 0 to 100 sets how much of the background goes; edges can be smoothed by up to 1 mm. Choose the number of colours, give each a filament in the colour window and, if you like, join two colours into one.'],
        ['name' => 'Set the size and the eyelet', 'text' => 'Enter the width, the thickness and a frame of up to 3 mm round the picture, with a bevelled edge if you want one. Grab the eyelet in the preview and drag it to the point the charm should hang from.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the size; the calculation after "Continue" shows the exact price and print time. You can also download the model as an STL, a ZIP with one STL per colour or a slicer project, free and without registration.'],
    ],
    'faq' => [
        ['q' => 'Can I use just a name instead of a picture?', 'a' => 'Yes. Type one or two lines and pick one of thirty typefaces, from bold sans serifs to handwriting. The tool ties loose pieces together with a small bridge so that the charm holds together, and tells you so.'],
        ['q' => 'Where can the eyelet go, and what goes through it?', 'a' => 'Anywhere on the outline: by a slider, or by dragging it in the 3D preview. The hole is 2 to 6 mm, 3 mm by default, and the ring wall 1.5 to 3 mm. A common key ring and a backpack carabiner pass through a 3 mm hole; neither is part of the print.'],
        ['q' => 'How does it differ from the keychain?', 'a' => 'The keychain is a plate with a name and a shape from a list; the charm follows the outline of your picture or lettering and has its eyelet anywhere. For a gift tag or a zip pull take the charm, for keys with a name on a plate the keychain.'],
        ['q' => 'How are the colours printed?', 'a' => 'The colours lie one on another, each 0.4 to 1.2 mm higher. Every layer is therefore a single filament, which you swap at the right height; the project for OrcaSlicer, Bambu Studio and PrusaSlicer contains the swaps. Colours flush with the surface and a rim in its own colour need a printer that changes filament by itself (AMS, MMU, ACE).'],
        ['q' => 'How many colours does the farm print?', 'a' => 'Up to four in one print: the base and three colours on top of it. You confirm the spool for each colour when ordering; the ones from the design are preselected. A design with more colours can be downloaded free for your own printer.'],
        ['q' => 'What is the charm printed from, and does it last on keys?', 'a' => 'We recommend PLA; it prints flat, face up and without supports. A thickness of 3 mm and a 2 mm eyelet wall take keys in a pocket, and the tool warns about thin motifs under 0.8 mm. For a charm that will get warm, for example in a car in the sun, PETG is the better choice.'],
    ],
    'examples' => [
        'Happy ghost 45 mm wide, white with dark eyes and pink cheeks: 3 g, 7 minutes, 99 CZK.',
        'Smiling yellow star 50 mm wide, the eyelet on a point.',
        'The name Ela in handwritten letters, 60 mm wide, joined into one piece: 3.7 g, 12 minutes.',
    ],
];
