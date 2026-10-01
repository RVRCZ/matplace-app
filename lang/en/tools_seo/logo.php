<?php

return [
    'title' => 'Logo for 3D printing from an SVG or a picture',
    'description' => 'Upload an SVG or a simple picture, or type a text. You get a relief on a plate, a cut-out shape or a standing logo, and you see the price at once.',
    'h1' => 'A logo or lettering as a relief, a cut-out or a standing sign',
    'intro' => [
        'The tool turns a logo, picture or text into a model for 3D printing. Upload an SVG with filled shapes or a simple high-contrast picture (PNG, JPG or WebP), or type up to two lines of text. Four versions are on offer: relief on a plate, shaded relief from a picture, cut-out shape, or logo standing on a base. The motif can be 20 to 250 mm wide.',
        'The preview shows the exact shape to be printed, its outer size and a rough price. The tool warns about lines thinner than 0.8 mm, which would not print cleanly, and parts that would fall apart. Order the model as a print from our print farm, or download it free for your own printer. With the relief on a plate the motif can be printed in a second colour.',
    ],
    'steps' => [
        ['name' => 'Upload artwork or type text', 'text' => 'Upload an SVG or a picture, or type a text in one or two lines of up to 30 characters. Uploaded artwork takes precedence over the text.'],
        ['name' => 'Pick the version', 'text' => 'Choose the relief on a plate, the shaded relief from a picture, the cut-out shape or the standing logo. For a plate you also pick its shape: rounded, square-cornered or round.'],
        ['name' => 'Set the size', 'text' => 'Enter the width of the motif and its thickness. The height follows from the proportions of the artwork.'],
        ['name' => 'Check preview and warnings', 'text' => 'Below the preview you read the size, a rough price and any warnings about thin lines or separate pieces. Use them to make the motif bigger or to change the version.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our print farm and pay from prepaid credit. Or download the model free as an STL file or a ready project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'What kind of picture can the tool use?', 'a' => 'Best is an SVG with filled shapes of up to 400 kB, or a clean picture with a dark motif on a light background of up to 5 MB. Outlines without a fill in an SVG are left out. A picture is traced on a grid of at most 360 cells along its longer side, so very fine details are lost.'],
        ['q' => 'Can I upload a photograph?', 'a' => 'Only in the version "Shaded relief from a picture", where the shades set the height of the surface: dark areas rise the highest. In the other versions the tool refuses a photograph and points to the Relief and lithophane tool.'],
        ['q' => 'Why does a cut-out logo fall into pieces?', 'a' => 'A cut-out shape has no plate, so nothing joins single letters or separate parts of a logo. The tool tells you how many pieces the shape consists of. If they should hold together, choose the relief on a plate.'],
        ['q' => 'How does the standing logo work?', 'a' => 'The logo and the base print as two parts and the logo slides into a slot in the base. To be strong enough, a standing logo is at least 2.4 mm thick. Parts that do not reach the base, such as dots and accents, would not hold, and the tool warns about them.'],
        ['q' => 'Can the logo be printed in two colours?', 'a' => 'With the relief on a plate, yes: for a print with us you pick a second colour and the printer swaps it at the height where the motif begins. You choose from the colours loaded in the printers at that moment, so we cannot guarantee an exact brand shade.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print by Packeta to a pickup point or to your door across the EU.'],
    ],
    'examples' => [
        'Lettering ATELIER, a relief on a rounded plate 110 × 26 mm, for a door.',
        'Standing lettering OPEN, 120 mm wide, in a base, 43 mm high in total.',
        'The letter M as a cut-out shape, 80 × 72 mm, 2 mm thick.',
    ],
];
