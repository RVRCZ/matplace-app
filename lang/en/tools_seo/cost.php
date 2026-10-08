<?php

return [
    'title' => 'Cost of 3D printing on your own printer: a calculator',
    'description' => 'What a print really costs you: filament, electricity, the printer\'s wear, failed prints and your work. A suggested price with margin, and beside it our price.',
    'h1' => 'What a print costs on your printer and what to sell it for',
    'intro' => [
        'The calculator adds up everything hidden in one piece: the filament by the grams from the slicer, the power by the printer\'s draw and the print time, the printer written off over the hours of its life, the share of failed prints the good ones pay for, your work in minutes and other costs such as packaging or a label. The result is the cost per piece and a suggested price with the margin you choose.',
        'From a print calculation on matplace the grams and hours are filled in, and beside your cost you see what we print the same piece for. A printer signed in gets their hourly rate and filament price from their profile. Everything is computed in the browser, nothing is stored on the server, and the page remembers the settings until the next visit.',
    ],
    'steps' => [
        ['name' => 'Enter the piece', 'text' => 'The filament price per kilogram, the grams and hours from the slicer. From a print calculation they are filled in by themselves.'],
        ['name' => 'Enter the printer', 'text' => 'Power draw, electricity price, the printer\'s price and the hours it prints before you write it off; the share of failed prints.'],
        ['name' => 'Enter the work', 'text' => 'Your hourly rate and the minutes per piece: setting up, taking off, cleaning, packing; other costs per piece.'],
        ['name' => 'Choose the margin', 'text' => 'A percentage above the cost. The page shows the cost, the price and what an hour of printing earns at once; send the price on to the seller\'s profit or the plan.'],
    ],
    'faq' => [
        ['q' => 'Why count failed prints?', 'a' => 'Every failed print used filament, power and the printer\'s time. If one in twenty fails, the nineteen good ones carry it; the calculator adds that to the cost per piece.'],
        ['q' => 'How do I estimate the printer\'s life?', 'a' => 'A desk printer runs thousands of hours before a bigger repair or a replacement. 5,000 hours is about three years at a few hours a day; give a cheap printer less, an industrial one more.'],
        ['q' => 'What is the right margin?', 'a' => 'The margin pays the platform\'s fees, extra shipping and the risk. For selling on Etsy or Fler a margin of 40 to 100 % is usual; the seller\'s profit tool shows it exactly.'],
        ['q' => 'Where do I get the grams and hours?', 'a' => 'From the slicer after slicing the model, or from a print calculation on matplace: upload the model, the calculation slices it and a button brings you here with the numbers filled in.'],
        ['q' => 'What does "print it with us" mean?', 'a' => 'The price from the calculation for the same piece on our farm, material and time included. It does not include your work or failed prints; it helps to see whether printing at home pays.'],
        ['q' => 'Do you store my numbers?', 'a' => 'No. The calculator computes in the browser and remembers the settings only in your browser. Nothing goes to the server.'],
    ],
    'examples' => [],
];
