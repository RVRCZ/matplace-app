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
        // Calibrated on Anycubic Kobra S1 slices: 20 mm cube 3.6 g → 14 min, phone stand 22.9 g → 41 min.
        'minutes_per_gram' => 1.4,           // 0.20 mm, modern fast FDM printer
        'overhead_minutes' => 8,
        'quality_time_factor' => ['draft' => 0.75, 'standard' => 1.0, 'fine' => 1.6],
        'quality_layer_mm' => ['draft' => 0.28, 'standard' => 0.20, 'fine' => 0.12],
        // How far the slice may land from the estimate, by the shape of the model (TODO #41, docs/M.md §6; App\Domain\Calculation\ShapeRange
        // and resources/js/calc/rough.ts rangeFactors). Calibrated on 400 calculations against their slices (docs/data/odhad-vs-rez-2026-10-24.csv):
        // the price lands inside 97 % of the time (82 % with the flat 0.85–1.25 before), the print time 96 %; `php artisan matplace:estimate-range`
        // recounts the hits from such a CSV.
        'range' => [
            'height_mm' => [15, 100],         // the height term grows from nothing at 15 mm to full at 100 mm (many layers)
            'fill_g_cm3' => [0.04, 0.2],      // the thinness term is full under 0.04 g per cm³ of the model's box, nothing above 0.2 (thin walls, lattices, long thin parts)
            'small_minutes' => [15, 30],      // the lower bound drops for prints estimated under 30 min (the fixed minutes of the estimate are too many for them)
            'overhang_share' => [0.05, 0.3],  // supports left to the slicer: the supports term rises from `auto` to `supports` as the overhanging share of the surface grows
            // high = the upper bound of a flat, compact print without supports; height, thin = what the terms add at full; supports, auto = by the
            // supports choice (off = nothing); low = the lower bound; small = what a tiny print loses of it
            'time' => ['high' => 1.25, 'height' => 0.2, 'thin' => 1.2, 'supports' => 1.2, 'auto' => 1.2, 'low' => 0.78, 'small' => 0.5],
            'price' => ['high' => 1.05, 'height' => 0.1, 'thin' => 0.2, 'supports' => 1.4, 'auto' => 1.15, 'low' => 0.7, 'small' => 0.3],
        ],
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
