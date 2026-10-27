<?php

// The page for the query "3d printed qr code generator" (docs/AP.md §1, §5). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed QR code generator: a sign with a stand, free STL',
    'heading' => '3D printed QR code: a sign with a stand',
    'description' => 'Paste a link, a text or your Wi‑Fi and the tool makes a sign with a QR code a phone reads, and reads it back to check. Free STL, or a two-colour print.',
    'h1' => 'How the QR sign is made, why it needs two colours and how big it should be',
    'intro' => [
        'The tool makes a plate with a raised QR code. The code can open a website, a menu, your reviews or a payment link, or connect a phone to your Wi‑Fi: you paste a link or a text of 4 to 300 characters, for Wi‑Fi the network name, the password and the security, and the tool composes the technical notation itself. Under the code goes a caption of up to 40 characters, and the sign can get a table stand it slides into at a slight tilt, or a hole for hanging. You set the code size from 30 to 150 mm. The tool watches readability: one module of the code must be at least 0.9 mm, otherwise it asks you to enlarge the code or shorten the link, and it reads the finished design back the way a phone does. A code reads only in two colours: a light plate, a dark code.',
        'An example from the production calculator (18 October 2026, PLA+): a sign with a web link, 70 × 83 × 3 mm, one module about 2 mm, weighs 14 g, prints in 25 minutes and costs 99 CZK (about 4 EUR) from us (calculation /c/lomf0wzlshw4); a 90 mm Wi‑Fi sign with a table stand weighs 35 g, prints in 1 h 1 min and costs 110 CZK, both in one colour and without delivery. For a print from us you pick the second colour at the order and the printer changes it by itself at the height of the plate; the project you download for your own printer already carries the filament change, and the STL is one body. Try the link from the preview with your phone before printing; a printed code cannot be changed.',
    ],
    'steps' => [
        ['name' => 'Paste a link, a text, or your Wi‑Fi', 'text' => 'In "Link or text" paste the address or any text; for Wi‑Fi the network name, the password and the security. In "Caption under the code" a short label, the network name say; it may stay empty.'],
        ['name' => 'Set the size', 'text' => 'Enter the size of the code including its quiet margin, 30 to 150 mm. Tick a table stand or a hanging hole as you need.'],
        ['name' => 'Check the preview', 'text' => 'Under the preview you see the number of modules and the size of one. If the code is too dense for the chosen size, the tool says how many millimetres to enlarge it by.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our farm and pick the colour of the plate and the code at the order. Or download the model free as an STL or a finished project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'How do I make a QR code for Wi‑Fi?', 'a' => 'Choose "Wi‑Fi" at the top and fill in the network name, the password and the security (WPA/WPA2, WEP, or no password); tick a hidden network. After scanning, the phone offers to connect. The password stays only in the code and in the saved design.'],
        ['q' => 'Why does the tool ask me to enlarge the code or shorten the link?', 'a' => 'A longer text means a denser code with smaller modules; under 0.9 mm a 0.4 mm nozzle cannot print them cleanly and a phone cannot read them. Shorten a long link to an address you control, or enlarge the sign; the tool says by how many millimetres.'],
        ['q' => 'Can a printed code be changed later?', 'a' => 'No. The code is part of the print, so try the link before you order. If the target may change, use an address you can redirect, on your own domain say.'],
        ['q' => 'Why must the sign have two colours?', 'a' => 'A phone needs contrast between the code and the background. A raised code printed in one colour does not read, the shadow of the edges is not enough. We recommend a light plate and a dark code; a light code on a dark plate reads only on some phones.'],
        ['q' => 'How does the sign sit in the stand?', 'a' => 'The stand is a separate part with a groove. The sign gets a blank 10 mm strip at the bottom that slides into the groove, and it stands leaning back by about 12 degrees, so it reads from the table and standing up.'],
        ['q' => 'What if I have my own printer?', 'a' => 'Download the model free as an STL, or as a project for your printer. The project carries a filament change at the height of the plate: the printer pauses, you swap the spool and the print goes on in the second colour. A printer with several spools does it by itself.'],
    ],
    'examples' => [
        'A 70 × 83 mm QR sign with a web link and the caption matplace.com: 14 g, 25 minutes, 99 CZK.',
        'A 90 × 113 mm QR sign with a Wi‑Fi caption in a table stand, for the guests of a café.',
        'A small 40 × 40 mm QR plate without a caption, for product packaging say.',
    ],
];
