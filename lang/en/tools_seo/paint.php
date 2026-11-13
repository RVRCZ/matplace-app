<?php

return [
    'title' => 'Colour a 3D model online: multicolour print from STL, 3MF',
    'description' => 'Upload an STL or a 3MF and say in the browser which part gets which colour: fill a surface, colour by height or paint with a brush. No slicer needed.',
    'h1' => 'Colouring a 3D model for a multicolour print',
    'intro' => [
        'The tool colours an uploaded model right in the browser. A tap fills a whole connected surface up to an edge, or a whole part; the brush adds details; the height colours horizontal bands. You pick the colours from the filaments we print from, and the tool keeps count of how many colours one print can take.',
        'The result is a 3MF with the colours painted on the model, the same kind Bambu Studio, OrcaSlicer or Anycubic Slicer Next save. The calculation shows the price in one colour and in colours at once, and you order the print or download the file for your own printer with a filament changer. The shape of the model does not change; the brush only refines the mesh where you paint.',
    ],
    'steps' => [
        ['name' => 'Upload the model', 'text' => 'STL, 3MF, OBJ or STEP. The model shows in the preview; a 3MF that already has colours opens with them.'],
        ['name' => 'Choose the colours', 'text' => 'The colour of the model and more colours from the filaments on offer. The tool says how many more you can add.'],
        ['name' => 'Colour it', 'text' => 'Fill: a tap colours a surface up to an edge. Brush: drag over the surface. By height: another colour from the chosen height up.'],
        ['name' => 'Get the price, or download', 'text' => 'The calculation shows the price in one colour and in colours. You can download the coloured 3MF for your own slicer.'],
    ],
    'faq' => [
        ['q' => 'What printer prints a coloured model?', 'a' => 'A printer that changes filament during the print: with an AMS, MMU or ACE unit, or with several heads. We print coloured models on such machines in one go, on one plate. Colours one above another also work on a single-nozzle printer when you swap the spool by hand at each change.'],
        ['q' => 'How many colours can a model have?', 'a' => 'As many as the printer with a changer holds spools; the tool shows the number and does not let you add more. Keep in mind that every further colour in the same layer means a filament change and makes the print longer.'],
        ['q' => 'Why does a multicolour print cost more than one colour?', 'a' => 'At every colour change the printer pushes the old filament out into a prime tower and loads the new one. That takes about two minutes and a little plastic. A model with colours side by side over its whole height has such a change in every layer, a model with colours one above another only one per change.'],
        ['q' => 'How fine can the painted details be?', 'a' => 'The nozzle is 0.4 mm wide and cannot print a thinner line. The brush therefore draws its edge to about 0.4 mm, and specks under half a square millimetre are merged into the colour around them when you save; the tool tells you how many there were.'],
        ['q' => 'Does colouring change the shape of the model?', 'a' => 'No. The fill and the colours by height only give the faces of the model a colour; the brush also splits the faces under the stroke into smaller ones, so the edge of the colour runs where you dragged. The surface and the size stay the same.'],
        ['q' => 'Does the tool handle large models and phones?', 'a' => 'The fill and the colours by height work up to a million triangles. The brush refines the mesh, so it works up to half a million triangles on a computer and two hundred thousand on a phone; for larger models the tool switches the brush off and says so.'],
    ],
    'examples' => [],
];
