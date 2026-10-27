<?php

// The page for the query "christmas ornament with name" (docs/AP.md §6). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Christmas ornament with a name: a 3D printed gingerbread',
    'heading' => 'Christmas ornament with a name: a gingerbread for the tree',
    'description' => 'Type a name and pick a man, a heart, a star or a tree with white icing. We print the gingerbread in two colours, or you download the model free.',
    'h1' => 'How the gingerbread with a name is made, what it costs and how long it prints',
    'intro' => [
        'The tool makes an ornament that looks like a gingerbread with sugar icing: a man, a heart, a star or a tree. You type a name of up to 16 characters, pick a typeface and the icing, wavy or a straight line. You set the width from 50 to 150 mm, the thickness from 2 to 6 mm; the eyelet for a ribbon is at the top and you move it with the slider or by dragging in the preview. The gingerbread has two colours one above the other: a brown body and white icing with the name, which stands 0.4 to 1.2 mm proud. The print pauses once for a filament change, so any printer can do it; on our farm it prints in one go and you pick both colours at the order. Brown and white are preset, but the gingerbread can just as well be red with gold icing.',
        'An example from the production calculator (27 October 2026, PLA): a 90 mm wide man with the name Ela weighs 10.8 g, prints in 20 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order (calculation /c/krswdwunexsg); six pieces of the same gingerbread cost 140 CZK, that is 23 CZK each, because the order preparation is paid once (/c/qkmqrfmbzaln). The print is an ornament, not food. It prints flat, face up and without supports, and hangs on a branch even on a thin ribbon. The model and the slicer project with the filament change are free to download, no sign-up.',
    ],
    'steps' => [
        ['name' => 'Type the name', 'text' => 'The name may have up to 16 characters. The tool shrinks it to fit the gingerbread; a short name comes out bigger.'],
        ['name' => 'Pick the shape and the icing', 'text' => 'The man has a face, buttons and icing on his legs, the heart and the star have icing round the edge, the tree two garlands. The icing can be wavy, a straight line, or none.'],
        ['name' => 'Set the size, the eyelet and the colours', 'text' => 'Enter the width and the thickness, move the eyelet along the outline and give each of the two parts a filament. Brown and white are preset.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price and print time show in the calculation after "Continue". The model and the project with the filament change are free to download, no sign-up.'],
    ],
    'faq' => [
        ['q' => 'How long a name fits on the gingerbread?', 'a' => 'Up to 16 characters. A longer name comes out smaller; if the letters would be under 3 mm, the tool warns you and suggests a wider gingerbread or a shorter name. A 90 mm man takes 6 to 8 letters comfortably.'],
        ['q' => 'Can I put a year or a dedication on it?', 'a' => 'One field of up to 16 characters: "Ela 2026" or "For Grandma" fits, a longer dedication does not. For more text and your own picture use the Christmas ornament from a picture or the nameplate made of layers.'],
        ['q' => 'What is the gingerbread printed from?', 'a' => 'We recommend PLA: it is light and hangs on a branch even on a thin ribbon. It prints flat, face up, without supports. The print is an ornament, not food; for real biscuits there is the Cookie cutter tool.'],
        ['q' => 'Do you print it in two colours?', 'a' => 'Yes. The body and the icing with the name lie one above the other, so one filament change is enough. You pick both colours at the order from the filaments we have in stock; the classic is brown with white, red with gold or green with white work too.'],
        ['q' => 'Can I use the gingerbread as a gift tag?', 'a' => 'Yes. A smaller gingerbread of 50 to 60 mm with an eyelet ties to the parcel with a ribbon, and the recipient hangs it on the tree. For tags without icing the Nameplate tool is quicker.'],
        ['q' => 'What do several gingerbreads cost at once?', 'a' => 'In the calculation you enter the number of pieces of one name: one 90 mm gingerbread costs 99 CZK, six of the same 140 CZK, that is 23 CZK each, because the order preparation is paid once. For other names you make another design; they print side by side on one plate.'],
    ],
    'examples' => [
        'A 90 mm wide gingerbread man with the name Ela, a face and buttons of white icing: 10.8 g, 20 minutes, 99 CZK.',
        'A 90 mm gingerbread heart with the name Mamince and wavy icing round the edge.',
        'An 80 mm gingerbread star with the name Tom, the eyelet on the top point.',
    ],
];
