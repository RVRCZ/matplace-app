<?php

return [
    'title' => 'Sliding fidget in your own plate: a groove with a slider',
    'description' => 'Upload a flat model and get a dovetail groove with a slider, knob and detents cut into it. Prints in one go, assembled; off the bed the slider runs and clicks.',
    'h1' => 'A sliding fidget cut into your plate, printed in one go',
    'intro' => [
        'The tool takes a flat model, a logo, a name tag, a pendant, anything at least 6 mm thick, and cuts a dovetail groove along its longer side (or the side you choose), with a margin at the ends so the slider cannot fall out. A slider goes in: a dovetail bar 0.3 mm smaller on every side, with a knob of Ø 10 mm on top and a dimple underneath that clicks on the balls of the detents in the groove\'s floor. All of it prints in one go, assembled, without supports.',
        'You set the groove\'s width and depth, the margin, the offset across the plate, the slider\'s length, the number of detents (0 to 5) and the play; the knob can be left off, the slider is then flush with the surface. The model takes a few seconds and opens in the preview with the plate and the slider in different colours, the size and the print price; download it for free or order the print from us.',
    ],
    'steps' => [
        ['name' => 'Upload a flat plate', 'text' => 'An STL or 3MF of a logo, a name tag or any other plate at least 6 mm thick. The output of our generators does too.'],
        ['name' => 'Set the groove', 'text' => 'Its direction, width and depth, the margin from the ends and the offset across, so it does not run through the text.'],
        ['name' => 'Set the slider', 'text' => 'Its length, the number of detents, the play and whether it gets a knob.'],
        ['name' => 'Create the model', 'text' => 'Click "Make the sliding fidget". A few seconds later it opens with the plate and the slider, the size and the print price.'],
    ],
    'faq' => [
        ['q' => 'Why a dovetail and not a plain groove?', 'a' => 'The flanks lean 15 degrees and hold the slider in the groove; it cannot lift out. The groove is closed at both ends, so it cannot slip out sideways either.'],
        ['q' => 'How does the slider come free after printing?', 'a' => 'The 0.3 mm of play on every side bridges with thin strands in the print. Once off the bed push the slider sideways with a finger; the first move breaks the strands.'],
        ['q' => 'How do the detents work?', 'a' => 'Balls of Ø 2 mm sit on the groove\'s floor where the slider can rest; the slider has a dimple underneath. Moving, it rises a quarter of a millimetre over a ball and drops in behind it: a click.'],
        ['q' => 'How thick must the plate be?', 'a' => 'At least the groove\'s depth plus 2 mm of floor, so 6 mm with the default depth of 4 mm. Across, the slider needs the groove\'s width plus 2 mm on each side.'],
        ['q' => 'Which material and settings?', 'a' => 'PLA or PETG, 0.2 mm layers, no supports, lying flat. The 0.3 mm of play suits an ordinary printer\'s accuracy; if it runs tight, choose 0.4.'],
        ['q' => 'What does it cost?', 'a' => 'Creating the model and downloading it are free. You pay only for the print, if you order it from us.'],
    ],
    'examples' => [],
];
