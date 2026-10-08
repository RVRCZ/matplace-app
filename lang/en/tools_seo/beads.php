<?php

return [
    'title' => 'Letter beads with a name for 3D printing',
    'description' => 'Type a name and get a bead for every letter: a cube, a ball, a heart or a star with a hole for a cord. We print them, or you download the model.',
    'h1' => 'Letter beads: a name on a cord or a bracelet',
    'intro' => [
        'The tool makes a bead for every character of a text. Type a name or a word of up to 16 characters, pick the shape of the bead, a cube, a ball, a heart or a star, and a size from 8 to 14 mm. Every bead has a hole for a cord, 1.5 to 4 mm across, running from side to side, so threaded beads lie next to each other and the name can be read. A space in the text is a bead without a letter.',
        'The letter is on the top face: raised by 0.4 to 1.2 mm in a second colour, or engraved. Optionally it is engraved underneath too, so the bead reads from both sides. All the beads print together on one bed. Raised letters mean one filament change, so we print two-colour beads on our farm as well, and you choose both colours when ordering.',
    ],
    'steps' => [
        ['name' => 'Type a name or a word', 'text' => 'Up to 16 characters including digits and symbols, a heart for example. Every character is one bead; a space is an empty bead.'],
        ['name' => 'Choose the shape and the letter', 'text' => 'A cube, a ball, a heart or a star. The letter raised in another colour, or engraved; the typeface sans, serif or monospace, always bold.'],
        ['name' => 'Set the size and the hole', 'text' => 'The size of the bead and the diameter of the hole to suit your cord. Under the preview you see how many beads there are and how big each one is.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the size and a rough price, the exact price and print time one step further. The model and the project with the filament change are free to download, without registration.'],
    ],
    'faq' => [
        ['q' => 'How big a hole should I choose?', 'a' => 'For elastic or a thin cord 2 mm is enough, for leather and thicker string 3 to 4 mm. At least 1.2 mm of plastic must stay above and below the hole; if that does not fit the bead, the tool says so.'],
        ['q' => 'Are the beads suitable for small children?', 'a' => 'Beads are small parts and are not for children under three. For older children choose a bigger size, 12 to 14 mm, and a strong cord.'],
        ['q' => 'What are the beads printed from?', 'a' => 'We recommend PLA. They print letter up and without supports; a printer bridges a horizontal hole of up to 4 mm by itself.'],
        ['q' => 'Do you print them in two colours?', 'a' => 'Yes. Raised letters lie on the beads, so one filament change is enough. You choose both colours when ordering from the filaments we have in stock.'],
        ['q' => 'Can I order a whole alphabet?', 'a' => 'One design has 16 beads at most. Split the alphabet into two designs. The quantity of an order multiplies the whole design.'],
        ['q' => 'How do I pay and how do I get the beads?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print through Packeta to a pick-up point or to an address in the EU.'],
    ],
    'examples' => [
        'Four cubes of 10 mm with the letters JANA raised in a second colour.',
        'Six balls of 12 mm with the word MÁMA and a heart; the space is a bead without a letter.',
        'Five hearts with the engraved text TOM 7.',
    ],
];
