<?php

return [
    'title' => 'Flexi from your own 3D model: segments with ball joints',
    'description' => 'A long model is cut into segments with ball joints that print in one go, assembled, and bend once off the bed. A snake or a dragon out of your own figure.',
    'h1' => 'A bendy toy from your own model: a flexi with ball joints',
    'intro' => [
        'The tool cuts a long model across its longest axis (or the axis you choose) into 3 to 20 segments and puts a ball joint into every cut: a ball of 6 to 10 mm on a neck growing out of one segment, and a socket with 0.35 to 0.5 mm of play in the other. The ball sits deeper in the socket than its opening is wide, so it cannot be pulled out, yet it turns. Between the segments is a gap of two layers.',
        'The whole prints as one piece, assembled, without supports, and once it is off the bed the joints are broken free with the fingers and the toy bends like the well-known printed dragons. At the cuts the model must be at least the ball plus 3 mm thick; where it is not, the tool says so and leaves that joint out. The flexi opens as a new file: have it printed by us, or download it.',
    ],
    'steps' => [
        ['name' => 'Upload a long model', 'text' => 'Drop a model into the field or click to choose a file: STL, 3MF, OBJ or STEP. Snakes, dragons, lizards, fish and anything with a clear length and enough thickness suit it.'],
        ['name' => 'Choose the segments and the joints', 'text' => 'The axis of the segments, their number (or automatic by the ball), the diameter of the ball and the play in the joint; the height of the model if you like.'],
        ['name' => 'Make the flexi', 'text' => 'Click "Make the flexi". It takes a few seconds; you see the segments in the preview, each in its colour, the joint in the X-ray view.'],
        ['name' => 'Order the print, or download', 'text' => 'Download the flexi as one STL or as a project for your slicer, or have it printed by us.'],
    ],
    'faq' => [
        ['q' => 'How is a flexi printed?', 'a' => 'As one piece, assembled, with 0.2 mm layers and no supports. The upper half of a socket is an overhang a usual printer manages; the 0.4 mm of play keeps the ball and the socket apart. After printing break the joints free with your fingers; the first move is stiff.'],
        ['q' => 'How thick must the model be?', 'a' => 'At every cut at least the ball\'s diameter plus 3 mm (11 mm for an 8 mm ball), so the socket has a wall round it. Where that does not work out, the tool leaves the joint out and says so; choose a smaller ball, fewer segments, or scale the model up.'],
        ['q' => 'How many segments should I choose?', 'a' => 'Automatic gives a segment for about every 2.5 ball diameters; more segments bend more, but shorter segments hold less. At least 3, at most 20.'],
        ['q' => 'Why do the joints not move right after printing?', 'a' => 'Thin layers in the gap may have fused; break the joints free carefully with your fingers, or open the play up to 0.5 mm. Joints that are too loose wobble instead: use 0.35 mm.'],
        ['q' => 'Can a flexi be made from a photo?', 'a' => 'Yes: first make a figure in the bust-from-a-photo tool, or download a model from Printables or MakerWorld, then upload it here.'],
        ['q' => 'What does it cost and how do I get the print?', 'a' => 'Making the flexi is free. You pay only for the print if you order it from us, from prepaid credit. We send the print by Packeta to a pick-up point or an address in the EU.'],
    ],
    'examples' => [],
];
