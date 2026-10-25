<?php

/*
 * What a visitor comes for (the row above the categories of /tools, docs/AN.md §4): a gift, a model from a photo, the
 * home, a spare part, something for a business. An intent gathers the tools of whole categories (`cats`, config/tools.php
 * `categories`), the tools that take a kind of input (`inputs`, App\Domain\Tools\ToolInput: photo, picture, text_or_picture…)
 * and tools named one by one (`tools`); only tools on offer are shown (App\Domain\Tools\ToolIntents). Names in
 * lang/<locale>/tools.php `aim.<intent>`. Kept apart from config/tools.php, whose every key is read as a tool.
 */
return [
    'gift' => ['cats' => ['names'], 'tools' => ['gifts']],
    'photo' => ['inputs' => ['photo', 'picture', 'text_or_picture']],
    'home' => ['cats' => ['home']],
    'spare' => ['cats' => ['parts'], 'tools' => ['repair', 'check', 'calc', 'spare']],
    'business' => ['tools' => ['qr', 'logo', 'badge', 'sign', 'nameplate', 'stencil', 'stamp', 'coaster', 'keychain', 'lightbox', 'text']],
];
