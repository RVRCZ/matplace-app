<?php

return [
    'title' => 'Christmas ornament from your own picture or name',
    'description' => 'Upload a picture or type a name and get a Christmas ornament in filament colours with an eyelet for a ribbon. We print it, or you download it free.',
    'h1' => 'A Christmas tree ornament from a picture, a photo or a name',
    'intro' => [
        'The tool turns a picture into a flat Christmas ornament with an eyelet for a ribbon. Upload a PNG, JPG, WebP or SVG, pick a motif from our library, or type a name instead of a picture. The background is removed automatically and the picture is reduced to 1 to 8 colours. Each colour is matched to the nearest filament we really have in stock on our print farm. The ornament follows the outline of the picture, or is a circle or a star, 40 to 150 mm wide and 2 to 5 mm thick.',
        'The colours lie one on another, each 0.4 to 1.2 mm higher than the one below. Every layer is therefore a single filament and any printer can print the ornament: just swap the filament at the heights the downloadable project contains. Our print farm prints up to four colours in one print: the base and up to three more. You confirm the spool for each colour when ordering; designs with more colours can be downloaded free for your own printer.',
    ],
    'steps' => [
        ['name' => 'Upload a picture or type a name', 'text' => 'Upload a picture, pick one from the library of about 137 motifs (8 of them in colour), or reuse one you uploaded earlier. A name or a short text can have one or two lines, in one of four typefaces.'],
        ['name' => 'Pick the shape, size and eyelet', 'text' => 'The ornament follows the picture, or is a circle or a star. Set the width, the thickness and a frame of up to 3 mm round the picture. The eyelet, with a hole of 3 to 6 mm, moves anywhere along the outline by a slider or by dragging it in the preview; a button puts it back at the top.'],
        ['name' => 'Adjust the colours', 'text' => 'Choose the number of colours and give any of them another filament in the colour window. You can change which colour lies on which, or join two colours into one. The 3D preview shows the ornament in the filament colours and its size in millimetres.'],
        ['name' => 'Order the print or download', 'text' => 'A rough price is shown next to the preview, the exact price and print time one step further. You pay for the print from prepaid credit. Or download an STL, a ZIP with one STL per colour or a slicer project, free and without registration.'],
    ],
    'faq' => [
        ['q' => 'What kind of picture suits an ornament?', 'a' => 'A drawing with a few solid colours works best, such as a gingerbread man or a tree. From a photo the tool picks the main colours; sliders adjust the contrast, brightness and saturation. To keep the whole photo, switch off the background removal.'],
        ['q' => 'Can a printer with one nozzle print a coloured ornament?', 'a' => 'Yes. The colours lie one on another, so the filament is simply swapped at a given height; the project for OrcaSlicer, Bambu Studio and PrusaSlicer contains those swaps. The options "Colours flush with the surface" and "Rim in its own colour" need a printer that changes filament by itself (AMS, MMU, ACE).'],
        ['q' => 'How many colours do you print?', 'a' => 'Up to four in one print: the base and up to three colours on top of it. You confirm the spool for each colour when ordering; the ones from the design are preselected. An ornament with more colours can be downloaded free and printed on your own printer.'],
        ['q' => 'What material is the ornament printed from?', 'a' => 'We recommend PLA. A PLA ornament is light and prints flat, face up and without supports. Choose PETG only if the ornament will hang somewhere warm.'],
        ['q' => 'What does the tool warn about?', 'a' => 'About lines thinner than the nozzle. It also tells you when it tied loose pieces of the picture together with a small bridge so that they hold, and when the picture has fewer distinct colours than you asked for.'],
        ['q' => 'How do I pay and how do I get the ornament?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print by Packeta to a pickup point or to your door across the EU.'],
    ],
    'examples' => [
        'Gingerbread man 80 mm wide: brown base, white icing and red buttons.',
        'Decorated Christmas tree 90 mm wide in five colours.',
        'Red Christmas ball, 70 mm, with a white band and no frame.',
    ],
];
