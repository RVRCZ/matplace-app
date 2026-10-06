<?php

return [
    'title' => 'Repair a 3D model online: STL, 3MF and OBJ, free',
    'description' => 'Upload the STL, 3MF or OBJ your slicer refuses. We close holes, turn flipped faces, show what changed, and you download the repaired STL for free.',
    'h1' => 'We repair the model your slicer refuses or prints badly',
    'intro' => [
        'The tool is for models downloaded from the internet, scanned or exported with a fault, which a slicer refuses or prints badly. You upload an STL, 3MF or OBJ file and the repair runs by itself. We close holes in the surface, turn flipped faces and remove doubled faces, faces with zero area and loose specks outside the model.',
        'You get a report of what was wrong and what we changed, with a table of values before and after the repair. You download the repaired model as an STL for free, or open it in the calculator and order the print from us. The original file stays as it was. The repair does not change the shape or the size of the model.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Choose a file or drop it into the field. The file may be 120 MB at most.'],
        ['name' => 'Wait for the repair', 'text' => 'The repair runs by itself and there is nothing to set. Large files can take a minute.'],
        ['name' => 'Read the report', 'text' => 'You see the result, the list of changes and a table with the columns "Before" and "After": open edges, flipped faces, the number of bodies and triangles.'],
        ['name' => 'Download or order the print', 'text' => 'The "Download the repaired STL" button saves the model. The second button opens it in the calculator, where you get the price and order the print.'],
    ],
    'faq' => [
        ['q' => 'Is the model repair free?', 'a' => 'Yes. The repair and the download of the repaired file are free and need no sign-up.'],
        ['q' => 'Which faults does the tool repair?', 'a' => 'Holes in the surface, flipped faces, doubled faces, faces with zero area, edges shared by more than two faces and loose specks outside the model. Every body in the file is repaired on its own, so a box and its lid stay two parts.'],
        ['q' => 'Does the repair change the shape or size of the model?', 'a' => 'No. A repair that would change the shape or the size is thrown away and the body stays as it was.'],
        ['q' => 'What if the model cannot be repaired?', 'a' => 'Some faults cannot be repaired automatically and the report says so. What is left needs a manual fix in a modelling program. You can still try printing; slicers often cope with small faults.'],
        ['q' => 'In which format do I get the repaired model?', 'a' => 'Always as an STL, even if you uploaded a 3MF or OBJ. The STL format carries only the shape, without colours or print settings.'],
    ],
    'examples' => [],
];
