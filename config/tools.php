<?php

/**
 * Tool catalogue. A tool is listed only when it really generates a model and continues to the price and inquiry
 * ('available' => true); everything else stays invisible, there are no "coming soon" cards with dead buttons.
 *
 * intent:     file | create | spare          (the three entrances on the tools page)
 * categories: gifts | home | signs | craft | file   (filters inside "I want to create something")
 * seo:        the tool's page as content for search engines. The texts live in lang/<locale>/tools_seo/<tool>.php
 *             (h1, intro, steps, faq, examples). `examples` = up to three outputs of the tool drawn in advance by
 *             `php artisan matplace:tool-examples` into public/img/tool-examples/<tool>-<n>.png: the parameters here are
 *             what the tool is given, the caption of each picture is `examples.<n>` of the texts.
 *             `schema` = the kind of thing the page describes for structured data (HowTo and FAQPage always).
 */
return [
    'calc' => ['route' => 'home', 'intent' => 'file', 'categories' => ['file'], 'available' => true, 'seo' => ['examples' => []]],
    'repair' => ['route' => 'tools.repair', 'intent' => 'file', 'categories' => ['file'], 'available' => true, 'seo' => ['examples' => []]],
    'check' => ['route' => 'tools.check', 'intent' => 'file', 'categories' => ['file'], 'available' => true, 'seo' => ['examples' => []]],
    'mold' => ['route' => 'tools.mold', 'intent' => 'file', 'categories' => ['file', 'craft'], 'available' => true, 'seo' => ['examples' => []]],
    'personalize' => ['route' => 'tools.personalize', 'intent' => 'file', 'categories' => ['file', 'gifts'], 'available' => false],

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
    'box' => ['route' => 'tools.box', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['inner_w' => 80, 'inner_d' => 50, 'inner_h' => 30, 'lid' => true]],
        ['params' => ['inner_w' => 120, 'inner_d' => 80, 'inner_h' => 40, 'lid' => true, 'cable_slot' => true, 'cable_d' => 8, 'radius' => 6]],
        ['params' => ['inner_w' => 40, 'inner_d' => 40, 'inner_h' => 20, 'lid' => false, 'radius' => 8]],
    ]]],
    'phone_stand' => ['route' => 'tools.phone_stand', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['style' => 'desk', 'width' => 75, 'angle' => 65]],
        ['params' => ['style' => 'wedge', 'width' => 80, 'angle' => 60, 'depth' => 70]],
        ['params' => ['style' => 'wall', 'width' => 80, 'screws' => true]],
    ]]],
    'holder' => ['route' => 'tools.holder', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
        ['preset' => 'remote'],
        ['preset' => 'headphones'],
        ['preset' => 'broom'],
    ]]],
    'cap' => ['route' => 'tools.cap', 'intent' => 'create', 'categories' => ['home'], 'available' => true, 'seo' => ['examples' => [
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
    'figure' => ['route' => 'tools.figure', 'intent' => 'create', 'categories' => ['gifts'], 'available' => true, 'seo' => ['examples' => []]],
    'relief' => ['route' => 'tools.relief', 'intent' => 'create', 'categories' => ['gifts'], 'available' => true, 'seo' => ['examples' => []]],
    'gifts' => ['route' => 'tools.gifts', 'intent' => 'create', 'categories' => ['gifts'], 'available' => true, 'seo' => ['examples' => []]],
    'sign' => ['route' => 'tools.sign', 'intent' => 'create', 'categories' => ['signs', 'gifts'], 'available' => true, 'seo' => ['examples' => [
        ['preset' => 'keyring', 'params' => ['line1' => 'Jana']],
        ['preset' => 'door', 'params' => ['line1' => 'Novákovi', 'line2' => '12']],
        ['preset' => 'name', 'params' => ['line1' => 'Ela']],
    ]]],
    'qr' => ['route' => 'tools.qr', 'intent' => 'create', 'categories' => ['signs'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['size' => 70, 'url' => 'https://matplace.com', 'label' => 'matplace.com']],
        ['params' => ['size' => 90, 'url' => 'WIFI:T:WPA;S:Kavarna;P:dobrakava;;', 'label' => 'Wi-Fi', 'stand' => true]],
        ['params' => ['size' => 40, 'url' => 'https://matplace.com/models', 'label' => '', 'hole' => true]],
    ]]],
    'logo' => ['route' => 'tools.logo', 'intent' => 'create', 'categories' => ['signs', 'craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['mode' => 'relief', 'shape' => 'rounded', 'width' => 100, 'line1' => 'ATELIER']],
        ['params' => ['mode' => 'standing', 'width' => 120, 'line1' => 'OPEN']],
        ['params' => ['mode' => 'cutout', 'shape' => 'circle', 'width' => 80, 'line1' => 'M']],
    ]]],
    'cutter' => ['route' => 'tools.cutter', 'intent' => 'create', 'categories' => ['home', 'gifts', 'craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['width' => 70, 'line1' => 'Ela', 'typeface' => 'script']],
        ['params' => ['width' => 60, 'line1' => '5', 'typeface' => 'sans', 'edge' => 'sharp']],
        ['params' => ['width' => 90, 'line1' => 'MAMA', 'typeface' => 'serif', 'edge' => 'straight']],
    ]]],
    'stamp' => ['route' => 'tools.stamp', 'intent' => 'create', 'categories' => ['craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['width' => 50, 'line1' => 'EVA', 'mode' => 'raised', 'handle' => 'knob']],
        ['params' => ['width' => 60, 'line1' => 'HAND', 'line2' => 'MADE', 'mode' => 'raised', 'handle' => 'knob']],
        ['params' => ['width' => 40, 'line1' => 'OK', 'mode' => 'recessed', 'handle' => 'none']],
    ]]],
    'stencil' => ['route' => 'tools.stencil', 'intent' => 'create', 'categories' => ['craft'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['width' => 120, 'line1' => 'BOA 8']],
        ['params' => ['width' => 160, 'line1' => 'FRAGILE']],
        ['params' => ['width' => 100, 'line1' => 'No.', 'line2' => '27']],
    ]]],
    'lightbox' => ['route' => 'tools.lightbox', 'intent' => 'create', 'categories' => ['signs', 'gifts'], 'available' => true, 'seo' => ['examples' => [
        ['params' => ['shape' => 'rect', 'width' => 180, 'line1' => 'OPEN']],
        ['params' => ['shape' => 'round', 'width' => 160, 'line1' => 'BAR']],
        ['params' => ['shape' => 'rect', 'width' => 240, 'line1' => 'CAFÉ', 'line2' => 'LUNA']],
    ]]],
    'mosaic' => ['route' => 'tools.mosaic', 'intent' => 'create', 'categories' => ['craft', 'gifts'], 'available' => false],

    'spare' => ['route' => 'tools.spare', 'intent' => 'spare', 'categories' => [], 'available' => (bool) env('FEATURE_MARKETPLACE', false)],   // an inquiry to printers: marketplace only
];
