<?php

return [
    'consent' => [
        'label' => 'I agree that Matplace may publish the time-lapse video of my print on its YouTube channel. (Optional; you can take it back at any time.)',
    ],

    'order' => [
        'short_download' => 'Download the square video (Instagram, TikTok, Shorts)',
        'title' => 'Video on YouTube',
        'on' => 'You agree that the time-lapse of this print may be published on the Matplace YouTube channel. We check every video before publishing it.',
        'off' => 'We will not publish the video of this print. If you would like it to appear on the Matplace YouTube channel, you can allow it.',
        'agree' => 'Allow publishing',
        'withdraw' => 'Take the consent back',
        'withdraw_confirm' => 'Take the consent back? If the video is already on YouTube, we will delete it.',
        'published' => 'The video is published:',
        'watch' => 'Watch on YouTube',
        'watch_wide' => 'Classic video (16:9)',
        'saved_on' => 'Thank you, the consent is saved.',
        'saved_off' => 'The consent is taken back. The video will not be on YouTube.',
    ],

    'video' => [
        'music' => 'Music: :track',
        'card' => ':material · :time of printing',
        'card_time' => ':time of printing',
        'tail' => ':time of printing in :seconds s',
        'tail_plain' => ':time of 3D printing',
        'title' => '3D print time-lapse – :material :color',
        'cta' => 'Upload a model and see the price right away: :url',
        'cta_tool' => 'Make your own: :url',
        'price' => 'Price: :price',
        'ask_short' => 'What should we print next?',
        'comment' => ':cta This print cost :price.',
        'hashtags' => '#3dtisk #3dtiskarna #dárek',
        'title_plain' => ':name – :time of printing',
        'title_unnamed' => 'What grows in :time of 3D printing',
        'description' => ":cta\nTime-lapse of a print from our 3D print farm; the finished piece and its price are at the end.\n\nMaterial: :material :color\nPrinter: :printer\nPrint time: :time\n\nPublished with the customer's consent.",
    ],

    'hook' => [
        'figure' => 'One photo → a bust', 'pet' => 'A pet photo → a figurine', 'relief' => 'A photo → a relief', 'papel' => 'A photo → a paper portrait',
        'qr' => 'A QR code you can hold', 'box' => 'The customer sent a size → a box', 'organizer' => 'A drawer size → an organizer',
        'vase' => 'A few curves → a vase', 'sign' => 'A name → a sign', 'nameplate' => 'A name → a door plate', 'medallion' => 'A name → a medal on a chain',
        'phone_stand' => 'Three numbers → a phone stand', 'cap' => 'A broken cap → a new one', 'holder' => 'A size → a holder', 'cable_holder' => 'Cables → a holder',
        'ornament' => 'A name → a Christmas ornament', 'gingerbread' => 'A picture → a cookie cutter', 'cookie' => 'A picture → a cookie cutter', 'topper' => 'A name → a cake topper',
        'stamp' => 'A logo → a stamp', 'logo' => 'A logo → 3D', 'text' => 'Lettering → 3D', 'map' => 'An address → a map', 'default' => 'A model → a print in :time',
    ],
    'title' => [
        'figure' => 'A bust from one photo', 'pet' => 'A pet figurine from a photo', 'relief' => 'A relief from a photo', 'papel' => 'A paper portrait from a photo',
        'qr' => 'A QR code you can hold', 'box' => 'A box exactly to size', 'organizer' => 'An organizer exactly for the drawer',
        'vase' => 'A vase without a seam', 'sign' => 'A sign with a name', 'nameplate' => 'A door plate', 'medallion' => 'A medal with a name and a chain',
        'phone_stand' => 'A phone stand made to measure', 'cap' => 'A new cap for a broken one', 'holder' => 'A holder made to measure', 'cable_holder' => 'A cable holder made to measure',
        'ornament' => 'A Christmas ornament with a name', 'gingerbread' => 'A cookie cutter from your own picture', 'cookie' => 'A cookie cutter made to measure', 'topper' => 'A cake topper with a name',
        'stamp' => 'A stamp from a logo', 'logo' => 'A logo in 3D', 'text' => 'Lettering in 3D', 'map' => 'A map of a place in 3D',
    ],
    'hashtag' => [
        'figure' => '#figurka', 'pet' => '#mazlíček', 'relief' => '#reliéf', 'papel' => '#portrét', 'qr' => '#qrkod', 'box' => '#krabička', 'organizer' => '#organizér',
        'vase' => '#váza', 'sign' => '#cedulka', 'nameplate' => '#jmenovka', 'medallion' => '#medaile', 'phone_stand' => '#stojánek', 'cap' => '#oprava', 'holder' => '#držák',
        'cable_holder' => '#držák', 'ornament' => '#vánoce', 'gingerbread' => '#vánoce', 'cookie' => '#vykrajovátko', 'topper' => '#dort', 'stamp' => '#razítko', 'logo' => '#logo',
        'text' => '#nápis', 'map' => '#mapa', 'default' => '#3dprinting',
    ],

    'weekly' => [
        'card' => 'This week on the farm',
        'title' => 'This week on the farm – :dates',
        'description' => ":cta\nWhat our 3D print farm printed this week (:dates): :count prints in time-lapse. Every piece has a chapter with a link to the tool it was made in.\n\nWe publish the videos with our customers' consent.",
    ],
];
