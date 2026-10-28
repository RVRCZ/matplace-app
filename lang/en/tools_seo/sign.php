<?php

// The page for the query "3d printed name sign" (docs/AP.md §9). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed name sign, tag and keyring: your text, free STL',
    'heading' => '3D printed name sign with your text',
    'description' => 'Type a name or a text in up to three lines, pick a shape and one of thirty typefaces, and see the model at once. Free STL, or a two-colour print.',
    'h1' => 'What the name sign does, how it prints in two colours and what it costs',
    'intro' => [
        'From the text you type the tool makes a door sign, a name tag, a keyring or a name without a plate. The text may have one to three lines of up to 40 characters; the second and third lines are smaller than the first. You pick the style of the letters (raised, recessed, outline, or just the name without a plate), the shape of the plate and one of thirty typefaces with Czech accents; your own font cannot be uploaded. The plate is sized by itself from the letter height (4 to 80 mm), the length of the text and the margin (2 to 30 mm), the plate is 1.2 to 10 mm thick; a keyring eyelet, a raised frame or a bevelled top edge can be added. Raised letters, the outline and the name without a plate print in a second colour: the printer changes it at the height where the letters begin.',
        'An example from the production calculator (18 October 2026, PLA+): the keyring "Jana" with an eyelet and a frame, 46 × 22 mm, weighs 2 g, prints in 5 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order; ten of the same keyrings cost 99 CZK together, because they fit into the minimum. The door sign "Novákovi 12" with raised letters and a frame, 173 × 69 mm, weighs 30 g, prints in 46 minutes and costs 99 CZK too. It is our most often calculated tool: 33 calculations in 90 days, most of them ending in a download for the user’s own printer. You download the model free as an STL or a project for your printer; the two-colour version also as the plate and the letters as separate parts.',
    ],
    'steps' => [
        ['name' => 'Type the text', 'text' => 'In "First line" type the name or the text, the second and third lines are optional. The buttons under the fields insert symbols, a heart or a star say.'],
        ['name' => 'Pick the style and the shape', 'text' => 'Choose the style of the letters, the shape of the plate and the typeface, or start from a preset (keyring, door sign, name tag, ornament with an eyelet, name in a script). You can add a keyring eyelet, a raised frame or a bevelled top edge.'],
        ['name' => 'Set the size', 'text' => 'Set the letter height from 4 to 80 mm and the plate thickness from 1.2 to 10 mm. The outer size shows under the preview at once.'],
        ['name' => 'Order the print or download', 'text' => 'Order the print from our farm and pick the colours at the order. Or download the model free as an STL or a finished project for your own printer.'],
    ],
    'faq' => [
        ['q' => 'How big can the sign be?', 'a' => 'The size follows the letter height (4 to 80 mm), the length of the text and the margin around it (2 to 30 mm). You do not enter the plate width, it is computed and shown under the preview. If the model is too big for the printer, the calculation says so in the next step.'],
        ['q' => 'What do signs for a whole class or team cost?', 'a' => 'Ten 46 × 22 mm keyrings fit into the minimum order, 99 CZK together; you enter the number of pieces of one name in the calculation and make another design for other names. Bigger door signs are priced by grams and minutes, and you see the price before you order anything.'],
        ['q' => 'Can the letters be printed in another colour than the plate?', 'a' => 'Yes, with raised letters, the outline and the name without a plate. For a print from us you pick the second colour from those loaded in the same printer, and the printer changes it at the height where the letters begin. Recessed letters print in one colour.'],
        ['q' => 'How do I fix the sign to a door?', 'a' => 'The sign has no screw holes. The back is flat, so you stick it on, with double-sided tape say. Only the keyring version has a hole.'],
        ['q' => 'Which letters and characters does the tool know?', 'a' => 'Thirty typefaces (sans, serif, handwritten, decorative and mono), all with Czech accents, and a row of symbols under the text fields. If a character is missing in the chosen typeface, the tool says which one. Your own font cannot be uploaded.'],
        ['q' => 'Can I print the model myself?', 'a' => 'Yes. Download the model free as an STL, or as a finished project for about 270 printers with the filament change at the height of the letters. In the two-colour version you also download the plate and the letters as separate parts.'],
    ],
    'examples' => [
        'The keyring Jana with an eyelet and a frame, 46 × 22 mm, letters in a second colour: 2 g, 5 minutes, 99 CZK.',
        'The door sign Novákovi 12, 173 × 69 mm, raised letters and a frame: 30 g, 46 minutes, 99 CZK.',
        'The name Ela in a script without a plate, with a keyring eyelet, 37 × 19 mm.',
    ],
];
