<?php

// The catalogue of tools (/tools): categories, search and the words each tool is found by. The names and hints of
// the tools themselves (tools.<tool>.title …) are older and live in lang/<locale>.json; Laravel looks there first.
return [
    'search' => 'Search the tools',
    'search.hint' => 'e.g. keyring, box, logo, mould',
    'search.none' => 'There is no such tool yet. Try another word, or tell us what you need made.',
    'search.count' => 'Found: :n',
    'verified' => 'verified by printing :date',
    'all' => 'All tools',

    'cats' => [
        'images' => 'Pictures and logos',
        'names' => 'Names and gifts',
        'home' => 'Home and storage',
        'parts' => 'Parts and mechanics',
        'toys' => 'Toys and games',
        'signs' => 'Signs and lettering',
        'craft' => 'Workshop and craft',
        'edit' => 'Editing a model',
        'sell' => 'Selling and planning',
    ],

    'keywords' => [
        'calc' => 'price quote calculator cost print stl 3mf obj step file',
        'repair' => 'repair fix holes mesh manifold broken stl',
        'check' => 'check printability walls size errors',
        'mold' => 'mould mold casting silicone plaster resin wax soap copy',
        'organizer' => 'organizer drawer compartments tray storage tidy',
        'modular' => 'modular bins grid drawer coloured compartments gridfinity',
        'box' => 'box lid case enclosure electronics openings cable',
        'phone_stand' => 'phone stand mobile holder tablet car wall',
        'holder' => 'holder hook clip remote headphones broom bottle wall',
        'cap' => 'cap lid plug cover thread pet bottle pipe profile m10',
        'cable_holder' => 'cable holder cables charger desk tidy',
        'vase' => 'vase flower pot planter saucer spiral flowers',
        'figure' => 'bust figure photo portrait statue 3d model from photo',
        'relief' => 'lithophane relief photo lamp picture backlit',
        'gifts' => 'gift name christmas birthday wedding keyring ornament',
        'sign' => 'sign nameplate keyring name door tag lettering text',
        'qr' => 'qr code wifi link sign stand menu payment',
        'logo' => 'logo svg picture to 3d emblem company relief extrude',
        'cutter' => 'cookie cutter biscuits gingerbread dough stamp fondant',
        'stamp' => 'stamp imprint ink name logo ceramics soap',
        'stencil' => 'stencil painting spray lettering letters',
        'lightbox' => 'illuminated sign led lightbox advert backlit neon',
        'spare' => 'spare part broken piece inquiry model it',
    ],
];
