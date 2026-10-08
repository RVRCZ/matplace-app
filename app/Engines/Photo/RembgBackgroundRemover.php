<?php

namespace App\Engines\Photo;

use App\Engines\Exceptions\EngineException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * engines/python/photo_cut.py with rembg (u2net, onnxruntime on the CPU; about a second per photo). The model lives
 * in U2NET_HOME (/opt/matplace-py/u2net on the server, config/engines.php `photo_home`): the variable has to reach the
 * script, because www-data has a different HOME in php-fpm than in the worker. Whether rembg is installed at all is
 * asked of the interpreter once an hour.
 */
final class RembgBackgroundRemover implements BackgroundRemover
{
    /** @param  array{bin?: string, timeout?: int, photo_home?: string}  $config  config/engines.php `python` + `photo_home` */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return (bool) Cache::remember('photo_engine:rembg:'.md5((string) ($this->config['bin'] ?? '')), 3600, function () {
            try {
                $r = Process::timeout(60)->env($this->env())->run([$this->config['bin'] ?? 'python3', base_path('engines/python/photo_cut.py'), '--probe']);
                $json = json_decode(trim($r->output()), true);

                return is_array($json) && ! empty($json['ok']);
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function cut(string $source, string $target): array
    {
        $r = Process::timeout((int) ($this->config['timeout'] ?? 120))->env($this->env())
            ->run([$this->config['bin'] ?? 'python3', base_path('engines/python/photo_cut.py'), $source, $target]);
        $json = json_decode(trim($r->output()), true);
        if (! is_array($json)) {
            throw new EngineException('photo_cut.py returned no JSON: '.mb_substr($r->output().$r->errorOutput(), -500));
        }
        if (empty($json['ok'])) {
            throw new EngineException('photo_cut.py: '.(string) ($json['error'] ?? 'failed'));
        }

        return ['width' => (int) $json['width'], 'height' => (int) $json['height'], 'coverage' => (float) $json['coverage']];
    }

    /** @return array<string, string> */
    private function env(): array
    {
        $home = (string) ($this->config['photo_home'] ?? '');

        return $home !== '' ? ['U2NET_HOME' => $home] : [];
    }
}
