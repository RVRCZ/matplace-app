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

    // the search entry pages of session N1 (docs/AP.md): SVG to STL, repair, check
    'svg_to_stl' => [
        'order' => [
            ['q' => 'How high should I pull the shape so that it prints and holds?', 'a' => 'At least 0.6 mm, that is three layers of 0.2 mm; a tag or a door sign takes 2 to 3 mm, a key pendant 4 to 6 mm, and a shape that should stand on its own 20 mm or more. The higher, the more grams and minutes; the calculation recounts after every change.'],
            ['q' => 'How thin can the lines be?', 'a' => 'A 0.4 mm nozzle needs two lines side by side, so at least 0.8 mm. The tool marks lines under 0.8 mm in advance; thicken them in your editor or make the shape bigger.'],
            ['q' => 'How much does a printed shape cost and how long does it print?', 'farm' => true, 'a' => 'The 80 mm cat pulled up to 6 mm weighs 9.6 g and prints in 17 minutes, 99 CZK from us, which is the minimum order (PLA, without delivery). A bigger or higher shape shows its grams, minutes and price in the calculation before you order anything.'],
            ['q' => 'Can the motif be in a different colour than the base?', 'a' => 'An extruded outline is one colour. If the motif should sit on a plate in another colour, switch the style to a relief: the colours then lie one above the other and a printer manages them with a filament change at the height of the plate, at our farm or at home.'],
            ['q' => 'How long does delivery take?', 'farm' => true, 'a' => 'We dispatch on the day the print ends, on working days. Packeta usually delivers within 1 to 2 working days in the Czech Republic, to a pickup point for :cz_point, to your door for :cz_home; to Slovakia from :sk_point, to the EU from :eu_point.'],
            ['q' => 'Can I edit the result further?', 'a' => 'Yes. The STL is an ordinary model: you scale it in the calculator, the Puzzle tool cuts it into pieces, the Mold tool makes a casting mold of it, and the Cookie cutter tool makes a cutter from the same outline.'],
        ],
        'who' => [
            ['t' => 'A company or club logo', 'text' => 'A logo from a vector file as a desk sign, a key pendant or an emblem on a door, 60 to 120 mm, exactly as the artwork.'],
            ['t' => 'An exact part from a 2D drawing', 'text' => 'A spacer, washer, template or insert you have drawn in a CAD program or in Inkscape: the width in millimetres, holes stay holes.'],
            ['t' => 'A drawing or an icon', 'text' => 'A child’s drawing turned into an outline, an icon from the Noun Project or a silhouette from our library as an ornament, a magnet or a pendant.'],
        ],
    ],

    'repair' => [
        'order' => [
            ['q' => 'How do I know the model needs a repair?', 'a' => 'The slicer reports non-manifold, open edges or a mesh that is not watertight, pieces of the surface are missing in the preview, or the print has holes and extra layers. If you are not sure, upload the model to the check first; it says what is wrong and offers the repair with one button.'],
            ['q' => 'How long does the repair take and how big a file can it handle?', 'a' => 'A file up to 120 MB. An ordinary model is done in a few seconds, a mesh with millions of triangles within a minute; the page says what is going on.'],
            ['q' => 'How much does printing the repaired model cost?', 'farm' => true, 'a' => 'The repair costs nothing. A print from us is priced by the calculation from the grams and the minutes; the minimum order is 99 CZK, and the 60 × 52 × 78 mm bust of the example comes to around 110 CZK (PLA, without delivery).'],
            ['q' => 'Does the repair help a model for resin printing too?', 'a' => 'Yes, an STL is the same for FDM and resin, and both need a closed mesh. For resin, hollow the model after the repair with drain holes: you save resin and the part will not crack.'],
            ['q' => 'How do I get the print?', 'farm' => true, 'a' => 'We send it by Packeta to a pickup point (in the Czech Republic :cz_point) or to an address in the EU. We dispatch on the day the print ends, on working days; as soon as we hand the parcel over, an e-mail with tracking arrives.'],
            ['q' => 'What if the slicer still reports an error after the repair?', 'a' => 'The report says what could not be done: a large missing piece of the surface or a wall without thickness cannot be rebuilt. The rest is manual work in Blender or Meshmixer; a slicer often passes over small faults and the print comes out fine.'],
        ],
        'who' => [
            ['t' => 'A model from the internet', 'text' => 'A holder, a cap or a figurine from Printables, Thingiverse or MakerWorld that the slicer refuses. The repair takes seconds and does not change the size.'],
            ['t' => 'A scan from a phone', 'text' => 'Photogrammetry and scanners leave holes and specks. The repair closes them and joins the fragments; the shape of the scan stays.'],
            ['t' => 'An export from CAD or from a game', 'text' => 'A file from SketchUp, Tinkercad or a game model with flipped and doubled faces. The repair turns and removes them.'],
        ],
    ],

    'check' => [
        'order' => [
            ['q' => 'Does the check look at wall thickness too?', 'a' => 'Only the thinnest dimension of the whole model: under 0.8 mm is an error, because a 0.4 mm nozzle cannot print such a wall. A thin spot inside an otherwise thick model is not found by the check; the slicer shows that.'],
            ['q' => 'What happens to a model that does not pass?', 'a' => 'Holes and flipped faces go to the repair with one button. A model bigger than the printer is scaled down in the calculator, or split into parts with pins. Wrong units are solved by saving the file again in millimetres.'],
            ['q' => 'If the check passes, can I order the print right away?', 'farm' => true, 'a' => 'Yes. The price button opens the model in the calculator, where you see the grams, the print time and the price; the minimum order is 99 CZK. The same check runs in the calculation, so nothing is reported twice.'],
            ['q' => 'Are STEP, 3MF and OBJ checked too?', 'a' => 'Yes. A STEP from a CAD program is converted to a mesh and checked like an STL; in a 3MF the shape is checked, the colours and print settings are left aside.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The calculation shows the print time. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'Do you keep my file?', 'a' => 'The file serves the check, the repair and the calculation and is passed to nobody else. The check does not touch it; the model you download is the same file you uploaded.'],
        ],
        'who' => [
            ['t' => 'Before printing at home', 'text' => 'A downloaded model before slicing: in a few seconds you know whether it has the right units, a closed mesh and fits the bed.'],
            ['t' => 'A club, a school, a FabLab', 'text' => 'Files from students made in Tinkercad and SketchUp: the report tells everyone what to fix, with no program to install.'],
            ['t' => 'Before ordering from us', 'text' => 'Every model on our farm goes through the same check. Whoever runs it in advance knows the order will go through without questions.'],
        ],
    ],

    // the search entry pages of session N1, batch 2 (docs/AP.md §4): hollow, split, G-code
    'hollow' => [
        'order' => [
            ['q' => 'How much do I save on the print price with you?', 'farm' => true, 'a' => 'It depends on the size. A cylinder of ⌀ 120 × 200 mm cost 910 CZK solid and 500 CZK hollowed with a 2.5 mm wall (PLA+, without delivery); for a small part the price does not change, because the minimum order is 99 CZK. The tool shows the difference right after hollowing and the calculation confirms it.'],
            ['q' => 'Does a hollow model print without supports?', 'a' => 'On the outside exactly like a solid one. Inside, the cavity has a ceiling that the slicer either bridges or props up with inner supports; you shake those out through the drain holes after printing. For FDM choose a 2 to 3 mm wall, the slicer still fills it according to its settings.'],
            ['q' => 'How do I hollow a model for resin printing?', 'a' => 'A 2 to 3 mm wall and at least two holes of 3 to 4 mm in the bottom, so that the resin drains and no vacuum forms. A solid cylinder of ⌀ 120 × 200 mm would swallow 2.3 litres of resin, a hollow one about a quarter of a litre.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A hollow model prints in less time: the cylinder of the example in 6 h 36 min instead of 8 h 14 min. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'Can I weight the cavity with sand?', 'a' => 'Yes. Pour sand or shot in through the holes in the bottom and seal them with a glue gun or a piece of plastic. A paperweight, a flag stand or a figurine then stands firmly and the model stays light to print.'],
            ['q' => 'Is hollowing free, and do I need an account?', 'a' => 'Hollowing and the download of the hollow model are free and need no account; the file serves only the computation and is passed to nobody. You need an account only when you have the model printed by us.'],
        ],
        'who' => [
            ['t' => 'A large figure or bust', 'text' => 'Over 150 mm a cavity saves hundreds of grams; a model over 250 mm you also split into parts that fit the printer.'],
            ['t' => 'Resin printing', 'text' => 'A solid part cracks and swallows litres. Hollow with a 2 to 3 mm wall and drain holes it prints cleanly and cheaply.'],
            ['t' => 'A paperweight or a stand', 'text' => 'A cavity for sand or shot: a figurine, a flag stand or a base stands firmly and the print stays quick.'],
        ],
    ],

    'split' => [
        'order' => [
            ['q' => 'How much does printing the parts cost with you?', 'farm' => true, 'a' => 'The calculation adds up all the parts and the pins: the cylinder of ⌀ 120 × 320 mm in two parts came to 1,440 CZK (814 g, PLA+, without delivery). You order the parts in one order and get them together.'],
            ['q' => 'How many cuts will it take?', 'a' => 'The tool says after the upload: a figure 60 cm tall needs two cuts for a 250 mm bed, that is three parts. You can move the planes to where the joint hides, say the waist or the edge of the base.'],
            ['q' => 'Which pins do I use and how many will there be?', 'a' => 'The pins are 6 mm in diameter and the holes have 0.2 mm of play. The tool puts as many into each joint as fit (the cylinder of the example got four) and adds them to the file as a separate part; wooden 6 mm dowels do the job too.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The calculation adds up the time of all the parts; the cylinder of the example printed in 13 h 8 min. The parts print one after another, we dispatch on the day the last one ends, on working days; Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'Can I scale the model up at the same time?', 'a' => 'That is what the Life size tool is for: you enter the height in centimetres and the model is scaled and split in one go. Here the model is only cut; change the size beforehand in the calculator.'],
            ['q' => 'Is the split free, and do I need an account?', 'a' => 'The split and the download of the parts are free and need no account; the file serves only the computation and is passed to nobody. You need an account only when you have the parts printed by us.'],
        ],
        'who' => [
            ['t' => 'A life-size figure', 'text' => 'A statue, a bust or a game character over 250 mm: parts with pins, a number in the cut and a map of how they go together.'],
            ['t' => 'Cosplay and props', 'text' => 'A helmet, a sword or a shield longer than the bed: dovetail keys hold without glue and the parts travel taken apart.'],
            ['t' => 'A long part', 'text' => 'A rail, a cover or a holder longer than 250 mm: one cut, two pins, and a sanded and painted joint does not show.'],
        ],
    ],

    'gcode' => [
        'order' => [
            ['q' => 'How much does printing a model from G-code cost with you?', 'farm' => true, 'a' => 'As with any model: the calculation slices it for our printers and prices it from the grams and the minutes; the time and grams in the G-code header are only a guide. The minimum order is 99 CZK (PLA, without delivery).'],
            ['q' => 'Can you not just print my G-code?', 'a' => 'No. G-code is written for one printer and its settings; our farm prints from a model it slices for its own machine and material. The conversion to a model makes that possible, and you can change the size or the colours on top.'],
            ['q' => 'Why is the surface of the model stepped?', 'a' => 'The model carries the layers of the print it came from, 0.2 mm say. In printing the steps are covered by new layers and do not show; after scaling the model up, sand them or print with a finer layer.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The calculation shows the print time. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'I have a .gcode.3mf from Bambu Studio or a .bgcode from Prusa. What now?', 'a' => 'A .gcode.3mf file is a zip: unpack it and upload the .gcode from its Metadata folder. The tool does not read binary .bgcode; in PrusaSlicer switch binary G-code off in the printer settings and export again, or convert the file with Prusa’s bgcode tool.'],
            ['q' => 'Is the conversion free, and do I need an account?', 'a' => 'The conversion and the download are free and need no account; the G-code serves only the conversion and the calculation and is passed to nobody. You need an account only when you have the model printed by us.'],
        ],
        'who' => [
            ['t' => 'A lost source model', 'text' => 'The print came out well but the STL is gone. From the G-code on the SD card comes a model you print again, scale or recolour.'],
            ['t' => 'A file from someone else', 'text' => 'You only got a G-code for a different printer. As a model you slice it for yours, or have it printed by us.'],
            ['t' => 'An archive on the SD card', 'text' => 'Old G-codes from the printer as models in a catalogue: you see the shape, the size and the price of another piece.'],
        ],
    ],

    // the search entry pages of session N1, batch 3 (docs/AP.md §5): lithophane, map
    'relief' => [
        'order' => [
            ['q' => 'How much does a lithophane cost from you?', 'farm' => true, 'a' => 'A 100 × 75 mm panel with a table stand came to 100 CZK, a lamp for an E27 socket 100 mm across and 150 mm high to 250 CZK (without delivery, calculation of 18 October 2026). We print in a white material; the exact price for your photo and size shows in the calculation before you order anything.'],
            ['q' => 'What light goes into the lamp?', 'a' => 'An E27 or E14 LED bulb of about 10 W at most; no incandescent or halogen bulb, PLA softens at around 55 °C. Warm white gives the picture softer shades, cool white a sharper contrast. An open base takes an LED strip, a closed one an LED candle.'],
            ['q' => 'How do I print a lithophane at home?', 'a' => 'The panel upright and solid, the lamp upright on its base, the relief flat without supports; the downloaded slicer project carries the settings we use. The material is white PLA; any other colour tints or darkens the picture.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A 100 × 75 mm panel prints in about 1 h 9 min, a 150 mm lamp in about 2 h 29 min. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'Is a phone photo enough?', 'a' => 'Yes. On a 100 mm plate only as much detail as a 0.4 mm nozzle gives can show anyway; what counts is contrast and one main subject, not resolution. Set the brightness and contrast sliders so that the tool reports as many shades as possible, and the backlit preview shows the result.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download; the photo is not kept and only the shape stays in the model. You need an account only when you have the lithophane printed by us.'],
        ],
        'who' => [
            ['t' => 'A gift from a photo', 'text' => 'A portrait, a wedding or family photo as a 100 × 75 mm panel with a stand for a shelf or a window; the picture appears when the sun or a lamp shines through it.'],
            ['t' => 'A lamp for a child’s room', 'text' => 'The photo all round a cylinder with an E27 LED bulb: a white lamp by day, a lit picture in the evening.'],
            ['t' => 'A relief for the wall', 'text' => 'A photo or a logo as a raised picture that shows without light; as a keepsake, for the office or as a sign.'],
        ],
    ],

    'map' => [
        'order' => [
            ['q' => 'How much does a map cost from you?', 'farm' => true, 'a' => 'The Old Town of Prague, 500 m on a 150 mm plate, came to 240 CZK in one colour (85 g, without delivery, calculation of 18 October 2026). You pick the colours of the base, the roads and the buildings at the order; you see the price before you order anything.'],
            ['q' => 'Which extent should I choose?', 'a' => 'For a centre or a district 500 m, for a small town 1 km, for a big city 2 km; on a 150 mm plate of a kilometre a 20 m building is 3 mm high. Choose a larger area as a landscape of 2 to 20 km, where the terrain relief is the main thing and towns are low blocks.'],
            ['q' => 'How do I print the map at home?', 'a' => 'Flat, without supports, 0.2 mm layers. The 3MF project for OrcaSlicer and PrusaSlicer carries two filament changes (roads, buildings); the printer pauses, you swap the spool and the print goes on. The STL is one body for a single-colour print.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A 150 mm map prints in about 3 h 42 min. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'My town has no building heights in the map. What then?', 'a' => 'Buildings without data get the height you enter, 8 m for family houses say. The preview of the area shows which buildings and streets OpenStreetMap has; anyone can add what is missing to OpenStreetMap, and the map takes it over once the data is refreshed.'],
            ['q' => 'What goes on the frame?', 'a' => 'A raised name you enter: the name of the city, a street or a dedication. The frame can also be switched off, and the plate then ends at the edge of the map.'],
        ],
        'who' => [
            ['t' => 'A gift of a home town', 'text' => 'The centre where someone grew up or where the wedding was: 500 m on a 150 mm plate with the name on the frame, in three colours.'],
            ['t' => 'A district, a campus, a site', 'text' => 'For architects, schools and site managers: buildings with real heights and streets by kind, within a minute and without drawing.'],
            ['t' => 'A landscape for a trip', 'text' => 'A hill, a dam or a valley as a relief with exaggeration, with roads and water on the surface; 2 to 20 km on one plate.'],
        ],
    ],

    // the Christmas tools of session N1 (docs/AP.md §6): gingerbread, ornament, cutter, nameplate, topper, letter
    'gingerbread' => [
        'order' => [
            ['q' => 'When should I order so that the gingerbreads arrive before Christmas?', 'farm' => true, 'a' => 'A 90 mm gingerbread prints in 20 minutes and printing starts as soon as a printer is free; we dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic. In December keep a reserve and order at least a week ahead.'],
            ['q' => 'How do I hang the gingerbread?', 'a' => 'A ribbon, a string or an ornament hook goes through the eyelet at the top with its 3 to 6 mm hole. A 90 mm gingerbread weighs about 11 g, so it hangs even on a thin branch; move the eyelet to where the shape balances, the point of a star say.'],
            ['q' => 'Which colours for the body and the icing?', 'a' => 'The classic is a brown body with white icing; red with gold, green with white or navy with silver look good too. Matte filaments hide the print layers better than glossy ones. You pick from the spools we have in stock right now.'],
            ['q' => 'How much is delivery?', 'farm' => true, 'a' => 'With Packeta to a pickup point in the Czech Republic :cz_point, to your door :cz_home, to Slovakia from :sk_point, to other EU countries from :eu_point. Several gingerbreads in one order travel in one parcel.'],
            ['q' => 'How do I print the gingerbread at home?', 'a' => 'Flat, face up, without supports, from PLA. The downloaded project for OrcaSlicer, Bambu Studio or PrusaSlicer carries a pause at the height of the icing: the printer stops, you swap the spool and the print goes on in white. A printer with several spools does it by itself.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the gingerbreads printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Tree tags for the whole family', 'text' => 'Everyone gets their own gingerbread with a name: one costs 99 CZK, six of the same 140 CZK, another design for other names.'],
            ['t' => 'A gift for a teacher or colleagues', 'text' => 'A 60 mm gingerbread with a name on a ribbon instead of a gift tag; after unwrapping it hangs on the tree.'],
            ['t' => 'A nursery or a club', 'text' => 'Ornaments with the children’s names from your own printer: a project with one filament change, 20 minutes a piece.'],
        ],
    ],

    'ornament' => [
        'order' => [
            ['q' => 'How much does an ornament cost from you and how long does it print?', 'farm' => true, 'a' => 'An 80 mm gingerbread man prints in 18 minutes and costs 99 CZK, which is the minimum order; six pieces come to 160 CZK, that is 27 CZK per ornament (PLA+, without delivery, calculation of 18 October 2026). The calculation shows the price of your ornament before you order anything.'],
            ['q' => 'Can I make an ornament from a child’s drawing?', 'a' => 'Yes. Photograph the drawing on white paper from above in daylight, the tool removes the background and merges the colours into 2 to 4 filaments; thicken faint pencil lines with a marker. The ornament then follows the outline of the drawing, or you put it in a circle.'],
            ['q' => 'How long does delivery take?', 'farm' => true, 'a' => 'We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point). Before Christmas, order in good time.'],
            ['q' => 'How do I print the ornament at home in several colours?', 'a' => 'Flat, face up, without supports. The downloaded project carries filament changes at the heights of the colours; with one nozzle you swap spools at the pause, a printer with AMS, MMU or ACE swaps them by itself.'],
            ['q' => 'Will the ornament last outdoors or by the fireplace?', 'a' => 'For a tree indoors PLA is enough. Near a radiator or a fireplace choose PETG, which takes more heat; a thin ornament does not suit sun and frost outdoors, and colours fade in direct sun.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the ornaments printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A tree of children’s drawings', 'text' => 'Every drawing as a 70 to 90 mm ornament in three colours with a ribbon eyelet; from one photo of the drawing in seconds.'],
            ['t' => 'A company gift with a logo', 'text' => 'A logo from an SVG as an ornament in the company colours, up to four colours in one print, for customers and the team.'],
            ['t' => 'For Grandma with the grandchildren’s names', 'text' => 'A name in one of thirty typefaces on a circle or a star, a different colour for every grandchild.'],
        ],
    ],

    'cutter' => [
        'order' => [
            ['q' => 'How much does a cutter cost from you?', 'farm' => true, 'a' => 'A cutter of the name Ela (82 × 54 mm) prints in 31 minutes and costs 99 CZK, which is the minimum order (PLA+, without delivery, calculation of 18 October 2026). Every shape is its own design and calculation; the number of pieces of one shape you enter there.'],
            ['q' => 'How do I cut so that the dough does not stick?', 'a' => 'Keep the dough chilled and dip the cutter in flour before every shape. Press straight down on the flange, do not twist, and push the shape out of the edge with a finger; a tapered edge cuts cleaner than a straight one.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A cutter prints in 25 to 35 minutes. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point). For Christmas baking, order in good time.'],
            ['q' => 'How do I print the cutter at home?', 'a' => 'Flange down, without supports, from PLA or PETG; the downloaded project carries the 1 mm wall and the 0.2 mm layers of the calculation. The stamp, if one was made, prints next to it as a separate part.'],
            ['q' => 'Can I make a set of letters for a name?', 'a' => 'Yes, in two ways: the whole name as one cutter for one biscuit (two lines of 20 characters), or a cutter of its own for every letter, ordered separately. For smaller letters choose a bold typeface so that the dough keeps its shape.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the cutter printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Christmas biscuits with a name', 'text' => 'A name or initials in a script typeface, 80 to 120 mm, on gingerbread and shortbread for everyone at the table.'],
            ['t' => 'A birthday number', 'text' => 'A 70 to 90 mm number on party biscuits; a year or an age as one piece.'],
            ['t' => 'A logo on biscuits', 'text' => 'A logo from an SVG as a cutter and a stamp with the details inside, for a company event or a café.'],
        ],
    ],

    'nameplate' => [
        'order' => [
            ['q' => 'How much do gift tags cost from you?', 'farm' => true, 'a' => 'A 70 mm tag with an eyelet prints in 10 minutes: one costs 99 CZK, which is the minimum order, ten of the same 120 CZK, that is 12 CZK each (PLA, without delivery, calculation of 27 October 2026). For different names you make a design for each; you see the price before you order anything.'],
            ['q' => 'How do I hang the tag on a gift?', 'a' => 'Tick the eyelet: its 2 to 8 mm hole takes a ribbon or a string. A 70 mm tag weighs 5 g, so it hangs on a bow or a branch; without an eyelet you stick it to the wrapping paper with double-sided tape.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A tag prints in 10 to 25 minutes depending on size. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point). Before Christmas, order in good time.'],
            ['q' => 'How do I print the tag at home in several colours?', 'a' => 'Flat, without supports. The downloaded project carries a filament change at the height of every layer; with one nozzle you swap the spool at the pause, a printer with AMS, MMU or ACE does it by itself. The STL is one body for a single-colour print.'],
            ['q' => 'Will the tag last on a bag or keys?', 'a' => 'For a gift PLA is enough. For a backpack, keys or a collar choose PETG, keep the default layer thickness and an eyelet of at least 4 mm; letters at least 6 mm high stay legible after a year of wear.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the tags printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Tags for Christmas gifts', 'text' => 'A 60 to 80 mm hexagon or star with an eyelet and a name in two colours: ten pieces for 120 CZK.'],
            ['t' => 'A sign for a child’s door', 'text' => 'A 120 mm cloud or house with a name and a favourite animal, three colours, for a door or a shelf.'],
            ['t' => 'A tag for a backpack and keys', 'text' => 'A bone, a shield or a ribbon of PETG with a 4 mm eyelet, the name and a phone number as two layers of text.'],
        ],
    ],

    'topper' => [
        'order' => [
            ['q' => 'How much does a topper cost from you and when does it arrive?', 'farm' => true, 'a' => 'A topper with the number 2 and the name Olivia (110 mm) prints in 55 minutes and costs 99 CZK, which is the minimum order (PLA, without delivery, calculation of 27 October 2026). We dispatch on the day the print ends, on working days, Packeta delivers within 1 to 2 working days; for a party order at least a week ahead.'],
            ['q' => 'How do I push the topper in so that it holds?', 'a' => 'Push the spikes into the cake up to the shape, best through the sponge, not just into the cream; two spikes hold better than one. Wrap them in cling film or push them into straws, and put the topper in only before serving.'],
            ['q' => 'How much is delivery?', 'farm' => true, 'a' => 'With Packeta to a pickup point in the Czech Republic :cz_point, to your door :cz_home, to Slovakia from :sk_point, to other EU countries from :eu_point.'],
            ['q' => 'How do I print the topper at home?', 'a' => 'Flat, face up, without supports, from PLA. The downloaded project carries a pause at the height of the name for the spool change; a printer with several spools does it by itself. 3 mm of thickness is enough so that it does not bend.'],
            ['q' => 'Which colours should I choose?', 'a' => 'Gold or pink with a white name, black with white, or a glitter or Silk filament if we have it in stock. You pick at the order from the spools in the printers; a light name on a dark shape reads best in photos.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the topper printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A child’s birthday', 'text' => 'The age as a 110 mm number with the name in a script typeface, two 60 mm spikes, for 99 CZK.'],
            ['t' => 'A wedding or an anniversary', 'text' => 'A 120 mm heart with "Ela a Tom" or a date, gold with white.'],
            ['t' => 'New Year’s Eve and a company party', 'text' => 'Just a text without a shape: "2027", "Thank you" or the team’s name, 180 mm wide, 80 mm spikes.'],
        ],
    ],

    'name_letter' => [
        'order' => [
            ['q' => 'How much does a letter cost from you?', 'farm' => true, 'a' => 'A letter E 120 mm high with the name Ela prints in 40 minutes and costs 99 CZK, which is the minimum order (PLA, without delivery, calculation of 27 October 2026). A 200 mm letter costs more by its grams and minutes; you see the price before you order anything.'],
            ['q' => 'How do I hang the letter on a door?', 'a' => 'Tick the eyelet and hang it on a ribbon or a hook, or stick it on with double-sided tape; a 120 mm letter weighs 27 g. For a shelf 10 mm of thickness is enough, or the base with a groove from the same file.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A 120 mm letter prints in 40 minutes, a 200 mm one in over an hour. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I print the letter at home in two colours?', 'a' => 'Flat, face up, without supports. The downloaded project carries a pause at the height of the name for the spool change; the base prints next to it. A printer with AMS, MMU or ACE changes the colour by itself.'],
            ['q' => 'Which colours suit it?', 'a' => 'Two contrasting ones: a pastel body with a white or dark name for a child’s room, black with gold for a table. Matte filaments hide the print layers better than glossy ones; you pick from the spools we have in stock.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the letter printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A gift for a birth or a christening', 'text' => 'A 120 to 150 mm letter with the baby’s name for the shelf above the cot; with "Ela 2026" as the date.'],
            ['t' => 'A child’s door', 'text' => 'A 150 mm letter with an eyelet on a ribbon or on double-sided tape, colours to match the room.'],
            ['t' => 'A wedding: initials for the table', 'text' => 'Two 150 mm letters with the names of the newlyweds, 10 mm thick so that they stand by themselves on the wedding table.'],
        ],
    ],

    // the next 10 tools, batch A (docs/AP.md §9): phone stand, sign, keychain
    'phone_stand' => [
        'order' => [
            ['q' => 'How much does a stand cost from you and how long does it print?', 'farm' => true, 'a' => 'The low 80 mm wedge prints in 1 h 8 min and costs 120 CZK, the 75 mm A-frame with a shelf in 1 h 45 min for 170 CZK (PLA+, without delivery, calculation of 18 October 2026). The calculation shows the price of your stand before you order anything.'],
            ['q' => 'How do I measure the phone?', 'a' => 'Measure the thickness with the case at the thickest point, including a finger grip if the case has one; the tool takes 7 to 20 mm and adds play by itself. Measure the width across the case; for desk types it decides how wide the backrest is.'],
            ['q' => 'How long does delivery take?', 'farm' => true, 'a' => 'We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point, to your door :cz_home).'],
            ['q' => 'How do I print the stand at home?', 'a' => 'The downloaded project for OrcaSlicer, Bambu Studio or PrusaSlicer carries the orientation and the settings of the calculation, just pick your printer; an STL you orient yourself. For a desk PLA with 15 % infill is enough, a matte filament hides the layers.'],
            ['q' => 'Does the stand survive a car in the sun?', 'a' => 'Print the car clip and a dashboard stand from PETG: PLA behind glass softens at around 55 °C and the clip lets go. For a desk or a wall PLA is enough.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the stand printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'For the desk', 'text' => 'An A-frame with a shelf at 65° with a cable cut-out: the phone charges and stays in view for calls and notifications.'],
            ['t' => 'For recipes in the kitchen', 'text' => 'A low wedge or a wide A-frame up to 260 mm for a tablet, at 60° so that the screen reads from the counter.'],
            ['t' => 'For the car', 'text' => 'A clip for the air vent by the slat thickness of 1 to 4 mm, from PETG, with a cable cut-out.'],
        ],
    ],

    'sign' => [
        'order' => [
            ['q' => 'How much do signs cost from you?', 'farm' => true, 'a' => 'A keyring with a name 99 CZK and ten of the same 99 CZK together, a 173 × 69 mm door sign 99 CZK (PLA+, without delivery, calculation of 18 October 2026). A bigger sign or more names is priced by grams and minutes; you see the price before you order anything.'],
            ['q' => 'Which shape and typeface for a door?', 'a' => 'For the door of a flat or an office a sans or serif typeface with letters at least 15 mm high and a frame, so that the sign reads from the corridor; keep scripts for names and keyrings. Raised letters in a second colour read better than recessed ones.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A keyring prints in 5 minutes, a door sign in 46 minutes. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I print the sign at home in two colours?', 'a' => 'Flat, without supports. The downloaded project carries a pause at the height where the letters begin: the printer stops, you swap the spool and the print goes on; a printer with AMS, MMU or ACE changes the colour by itself. In the two-colour version you also download the plate and the letters separately.'],
            ['q' => 'Will the sign last outdoors?', 'a' => 'For a flat and inner doors PLA is enough. For an outer door or a fence choose ASA, which takes sun and frost; PETG lasts in the shade. You pick the material in the material comparison of the calculation.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the signs printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Name tags for a class or a team', 'text' => '46 × 22 mm keyrings with names: ten pieces for 99 CZK, every name its own design with the same layout.'],
            ['t' => 'A door sign for a flat or an office', 'text' => '"Novákovi 12" with raised letters and a frame, 173 × 69 mm, stuck on with double-sided tape.'],
            ['t' => 'A name for a child’s room', 'text' => 'Just the name in a script without a plate, with an eyelet for a ribbon, or without one for the door.'],
        ],
    ],

    'keychain' => [
        'order' => [
            ['q' => 'How much do keychains cost from you?', 'farm' => true, 'a' => 'A 70 mm bone with a name in two colours costs 99 CZK, which is the minimum order, ten of the same 100 CZK, that is 10 CZK each (PLA, without delivery, calculation of 28 October 2026). You pick the colours at the order from the spools we have in stock.'],
            ['q' => 'How do I make keychains with different names for a team?', 'a' => 'The design stays on the page: rewrite the name, keep the shape and the picture and continue to the calculation for each name separately; the number of pieces of one name you enter there. All the keychains print side by side on one plate.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A keychain prints in 10 minutes. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I print the keychain at home in several colours?', 'a' => 'Flat, without supports. The downloaded project carries a filament change at the height of every layer; with one nozzle you swap the spool at the pause, a printer with AMS, MMU or ACE does it by itself. The STL is one body for a single-colour print.'],
            ['q' => 'Does it work as a company gift with a logo?', 'a' => 'Yes. Upload the logo as an SVG and a one-colour silhouette of it remains on a shape in the company colour; a multi-colour logo is handled by the quick form. Ten keychains come to 100 CZK, the calculation shows the price of a bigger batch.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the keychains printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Keychains for a team or a wedding', 'text' => 'One layout, different names, ten pieces for 100 CZK; for a wedding a heart with the names and the date.'],
            ['t' => 'A tag for a backpack and keys', 'text' => 'An 80 mm tag of PETG with the name and a phone number in two lines, a 5 mm eyelet for a carabiner.'],
            ['t' => 'A gift with a logo', 'text' => 'A logo from an SVG as a silhouette on a circle in the company colour, up to four colours in one print.'],
        ],
    ],

    // batch B (docs/AP.md §9): medal, vase (the box has its blocks from AH.md §11)
    'medallion' => [
        'order' => [
            ['q' => 'What does a set of medals for a race cost?', 'farm' => true, 'a' => 'A 60 mm medal with a chain of 40 links costs 130 CZK (PLA+, without delivery, calculation of 22 October 2026). For 1st to 3rd place you make three designs, each with its own calculation, and enter the number of pieces of one place there; you see the price of the whole set before you order anything.'],
            ['q' => 'How do I hang the medal without the chain?', 'a' => 'Set the number of links to zero: the medal has an eyelet at the top with a 6 mm hole that takes a ribbon up to 15 mm or a cord. The chain of links prints only when you want it.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A 60 mm medal with a chain prints in 1 h 42 min, so a set for three places keeps a printer busy for half a day. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point); for a race order at least a week ahead.'],
            ['q' => 'How do I print the medal at home?', 'a' => 'A flush motif alternates two spools in the same layers, which needs a printer with a changer (AMS, MMU, ACE). Without a changer choose the raised motif: the project has one filament change at the height of the motif. The chain prints flat without supports; in PLA Silk it looks metallic.'],
            ['q' => 'Can I put a club logo or a name on the medal?', 'a' => 'Yes. Instead of the numeral you type a name in two lines of 16 characters, or upload a picture, of which a one-colour silhouette remains; you pick the plate shape from seven. Enlarge a logo with fine detail with the motif at 60 to 70 % of the diameter.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the medals printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A children’s race or tournament', 'text' => 'A set for 1st to 3rd place with chains of 28 to 32 links for children, every medal 130 CZK.'],
            ['t' => 'A school contest or a kids’ day', 'text' => 'A 60 mm medal with a name or a picture on a ribbon, without a chain, for every participant.'],
            ['t' => 'A company tournament', 'text' => 'An 80 mm shield or rosette with the company logo and a chain of 40 links, gold PLA Silk.'],
        ],
    ],

    'vase' => [
        'order' => [
            ['q' => 'How much does a vase cost from you?', 'farm' => true, 'a' => 'A smooth 160 mm vase 190 CZK, a 180 mm spiral one 280 CZK, a ribbed 120 mm pot cover 260 CZK (PLA+, without delivery, calculation of 18 October 2026). The calculation shows the price of your vase before you order anything; grams and minutes decide, for spirals mainly the minutes.'],
            ['q' => 'Which filament for a vase?', 'a' => 'For spiral and ribbed vases glossy PLA Silk, on which the ribs play with the light; for smooth ones a matte filament that hides the layers. For a pot cover, which gets wet, choose PETG. You pick the colour from the spools we have in stock right now.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A smooth 160 mm vase prints in under 2 hours, a 180 mm spiral one in 5 hours. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I print the vase at home?', 'a' => 'Upright, without supports; the downloaded project has the wall and the bottom as designed. The STL is a solid body, so you can switch your slicer to vase mode with a single wall, which prints faster and quieter but holds water worse. Print tall vases with a brim so that they do not come off the bed.'],
            ['q' => 'How do I measure a pot for a cover?', 'a' => 'Measure the outer diameter of the pot at the top rim and its height, add 5 to 10 mm of play to the diameter and enter it as the top diameter of the cover; the bottom diameter a little smaller to follow the shape of the pot. The saucer is computed by itself.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the vase printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Dried flowers for the table', 'text' => 'A 180 mm spiral vase with a narrow neck in PLA Silk; for dried flowers it need not hold water.'],
            ['t' => 'A pot cover for the flat', 'text' => 'A ribbed 120 mm cover with drainage and a saucer in PETG, the diameter of the pot plus 5 mm.'],
            ['t' => 'A set of three vases', 'text' => 'Smooth vases of 120, 160 and 200 mm in the same shape and one colour for a shelf or as a gift.'],
        ],
    ],

    // batch C (docs/AP.md §9): logo, wall mount, stamp, lightbox sign
    'logo' => [
        'order' => [
            ['q' => 'How much does a logo cost from you?', 'farm' => true, 'a' => 'A relief on a plate 100 mm wide costs 99 CZK, which is the minimum order (PLA, without delivery, calculation of 28 October 2026). A bigger sign or a standing logo is priced by grams and minutes; you see the price before you order anything, for several pieces too.'],
            ['q' => 'How do I prepare the SVG of the logo?', 'a' => 'In one colour, with fills instead of strokes (Inkscape: Stroke to Path) and text converted to paths; gradients and masks are not read. Enlarge a logo with small text to at least 100 mm so that no line is under 0.8 mm.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A 100 mm relief prints in 43 minutes, a 250 mm sign in over two hours. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I print the logo at home in two colours?', 'a' => 'Flat, without supports. With a relief on a plate the downloaded project has a pause at the height where the motif begins: the printer stops, you swap the spool and the print goes on; a printer with AMS, MMU or ACE does it by itself. A standing logo and its base print as two parts.'],
            ['q' => 'Will the logo last outdoors on a facade?', 'a' => 'Outdoors in sun and frost choose ASA, indoors PLA is enough, in the shade PETG. A logo over 250 mm does not fit the bed; split it into parts, or let the Split model tool cut it with pins.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the logo printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A sign for an office door', 'text' => 'A relief of the logo or the company name, 120 to 150 mm, in two colours, stuck on with double-sided tape.'],
            ['t' => 'A logo for a reception or a counter', 'text' => 'A standing logo of 150 to 200 mm in a base with a groove, at least 2.4 mm thick, printed as two parts.'],
            ['t' => 'Small things with a logo for a team', 'text' => 'A cut-out logo of 60 mm as a magnet or a pendant; for keychains with names there is the Keychain tool.'],
        ],
    ],

    'holder' => [
        'order' => [
            ['q' => 'How much does a mount cost from you?', 'farm' => true, 'a' => 'A cradle for a remote 100 CZK, a hook for headphones 99 CZK, a clip for a broom 99 CZK (PLA+, without delivery, calculation of 18 October 2026); 99 CZK is the minimum order. Several mounts in one order come cheaper per piece; you see the price before you order anything.'],
            ['q' => 'Which kind of mount for what?', 'a' => 'The cradle for a remote, a phone or glasses, the pocket for a bottle, tools and pens, the hook for headphones, a bag or keys with a loop, the clip for the handle of a broom, a mop or a rake. The presets set the sizes of common things, your own sizes overwrite them.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The clip prints in 21 minutes, the hook in 27 minutes, the cradle in 1 h 12 min. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I fix the mount without drilling?', 'a' => 'Switch the holes off and stick the mount on with double-sided tape; it carries only light things like a remote or headphones. Where a screw is already in the wall, choose hanging on a screw: the head passes through the wide hole and the mount slides down into the slot.'],
            ['q' => 'How do I print the mount at home?', 'a' => 'Flat with the back on the bed, without supports; the downloaded project carries the settings of the calculation. For heavier things raise the number of walls or the infill in your slicer and print from PETG.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the mount printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'Remotes by the sofa', 'text' => 'A 50 × 22 mm cradle on the side of a shelf or a side table, stuck on with tape; one for every remote.'],
            ['t' => 'Tools in the workshop and garage', 'text' => 'Clips for handles and pockets for hammers from PETG on screws, sized to every piece.'],
            ['t' => 'Headphones at the desk', 'text' => 'A 35 mm hook under the desktop or on the side of the monitor, the headphones out of the way.'],
        ],
    ],

    'stamp' => [
        'order' => [
            ['q' => 'How much does a stamp cost from you?', 'farm' => true, 'a' => 'A 50 mm stamp with a handle costs 99 CZK, which is the minimum order (PLA, without delivery, calculation of 28 October 2026). A bigger stamp or more pieces is priced by grams and minutes; you see the price before you order anything.'],
            ['q' => 'How do I make a maker’s mark for the bottom of mugs?', 'a' => 'Upload the logo as an SVG or type your initials, choose a raised motif, a width of 20 to 40 mm and a motif height of 1 to 1.5 mm; the impression in leather-hard clay is then clean. With a handle the stamp is easier to hold, without one you press it with your thumb.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'A 50 mm stamp prints in 28 minutes. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'Does the stamp suit soap and dough?', 'a' => 'Yes, anything soft that keeps the impression: soap, fondant, shortcrust dough, polymer clay. For a raised logo on soap choose a recessed motif. Printed plastic is meant for a brief touch with food; wash the stamp by hand, not in the dishwasher.'],
            ['q' => 'How big a stamp should I choose?', 'a' => 'A maker’s mark 20 to 40 mm, a decorative stamp for ceramics 50 to 80 mm, 120 mm at most. The bigger the stamp, the more pressure it needs; press a large motif with a rolling pin over a plate without a handle instead.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the stamp printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A pottery studio', 'text' => 'A 30 mm maker’s mark for the bottom of mugs and bowls, a raised motif, with a handle.'],
            ['t' => 'Soap making', 'text' => 'A 50 mm recessed motif that leaves a raised logo on the soap; a stamp from PETG takes the damp.'],
            ['t' => 'Baking and fondant', 'text' => 'A "Hand made" stamp or a name for dough and fondant, from PLA, washed by hand.'],
        ],
    ],

    'lightbox' => [
        'order' => [
            ['q' => 'How much do the parts cost from you?', 'farm' => true, 'a' => 'The sign OPEN of 180 mm, four parts, costs 180 CZK (PLA, without delivery, calculation of 28 October 2026). You pick the colours of the parts when ordering, in one order, a dark body and a white diffuser; you see the price before you order anything.'],
            ['q' => 'What do I buy for the sign?', 'a' => 'A 5 V LED strip 8 or 10 mm wide in the length the tool lists under the preview, a USB cable with power (a phone charger is enough) and double-sided tape or glue. Choose a strip with a remote up to 18 × 8 mm.'],
            ['q' => 'How long do printing and delivery take?', 'farm' => true, 'a' => 'The four parts of a 180 mm sign print in 2 h 17 min. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in the Czech Republic (to a pickup point :cz_point).'],
            ['q' => 'How do I print the sign at home?', 'a' => 'The parts print flat; the downloaded project carries the settings of the calculation. The body, the mask and the back cover from dark PLA or PETG, the diffuser from white or translucent PLA; print the mask with the bridges inside the letters slower so that the bridges hold.'],
            ['q' => 'How bright will the sign be?', 'a' => 'The strip gives the brightness, not the parts; the diffuser spreads the light so that the single LEDs do not show. A 5 V strip draws a few watts, so a USB charger is enough and the sign can glow all evening.'],
            ['q' => 'Do I need an account?', 'a' => 'Not for the design or the download. You need one only when you have the parts printed by us; that is paid from credit topped up by card.'],
        ],
        'who' => [
            ['t' => 'A café or a shop', 'text' => 'OPEN or the name of the business, 240 mm, for the shop window, a rectangle with an LED strip, from PETG for the sun.'],
            ['t' => 'A home bar or games room', 'text' => 'A round 160 mm sign with a flat foot for a shelf, one word or a name.'],
            ['t' => 'A night light with a name', 'text' => 'A child’s name, 120 mm, with an LED module and a remote, a soft light for the room.'],
        ],
    ],
];
