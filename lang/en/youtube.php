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
        'description' => ":cta\nTime-lapse of a print from our 3D print farm.\n\nMaterial: :material :color\nPrinter: :printer\nPrint time: :time\n\nPublished with the customer's consent.",
    ],

    'weekly' => [
        'card' => 'This week on the farm',
        'title' => 'This week on the farm – :dates',
        'description' => ":cta\nWhat our 3D print farm printed this week (:dates): :count prints in time-lapse. Every piece has a chapter with a link to the tool it was made in.\n\nWe publish the videos with our customers' consent.",
    ],
];
