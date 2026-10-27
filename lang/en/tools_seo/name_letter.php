<?php

// The page for the query "3d printed letter with name" (docs/AP.md §6). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed letter with a name: a custom decoration, free',
    'heading' => 'A big letter with a name as a gift',
    'description' => 'Type a name and get its big first letter with the whole name in a second colour. We print it in two colours from 99 CZK, or you download the model free.',
    'h1' => 'How the letter with a name is made, when it stands by itself and what it costs',
    'intro' => [
        'The tool makes the big first letter of a name and writes the whole name on it. Type a name of up to 20 characters: the letter picks itself, or you choose another, the initial of a surname say. You set the height from 60 to 200 mm and the thickness from 3 to 15 mm; from 10 mm most letters stand on a shelf by themselves, and any of them can stand in a base with a groove that prints next to it. The big letter comes in three bold typefaces, the name in thirty including handwritten ones. The tool places the name where the letter has the most room: across a horizontal stroke, or upright along a vertical one; it stands 0.4 to 2 mm proud and prints in the second colour. The two colours lie one above the other, so the print pauses once for a filament change; on our farm the letter prints in one go and you pick both colours at the order.',
        'An example from the production calculator (27 October 2026, PLA): a letter E 120 mm high and 8 mm thick with the name Ela in a script typeface weighs 27 g, prints in 40 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order (calculation /c/vhfifzrzy5in, one colour, the second you pick at the order). At 8 mm you stand it in the base from the same file, from 10 mm it stands by itself. It prints flat without supports. It suits a child’s door, a shelf, or a gift for a birth or a name day; the model and the project with the filament change are free to download, no sign-up.',
    ],
    'steps' => [
        ['name' => 'Type the name', 'text' => 'The big letter is the first letter of the name. If you want another, the initial of a surname say, type it into the second field.'],
        ['name' => 'Pick the typefaces', 'text' => 'For the big letter sans, serif or mono, always bold. For the name any of thirty typefaces.'],
        ['name' => 'Set the height, the thickness and the colours', 'text' => 'The preview shows where the name fitted and how big it is. Give the letter and the name a filament from those we have in stock.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price and print time show in the calculation after "Continue". The model and the project with the filament change are free to download, no sign-up.'],
    ],
    'faq' => [
        ['q' => 'Where does the name go on the letter?', 'a' => 'Where the letter has the most room. On letters with a vertical stroke (E, T, L, I, K) the name goes upright along it, on wide horizontal strokes across. The tool finds the place itself and the preview shows it at once.'],
        ['q' => 'What if the name does not fit?', 'a' => 'On round and narrow letters (O, S, C) the name comes out smaller. If it would be under 4 mm high, the tool warns you: a taller letter, a shorter name or another typeface of the big letter helps.'],
        ['q' => 'Can I write a date or a word instead of a name?', 'a' => 'Yes, any text of up to 20 characters: "Ela 2026", "The Smiths" or "Christmas". Then choose the big letter by hand in the second field so that it matches what the decoration should say.'],
        ['q' => 'Will the letter stand?', 'a' => 'From 10 mm thick most letters stand by themselves on a flat surface; thinner ones stand in the base with a groove that is in the file. Letters with a narrow foot, P or F say, are safer leaned or stuck to a door with double-sided tape.'],
        ['q' => 'What is it printed from and how many colours does it have?', 'a' => 'We recommend PLA. The letter has two colours: the body and the name that lies on it. One filament change is enough, so any printer prints it; with us you pick both colours at the order.'],
        ['q' => 'How big a letter can you print?', 'a' => 'Up to 200 mm high, the bed of our farm is 250 × 250 mm. The preview shows by the dimensions whether the letter fits the bed; wide letters such as M and W are wider at the same height.'],
    ],
    'examples' => [
        'A letter E 120 mm high with the name Ela in a script typeface along the vertical stroke: 27 g, 40 minutes, 99 CZK.',
        'A serif letter T 150 mm high with the name Tomáš.',
        'A letter I 100 mm high and 12 mm thick with the name Ivana, standing by itself.',
    ],
];
