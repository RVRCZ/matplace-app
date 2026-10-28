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
        // the search entry pages of session N1 (docs/AP.md §1): file tools first
        'svg_to_stl' => ['related' => ['logo', 'stamp', 'cutter', 'stencil'], 'blog' => ['svg-do-stl-logo-a-kresba-jako-3d-model', 'jak-pripravit-stl-soubor-pro-3d-tisk', 'cena-3d-tisku-z-modelu']],
        'repair' => ['related' => ['check', 'hollow', 'split'], 'blog' => ['jak-pripravit-stl-soubor-pro-3d-tisk', 'rozdeleni-a-vyduteni-3d-modelu']],
        'check' => ['related' => ['repair', 'split', 'life_size'], 'blog' => ['jak-pripravit-stl-soubor-pro-3d-tisk', 'cena-3d-tisku-z-modelu']],
        'hollow' => ['related' => ['split', 'life_size', 'repair'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu', 'cena-3d-tisku-z-modelu']],
        'split' => ['related' => ['life_size', 'hollow', 'check'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu', 'jak-pripravit-stl-soubor-pro-3d-tisk']],
        'gcode' => ['related' => ['check', 'hollow', 'split'], 'blog' => ['gcode-zpet-na-stl-kdyz-se-ztratil-model', 'jak-pripravit-stl-soubor-pro-3d-tisk', 'cena-3d-tisku-z-modelu']],
        'relief' => ['related' => ['figure', 'filament_art', 'lightbox'], 'blog' => ['litofanie-z-fotky', 'vytvoreni-stl-z-fotek-pomoci-ai-kompletni-navod-pro-3d-tisk-2026']],
        'map' => ['related' => ['relief', 'nameplate', 'coaster'], 'blog' => ['3d-mapa-mesta-z-openstreetmap', 'cena-3d-tisku-z-modelu']],
        // the Christmas tools (docs/AP.md §6)
        'gingerbread' => ['related' => ['ornament', 'nameplate', 'cutter'], 'blog' => ['vanocni-ozdoby-se-jmenem-z-3d-tiskarny', 'vanocni-ozdoba-z-obrazku', 'cedulka-jmenovka-a-klicenka-se-jmenem']],
        'ornament' => ['related' => ['gingerbread', 'nameplate', 'charm'], 'blog' => ['vanocni-ozdoba-z-obrazku', 'vanocni-ozdoby-se-jmenem-z-3d-tiskarny', 'klicenka-a-privesek-z-obrazku']],
        'cutter' => ['related' => ['stamp', 'gingerbread', 'cookie'], 'blog' => ['vykrajovatko-na-cukrovi-podle-vlastniho-tvaru', 'jmenovky-na-darky-a-vykrajovatka-vanocni-dilna', 'vanocni-ozdoba-z-obrazku']],
        'nameplate' => ['related' => ['sign', 'keychain', 'gingerbread'], 'blog' => ['jmenovky-na-darky-a-vykrajovatka-vanocni-dilna', 'cedulka-jmenovka-a-klicenka-se-jmenem', 'klicenka-a-privesek-z-obrazku']],
        'topper' => ['related' => ['name_letter', 'nameplate', 'cutter'], 'blog' => ['jmenovky-na-darky-a-vykrajovatka-vanocni-dilna', 'cedulka-jmenovka-a-klicenka-se-jmenem', 'vykrajovatko-na-cukrovi-podle-vlastniho-tvaru']],
        'name_letter' => ['related' => ['nameplate', 'topper', 'text'], 'blog' => ['vanocni-ozdoby-se-jmenem-z-3d-tiskarny', 'cedulka-jmenovka-a-klicenka-se-jmenem', 'vanocni-ozdoba-z-obrazku']],
        // the next 10 tools, batch A (docs/AP.md §9)
        'phone_stand' => ['related' => ['holder', 'cable_holder', 'box'], 'blog' => ['stojanek-na-telefon-na-miru', 'cena-3d-tisku-z-modelu']],
        'sign' => ['related' => ['nameplate', 'keychain', 'name_letter'], 'blog' => ['cedulka-jmenovka-a-klicenka-se-jmenem', 'jmenovky-na-darky-a-vykrajovatka-vanocni-dilna']],
        'keychain' => ['related' => ['charm', 'sign', 'nameplate'], 'blog' => ['klicenka-a-privesek-z-obrazku', 'cedulka-jmenovka-a-klicenka-se-jmenem']],
    ],
];
