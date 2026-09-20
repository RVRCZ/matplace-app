<?php

/*
 * AI providers and daily quotas. Everything for customers is free; expensive calls are limited by count per day.
 */
return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'vision_model' => env('ANTHROPIC_VISION_MODEL', 'claude-haiku-4-5-20251001'),
        'timeout' => 45,
    ],
    'daily_limits' => [
        'describe' => (int) env('AI_LIMIT_DESCRIBE', 20),   // photo identifications per visitor per day
        'generate_guest' => (int) env('AI_LIMIT_GENERATE_GUEST', 1),     // 3D generations per anonymous visitor per day
        'generate_user' => (int) env('AI_LIMIT_GENERATE_USER', 3),       // … per signed-in account per day
        'generate_printer' => (int) env('AI_LIMIT_GENERATE_PRINTER', 15), // … per signed-in printer per day (they prepare models for customers)
        'generate_global' => (int) env('AI_LIMIT_GENERATE_GLOBAL', 100), // hard cap for the whole site per day (cost ceiling)
    ],

    'tripo' => [
        'api_key' => env('TRIPO_API_KEY', ''),
        'base_url' => env('TRIPO_BASE_URL', 'https://openapi.tripo3d.ai'),
        'model' => env('TRIPO_MODEL', 'v3.1-20260211'),
        'face_limit' => (int) env('TRIPO_FACE_LIMIT', 200000),
        'work_dir' => storage_path('app/generated'),
    ],
    'default_target_mm' => 80,
    // photo size guess → volume: box volume × typical fill of a printed part (hollow-ish objects)
    'photo_fill_factor' => 0.35,
];
