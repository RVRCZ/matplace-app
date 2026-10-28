<?php

// The page for the query "3d printed logo sign" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed logo from an SVG or picture: relief or standing',
    'heading' => '3D printed logo sign from your SVG, picture or text',
    'description' => 'Upload an SVG or a picture of your logo, or type a text, and get a relief on a plate, a cut-out shape or a logo standing in a base. Free model.',
    'h1' => 'Which styles the logo tool makes, what to watch in the artwork and what a print costs',
    'intro' => [
        'The tool turns a logo, a picture or a text into a model for 3D printing. You can upload an SVG with filled shapes up to 400 kB or a simple high-contrast PNG, JPG or WebP picture up to 5 MB, or type up to two lines of 30 characters. You pick one of four styles: a relief on a plate (rounded, rectangular or round), a sculpted relief from a picture where the shades set the height, a cut-out shape without a plate, or a logo standing in a base with a groove. You set the width of the motif from 20 to 250 mm and the height follows the proportions. The tool warns about lines thinner than 0.8 mm, which a printer cannot print cleanly, and about parts that would not hold together. With a relief on a plate the motif prints in a second colour: the printer changes it at the height where the motif begins.',
        'An example from the production calculator (28 October 2026, PLA): a relief on a rounded plate 100 mm wide with the text LOGO and a cat silhouette weighs 27.6 g, prints in 43 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order, without delivery (calculation /c/11vhrvmivr3e). A bigger sign for a door or a counter is priced by grams and minutes, and you see the price before you order anything. You pick the colours from the spools we have in stock right now, so we cannot guarantee an exact brand shade. You download the model free as an STL or a project for your printer; with a relief also the plate and the motif as separate parts.',
    ],
    'steps' => [
        ['name' => 'Upload the artwork or type a text', 'text' => 'Upload an SVG or a picture, or type a text in one or two lines of 30 characters. An uploaded artwork takes precedence over the text.'],
        ['name' => 'Pick the style', 'text' => 'Choose a relief on a plate, a sculpted relief from a picture, a cut-out shape, or a standing logo. For the plate pick the shape: rounded, rectangular or round.'],
        ['name' => 'Set the size', 'text' => 'Enter the width of the motif and its thickness. The height follows the proportions of the artwork.'],
        ['name' => 'Check the preview and the warnings', 'text' => 'Under the preview you read the dimensions and any warnings about thin lines or separate pieces. Enlarge the motif or change the style accordingly.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our farm and pick the colours at the order. Or download the model free as an STL or a finished project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Which picture does the tool take?', 'a' => 'Best an SVG with filled shapes up to 400 kB, or a clean picture with a dark motif on a light background up to 5 MB. Outlines without a fill are left out of an SVG. A picture is traced in a grid of at most 360 points on the longer side, so very fine detail is lost; send a logo with small text as an SVG.'],
        ['q' => 'Can I upload a photo?', 'a' => 'Only in the style "Sculpted relief from a picture", where the shades set the height of the surface: dark places rise highest. In the other styles the tool refuses a photo and points to the Relief and lithophane tool.'],
        ['q' => 'Why does a cut-out logo fall apart into pieces?', 'a' => 'A cut-out shape has no plate, so nothing joins the letters or the separate parts of a logo. The tool says how many pieces the shape consists of. If they should hold together, choose a relief on a plate, or join the pieces in your editor.'],
        ['q' => 'How does the standing logo work?', 'a' => 'The logo and the base print as two parts and the logo slides into a groove in the base. For strength a standing logo is at least 2.4 mm thick. Parts that do not reach the base, dots and accents say, would not hold and the tool warns about them.'],
        ['q' => 'Can the logo be printed in two colours?', 'a' => 'With a relief on a plate, yes: for a print from us you pick the second colour and the printer changes it at the height where the motif begins; the downloaded project carries the change. You pick from the colours loaded in the printers right now, so we cannot guarantee an exact brand shade.'],
        ['q' => 'How big a logo for a door or a reception?', 'a' => 'For an office door a relief of 100 to 150 mm is enough, for a reception or a shop window 200 to 250 mm; over 250 mm the logo does not fit the bed and you split it into parts. A standing logo for a counter works best between 120 and 200 mm; for a lit sign there is the separate Lightbox sign tool.'],
    ],
    'examples' => [
        'The text ATELIER as a relief on a rounded plate of 110 × 26 mm, for a door say.',
        'The standing text OPEN 120 mm wide in a base, 43 mm high in all.',
        'The letter M as a cut-out shape of 80 × 72 mm, 2 mm thick.',
    ],
];
