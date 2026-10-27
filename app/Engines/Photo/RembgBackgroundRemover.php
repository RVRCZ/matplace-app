<?php

namespace App\Engines\Photo;

use App\Engines\Exceptions\EngineException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * engines/python/photo_cut.py with rembg (u2net, onnxruntime on the CPU; about a second per photo). The model lives
 * in U2NET_HOME (/opt/matplace-py/u2net on the server, config/engines.php `photo_home`): the variable has to reach the
 * script, because www-data has a different HOME in php-fpm than in the worker. rembg's pymatting compiles with numba,
 * which wants a writable cache next to the package: www-data has none there, so NUMBA_CACHE_DIR points into storage
 * (`photo_numba_cache`). Whether rembg is installed and the model is there is asked of the interpreter once an hour;
 * without the model nothing is cut (the photo is used as it is) rather than every cut failing or downloading.
 */
final class RembgBackgroundRemover implements BackgroundRemover
{
    /** @param  array{bin?: string, timeout?: int, photo_home?: string, photo_numba_cache?: string}  $config  config/engines.php `python` + `photo_home` + `photo_numba_cache` */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        $probe = $this->probe();

        return ! empty($probe['ok']) && ! empty($probe['model_present']);
    }

    /**
     * What the interpreter says (remembered for an hour): rembg installed (`ok`, `rembg` version), the model in its
     * place (`model_present`, `home`), or the error. `farm:photo --probe` prints it.
     *
     * @return array{ok: bool, rembg?: string, model_present?: bool, home?: string, error?: string}
     */
    public function probe(): array
    {
        return (array) Cache::remember('photo_engine:rembg:'.md5((string) ($this->config['bin'] ?? '').'|'.(string) ($this->config['photo_home'] ?? '')), 3600, function () {
            try {
                $r = Process::timeout(60)->env($this->env())->run([$this->config['bin'] ?? 'python3', base_path('engines/python/photo_cut.py'), '--probe']);
                $json = json_decode(trim($r->output()), true);

                return is_array($json) ? $json : ['ok' => false, 'error' => mb_substr($r->output().$r->errorOutput(), -300)];
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300)];
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

    /** @return array<string, string> the model's home and numba's cache (made when missing) for the script */
    private function env(): array
    {
        $env = [];
        if (($home = (string) ($this->config['photo_home'] ?? '')) !== '') {
            $env['U2NET_HOME'] = $home;
        }
        if (($cache = (string) ($this->config['photo_numba_cache'] ?? '')) !== '') {
            if (! is_dir($cache)) {
                @mkdir($cache, 0775, true);
            }
            $env['NUMBA_CACHE_DIR'] = $cache;
        }

        return $env;
    }
}
