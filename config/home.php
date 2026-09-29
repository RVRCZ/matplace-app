<?php

/*
 * The homepage. `tools` are the cards under the first screen, in this order: what people make most often with
 * a 3D printer (a name as a gift, a photo as a picture, order in a drawer…). Keys of config/tools.php; a tool that
 * is switched off or has no page is left out, the next one takes its place, at most `tools_shown` cards are shown.
 */
return [
    'tools' => ['gifts', 'relief', 'organizer', 'phone_stand', 'cutter', 'vase', 'box', 'repair', 'holder', 'sign', 'figure', 'lightbox'],
    'tools_shown' => 8,
];
