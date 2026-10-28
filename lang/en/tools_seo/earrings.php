<?php

// The page for the query "3d printed earrings generator" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed earrings generator: a pair from your picture',
    'heading' => '3D printed earrings generator: a pair from your own picture or text',
    'description' => 'A picture, a library motif or a short text becomes a pair of earrings, identical or mirrored, in filament colours. We print them, or download free.',
    'h1' => 'How the pair is made, what it weighs and what it costs',
    'intro' => [
        'The tool makes a pair of earrings from one picture. Both are identical, or the second one is mirrored so that the motif faces the same way on both ears. Upload a PNG, JPG, WebP or SVG, pick a motif from the library, or type a short text of up to 12 characters per line. One earring is 10 to 60 mm wide and 1.6 to 3 mm thick; it follows the outline of the picture, or is a circle, with a frame of up to 2 mm round the picture. The eyelet, with a hole of 1.5 to 3 mm, goes anywhere on the outline, by a slider or by dragging it in the preview. The picture is reduced to 1 to 8 colours of filaments we have in stock; the colours lie one on another, each 0.2 to 0.8 mm higher. Hooks and jump rings are not included; you can buy them at a craft shop.',
        'An example from the production calculator (28 October 2026, PLA): a pair of red hearts from the library, each 28 mm wide and 2.4 mm thick, weighs 2.2 g together, prints in 6 minutes and costs 99 CZK (about 4 EUR) from us, the lowest order price, without delivery (calculation /c/5xbjn5tfg5gn). One earring weighs about a gram, so it does not pull. Several pairs of the same design go in as the quantity and the price per pair falls. Our farm prints up to four colours in one print, the base and three more; you confirm the spool of each colour when ordering. The STL and the slicer project with the colour changes download free, without registration.',
    ],
    'steps' => [
        ['name' => 'Upload a picture or type a text', 'text' => 'Upload a picture, pick one from the library, or reuse one you uploaded earlier. The background is removed automatically; a slider sets how much of it goes.'],
        ['name' => 'Choose the pair and the size', 'text' => 'Pick identical or mirrored earrings, and a shape that follows the picture or a circle. Set the width of one earring, the thickness and a frame of up to 2 mm round the picture.'],
        ['name' => 'Place the eyelet, pick colours', 'text' => 'Move the eyelet by a slider or by dragging it in the preview. In the colour window you can give each colour another filament, change the order of the colours or join two of them.'],
        ['name' => 'Order the print or download', 'text' => 'The preview shows both earrings in the filament colours and the size in millimetres. The exact price and print time come one step further, in the calculation. The STL and the slicer project download free, without registration.'],
    ],
    'faq' => [
        ['q' => 'Are the hooks included?', 'a' => 'No. You get only the plastic parts with an eyelet. Hooks, jump rings or studs are sold at craft shops and you thread them through the eyelet yourself; a 4 to 5 mm jump ring suits a 2 mm eyelet hole.'],
        ['q' => 'What is a mirrored pair for?', 'a' => 'The right earring is flipped, so the motif faces the same way on both ears. For a symmetrical motif such as a heart, an identical pair will do; do not mirror a text, it would read backwards.'],
        ['q' => 'How much does an earring weigh, and does it last?', 'a' => 'PLA is light: an earring 32 mm wide and 2.4 mm thick weighs about 1 to 2 grams, so it does not pull on the ear. The eyelet is joined to the body; at 2.4 mm thickness and a 1.6 mm eyelet wall it takes everyday wear, and the tool warns about thin motifs under 0.8 mm.'],
        ['q' => 'Which picture comes out well?', 'a' => 'A simple silhouette or a motif with 2 to 4 colours: a heart, a star, a leaf, a letter. At 30 mm the fine details of a photo merge, so portraits are better off on a magnet or a coaster. A short name or initials can be typed instead of a picture.'],
        ['q' => 'What are the earrings printed from, and how do I print at home?', 'a' => 'We recommend PLA; both earrings print together, flat, face up and without supports, 0.2 mm layers or finer. The project for OrcaSlicer, Bambu Studio and PrusaSlicer holds the filament changes at the heights of the colours. Colours flush with the surface need a printer that changes filament by itself (AMS, MMU, ACE).'],
        ['q' => 'How many colours do you print?', 'a' => 'Up to four in one print: the base and three colours on top of it. You confirm the spool for each colour when ordering; the ones from the design are preselected. A design with more colours you download and print on your own printer.'],
    ],
    'examples' => [
        'A pair of red hearts, each 28 mm wide: 2.2 g, 6 minutes, 99 CZK.',
        'A mirrored pair of ghosts, each 32 mm wide, the eyelet at the tip.',
        'Round earrings, 30 mm, with a smiling star, three colours.',
    ],
];
