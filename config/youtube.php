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

    // the call with the link as the channel's comment under every public video (pinned by hand in Studio, the API
    // cannot pin); needs the scope youtube.force-ssl = connecting the channel again in /admin/youtube (docs/AK.md §4)
    'pin_comment' => (bool) env('YOUTUBE_PIN_COMMENT', false),

    // background music under every video the farm builds (the customer's, the Short, the 16:9 and the weekly cut):
    // MP3s from the YouTube Audio Library, "Title - Artist.mp3" (the name goes into the description as the credit);
    // one track per order for all its versions, taken in turn by order id; empty folder = silent videos
    'music_dir' => env('YOUTUBE_MUSIC_DIR', storage_path('app/music')),
    'music_volume' => 0.8,
    'music_fade_out' => 1.5,   // seconds at the end; a longer track is cut, never sped up

    // How the videos are cut (App\Domain\YouTube\TimelapseCutter), in seconds. The growing print runs at an even pace:
    // one frame (a layer, or a minute of printing) = `frame_seconds`, stretched or squeezed into the kind's `growth`
    // range; never slower than `slowest_frame` (a short print stays short), every n-th frame taken when one frame
    // would be shorter than a video frame. First and last frame always stay.
    'cut' => [
        'frame_seconds' => 0.1,
        'slowest_frame' => 0.2,
        // the customer's video on the order page: logo, 3 2 1, the print, the finished piece, the photo, thank you
        'customer' => ['growth' => [12, 28], 'hold' => 3.0, 'photo' => 3.0, 'fps' => 24],
        // the vertical Short (9:16, docs/AL.md §13.4): the hook card over the first frame, the print growing 15 to 25 s, the
        // last frame held, the end with the photographed piece (or the print on the plate), the price and the tool's address
        'short' => ['growth' => [15, 25], 'hook' => 1.0, 'hold' => 1.5, 'end' => 3.0, 'fps' => 24, 'width' => 1080, 'height' => 1920],
        // the classic 16:9: title, the print, the piece held, the photo, the card with the tool's address; under 45 s
        'wide' => ['growth' => [20, 28], 'title' => 1.5, 'hold' => 2.0, 'photo' => 3.0, 'end' => 3.0, 'fps' => 30, 'width' => 1920, 'height' => 1080],
        // "Tento týden na farmě": intro, every print of the week with its name, the closing card; 2 to 4 min
        'weekly' => ['growth' => [8.5, 10], 'piece_min' => 10.0, 'intro' => 3.0, 'end' => 4.0, 'max_seconds' => 240, 'min_prints' => 3, 'fps' => 30, 'width' => 1920, 'height' => 1080],
    ],

    // the classic 16:9 of a print is built for the customer and the weekly cut but not uploaded next to its Short (the same
    // time-lapse twice split the views and looked like spam, YouTube audit of 27 Oct 2026); on = upload both as before
    'wide_uploads' => (bool) env('YOUTUBE_WIDE_UPLOADS', false),

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
