<?php

return [
    /*
     * Route names (without the language prefix "l.") that search engines must not keep: private pages and
     * one-off links. They still exist in every language, they just carry "noindex" and no hreflang.
     */
    'noindex' => [
        'calc.share',
        'quote.*',
        'inquiry.*',
        'login', 'register', 'password.*',
        'account', 'account.*',
        'farm.start', 'farm.orders', 'farm.orders.*',
        'printer.*',
        'admin.*',
    ],
];
