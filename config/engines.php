<?php

/*
 * Swappable engines. Change the implementation here; the rest of the application only sees the contracts.
 */
return [
    'slicer' => env('ENGINE_SLICER', 'orca'),        // orca | fake
    'project_exporter' => env('ENGINE_PROJECT_EXPORTER', env('ENGINE_SLICER', 'orca')), // orca | fake
    'repair' => env('ENGINE_REPAIR', 'trimesh'),     // trimesh | null
    'generator' => env('ENGINE_GENERATOR', 'null'),  // null | tripo | meshy (written from its documentation, not tried yet: docs/Y.md §11) | fake
    'settlement' => env('ENGINE_SETTLEMENT', 'qr-manual'),
    'translator' => env('ENGINE_TRANSLATOR', 'claude'),   // claude | fake
    'shipping' => env('ENGINE_SHIPPING', 'packeta'),      // packeta | fake
    'assistant' => env('ENGINE_ASSISTANT', 'claude'),     // claude | fake
    'social' => env('ENGINE_SOCIAL', 'meta'),             // meta | fake
    'mailbox' => env('ENGINE_MAILBOX', 'gmail'),          // gmail | fake — the shared inbox the admin answers from
    // where the admin looks for models to add to the inspiration catalogue (the home page searches `search` below)
    'admin_search' => ['printables', 'makerworld', 'makeronline'],

    // Portfolio import (Printables, MakerWorld): metadata and pictures only, never files.
    'import' => [
        'driver' => env('ENGINE_IMPORT', 'live'),         // live | fake
        // api.printables.com and makerworld.com refuse data-centre addresses (Cloudflare, 403 from the Hetzner server):
        // the import AND the search send their calls through this proxy (pictures are fetched directly, those hosts allow it)
        'proxy' => env('IMPORT_HTTP_PROXY'),
        'timeout' => 20,
        // pictures of imported cards are fetched only from these hosts (and their subdomains)
        'image_hosts' => ['printables.com', 'bblmw.com', 'bambulab.com', 'makerworld.com'],
    ],
    'search' => ['local', 'printables', 'makerworld'],       // ModelSearch sources, merged in this order
    'converters' => ['threemf', 'trimesh', 'ocp', 'freecad'], // tried in order; STL needs no conversion

    'orca' => [
        'bin' => env('ORCA_BIN', '/opt/orca/squashfs-root/AppRun'),
        'profiles' => env('ORCA_PROFILES', base_path('engines/orca/profiles')),
        'xvfb' => env('ORCA_XVFB', true),
        'work_dir' => env('ORCA_WORK_DIR', storage_path('app/slicer')),
        'timeout' => (int) env('ORCA_TIMEOUT', 180),
        // the same mesh + parameters + profiles + slicer = the same G-code: kept in work_dir/cache (App\Engines\Slicer\CachedSlicer)
        'cache' => (bool) env('ORCA_CACHE', true),
        'cache_mb' => (int) env('ORCA_CACHE_MB', 3000),
        // at most this many slicers at once (0 = no limit): see docs/M.md before changing
        'parallel' => (int) env('ORCA_PARALLEL', 0),
        // printer catalogue for 3MF projects: vendor presets shipped with OrcaSlicer
        'vendor_profiles' => env('ORCA_VENDOR_PROFILES', '/opt/orca/squashfs-root/resources/profiles'),
        'catalog' => storage_path('app/printer_catalog.json'),
        // material code → filament profile file; quality → process profile file
        'machine' => 'machine.json',
        'filaments' => [
            'PLA' => 'filament_pla.json',
            'PETG' => 'filament_petg.json',
            'ASA' => 'filament_asa.json',
            'TPU' => 'filament_tpu.json',
        ],
        'processes' => [
            'draft' => 'process_draft.json',
            'standard' => 'process_standard.json',
            'fine' => 'process_fine.json',
        ],
    ],

    // PrusaSlicer projects: Prusa's public vendor bundle, refreshed by `php artisan matplace:printer-catalog`
    'prusa' => [
        'repository' => env('PRUSA_PROFILES_URL', 'https://raw.githubusercontent.com/prusa3d/PrusaSlicer-settings-prusa-fff/main/PrusaResearch'),
        'bundle' => storage_path('app/prusa/PrusaResearch.ini'),
        'catalog' => storage_path('app/prusa_catalog.json'),
        'work_dir' => env('ORCA_WORK_DIR', storage_path('app/slicer')),
    ],

    // Very fine meshes (a scanned dragon, 1.8 M triangles, 22 Oct 2026) keep the slicer busy for minutes: above `above`
    // the mesh is thinned to about `target` triangles before it is sliced (quadric decimation, engines/python/decimate_tool.py),
    // only while the worst deviation stays within `max_deviation_mm` and the mesh stays watertight; the original is what
    // the customer sees and downloads. Above `max_triangles` a file is refused on upload with the advice to thin it at home.
    'mesh' => [
        'decimate_above' => (int) env('MESH_DECIMATE_ABOVE', 400000),
        'decimate_target' => (int) env('MESH_DECIMATE_TARGET', 350000),
        'max_deviation_mm' => (float) env('MESH_DECIMATE_DEVIATION_MM', 0.05),        // the 99th percentile of the surface distance
        'max_deviation_peak_mm' => (float) env('MESH_DECIMATE_PEAK_MM', 0.15),       // the worst sampled point (under a layer)
        'decimate_timeout' => (int) env('MESH_DECIMATE_TIMEOUT', 240),
        // thinned files remembered by their bytes and the limits (30 days): a model calculated, re-sliced or printed again
        // skips the minute of Python; empty switches it off
        'decimate_cache' => env('MESH_DECIMATE_CACHE', storage_path('app/mesh-cache')),
        'max_triangles' => (int) env('MESH_MAX_TRIANGLES', 6000000),
        // the check after an upload (mesh_tool.py check): a binary STL of at least this many triangles is measured with
        // numpy alone instead of trimesh (23 s of a 2.7 M upload's wait on the server, 24 Oct 2026); 0 = always trimesh
        'fast_check_above' => (int) env('MESH_FAST_CHECK_ABOVE', 400000),
    ],

    'python' => [
        'bin' => env('PYTHON_BIN', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
        'timeout' => (int) env('PYTHON_TIMEOUT', 240),
    ],

    'freecad' => [
        'bin' => env('FREECAD_BIN', 'freecadcmd'),
        'timeout' => (int) env('FREECAD_TIMEOUT', 300),
    ],
];
