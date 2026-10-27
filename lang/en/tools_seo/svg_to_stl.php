<?php

// The page for the query "svg to stl" (docs/AP.md §1, §3). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'SVG to STL converter online, free: outline to 3D model',
    'heading' => 'SVG to STL converter, free and online',
    'description' => 'Upload an SVG or PNG, or type a text, set the width in millimetres and the extrusion height. Download a watertight STL with no sign-up, or we print it.',
    'h1' => 'What the SVG to STL converter does and where it stops',
    'intro' => [
        'The tool takes the filled shapes of an SVG file and pulls them straight up to the height you enter: 0.6 to 50 mm. You set the width from 20 to 250 mm and the other dimension follows the proportions, so the model has exactly the millimetres you read next to the preview; the units written in the SVG do not matter. Holes inside a shape stay holes. The SVG may be up to 400 kB, and lines without a fill are left out and the tool says so. Instead of an SVG you can upload a simple high-contrast PNG, JPG or WebP picture up to 5 MB, pick a silhouette from the library, or type a text. What comes out is a closed solid with a bottom, a top and walls, which a slicer takes without repair. The tool warns in advance about lines thinner than 0.8 mm, which a 0.4 mm nozzle cannot print cleanly, and says how many separate pieces the shape consists of.',
        'An example from 27 October 2026: the cat silhouette from the library, 80 mm wide and pulled up to 6 mm, came out at 80 × 81 × 6 mm, 2,568 triangles and 17.7 cm³. The calculation gives it 9.6 g of PLA and 17 minutes of printing, 99 CZK (about 4 EUR) from our farm as the minimum order, without delivery (calculation /c/lfa9iggn3hls). You download the model free and without an account as an STL, or as a 3MF project for OrcaSlicer, Bambu Studio or PrusaSlicer with the settings of the calculation. If you have no printer, our farm prints the shape in a colour loaded in the printers right now.',
    ],
    'steps' => [
        ['name' => 'Upload an SVG, a picture or a text', 'text' => 'An SVG up to 400 kB, a PNG, JPG or WebP picture up to 5 MB, a silhouette from the library, or a line of text. The cat from the library is preselected to try the tool.'],
        ['name' => 'Enter the width and the extrusion height', 'text' => 'Set the width in millimetres with the slider or by dragging the arrow in the preview, the height from 0.6 to 50 mm. One tick bevels the top edge.'],
        ['name' => 'Read the warnings', 'text' => 'Next to the preview are the outer dimensions, below it a note about lines under 0.8 mm and about separate pieces, if there are any.'],
        ['name' => 'Download the STL or order a print', 'text' => 'The STL or the slicer project is free to download with no sign-up. A print from us continues to the calculation, where you see the grams, the minutes and the price.'],
    ],
    'faq' => [
        ['q' => 'Why does my slicer take an STL from here without errors when another converter gave a leaky mesh?', 'a' => 'Every outline gets a bottom, a top and walls, and the bodies are joined into one closed mesh. The slicer therefore reports no open edges or flipped faces and the model needs no repair.'],
        ['q' => 'How do I prepare the SVG so that all of it converts?', 'a' => 'Everything that should print must be a filled shape. In Inkscape convert lines with Stroke to Path and text with Object to Path; Illustrator and Affinity do the same. Layers, masks and gradients are not read.'],
        ['q' => 'How do I get exact dimensions in millimetres?', 'a' => 'You enter the width and the other dimension follows the proportions, so the shape is never distorted. The dimensions written in the SVG (px, mm, inches) are not used; what you read next to the preview is what you get.'],
        ['q' => 'Why did the shape fall apart into several pieces?', 'a' => 'An extruded outline has no plate, so nothing joins the separate parts of a drawing, and the tool says how many there are. If they should hold together, switch the style to a relief on a plate, or join the drawing in your editor.'],
        ['q' => 'Can I upload a PNG or a JPG instead of an SVG?', 'a' => 'Yes, a simple picture with a dark motif on a light background. The outline is traced in a grid of at most 360 points on the longer side, so an SVG gives smoother edges. A photo does not belong here; the Relief and lithophane tool is for that.'],
        ['q' => 'Is the conversion free, and do I need an account?', 'a' => 'The conversion and the STL download are free and need no account, and the uploaded file is not passed to anyone. You need an account only when you have the shape printed by us; that is paid from credit topped up by card, and we ship by Packeta across the EU.'],
    ],
    'examples' => [
        'The cat silhouette from the library, 80 × 81 mm, pulled up to 6 mm: 9.6 g, 17 minutes of printing.',
        'An oak leaf of 100 × 62 mm, 3 mm thick, with a bevelled top edge.',
        'A star of 60 × 57 mm pulled up to a height of 20 mm.',
    ],
];
