<?php

return [
    'title' => 'Check a 3D model before printing, online and free',
    'description' => 'Upload a model and find out whether its size, mesh and face orientation are right. Every finding comes with what it means for the print.',
    'h1' => 'Check your model before it goes to the printer',
    'intro' => [
        'The check is for the moment when you have a model and are not sure it is ready to print. You upload the file and a moment later you get a report split into errors, advice and things that are fine. Every finding says what it will do to the print and how to put it right.',
        'We check only what we can find reliably: size and units, whether the mesh is closed, flipped faces, the number of separate bodies and how dense the mesh is. We do not judge overhangs, wall strength or accuracy, because they depend on the printer, the material and how the model is turned. The check is free, needs no sign-up and changes nothing in your file.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Choose a file or drop it into the field. The supported formats and the largest file size are written right there.'],
        ['name' => 'Read the report', 'text' => 'Next to the rotatable preview you see the findings in three groups: "Errors", "Advice" and "Fine".'],
        ['name' => 'Continue to the price', 'text' => 'The "Get the price and order the print" button opens the model in the calculator. There you order the print from us or download the model.'],
    ],
    'faq' => [
        ['q' => 'What exactly does the check look at?', 'a' => 'Wrong units, a model thinner than 0.8 mm, holes in the mesh, flipped faces, several separate bodies and a mesh that is too dense or too coarse. It also says whether the model fits an ordinary printer with a build space of 250 × 250 × 250 mm.'],
        ['q' => 'Does the check guarantee that the model will print well?', 'a' => 'No. Overhangs, wall strength and accuracy depend on the printer, the material and the orientation of the model, and the check does not judge them.'],
        ['q' => 'The check found holes in the mesh or flipped faces. What now?', 'a' => 'Use our model repair tool. It closes the holes, turns the faces and you download the repaired file for free.'],
        ['q' => 'Why does the check say my model measures only a fraction of a millimetre?', 'a' => 'It was most likely saved in metres or inches instead of millimetres. Save it again in millimetres and upload it once more.'],
        ['q' => 'Does the check change my file?', 'a' => 'No. The check only reads the file and edits nothing in it.'],
    ],
    'examples' => [],
];
