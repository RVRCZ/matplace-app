<?php

// The page for the query "3d printed city map generator" (docs/AP.md §1, §5). 'heading' = the page's H1, 'h1' = the heading of the content under the tool.
return [
    'title' => '3D printed city map from OpenStreetMap: free STL generator',
    'heading' => '3D printed map of any city from its name',
    'description' => 'Type a city or a place and in a minute you have a 3D map to print: buildings with heights, streets and water from OpenStreetMap, or a relief. Free.',
    'h1' => 'What the map contains, how it prints in three colours and where the limits are',
    'intro' => [
        'Type a city, a town, a district or a landmark, pick the extent and you get the map as a thin plate to print. A city has buildings with their real heights from OpenStreetMap (where a height is missing, the one you enter applies), streets by kind from motorways to footpaths, rivers and ponds sunk into the base, railways and parks. A landscape is a terrain relief from Mapzen elevation data at about 30 m resolution with adjustable exaggeration, with water and roads on the surface and villages as low blocks. No artificial intelligence guesses anything: the data is exact, its date is on the map, and the model is ready within a minute. The extent of a city is 500 m, 1 km or 2 km, of a landscape 2 to 20 km; the plate is 80 to 250 mm to suit the bed, the scale is computed and shown, and a raised name goes on the frame. In the Miniature style the buildings have rounded corners and are half as high again; the Sleek style keeps exact edges and real heights. Before the build you see a preview of the area from above.',
        'An example from the production calculator (18 October 2026, PLA+): the Old Town of Prague, a 500 m square on a 150 mm plate (scale about 1 : 3,300), Sleek style, weighs 85 g, prints in 3 h 42 min and costs 240 CZK (about 10 EUR) from us in one colour, without delivery. The map prints flat without supports. The base, the roads and the buildings are three colours one above the other: on our farm the filament changes by itself at two heights, the 3MF project for OrcaSlicer and PrusaSlicer carries the changes, and the STL is one body for a single-colour print. Without an account you make three maps a day, with an account twenty; the preview of the area does not count.',
    ],
    'steps' => [
        ['name' => 'Enter the place', 'text' => 'The name of a city, a district, a street or a landmark; or coordinates. Pick the place you mean and choose a city or a landscape and the extent: a city 500 m to 2 km, a landscape 2 to 20 km.'],
        ['name' => 'Look at the preview and set the map', 'text' => 'The preview of the area shows the buildings, roads, water and green as they will print. Choose the style, the plate size, the frame with the name, for a city the height of buildings without data and raised or sunk roads, for a landscape the exaggeration.'],
        ['name' => 'Pick the colours', 'text' => 'The base, the roads and the buildings, or the base and the terrain, each in its own colour. The colours lie one above the other by height, so any printer with a filament change can do them.'],
        ['name' => 'Create the map and print or download', 'text' => 'The model is built on the server, a 1 km city within half a minute. Then you see the dimensions; the exact price and a print from us are one step on, the STL and the 3MF project with the changes are free to download.'],
    ],
    'faq' => [
        ['q' => 'Where does the data come from and how exact is it?', 'a' => 'Buildings, streets, water and railways from OpenStreetMap, the map that people all over the world keep up; in cities it is very complete, building heights where someone recorded them (otherwise the height you enter applies). Terrain heights from Mapzen data (AWS Open Data) at about 30 m resolution. The date of the data is on the map.'],
        ['q' => 'How big a city fits?', 'a' => 'A square of 500 m, 1 km or 2 km around the centre. On a 150 mm plate a kilometre is at a scale of about 1 : 7,000 and a 20 m building is 3 mm high. A bigger city on one plate would have buildings smaller than the nozzle; for a large area choose a landscape.'],
        ['q' => 'How do the three colours print?', 'a' => 'The base in the first colour, the roads from its top edge in the second, the buildings above them in the third: two filament changes by height. Our farm does them by itself, the 3MF project for OrcaSlicer and PrusaSlicer carries the changes. One colour works too: the STL is one body.'],
        ['q' => 'Can I upload a picture of a map?', 'a' => 'No. From a picture one could only guess; from the name of the place you get exact buildings and streets. If we cannot find the place, enter coordinates, from Google Maps for instance.'],
        ['q' => 'May I sell the map?', 'a' => 'Yes. OpenStreetMap data is under the ODbL licence: you can print, give away and sell the model, just credit "© OpenStreetMap contributors". Mapzen elevation data is free to use.'],
        ['q' => 'How many maps can I make?', 'a' => 'The map services are public, so three maps a day without an account, twenty with one. The preview of the area does not count.'],
    ],
    'examples' => [
        'A 500 m city on a 150 mm plate, Sleek style: buildings by height, main streets, a brook with a pond, a railway and a frame with the name; 85 g, 3 h 42 min.',
        'The same place in the Miniature style: rounded buildings half as high again, wider streets, a park sunk into the base.',
        'A 5 km landscape with a hill in the middle, exaggeration 2×, on a 150 mm plate.',
    ],
];
