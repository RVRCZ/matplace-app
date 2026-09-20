<?php

return [
    'max_printers' => (int) env('INQUIRY_MAX_PRINTERS', 6),   // how many printers receive one inquiry
    'expire_days' => (int) env('INQUIRY_EXPIRE_DAYS', 14),    // open inquiries without acceptance expire after
    'chat_attachment_max_mb' => 20,
];
