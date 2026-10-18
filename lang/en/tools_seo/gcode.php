<?php

return [
    'title' => 'Model from G-code: STL or 3MF back from G-code, by colour',
    'description' => 'Upload a .gcode file up to 150 MB and the outer walls become a watertight model: STL, a part per colour or a 3MF project. Price it and have it printed. Free.',
    'h1' => 'A model back from G-code: print it again, in another size or colours',
    'intro' => [
        'You only have the G-code and the original model is gone? The tool reads the paths of the print head and rebuilds a solid, watertight model from the outer walls of every layer. The surface lies where the outer wall of the print was; holes and islands stay, inner walls, infill, supports and the brim do not shape it. It understands G-code from OrcaSlicer, Bambu Studio, Anycubic Slicer, PrusaSlicer and Cura, arcs and tool changes included.',
        'From the file header it reads the slicer, the printer, the layer height, the print time and the filament used. Colour prints are kept: several tools in one layer give a part per colour and a 3MF project with the colours; colours alternating by height stay as filament changes. The new model opens like any other file: price it, change its size or colours and have it printed by us, or download it.',
    ],
    'steps' => [
        ['name' => 'Upload the G-code', 'text' => 'Drop the .gcode file into the field or click to choose it. Up to 150 MB and 1.5 million lines; nothing to set.'],
        ['name' => 'Wait a few seconds', 'text' => 'The tool finds the outer-wall loops of every layer, nests them and extrudes them into a solid. A usual print takes three to ten seconds.'],
        ['name' => 'Look at the result', 'text' => 'The preview shows the model; the report under it the slicer, the printer, the layer height, the time, the grams and the colours. Parts per colour are coloured in the preview.'],
        ['name' => 'Order the print, or download', 'text' => 'Price it and have the model printed by us, or download the STL, the parts per colour or a 3MF project for your slicer.'],
    ],
    'faq' => [
        ['q' => 'How accurate is a model from G-code?', 'a' => 'The surface is where the centre of the outer wall was plus half a line width, that is where the surface of the print was. On a known body the volume came out 0.8 % bigger, the footprint within hundredths of a millimetre, the height rounded to layers. Corners are rounded to half a line and the layer steps remain, as on a real print; details smaller than a line width are missing.'],
        ['q' => 'Which G-code files does the tool read?', 'a' => 'G-code for FDM printers with path type marks: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura. It does not read .gcode.3mf bundles, binary .bgcode, G-code without type marks (old Cura, Simplify3D) or files for resin or CNC; for those it says it found no walls.'],
        ['q' => 'What happens to the colours?', 'a' => 'Several tools in one layer (a letter set into a plate, say) give a part per colour with the filament colour from the header, plus a 3MF project with the colours. Colours alternating by height stay as filament changes at those heights. One filament gives one part.'],
        ['q' => 'Can I enlarge the model or edit it further?', 'a' => 'Yes, the model is an ordinary STL: scale it in the calculation, hollow it, split it or make a mould of it in the other tools. Scaling up also enlarges the layer steps that remain in the model.'],
        ['q' => 'What does it cost and how do I get the print?', 'a' => 'The conversion is free. You pay only for the print if you order it from us, from prepaid credit. We send the print by Packeta to a pick-up point or an address in the EU.'],
    ],
    'examples' => [],
];
