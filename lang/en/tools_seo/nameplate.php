<?php

return [
    'title' => 'Nameplate shaped as a heart, cloud or bone, with a picture',
    'description' => 'Type a name, pick one of twenty plate shapes and a motif next to the text. We print the nameplate in two colours, or you download the model for free.',
    'h1' => 'Nameplate and sign in a shape, with your own motif',
    'intro' => [
        'The tool makes a plate with a name in one of twenty shapes: heart, star, cloud, bone, ribbon, arrow, house, car, cat, fish, shield, speech bubble and more. Type one to three lines of up to 40 characters and pick one of thirty typefaces. The shape grows by itself just enough for the text and its margin to fit in, so nothing sticks out and there is nothing to calculate. The letter height goes from 4 to 80 mm.',
        'A picture can stand next to the text: a motif from the library of silhouettes, or your own SVG or simple picture. Put it left, right or above the text and it is printed like the letters. The letters and the rim can be printed in a second colour; the printer changes filament at the height where the letters begin. An eyelet goes left, right or on top, so the same design is a sign for a child\'s door, a bag tag or a tag for a collar.',
    ],
    'steps' => [
        ['name' => 'Type the name', 'text' => 'One to three lines, the first one the biggest. Below the fields is a row of symbols you can put into the text.'],
        ['name' => 'Pick the shape and the typeface', 'text' => 'Twenty plate shapes are shown as tiles, thirty typefaces as their names set in the face itself.'],
        ['name' => 'Add a picture and an eyelet', 'text' => 'A motif from the library or your own picture goes left, right or above the text. The eyelet has three possible sides.'],
        ['name' => 'Order a print or download', 'text' => 'Next to the preview you see the dimensions and a rough price. When ordering you pick the colour of the plate and of the letters; the model and the slicer project are free to download.'],
    ],
    'faq' => [
        ['q' => 'How big will the nameplate be?', 'a' => 'That depends on the text and the shape. The name Jana in 14 mm letters with a star comes out as a cloud of 97 × 53 mm. Shapes with a narrow middle, such as the bone or the arrow, come out longer; smaller letters or a smaller margin shrink them.'],
        ['q' => 'Why is the bone or the arrow so much bigger than a rectangle?', 'a' => 'The whole text must lie inside the shape. A bone has a narrow shaft in the middle, so it grows until the letter height fits into the shaft. A short name on one line suits it best.'],
        ['q' => 'What picture can I add?', 'a' => 'A silhouette from the library, an SVG with filled shapes, or a simple high-contrast PNG or JPG. A photo does not work here; the Relief and lithophane tool is for that.'],
        ['q' => 'Do you print the nameplate in two colours?', 'a' => 'Yes. The letters, the picture and the rim lie above the plate, so one filament change is enough. You pick both colours when ordering, from those loaded in the printers right now.'],
        ['q' => 'Does it work as a dog tag?', 'a' => 'The bone with an eyelet does; choose PETG for a collar and letters of at least 6 mm. Put the phone number on the second line; below 4 mm it no longer prints cleanly.'],
        ['q' => 'How do I pay and how do I get the nameplate?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or in euros. We send the print through Packeta to a pick-up point or to an address in the EU.'],
    ],
    'examples' => [
        'A cloud with the name Jana and a star on the left, 97 × 53 mm, letters and rim in a second colour.',
        'A bone with the name Rex and a paw on the right, 110 × 51 mm, in Titan One.',
        'A heart of 61 × 54 mm with the name Ela, the year 2020 and an eyelet on top, in Lobster.',
    ],
];
