<?php

return [
    'title' => '3D map of a city or a landscape to print, from OpenStreetMap',
    'description' => 'Name a city, a village or a place and get a 3D map to print: buildings with their heights, streets and water from OpenStreetMap, or a landscape\'s relief. Free.',
    'h1' => '3D map of a city or a landscape: from the name of a place straight to a printable model',
    'intro' => [
        'Type a city, a village, a district or a landmark, pick a range, and you get the map as a thin plate to print. A city has buildings with their real heights from OpenStreetMap, streets by their kind from motorways to footpaths, rivers and ponds sunk into the plate, railways and parks. A landscape is the relief of the terrain from the Mapzen height data with an exaggeration, water and roads on its surface and towns as low blocks. No AI guesses anything: the data are exact and the model is done within a minute, without an account and free.',
        'A map prints flat without supports. The plate, the roads and the buildings are three colours one above the other: on our farm the filament changes by itself at two heights, the project for your printer carries the changes. The plate is 80 to 250 mm to fit your bed, the scale follows and is shown, and a raised name goes on the frame. In the Miniature style the buildings have rounded corners and stand half as high again, the roads are wider and the frame is round; Sleek keeps exact edges and true heights. Before the build you see the area from above, so you know what will be printed.',
    ],
    'steps' => [
        ['name' => 'Name the place', 'text' => 'A city, a district, a street or a landmark; or coordinates. Pick the place you mean, then a city or a landscape and the range: a city 500 m to 2 km, a landscape 2 to 20 km.'],
        ['name' => 'Look at the area and set the map', 'text' => 'The preview shows the buildings, roads, water and green as they will print. Choose the style, the size of the plate, the frame with its name, for a city the height of buildings without data and raised or sunk roads, for a landscape the exaggeration.'],
        ['name' => 'Pick the colours', 'text' => 'The plate, the roads and the buildings, or the plate and the terrain, each in its own colour. The colours lie one above the other by height, so any printer with a filament change prints them.'],
        ['name' => 'Make the map and print it or download', 'text' => 'The model is built on the server, a city of 1 km within half a minute. Then you see the size and a rough price; a print by us is one step on, the STL and the 3MF project with the changes are free to download.'],
    ],
    'faq' => [
        ['q' => 'Where do the data come from and how exact are they?', 'a' => 'Buildings, streets, water and railways from OpenStreetMap, the map that people all over the world keep; in cities it is very complete, with the heights of buildings where somebody wrote them down (otherwise the height you set applies). Heights of the terrain from the Mapzen data (AWS Open Data) at about 30 m. The map says the date of its data.'],
        ['q' => 'How big a city fits?', 'a' => 'A square of 500 m, 1 km or 2 km round the middle. On a plate of 150 mm a kilometre is about 1 : 7 000 and a building of 20 m stands 3 mm high. A bigger city on one plate would have buildings below the size of a nozzle; for a large area choose a landscape.'],
        ['q' => 'How are the three colours printed?', 'a' => 'The plate in the first colour, the roads from its top in the second, the buildings above them in the third: two filament changes by height. Our farm does them by itself; the 3MF project for OrcaSlicer and PrusaSlicer carries them. One colour works too: the STL is one solid.'],
        ['q' => 'Can I upload a picture of a map?', 'a' => 'No. From a picture one could only guess; from the name of a place you get the exact buildings and streets. When a place is not found, type its coordinates, from Google Maps for instance.'],
        ['q' => 'May I sell the map?', 'a' => 'Yes. The OpenStreetMap data are under the ODbL licence: you may print, give away and sell the model, only say "© OpenStreetMap contributors". The Mapzen height data are free to use.'],
        ['q' => 'How many maps can I make?', 'a' => 'The map services are public, so three maps a day without an account and twenty with one. The preview of the area does not count.'],
    ],
    'examples' => [
        'A city of 500 m on a plate of 150 mm, Sleek style: buildings by their heights, the main streets, a stream with a pond, a railway and a frame with the name.',
        'The same place in the Miniature style: rounded buildings half as high again, wider streets, the park sunk into the plate.',
        'A landscape of 5 km with a hill in the middle, exaggeration 2×, on a plate of 150 mm.',
    ],
];
