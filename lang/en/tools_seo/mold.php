<?php

return [
    'title' => 'Casting mold from a 3D model: make it online',
    'description' => 'Turn your own 3D model into a printable mold: two to four parts, or a mold for silicone. The tool measures undercuts and the volume for one casting.',
    'h1' => 'A printable mold from your model: in parts or for silicone',
    'intro' => [
        'The tool is for people who want to cast their own shape and need a mold for it. You upload a 3D model and the tool builds a mold around it, ready to print. Simple shapes can be cast straight in a printed mold of two to four parts. For figures and other shapes with undercuts there is the mold for silicone.',
        'Before the mold is built, the tool measures which part of the surface a hard mold would not let go of and marks it red on the model. You get a mold with keys that keep the parts aligned, a pouring opening and the amount of material needed for one casting. The mold opens as a new file. You have it printed by us or download it.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop a model into the field or click to choose a file. The model must be a closed body between 5 and 400 mm in size.'],
        ['name' => 'Look at the undercuts', 'text' => 'The tool measures what a printed mold would not let go of and marks those places red. It also shows the share of undercuts for 2, 3 and 4 parts.'],
        ['name' => 'Choose the kind of mold', 'text' => 'Pick a printed mold or a mold for silicone, the wall of the mold and the number of parts. The split and the cut position can stay on automatic.'],
        ['name' => 'Make the mold', 'text' => 'Click "Make the mold". Building takes a few seconds, up to two minutes for large models.'],
        ['name' => 'Order the print or download', 'text' => 'The "Price and print the mold" button opens the mold in the calculator. There you get the price, order the print or download the file.'],
    ],
    'faq' => [
        ['q' => 'What is an undercut and why does it matter?', 'a' => 'An undercut is a place the mold catches on when it is taken apart. A hard mold cannot let go of such a casting without damage.'],
        ['q' => 'When should I choose the mold for silicone?', 'a' => 'When the tool shows that the shape will not come out of a printed mold even with more parts. You print a base with the model and a sleeve, pour silicone over the model and cast into the soft mold you peel off it. You buy the silicone yourself; the tool works out how much is needed.'],
        ['q' => 'What does "Fill the undercuts" do?', 'a' => 'The places that would hold the mold are filled with material in the casting, so the printed mold comes apart. The casting differs from the model in those places. You see the result in a preview first, with the added material in orange.'],
        ['q' => 'Which material should the mold be printed from?', 'a' => 'A shape without undercuts from an ordinary hard plastic. For small undercuts the tool recommends flexible TPU, because a hard mold would break the casting.'],
        ['q' => 'Which models can the tool not process?', 'a' => 'A model smaller than 5 mm or larger than 400 mm, and a model that cannot be turned into a closed body. Repair a model with holes in our repair tool first.'],
        ['q' => 'What does making a mold cost?', 'a' => 'Making the mold is free. You pay only for the print, if you order it from us.'],
    ],
    'examples' => [],
];
