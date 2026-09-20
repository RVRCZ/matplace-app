<?php

/*
 * Swappable engines. Change the implementation here; the rest of the application only sees the contracts.
 */
return [
    'slicer' => env('ENGINE_SLICER', 'orca'),        // orca | fake
    'project_exporter' => env('ENGINE_PROJECT_EXPORTER', env('ENGINE_SLICER', 'orca')), // orca | fake
    'repair' => env('ENGINE_REPAIR', 'trimesh'),     // trimesh | null
    'generator' => env('ENGINE_GENERATOR', 'null'),  // null | tripo | meshy (later)
    'settlement' => env('ENGINE_SETTLEMENT', 'qr-manual'),
    'search' => ['local', 'printables', 'makerworld'],       // ModelSearch sources, merged in this order
    'converters' => ['threemf', 'trimesh', 'ocp', 'freecad'], // tried in order; STL needs no conversion

    'orca' => [
        'bin' => env('ORCA_BIN', '/opt/orca/squashfs-root/AppRun'),
        'profiles' => env('ORCA_PROFILES', base_path('engines/orca/profiles')),
        'xvfb' => env('ORCA_XVFB', true),
        'work_dir' => env('ORCA_WORK_DIR', storage_path('app/slicer')),
        'timeout' => (int) env('ORCA_TIMEOUT', 180),
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

    'python' => [
        'bin' => env('PYTHON_BIN', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
        'timeout' => (int) env('PYTHON_TIMEOUT', 120),
    ],

    'freecad' => [
        'bin' => env('FREECAD_BIN', 'freecadcmd'),
        'timeout' => (int) env('FREECAD_TIMEOUT', 300),
    ],
];
