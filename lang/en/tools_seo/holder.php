<?php

// The page for the query "custom wall mount 3d print" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom wall mount to 3D print: cradle, pocket, hook, clip',
    'heading' => 'Custom wall mount to 3D print',
    'description' => 'Measure the thing, pick how it should hold, and the mount is made to size: a cradle, a pocket, a hook or a clip, for screws or tape. Free model.',
    'h1' => 'Which mounts the tool makes, how to measure and what a print costs',
    'intro' => [
        'The tool makes a mount from the size of the thing you want to put away on a wall: a remote, a bottle, a tool, headphones, a broom. You pick one of four kinds: a cradle with a low front, a closed pocket, a hook for anything with a loop or a strap, or a clip that grips a round handle up to 60 mm across. You enter the real size of the thing (width 10 to 300 mm, thickness 5 to 150 mm) and the tool adds 0.8 mm of play around it by itself; the clip is a touch tighter instead. The mount is fixed with 4 mm screws and wall plugs, by hanging on a screw already in the wall, or with double-sided tape without holes. The preview shows the shape with its holes and sizes, and the model prints without supports.',
        'An example from the production calculator (18 October 2026, PLA+): a cradle for a 50 × 22 mm remote, 70 mm high, weighs 27 g, prints in 1 h 12 min and costs 100 CZK (about 4 EUR) from us; a hook for headphones weighs 11 g, prints in 27 minutes and costs 99 CZK; a clip for a 24 mm broom handle weighs 8 g, prints in 21 minutes and costs 99 CZK, all without delivery. In 90 days 11 mounts went through the calculator. For light things PLA and tape are enough, for heavier ones choose PETG and screws; screws, plugs and tape are not part of the print. You download the model free as an STL or a project for your printer.',
    ],
    'steps' => [
        ['name' => 'Measure the thing', 'text' => 'Measure the width and the thickness of the thing, for a handle its diameter. Enter the real size without a reserve, the tool adds the play.'],
        ['name' => 'Pick how it should hold', 'text' => 'The cradle and the pocket embrace the thing, the hook is for anything with a loop or a strap, and the clip grips a round handle. You can start from a preset, "Remote" or "Headphones" say.'],
        ['name' => 'Choose the fixing', 'text' => 'Leave the screw holes on, or switch them off and stick the mount on with double-sided tape. The holes can be round, or keyholes for hanging on a screw in the wall.'],
        ['name' => 'Order the print or download', 'text' => 'Check the preview and go on to the exact price with "Continue". Or download the model free for your own printer.'],
    ],
    'faq' => [
        ['q' => 'How big a thing does the mount take?', 'a' => 'The width of the thing from 10 to 300 mm and the thickness from 5 to 150 mm. The clip is only for round handles up to 60 mm across. A mount over 250 mm does not fit the bed of our farm; the calculation says so.'],
        ['q' => 'How much does the mount carry?', 'a' => 'We state no load, it depends on the material, the screws and the wall. For heavier things choose a sturdy plastic (PETG) and screws in wall plugs. Double-sided tape carries only light things, a remote or headphones say.'],
        ['q' => 'Which screws do I need?', 'a' => 'The holes are for 4 mm screws with wall plugs. Neither screws nor plugs are part of the print.'],
        ['q' => 'What does hanging on a screw in the wall mean?', 'a' => 'The head of a screw already in the wall passes through the wide hole, the mount slides down and hangs in the narrow slot. You lift it to take it off. Handy where you do not want to drill new holes.'],
        ['q' => 'Should I add play to the sizes?', 'a' => 'No. The tool adds 0.8 mm of play around the thing, which you can change from 0.3 to 2 mm. The clip is a touch tighter instead, so that it grips the handle; measure a handle with callipers, not by eye.'],
        ['q' => 'Does the mount last in a bathroom or outdoors?', 'a' => 'In a bathroom or a garage choose PETG, which takes damp and heat; outdoors in the sun ASA. PLA is enough for a room or an office. You pick the material in the material comparison of the calculation.'],
    ],
    'examples' => [
        'A cradle for a 50 × 22 mm remote, 70 mm high, for screws: 27 g, 1 h 12 min, 100 CZK.',
        'A hook for headphones, 35 mm wide, 45 mm deep, hook 25 mm high: 11 g, 27 minutes, 99 CZK.',
        'A clip for a 24 mm broom handle, 25 mm high, for screws: 8 g, 21 minutes, 99 CZK.',
    ],
];
