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
        // what the order page offers: packeta_point | packeta_home | pickup (in person, free). The farm has no place
        // to hand prints over yet, so pickup is off; the admin ticks it in the farm's settings once there is one.
        // The parcel prices are the table `shipping` below.
        'delivery_modes' => ['packeta_point', 'packeta_home'],

        // ── credit (accounts in crowns; accounts in euros use `topup_eur` below) ──
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

    // Prices are defined in CZK. Accounts outside Czechia pay and are paid in EUR at this fixed rate (CZK per 1 EUR);
    // an amount in euros is rounded up to 0.10 € (App\Support\Money).
    'eur_rate' => (float) env('FARM_EUR_RATE', 25),

    // top-up of an account kept in euros
    'topup_eur' => ['amounts' => [10, 20, 50], 'min' => 5, 'max' => 800],

    /*
     * Parcels (Packeta). Data, not logic: change the numbers here, nothing else has to be touched.
     *
     * Prices are with VAT, per zone and kind (point = pickup point or box, home = to the address), in both
     * currencies: an account in crowns pays the CZK column wherever the parcel goes, an account in euros the EUR one.
     * They come from Packeta's client price list for Matoli s.r.o. valid from 30. 9. 2026 (cost of a parcel up to
     * 1 kg sent from Czechia incl. fuel, toll and the out-of-depot fee, plus a small reserve).
     * null = this kind is not offered in the zone; `only` narrows one country inside its zone.
     */
    'shipping' => [
        'zones' => [
            'CZ' => ['countries' => ['CZ'], 'point' => ['CZK' => 99, 'EUR' => 4.00], 'home' => ['CZK' => 149, 'EUR' => 6.00]],
            'SK' => ['countries' => ['SK'], 'point' => ['CZK' => 149, 'EUR' => 5.90], 'home' => ['CZK' => 175, 'EUR' => 6.90]],
            'EU1' => ['countries' => ['PL', 'HU', 'RO', 'SI', 'HR', 'BG', 'GR', 'LT'], 'point' => ['CZK' => 199, 'EUR' => 7.90], 'home' => ['CZK' => 225, 'EUR' => 8.90]],
            'EU2' => ['countries' => ['DE', 'AT', 'ES', 'PT', 'FR', 'IT', 'LV'], 'point' => ['CZK' => 249, 'EUR' => 9.90], 'home' => ['CZK' => 299, 'EUR' => 11.90]],
            'EU3' => ['countries' => ['NL', 'BE', 'LU', 'IE', 'DK', 'SE', 'FI', 'EE', 'CY'], 'point' => ['CZK' => 399, 'EUR' => 15.90], 'home' => ['CZK' => 575, 'EUR' => 22.90]],
        ],
        // countries where only one kind exists
        'only' => ['AT' => 'home', 'LU' => 'home', 'IE' => 'home', 'CY' => 'point'],
        // weight of the parcel → what is added to the zone price; the last band a zone may use is its limit
        'bands' => [
            ['up_to_g' => 2000, 'CZK' => 0, 'EUR' => 0],
            ['up_to_g' => 5000, 'CZK' => 50, 'EUR' => 2],
            ['up_to_g' => 15000, 'CZK' => 100, 'EUR' => 4, 'zones' => ['CZ']],   // heavier than 5 kg: Czechia only
        ],
        // a piece bigger than this cannot be sent at all (it can only be picked up in person, when the farm offers that)
        'limits' => ['longest_mm' => 700, 'sum_mm' => 1200],
        // the parcel weighs what the print weighs plus the box, rounded up to this step
        'packaging_g' => 60,
        'weight_step_g' => 50,
    ],

    /*
     * VAT of the print by where it is delivered. null = the farm's own rate (settings.vat_percent, Czech VAT).
     * Until the yearly B2C turnover into other EU countries passes 10 000 €, Czech VAT applies everywhere. After
     * that (OSS) put the customer country's rate here: by zone, or by country code, which wins over its zone.
     */
    'vat_rate' => ['CZ' => null, 'SK' => null, 'EU1' => null, 'EU2' => null, 'EU3' => null],

    /*
     * Packeta carriers. Countries of Packeta's own network take the id of the pickup point as the address; anywhere
     * else the parcel goes to a partner carrier: `addressId` is the carrier's id (plus the carrier's code of the
     * point for a pickup point).
     *
     * `packeta_home_carriers` and `packeta_point_carriers`: country → carrier id. What is written here wins. A country
     * that is missing is looked up in the carrier list downloaded by `php artisan matplace:packeta-carriers`
     * (storage/app/packeta_carriers.json), choosing by `packeta_prefer`; `--suggest` prints the map to paste here.
     * A country with no carrier known either way is simply not offered.
     */
    'packeta_carriers_file' => env('PACKETA_CARRIERS_FILE', storage_path('app/packeta_carriers.json')),
    'packeta_internal' => ['CZ', 'SK', 'HU', 'RO', 'PL'],
    'packeta_home_carriers' => ['CZ' => 106, 'SK' => 131, 'DE' => 111, 'AT' => 80],
    'packeta_point_carriers' => [],
    // the cheapest partner of the price list per country: the first carrier whose name holds one of the words wins
    'packeta_prefer' => [
        'ES' => ['point' => ['MRW'], 'home' => ['Correos']],
        'DE' => ['point' => ['Hermes'], 'home' => ['Hermes']],
        'FR' => ['point' => ['Mondial Relay'], 'home' => ['Colis Priv']],
        'IT' => ['point' => ['Punto Poste'], 'home' => ['HR Parcel']],
        'PL' => ['home' => ['DPD']],
        'HU' => ['home' => ['Packeta', 'Home']],
        'RO' => ['home' => ['Packeta', 'Home']],
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
