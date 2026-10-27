<?php

// The page for the query "gcode to stl converter" (docs/AP.md §1, §4). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => 'G-code to STL converter, free online: the model back',
    'heading' => 'Convert G-code back to STL',
    'description' => 'Upload a .gcode up to 150 MB and the outer wall paths become a closed model: an STL, parts by colour or a 3MF project. Price it, scale it, print it. Free.',
    'h1' => 'What comes out of the G-code, how exact it is and what is lost',
    'intro' => [
        'You only have the G-code and the original model is lost, or you got a print file without its source? The tool reads the toolhead paths and rebuilds a solid, closed model from the outer walls of every layer. The surface lies where the outer wall of the print was; holes and islands stay, while inner walls, infill, supports, brim and the prime tower do not shape the model. It understands G-code from OrcaSlicer, Bambu Studio, Anycubic Slicer, PrusaSlicer and Cura, arcs and tool changes included, up to 150 MB and 1.5 million lines. From the header it reads the slicer, the printer, the layer height, the print time and the filament used. Colours are kept: several tools in one layer give one part per colour and a 3MF project with the colours, colour changes by height stay as filament changes.',
        'An example from 27 October 2026 (run on our computer, files from our own printer): a G-code from Anycubic Slicer Next for a Kobra S1, 5.6 MB and 191 thousand lines, 165 layers of 0.2 mm, a 36 minute print of 3.6 g, gave in 12 seconds a model of 91 × 57 × 16.5 mm with 522 thousand triangles. A two-colour G-code of 14 MB and 545 thousand lines (39 layers of 0.12 mm, 5 h 16 min, 81 g) gave in 9 seconds two parts by colour, black 49 g and yellow Silk 33 g, with the colours from the header. On a known solid the volume came out 0.8 % larger than the original model. The new model opens like any other file: you price it, change its size or colours and have it printed by us, or download the STL, the parts by colour or the 3MF project.',
    ],
    'steps' => [
        ['name' => 'Upload the G-code', 'text' => 'Drop the .gcode file into the field or click and choose it. Up to 150 MB and 1.5 million lines; there is nothing to set.'],
        ['name' => 'Wait a few seconds', 'text' => 'The tool finds the outer wall loops of every layer, nests them and pulls them up into a solid. An ordinary print takes three to twelve seconds.'],
        ['name' => 'Look at the result', 'text' => 'The preview shows the model; the report under it the slicer, the printer, the layer height, the time, the grams and the colours. Parts by colour are coloured in the preview.'],
        ['name' => 'Order the print or download', 'text' => 'Price the model and have it printed by us, or download the STL, the parts by colour or the 3MF project for your slicer.'],
    ],
    'faq' => [
        ['q' => 'How exact is a model from G-code?', 'a' => 'The surface is where the centre of the outer wall was plus half a line width, that is where the surface of the print was. On a known solid the volume came out 0.8 % larger, the footprint within hundredths of a millimetre, the height rounded to layers. Corners are rounded by half a line and the layer steps stay, as on the real print.'],
        ['q' => 'Which G-codes does the tool read?', 'a' => 'G-code for FDM printers with path type marks: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura. It does not read binary .bgcode, G-code without type marks (old Cura, Simplify3D) or files for resin or CNC; for those it says it found no walls. A .gcode.3mf package from Bambu Studio is a zip: unpack it and upload the .gcode file from its Metadata folder.'],
        ['q' => 'What happens to the colours?', 'a' => 'Several tools in one layer (a letter inlaid in a plate, say) give one part per colour with the filament colour from the header, plus a 3MF project with the colours. Colour changes by height stay as filament changes at those heights. One filament gives one part.'],
        ['q' => 'Why are small details and text missing from the model?', 'a' => 'The model is made from the outer walls only: whatever is narrower than one line (0.4 mm) the slicer never printed, so it is not in the G-code. Infill, top and bottom layers do not shape anything. Raised text or a fine pattern of the original model therefore comes back only as coarse as the printer really printed it.'],
        ['q' => 'Can I scale the model or edit it further?', 'a' => 'Yes, the result is an ordinary STL: scale it in the calculator, hollow it, split it into parts or make a mold of it. Scaling up also enlarges the layer steps that stayed in the model; for twice the size and more, sanding the surface is better.'],
        ['q' => 'What about a G-code printed in vase mode?', 'a' => 'A spiral has no closed loops, so the tool closes every layer where the spiral passes its height. The model comes out solid; to print it as a vase again, switch vase mode on in your slicer once more.'],
    ],
    'examples' => [],
];
