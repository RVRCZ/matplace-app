<?php

// Landing pages (docs/AH.md §11): the blocks under the tool. "order" = Before you order (FAQ, 'farm' => true only
// while printing with us is offered), "who" = Who it is for. Placeholders are filled by App\Support\LandingPage.
return [
    'titles' => [
        'result' => 'How it turned out',
        'result_lead' => 'Real prints from our farm with their size, material, price and print time.',
        'order' => 'Before you order',
        'who' => 'Who it is for',
        'next' => 'Where next',
        'next_tools' => 'Similar tools',
        'next_blog' => 'Further reading',
    ],

    'calc' => [
        'order' => [
            ['q' => 'How long until the print arrives?', 'farm' => true, 'a' => 'The calculation shows the print time right after the upload. Printing starts as soon as a printer is free. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic. All times are estimates.'],
            ['q' => 'How much is delivery?', 'farm' => true, 'a' => 'With Packeta to a pickup point in the Czech Republic :cz_point, to your door :cz_home, to Slovakia from :sk_point, to other EU countries from :eu_point. The order shows the exact amount for your country and the weight of the parcel.'],
            ['q' => 'How exact are the size and the price?', 'a' => 'The calculation takes your file as it is: sizes in millimetres you can change, and the slicer’s exact result for print time and material. Standard quality prints in 0.2 mm layers. The model check warns about walls that are too thin and parts that do not fit the printer.'],
            ['q' => 'Can I just download the model and print it at home?', 'a' => 'Yes. “Download the model” gives you an STL file or a ready project for your printer (OrcaSlicer, Bambu Studio, PrusaSlicer) with the settings of the calculation.'],
            ['q' => 'What if the model is broken?', 'a' => 'The calculation still tries to price it, and the model check says what is wrong (holes, flipped or doubled faces). You can repair it free of charge with the Repair tool and go on with the repaired file.'],
            ['q' => 'What if I do not like the print?', 'farm' => true, 'a' => 'Before you order you see the 3D preview, the size, the print time and the price. We make every print to order, so it cannot be returned without a reason within 14 days. A defect (cracks, missing layers, a size other than ordered) is claimed under our complaints policy.'],
        ],
        'who' => [
            ['t' => 'A spare part from the internet', 'text' => 'You found a bracket, a cap or a wheel on Printables or Thingiverse. Upload the STL, see its size and print time, and decide whether to print it with us or at home.'],
            ['t' => 'Your own CAD model', 'text' => 'You designed a part in Fusion, Onshape or FreeCAD. The calculation checks the walls and the size, shows the material used and takes STEP files too.'],
            ['t' => 'You own a printer', 'text' => 'Download a project for your machine with the settings of the calculation. Two-colour models carry the filament change as well.'],
        ],
    ],

    'figure' => [
        'order' => [
            ['q' => 'How much is a printed bust and how big is it?', 'farm' => true, 'a' => 'A bust 80 mm high comes to 99 CZK with us, 120 mm to 130 CZK and 150 mm to 200 CZK (PLA+, one piece, without delivery; calculations on the live site, 17 Oct 2026). Change the size in the calculation and the price follows.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The model takes 2 to 4 minutes. An 80 mm bust prints in about an hour, a 150 mm one in just under three hours. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic.'],
            ['q' => 'What is the bust printed from, and in what colour?', 'a' => 'In one colour of PLA or PLA+. Matte shades suit figures best: they show the print layers less.'],
            ['q' => 'What if I do not like the model?', 'a' => 'You look at the model in 3D first. Within the daily limit (:guest without an account, :account with one) you can try another photo; the base, the name and the dedication change without a new generation.'],
            ['q' => 'Can I download the model and print it myself?', 'a' => 'Yes, as an STL or a project for your printer. The busts we printed needed no supports; the check in the calculation confirms it for your model.'],
            ['q' => 'How do I get the print?', 'farm' => true, 'a' => 'We send it with Packeta to a pickup point or an address in the EU (in the Czech Republic from :cz_point). Once we hand the parcel over you get an e-mail with a tracking link.'],
        ],
        'who' => [
            ['t' => 'A gift with a personal dedication', 'text' => 'A 120 mm bust with a name and a dedication on its base, for a birthday, an anniversary or as a thank-you. It fits a shelf or a desk.'],
            ['t' => 'A colleague, a coach, a teacher', 'text' => 'For a farewell or the end of a season: one photo from the front is enough, and the team’s signature fits the base.'],
            ['t' => 'For yourself', 'text' => 'Your own 80 mm figure for the desk, or a blank to paint with acrylics.'],
        ],
    ],

    'pet' => [
        'order' => [
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The model takes 2 to 4 minutes, printing one to three hours depending on the size. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic.'],
            ['q' => 'What is the figure printed from, and in what colour?', 'a' => 'In one colour of PLA or PLA+. The fur and the details are in the shape, not in the colour; matte shades show them best.'],
            ['q' => 'What if I do not like the model?', 'a' => 'You look at the model in 3D first. Within the daily limit (:guest without an account, :account with one) you can try another photo or another style; the base changes without a new generation.'],
            ['q' => 'Can I download the model and print it myself?', 'a' => 'Yes, as an STL or a project for your printer. The check in the calculation warns about thin legs, ears or tails that would print badly.'],
            ['q' => 'How do I get the print?', 'farm' => true, 'a' => 'We send it with Packeta to a pickup point or an address in the EU (in the Czech Republic from :cz_point). Once we hand the parcel over you get an e-mail with a tracking link.'],
        ],
        'who' => [
            ['t' => 'A gift for dog and cat people', 'text' => 'An 80 to 100 mm figure with the pet’s name on the base, for a shelf or next to the bowl.'],
            ['t' => 'In memory', 'text' => 'From a favourite photo of a pet who is no longer with you. One sharp photo of the whole animal from the side or the front is enough.'],
            ['t' => 'For the car or the desk', 'text' => 'A smaller figure around 60 mm that fits next to a monitor or on the dashboard.'],
        ],
    ],

    'qr' => [
        'order' => [
            ['q' => 'How big must the sign be to scan?', 'a' => 'On a table or a counter the default 70 mm is enough; one module of the code is then about 2 mm. On a wall or at the entrance, where it is read from further away, choose a bigger sign. At every size the tool shows the module size and checks that the code reads.'],
            ['q' => 'How many can I order at once?', 'farm' => true, 'a' => 'Up to 64 in one order. The calculation shows how many fit on one plate (the default sign: 6 on 250 × 250 mm); further plates print one after another.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The default 70 mm sign prints in about 25 minutes. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'Will the sign last outdoors?', 'a' => 'On a table and indoors PLA is enough. For direct sun and rain choose a material marked for outdoor use in the material comparison of the calculation.'],
            ['q' => 'What if the code does not scan?', 'a' => 'Try the link with your phone from the preview on the screen before you order. The tool checks the module size and the contrast; if the print does not scan because of a printing defect, you claim it.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only to order a print from us.'],
        ],
        'who' => [
            ['t' => 'Cafés and restaurants', 'text' => 'The menu or table ordering: a 70 mm sign with a stand on every table, the code leads to your menu.'],
            ['t' => 'Holiday rentals and Airbnb', 'text' => 'Wi‑Fi for guests without typing the password: a 100 mm sign on the shelf by the door, one scan and they are online.'],
            ['t' => 'Shops, stalls, salons', 'text' => 'A link to your online shop, Google reviews or bookings: a sign at the till next to the card terminal.'],
        ],
    ],

    'organizer' => [
        'order' => [
            ['q' => 'How do I measure the drawer?', 'a' => 'Measure the inside width and depth at the narrowest point (the corners at the bottom are often rounded) and take off 2 to 3 mm so the organiser goes in and out. Choose a height a few millimetres lower than the drawer, or it will not close.'],
            ['q' => 'How exact are the sizes?', 'a' => 'A printer keeps sizes to a few tenths of a millimetre, and a large part may shrink a little as it cools. So allow 2 to 3 mm of play all round.'],
            ['q' => 'How much is it and how long does it print?', 'farm' => true, 'a' => 'Mostly a matter of size and height. An organiser of 200 × 120 × 40 mm with 3 × 2 compartments prints in about two and a half hours and comes to 250 CZK (PLA+, without delivery). The calculation shows the exact time and price before you order anything.'],
            ['q' => 'Will a large organiser fit the printer?', 'a' => 'The calculation shows straight away whether it fits the printer. If it is too big, split the drawer into two organisers or use the modular trays.'],
            ['q' => 'Which material?', 'a' => 'PLA is enough for a drawer in an office or a workshop. For the bathroom or near the oven choose PETG, which copes better with heat and damp.'],
            ['q' => 'What if it does not fit?', 'farm' => true, 'a' => 'We make the print to your sizes, so it cannot be returned without a reason. If it does not match the sizes of the order, that is a defect and you claim it.'],
        ],
        'who' => [
            ['t' => 'Workshop', 'text' => 'Bits, screws and wall plugs in the bench drawer: a grid of 4 × 3 compartments, about 40 mm high.'],
            ['t' => 'Kitchen', 'text' => 'Cutlery, spices and small tools: an organiser exactly as wide as the drawer, in PETG.'],
            ['t' => 'Desk', 'text' => 'Pens, chargers and cables: a shallow organiser of 20 to 30 mm so the drawer still closes well.'],
        ],
    ],

    'box' => [
        'order' => [
            ['q' => 'How do I measure what goes inside?', 'a' => 'Measure the item at its widest point and add 1 to 2 mm on each side. The tool takes the inside sizes and works out the outside from the walls and the bottom.'],
            ['q' => 'How exact is the fit?', 'a' => 'A printer keeps sizes to a few tenths of a millimetre. The lid has 0.25 mm of play, which you can change; try it on the first box of a new set.'],
            ['q' => 'How much is it and how long does it print?', 'farm' => true, 'a' => 'A box of 84 × 54 × 32 mm with its lid prints in about 35 minutes and comes to 99 CZK (PLA+, without delivery). For other sizes the calculation shows the price and the time before you order anything. We dispatch on the day the print ends, on working days.'],
            ['q' => 'Which material?', 'a' => 'PLA is enough for small things and electronics inside. For heat or damp choose PETG.'],
            ['q' => 'How much is delivery?', 'farm' => true, 'a' => 'With Packeta to a pickup point in the Czech Republic :cz_point, to your door :cz_home, to Slovakia from :sk_point, to other EU countries from :eu_point.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only to order a print from us.'],
        ],
        'who' => [
            ['t' => 'Electronics', 'text' => 'A case for an Arduino, an ESP32 or a power supply, with holes for cables and a cut-out for the connector.'],
            ['t' => 'Small things', 'text' => 'Earrings, SD cards, spare buttons: a small box with a lid, exactly to size.'],
            ['t' => 'Gift wrapping', 'text' => 'A box made to fit the gift, in the colour you choose, with a slot for a ribbon if you like.'],
        ],
    ],
];
