<?php

return [
    'title' => 'Keychain with a name or a picture for 3D printing',
    'description' => 'Type a name or upload a picture and get a keychain in filament colours with an eyelet for the ring. We print it, or you download the model for free.',
    'h1' => 'Keychain with a name, a logo or your own picture',
    'intro' => [
        'The tool makes a keychain from a name, a short text or a picture. Type one or two lines and pick one of four typefaces, or upload a PNG, JPG, WebP or SVG, or choose a motif from the library. The keychain is a rounded rectangle, a circle, or follows the outline of the motif. The width goes from 30 to 100 mm, the thickness from 2.4 to 6 mm.',
        'The eyelet for the ring has a hole of 3 to 8 mm and sits where you put it: with a slider, or by dragging it in the preview. A name on a base is two colours, one on the other, so the print stops once and the filament is changed. We print such a keychain on our print farm too, as well as a picture in up to four colours: the base and up to three more. You confirm the spool for each colour when ordering; a picture in more colours can be downloaded for your own printer for free.',
    ],
    'steps' => [
        ['name' => 'Type a name or choose a picture', 'text' => 'The text can have two lines of 24 characters. Instead of it you can upload a picture or choose a motif from the library; the background of the picture is removed automatically.'],
        ['name' => 'Set the shape, the size and the eyelet', 'text' => 'Choose a rectangle, a circle or the shape of the outline. Move the eyelet along the outline with the slider or by dragging it in the preview, and set the hole to fit your ring.'],
        ['name' => 'Choose the colours', 'text' => 'The base and the lettering get a filament from those we really have in stock. For a picture you set the number of colours, their order and a filament for each.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the size and a rough price, the exact price and print time one step further. The model and the slicer project with the filament change are free to download, without registration.'],
    ],
    'faq' => [
        ['q' => 'How big a hole does a key ring need?', 'a' => 'A common ring of 25 to 30 mm needs a 5 mm hole, which is the preset. For a carabiner or a thicker ring make it up to 8 mm.'],
        ['q' => 'What is the keychain printed from and will it last in a pocket?', 'a' => 'We recommend PLA or the tougher PETG. At 3 mm the keychain stands everyday use; for car keys left in the sun choose PETG, PLA softens at about 55 °C.'],
        ['q' => 'How many colours can the keychain have?', 'a' => 'A name on a base has two colours. A picture is reduced to 1 to 8 colours, each lying one step higher than the one below. Our farm prints up to four colours in one print, the base and up to three more; a design with more colours can be downloaded with a project that contains the filament changes.'],
        ['q' => 'What if the letters of the name do not touch?', 'a' => 'With the shape that follows the outline the tool ties separate letters and dots together with a short bridge and tells you so. On a rectangle or a circle everything stands on the base.'],
        ['q' => 'How small can the lettering be?', 'a' => 'Lines thinner than the nozzle of the printer do not come out, and the tool warns about them. Put a longer name on a wider keychain, or split it into two lines.'],
        ['q' => 'How do I pay and how do I get the keychain?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print through Packeta to a pick-up point or to an address in the EU.'],
    ],
    'examples' => [
        'A keychain with the name Jana on a rounded rectangle 55 mm wide, the eyelet on the left.',
        'A keychain with a paw following the outline of the motif, 50 mm, the eyelet on top.',
        'A round keychain of 45 mm with a smiling star.',
    ],
];
