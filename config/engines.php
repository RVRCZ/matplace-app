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
    // the background off a photo of a finished print (App\Domain\Farm\PrintPhotos): rembg | fake | none; rembg asks the
    // interpreter once an hour whether it is installed and otherwise the photo is used as it is
    'photo' => env('ENGINE_PHOTO', 'rembg'),
    'photo_home' => env('U2NET_HOME', '/opt/matplace-py/u2net'),   // where rembg finds the u2net model (passed to the script as U2NET_HOME)
    // numba (pymatting inside rembg) compiles into a cache next to its package, which www-data cannot write: a folder of ours instead
    'photo_numba_cache' => env('NUMBA_CACHE_DIR', storage_path('app/numba')),
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
        // a tool changer (a machine profile named "indx") slices with this binary when it is set (Orca 2.5, docs/AM.md §12e),
        // whatever `slicer` below says. Empty = one slicer for everything.
        'bin_toolchanger' => env('ORCA_BIN_TOOLCHANGER') ?: null,
        // Which slicer slices everything but a tool changer – the calculator and every farm printer (docs/AL.md §22): `bin`
        // above is version `bin_version`, `bins` are the other versions installed here. ORCA_SLICER=2.5 moves all of it to
        // Orca 2.5, ORCA_SLICER=2.4 (or no line) back: one line of .env, then `php artisan optimize` and a restart of php-fpm
        // and the queue workers, no deploy. Without its own ORCA_BIN_25 the 2.5 is the tool changer's binary; a version
        // without a binary falls back on `bin` (and says so in the log). The slice cache keys on the binary, so a switch
        // never hands out the other slicer's G-code.
        'slicer' => (string) env('ORCA_SLICER', ''),
        'bin_version' => (string) env('ORCA_BIN_VERSION', '2.4'),
        'bins' => [
            '2.5' => env('ORCA_BIN_25') ?: (env('ORCA_BIN_TOOLCHANGER') ?: null),
        ],
        // the farm orders of an admin are sliced with this version instead (trial prints before the switch); empty = no trial
        'slicer_trial' => env('ORCA_SLICER_TRIAL') ?: null,
        'profiles' => env('ORCA_PROFILES', base_path('engines/orca/profiles')),
        // the CLI's log level (3 = info): what OrcaSlicer::logOf reads the reason of a failure, a path conflict between the
        // copies of a plate and the shared slice of instances from. Orca 2.4 and the 2.5 alpha logged at this level by
        // themselves, the stable 2.5.0 logs nothing without it (docs/AL.md §22). 0 = the slicer's own default
        'debug' => (int) env('ORCA_DEBUG', 3),
        'xvfb' => env('ORCA_XVFB', true),
        'work_dir' => env('ORCA_WORK_DIR', storage_path('app/slicer')),
        'timeout' => (int) env('ORCA_TIMEOUT', 180),
        // a plate of several pieces may take longer: this many seconds more per piece beyond the first, never over timeout_max
        // (PrepareFarmOrder::sliceTimeout, docs/AL.md §11.10); 0 = every plate gets `timeout`. The job's own timeout follows timeout_max
        'timeout_per_piece' => (int) env('ORCA_TIMEOUT_PER_PIECE', 60),
        'timeout_max' => (int) env('ORCA_TIMEOUT_MAX', 1200),
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
    // the colours painted in an uploaded 3MF (App\Domain\Files\PaintedColors, docs/AL.md §14): a colour under `merge_below`
    // per cent of the area joins the colour of its body (the turtle of 28 Oct 2026: eyes 0.8 %, pupils 0.1 %); as many
    // colours print as the farm's biggest changer has spool positions (App\Domain\Farm\ColorLimit::farm, docs/AL.md §19),
    // the smaller ones join theirs too
    'colors' => [
        'merge_below' => (float) env('COLORS_MERGE_BELOW', 1.0),
        'timeout' => 180,
        // what a colour change of a one-plate print costs for the calculator's "in colours from", measured on the turtle of
        // 28 Oct 2026 sliced by Orca 2.4 for the S1 with ACE (docs/AL.md §14.5): 69 changes added 155 min and 2.9 g (the
        // purge goes into the infill and the tower); a little under, so "from" stays a floor
        'change_minutes' => 2.2,
        'flush_mm3' => 30,
        'tower_g' => 0.5,
    ],

    // colouring a model in the browser (App\Domain\Tools\ModelPainter, engines/python/paint_tool.py, docs/AS.md). A model
    // of more triangles than `max_triangles` (on a phone or a tablet: `max_triangles_phone`) is not opened; the brush refines
    // the mesh and may add `max_added` triangles;
    // above `brush_triangles` (a phone: `brush_triangles_phone`) only the fill and the colours by height are offered
    // (measured, docs/AS.md §6). A patch of one colour under `speck_mm2` joins the colour around it: a 0.4 mm nozzle
    // cannot draw it. `edge_mm`: how finely the brush draws its rim (the shortest edge it splits down to).
    'paint' => [
        'max_triangles' => (int) env('PAINT_MAX_TRIANGLES', 1000000),
        'max_triangles_phone' => (int) env('PAINT_MAX_TRIANGLES_PHONE', 500000),
        'max_added' => (int) env('PAINT_MAX_ADDED', 400000),
        'brush_triangles' => (int) env('PAINT_BRUSH_TRIANGLES', 500000),
        'brush_triangles_phone' => (int) env('PAINT_BRUSH_TRIANGLES_PHONE', 200000),
        'speck_mm2' => (float) env('PAINT_SPECK_MM2', 0.5),
        'edge_mm' => 0.4,
        'timeout' => 600,
    ],

    'mesh' => [
        'decimate_above' => (int) env('MESH_DECIMATE_ABOVE', 400000),
        // a file whose last slice on the first page took longer than this is "big" too: the calculator shows its estimate with a
        // range and leaves the slice to the order page (TODO #41, App\Domain\Calculation\BigModel); 0 switches the rule off
        'big_slice_s' => (int) env('MESH_BIG_SLICE_S', 60),
        'decimate_target' => (int) env('MESH_DECIMATE_TARGET', 350000),
        'max_deviation_mm' => (float) env('MESH_DECIMATE_DEVIATION_MM', 0.05),        // the 99th percentile of the surface distance
        'max_deviation_peak_mm' => (float) env('MESH_DECIMATE_PEAK_MM', 0.15),       // the worst sampled point (under a layer)
        'decimate_timeout' => (int) env('MESH_DECIMATE_TIMEOUT', 240),
        // thinned files remembered by their bytes and the limits (30 days): a model calculated, re-sliced or printed again
        // skips the minute of Python; empty switches it off
        'decimate_cache' => env('MESH_DECIMATE_CACHE', storage_path('app/mesh-cache')),
        'max_triangles' => (int) env('MESH_MAX_TRIANGLES', 6000000),
        // "Automatic" supports of a farm order: the model's triangles are asked first whether anything of it can hang in the
        // air (engines/python/supports_tool.py, docs/AL.md §22); when nothing can, the slice runs with supports off. Off by
        // default: measured on 12 Nov 2026, Orca 2.5 loses next to nothing on a model that needs no supports (two meshes of
        // 350 000 triangles: 6.6 s with supports on against 6.2 s off, 20.4 s against 20.0 s) – the time the brief hoped to
        // save goes into building supports a model really needs – while the check itself takes 0.4 to 0.65 s. On = asked
        // for every automatic order of one piece
        'supports_check' => (bool) env('MESH_SUPPORTS_CHECK', false),
        'supports_check_timeout' => (int) env('MESH_SUPPORTS_CHECK_TIMEOUT', 20),
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
