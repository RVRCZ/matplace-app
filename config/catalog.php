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

    /*
     * Which inspiration pages search engines may index (CatalogModel::scopeIndexable); the rest answer with
     * "noindex, follow" and stay out of the sitemaps, visitors see every page as before (Roman, 18 Oct 2026). A page
     * must have everything listed here: `enriched` = a text of our own (catalog:enrich, enriched_at), `price` = a real
     * price from a calculation (the author's printable card, or the slicer's grams and minutes taken over from the old
     * site, with a kind loaded on the farm), `file` = we hold the file (file_available, model_file_id, or the author's
     * card). Take `price` and `file` out to index every enriched model.
     */
    'index_requires' => array_values(array_filter(explode(',', (string) env('CATALOG_INDEX_REQUIRES', 'enriched,price,file')))),

    // the materials an inspiration page prices one piece in, from the slice of the model's own file (catalog:slice, docs/AN.md §13)
    'value_materials' => ['PLA', 'PETG', 'ASA'],

    // catalog:enrich – how many of the most visited models by default, and how much of the source's text Gemini reads
    'enrich' => ['top' => 300, 'max_source_chars' => 3000],

    // the designers' catalogue (/models) is in the menu, named by the inspiration catalogue, indexed and in the sitemap
    // only from this many printable cards (DesignerModel::enoughToShow): a link to an empty page is a blind alley
    'menu_from' => 6,

    // /models filter by the longest side of one piece, in mm: S up to, M up to, L above
    'sizes' => ['s' => 80, 'm' => 160],

    // a designer's reward is capped at this share of the print price of one piece (without delivery)
    'royalty_cap' => 0.30,
];
