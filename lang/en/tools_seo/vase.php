<?php

return [
    'title' => 'Custom vase or plant pot cover for 3D printing',
    'description' => 'Choose the height, diameters, shape and surface: smooth, ribbed or spiral. A pot cover has drainage and a saucer. We print it, or download it free.',
    'h1' => 'A vase or a plant pot cover in your own size',
    'intro' => [
        'The tool builds a vase or a plant pot cover from the height and the diameters you enter. You pick one of four shapes: narrow neck, bellied, straight cone or tulip. The surface can be smooth, ribbed or spiral. For ribbed and spiral surfaces you set the number of ribs, the flute depth and the twist. A pot cover can have drainage holes in the floor and a separate saucer.',
        'The preview changes at once and shows the real outer size, because some shapes are wider than the diameters you entered. A rough price is next to it. We print the vase on our print farm in Czechia: it comes by Packeta to a pickup point or to your door. You can also download the model free for your own printer. Note that a printed vase may not be watertight.',
    ],
    'steps' => [
        ['name' => 'Choose purpose and shape', 'text' => 'Pick a vase or a plant pot cover and one of the four shapes. You can start from the preset "Spiral", "Ribbed", "Smooth" or "Plant pot".'],
        ['name' => 'Enter the dimensions', 'text' => 'Set the height, the top diameter and the bottom diameter. For a pot cover measure the pot that goes inside and add some room.'],
        ['name' => 'Tune the surface', 'text' => 'For the ribbed and the spiral surface set the number of ribs, the flute depth and the twist. The tool warns you when the wall between the flutes would come out too thin.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview and the rough price and continue to the precise price; a print from us is paid from credit topped up by card. Or download the model for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Will a printed vase hold water?', 'a' => 'It may not. Microscopic gaps can remain between the layers. For cut flowers put a glass inside the vase, or seal the inside with varnish.'],
        ['q' => 'How big can the vase be?', 'a' => 'The height can be from 40 to 300 mm, the top and bottom diameter from 30 to 250 mm. A tall or wide vase may not fit every printer.'],
        ['q' => 'What are the drainage holes and the saucer for?', 'a' => 'In a pot cover, surplus water drains through the holes in the floor and the saucer catches it. The saucer is a separate part, and the tool works out its diameter from the base.'],
        ['q' => 'Why is the vase wider than the diameters I entered?', 'a' => 'The diameters apply to the top rim and the base. The narrow neck and the bellied shape widen between them, and the ribs add a few more millimetres. The preview shows the real outer size.'],
        ['q' => 'How thick is the wall?', 'a' => 'By default the wall and the floor are 1.6 mm. Under "Wall thickness and more" you change the wall from 0.8 to 4 mm and the floor from 0.8 to 5 mm. A thin wall between deep flutes is more fragile and holds water less well.'],
    ],
    'examples' => [
        'Spiral vase with a narrow neck, 180 mm high, top diameter 62 mm, 20 ribs.',
        'Smooth vase with a narrow neck, 160 mm high, top diameter 70 mm.',
        'Ribbed plant pot cover with a saucer, 120 mm high, top diameter 130 mm.',
    ],
];
