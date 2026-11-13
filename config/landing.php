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
        'figure' => ['related' => ['pet', 'relief', 'gifts'], 'blog' => ['jak-vznika-busta-z-fotky', 'figurka-z-fotky-busta-a-mazlicek']],
        'pet' => ['related' => ['figure', 'relief', 'gifts'], 'blog' => ['figurka-z-fotky-busta-a-mazlicek', 'jak-vznika-busta-z-fotky']],
        'qr' => ['related' => ['sign', 'nameplate', 'keychain'], 'blog' => ['qr-cedulka-a-wifi-stojanek', 'kolik-stoji-3d-tisk-na-zakazku-v-roce-2026']],
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
        // batch B (docs/AP.md §9); the box keeps its entry above
        'medallion' => ['related' => ['badge', 'sign', 'keychain'], 'blog' => ['co-lide-tisknou-nejcasteji-cisla-z-farmy', 'cedulka-jmenovka-a-klicenka-se-jmenem']],
        'vase' => ['related' => ['holder', 'organizer', 'hollow'], 'blog' => ['vaza-a-obal-na-kvetinac-na-miru', 'pla-vs-petg-vs-asa-jaky-material-vybrat']],
        // batch C (docs/AP.md §9)
        'logo' => ['related' => ['svg_to_stl', 'lightbox', 'stamp'], 'blog' => ['svg-do-stl-logo-a-kresba-jako-3d-model', 'cedulka-jmenovka-a-klicenka-se-jmenem']],
        'holder' => ['related' => ['cable_holder', 'phone_stand', 'box'], 'blog' => ['drzak-na-zed-na-miru', 'pla-vs-petg-vs-asa-jaky-material-vybrat']],
        'stamp' => ['related' => ['cutter', 'logo', 'svg_to_stl'], 'blog' => ['vykrajovatko-na-cukrovi-podle-vlastniho-tvaru', 'svg-do-stl-logo-a-kresba-jako-3d-model']],
        'lightbox' => ['related' => ['logo', 'sign', 'name_letter'], 'blog' => ['cedulka-jmenovka-a-klicenka-se-jmenem', 'svg-do-stl-logo-a-kresba-jako-3d-model']],
        // the second ten (docs/AP.md §12), batch D
        'text' => ['related' => ['sign', 'name_letter', 'nameplate'], 'blog' => ['cedulka-jmenovka-a-klicenka-se-jmenem', 'jmenovky-na-darky-a-vykrajovatka-vanocni-dilna']],
        'filament_art' => ['related' => ['relief', 'svg_to_stl', 'logo'], 'blog' => ['litofanie-z-fotky', 'vanocni-ozdoba-z-obrazku']],
        'modular' => ['related' => ['organizer', 'box', 'holder'], 'blog' => ['organizer-do-supliku-na-miru', 'krabicka-na-miru']],
        // batch E (gifts)
        'cap' => ['related' => ['box', 'holder', 'cable_holder'], 'blog' => ['krabicka-na-miru', 'pla-vs-petg-vs-asa-jaky-material-vybrat']],
        'coaster' => ['related' => ['magnet', 'nameplate', 'ornament'], 'blog' => ['vanocni-ozdoba-z-obrazku', 'vanocni-darky-z-3d-tisku-do-24-12-terminy']],
        'magnet' => ['related' => ['coaster', 'keychain', 'ornament'], 'blog' => ['vanocni-ozdoba-z-obrazku', 'klicenka-a-privesek-z-obrazku']],
        'earrings' => ['related' => ['keychain', 'medallion', 'nameplate'], 'blog' => ['klicenka-a-privesek-z-obrazku', 'vanocni-darky-z-3d-tisku-do-24-12-terminy']],
        // batch F
        'puzzle' => ['related' => ['relief', 'coaster', 'logo'], 'blog' => ['litofanie-z-fotky', 'rozdeleni-a-vyduteni-3d-modelu']],
        'cable_holder' => ['related' => ['holder', 'phone_stand', 'box'], 'blog' => ['drzak-na-zed-na-miru', 'stojanek-na-telefon-na-miru']],
        'charm' => ['related' => ['keychain', 'earrings', 'magnet'], 'blog' => ['klicenka-a-privesek-z-obrazku', 'vanocni-ozdoba-z-obrazku']],
    ],

    /*
     * "Where next" alone (docs/AP.md §15): the tools without the blocks above still lead to the tools beside them and to
     * the articles about them, so every tool page links to its neighbours and no article stands without a way to a tool.
     * Same shape as `pages`, without texts in lang/<locale>/landing.php.
     */
    'next' => [
        'gifts' => ['related' => ['sign', 'keychain', 'ornament', 'figure'], 'blog' => ['vanocni-darky-z-3d-tisku-do-24-12-terminy', 'vanocni-ozdoby-se-jmenem-z-3d-tiskarny', 'cedulka-jmenovka-a-klicenka-se-jmenem']],
        'mold' => ['related' => ['hollow', 'split', 'repair'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu', 'jak-pripravit-stl-soubor-pro-3d-tisk']],
        'papel' => ['related' => ['filament_art', 'stencil', 'relief'], 'blog' => ['litofanie-z-fotky', 'vanocni-ozdoba-z-obrazku']],
        'notes' => ['related' => ['photo_organizer', 'name_cup', 'hair_tie'], 'blog' => ['organizer-do-supliku-na-miru']],
        'hair_tie' => ['related' => ['notes', 'candle_stand', 'tray'], 'blog' => ['klicenka-a-privesek-z-obrazku']],
        'candle_stand' => ['related' => ['notes', 'hair_tie', 'lightbox'], 'blog' => ['vanocni-ozdoba-z-obrazku']],
        'stencil' => ['related' => ['stamp', 'svg_to_stl', 'papel'], 'blog' => ['svg-do-stl-logo-a-kresba-jako-3d-model']],
        'cookie' => ['related' => ['cutter', 'gingerbread', 'magnet'], 'blog' => ['vykrajovatko-na-cukrovi-podle-vlastniho-tvaru']],
        'beads' => ['related' => ['keychain', 'name_letter', 'charm'], 'blog' => ['cedulka-jmenovka-a-klicenka-se-jmenem']],
        'tray' => ['related' => ['photo_organizer', 'coaster', 'organizer'], 'blog' => ['organizer-do-supliku-na-miru']],
        'name_cup' => ['related' => ['photo_organizer', 'name_letter', 'text'], 'blog' => ['cedulka-jmenovka-a-klicenka-se-jmenem']],
        'badge' => ['related' => ['bag_charm', 'charm', 'keychain'], 'blog' => ['klicenka-a-privesek-z-obrazku']],
        'photo_organizer' => ['related' => ['tray', 'name_cup', 'organizer'], 'blog' => ['organizer-do-supliku-na-miru']],
        'bag_charm' => ['related' => ['charm', 'badge', 'keychain'], 'blog' => ['klicenka-a-privesek-z-obrazku']],
        'compose' => ['related' => ['keychain', 'nameplate', 'topper'], 'blog' => ['cedulka-jmenovka-a-klicenka-se-jmenem', 'klicenka-a-privesek-z-obrazku']],
        'life_size' => ['related' => ['split', 'hollow', 'wearable'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu']],
        'holder_model' => ['related' => ['holder', 'soap_model', 'cable_holder'], 'blog' => ['drzak-na-zed-na-miru']],
        'potion' => ['related' => ['hollow', 'holder_model', 'flexi_cut'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu']],
        'flexi_cut' => ['related' => ['slider', 'split', 'potion'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu']],
        'colors' => ['related' => ['gcode', 'split', 'filament_art'], 'blog' => ['jak-pripravit-stl-soubor-pro-3d-tisk']],
        'soap_model' => ['related' => ['holder_model', 'hollow', 'tray'], 'blog' => ['pla-vs-petg-vs-asa-jaky-material-vybrat']],
        'wearable' => ['related' => ['life_size', 'split', 'hollow'], 'blog' => ['rozdeleni-a-vyduteni-3d-modelu']],
        'slider' => ['related' => ['flexi_cut', 'puzzle', 'coaster'], 'blog' => ['jak-pripravit-stl-soubor-pro-3d-tisk']],
        // the headband came with main on 13 Nov 2026; no article is about it yet
        'headband' => ['related' => ['earrings', 'medallion', 'wearable'], 'blog' => []],
    ],
];
