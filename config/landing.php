<?php

/*
 * Landing pages (docs/AH.md §11): the blocks under the tool of the pages people search for most – the price of a 3D
 * print (the home page, `calc`), a figure from a photo, the QR sign, the organiser, the box. Texts are in
 * lang/<locale>/landing.php; here is only what links where. `related`: tools shown as "similar" (left out when not
 * public); `blog`: article slugs, shown only while the article is published in that language (session 1 rewrites them).
 * The results with photos are config/site.php `example_items` (shown only with SITE_EXAMPLES).
 */
return [
    'pages' => [
        'calc' => ['related' => ['repair', 'check', 'box', 'organizer'], 'blog' => ['kolik-stoji-3d-tisk-na-zakazku-v-roce-2026', 'jak-pripravit-stl-soubor-pro-3d-tisk', 'pla-vs-petg-vs-asa-jaky-material-vybrat']],
        'figure' => ['related' => ['pet', 'relief', 'gifts'], 'blog' => ['jak-vznika-busta-z-fotky']],
        'pet' => ['related' => ['figure', 'relief', 'gifts'], 'blog' => ['jak-vznika-busta-z-fotky']],
        'qr' => ['related' => ['sign', 'nameplate', 'keychain'], 'blog' => ['kolik-stoji-3d-tisk-na-zakazku-v-roce-2026']],
        'organizer' => ['related' => ['modular', 'box', 'tray'], 'blog' => ['pla-vs-petg-vs-asa-jaky-material-vybrat']],
        'box' => ['related' => ['organizer', 'modular', 'cable_holder'], 'blog' => ['pla-vs-petg-vs-asa-jaky-material-vybrat']],
    ],
];
