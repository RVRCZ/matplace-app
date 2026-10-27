<?php

// The page for the query "3d printed gift tag" (docs/AP.md §6). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed gift tag and name plate, made of layers, free STL',
    'heading' => '3D printed gift tag with a name',
    'description' => 'Pick a plate shape, type a name, add a picture and a ribbon eyelet; every layer has a filament colour. We print the tag, or you download the model free.',
    'h1' => 'How to build the tag from layers, what it costs and where it fits',
    'intro' => [
        'Here you build a name tag from layers. The plate has one of nineteen shapes: a hexagon, a heart, a star, a cloud, a bone, a ribbon, an arrow, a house, a car, a cat, a fish, a shield, a speech bubble and more. On it you lay a name in one of thirty typefaces and a picture from a library of 178 silhouettes or your own; up to twelve layers, each in its own colour from the filaments our farm has. You pick a layer by clicking in the preview, move it by dragging, scale it by the corner of its frame and turn it with the wheel, and set the width of each from 5 to 250 mm. The layers lie one above the other, each a step higher, so a printer only changes the filament at the right height; on our farm we print up to four colours in one go. With an eyelet the same design is a gift tag, a sign for a child’s door, a bag tag and a collar tag.',
        'An example from the production calculator (27 October 2026, PLA): a 120 × 60 mm hexagon with the name Adéla and a star in three colours weighs 14.5 g, prints in 25 minutes and costs 99 CZK (about 4 EUR) from us, which is the minimum order (calculation /c/v9fh1s1ogsob). A gift tag, a 70 mm hexagon with an eyelet and the text "Pro Elu", weighs 5.2 g and prints in 10 minutes: one costs 99 CZK, ten of the same 120 CZK, that is 12 CZK each, because the order preparation is paid once (/c/udkkubsktt9d). The calculation counts one colour, the spools for the others you confirm at the order. Just want a name on a plate that grows around the text by itself? Under the options at the top of the page is a link to the quick form.',
    ],
    'steps' => [
        ['name' => 'Start from the name plate', 'text' => 'The page opens with a plate, the name Adéla and a star. Rewrite the name and pick another plate shape.'],
        ['name' => 'Edit the layers', 'text' => 'Click a layer in the list or in the preview and change its text, typeface, shape, picture and colour. In the preview you move it by dragging, scale it by the corner of its frame and turn it with the wheel.'],
        ['name' => 'Add more, and the eyelet', 'text' => 'With the buttons you add a second line of text, a picture or another shape, up to twelve layers; the arrows change what lies on what. A tick adds the eyelet for a ribbon.'],
        ['name' => 'Order the print or download', 'text' => 'Next to the preview you see the dimensions; the exact price and print time show in the calculation after "Continue". The model and the slicer project with the filament changes are free to download, no sign-up.'],
    ],
    'faq' => [
        ['q' => 'How big does the tag come out?', 'a' => 'As you set it: the width of every layer goes from 5 to 250 mm. The plate the page opens with is 120 × 60 mm and 4.6 mm thick; for a gift 60 to 80 mm with an eyelet is enough.'],
        ['q' => 'How do I make a set of tags for the whole family?', 'a' => 'The design stays on the page: rewrite the name, keep the layout and continue to the calculation for each name separately; the number of pieces of one design you enter in the calculation. Ten of the same 70 mm tags come to 120 CZK.'],
        ['q' => 'Which picture can I add?', 'a' => 'A silhouette from the library (178 motifs), an SVG with filled shapes, or a simple high-contrast PNG or JPG. From a picture a one-colour silhouette remains. A photo does not suit; the Relief and lithophane tool is for that.'],
        ['q' => 'Do you print the tag in several colours?', 'a' => 'Yes, up to four in one print. Every layer lies a step higher, so the filament changes by height. You pick the colours from what we have in stock; on your own printer the project carries the changes.'],
        ['q' => 'What if the text runs over the plate?', 'a' => 'The layout is yours: shrink the text by dragging the corner of its frame, or enlarge the plate. Whatever runs over the plate prints too, because the lower layers are filled in under every layer.'],
        ['q' => 'Does it work as a dog tag?', 'a' => 'The bone shape with an eyelet does; for a collar choose PETG and letters at least 6 mm high. Add the phone number as another text layer.'],
    ],
    'examples' => [
        'A 120 × 60 mm hexagon with the name Adéla and a star, three colours: 14.5 g, 25 minutes, 99 CZK.',
        'A 130 mm bone with the name Rex and a paw, the Titan One typeface.',
        'A 100 × 55 mm cloud with the name Ela and a star.',
    ],
];
