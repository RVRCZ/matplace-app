<?php

// The page for the query "3d text generator for 3d printing" (docs/AP.md §12). 'heading' = the page H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D text generator for 3D printing: a standing word, free',
    'heading' => '3D text generator for 3D printing: a name or a word that stands',
    'description' => 'Type a name, a word or a message, pick one of 30 typefaces and get standing 3D text of thick letters on a foot. We print it, or you download the STL free.',
    'h1' => 'How the text is made, how big it comes out and what it costs',
    'intro' => [
        'The tool turns a text into lettering that stands by itself on a shelf, a desk or in a shop window. Type one to three lines of up to 40 characters, pick one of thirty typefaces and a capital height from 4 to 80 mm. The letters are 1.2 to 30 mm thick and stand on a common foot, so even a typeface whose letters do not touch holds together; lines one above another are joined by a rail. Add a silhouette from the library or your own SVG, next to the letters or above them. The text prints lying on its back without supports, so its front is flat and smooth. If it is too thin for its height and could tip over, the tool says so in the preview.',
        'An example from the production calculator (28 October 2026, PLA): the word HOME in Archivo Black with 30 mm letters 12 mm thick measures 144 × 34 mm, weighs 21.1 g, prints in 40 minutes and costs 99 CZK (about 4 EUR) from us without delivery (calculation /c/bkdbbtgjavzn). The two-line text KAVÁRNA u Jany in Bebas Neue, 20 mm thick, weighs 55.7 g, prints in 1 h 53 min and costs 130 CZK (calculation /c/qxcoca4ayqkb). You see the price before you order anything, for several pieces too. The model downloads free as an STL or a project for your printer, without registration.',
    ],
    'steps' => [
        ['name' => 'Type the text', 'text' => 'One to three lines; the first is the biggest. The symbols below the fields go into the text with one tap; add a picture from the library or upload an SVG.'],
        ['name' => 'Pick the typeface', 'text' => 'Thirty typefaces are shown as tiles with their names set in the face itself. Bold sans serifs stand most firmly, handwritten ones are more decorative.'],
        ['name' => 'Set the height and the thickness', 'text' => 'The letter height sets the size of the text, the thickness its depth. To stand, the text should be at least a fifth as deep as it is tall; the tool warns when it is thinner.'],
        ['name' => 'Order a print or download', 'text' => 'Next to the preview you see the dimensions; the calculation after "Continue" shows the precise price. The model and the slicer project are free to download without an account.'],
    ],
    'faq' => [
        ['q' => 'How big will the text be?', 'a' => 'The word HOME in 30 mm letters comes out 144 mm long, 34 mm tall and 12 mm deep. The length grows with the number of letters and the letter height; a text of up to 250 mm fits on the print bed. Split a longer text into two lines, the tool joins them with a rail.'],
        ['q' => 'Will it really stand?', 'a' => 'Yes, if it is at least a fifth as deep as it is tall; the tool warns when it is thinner. A one-line text 30 mm tall stands from 8 mm, two lines need 15 to 20 mm. A text with a tall picture above the letters is top-heavy, give it a third more depth.'],
        ['q' => 'What if the letters do not touch?', 'a' => 'That is fine. They all stand on the foot, and lines above one another are joined by a rail. Accents and dots that would hang in the air are held by a short tie, so a name with diacritics is one piece too.'],
        ['q' => 'Which typeface should I pick?', 'a' => 'A bold sans serif for a short word, such as Archivo Black or Bebas Neue. A handwritten one for a name, for example Pacifico or Lobster. Use very thin scripts from 30 mm up, otherwise the tool warns about lines thinner than 0.8 mm, which do not print cleanly.'],
        ['q' => 'Can the text be printed in two colours?', 'a' => 'Standing text is one piece in one colour. For a second colour, split the text into two designs and order each in another colour, or choose the sign, where the letters have another colour than the plate.'],
        ['q' => 'How do I print the text at home?', 'a' => 'On its back, front face down, without supports, 0.2 mm layers, PLA or PETG. The downloaded project for OrcaSlicer and PrusaSlicer has the text laid down already; from the STL lay it on its front face yourself. The underside of the foot stays smooth after it comes off the bed.'],
    ],
    'examples' => [
        'The word HOME in Archivo Black, 144 × 34 mm, 12 mm deep: 21.1 g, 40 minutes, 99 CZK.',
        'The name Ela in a script with a heart on the same foot, 143 × 48 mm, 15 mm deep.',
        'Two lines, KAVÁRNA u Jany, in Bebas Neue, 20 mm deep: 55.7 g, 1 h 53 min, 130 CZK.',
    ],
];
