<?php

// The page for the query "custom coaster 3d print generator" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom coaster generator for 3D printing: photo or drawing',
    'heading' => 'Custom coaster generator for 3D printing: your photo or drawing in filament colours',
    'description' => 'A round, square or hexagonal coaster with a photo, drawing or name in filament colours, with grooves so it does not slide. We print it, or download free.',
    'h1' => 'How the coaster is made, how many colours it takes and what it costs',
    'intro' => [
        'The tool makes a coaster with your picture: a circle, a square or a hexagon 80 to 120 mm wide and 3 to 6 mm thick. Upload a PNG, JPG, WebP or SVG, pick a motif from the library, or type a name. A whole photo fills the coaster up to the frame, a cut-out motif sits in the middle. The picture is reduced to 1 to 8 colours of filaments we have in stock on the farm, and the colours rise above the base in steps, 0.4 mm each by default, so a glass stands without trouble. Add grooves underneath so that the coaster does not slide on a wet table, a frame of up to 4 mm round the picture or a bevelled edge. A fully flat top comes with "Colours flush with the surface", which needs a printer that swaps filament.',
        'An example from the production calculator (14 November 2026, PLA): a square 100 mm coaster with the Matplace logo, 4 mm thick, with grooves underneath, weighs 26 g, prints in 42 minutes and its print with us comes to 80 CZK. A set of four round coasters with the snowman from the library cost 170 CZK on 28 October 2026 (calculation /c/57k7ndo2ibxo), because the setup is paid once. Our farm prints up to four colours in one print, the base and three more; you confirm the spool of each colour when ordering. The model and the slicer project with the colour changes download free, without registration.',
    ],
    'steps' => [
        ['name' => 'Upload a photo or a drawing', 'text' => 'Upload a picture, pick one from the library, or reuse one you uploaded earlier. For a whole photo switch the background removal off; if you leave it on, only the motif in the middle stays.'],
        ['name' => 'Pick the shape and size', 'text' => 'A circle, a square or a hexagon. Set the width, the thickness and a frame of up to 4 mm round the picture, plus a bevelled edge and grooves underneath if you want them.'],
        ['name' => 'Tune the colours', 'text' => 'Choose the number of colours and give each a filament in the colour window. Set how much higher each colour is (0.2 to 0.8 mm); you can also join two colours into one.'],
        ['name' => 'Order the print or download', 'text' => 'The size is shown next to the preview; the calculation after "Continue" shows the exact price and print time. For a set, just set the quantity. The model and the slicer project download free, without registration.'],
    ],
    'faq' => [
        ['q' => 'Will a glass stand level on the coaster?', 'a' => 'Yes. By default the colours are stepped by 0.4 mm and a glass stands on them without trouble. For a fully flat top use the option "Colours flush with the surface", which only a printer that changes filament by itself (AMS, MMU, ACE) can print.'],
        ['q' => 'What is the coaster printed from, and does it take a hot mug?', 'a' => 'We recommend PLA: it is fine for cold and warm drinks but softens at about 55 °C. For a mug straight from the stove choose PETG. The coaster prints flat and without supports.'],
        ['q' => 'Which photo comes out well on a coaster?', 'a' => 'A drawing, a logo or a photo with a few solid colours works best; a portrait reduced to 4 to 8 colours comes out poster-like. For a whole photo switch the background removal off, otherwise only the motif in the middle stays. Details under 0.8 mm merge.'],
        ['q' => 'What are the grooves underneath for?', 'a' => 'They keep the coaster from sliding on a wet table, and a drop under it cannot form a suction cup. The grooves are optional and add no print time.'],
        ['q' => 'How do I order a set of coasters?', 'a' => 'A set is the same coaster in several pieces: just set the quantity, and the price per piece falls because the setup is paid once. Coasters with different motifs are different designs, each ordered on its own.'],
        ['q' => 'How many colours do you print, and how do I print at home?', 'a' => 'From us up to four in one print: the base and three colours on top of it; you confirm the spool of each when ordering. At home any printer prints a design with more colours: the colours lie one on another and the project for OrcaSlicer, Bambu Studio and PrusaSlicer holds the filament changes at the right heights.'],
    ],
    'examples' => [
        'Square coaster 100 mm wide with the Matplace logo, a black rim and grooves underneath: 26 g, 42 minutes, 80 CZK.',
        'Hexagonal coaster, 95 mm, with a paw, a 2 mm frame, four colours.',
        'Square coaster, 100 mm, with a photo over the whole face, 6 colours, bevelled edge.',
    ],
];
