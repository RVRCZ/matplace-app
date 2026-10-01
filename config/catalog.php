<?php

/*
 * The inspiration catalogue taken over from the old site (/model/{slug}) and the catalogue of designers' models
 * the farm prints (/models).
 */
return [
    // where the old site keeps its thumbnails on this machine (matplace:import-catalog copies them into our storage)
    'legacy_thumbs' => env('LEGACY_THUMBS_DIR', '/var/www/matplace/public/assets/thumbs'),
    // where the old site keeps the pictures of its blog articles (matplace:import-blog copies them into our storage)
    'legacy_blog_images' => env('LEGACY_BLOG_IMAGES_DIR', '/var/www/matplace/storage/blog-images'),
    // pictures that could not be copied are shown from here
    'legacy_assets_url' => env('LEGACY_ASSETS_URL', 'https://legacy.matplace.com'),

    'per_page' => ['models' => 24, 'inspiration' => 48],

    // how many of the most visited inspiration models get an English and a Spanish text, besides every model
    // whose licence lets the farm print it (matplace:translate-catalog)
    'translate_top' => 500,

    // /models filter by the longest side of one piece, in mm: S up to, M up to, L above
    'sizes' => ['s' => 80, 'm' => 160],

    // a designer's reward is capped at this share of the print price of one piece (without delivery)
    'royalty_cap' => 0.30,
];
