<?php

namespace App\Engines\Project;

use App\Engines\Contracts\ProjectExporter;
use App\Engines\DTO\SliceParams;
use App\Engines\Exceptions\EngineException;

/** Tests and local development without OrcaSlicer: two printers, a tiny zip that records what was asked for. */
final class FakeProjectExporter implements ProjectExporter
{
    public function name(): string
    {
        return 'fake';
    }

    public function printers(): array
    {
        return [
            ['id' => 'bbl-a1-mini', 'vendor' => 'BBL', 'vendor_label' => 'Bambu Lab', 'model' => 'A1 mini', 'slicer' => 'orca', 'bed' => ['x' => 180, 'y' => 180, 'z' => 180], 'materials' => ['PLA', 'PETG', 'TPU']],
            ['id' => 'prusa-mk4s', 'vendor' => 'Prusa', 'vendor_label' => 'Prusa', 'model' => 'MK4S', 'slicer' => 'prusaslicer', 'bed' => ['x' => 250, 'y' => 210, 'z' => 220], 'materials' => ['PLA', 'PETG', 'ASA']],
        ];
    }

    public function export(string $stlPath, string $printerId, SliceParams $params, array $hints = []): string
    {
        if (! collect($this->printers())->firstWhere('id', $printerId)) {
            throw new EngineException('Unknown printer: '.$printerId);
        }
        $out = sys_get_temp_dir().'/mp_project_'.uniqid().'.3mf';
        $zip = new \ZipArchive;
        $zip->open($out, \ZipArchive::CREATE);
        $zip->addFromString('Metadata/project_settings.config', json_encode(['printer' => $printerId] + OrcaProjectExporter::overrides($params, $hints) + $params->toArray()));
        $zip->close();

        return $out;
    }
}
