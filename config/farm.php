<?php

/*
 * Public print farm ("Rent a printer"). These are the DEFAULTS: every value under `settings` can be changed in
 * /admin/farm/settings (stored in farm_settings) without a deploy. Printers, materials, colours and slots are rows
 * in the database (seeded by FarmSeeder), never code.
 */
return [
    'enabled' => (bool) env('FARM_ENABLED', true),

    'ffmpeg' => env('FFMPEG_BIN', 'ffmpeg'),   // time-lapse of finished prints; missing = no video, frames stay

    // private disk for print files, G-code and camera snapshots (config/filesystems.php)
    'disk' => 'farm',

    // extra slicer profiles uploaded for new printers/materials; looked up before engines/orca/profiles
    'profiles_dir' => storage_path('app/farm/profiles'),

    'settings' => [
        // ── upload and abuse limits ──────────────────────────────────────────
        'max_upload_mb' => (int) env('FARM_MAX_UPLOAD_MB', 50),
        'daily_slices_per_user' => (int) env('FARM_DAILY_SLICES', 20),
        'min_model_mm' => 5.0,              // largest side below this = "extremely small part"
        'bed_margin_mm' => 2.0,             // kept free on each side of the plate

        // ── customer presets: quality = process profile key + layer, strength = infill ──
        'qualities' => [
            'draft' => ['layer_mm' => 0.28],
            'standard' => ['layer_mm' => 0.20],
            'fine' => ['layer_mm' => 0.12],
        ],
        'strengths' => [
            'low' => ['infill' => 10],
            'standard' => ['infill' => 15],
            'high' => ['infill' => 30],
        ],

        // ── price = h × time_factor × hourly + g × weight_factor × per_gram + fixed (+ VAT) ──
        'currency' => 'CZK',
        'hourly_rate' => 35.0,              // per printing hour, without VAT
        'fixed_fee' => 30.0,                // per order, without VAT
        'min_price' => 99.0,                // per order, without VAT
        'vat_percent' => 21.0,              // 0 = not a VAT payer
        'rounding' => 10.0,                 // final price rounded up to a multiple of this (0 = no rounding); 10 = like the calculator
        'shipping_price' => 99.0,           // with VAT; pickup is free
        'delivery_modes' => ['pickup', 'shipping'],

        // ── credit ───────────────────────────────────────────────────────────
        'topup_amounts' => [200, 500, 1000],
        'topup_min' => 100,
        'topup_max' => 20000,
        'generation_price' => 15.0,         // with VAT; a generation beyond the free daily quota, paid from credit (0 = quota is hard)

        // ── operation ────────────────────────────────────────────────────────
        'require_approval' => false,        // true = every paid order waits for an admin before it may print
        'changeover_minutes' => 10,         // plate swap between two jobs, used for the queue estimate
        'offline_after_seconds' => 120,     // no heartbeat for this long = printer offline
        'snapshot_keep' => 1,               // snapshots kept per order (the last one is what people see)
        'terms_version' => '2026-09',
        'admin_email' => env('FARM_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),

        // ── product switches (the admin's value wins over .env; see App\Providers\AppServiceProvider) ──
        'marketplace' => (bool) env('FEATURE_MARKETPLACE', false),   // printers' marketplace: price lists, inquiries, quotes
        'farm_open' => true,                                          // false: the farm takes no new orders (running ones finish)
        'farm_public' => false,                                       // false: only admins see "rent a printer" (the farm is still being tried out)
    ],

    'payments' => [
        'gateway' => env('FARM_PAYMENT_GATEWAY', 'stripe'),   // stripe | fake
        'stripe' => [
            'secret' => env('STRIPE_SECRET_KEY'),
            'publishable' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],
];
