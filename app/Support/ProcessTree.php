<?php

namespace App\Support;

/**
 * Kills a process with everything it started. The slicer runs under `xvfb-run` on the server (a shell script that
 * starts Xvfb and the AppImage, which starts the real binary), on Windows under `cmd /C`: a signal to the process PHP
 * started reaches only that wrapper and the slicer would go on for minutes on all cores.
 */
final class ProcessTree
{
    /** Every descendant of `$pid`, parents before their children (Linux: from /proc; elsewhere empty). */
    public static function descendants(int $pid): array
    {
        if (! is_dir('/proc/'.$pid)) {
            return [];
        }
        $children = [];
        foreach (glob('/proc/[0-9]*', GLOB_ONLYDIR | GLOB_NOSORT) ?: [] as $dir) {
            $stat = @file_get_contents($dir.'/stat');
            // "pid (comm) state ppid …": the name may hold spaces and brackets, so read after the last ")"
            if ($stat !== false && ($at = strrpos($stat, ')')) !== false && preg_match('/^\s+\S+\s+(\d+)/', substr($stat, $at + 1), $m)) {
                $children[(int) $m[1]][] = (int) basename($dir);
            }
        }
        $out = [];
        $queue = [$pid];
        while ($queue) {
            foreach ($children[array_shift($queue)] ?? [] as $child) {
                $out[] = $child;
                $queue[] = $child;
            }
        }

        return $out;
    }

    /** The name of a process (Linux), '' when it is gone. */
    public static function nameOf(int $pid): string
    {
        return trim((string) @file_get_contents('/proc/'.$pid.'/comm'));
    }

    public static function alive(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return str_contains((string) @shell_exec('tasklist /FI "PID eq '.$pid.'" /NH 2>NUL'), (string) $pid);
        }

        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        if ($stat !== false && ($at = strrpos($stat, ')')) !== false) {
            // a process that has ended but was not collected by its parent yet (a zombie) is not running any more
            return ! preg_match('/^\s+Z\b/', substr($stat, $at + 1));
        }

        return ! is_dir('/proc') && function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    /**
     * Kills `$pid` and its descendants. On Linux the descendants go first and the X server last: `xvfb-run` then ends
     * on its own and tidies up after itself (its Xvfb and the lock in /tmp); whatever is still there after `$grace`
     * seconds is killed as well.
     */
    public static function kill(int $pid, float $grace = 2.0): void
    {
        if ($pid <= 0) {
            return;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            @exec('taskkill /F /T /PID '.$pid.' 2>NUL');

            return;
        }
        $all = self::descendants($pid);
        foreach (array_reverse($all) as $child) {
            if (self::nameOf($child) !== 'Xvfb') {
                self::signal($child, 9);
            }
        }
        $until = microtime(true) + $grace;
        while (self::alive($pid) && microtime(true) < $until) {
            usleep(50000);
        }
        // the wrapper did not end by itself (or there was none): the rest, the process itself included
        foreach (array_reverse(array_merge([$pid], $all, self::descendants($pid))) as $p) {
            self::signal($p, 9);
        }
    }

    private static function signal(int $pid, int $signal): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, $signal);
        } else {
            @exec('kill -'.$signal.' '.$pid.' 2>/dev/null');
        }
    }
}
