<?php

return [
    'title' => 'Casting mold from a 3D model: make it online',
    'description' => 'Turn your 3D model into a printable mold: 2 to 4 parts, or for silicone (sleeve, shell, tray, two-part). With vents, a core and the volumes in ml.',
    'h1' => 'A printable mold from your model: in parts or for silicone',
    'intro' => [
        'You upload a 3D model and the tool builds a mold around it, ready to print. Simple shapes can be cast straight in a printed mold of two to four parts. For figures, busts and other shapes with undercuts there is the mold for silicone in four kinds: a sleeve, a shell that follows the model and saves more than half of the silicone, a tray for reliefs, and a two-part mold with a parting plate and keys.',
        'Before the mold is built, the tool measures which part of the surface a hard mold would not let go of and marks it red on the model. It works out how much material one casting takes and how much silicone the mold does. It finds the places where air would stay in the cavity and leads vents out of them. A printed core makes the casting hollow. The mold opens as a new file: you have it printed by us or download it.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop a model into the field or click to choose a file. The model must be a closed body between 5 and 400 mm in size. The tool marks the undercuts red at once and states the volume of the casting and of the silicone.'],
        ['name' => 'Choose the kind of mold', 'text' => 'Click a picture of a kind at the top, or choose in the settings: a printed mold in parts, or a mold for silicone and its kind. Vents, the core and the wall are optional; the division and the cut can be left to the tool.'],
        ['name' => 'Make the mold and order the print', 'text' => 'Click "Make the mold". It takes a few seconds, up to two minutes for large models. "Price and print" opens the mold in the calculation, where you see the price, order the print or download the file.'],
    ],
    'which' => [
        ['shape' => 'A simple shape with no undercuts (a pendant, a tile, a button, a soap)', 'kind' => 'Printed mold, 2 parts'],
        ['shape' => 'A boxy or round shape with mild undercuts at the sides (a vase, a mug, a figure with legs)', 'kind' => 'Printed mold, 3 or 4 parts, perhaps in TPU'],
        ['shape' => 'A figure, a bust, an animal: undercuts all round, and you want several castings', 'kind' => 'Silicone: shell (least silicone) or sleeve (simplest)'],
        ['shape' => 'A relief, a sign, a coaster, a magnet: one side flat', 'kind' => 'Silicone: tray'],
        ['shape' => 'A shape that must be clean all round and is cast again and again', 'kind' => 'Silicone: two-part mold with keys'],
    ],
    'faq' => [
        ['q' => 'What is an undercut and why does it matter?', 'a' => 'An undercut is a place the mold catches on when it is taken apart. A hard mold does not let such a casting go without damage. "Fill the undercuts" fills those places with material in the casting (orange in the preview) so that a printed mold comes apart; otherwise choose the mold for silicone.'],
        ['q' => 'What is the difference between a sleeve, a shell, a tray and a two-part mold?', 'a' => 'All four are printed vessels you pour silicone over the model in. A sleeve is a straight wall round the outline: the simplest, but it takes a lot of silicone. A shell follows the model a wall away and usually saves half of the silicone; it prints in two halves with supports. A tray is one piece for a model with a flat back. A two-part mold has a parting plate with cone keys: you pour two halves that fit into each other, and the casting is clean all round.'],
        ['q' => 'What are the vents for and how does the tool make them?', 'a' => 'When pouring, air stays in every pocket of the cavity that is higher than its surroundings: in the nose of a bust, under the chin, in a hollow. The casting gets bubbles there. The tool finds those places and leads channels of 2.5 mm out of them: in the parting plane of a printed mold, as thin pins on the master of a silicone one, which leave a channel in the silicone. After casting the sprues of the channels are cut off.'],
        ['q' => 'What is the core and when is it useful?', 'a' => 'The core is a printed model shrunk by the wall of the casting, with a stem into the pouring opening. You set it into the silicone mold and the casting comes out as a shell of the chosen thickness: it saves resin, wax or soap, and a large casting is lighter. The tool states the volume of the core and of the material for one hollow casting.'],
        ['q' => 'How much silicone and material will I need?', 'a' => 'Right after the upload the tool states the volume of the casting in ml and the silicone for a sleeve and a shell. After the mold is built it states the exact values for the chosen kind, including the saving of a shell against a sleeve and the volume of the core. Buy about a tenth more for what stays in the cup.'],
        ['q' => 'Which model will the tool not take, and what does it cost?', 'a' => 'A model smaller than 5 mm or larger than 400 mm, and a model that cannot be made into a closed body; repair a model with holes in our repair tool first. Making the mold is free; you pay only for the print if you order it from us. The silicone you buy yourself.'],
    ],
    'examples' => [
        'A printed mold of two parts with ball keys, a pouring opening and vents in the parting plane',
        'A printed mold of four wedges for a shape with undercuts at the sides',
        'A mold for silicone: a shell in two halves follows the bust and saves more than half of the silicone',
        'A mold for silicone: a tray in one piece for a model with a flat back',
        'A mold for silicone: two-part, with a parting plate, cone keys and a box',
    ],
];
