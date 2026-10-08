<?php

return [
    'title' => 'Filament art: a picture as layered plates or one print',
    'description' => 'A picture or a photo becomes wall art in filament colours: layered plates in a frame, or one print with the colours stacked. We print it, or you download it.',
    'h1' => 'Filament art: a photo or a drawing as layers of plastic',
    'intro' => [
        'The tool reduces a picture or a photo to 1 to 8 colours of filaments we really have in stock on our print farm and builds a picture for the wall of them, 50 to 250 mm wide. By default it is a layered picture: every colour is a plate of its own, 1.5 to 3 mm thick, and the plates stack one behind another like a paper cut, the back one carrying the whole silhouette and the front one only its colour. Spacer posts of 2 to 5 mm between the plates give the picture depth and shadow; for a thin picture the plates lie flat. Add a round or square frame with a slot for a nail and, if you like, room for an LED strip.',
        'The second way is one print: a base plate with the colours 0.4 mm one above another. Every layer of the print holds one filament, so any printer prints it by swapping the filament at the heights the project names. For every colour you see the spool the tool chose and can swap it for another from our catalogue, reorder the colours or merge two. Next to the preview is the guide: the plates in order from the back to the front, each one drawn.',
    ],
    'steps' => [
        ['name' => 'Upload a picture', 'text' => 'Upload a picture, pick one from the library, or reuse one you uploaded earlier. The background goes by itself; for a photo that is to fill the whole area, switch the removal off.'],
        ['name' => 'Choose how it is made', 'text' => 'A layered picture of plates with a frame, or one print with the colours one above another. Set the width, the thickness of the plates and the gap between them, the frame and room for an LED strip if you want it.'],
        ['name' => 'Fine-tune the colours', 'text' => 'Choose how many colours and give each one a filament from our catalogue. Colours can be moved forward or back, or two merged into one.'],
        ['name' => 'Order the print, or download', 'text' => 'Next to the preview you see the size and a rough price, the exact price one step on. Download every plate as its own STL, or the whole design as a project for your slicer, free and without registration.'],
    ],
    'faq' => [
        ['q' => 'How is a layered picture assembled?', 'a' => 'Print every plate in its colour, then glue them by the guide from the back to the front. The spacer posts are part of the plate and go under the plate in front of them. Superglue is enough. The frame prints bottom down and the plates are glued inside it.'],
        ['q' => 'What if I only have a single-colour printer?', 'a' => 'The layered picture is made for it: every plate is a single-colour print. One print with the colours as steps works too; the project holds filament changes at the right heights, the printer stops and you swap the filament.'],
        ['q' => 'Which pictures come out best?', 'a' => 'Drawings and logos with a few solid colours. Of a photo the tool takes the main colours and the result is poster-like; for a portrait a lithophane is the better choice. Lines thinner than 0.8 mm would break as a plate of their own, so the tool gives them to the colour behind them and says so.'],
        ['q' => 'What frame, and how does it hang?', 'a' => 'Round or square, 6 to 20 mm wide, with a slot for a nail in the back. With "Room for an LED strip" the plates stand 10 mm further from the back and there is a slot for the cable at the bottom; the strip is yours to buy.'],
        ['q' => 'What is the picture printed from?', 'a' => 'From PLA, which has the widest choice of colours and prints flat without supports. The colours are spools we really have in stock, so the print looks like the preview.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit topped up by card; prices show in crowns or euros. We send the print by Packeta to a pick-up point or an address in the EU.'],
    ],
    'examples' => [
        'A snowman as a layered picture of five plates in a round 180 mm frame, with spacer posts.',
        'A Christmas tree from the library as four plates without a frame, 150 mm, the back plate carrying the whole silhouette.',
        'A gingerbread man as one print of 120 × 140 mm: a base and three colours 0.4 mm one above another.',
    ],
];
