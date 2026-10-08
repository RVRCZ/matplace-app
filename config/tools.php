<?php

/**
 * Tool catalogue. A tool is listed only when it really generates a model and continues to the price and inquiry
 * ('available' => true); everything else stays invisible, there are no "coming soon" cards with dead buttons.
 *
 * intent:     file | create | spare          (the three entrances on the tools page)
 * categories: images | names | home | parts | toys | signs | craft | edit | sell   (the filter of the tools page, also /tools#<category>;
 *             names in lang/<locale>/tools.php `cats`; the words a tool is found by in `keywords`)
 * seo.kind:   for a tool that is another generator opened with a preset (SVG to STL = `logo` with `extrude`): the generator
 *             its examples are drawn by.
 * card:       for a tool that is not one of the generators: whose output its card shows (`matplace:tool-examples --card`),
 *             ['kind' => generator, 'preset' => …, 'params' => […], 'colors' => [part => filament]]. A generator draws its own
 *             first example. Tools with neither (price from a file, repair, check, mold, figure, relief) keep the picture
 *             they have until a photo of a print replaces it.
 * verified:   'YYYY-MM-DD' = the day a print of this tool's output was checked on the farm. The card and the tool's page
 *             then say "verified by printing <date>". No key = no badge: Roman writes the dates of what he has printed.
 * seo:        the tool's page as content for search engines. The texts live in lang/<locale>/tools_seo/<tool>.php
 *             (h1, intro, steps, faq, examples). `examples` = up to three outputs of the tool drawn in advance by
 *             `php artisan matplace:tool-examples` into public/img/tool-examples/<tool>-<n>.png: the parameters here are
 *             what the tool is given, the caption of each picture is `examples.<n>` of the texts.
 *             `schema` = the kind of thing the page describes for structured data (HowTo and FAQPage always).
 */
