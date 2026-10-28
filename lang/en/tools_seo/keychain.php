<?php

// The page for the query "custom keychain 3d print" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom keychain to 3D print: shape, name and picture, free',
    'heading' => 'Custom keychain with a name to 3D print',
    'description' => 'Build it from a shape, a name and a picture, every layer in its own colour, and add an eyelet for the ring. Free download, or we print it.',
    'h1' => 'How to build the keychain from layers, which hole for the ring and what it costs',
    'intro' => [
        'Here you build a keychain from layers. At the bottom lies a shape (a bone, a heart, a circle, a tag, a rounded rectangle and more), on it a name in one of thirty typefaces and, if you like, a picture from the library of silhouettes or your own. Every layer has its own colour from the filaments our farm has and lies a step higher than the one below; up to twelve layers. You pick a layer by clicking in the preview, move it by dragging, scale it by the corner of its frame and turn it with the wheel. At the top is an eyelet with a 2 to 8 mm hole. The colours lie one above the other, so a printer only changes the filament at the right height; on our farm we print up to four colours in one go. A keychain from a colour picture, a logo say, is made by the quick form the page links to.',
        'An example from the production calculator (28 October 2026, PLA): a 70 mm wide bone with the name Rex in two colours weighs 3.8 g and prints in 10 minutes. One costs 99 CZK (about 4 EUR), which is the minimum order; ten of the same cost 100 CZK, that is 10 CZK each, because the order preparation is paid once (calculations /c/fwgocmbyzfx6 and /c/teyrxpsrhi4k). For other names you make another design: the layout stays, you only rewrite the name. The ring is not part of the print. The model and the slicer project with the filament changes are free to download, no sign-up.',
    ],
    'steps' => [
        ['name' => 'Start from the keychain', 'text' => 'The page opens with a bone and the name Rex. Rewrite the name, change the shape, or pick another preset, a heart with a name say.'],
        ['name' => 'Edit the layers', 'text' => 'Click a layer in the list or in the preview and change its text, typeface, shape, picture and colour. In the preview you move it by dragging, scale it by the corner of its frame and turn it with the wheel.'],
        ['name' => 'Add more and set the eyelet', 'text' => 'With the buttons you add a text, a picture or a shape, up to twelve layers. In the dimensions you set the thickness and the eyelet hole to fit the ring.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price and print time show in the calculation after "Continue". The model and the slicer project with the filament changes are free to download, no sign-up.'],
    ],
    'faq' => [
        ['q' => 'How big a hole does a key ring need?', 'a' => 'For an ordinary ring of 25 to 30 mm a 4 to 5 mm hole is enough; 4 mm is preset. For a carabiner or a thicker ring enlarge it up to 8 mm. The ring is not part of the print.'],
        ['q' => 'How big should the keychain be?', 'a' => 'For keys 40 to 70 mm and 3 mm thick: the keychain then weighs 3 to 5 g and does not get in the way in a pocket. For a backpack or a bag it can be 80 to 100 mm, so that the name reads from a distance.'],
        ['q' => 'What is the keychain printed from, and does it survive a pocket?', 'a' => 'We recommend PLA or the sturdier PETG. At 3 mm thick the keychain stands up to everyday wear; for car keys that lie in the sun choose PETG, PLA softens at around 55 °C.'],
        ['q' => 'How many colours can the keychain have?', 'a' => 'Every layer has its own colour and there can be twelve layers. On our farm we print up to four colours in one go; a design with more colours you download with the project that carries the filament changes.'],
        ['q' => 'Can I use my own picture or logo?', 'a' => 'Yes. Upload an SVG, PNG or JPG as a layer and a one-colour silhouette remains of it. A logo in several colours is handled by the quick form the page links to.'],
        ['q' => 'What if the pieces lie next to each other and do not touch?', 'a' => 'The tool joins them in the bottom layer with a short bridge and says so, so the keychain holds together.'],
    ],
    'examples' => [
        'A 70 × 32 mm bone with the name Rex and an eyelet for the ring, two colours: 3.8 g, 10 minutes, 99 CZK.',
        'A 60 mm heart with the name Ema in the Lobster typeface, the eyelet on top.',
        'A round 45 mm keychain with a paw, black and yellow.',
    ],
];
