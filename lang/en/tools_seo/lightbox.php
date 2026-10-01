<?php

return [
    'title' => 'Illuminated sign or logo for 3D printing with an LED strip',
    'description' => 'Type the lettering or upload a logo and the tool designs an illuminated sign in four printed parts. You buy the LED strip and the power supply separately.',
    'h1' => 'An illuminated sign from your own lettering or logo',
    'intro' => [
        'The tool designs an illuminated sign in four printed parts: a body, a front mask with the cut-out motif, a diffuser that spreads the light, and a back cover. The motif is up to two lines of 16 characters, or an uploaded SVG or picture. The shape is rectangular, or round with a flat foot. The sign is 80 to 300 mm wide and 25 to 80 mm deep.',
        'We print only the plastic parts. You buy the 5 V LED strip, the USB power and the double-sided tape separately and assemble the sign yourself. The tool lists what you need and how many metres of strip fit around the inside. The front mask should be dark and the diffuser white or translucent. Order the parts from our print farm, or download them free for your own printer.',
    ],
    'steps' => [
        ['name' => 'Type lettering or upload logo', 'text' => 'Type the lettering in one or two lines, or upload an SVG or a picture with a dark motif on a light background. Narrow bridges hold the insides of letters in the mask.'],
        ['name' => 'Pick shape and light source', 'text' => 'Choose a rectangular or a round shape and the light source: an LED strip 8 mm or 10 mm wide, or an LED module with a controller. The tool checks the smallest body depth that the source needs.'],
        ['name' => 'Set the dimensions', 'text' => 'Enter the width and the depth. For the rectangular shape the height follows from the motif and the margin around it.'],
        ['name' => 'Look at the parts', 'text' => 'The preview shows the assembly pulled apart in layers, and you can switch it to single parts. Below it is the list of things that are not part of the print.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our print farm and pay from prepaid credit. Or download the parts free as STL files or a ready project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Is the LED strip part of the print?', 'a' => 'No. We print four plastic parts. You get the LED strip or module, the USB power cable and the double-sided tape or glue yourself.'],
        ['q' => 'Which LED strip suits the sign?', 'a' => 'A 5 V strip 8 or 10 mm wide, or an LED module or a strip with a controller of up to 18 × 8 mm. The tool gives the strip length you need below the preview. The cable passes through a hole in the body, 3 to 10 mm in diameter.'],
        ['q' => 'Which colours should the parts be printed in?', 'a' => 'The body, the front mask and the back cover should be dark, so that light passes only through the motif, and the diffuser white or translucent. One order with us prints in one colour. So download the diffuser as a separate part and upload it as your own model in a second order.'],
        ['q' => 'How do I assemble the sign?', 'a' => 'Stick the LED strip around the inside of the body and pass the cable through the hole. The diffuser and the front mask go in from the front onto a ledge in the body, and the back cover fits on from behind.'],
        ['q' => 'How big can the sign be?', 'a' => 'The width is 80 to 300 mm, and the height of a rectangular sign follows from the motif, up to 300 mm. The round shape is nine tenths as high as it is wide. Every part has to fit the print bed; the calculation in the next step says if one does not.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. You pick the print up in person, or we send it by Packeta to a pickup point or to your door across the EU.'],
    ],
    'examples' => [
        'Rectangular illuminated sign OPEN, 180 × 64 mm, 37 mm deep, for an LED strip.',
        'Round illuminated sign BAR with a flat foot, 160 × 144 mm, for a shelf.',
        'Two-line illuminated sign CAFÉ LUNA, 240 × 172 mm, for a counter or shop window.',
    ],
];
