<?php

/*
 * Selling and planning (session 4, docs/S.md): the figures the cost, profit, plan and vendors pages start from.
 * Every fee carries the date it was read and where (`as_of`, `source`); Roman checks them before a deploy and
 * the pages say "rates as of <date>". Amounts are in the currency named next to them; the pages convert with the
 * rates below (crowns per unit, with their own date).
 */
return [
    // crowns per euro and per dollar for the fees quoted in those currencies (the farm's euro rate is config/farm.php)
    'rates' => ['EUR' => 25.0, 'USD' => 21.5, 'as_of' => '2026-10-08', 'source' => 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/'],

    // what printing a piece costs at home: the starting values of /tools/cost (crowns)
    'cost' => [
        'filament_kg' => 600,        // Kč per kilogram of PLA
        'grams' => 40,
        'hours' => 2.5,
        'watts' => 120,              // what a desk printer draws while printing, averaged
        'kwh' => 6.5,                // Kč per kWh, a household tariff in 2026
        'printer_price' => 12000,    // Kč
        'printer_hours' => 5000,     // hours a printer prints before it is written off
        'scrap_pct' => 5,            // failed prints, as a share of all
        'labour_rate' => 250,        // Kč per hour of the maker's time
        'labour_minutes' => 10,      // slicing, cleaning the plate, taking the piece off, packing
        'other' => 0,                // packaging, labels… per piece
        'margin_pct' => 40,
        'as_of' => '2026-10-08',
        'source' => 'https://www.cenyenergie.cz/elektrina/ (kWh), https://www.prusa3d.com/ (filament)',
    ],

    // where a piece is sold and what the platform keeps: /tools/profit
    // listing: per listing (Etsy renews it every four months or on a sale); transaction: of price + shipping charged;
    // payment: of the amount paid, plus a fixed part; currency: when the listing currency is not the payout one;
    // monthly: a plan or a shop, spread over the pieces sold in a month; stall: a market day, spread the same way
    'platforms' => [
        'etsy' => ['listing' => ['amount' => 0.20, 'currency' => 'USD'], 'transaction_pct' => 6.5, 'payment_pct' => 4.0, 'payment_fixed' => ['amount' => 10, 'currency' => 'CZK'], 'currency_pct' => 2.5, 'monthly' => null, 'stall' => false,
            'as_of' => '2026-10-08', 'source' => 'https://www.etsy.com/legal/fees', 'note' => 'Offsite Ads (12–15 % of a sale they brought) are not counted; the regulatory operating fee does not apply to the Czech Republic.'],
        'fler' => ['listing' => null, 'transaction_pct' => 11.0, 'payment_pct' => 0.0, 'payment_fixed' => null, 'currency_pct' => 0.0, 'monthly' => null, 'stall' => false,
            'as_of' => '2026-10-08', 'source' => 'https://www.fler.cz/napoveda/poplatky', 'note' => 'The commission is taken from the price of the goods; shipping is passed on.'],
        'shopify' => ['listing' => null, 'transaction_pct' => 2.0, 'payment_pct' => 1.4, 'payment_fixed' => ['amount' => 3, 'currency' => 'CZK'], 'currency_pct' => 0.0, 'monthly' => ['amount' => 39, 'currency' => 'USD'], 'stall' => false,
            'as_of' => '2026-10-08', 'source' => 'https://www.shopify.com/pricing, https://www.comgate.cz/cenik', 'note' => 'Basic plan paid monthly; Shopify Payments is not offered in the Czech Republic, so a gateway (Comgate) plus Shopify\'s 2 % third-party fee.'],
        'own' => ['listing' => null, 'transaction_pct' => 0.0, 'payment_pct' => 1.3, 'payment_fixed' => ['amount' => 3, 'currency' => 'CZK'], 'currency_pct' => 0.0, 'monthly' => ['amount' => 390, 'currency' => 'CZK'], 'stall' => false,
            'as_of' => '2026-10-08', 'source' => 'https://www.shoptet.cz/cenik/, https://www.comgate.cz/cenik', 'note' => 'A rented shop (Shoptet Základní) and a payment gateway; a shop of your own on a server costs what the server costs.'],
        'fair' => ['listing' => null, 'transaction_pct' => 0.0, 'payment_pct' => 1.0, 'payment_fixed' => null, 'currency_pct' => 0.0, 'monthly' => null, 'stall' => true,
            'as_of' => '2026-10-08', 'source' => 'stall fees as the events list them (/tools/vendors)', 'note' => 'A market or a fair: the stall fee spread over the pieces sold that day, a card terminal at about 1 %.'],
        'matplace' => ['listing' => null, 'transaction_pct' => 0.0, 'payment_pct' => 0.0, 'payment_fixed' => null, 'currency_pct' => 0.0, 'monthly' => null, 'stall' => false,
            'as_of' => '2026-10-08', 'source' => 'https://matplace.com/', 'note' => 'We print and ship: your cost is our price from the calculator, nothing else is taken.'],
    ],

    // the standing list of channels on /tools/vendors: where handmade and printed things are sold, with what they keep
    'channels' => [
        ['key' => 'fler', 'url' => 'https://www.fler.cz/', 'fee' => '11 %', 'as_of' => '2026-10-08', 'source' => 'https://www.fler.cz/napoveda/poplatky'],
        ['key' => 'etsy', 'url' => 'https://www.etsy.com/', 'fee' => '0,20 USD + 6,5 % + 4 % + 10 Kč', 'as_of' => '2026-10-08', 'source' => 'https://www.etsy.com/legal/fees'],
        ['key' => 'amazon_handmade', 'url' => 'https://sell.amazon.com/programs/handmade', 'fee' => '15 %', 'as_of' => '2026-10-08', 'source' => 'https://sell.amazon.com/programs/handmade'],
        ['key' => 'vinted', 'url' => 'https://www.vinted.cz/', 'fee' => '0 % (the buyer pays protection)', 'as_of' => '2026-10-08', 'source' => 'https://www.vinted.cz/help'],
        ['key' => 'aukro', 'url' => 'https://aukro.cz/', 'fee' => '7–9 %', 'as_of' => '2026-10-08', 'source' => 'https://aukro.cz/poplatky'],
        ['key' => 'consignment', 'url' => null, 'fee' => '20–40 %', 'as_of' => '2026-10-08', 'source' => 'usual terms of design shops and galleries'],
        ['key' => 'matplace', 'url' => 'https://matplace.com/', 'fee' => '0 %', 'as_of' => '2026-10-08', 'source' => 'https://matplace.com/'],
    ],

    // the plan: seasonality presets (a multiplier per month, January first)
    'seasons' => [
        'flat' => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
        'christmas' => [0.6, 0.6, 0.8, 0.9, 1.0, 0.8, 0.7, 0.8, 1.0, 1.4, 2.0, 2.4],
        'summer' => [0.5, 0.6, 0.9, 1.2, 1.5, 1.6, 1.4, 1.5, 1.3, 0.9, 0.9, 0.9],
        'school' => [0.8, 0.8, 0.9, 0.9, 1.0, 1.2, 0.9, 1.8, 1.6, 0.9, 0.8, 0.8],
    ],

    // the VAT that comes out of a price when the seller is registered (Czech standard rate)
    'vat_pct' => 21,
];
