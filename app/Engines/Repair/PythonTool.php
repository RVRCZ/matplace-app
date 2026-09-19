<?php

namespace App\Engines\Repair;

use App\Engines\Exceptions\EngineException;
use Illuminate\Support\Facades\Process;

/** Thin runner around engines/python/mesh_tool.py (trimesh). Returns the tool's JSON output. */
final class PythonTool
{
    private ?bool $available = null;

    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        if ($this->available === null) {
            $r = Process::timeout(20)->run([$this->config['bin'], base_path('engines/python/mesh_tool.py'), 'probe']);
            $json = json_decode(trim($r->output()), true);
            $this->available = $r->successful() && ! empty($json['ok']);
        }

        return $this->available;
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
}
