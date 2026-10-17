<?php

/**
 * Pictures of the QR code a sign carries, read off the rendered model (the same way tests/Feature/QrWifiTest reads
 * it): for a phone test of the Wi-Fi form without a print. Writes docs/img/qr-wifi/<name>.png.
 *
 *   php scripts/qr_wifi_png.php                       the sample network and the sample address
 *   php scripts/qr_wifi_png.php "My net" "secret" wpa   a network of your own (nothing is stored; the PNG is the only trace)
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Tools\ParametricGenerator;
use Tests\Support\QrRaster;

require __DIR__.'/../tests/Support/QrRaster.php';

$out = __DIR__.'/../docs/img/qr-wifi';
@mkdir($out, 0777, true);
$samples = isset($argv[1])
    ? ['own-network' => ['content' => 'wifi', 'ssid' => $argv[1], 'password' => $argv[2] ?? '', 'security' => $argv[3] ?? 'wpa', 'size' => 90]]
    : [
        'wifi-sample' => ['content' => 'wifi', 'ssid' => 'matplace-test', 'password' => 'Tisk3D-2026', 'security' => 'wpa', 'size' => 90],
        'wifi-hidden-wep' => ['content' => 'wifi', 'ssid' => 'Kavárna "U;Dvou:Koček"', 'password' => 'a\\b;c,d:e"f', 'security' => 'wep', 'hidden' => true, 'size' => 90],
        'web-sample' => ['content' => 'web', 'url' => 'matplace.com/tools/qr', 'size' => 70],
    ];
$tools = app(ParametricGenerator::class);
foreach ($samples as $name => $params) {
    $clean = ParametricGenerator::clean('qr', $params + ['label' => '']);
    $built = $tools->build('qr', $clean);
    $grid = QrRaster::modules($built['path'], (float) $built['meta']['notes']['module_mm'], (int) $built['meta']['notes']['modules']);
    @unlink($built['path']);
    QrRaster::png($grid, $out.'/'.$name.'.png', 10);
    printf("%-16s %s  -> %s\n", $name, ParametricGenerator::qrPayload($clean), $out.'/'.$name.'.png');
}
