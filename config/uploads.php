<?php

return [
    'max_mb' => (int) env('UPLOAD_MAX_MB', 120),
    // Files nothing refers to, with an account or without, and their calculations are deleted after this many days
    // (matplace:prune, docs/AL.md §16.5).
    'unordered_retention_days' => (int) env('UNORDERED_RETENTION_DAYS', 30),
];