return [
    'calc' => ['route' => 'home', 'intent' => 'file', 'categories' => ['edit'], 'available' => true, 'seo' => ['examples' => []]],
    'repair' => ['route' => 'tools.repair', 'intent' => 'file', 'categories' => ['edit'], 'available' => true, 'seo' => ['examples' => []]],
    'check' => ['route' => 'tools.check', 'intent' => 'file', 'categories' => ['edit'], 'available' => true, 'seo' => ['examples' => []]],
    'mold' => ['route' => 'tools.mold', 'intent' => 'file', 'categories' => ['edit', 'craft'], 'available' => true, 'seo' => ['examples' => []]],
    'personalize' => ['route' => 'tools.personalize', 'intent' => 'file', 'categories' => ['edit', 'names'], 'available' => false],

    'organizer' => ['route' => 'tools.organizer', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['preset' => 'drawer'],
        ['preset' => 'office'],
        ['preset' => 'parts'],
    ]]],
    'modular' => ['route' => 'tools.modular', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['inner_w' => 300, 'inner_d' => 150, 'height' => 40, 'cols' => 6, 'rows' => 3, 'bins' => [
            ['x' => 0, 'y' => 0, 'w' => 2, 'h' => 3, 'color' => 'blue'], ['x' => 2, 'y' => 0, 'w' => 2, 'h' => 1, 'color' => 'white'], ['x' => 4, 'y' => 0, 'w' => 2, 'h' => 1, 'color' => 'white'], ['x' => 2, 'y' => 1, 'w' => 4, 'h' => 2, 'color' => 'orange'],
        ]]],
        ['params' => ['inner_w' => 160, 'inner_d' => 160, 'height' => 30, 'cols' => 4, 'rows' => 4, 'bins' => [
            ['x' => 0, 'y' => 0, 'w' => 2, 'h' => 2, 'color' => 'grey'], ['x' => 2, 'y' => 0, 'w' => 2, 'h' => 2, 'color' => 'yellow'], ['x' => 0, 'y' => 2, 'w' => 2, 'h' => 2, 'color' => 'yellow'], ['x' => 2, 'y' => 2, 'w' => 2, 'h' => 2, 'color' => 'grey'],
        ]]],
        ['params' => ['inner_w' => 240, 'inner_d' => 120, 'height' => 45, 'cols' => 4, 'rows' => 2, 'tray' => true, 'bins' => [
            ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 2, 'color' => 'green'], ['x' => 1, 'y' => 0, 'w' => 2, 'h' => 1, 'color' => 'white'], ['x' => 1, 'y' => 1, 'w' => 2, 'h' => 1, 'color' => 'white'], ['x' => 3, 'y' => 0, 'w' => 1, 'h' => 2, 'color' => 'green'],
        ]]],
    ]]],
    'box' => ['route' => 'tools.box', 'intent' => 'create', 'categories' => ['home', 'parts'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['inner_w' => 80, 'inner_d' => 50, 'inner_h' => 30, 'lid' => true]],
        ['params' => ['inner_w' => 120, 'inner_d' => 80, 'inner_h' => 40, 'lid' => true, 'cable_slot' => true, 'cable_d' => 8, 'radius' => 6]],
        ['params' => ['inner_w' => 40, 'inner_d' => 40, 'inner_h' => 20, 'lid' => false, 'radius' => 8]],
    ]]],
    'phone_stand' => ['route' => 'tools.phone_stand', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['style' => 'desk', 'width' => 75, 'angle' => 65]],
        ['params' => ['style' => 'wedge', 'width' => 80, 'angle' => 60, 'depth' => 70]],
        ['params' => ['style' => 'wall', 'width' => 80, 'screws' => true]],
    ]]],
    'holder' => ['route' => 'tools.holder', 'intent' => 'create', 'categories' => ['home', 'parts'], 'available' => true, 'verified' => '2026-09-29', 'seo' => ['examples' => [
        ['preset' => 'remote'],
        ['preset' => 'headphones'],
        ['preset' => 'broom'],
    ]]],
    'cap' => ['route' => 'tools.cap', 'intent' => 'create', 'categories' => ['parts', 'home'], 'available' => true, 'verified' => '2026-10-01', 'seo' => ['examples' => [
        ['preset' => 'pet'],
        ['preset' => 'profile'],
        ['preset' => 'jar'],
    ]]],
    'cable_holder' => ['route' => 'tools.cable_holder', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['count' => 4, 'cable' => 6]],
        ['params' => ['count' => 2, 'cable' => 10, 'wall' => 8]],
        ['params' => ['count' => 6, 'cable' => 4, 'screws' => true]],
    ]]],
    'vase' => ['route' => 'tools.vase', 'intent' => 'create', 'categories' => ['home', 'craft'], 'available' => true, 'seo' => ['examples' => [
        ['preset' => 'spiral'],
        ['preset' => 'smooth'],
        ['preset' => 'pot', 'params' => ['saucer' => true, 'drainage' => true]],
    ]]],
    'figure' => ['route' => 'tools.figure', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => []]],
    'relief' => ['route' => 'tools.relief', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => []]],
    'gifts' => ['route' => 'tools.gifts', 'intent' => 'create', 'categories' => ['names'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['kind' => 'sign', 'preset' => 'keyring', 'params' => ['line1' => 'Ela']]],
    'sign' => ['route' => 'tools.sign', 'intent' => 'create', 'categories' => ['names', 'signs'], 'available' => true, 'seo' => ['examples' => [
        ['preset' => 'keyring', 'params' => ['line1' => 'Jana']],
        ['preset' => 'door', 'params' => ['line1' => 'Novákovi', 'line2' => '12']],
        ['preset' => 'name', 'params' => ['line1' => 'Ela']],
    ]]],
    'nameplate' => ['route' => 'tools.nameplate', 'intent' => 'create', 'categories' => ['names', 'signs'], 'available' => true,
        'card' => ['kind' => 'sign', 'preset' => 'shaped', 'params' => ['line1' => 'Jana', 'artwork' => 'lib:hearts-stars/star']],
        'seo' => ['kind' => 'sign', 'examples' => [
            ['preset' => 'shaped', 'params' => ['line1' => 'Jana', 'artwork' => 'lib:hearts-stars/star']],
            ['preset' => 'shaped', 'params' => ['line1' => 'Rex', 'shape' => 'bone', 'border' => false, 'typeface' => 'titan', 'artwork' => 'lib:animals/paw', 'motif_at' => 'right']],
            ['preset' => 'shaped', 'params' => ['line1' => 'Ela', 'line2' => '2020', 'shape' => 'heart', 'keyring' => true, 'ring_at' => 'top', 'text_height' => 9, 'typeface' => 'lobster']],
        ]]],
    'text' => ['route' => 'tools.text', 'intent' => 'create', 'categories' => ['names', 'signs', 'home'], 'available' => true,
        'card' => ['kind' => 'sign', 'preset' => 'stand', 'params' => ['line1' => 'HOME']],
        'seo' => ['kind' => 'sign', 'examples' => [
            ['preset' => 'stand', 'params' => ['line1' => 'HOME']],
            ['preset' => 'stand', 'params' => ['line1' => 'Ela', 'typeface' => 'script', 'text_height' => 40, 'thickness' => 15, 'artwork' => 'lib:hearts-stars/heart', 'motif_at' => 'right']],
            ['preset' => 'stand', 'params' => ['line1' => 'KAVÁRNA', 'line2' => 'u Jany', 'typeface' => 'bebas', 'text_height' => 35, 'thickness' => 20]],
        ]]],
    'qr' => ['route' => 'tools.qr', 'intent' => 'create', 'categories' => ['signs', 'sell'], 'available' => true, 'verified' => '2026-10-04', 'seo' => ['examples' => [
        ['params' => ['size' => 70, 'url' => 'https://matplace.com', 'label' => 'matplace.com']],
        ['params' => ['size' => 90, 'url' => 'WIFI:T:WPA;S:Kavarna;P:dobrakava;;', 'label' => 'Wi-Fi', 'stand' => true]],
        ['params' => ['size' => 40, 'url' => 'https://matplace.com/models', 'label' => '', 'hole' => true]],
    ]]],
    'logo' => ['route' => 'tools.logo', 'intent' => 'create', 'categories' => ['images', 'signs'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['mode' => 'relief', 'shape' => 'rounded', 'width' => 100, 'line1' => 'ATELIER']],
        ['params' => ['mode' => 'standing', 'width' => 120, 'line1' => 'OPEN']],
        ['params' => ['mode' => 'cutout', 'shape' => 'circle', 'width' => 80, 'line1' => 'M']],
    ]]],
    'svg_to_stl' => ['route' => 'tools.svg_to_stl', 'intent' => 'create', 'categories' => ['images', 'craft'], 'available' => true,
        'card' => ['kind' => 'logo', 'preset' => 'extrude', 'params' => ['artwork' => 'lib:animals/cat', 'line1' => '', 'width' => 80, 'thickness' => 6]],
        'seo' => ['kind' => 'logo', 'examples' => [
            ['preset' => 'extrude', 'params' => ['artwork' => 'lib:animals/cat', 'line1' => '', 'width' => 80, 'thickness' => 6]],
            ['preset' => 'extrude', 'params' => ['artwork' => 'lib:nature/oak-leaf', 'line1' => '', 'width' => 100, 'thickness' => 3, 'bevel' => true]],
            ['preset' => 'extrude', 'params' => ['artwork' => 'lib:hearts-stars/star', 'line1' => '', 'width' => 60, 'thickness' => 20]],
        ]]],
    'cutter' => ['route' => 'tools.cutter', 'intent' => 'create', 'categories' => ['craft', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['width' => 70, 'line1' => 'Ela', 'typeface' => 'script']],
        ['params' => ['width' => 60, 'line1' => '5', 'typeface' => 'sans', 'edge' => 'sharp']],
        ['params' => ['width' => 90, 'line1' => 'MAMA', 'typeface' => 'serif', 'edge' => 'straight']],
    ]]],
    'stamp' => ['route' => 'tools.stamp', 'intent' => 'create', 'categories' => ['craft', 'images'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['width' => 50, 'line1' => 'EVA', 'mode' => 'raised', 'handle' => 'knob']],
        ['params' => ['width' => 60, 'line1' => 'HAND', 'line2' => 'MADE', 'mode' => 'raised', 'handle' => 'knob']],
        ['params' => ['width' => 40, 'line1' => 'OK', 'mode' => 'recessed', 'handle' => 'none']],
    ]]],
    'papel' => ['route' => 'tools.papel', 'intent' => 'create', 'categories' => ['images', 'craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:holidays/sugar-skull', 'width' => 150, 'height' => 200]],
        ['params' => ['artwork' => 'lib:hearts-stars/heart', 'width' => 120, 'height' => 120, 'border' => 'hearts']],
        ['params' => ['artwork' => 'lib:animals/butterfly', 'width' => 200, 'height' => 150, 'border' => 'diamonds', 'invert' => true]],
    ]]],
    'notes' => ['route' => 'tools.notes', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:animals/cat', 'width' => 90]],
        ['params' => ['line1' => 'Jana', 'typeface' => 'script', 'width' => 100, 'pen' => false]],
        ['params' => ['artwork' => 'lib:hearts-stars/star', 'width' => 70, 'pad' => 51, 'depth' => 10]],
    ]]],
    'hair_tie' => ['route' => 'tools.hair_tie', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:hearts-stars/crown', 'width' => 90]],
        ['params' => ['line1' => 'Ema', 'typeface' => 'script', 'width' => 110, 'post_h' => 120]],
        ['params' => ['artwork' => 'lib:animals/owl', 'width' => 80, 'post_d' => 20, 'post_h' => 70]],
    ]]],
    'candle_stand' => ['route' => 'tools.candle_stand', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:holidays/christmas-tree', 'width' => 100, 'jar_d' => 80]],
        ['params' => ['artwork' => 'lib:animals/deer', 'width' => 120, 'jar_d' => 103]],
        ['params' => ['line1' => 'Home', 'typeface' => 'script', 'width' => 110, 'jar_d' => 70]],
    ]]],
    'stencil' => ['route' => 'tools.stencil', 'intent' => 'create', 'categories' => ['craft', 'signs'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['width' => 120, 'line1' => 'BOA 8']],
        ['params' => ['width' => 160, 'line1' => 'FRAGILE']],
        ['params' => ['width' => 100, 'line1' => 'No.', 'line2' => '27']],
    ]]],
    'lightbox' => ['route' => 'tools.lightbox', 'intent' => 'create', 'categories' => ['signs'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['shape' => 'rect', 'width' => 180, 'line1' => 'OPEN']],
        ['params' => ['shape' => 'round', 'width' => 160, 'line1' => 'BAR']],
        ['params' => ['shape' => 'rect', 'width' => 240, 'line1' => 'CAFÉ', 'line2' => 'LUNA']],
    ]]],
    // a picture or a name in the colours of filaments (engines/python/shape_kinds.py): the pictures are of our own library
    'ornament' => ['route' => 'tools.ornament', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/gingerbread-man', 'width' => 80]],
        ['params' => ['artwork' => 'lib:colour/christmas-tree-colour', 'width' => 90, 'colors_n' => 5]],
        ['params' => ['artwork' => 'lib:colour/christmas-ball', 'width' => 70, 'frame' => 0]],
    ]]],
    // dough and icing with the name: two colours one on another, one filament change
    'gingerbread' => ['route' => 'tools.gingerbread', 'intent' => 'create', 'categories' => ['names', 'images'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['line1' => 'Ela', 'cookie' => 'man', 'width' => 90]],
        ['params' => ['line1' => 'Mamince', 'cookie' => 'heart', 'width' => 90]],
        ['params' => ['line1' => 'Tom', 'cookie' => 'star', 'width' => 80]],
    ]]],
    // a biscuit with icing drawn by hand; `strokes` = what was drawn, points in shares of the picture's width
    'cookie' => ['route' => 'tools.cookie', 'intent' => 'create', 'categories' => ['toys', 'images'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/gingerbread-man', 'width' => 80]],
        ['params' => ['artwork' => 'lib:hearts-stars/star', 'width' => 80, 'strokes' => [
            ['c' => 'white', 'w' => 2.5, 't' => 'round', 'p' => [[0.12, 0.55], [0.22, 0.60], [0.32, 0.54], [0.42, 0.60], [0.50, 0.54], [0.58, 0.60], [0.68, 0.54], [0.78, 0.60], [0.88, 0.55]]],
            ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.50, 0.38]]], ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.38, 0.22]]],
            ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.62, 0.22]]], ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.50, 0.75]]],
        ]]],
        ['params' => ['artwork' => 'lib:holidays/christmas-tree', 'width' => 90, 'hang' => true, 'strokes' => [
            ['c' => 'white', 'w' => 2.5, 't' => 'round', 'p' => [[0.20, 0.30], [0.30, 0.26], [0.40, 0.31], [0.50, 0.26], [0.60, 0.31], [0.70, 0.26], [0.80, 0.30]]],
            ['c' => 'white', 'w' => 2.5, 't' => 'round', 'p' => [[0.32, 0.56], [0.41, 0.52], [0.50, 0.57], [0.59, 0.52], [0.68, 0.56]]],
            ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.35, 0.42]]], ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.62, 0.43]]],
            ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.50, 0.70]]], ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.28, 0.17]]], ['c' => 'red', 'w' => 4, 't' => 'round', 'p' => [[0.72, 0.17]]],
        ]]],
    ]]],
    'topper' => ['route' => 'tools.topper', 'intent' => 'create', 'categories' => ['names', 'craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['template' => 'number', 'number' => '2', 'line1' => 'Olivia', 'width' => 110]],
        ['params' => ['template' => 'heart', 'line1' => 'Ela a Tom', 'width' => 120, 'text_size' => 90, 'text_y' => 5]],
        ['params' => ['template' => 'none', 'line1' => 'Všechno nejlepší', 'width' => 180, 'spike' => 80]],
    ]]],
    'beads' => ['route' => 'tools.beads', 'intent' => 'create', 'categories' => ['names', 'toys'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['line1' => 'JANA', 'shape' => 'cube', 'size' => 10]],
        ['params' => ['line1' => 'MÁMA ♥', 'shape' => 'ball', 'size' => 12]],
        ['params' => ['line1' => 'TOM 7', 'shape' => 'heart', 'size' => 12, 'style' => 'engraved']],
    ]]],
    'tray' => ['route' => 'tools.tray', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/paw-badge', 'width' => 100, 'height' => 15]],
        ['params' => ['artwork' => 'lib:hearts-stars/heart', 'width' => 110, 'height' => 20, 'floor' => 'plain']],
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'width' => 120, 'height' => 12, 'floor' => 'colors']],
    ]]],
    'name_cup' => ['route' => 'tools.name_cup', 'intent' => 'create', 'categories' => ['names', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['line1' => 'Jana', 'width' => 160, 'height' => 80]],
        ['params' => ['line1' => 'TOM', 'typeface' => 'sans', 'width' => 150, 'height' => 90]],
        ['params' => ['line1' => 'Ela', 'width' => 110, 'height' => 60, 'base' => false]],
    ]]],
    'name_letter' => ['route' => 'tools.name_letter', 'intent' => 'create', 'categories' => ['names', 'signs'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['line1' => 'Ela', 'height' => 120]],
        ['params' => ['line1' => 'Tomáš', 'height' => 150, 'letter_face' => 'serif', 'typeface' => 'sans']],
        ['params' => ['line1' => 'Ivana', 'height' => 100, 'thickness' => 12]],
    ]]],
    'charm' => ['route' => 'tools.charm', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/happy-ghost', 'width' => 45]],
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'width' => 50, 'eye_pos' => 0]],
        ['params' => ['line1' => 'Ela', 'typeface' => 'script', 'width' => 60]],
    ]]],
    // opens with a name: letters on a plate are two colours one on another, the kind of print the farm does today
    'keychain' => ['route' => 'tools.keychain', 'intent' => 'create', 'categories' => ['names', 'images'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['line1' => 'Jana', 'width' => 55, 'body' => 'rect']],
        ['params' => ['line1' => '', 'artwork' => 'lib:colour/paw-badge', 'width' => 50, 'body' => 'image', 'eye_pos' => 0]],
        ['params' => ['line1' => '', 'artwork' => 'lib:colour/smiling-star', 'width' => 45, 'body' => 'circle', 'eye_pos' => 0]],
    ]]],
    'earrings' => ['route' => 'tools.earrings', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/red-heart', 'width' => 28]],
        ['params' => ['artwork' => 'lib:colour/happy-ghost', 'width' => 32, 'mirror' => true]],
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'width' => 30, 'body' => 'circle']],
    ]]],
    'magnet' => ['route' => 'tools.magnet', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/paw-badge', 'width' => 60]],
        ['params' => ['artwork' => 'lib:colour/red-heart', 'width' => 50, 'disc' => 'd8x3', 'mount' => 'press']],
        ['params' => ['artwork' => 'lib:colour/snowman', 'width' => 45, 'body' => 'rect']],
    ]]],
    'badge' => ['route' => 'tools.badge', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'line1' => 'Jana', 'width' => 40]],
        ['params' => ['artwork' => 'lib:colour/red-heart', 'width' => 38, 'body' => 'circle']],
        ['params' => ['artwork' => 'lib:colour/paw-badge', 'line1' => 'Petr', 'width' => 45, 'body' => 'rect']],
    ]]],
    'medallion' => ['route' => 'tools.medallion', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'width' => 80, 'links' => 20]],
        ['params' => ['line1' => '1', 'typeface' => 'archivo', 'width' => 70, 'body' => 'star', 'links' => 24]],
        ['params' => ['artwork' => 'lib:colour/paw-badge', 'width' => 90, 'body' => 'hex', 'links' => 0]],
    ]]],
    'photo_organizer' => ['route' => 'tools.photo_organizer', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:nature/cloud', 'width' => 120, 'height' => 80, 'cell' => 40]],
        ['params' => ['artwork' => 'lib:nature/cloud', 'body' => 'circle', 'width' => 90, 'height' => 90, 'inside' => 'holes', 'hole_d' => 20]],
        ['params' => ['artwork' => 'lib:hearts-stars/heart', 'width' => 110, 'height' => 60, 'inside' => 'open']],
    ]]],
    'bag_charm' => ['route' => 'tools.bag_charm', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/red-heart', 'width' => 45]],
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'width' => 50, 'bag_hole' => 10]],
        ['params' => ['line1' => 'EMA', 'typeface' => 'titan', 'width' => 60, 'body' => 'rect']],
    ]]],
    // layers put together by hand; the page has the list of layers and their fields, dragging them in the preview is still to come (docs/P.md §7)
    'compose' => ['route' => 'tools.compose', 'intent' => 'create', 'categories' => ['names', 'images'], 'available' => true, 'seo' => ['examples' => [
        ['preset' => 'cloud'], ['preset' => 'topper'], ['preset' => 'tag'],
    ]]],
    // out of the catalogue until it was tried on real photos of real things (it is tested on drawn ones, docs/P.md)
    'insert' => ['route' => 'tools.insert', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => false],
    // out of the catalogue until one of each was printed and tried: does the clip hold a straw, does the tongue lift a tab (docs/P.md §5)
    'straw' => ['route' => 'tools.straw', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => false],
    'opener' => ['route' => 'tools.opener', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => false],
    'coaster' => ['route' => 'tools.coaster', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/snowman', 'width' => 100, 'body' => 'circle']],
        ['params' => ['artwork' => 'lib:colour/paw-badge', 'width' => 95, 'body' => 'hex', 'grooves' => true]],
        ['params' => ['artwork' => 'lib:colour/christmas-tree-colour', 'width' => 100, 'body' => 'square']],
    ]]],
    // session 3 (docs/R.md): a picture as plates for the wall (engines/python/art_tool.py) and the editing of a model file (edit_tool.py)
    'filament_art' => ['route' => 'tools.filament_art', 'intent' => 'create', 'categories' => ['images', 'craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'round', 'width' => 160, 'height' => 160, 'colors_n' => 5]],
        ['params' => ['artwork' => 'lib:colour/christmas-tree-colour', 'mode' => 'layered', 'frame' => 'none', 'width' => 150, 'height' => 150, 'colors_n' => 5]],
        ['params' => ['artwork' => 'lib:colour/gingerbread-man', 'mode' => 'stack', 'shape' => 'rect', 'width' => 120, 'height' => 140, 'colors_n' => 4]],
    ]]],
    // the card of a file tool is its own output: a vase of the vase tool at 300 mm, cut for the farm's bed
    'split' => ['route' => 'tools.split', 'intent' => 'file', 'categories' => ['edit'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'split', 'kind' => 'vase', 'preset' => 'smooth', 'params' => ['height' => 300, 'top_d' => 120, 'bottom_d' => 90], 'edit_params' => ['joint' => 'pins']]],
    // the hollow's card is a big letter with a quarter taken out, so the wall shows; life size is a vase of the vase tool at 400 mm, cut for the bed
    'hollow' => ['route' => 'tools.hollow', 'intent' => 'file', 'categories' => ['edit'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'hollow', 'kind' => 'name_letter', 'params' => ['line1' => 'Ela', 'height' => 120, 'thickness' => 15], 'edit_params' => ['wall' => 2.5, 'view' => 'cut']]],
    'life_size' => ['route' => 'tools.life_size', 'intent' => 'file', 'categories' => ['edit', 'images'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'life_size', 'kind' => 'vase', 'preset' => 'smooth', 'params' => ['height' => 180, 'top_d' => 62, 'bottom_d' => 54], 'edit_params' => ['height_cm' => 40, 'joint' => 'pins']]],
    // the puzzle's card is a logo plate of the logo tool cut into 3 × 4 pieces with knobs
    'puzzle' => ['route' => 'tools.puzzle', 'intent' => 'file', 'categories' => ['edit', 'toys', 'images'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'puzzle', 'kind' => 'logo', 'params' => ['mode' => 'relief', 'shape' => 'rect', 'width' => 120, 'line1' => 'PUZZLE', 'line2' => '2026'], 'edit_params' => ['rows' => 3, 'cols' => 4, 'lock' => 'tabs']]],
    // a holder out of a model: a big letter of the letter tool, stood up to 130 mm, with a can's cavity taken out of its top
    'holder_model' => ['route' => 'tools.holder_model', 'intent' => 'file', 'categories' => ['edit', 'home'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'holder', 'kind' => 'vase', 'preset' => 'smooth', 'params' => ['height' => 120, 'top_d' => 90, 'bottom_d' => 80], 'edit_params' => ['cavity' => 'can330', 'cav_depth_own' => true, 'cav_depth' => 100, 'height' => 0]]],
    // a potion bottle out of a model: a bellied vase of the vase tool with a neck, a cork and a label
    'potion' => ['route' => 'tools.potion', 'intent' => 'file', 'categories' => ['edit', 'toys', 'craft'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'potion', 'kind' => 'vase', 'preset' => 'belly', 'params' => ['height' => 100, 'top_d' => 40, 'bottom_d' => 50], 'edit_params' => ['neck_d' => 22, 'neck_h' => 24, 'cut' => 0, 'text' => 'Elixir', 'label' => true]]],
    // a flexi out of a model: a big letter I of the letter tool, cut into segments with ball joints
    'flexi_cut' => ['route' => 'tools.flexi_cut', 'intent' => 'file', 'categories' => ['edit', 'toys'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'flexi_cut', 'kind' => 'name_letter', 'params' => ['line1' => 'Ivo', 'height' => 140, 'thickness' => 14], 'edit_params' => ['segments' => 5, 'ball_d' => 8]]],
    // the card: a picture in colours (every plate its own filament) packed as a coloured 3MF and split by colour again
    'colors' => ['route' => 'tools.colors', 'intent' => 'file', 'categories' => ['edit', 'images'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'colors', 'kind' => 'filament_art', 'params' => ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'none', 'width' => 160, 'height' => 160, 'colors_n' => 5], 'edit_params' => ['depth' => 1.2]]],
    // the card: a dish round the footprint of a rounded plate
    'soap_model' => ['route' => 'tools.soap_model', 'intent' => 'file', 'categories' => ['edit', 'home'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'soap', 'kind' => 'logo', 'params' => ['mode' => 'relief', 'shape' => 'rounded', 'width' => 90, 'line1' => 'SOAP'], 'edit_params' => ['drain' => 'grooves']]],
    // the card: a domed cap grown to a head of 56 cm, a window in front, strap slots in the sides
    'wearable' => ['route' => 'tools.wearable', 'intent' => 'file', 'categories' => ['edit', 'craft'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'wearable', 'kind' => 'cap', 'params' => ['style' => 'push', 'shape' => 'round', 'head' => 'dome', 'size_a' => 60, 'height' => 30, 'wall' => 2], 'edit_params' => ['measure' => 'head', 'circumference' => 56, 'windows' => '[{"side":"front","shape":"rect","w":80,"h":30}]', 'straps' => true]]],
    // the card: a square coaster 6 mm thick with a word on top and the groove, its slider and four detents along the lower edge
    'slider' => ['route' => 'tools.slider', 'intent' => 'file', 'categories' => ['edit', 'toys'], 'available' => true, 'seo' => ['examples' => []],
        'card' => ['edit' => 'slider', 'kind' => 'coaster', 'params' => ['body' => 'square', 'width' => 100, 'thickness' => 6, 'frame' => 2, 'line1' => 'FIDGET'], 'edit_params' => ['detents' => 4, 'dy' => -30]]],
    'mosaic' => ['route' => 'tools.mosaic', 'intent' => 'create', 'categories' => ['images', 'craft'], 'available' => false],
    // session 4: selling and planning (no geometry; the arithmetic is App\Domain\Sell, the cards are drawn by engines/python/sell_cards.py)
    'cost' => ['route' => 'tools.cost', 'intent' => 'create', 'categories' => ['sell'], 'available' => true, 'seo' => ['examples' => []]],
    'profit' => ['route' => 'tools.profit', 'intent' => 'create', 'categories' => ['sell'], 'available' => true, 'seo' => ['examples' => []]],
    'plan' => ['route' => 'tools.plan', 'intent' => 'create', 'categories' => ['sell'], 'available' => true, 'seo' => ['examples' => []]],
    'vendors' => ['route' => 'tools.vendors', 'intent' => 'create', 'categories' => ['sell'], 'available' => true, 'seo' => ['examples' => []]],
    // session 4, the studio: a picture from a description (Gemini), the texts of a listing (Claude), a product photo without its background (rembg)
    'image' => ['route' => 'tools.image', 'intent' => 'create', 'categories' => ['images', 'sell'], 'available' => true, 'seo' => ['examples' => []]],
    'listing' => ['route' => 'tools.listing', 'intent' => 'create', 'categories' => ['sell'], 'available' => true, 'seo' => ['examples' => []]],
    'photo' => ['route' => 'tools.photo', 'intent' => 'create', 'categories' => ['sell'], 'available' => true, 'seo' => ['examples' => []]],

    'spare' => ['route' => 'tools.spare', 'intent' => 'spare', 'categories' => ['parts'], 'available' => (bool) env('FEATURE_MARKETPLACE', false)],   // an inquiry to printers: marketplace only
];
