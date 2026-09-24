<?php

/**
 * Tool catalogue. A tool is listed only when it really generates a model and continues to the price and inquiry
 * ('available' => true); everything else stays invisible, there are no "coming soon" cards with dead buttons.
 *
 * intent:     file | create | spare          (the three entrances on the tools page)
 * categories: gifts | home | signs | craft | file   (filters inside "I want to create something")
 */
return [
    'calc' => ['route' => 'home', 'intent' => 'file', 'categories' => ['file'], 'available' => true],
    'check' => ['route' => 'tools.check', 'intent' => 'file', 'categories' => ['file'], 'available' => true],
    'mold' => ['route' => 'tools.mold', 'intent' => 'file', 'categories' => ['file', 'craft'], 'available' => true],
    'personalize' => ['route' => 'tools.personalize', 'intent' => 'file', 'categories' => ['file', 'gifts'], 'available' => false],

    'organizer' => ['route' => 'tools.organizer', 'intent' => 'create', 'categories' => ['home'], 'available' => true],
    'modular' => ['route' => 'tools.modular', 'intent' => 'create', 'categories' => ['home'], 'available' => true],
    'box' => ['route' => 'tools.box', 'intent' => 'create', 'categories' => ['home'], 'available' => true],
    'phone_stand' => ['route' => 'tools.phone_stand', 'intent' => 'create', 'categories' => ['home'], 'available' => true],
    'cable_holder' => ['route' => 'tools.cable_holder', 'intent' => 'create', 'categories' => ['home'], 'available' => true],
    'vase' => ['route' => 'tools.vase', 'intent' => 'create', 'categories' => ['home', 'craft'], 'available' => true],
    'figure' => ['route' => 'tools.figure', 'intent' => 'create', 'categories' => ['gifts'], 'available' => true],
    'relief' => ['route' => 'tools.relief', 'intent' => 'create', 'categories' => ['gifts'], 'available' => true],
    'sign' => ['route' => 'tools.sign', 'intent' => 'create', 'categories' => ['signs', 'gifts'], 'available' => true],
    'qr' => ['route' => 'tools.qr', 'intent' => 'create', 'categories' => ['signs'], 'available' => true],
    'logo' => ['route' => 'tools.logo', 'intent' => 'create', 'categories' => ['signs', 'craft'], 'available' => true],
    'stamp' => ['route' => 'tools.stamp', 'intent' => 'create', 'categories' => ['craft'], 'available' => true],
    'stencil' => ['route' => 'tools.stencil', 'intent' => 'create', 'categories' => ['craft'], 'available' => true],
    'lightbox' => ['route' => 'tools.lightbox', 'intent' => 'create', 'categories' => ['signs', 'gifts'], 'available' => true],
    'mosaic' => ['route' => 'tools.mosaic', 'intent' => 'create', 'categories' => ['craft', 'gifts'], 'available' => false],

    'spare' => ['route' => 'tools.spare', 'intent' => 'spare', 'categories' => [], 'available' => (bool) env('FEATURE_MARKETPLACE', false)],   // an inquiry to printers: marketplace only
];
