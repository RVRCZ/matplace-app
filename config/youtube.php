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

    // background music for the YouTube version (the customer's download stays silent): MP3s from the YouTube Audio
    // Library, "Title - Artist.mp3" (the name goes into the description as the credit); one per video, taken in
    // turn by order id; empty folder = silent videos
    'music_dir' => env('YOUTUBE_MUSIC_DIR', storage_path('app/music')),
    'music_volume' => 0.8,

    // quota ran out (about 6 uploads a day by default): try again after this many minutes
    'retry_after_minutes' => 360,

    // When an approved video goes public. Shorts live or die in their first hours, so they go out when the Czech
    // audience is on the phone (early evening), and one a day: several on one day compete with each other and the
    // channel looks like spam; a steady one-a-day beats a burst. The approval takes the next free slot; YouTube
    // flips the video public at that time (status.publishAt), the admin can still say "now".
    'publish_times' => array_values(array_filter(array_map('trim', explode(',', (string) env('YOUTUBE_PUBLISH_TIMES', '18:00'))))),
    'publish_timezone' => 'Europe/Prague',
    'max_per_day' => (int) env('YOUTUBE_MAX_PER_DAY', 1),
];
