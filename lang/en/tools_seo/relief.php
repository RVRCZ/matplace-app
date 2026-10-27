<?php

// The page for the query "lithophane maker online" (docs/AP.md §1, §5). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'Lithophane maker online, free: a panel, a lamp or a relief',
    'heading' => 'Lithophane maker online, free',
    'description' => 'Turn a photo into a lithophane in seven shapes, a lamp with the photo all round or a wall relief. Ready in a minute, the photo is not kept. Free.',
    'h1' => 'How a lithophane is made, which photo suits it and where the tool stops',
    'intro' => [
        'A lithophane is a thin plate on which the photo appears only when light shines through it: dark places are thicker and let less light through, light places thinner. The tool makes exactly that from your photo, in three forms: a panel for a window or a stand, a lamp where the same photo is wrapped round a cylinder with a base for an E27 or E14 socket, and a relief for the wall that is visible without light, because the light places rise above the plate. The plate can be a rectangle, a circle, an oval, a heart, an arch, a tree or your own silhouette from the library, up to 250 mm wide and high; the lamp up to 400 mm round (about 127 mm across) and 250 mm high. The thickness goes from 0.4 to 5 mm in steps of 0.2 mm and the tool says at once how many shades the photo will give; brightness, contrast and midtones are set with sliders and the preview shows the plate backlit. The photo is used only for the computation and is not kept.',
        'An example from the production calculator (18 October 2026, PLA+): a 100 × 75 mm panel with a table stand weighs 27 g, prints in 1 h 9 min and costs 100 CZK (about 4 EUR) from us; a lamp for an E27 socket, 100 mm across and 150 mm high, weighs 111 g, prints in 2 h 29 min and costs 250 CZK (about 10 EUR), without delivery. Both were made from a generated test picture, not from a photo of a person. A portrait or a photo with strong contrast and one main subject comes out best; small details in the background get lost in the shades of thickness. We print the lithophane in a white material so that light passes through it, or you download the model as an STL or as a project for your own printer with the settings that work for us.',
    ],
    'steps' => [
        ['name' => 'Choose what to make', 'text' => 'A photo panel for a window or a stand, a lamp with the photo all round, or a relief for the wall without backlight.'],
        ['name' => 'Upload the photo', 'text' => 'Choose or take a picture (JPG, PNG, WebP). Set brightness, contrast and midtones with the sliders, invert light and dark with a tick.'],
        ['name' => 'Choose the shape and the size', 'text' => 'For a panel the shape of the plate, the width, the height and the frame; for a lamp the circumference, the height and the socket. Extras: a hole or an eyelet for hanging, a table stand.'],
        ['name' => 'Create the model', 'text' => 'Click "Create the model". In about a minute the model opens with a backlit preview; then a free download, or the calculation and a print from us.'],
    ],
    'faq' => [
        ['q' => 'What is the difference between a lithophane and a relief?', 'a' => 'A lithophane shows the picture only against light, in a window or in front of a lamp; without light it is a white plate with a faint pattern. A relief is visible without light, because the light places of the photo rise above the plate; it suits a wall or a shelf.'],
        ['q' => 'Which photo works best?', 'a' => 'A portrait or a photo with strong contrast and one subject, sharp, not against the light. The tool says how many shades of thickness in steps of 0.2 mm the photo will give; the sliders for brightness, contrast and midtones improve a weaker photo. A dark subject on a light background comes out better than the other way round.'],
        ['q' => 'Why must a lithophane be white, and how do I light it?', 'a' => 'Coloured plastic tints the light and dark plastic blocks it; white PLA gives a clean picture. Daylight in a window, an LED strip or an LED bulb in the lamp is enough. Do not use an incandescent bulb, PLA softens at around 55 °C.'],
        ['q' => 'How does the lamp work?', 'a' => 'The photo is wrapped round a cylinder of the circumference you enter, with a smooth wall inside and a base with a Ø 40 mm hole for an E27 socket (or Ø 28 mm for E14). You can also choose an open base for an LED strip, or a closed one for an LED candle.'],
        ['q' => 'Do you keep my photo?', 'a' => 'No. The photo serves only to compute the model and is discarded at once; only the shape stays in the model. If you come back to the settings, you need to pick it again.'],
        ['q' => 'How big and how thick can the model be?', 'a' => 'A panel up to 250 × 250 mm, a lamp up to 400 mm round and 250 mm high. A lithophane is at most 5 mm thick, with the thinnest place from 0.4 mm; the wider the range, the more shades, but the longer the print.'],
    ],
    'examples' => [],
];
