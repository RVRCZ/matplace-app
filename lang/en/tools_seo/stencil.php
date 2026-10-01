<?php

return [
    'title' => 'Stencil for painting and spraying from your own text',
    'description' => 'Type a text or upload a motif and the tool cuts a stencil from it. Bridges hold the insides of letters in place. We print it, or you download it free.',
    'h1' => 'A stencil with a text or a motif that holds together',
    'intro' => [
        'The tool cuts a text or a motif into a thin plate: a stencil for painting, dabbing or spraying. The text can have two lines of 24 characters each, or you upload an SVG or a simple picture. The insides of letters such as O or A would fall out of a stencil. The tool ties them to the frame with narrow bridges and says how many it added.',
        'The motif can be 30 to 250 mm wide, the margin 5 to 40 mm and the stencil 0.8 to 3 mm thick. The preview shows exactly the stencil you will get, bridges included. Order the stencil as a print from our print farm, or download the model free for your own printer. For repeated use we recommend PETG: it is more flexible and paint washes off it better.',
    ],
    'steps' => [
        ['name' => 'Type text or upload motif', 'text' => 'Type the text in one or two lines. Instead of a text you can upload an SVG with filled shapes or a picture with a dark motif on a light background.'],
        ['name' => 'Set the size and margin', 'text' => 'Enter the width of the motif and the margin around it. The height of the stencil follows from the motif.'],
        ['name' => 'Check the bridges', 'text' => 'The preview shows where the bridges run, and their number is given below it. You change the bridge width, from 0.8 to 3 mm, in the section "Wall thickness and more".'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our print farm and pay from prepaid credit. Or download the model free as an STL file or a ready project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'Why are there bridges in the letters?', 'a' => 'Closed letters such as O, A or B have an inside that would fall out once cut. A bridge holds it to the frame. It leaves a thin unpainted strip, which you can touch up with a brush.'],
        ['q' => 'How big can the stencil be?', 'a' => 'The motif can be 30 to 250 mm wide and at most 300 mm high, plus the margin. A large stencil may not fit the print bed; the calculation in the next step says so.'],
        ['q' => 'Which material should the stencil be printed from?', 'a' => 'For repeated use we recommend PETG: it is more flexible than the ordinary plastic PLA and paint washes off it better. The stencil prints lying flat, without supports.'],
        ['q' => 'What kind of motif can I upload?', 'a' => 'An SVG with filled shapes of up to 400 kB, or a PNG, JPG or WebP picture with a dark motif on a light background. What is dark in the picture is cut out; the option "Swap light and dark" turns that round. The tool does not accept a photograph with smooth shades.'],
        ['q' => 'Can I change the typeface of the stencil?', 'a' => 'No, the stencil uses one bold sans typeface. Set other lettering in a drawing program, convert it to filled paths and upload it as an SVG.'],
        ['q' => 'How do I pay and how do I get the print?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. You pick the print up in person, or we send it by Packeta to a pickup point or to your door across the EU.'],
    ],
    'examples' => [
        'Stencil BOA 8 for marking crates, 144 × 52 mm, bridges hold the letter insides.',
        'Stencil FRAGILE for boxes and crates, 184 × 50 mm, 1.2 mm thick.',
        'Two-line stencil No. 27, for example for a house number, 124 × 130 mm.',
    ],
];
