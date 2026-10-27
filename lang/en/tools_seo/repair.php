<?php

// The page for the query "repair stl file online" (docs/AP.md §1, §3). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Repair an STL file online, free: holes and flipped faces',
    'heading' => 'Repair an STL file online, free',
    'description' => 'Upload the STL, 3MF or OBJ your slicer refuses. We close holes, turn flipped faces, join broken bodies, and you download the repaired STL free, no sign-up.',
    'h1' => 'What the repair fixes, what it leaves alone and how long it takes',
    'intro' => [
        'The tool is for a model downloaded from Printables, Thingiverse or MakerWorld, for a scan, and for a file exported with a fault, which a slicer refuses or prints with holes. You upload an STL, 3MF or OBJ up to 120 MB and the repair runs by itself, nothing to set. It closes holes in the surface, turns flipped faces, removes doubled faces, faces with zero area and loose specks outside the model, and sorts out edges shared by more than two faces. Every body in the file is repaired on its own, so a box with its lid stays two parts. A repair that would move the size of a body by more than 2 % is thrown away and the body stays as it was: neither the shape nor the millimetres change. You get a report of what was wrong and what changed, with a table before and after: open edges, edges shared by several faces, the number of bodies and triangles. The original file stays as it was.',
        'An example from 27 October 2026: a bust of 60 × 52 × 78 mm with 4,053 triangles, into which we cut three holes, doubled 25 faces and flipped 40. A slicer reports such a mesh as not watertight. The repair took 1.4 seconds: it closed 36 open edges, sorted out 75 edges shared by several faces, removed 25 doubled faces and joined 51 loose fragments into one closed body with a volume of 80.6 cm³. The size stayed at 60 × 52 × 78 mm. You download the repaired STL free and without an account, or open it in the calculator with one button: on our farm it would print in about an hour for around 110 CZK (about 4.50 EUR, PLA, without delivery); if you own a printer, you download the slicer project instead.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Choose an STL, 3MF or OBJ up to 120 MB or drop it into the field. There is nothing to set.'],
        ['name' => 'Wait for the repair', 'text' => 'An ordinary model is done in a few seconds, a file with millions of triangles within a minute. The page says what is going on.'],
        ['name' => 'Read the report', 'text' => 'The result (repaired, improved, unchanged), the list of changes and the table "Before" and "After": open edges, edges shared by several faces, bodies, triangles, volume.'],
        ['name' => 'Download or order the print', 'text' => 'The "Download the repaired STL" button saves the file. The second button opens it in the calculator, where you see the grams, the print time and the price.'],
    ],
    'faq' => [
        ['q' => 'My slicer says "non-manifold", "open edges" or "mesh is not watertight". Will this help?', 'a' => 'Yes, those messages are exactly what the repair is for. Open edges are holes in the surface, non-manifold means edges shared by more than two faces, and the tool fixes both. The report then shows how many edges it closed and how many it sorted out.'],
        ['q' => 'Does the repair change the shape or the size of the model?', 'a' => 'No. Holes are closed in the plane of their rim and faces are only turned. If a repair would move the size of a body by more than 2 %, it is thrown away and the body stays as it was; the report says so.'],
        ['q' => 'What can the tool not repair?', 'a' => 'A large missing part of the surface has nothing to be rebuilt from, and a surface without thickness (a single wall with no volume) cannot be closed. The report then says "improved" or "unchanged", and the rest is manual work in Blender, Meshmixer or a CAD program.'],
        ['q' => 'In which format do I get the repaired model?', 'a' => 'Always as an STL, even if you uploaded a 3MF or OBJ. An STL carries only the shape; the colours, part names and print settings of a 3MF are not carried over.'],
        ['q' => 'Is the repair free, and do I need an account?', 'a' => 'The repair and the download are free and need no account, with no watermark and no limit on the number of files. The uploaded file serves only the repair and the calculation and is passed to nobody.'],
        ['q' => 'Why did the report show no volume before the repair?', 'a' => 'A volume can be computed only from a closed mesh. While the model has holes, the volume, and so the grams and the price, make no sense; after the repair the calculation computes them and the slicer gets a clean file.'],
    ],
    'examples' => [],
];
