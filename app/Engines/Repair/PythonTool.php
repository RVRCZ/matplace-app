<?php

namespace App\Engines\Repair;

use App\Engines\Exceptions\EngineException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/** Thin runner around engines/python/mesh_tool.py (trimesh, optional cadquery). Returns the tool's JSON output. */
final class PythonTool implements ScriptRunner
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
        $r = Process::timeout($this->config['timeout'])->env(self::switches())
            ->run(array_merge([$this->config['bin'], base_path('engines/python/mesh_tool.py')], $args));
        $json = json_decode(trim($r->output()), true);
        if (! is_array($json)) {
            throw new EngineException('mesh_tool.py returned no JSON: '.mb_substr($r->output().$r->errorOutput(), -500));
        }

        return $json;
    }

    /**
     * Runs another script from engines/python with the same interpreter (e.g. sign_tool.py). One JSON object on stdout.
     *
     * @return array<string,mixed>
     */
    public function runScript(string $script, array $args, int $timeout = 90): array
    {
        $r = Process::timeout($timeout)->env(self::switches())->run(array_merge([$this->config['bin'], base_path('engines/python/'.$script)], $args));
        $json = json_decode(trim($r->output()), true);
        if (! is_array($json)) {
            throw new EngineException($script.' returned no JSON: '.mb_substr($r->output().$r->errorOutput(), -500));
        }

        return $json;
    }

    /**
     * The switches the scripts read from their environment (PAPEL_REMBG=off in PapelPortraitTest), handed over by name:
     * Symfony's Process gives a child only the variables the process started with ($_SERVER), so a putenv() made later,
     * as a test does, would never reach Python and the test would follow whatever this machine has installed.
     *
     * @return array<string, string>
     */
    private static function switches(): array
    {
        $out = [];
        foreach (['PAPEL_REMBG'] as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $out[$name] = (string) $value;
            }
        }

        return $out;
    }

    private function probe(): array
    {
        if ($this->probe === null) {
            // the probe imports the CAD kernel (seconds): ask once an hour, not on every page view
            $cached = Cache::get('python_probe:'.md5((string) $this->config['bin']));
            if (is_array($cached) && ! empty($cached['ok'])) {
                return $this->probe = $cached;
            }
            try {
                $r = Process::timeout(30)->run([$this->config['bin'], base_path('engines/python/mesh_tool.py'), 'probe']);
                $json = json_decode(trim($r->output()), true);
                $this->probe = ($r->successful() && is_array($json)) ? $json : ['ok' => false];
                if (! empty($this->probe['ok'])) {
                    Cache::put('python_probe:'.md5((string) $this->config['bin']), $this->probe, 3600);
                }
            } catch (\Throwable) {
                $this->probe = ['ok' => false];
            }
        }

        return $this->probe;
    }
}
