<?php

namespace App\Support;

use App\Engines\Exceptions\SupersededException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;

/**
 * Lets the work of one job be given up while a child process of it runs (docs/AL.md §22). A job that can be superseded
 * (PrepareFarmOrder: the customer changed the choices, the order's slice_run moved on) says how to tell, and every
 * engine process started through run() is watched: once a second the question is asked, and when the answer is yes the
 * process is killed with everything it started (ProcessTree) and a SupersededException ends the job's work. Without a
 * watcher run() is PendingProcess::run() as it always was: the calculator and everything else are not touched.
 *
 * The job asks the question itself, in its own process: nothing is signalled from outside, so a restart of the queue
 * units (KillMode=mixed: the workers get SIGTERM and finish their job, their children are left alone until
 * TimeoutStopSec) changes nothing here, and no process id has to be shared between workers.
 */
final class RunGuard
{
    /** Seconds between two questions while a process runs. */
    private const ASK_EVERY = 1.0;

    /** @var (\Closure(): bool)|null */
    private static ?\Closure $superseded = null;

    /** @param  callable(): bool  $superseded  true once the work of this job is no longer wanted */
    public static function watch(callable $superseded): void
    {
        self::$superseded = $superseded(...);
    }

    public static function clear(): void
    {
        self::$superseded = null;
    }

    public static function watching(): bool
    {
        return self::$superseded !== null;
    }

    /** Is the work of the job that is being watched no longer wanted? False without a watcher. */
    public static function superseded(): bool
    {
        return self::$superseded !== null && (self::$superseded)() === true;
    }

    /** @throws SupersededException when the watched job's work is no longer wanted */
    public static function check(): void
    {
        if (self::superseded()) {
            throw new SupersededException('Superseded: the work is no longer wanted.');
        }
    }

    /**
     * Runs the command like PendingProcess::run(), but gives it up as soon as the watched job is superseded.
     *
     * @param  array<int, string>|string  $command
     *
     * @throws SupersededException
     */
    public static function run(PendingProcess $pending, array|string $command): ProcessResult
    {
        if (self::$superseded === null) {
            return $pending->run($command);
        }
        self::check();
        $process = $pending->start($command);
        $asked = microtime(true);
        while ($process->running()) {
            usleep(100000);
            if (method_exists($process, 'ensureNotTimedOut')) {
                $process->ensureNotTimedOut();   // a faked process of the tests has no clock
            }
            if (microtime(true) - $asked >= self::ASK_EVERY) {
                $asked = microtime(true);
                if (self::superseded()) {
                    if ($pid = (int) $process->id()) {
                        ProcessTree::kill($pid);
                    }
                    $process->stop(1);
                    throw new SupersededException('Superseded: the process was stopped, its work is no longer wanted.');
                }
            }
        }

        return $process->wait();
    }
}
