<?php

return [
    'title' => 'Papel picado from a photo or a drawing for 3D printing',
    'description' => 'Upload a photo or a drawing and get a cut-out panel in the style of Mexican papel picado, with a border of flowers and holes for the string.',
    'h1' => 'Papel picado: a cut-out panel from your own picture',
    'intro' => [
        'Papel picado is the Mexican banner of coloured paper with pictures and ornaments punched into it; it is hung at feasts, weddings and above all on the Day of the Dead. The tool does the same in plastic: a thin panel 80 to 250 mm wide and tall, in which your picture is cut out as a window. The dark parts of the picture stay as "paper", the light ones are cut out; it can be reversed. You can upload a photo, a drawing or a silhouette (PNG, JPG, WebP, SVG), or pick a motif from the library, such as a sugar skull.',
        'Round the window runs a border pierced with one of six patterns: flowers, diamonds, dots, hearts, leaves or stars. The lower edge has scallops with a hole, and the top corners have two holes for the string. Parts of the picture that would hang in the air, such as a face in a cut-out background or the pupil of an eye, are held by thin ties the tool adds by itself. For a photo you set how much paper stays and how much it is simplified. The panel is a one-colour print without supports; a garland is made of several panels in different colours.',
    ],
    'steps' => [
        ['name' => 'Choose a picture', 'text' => 'Upload a photo or a drawing, or pick a motif from the library. A picture fills the whole window of the panel; an SVG drawing is set into it whole.'],
        ['name' => 'Tune the cut', 'text' => 'The slider "How much paper stays" moves the line between paper and holes; "Simplifying the photo" takes away small details that would not print.'],
        ['name' => 'Choose the border and the size', 'text' => 'Six border patterns or a plain border; the scallops below and the holes for the string can be turned off. Change the width and the height with the sliders or by dragging the arrows in the preview.'],
        ['name' => 'Order a print or download', 'text' => 'Next to the preview you see the dimensions and a rough price, the precise price one step further. The model and the slicer project are free to download without an account.'],
    ],
    'faq' => [
        ['q' => 'What photo works?', 'a' => 'A portrait or a motif with strong contrast and a calm background: dark hair, a light face. A photo with many areas of the same grey gives a shapeless blot; moving the paper line or simplifying more helps.'],
        ['q' => 'Why are there thin vertical lines in the picture?', 'a' => 'Those are the ties. A piece of paper that nothing would hold once its surroundings are cut out is tied by them to the rim of the hole. The ties can be 0.8 to 2.4 mm wide; below the preview you read how many there are.'],
        ['q' => 'How thick should the panel be?', 'a' => 'From 0.8 to 2 mm; 1.2 mm is the default. Thinner is more translucent and flexible, thicker keeps its shape in a bigger format. For a panel of 200 mm and more choose at least 1.2 mm.'],
        ['q' => 'Does it last outdoors?', 'a' => 'PLA takes rain, but in direct sun a thin panel warps and fades over time. It lasts a garden party; for a whole summer choose PETG.'],
        ['q' => 'How do I make a whole garland?', 'a' => 'Print the same or different panels in several colours and thread them on a string through the holes in the top corners. The traditional colours are pink, orange, yellow, green, blue and purple.'],
        ['q' => 'How do I pay and how do I get the panel?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or in euros. We send the print through Packeta to a pick-up point or to an address in the EU.'],
    ],
    'examples' => [
        'A panel of 150 × 213 mm with the sugar skull of the library and a border of flowers.',
        'A square panel of 120 × 132 mm with a heart and a border of hearts.',
        'A landscape panel of 200 × 163 mm with a butterfly cut out and a border of diamonds.',
    ],
];
