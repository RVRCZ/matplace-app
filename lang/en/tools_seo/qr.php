<?php

return [
    'title' => 'QR code sign for 3D printing, with a desk stand',
    'description' => 'Paste a link, a text or Wi-Fi details and the tool makes a sign with a QR code that a phone can read. We print it in two colours, or you download it.',
    'h1' => 'A QR sign with a caption and a stand',
    'intro' => [
        'The tool makes a plate with a raised QR code. The code can open a website, a menu or a payment link, or connect a phone to Wi-Fi: paste a link or a text of 4 to 300 characters. A caption of up to 40 characters fits under the code, and a desk stand holds the sign at a slight lean. The code measures 30 to 150 mm.',
        'The tool checks that the code can be read. A module must be at least 0.9 mm, otherwise the tool asks you to enlarge the code or shorten the link. It also reads the finished design back. A code reads only in two colours: a light plate and a dark code. With us you pick the second colour when ordering; a downloaded project already carries the filament change.',
    ],
    'steps' => [
        ['name' => 'Paste the link or text', 'text' => 'Put the address or another text into the field "Link or text" and a short wording, such as the network name, into "Caption under the code". The caption can stay empty.'],
        ['name' => 'Set the size', 'text' => 'Enter the code size including the quiet zone, 30 to 150 mm. Tick the desk stand if you want one, or a hole to hang the sign by.'],
        ['name' => 'Check the preview', 'text' => 'Below the preview you see the number of modules, the size of one module and a rough price. If the code is too dense for the chosen size, the tool says how many millimetres it needs.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our print farm, choose the colours of the plate and the code and pay from prepaid credit. Or download the model free as an STL file or a ready project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'How do I make a QR code for Wi-Fi?', 'a' => 'Type a text in the form WIFI:T:WPA;S:network name;P:password;; into the link field, and a phone that scans it offers to connect. The tool has no separate Wi-Fi form.'],
        ['q' => 'Can a printed code be changed later?', 'a' => 'No. The code is part of the print, so test the link before you order. If the target may change, use an address you control and can redirect.'],
        ['q' => 'Why must the sign be in two colours?', 'a' => 'A phone needs contrast between the code and the background. It cannot read a raised code printed in one colour. We recommend a light plate and a dark code.'],
        ['q' => 'How does the sign sit in the stand?', 'a' => 'The stand is a separate part with a slot. The sign gets a blank strip of 10 mm at the bottom that slides into the slot, and it leans back by about 12 degrees.'],
        ['q' => 'What if I have my own printer?', 'a' => 'You download the model free as an STL file or as a project for your printer. The project carries a filament change at the plate height: the printer stops, you swap the spool and the print goes on in the second colour.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print by Packeta to a pickup point or to your door across the EU.'],
    ],
    'examples' => [
        'QR sign 70 × 83 mm with a website link and the caption matplace.com.',
        'QR sign 90 × 113 mm, caption Wi-Fi, in a desk stand for café guests.',
        'Small QR plate 40 × 40 mm without a caption, for example for product packaging.',
    ],
];
