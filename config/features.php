<?php

/*
 * Switches for whole parts of the product. Off = routes, buttons and prices of that part are hidden; the code and the
 * data stay, so it can come back without a rewrite.
 */
return [
    // Marketplace: price profiles and printers' price lists in the calculator, "make it for me" inquiries, quotes,
    // public printer pages, the printer role. Phase 1 of the public print farm runs without it: the calculator
    // shows the slicer's facts (size, time, material) and the only price is the farm's.
    'marketplace' => (bool) env('FEATURE_MARKETPLACE', false),
];
