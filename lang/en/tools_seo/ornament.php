<?php

// The page for the query "3d printed christmas ornament" (docs/AP.md §6). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed Christmas ornament from your picture or name',
    'heading' => '3D printed Christmas ornament from a picture or a name',
    'description' => 'Upload a picture or type a name and get a Christmas ornament in filament colours with a ribbon eyelet. We print it, or you download the model free.',
    'h1' => 'How the ornament is made from a picture, how many colours it takes and what it costs',
    'intro' => [
        'The tool turns a picture into a flat Christmas ornament with an eyelet for a ribbon. You upload a PNG, JPG, WebP or SVG, pick a motif from our library (about 137 motifs, 8 of them in colour), or type a name in one of thirty typefaces instead of a picture. The background is removed by itself and the picture is reduced to 1 to 8 colours; each is matched to the nearest filament our farm really has in stock. The ornament follows the outline of the picture or is a circle or a star, 40 to 150 mm wide and 2 to 5 mm thick. The colours lie one on another, each 0.4 to 1.2 mm higher, so any printer prints the ornament with filament changes at the heights the downloadable project carries; our farm prints up to four colours in one go.',
        'An example from the production calculator (18 October 2026, PLA+): an 80 mm wide gingerbread man from the library weighs 10 g, prints in 18 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order; six pieces come to 160 CZK, that is 27 CZK per ornament, because the order preparation is paid once. The calculation counts one colour, the spools for the other colours you confirm at the order. The ornament prints flat, face up and without supports, weighs about ten grams and hangs even on a thin ribbon. The STL, a ZIP with one STL per colour and the slicer project are free to download, no sign-up.',
    ],
    'steps' => [
        ['name' => 'Upload a picture or type a name', 'text' => 'Upload a picture, pick one from the library, or use one you uploaded before. A name or a short text may have one or two lines, in one of thirty typefaces.'],
        ['name' => 'Pick the shape, the size and the eyelet', 'text' => 'The ornament follows the picture, or is a circle or a star. Set the width, the thickness and a margin around the picture of up to 3 mm. Move the eyelet with a 3 to 6 mm hole anywhere along the outline with the slider or by dragging; a button puts it back on top.'],
        ['name' => 'Adjust the colours', 'text' => 'Choose the number of colours and give each one a filament in the colour window. Change which colour lies on which, or merge two colours into one. The 3D preview shows the ornament in the filament colours with its size in millimetres.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price and print time show in the calculation after "Continue". Or download the STL, a ZIP with one STL per colour or the slicer project, free and without sign-up.'],
    ],
    'faq' => [
        ['q' => 'Which picture suits an ornament?', 'a' => 'Best a drawing with a few solid colours, a gingerbread man or a tree say. From a photo the tool picks the main colours; contrast, brightness and saturation are tuned with sliders. If the whole photo should show, switch the background removal off.'],
        ['q' => 'How big an ornament suits the tree?', 'a' => '60 to 90 mm for an ordinary tree, 40 to 50 mm for a small table-top one; an 80 mm ornament weighs about 10 g. The 3 to 6 mm eyelet takes a ribbon or a hook. Bigger ornaments up to 150 mm suit a door or a window.'],
        ['q' => 'Does a printer with one nozzle print the colours too?', 'a' => 'Yes. The colours lie one above the other, so the filament is only changed at the given height; the project for OrcaSlicer, Bambu Studio and PrusaSlicer carries those changes. The options "Colours flush with the surface" and "Rim in its own colour" need a printer that changes filament by itself (AMS, MMU, ACE).'],
        ['q' => 'How many colours do you print?', 'a' => 'Up to four in one print: the base and three colours above it. You confirm the spool for every colour at the order, the ones of the design are preselected. An ornament with more colours you download free and print on your own printer.'],
        ['q' => 'What material is the ornament printed from?', 'a' => 'We recommend PLA. An ornament of it is light and prints flat, face up and without supports. Choose PETG only if the ornament will hang somewhere warm.'],
        ['q' => 'What does the tool warn about?', 'a' => 'Lines thinner than the nozzle. Also that it joined separate pieces of the picture with a small bridge so they hold together, and that the picture has fewer distinct colours than you asked for.'],
    ],
    'examples' => [
        'An 80 mm wide gingerbread man: brown body, white icing and red buttons; 10 g, 18 minutes, 99 CZK.',
        'A decorated Christmas tree 90 mm wide in five colours.',
        'A red 70 mm bauble with a white stripe, without a margin.',
    ],
];
