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
        'generate' => (int) env('AI_LIMIT_GENERATE', 3),    // 3D generations per visitor per day
    ],
    // photo size guess → volume: box volume × typical fill of a printed part (hollow-ish objects)
    'photo_fill_factor' => 0.35,
];
