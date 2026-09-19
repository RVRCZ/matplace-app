<?php

/*
 * Rough estimate constants (shared with the browser via /api/config — keep in sync with resources/js/calc/rough.ts)
 * and orientation pricing profiles shown before real printer profiles exist (step 2 replaces them).
 */
return [
    'rough' => [
        'perimeters' => 2,
        'line_width_mm' => 0.4,
        'shell_fraction_fallback' => 0.25,   // when surface area unknown
        'support_factor' => 1.12,
        'minutes_per_gram' => 5.5,           // 0.20 mm, ~50 mm/s reference
        'overhead_minutes' => 10,
        'quality_time_factor' => ['draft' => 0.75, 'standard' => 1.0, 'fine' => 1.6],
        'quality_layer_mm' => ['draft' => 0.28, 'standard' => 0.20, 'fine' => 0.12],
        'range_low' => 0.85,                 // rough price range = estimate × [low, high]
        'range_high' => 1.25,
    ],

    // Platform orientation profiles (Kč). Replaced by real printer pricing profiles from step 2.
    'orientation_profiles' => [
        ['key' => 'budget', 'hourly_rate' => 50, 'price_per_gram' => 2.0, 'setup_fee' => 30, 'margin_pct' => 0, 'min_price' => 79, 'lead_time_days' => 7],
        ['key' => 'standard', 'hourly_rate' => 70, 'price_per_gram' => 3.0, 'setup_fee' => 40, 'margin_pct' => 0, 'min_price' => 99, 'lead_time_days' => 4],
        ['key' => 'express', 'hourly_rate' => 100, 'price_per_gram' => 5.0, 'setup_fee' => 60, 'margin_pct' => 0, 'min_price' => 149, 'lead_time_days' => 2],
    ],

    'round_to' => 10,        // Kč, ceil
    'currency' => 'CZK',
    'max_scale' => 4.0,
    'bed_mm' => ['x' => 250, 'y' => 250, 'z' => 250], // typical desktop printer, used for "fits" warning before printers exist
];
