<?php

// The page for the query "hollow stl online" (docs/AP.md §1, §4). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Hollow out an STL online, free: a shell with drain holes',
    'heading' => 'Hollow out a 3D model online, free',
    'description' => 'Turn a solid STL, 3MF, OBJ or STEP into a hollow model with a 1.5 to 6 mm wall and drain holes. You see the grams and money saved at once. Free.',
    'h1' => 'What hollowing does to a model, when it pays off and where it stops',
    'intro' => [
        'The tool removes the inside of the model and leaves a wall of the thickness you choose, 1.5 to 6 mm. It measures the distance from the surface on a fine 0.6 mm grid (coarser for models over 190 mm, and the tool says so) and the inner surface forms where the wall thickness is reached; that is why it follows the shape even in folds and the cavity never breaks through to the surface. From the outside the model does not change by a tenth of a millimetre. Drain holes of 3 to 8 mm appear in the bottom of the cavity: as many as fit, four at most, or the number you enter; they can also be switched off. It takes STL, 3MF, OBJ and STEP from 5 to 1000 mm and up to 2 million triangles; a model that is not closed is closed first, the same way our farm does it. The hollow model opens as a new file and the original stays.',
        'An example from the production calculator (18 October 2026, PLA+, 0.2 mm layers, 15 % infill): a solid cylinder of ⌀ 120 × 200 mm weighs 503 g, prints in 8 h 14 min and costs 910 CZK (about 37 EUR) from us. Hollowed with a 2.5 mm wall and three holes it weighs 223 g, prints in 6 h 36 min and costs 500 CZK (about 20 EUR): 280 g and 410 CZK less, the same on the outside. For resin printing the difference is bigger still: the solid cylinder would swallow 2.3 litres of resin, the hollow one with a 2.5 mm wall about a quarter of a litre (computed from the volume, not printed). The tool shows the volume of the cavity, the grams saved and the price difference right after hollowing; the x-ray in the preview shows the wall. You download the model as an STL or a slicer project, or have it printed by us.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'Drop an STL, 3MF, OBJ or STEP of 5 to 1000 mm into the field, or click and choose the file.'],
        ['name' => 'Choose the wall and the holes', 'text' => 'Wall thickness 1.5 to 6 mm. Drain holes in the bottom with a diameter of 3 to 8 mm and a count, automatically up to four, or none.'],
        ['name' => 'Hollow', 'text' => 'Click "Hollow the model". It takes a few seconds, up to a minute for large models; the page says what is going on.'],
        ['name' => 'Read what you save and go on', 'text' => 'The volume of the cavity, the grams and the money saved, the x-ray of the wall. Then download the STL or the slicer project, or print with us.'],
    ],
    'faq' => [
        ['q' => 'When does hollowing pay off and when not?', 'a' => 'Leave a small FDM part solid: the slicer gives it 15 % infill and a cavity would save a few grams. It pays off for large figures and busts over 150 mm, always for resin printing (a solid part cracks and swallows litres), and for things you want to weight with sand or put something inside.'],
        ['q' => 'How thick should the wall be?', 'a' => 'For FDM 2 to 3 mm; the wall is still printed with infill by the slicer, so it holds. For resin 2 to 3 mm, for large busts over 300 mm 3 to 5 mm. The tool does not go under 1.5 mm, such a shell prints badly.'],
        ['q' => 'What are the drain holes for and where will they be?', 'a' => 'Always in the bottom of the cavity, the side the model stands on. In resin printing the uncured resin drains out through them and no vacuum forms; in FDM you shake the supports out of the cavity through them, or pour sand in. If you do not want them, switch them off and the cavity stays closed.'],
        ['q' => 'Does the outside change?', 'a' => 'No. The outer surface stays exactly as it was; the cavity always ends at the chosen thickness under it, even in folds and thin places, where it simply does not form. The dimensions the calculator shows are the same before and after.'],
        ['q' => 'What happens to a model with holes in it?', 'a' => 'Before measuring, the tool closes it the same way our farm does. If that fails, it says so; run such a model through the Model repair tool first and hollow it again.'],
        ['q' => 'How big a model can the tool handle?', 'a' => 'Up to 1000 mm and 2 million triangles. Over 190 mm the measuring grid is coarser than 0.6 mm and the wall can differ in places by a tenth to half a millimetre; the tool says so with the result.'],
    ],
    'examples' => [],
];
