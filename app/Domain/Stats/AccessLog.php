<?php

namespace App\Domain\Stats;

/**
 * Reads the web server's access log (nginx "combined" format, plain or .gz) line by line. Used once in a while
 * to judge old visits and to list addresses that were not found; nothing of it is stored as it is — the
 * statistics keep no IP address and no User-Agent.
 *
 *   203.0.113.7 - - [03/Oct/2026:14:02:11 +0000] "GET /tools/vase?x=1 HTTP/2.0" 200 5123 "https://www.google.com/" "Mozilla/5.0 …"
 */
final class AccessLog
{
    private const LINE = '/^(\S+) \S+ \S+ \[([^\]]+)\] "([A-Z]+) (\S+)[^"]*" (\d{3}) \S+(?: "([^"]*)" "([^"]*)")?/';

    public int $lines = 0;

    public int $unreadable = 0;

    /** @param  list<string>  $files */
    public function __construct(private array $files) {}

    /** @return list<string> files matching a pattern like /var/log/nginx/access.log*, oldest first */
    public static function files(string $pattern): array
    {
        $files = array_values(array_filter(glob($pattern) ?: [], 'is_file'));
        usort($files, fn (string $a, string $b) => filemtime($a) <=> filemtime($b));

        return $files;
    }

    /**
     * @return \Generator<int, array{ip: string, time: int, method: string, path: string, status: int, referer: string, agent: string}>
     */
    public function read(): \Generator
    {
        foreach ($this->files as $file) {
            $handle = @fopen(str_ends_with($file, '.gz') ? 'compress.zlib://'.$file : $file, 'r');
            if (! $handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $this->lines++;
                if (! preg_match(self::LINE, $line, $m) || ! ($at = \DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O', $m[2]))) {
                    $this->unreadable++;

                    continue;
                }
                yield [
                    'ip' => $m[1], 'time' => $at->getTimestamp(), 'method' => $m[3],
                    'path' => '/'.trim((string) parse_url($m[4], PHP_URL_PATH), '/'),
                    'status' => (int) $m[5], 'referer' => $m[6] ?? '', 'agent' => $m[7] ?? '',
                ];
            }
            fclose($handle);
        }
    }

    /** A request for one of the page's own files: what a browser fetches after the page and a robot does not. */
    public static function isAsset(string $path): bool
    {
        return str_starts_with($path, '/build/') || preg_match('/\.(css|js|woff2?)$/', $path) === 1;
    }
}
