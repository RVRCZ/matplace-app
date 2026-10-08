<?php

return [
    'title' => 'Pen holder in the shape of a name for 3D printing',
    'description' => 'Type a name and get a pen holder in its shape: the letters are the walls, with room for pens between them. We print it, or you download it for free.',
    'h1' => 'Pen holder in the shape of a name',
    'intro' => [
        'The tool makes a pen holder that has the shape of a name. Type a name of up to 14 characters and pick a typeface; the script works best, as its letters join into one wide pocket. The width goes from 80 to 250 mm and the height from 40 to 120 mm. The wall is 1.6 mm thick and the bottom 2 mm; both can be changed.',
        'The pocket follows the letters, widened so that pencils, pens and scissors fit; the preview tells how wide its widest place is. The eyes of letters such as e or a are filled, and letters that do not touch are joined with a bar. With a base the holder stands more firmly, and the base can have another colour, for which one filament change is enough.',
    ],
    'steps' => [
        ['name' => 'Type the name', 'text' => 'A short name gives a wider pocket. For a longer one make the holder wider so that room for pencils remains.'],
        ['name' => 'Choose the typeface', 'text' => 'The script gives one continuous pocket. With sans, serif and monospace the letters are separate pockets; where they do not touch, a bar joins them.'],
        ['name' => 'Set the size and the base', 'text' => 'Change the width and the height with the sliders or by dragging the arrows in the preview. Under the size you see the width of the pocket; if it is too narrow for a pencil, the tool says so.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the size and a rough price, the exact price and print time one step further. The model and the slicer project are free to download, without registration.'],
    ],
    'faq' => [
        ['q' => 'Do pencils fit into the holder?', 'a' => 'A pencil is 7 to 8 mm, so it needs a pocket at least 9 mm wide. The tool shows the width of the widest place of the pocket and warns about a narrow one. For the name Jana 160 mm wide, the widest place of the pocket is over 30 mm.'],
        ['q' => 'How tall should the holder be?', 'a' => 'For crayons and short pencils 60 mm is enough, for ordinary pencils and pens 80 to 90 mm. The taller the holder, the more a base pays off.'],
        ['q' => 'What is the holder printed from?', 'a' => 'We recommend PLA. It prints standing and without supports; a 1.6 mm wall is four perimeters of a 0.4 mm nozzle, which is firm even for a tall holder.'],
        ['q' => 'Can the base be another colour?', 'a' => 'Yes. The base is the bottom 3 mm of the print, so one filament change at that height is enough. On our farm you choose both colours when ordering.'],
        ['q' => 'How long a name fits the bed?', 'a' => 'The holder can be up to 250 mm wide, which is the bed of our farm. The preview says next to the size whether it fits.'],
        ['q' => 'How do I pay and how do I get the holder?', 'a' => 'You pay from prepaid credit that you top up by card; prices are shown in Czech crowns or euros. We send the print through Packeta to a pick-up point or to an address in the EU.'],
    ],
    'examples' => [
        'A holder with the name Jana in script, 160 mm wide and 80 mm tall, with a base in another colour.',
        'A holder TOM in sans, 150 × 90 mm, every letter a pocket of its own.',
        'A low holder Ela for crayons, 110 × 60 mm, without a base.',
    ],
];
