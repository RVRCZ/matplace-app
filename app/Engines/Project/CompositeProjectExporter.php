<?php

namespace App\Engines\Project;

use App\Engines\Contracts\ProjectExporter;
use App\Engines\DTO\SliceParams;
use App\Engines\Exceptions\EngineException;

/** Several slicer families behind one list: every printer id belongs to exactly one exporter. */
final class CompositeProjectExporter implements ProjectExporter
{
    /** @param  ProjectExporter[]  $exporters */
    public function __construct(private readonly array $exporters) {}

    public function name(): string
    {
        return implode('+', array_map(fn (ProjectExporter $e) => $e->name(), $this->exporters));
    }

    /** @return ProjectExporter[] */
    public function exporters(): array
    {
        return $this->exporters;
    }

    public function printers(): array
    {
        $all = [];
        foreach ($this->exporters as $e) {
            foreach ($e->printers() as $p) {
                $all[] = $p + ['slicer' => $e->name()];
            }
        }

        return $all;
    }

    public function export(string $stlPath, string $printerId, SliceParams $params, array $hints = []): string
    {
        foreach ($this->exporters as $e) {
            if (collect($e->printers())->contains('id', $printerId)) {
                return $e->export($stlPath, $printerId, $params, $hints);
            }
        }
        throw new EngineException('Unknown printer: '.$printerId);
    }
}
