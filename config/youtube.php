<?php

/*
 * Print videos on the Matplace YouTube channel. A finished farm print whose customer agreed gets its time-lapse
 * uploaded as PRIVATE; an admin publishes or rejects it in /admin/youtube. The channel is connected once from that
 * page (OAuth, Google Cloud project `matplace-youtube`, Web client with the redirect URI /admin/youtube/callback).
 */
return [
    'client_id' => env('YOUTUBE_CLIENT_ID'),
    'client_secret' => env('YOUTUBE_CLIENT_SECRET'),

    'channel_url' => env('YOUTUBE_CHANNEL_URL', 'https://www.youtube.com/@Matplace3D'),   // linked from the footer

    'category_id' => env('YOUTUBE_CATEGORY_ID', '28'),   // 28 = Science & Technology
    'language' => env('YOUTUBE_LANGUAGE', 'cs'),          // titles and descriptions are written in the channel's language
    'tags' => ['3D tisk', '3D printing', 'timelapse', 'Matplace'],

    // quota ran out (about 6 uploads a day by default): try again after this many minutes
    'retry_after_minutes' => 360,
];
