<?php

/**
 * Tool catalogue. A tool is listed only when it really generates a model and continues to the price and inquiry
 * ('available' => true); everything else stays invisible, there are no "coming soon" cards with dead buttons.
 *
 * intent:     file | create | spare          (the three entrances on the tools page)
 * categories: images | names | home | parts | toys | signs | craft | edit | sell   (the filter of the tools page, also /tools#<category>;
 *             names in lang/<locale>/tools.php `cats`; the words a tool is found by in `keywords`)
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
    'charm' => ['route' => 'tools.charm', 'intent' => 'create', 'categories' => ['images', 'names'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/happy-ghost', 'width' => 45]],
        ['params' => ['artwork' => 'lib:colour/smiling-star', 'width' => 50, 'eye_pos' => 0]],
        ['params' => ['line1' => 'Ela', 'typeface' => 'script', 'width' => 60]],
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
    'coaster' => ['route' => 'tools.coaster', 'intent' => 'create', 'categories' => ['images', 'home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['artwork' => 'lib:colour/snowman', 'width' => 100, 'body' => 'circle']],
        ['params' => ['artwork' => 'lib:colour/paw-badge', 'width' => 95, 'body' => 'hex', 'grooves' => true]],
        ['params' => ['artwork' => 'lib:colour/christmas-tree-colour', 'width' => 100, 'body' => 'square']],
    ]]],
    'mosaic' => ['route' => 'tools.mosaic', 'intent' => 'create', 'categories' => ['images', 'craft'], 'available' => false],

    'spare' => ['route' => 'tools.spare', 'intent' => 'spare', 'categories' => ['parts'], 'available' => (bool) env('FEATURE_MARKETPLACE', false)],   // an inquiry to printers: marketplace only
];
