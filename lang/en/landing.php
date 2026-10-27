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
];
