<?php

namespace App\Engines\Repair;

use App\Engines\Exceptions\EngineException;
use Illuminate\Support\Facades\Process;

/** Thin runner around engines/python/mesh_tool.py (trimesh, optional cadquery). Returns the tool's JSON output. */
final class PythonTool
{
    private ?array $probe = null;

    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return (bool) ($this->probe()['ok'] ?? false);
    }

    /** cadquery/OpenCascade present → STEP/IGES conversion possible. */
    public function hasCad(): bool
    {
        return $this->available() && (bool) ($this->probe()['cad'] ?? false);
    }

    /** @return array<string,mixed> */
    public function run(array $args): array
    {
        $r = Process::timeout($this->config['timeout'])
            ->run(array_merge([$this->config['bin'], base_path('engines/python/mesh_tool.py')], $args));
        $json = json_decode(trim($r->output()), true);
        if (! is_array($json)) {
            throw new EngineException('mesh_tool.py returned no JSON: '.mb_substr($r->output().$r->errorOutput(), -500));
        }

        return $json;
    }

    private function probe(): array
    {
        if ($this->probe === null) {
            try {
                $r = Process::timeout(30)->run([$this->config['bin'], base_path('engines/python/mesh_tool.py'), 'probe']);
                $json = json_decode(trim($r->output()), true);
                $this->probe = ($r->successful() && is_array($json)) ? $json : ['ok' => false];
            } catch (\Throwable) {
                $this->probe = ['ok' => false];
            }
        }

        return $this->probe;
    }
}
