<?php

namespace App\Domain\Ops;

use App\Domain\Mail\MailboxWatch;
use App\Engines\Mail\Mailbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the server is asked every ten minutes (docs/AU.md): disk space, the models' disk, jobs that failed, a queue that
 * stands, errors in the log, the age of the last backup and whether the inbox watch runs. Each answer is ok or not,
 * with one line a person understands. Tests set `$fake` instead of asking the machine.
 */
final class HealthChecks
{
    public const LABELS = [
        'disk' => 'Disk', 'models' => 'Modely (disk Hetzner)', 'failed_jobs' => 'Spadlé úlohy', 'queue' => 'Fronta úloh',
        'log' => 'Chyby v logu', 'backup' => 'Záloha', 'mailbox' => 'Hlídač schránky',
    ];

    /** @var list<array{key: string, ok: bool, message: string}>|null */
    public static ?array $fake = null;

    public function __construct(private readonly Mailbox $mailbox) {}

    /** @return list<array{key: string, ok: bool, message: string}> */
    public function run(): array
    {
        if (self::$fake !== null) {
            return self::$fake;
        }
        $out = [$this->disk(), $this->models(), $this->failedJobs(), $this->queue(), $this->log()];
        if ((string) config('watch.backup_marker') !== '') {
            $out[] = $this->backup();
        }
        if ($this->mailbox->available()) {
            $out[] = $this->mailbox();
        }

        return $out;
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function disk(): array
    {
        $warn = (int) config('watch.disk_warn_percent', 85);
        $critical = (int) config('watch.disk_critical_percent', 93);
        $parts = [];
        $seen = [];
        $worst = 0;
        foreach (['systém' => base_path(), 'modely' => storage_path('app/models')] as $name => $path) {
            if (! is_dir($path)) {
                continue;
            }
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if (! $total || $free === false || isset($seen[$total.'/'.$free])) {
                continue;   // unreadable, or the same disk as the one before (no separate volume)
            }
            $seen[$total.'/'.$free] = true;
            $used = (int) round(100 - $free / $total * 100);
            $worst = max($worst, $used);
            $parts[] = sprintf('%s %d %% (%s volných)', $name, $used, self::size($free));
        }
        $message = implode(', ', $parts) ?: 'disk nelze přečíst';

        return ['key' => 'disk', 'ok' => $parts !== [] && $worst < $warn, 'message' => $worst >= $critical ? 'KRITICKÉ: '.$message : $message];
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function models(): array
    {
        $dir = storage_path('app/models');
        if (! is_dir($dir)) {
            return ['key' => 'models', 'ok' => false, 'message' => 'složka modelů chybí ('.$dir.')'];
        }
        $n = 0;
        foreach (new \DirectoryIterator($dir) as $f) {
            if (! $f->isDot() && ++$n > 2) {
                break;
            }
        }

        return ['key' => 'models', 'ok' => $n > 0, 'message' => $n > 0 ? 'připojeno, soubory na místě' : 'složka modelů je prázdná – disk Hetzneru není připojený?'];
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['key' => 'failed_jobs', 'ok' => true, 'message' => 'bez tabulky'];
        }
        $minutes = (int) config('watch.failed_jobs_window_minutes', 60);
        $since = now()->subMinutes($minutes);
        $n = DB::table('failed_jobs')->where('failed_at', '>=', $since)->count();
        if ($n === 0) {
            return ['key' => 'failed_jobs', 'ok' => true, 'message' => 'žádná za posledních '.$minutes.' min'];
        }
        $names = DB::table('failed_jobs')->where('failed_at', '>=', $since)->orderByDesc('id')->limit(5)->get(['payload', 'exception'])
            ->map(fn ($r) => class_basename((string) (json_decode((string) $r->payload)->displayName ?? '?')).': '.mb_substr(preg_replace('/\s+/', ' ', (string) $r->exception) ?? '', 0, 90))->all();

        return ['key' => 'failed_jobs', 'ok' => false, 'message' => $n.' za posledních '.$minutes.' min – '.implode(' | ', $names)];
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function queue(): array
    {
        if (! Schema::hasTable('jobs')) {
            return ['key' => 'queue', 'ok' => true, 'message' => 'bez tabulky'];
        }
        $max = (int) config('watch.queue_max_minutes', 15);
        $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        $waiting = DB::table('jobs')->count();
        if (! $oldest || (int) $oldest > now()->getTimestamp() - $max * 60) {
            return ['key' => 'queue', 'ok' => true, 'message' => $waiting.' ve frontě, nic nečeká déle než '.$max.' min'];
        }
        $minutes = (int) floor((now()->getTimestamp() - (int) $oldest) / 60);

        return ['key' => 'queue', 'ok' => false, 'message' => 'úloha čeká '.$minutes.' min ('.$waiting.' ve frontě) – stojí fronta?'];
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function log(): array
    {
        $file = storage_path('logs/laravel.log');
        $minutes = (int) config('watch.log_window_minutes', 15);
        if (! is_file($file)) {
            return ['key' => 'log', 'ok' => true, 'message' => 'bez souboru'];
        }
        $size = (int) filesize($file);
        $fh = fopen($file, 'r');
        if (! $fh) {
            return ['key' => 'log', 'ok' => true, 'message' => 'soubor nelze číst'];
        }
        fseek($fh, max(0, $size - 262144));
        $tail = (string) stream_get_contents($fh);
        fclose($fh);
        $since = now()->subMinutes($minutes);
        $hits = [];
        if (preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $tail, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (preg_match('/Scheduled command .*matplace:(health|watch-inbox)/', $m[3])) {
                    continue;   // the watch's own exit codes: reported by their own checks, not twice
                }
                if (Carbon::parse($m[1], config('app.timezone'))->gte($since)) {   // the log is stamped in the app's timezone
                    $hits[] = $m[2].' '.mb_substr(trim($m[3]), 0, 140);
                }
            }
        }
        if ($hits === []) {
            return ['key' => 'log', 'ok' => true, 'message' => 'žádná chyba za posledních '.$minutes.' min'];
        }

        return ['key' => 'log', 'ok' => false, 'message' => count($hits).' za posledních '.$minutes.' min – '.end($hits)];
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function backup(): array
    {
        $marker = (string) config('watch.backup_marker');
        $max = (int) config('watch.backup_max_hours', 30);
        if (! is_file($marker)) {
            return ['key' => 'backup', 'ok' => false, 'message' => 'záznam o záloze chybí ('.$marker.')'];
        }
        $at = Carbon::createFromTimestamp((int) filemtime($marker));
        $hours = (int) floor($at->diffInHours(now()));

        return ['key' => 'backup', 'ok' => $hours <= $max, 'message' => 'poslední záloha před '.$hours.' h ('.$at->timezone(config('app.timezone'))->format('j. n. H:i').')'];
    }

    /** @return array{key: string, ok: bool, message: string} */
    private function mailbox(): array
    {
        $state = Cache::get(MailboxWatch::CACHE);
        if (! is_array($state) || empty($state['checked_at'])) {
            return ['key' => 'mailbox', 'ok' => true, 'message' => 'zatím neběžel'];
        }
        $minutes = (int) floor(Carbon::parse((string) $state['checked_at'])->diffInMinutes(now()));
        $max = (int) config('watch.mailbox_stale_minutes', 30);

        return ['key' => 'mailbox', 'ok' => $minutes <= $max, 'message' => 'naposledy před '.$minutes.' min, nepřečtených od lidí: '.(int) ($state['unread_human'] ?? 0)];
    }

    private static function size(float|int $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 1).' GB' : round($bytes / 1048576).' MB';
    }
}
