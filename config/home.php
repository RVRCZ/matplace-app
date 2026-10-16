<?php

/*
 * The homepage. `tools` are the cards under the first screen, in this order: what people make most often with
 * a 3D printer (a name as a gift, a photo as a picture, order in a drawer…). Keys of config/tools.php; a tool that
 * is switched off or has no page is left out, the next one takes its place, at most `tools_shown` cards are shown.
 */
return [
    'tools' => ['qr', 'figure', 'gifts', 'relief', 'organizer', 'pet', 'phone_stand', 'cutter', 'vase', 'repair', 'holder', 'sign', 'lightbox'],
    'tools_shown' => 8,
    // what the farm prints most often (Roman, 13 Oct 2026): first on the page, with a badge saying so
    'popular' => ['qr', 'figure'],
];
