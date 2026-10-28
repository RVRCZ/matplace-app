<?php

// The page for the query "custom stamp 3d print" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Custom stamp to 3D print for clay and polymer clay, free STL',
    'heading' => 'Custom stamp to 3D print for clay and polymer clay',
    'description' => 'Type a text or upload a motif and the tool makes a stamp with a handle, mirrored for a readable impression in ceramics and polymer clay. Free model.',
    'h1' => 'What the stamp presses into, how it is made and what it costs',
    'intro' => [
        'The tool makes a stamp for impressions in ceramic clay, polymer clay, soap or dough, that is in a soft material that keeps the impression. The motif can be a text of up to two lines of 20 characters in one of four typefaces, an SVG or a simple black-and-white picture; an uploaded artwork takes precedence over the text. The motif is mirrored by itself, so the impression reads right; the preview can be switched to the impression the stamp leaves. You set the width of the motif from 15 to 120 mm, its height from 0.8 to 4 mm, and pick a stamp with a round handle or just a plate. The motif can be raised, then it presses in, or recessed, then the impression stands out of the material. The tool warns about lines thinner than 0.8 mm. A printed stamp does not suit ink and paper, its surface is too hard.',
        'An example from the production calculator (28 October 2026, PLA): the stamp EVA 50 mm wide with a handle and a raised motif weighs 6.8 g, prints in 28 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order, without delivery (calculation /c/qr5iss66z3ip). The handle prints separately and is glued to the plate with superglue. A stamp with a logo for a pottery studio or a maker’s mark for the bottom of mugs prints the same way, you just upload an SVG. You download the model free as an STL or a project for your printer.',
    ],
    'steps' => [
        ['name' => 'Type a text or upload a motif', 'text' => 'Type the text in one or two lines, or upload an SVG or a picture with a dark motif on a light background. An uploaded artwork takes precedence over the text.'],
        ['name' => 'Pick the motif and the handle', 'text' => 'Choose a raised or a recessed motif and a stamp with a handle or without one. Set the width and the height of the motif.'],
        ['name' => 'Switch the preview to the impression', 'text' => 'With "The impression it leaves" you check what the impression will look like. The tool warns about lines thinner than 0.8 mm.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our farm. Or download the model free as an STL or a finished project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Does the stamp suit paper and ink?', 'a' => 'No, the surface of a printed stamp is too hard for that and ink does not hold on it. It is meant for impressions in ceramic clay, polymer clay, soap or dough.'],
        ['q' => 'Do I have to mirror the motif in advance?', 'a' => 'No. The tool mirrors the text and the uploaded picture by itself. Prepare the artwork the way the finished impression should look, and check it with the preview "The impression it leaves".'],
        ['q' => 'How is the handle fixed?', 'a' => 'The handle prints as a separate part and is glued to the plate with superglue. The glue is not part of the print; without a handle the plate serves as an embossing plate under a rolling pin.'],
        ['q' => 'What is the difference between a raised and a recessed motif?', 'a' => 'A raised motif presses into the material and leaves a sunken impression, a signature on the bottom of a mug say. A recessed motif is cut into the plate, so the impression stands out of the material, a raised logo on soap say.'],
        ['q' => 'How fine a detail does the stamp manage?', 'a' => 'Lines thinner than 0.8 mm do not print cleanly or at all and the tool warns about them. Enlarging the stamp or thickening the lines in the artwork helps; a motif height of 1 to 1.5 mm gives the most legible impression in clay.'],
        ['q' => 'How do I print the stamp at home and clean it?', 'a' => 'Motif up, without supports, from PLA or PETG, 0.2 mm layers or finer for smoother edges. Wash clay off the stamp with water and a brush; before stamping into a sticky material dust the stamp with cornstarch.'],
    ],
    'examples' => [
        'The stamp EVA with a handle, a 56 × 23 mm plate, a raised motif for clay: 6.8 g, 28 minutes, 99 CZK.',
        'The two-line stamp HAND MADE with a handle, a 66 × 41 mm plate, for ceramics.',
        'The plate OK without a handle, 46 × 25 mm, a recessed motif, the impression stands out.',
    ],
];
