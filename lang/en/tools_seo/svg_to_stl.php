<?php

return [
    'title' => 'SVG to STL: turn an outline into a 3D model online',
    'description' => 'Upload an SVG or a simple picture, set the width and the height in millimetres and download the STL free, no sign-up. Or we print the shape for you.',
    'h1' => 'SVG to STL converter: an outline pulled up to a height',
    'intro' => [
        'The tool takes the filled shapes of an SVG file and pulls them straight up to the height you enter: 0.6 to 50 mm. You set the width from 20 to 250 mm and the other dimension follows the proportions, so the model has exactly the millimetres you read next to the preview. Holes inside a shape stay holes. Instead of an SVG you can upload a simple high-contrast PNG, JPG or WebP picture, pick a motif from the library, or type a text.',
        'What comes out is a closed solid ready for a slicer, not a leaky mesh you would have to repair. The tool warns in advance about lines thinner than 0.8 mm and says how many separate pieces the shape consists of. The top edge can be bevelled. You download the model as an STL or as a finished slicer project, or order it as a print from our print farm in a colour that is loaded in the printers right now.',
    ],
    'steps' => [
        ['name' => 'Upload an SVG', 'text' => 'Choose an SVG file up to 400 kB, or a PNG, JPG or WebP picture up to 5 MB. A motif from the library is there to try the tool with.'],
        ['name' => 'Enter the width and the height', 'text' => 'Set the width of the shape with the slider or by dragging the arrow in the preview, the thickness from 0.6 to 50 mm. One tick bevels the top edge.'],
        ['name' => 'Check the preview', 'text' => 'Next to the preview you read the outer dimensions and an approximate price, below it the warnings about thin lines or separate pieces.'],
        ['name' => 'Download the STL or order a print', 'text' => 'The model is free to download without an account, as an STL or as a slicer project. A print from our farm is paid from prepaid credit.'],
    ],
    'faq' => [
        ['q' => 'What kind of SVG does the tool take?', 'a' => 'A file up to 400 kB with filled shapes. Lines without a fill are left out and the tool says so; convert them to outlines in your drawing program first (Stroke to Path in Inkscape) and texts to curves.'],
        ['q' => 'What units does the model come out in?', 'a' => 'Millimetres. The dimensions written in the SVG do not matter: the shape is scaled to the width you enter and the other dimension follows the proportions.'],
        ['q' => 'Why does my shape come out in several pieces?', 'a' => 'An extruded outline has no plate, so nothing joins the separate parts of a drawing. The tool says how many there are. If they should hold together, switch the style to a relief on a plate.'],
        ['q' => 'Can I upload a PNG or a JPG instead of an SVG?', 'a' => 'Yes, a simple picture with a dark motif on a light background. It is traced in a grid of at most 360 points on the longer side, so an SVG gives smoother edges. A photo does not belong here; the Relief and lithophane tool is for that.'],
        ['q' => 'How high can the shape be pulled?', 'a' => 'From 0.6 to 50 mm. Up to 3 mm makes a tag or an ornament, around 5 mm a pendant or a coaster, and from 20 mm a shape that stands on its own.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or in euros. We send the print through Packeta to a pick-up point or to an address in the EU.'],
    ],
    'examples' => [
        'A cat silhouette from the library, 80 × 81 mm, pulled up to 6 mm.',
        'An oak leaf of 100 × 62 mm, 3 mm thick, with a bevelled top edge.',
        'A star of 60 × 57 mm pulled up to a height of 20 mm.',
    ],
];
