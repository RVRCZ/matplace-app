<?php

// The page for the query "custom fridge magnet 3d print" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom fridge magnet from a photo or picture, 3D printed',
    'heading' => 'Custom fridge magnet from a photo or a picture, 3D printed',
    'description' => 'A photo, a picture or a name becomes a fridge magnet in filament colours with a pocket made for your magnet. We print it, or you download the model free.',
    'h1' => 'How the magnet is made, which magnet goes in it and what it costs',
    'intro' => [
        'The tool turns a picture into a fridge magnet. Upload a PNG, JPG, WebP or SVG, pick a motif from the library, or type a name. The picture is reduced to 1 to 8 colours of filaments we have in stock on the farm; the background of a photo goes by itself and the magnet then follows the outline of the motif, or it is a circle or a rectangle with the whole photo. Width 40 to 100 mm, thickness 3 to 6 mm, a frame of up to 3 mm round the picture, a bevelled edge if you like. The back has a pocket for a magnet in the middle: pick a common disc magnet from Ø 6 × 2 to Ø 20 × 3 mm, or enter your own size, and glue it in, press it in, or leave a hole right through; for self-adhesive magnetic sheet choose the version with no pocket. If the magnet is tall for the chosen thickness, the tool thickens the base so that four layers of plastic stay over it. Magnets are not included.',
        'An example from the production calculator (28 October 2026, PLA): the paw from the library in a 60 mm circle, 3 mm thick, with a pocket for a Ø 10 × 2 mm magnet, weighs 7 g, prints in 14 minutes and costs 99 CZK (about 4 EUR) from us, the lowest order price, without delivery (calculation /c/gob4imtsfmor). Several pieces of the same design go in as the quantity and the price per piece falls; magnets from the photos of the whole family are different designs. Our farm prints up to four colours in one print, the base and three more; you confirm the spool of each colour when ordering. The STL, a ZIP with one STL per colour and the slicer projects download free, without registration.',
    ],
    'steps' => [
        ['name' => 'Upload a picture or a photo', 'text' => 'The background is removed automatically and the shape then follows the outline of the motif. To keep the whole photo, switch the background removal off. Sliders adjust the contrast, brightness and saturation of a photo.'],
        ['name' => 'Choose the shape and size', 'text' => 'The shape follows the picture, or is a circle or a rectangle. Set the width, the thickness, a frame of up to 3 mm round the picture and, if you like, a bevelled edge.'],
        ['name' => 'Pick the magnet and its mount', 'text' => 'Choose the magnet size and how it is held: a pocket for gluing with a clearance of 0.1 to 0.3 mm, a press-fit pocket, a hole right through, or no pocket for magnetic sheet.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the size; the calculation after "Continue" shows the exact price and print time. The STL, a ZIP with one STL per colour and the slicer projects download free, without registration.'],
    ],
    'faq' => [
        ['q' => 'Which magnets fit the pocket?', 'a' => 'There are presets for the common disc magnets Ø 6 × 2, 8 × 3, 10 × 2, 12 × 3, 15 × 3 and 20 × 3 mm. Your own size can be 4 to 30 mm in diameter and 1 to 6 mm high. Magnets are not part of the print; a neodymium Ø 10 × 2 mm is enough for a 60 mm piece.'],
        ['q' => 'How does the magnet stay in?', 'a' => 'In a pocket for gluing you glue it in, for example with superglue; the clearance round the magnet can be set from 0.1 to 0.3 mm. Into a press-fit pocket you push the magnet. A hole right through passes through the whole piece, so the magnet may show from the front.'],
        ['q' => 'Which photo comes out well?', 'a' => 'A drawing, a logo or a photo with a clear motif on a calm background; the tool removes the background and the magnet follows the outline. For a portrait keep a circle or a rectangle with the whole photo and 6 to 8 colours, it comes out poster-like. Details under 0.8 mm merge.'],
        ['q' => 'What if the magnet is too high for the chosen thickness?', 'a' => 'The tool thickens the base by itself so that four layers of plastic stay over the magnet, and shows the new thickness. If the magnet does not fit inside the shape, it tells you to increase the width or choose a smaller magnet.'],
        ['q' => 'What is it printed from, and how do I print it at home?', 'a' => 'We recommend PLA. It prints face up, with the pocket down and without supports, 0.2 mm layers; the pocket is in the base, so the printer bridges a few millimetres over it. For places where it gets warm, choose PETG.'],
        ['q' => 'Do you print the magnet in colour?', 'a' => 'Yes, in up to four colours in one print: the base and three colours on top of it. You confirm the spool for each colour when ordering; the ones from the design are preselected. A design with more colours downloads free; the colours lie one on another, so any printer prints it by swapping filament at the heights the project contains.'],
    ],
    'examples' => [
        'Paw in a circle 60 mm wide, with a pocket at the back for a Ø 10 × 2 mm magnet: 7 g, 14 minutes, 99 CZK.',
        'Red heart 50 mm wide with a press-fit Ø 8 × 3 mm magnet.',
        'A photo on a rectangle 80 mm wide, 6 colours, a hole right through for a Ø 12 × 3 mm magnet.',
    ],
];
