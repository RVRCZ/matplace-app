<?php

// The page for the query "check stl file for 3d printing" (docs/AP.md §1, §3). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Check an STL file before printing, online and free',
    'heading' => 'Check an STL file before printing',
    'description' => 'Upload a model and know in seconds whether its units, mesh and faces are right and whether it fits a printer. Every finding says what it means in print.',
    'h1' => 'What the check looks at, what it does not judge, and how to read the report',
    'intro' => [
        'The check is for the moment when you have an STL, 3MF, OBJ or STEP and do not know whether a printer will take it. You upload the file and within a few seconds you get a report in three groups: errors, advice and what is fine, each finding with what it will do to the print and how to put it right. It watches the units (a model under 1 mm was almost certainly saved in metres or inches, one over 2 m the other way round), the thinnest dimension under 0.8 mm, which a 0.4 mm nozzle cannot print, whether the mesh is closed, flipped faces, the number of separate bodies, a mesh over 1.5 million triangles that slows the slicer down, and a mesh under 40 triangles that gives a faceted print. It also says whether the model fits a printer with a build space of 250 × 250 × 250 mm; for a set of parts it measures the largest one. It does not judge overhangs, wall strength or accuracy, because they depend on the printer, the material and the orientation of the model. It only reads the file and changes nothing in it.',
        'An example from 27 October 2026: a bust of 60 × 52 × 78 mm into which we cut three holes and flipped part of the faces. The check returned three lines: size 60 × 52 × 78 mm fine, the error "the mesh is not closed" and the advice "51 separate bodies". With one button we sent it to the repair; afterwards the mesh is closed, there is one body and the check is clean. A healthy model goes straight on: the price button opens it in the calculator, where you see the grams, the print time and our price, or you download the project for your own printer. The check is free, needs no account and has no limit on files.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Choose an STL, 3MF, OBJ or STEP or drop it into the field. The largest file size is written right there.'],
        ['name' => 'Read the report', 'text' => 'Next to the rotatable preview are the findings in three groups: "Errors", "Advice" and "Fine", each with an explanation for the print.'],
        ['name' => 'Repair, or continue to the price', 'text' => 'Holes and flipped faces go to the repair with one button. A healthy model opens in the calculator, where you order it or download it.'],
    ],
    'faq' => [
        ['q' => 'What exactly does the check look at?', 'a' => 'Units (under 1 mm and over 2 m is an error, under 5 mm a warning), the thinnest dimension under 0.8 mm, holes in the mesh, flipped faces, several separate bodies, a mesh over 1.5 million or under 40 triangles, and whether the model fits 250 × 250 × 250 mm.'],
        ['q' => 'Does the check guarantee that the model will print well?', 'a' => 'No. Overhangs, wall strength and accuracy depend on the printer, the material and the orientation of the model, and the check does not judge them. It says only what can be found reliably in the file.'],
        ['q' => 'The check found holes in the mesh or flipped faces. What now?', 'a' => 'The button next to the finding sends the model to the repair. It closes the holes, turns the faces, and you download the repaired STL free; the repair report shows the numbers before and after.'],
        ['q' => 'Why does the check say my model measures only a fraction of a millimetre?', 'a' => 'It was saved in metres or inches instead of millimetres. Save it again in millimetres, or scale it in the calculator by 1000 (metres) or 25.4 (inches).'],
        ['q' => 'The model does not fit the printer. What can I do?', 'a' => 'The check says which dimension is over 250 mm. Either scale the model down in the calculator, or cut it with the Split model tool into parts with pins that fit.'],
        ['q' => 'The check reports several separate bodies. Is that a fault?', 'a' => 'For a box with its lid or a set of parts it is by design, and the check knows that for our own tools. In a downloaded model they are usually fragments of a broken mesh or specks; the repair joins or removes them.'],
    ],
    'examples' => [],
];
