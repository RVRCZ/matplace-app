<?php

return [
    'max_mb' => (int) env('UPLOAD_MAX_MB', 100),
    // Unclaimed anonymous files and calculations are deleted after this many days (GDPR retention).
    'anonymous_retention_days' => (int) env('ANON_RETENTION_DAYS', 30),
];
